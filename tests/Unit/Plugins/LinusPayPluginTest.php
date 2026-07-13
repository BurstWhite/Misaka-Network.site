<?php

namespace Tests\Unit\Plugins;

use PHPUnit\Framework\TestCase;
use Plugin\LinusPay\Plugin;

class LinusPayPluginTest extends TestCase
{
    private string $privateKey;
    private string $publicKey;
    private Plugin $plugin;

    protected function setUp(): void
    {
        parent::setUp();

        $keyPair = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        $privateKey = '';
        openssl_pkey_export($keyPair, $privateKey);
        $this->privateKey = $privateKey;
        $this->publicKey = openssl_pkey_get_details($keyPair)['key'];

        $this->plugin = new Plugin('linus_pay');
        $this->plugin->setConfig([
            'gateway_url' => 'https://pay.linus.us.kg',
            'pid' => '1001',
            'merchant_private_key' => $this->privateKey,
            'platform_public_key' => $this->publicKey,
            'type' => 'alipay',
        ]);
    }

    public function test_pay_builds_linus_pay_submit_url_with_rsa_signature(): void
    {
        $result = $this->plugin->pay([
            'trade_no' => 'ORDER123',
            'total_amount' => 1299,
            'notify_url' => 'https://example.com/api/v1/guest/payment/notify/LinusPay/payment-uuid',
            'return_url' => 'https://example.com/#/order/ORDER123',
        ]);

        $this->assertSame(1, $result['type']);
        $this->assertStringStartsWith('https://pay.linus.us.kg/api/pay/submit?', $result['data']);

        parse_str(parse_url($result['data'], PHP_URL_QUERY), $params);

        $this->assertSame('1001', $params['pid']);
        $this->assertSame('alipay', $params['type']);
        $this->assertSame('ORDER123', $params['out_trade_no']);
        $this->assertSame('ORDER123', $params['name']);
        $this->assertSame('12.99', $params['money']);
        $this->assertSame('RSA', $params['sign_type']);
        $this->assertArrayHasKey('timestamp', $params);

        $this->assertRsaSignatureIsValid($params, $params['sign'], $this->publicKey);
    }

    public function test_notify_accepts_successful_signed_callback(): void
    {
        $params = [
            'pid' => '1001',
            'trade_no' => 'LINUS202607040001',
            'out_trade_no' => 'ORDER123',
            'api_trade_no' => 'API202607040001',
            'type' => 'alipay',
            'trade_status' => 'TRADE_SUCCESS',
            'money' => '12.99',
            'timestamp' => (string) time(),
        ];
        $params['sign'] = $this->signParams($params, $this->privateKey);
        $params['sign_type'] = 'RSA';

        $this->assertSame([
            'trade_no' => 'ORDER123',
            'callback_no' => 'LINUS202607040001',
        ], $this->plugin->notify($params));
    }

    public function test_notify_rejects_non_success_status(): void
    {
        $params = [
            'pid' => '1001',
            'trade_no' => 'LINUS202607040001',
            'out_trade_no' => 'ORDER123',
            'trade_status' => 'WAIT_BUYER_PAY',
            'timestamp' => (string) time(),
        ];
        $params['sign'] = $this->signParams($params, $this->privateKey);
        $params['sign_type'] = 'RSA';

        $this->assertFalse($this->plugin->notify($params));
    }

    public function test_notify_rejects_invalid_signature(): void
    {
        $params = [
            'pid' => '1001',
            'trade_no' => 'LINUS202607040001',
            'out_trade_no' => 'ORDER123',
            'trade_status' => 'TRADE_SUCCESS',
            'money' => '12.99',
            'timestamp' => (string) time(),
        ];
        $params['sign'] = $this->signParams($params, $this->privateKey);
        $params['sign_type'] = 'RSA';
        $params['money'] = '99.99';

        $this->assertFalse($this->plugin->notify($params));
    }

    private function assertRsaSignatureIsValid(array $params, string $signature, string $publicKey): void
    {
        $verified = openssl_verify(
            $this->buildSignContent($params),
            base64_decode($signature, true),
            openssl_pkey_get_public($publicKey),
            OPENSSL_ALGO_SHA256
        );

        $this->assertSame(1, $verified);
    }

    private function signParams(array $params, string $privateKey): string
    {
        $signature = '';
        openssl_sign($this->buildSignContent($params), $signature, openssl_pkey_get_private($privateKey), OPENSSL_ALGO_SHA256);

        return base64_encode($signature);
    }

    private function buildSignContent(array $params): string
    {
        unset($params['sign'], $params['sign_type']);

        $params = array_filter($params, function ($value) {
            return $value !== null && $value !== '' && !is_array($value);
        });

        ksort($params);

        return urldecode(http_build_query($params));
    }
}
