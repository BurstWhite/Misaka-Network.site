<?php

namespace Tests\Unit\Rules;

use App\Models\SubscribeTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

class DefaultClashTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_template_uses_valid_loyalsoldier_rule_providers(): void
    {
        $config = Yaml::parseFile(resource_path('rules/default.clash.yaml'));
        $providers = $config['rule-providers'];
        $expectedBehaviors = [
            'applications' => 'classical',
            'private' => 'domain',
            'reject' => 'domain',
            'icloud' => 'domain',
            'apple' => 'domain',
            'google' => 'domain',
            'proxy' => 'domain',
            'direct' => 'domain',
            'lancidr' => 'ipcidr',
            'cncidr' => 'ipcidr',
            'telegramcidr' => 'ipcidr',
        ];

        foreach ($expectedBehaviors as $name => $behavior) {
            $this->assertSame('http', $providers[$name]['type']);
            $this->assertSame($behavior, $providers[$name]['behavior']);
            $this->assertSame(86400, $providers[$name]['interval']);
            $this->assertSame(
                "https://cdn.jsdelivr.net/gh/Loyalsoldier/clash-rules@release/{$name}.txt",
                $providers[$name]['url']
            );
        }

        foreach ($config['rules'] as $rule) {
            if (str_starts_with($rule, 'RULE-SET,')) {
                $this->assertArrayHasKey(explode(',', $rule, 3)[1], $providers);
            }
        }

        $this->assertSame('$app_name', $config['proxy-groups'][0]['name']);
        $this->assertSame('MATCH,$app_name', end($config['rules']));
    }

    public function test_builtin_app_template_uses_the_same_providers_with_select_policy(): void
    {
        $subscription = Yaml::parseFile(resource_path('rules/default.clash.yaml'));
        $app = Yaml::parseFile(resource_path('rules/app.clash.yaml'));

        $this->assertSame($subscription['rule-providers'], $app['rule-providers']);
        $this->assertSame('SELECT', $app['proxy-groups'][0]['name']);
        $this->assertSame('MATCH,SELECT', end($app['rules']));

        foreach ($app['rules'] as $rule) {
            if (str_starts_with($rule, 'RULE-SET,')) {
                $this->assertArrayHasKey(explode(',', $rule, 3)[1], $app['rule-providers']);
            }
        }
    }

    public function test_upgrade_migration_updates_only_legacy_defaults(): void
    {
        $legacy = File::get(base_path('tests/Fixtures/rules/default.clash.legacy.yaml'));
        $newDefault = File::get(resource_path('rules/default.clash.yaml'));
        $custom = "proxies: []\nproxy-groups: []\nrules: []\n";

        $this->assertSame(
            '7f8bb209fcc52e897ad79af3cb46ca0cc8ad42e10fa857632f089ef8e652b9b5',
            hash('sha256', $legacy)
        );

        DB::table('v2_subscribe_templates')
            ->where('name', 'clash')
            ->update(['content' => $legacy]);
        DB::table('v2_subscribe_templates')
            ->whereIn('name', ['clashmeta', 'stash'])
            ->update(['content' => $custom]);
        Cache::store()->forever('subscribe_template:v2:clash', $legacy);
        Cache::store()->forever('subscribe_template:v2:clashmeta', $custom);
        Cache::store()->forever('subscribe_template:clash', $legacy);

        $migration = require database_path('migrations/2026_07_14_000002_upgrade_default_clash_rule_providers.php');
        $migration->up();

        $contents = DB::table('v2_subscribe_templates')
            ->whereIn('name', ['clash', 'clashmeta', 'stash'])
            ->pluck('content', 'name');

        $this->assertSame($newDefault, $contents['clash']);
        $this->assertSame($custom, $contents['clashmeta']);
        $this->assertSame($custom, $contents['stash']);
        $this->assertFalse(Cache::store()->has('subscribe_template:v2:clash'));
        $this->assertTrue(Cache::store()->has('subscribe_template:v2:clashmeta'));
        $this->assertSame($newDefault, SubscribeTemplate::getContent('clash'));
    }
}
