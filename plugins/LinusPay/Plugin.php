<?php

namespace Plugin\LinusPay;

use App\Contracts\PaymentInterface;
use App\Exceptions\ApiException;
use App\Services\Plugin\AbstractPlugin;

class Plugin extends AbstractPlugin implements PaymentInterface
{
    public function boot(): void
    {
        $this->filter('available_payment_methods', function ($methods) {
            if ($this->getConfig('enabled', true)) {
                $methods['LinusPay'] = [
                    'name' => $this->getConfig('display_name', 'Linus Pay'),
                    'icon' => $this->getConfig('icon', '💳'),
                    'plugin_code' => $this->getPluginCode(),
                    'type' => 'plugin'
                ];
            }
            return $methods;
        });
    }

    public function form(): array
    {
        return [
            'gateway_url' => [
                'label' => '支付网关地址',
                'type' => 'string',
                'required' => true,
                'description' => 'Linus Pay 网关地址，默认 https://pay.linus.us.kg'
            ],
            'pid' => [
                'label' => '商户ID',
                'type' => 'string',
                'required' => true,
                'description' => '请填写 Linus Pay 商户ID'
            ],
            'merchant_private_key' => [
                'label' => '商户私钥',
                'type' => 'text',
                'required' => true,
                'description' => '商户后台生成的 RSA 私钥，用于 SHA256WithRSA 签名'
            ],
            'platform_public_key' => [
                'label' => '平台公钥',
                'type' => 'text',
                'required' => true,
                'description' => 'Linus Pay 平台公钥，用于异步通知验签'
            ],
            'type' => [
                'label' => '支付方式',
                'type' => 'string',
                'description' => 'alipay 或 wxpay；留空时进入 Linus Pay 收银台'
            ],
        ];
    }

    public function pay($order): array
    {
        $params = [
            'pid' => $this->getConfig('pid'),
            'out_trade_no' => $order['trade_no'],
            'notify_url' => $order['notify_url'],
            'return_url' => $order['return_url'],
            'name' => $order['trade_no'],
            'money' => sprintf('%.2f', $order['total_amount'] / 100),
            'timestamp' => time(),
        ];

        if ($paymentType = $this->getConfig('type')) {
            $params['type'] = $paymentType;
        }

        $params['sign'] = $this->sign($params);
        $params['sign_type'] = 'RSA';

        return [
            'type' => 1,
            'data' => rtrim($this->getConfig('gateway_url', 'https://pay.linus.us.kg'), '/') . '/api/pay/submit?' . http_build_query($params),
        ];
    }

    public function notify($params): array|bool
    {
        if (($params['trade_status'] ?? '') !== 'TRADE_SUCCESS') {
            return false;
        }

        if (empty($params['sign']) || !$this->verify($params, $params['sign'])) {
            return false;
        }

        return [
            'trade_no' => $params['out_trade_no'],
            'callback_no' => $params['trade_no'],
        ];
    }

    private function sign(array $params): string
    {
        $key = $this->getPrivateKey((string) $this->getConfig('merchant_private_key'));
        if (!$key) {
            throw new ApiException('Linus Pay 商户私钥无效');
        }

        $signature = '';
        $signed = openssl_sign($this->buildSignContent($params), $signature, $key, OPENSSL_ALGO_SHA256);
        if (!$signed) {
            throw new ApiException('Linus Pay 签名失败');
        }

        return base64_encode($signature);
    }

    private function verify(array $params, string $sign): bool
    {
        $key = $this->getPublicKey((string) $this->getConfig('platform_public_key'));
        if (!$key) {
            throw new ApiException('Linus Pay 平台公钥无效');
        }

        return openssl_verify($this->buildSignContent($params), base64_decode($sign), $key, OPENSSL_ALGO_SHA256) === 1;
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

    private function getPrivateKey(string $key)
    {
        $normalized = trim(str_replace(["\r\n", "\r"], "\n", $key));
        if (str_contains($normalized, 'BEGIN')) {
            return openssl_pkey_get_private($normalized);
        }

        $payload = str_replace(["\n", ' '], '', $normalized);
        foreach (['PRIVATE KEY', 'RSA PRIVATE KEY'] as $type) {
            $resource = openssl_pkey_get_private("-----BEGIN {$type}-----\n" . chunk_split($payload, 64, "\n") . "-----END {$type}-----");
            if ($resource) {
                return $resource;
            }
        }

        return false;
    }

    private function getPublicKey(string $key)
    {
        $normalized = trim(str_replace(["\r\n", "\r"], "\n", $key));
        if (str_contains($normalized, 'BEGIN')) {
            return openssl_pkey_get_public($normalized);
        }

        $payload = str_replace(["\n", ' '], '', $normalized);
        foreach (['PUBLIC KEY', 'RSA PUBLIC KEY'] as $type) {
            $resource = openssl_pkey_get_public("-----BEGIN {$type}-----\n" . chunk_split($payload, 64, "\n") . "-----END {$type}-----");
            if ($resource) {
                return $resource;
            }
        }

        return false;
    }
}
