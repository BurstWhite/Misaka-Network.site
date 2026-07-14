<?php

namespace Tests\Unit\Services;

use App\Services\ThemeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ThemeServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_misaka_is_discovered_as_a_system_theme(): void
    {
        $themes = app(ThemeService::class)->getList();

        $this->assertArrayHasKey('Misaka', $themes);
        $this->assertTrue($themes['Misaka']['is_system']);
        $this->assertFalse($themes['Misaka']['can_delete']);
        $this->assertSame('1.0.0', $themes['Misaka']['version']);
    }

    public function test_misaka_dashboard_view_exists(): void
    {
        $path = app(ThemeService::class)->getThemeViewPath('Misaka');

        $this->assertNotNull($path);
        $this->assertFileExists($path);
        $this->assertStringEndsWith('theme/Misaka/dashboard.blade.php', $path);
    }

    public function test_misaka_runtime_config_avoids_complex_blade_json_directive(): void
    {
        $path = app(ThemeService::class)->getThemeViewPath('Misaka');
        $view = file_get_contents($path);

        $this->assertStringContainsString('{!! $runtime_config_json !!}', $view);
        $this->assertStringNotContainsString('@json(', $view);
    }

    public function test_refresh_uses_frontend_theme_and_synchronizes_public_files(): void
    {
        $originalPublicPath = public_path();
        $testPublicPath = storage_path('framework/testing/theme-service-' . uniqid());
        $this->app->usePublicPath($testPublicPath);

        try {
            admin_setting([
                'frontend_theme' => 'Misaka',
                'current_theme' => 'Xboard',
            ]);
            File::ensureDirectoryExists($testPublicPath . '/theme/Misaka');
            File::put($testPublicPath . '/theme/Misaka/stale.js', 'stale');

            $service = app(ThemeService::class);
            $this->assertTrue($service->refreshCurrentTheme());
            $this->assertFileExists($testPublicPath . '/theme/Misaka/assets/app.js');
            $this->assertFileDoesNotExist($testPublicPath . '/theme/Misaka/stale.js');
            $this->assertTrue($service->isPublished('Misaka'));
            File::delete($testPublicPath . '/theme/Misaka/assets/app.js');
            $this->assertFalse($service->isPublished('Misaka'));
            $this->assertSame('Misaka', admin_setting('frontend_theme'));
            $this->assertSame('Misaka', admin_setting('current_theme'));
        } finally {
            $this->app->usePublicPath($originalPublicPath);
            File::deleteDirectory($testPublicPath);
        }
    }

    public function test_failed_publication_preserves_existing_theme(): void
    {
        $originalPublicPath = public_path();
        $testPublicPath = storage_path('framework/testing/theme-service-' . uniqid());
        $this->app->usePublicPath($testPublicPath);
        File::ensureDirectoryExists($testPublicPath . '/theme/Misaka');
        File::put($testPublicPath . '/theme/Misaka/app.js', 'working');
        admin_setting(['frontend_theme' => 'Misaka', 'current_theme' => 'Misaka']);

        $files = File::getFacadeRoot();
        File::partialMock()->shouldReceive('copyDirectory')->once()->andReturnUsing(function ($source, $destination): bool {
            mkdir($destination, 0755, true);
            file_put_contents($destination . '/partial.js', 'broken');
            return false;
        });

        try {
            app(ThemeService::class)->switch('Misaka');
            $this->fail('Theme publication should fail.');
        } catch (\Throwable $e) {
            $this->assertSame('Failed to copy theme files', $e->getMessage());
            $this->assertSame('working', file_get_contents($testPublicPath . '/theme/Misaka/app.js'));
            $this->assertSame([], glob($testPublicPath . '/theme/.Misaka.*'));
            $this->assertSame('Misaka', admin_setting('frontend_theme'));
            $this->assertSame('Misaka', admin_setting('current_theme'));
        } finally {
            File::swap($files);
            $this->app->usePublicPath($originalPublicPath);
            File::deleteDirectory($testPublicPath);
        }
    }

    public function test_database_failure_restores_previous_public_theme(): void
    {
        $originalPublicPath = public_path();
        $testPublicPath = storage_path('framework/testing/theme-service-' . uniqid());
        $this->app->usePublicPath($testPublicPath);

        try {
            File::ensureDirectoryExists($testPublicPath . '/theme/Misaka');
            File::put($testPublicPath . '/theme/Misaka/app.js', 'working');
            admin_setting(['frontend_theme' => 'Misaka', 'current_theme' => 'Misaka']);

            $database = DB::getFacadeRoot();
            DB::partialMock()->shouldReceive('transaction')->once()->andThrow(new \RuntimeException('database unavailable'));

            try {
                app(ThemeService::class)->switch('Misaka');
                $this->fail('Theme publication should fail.');
            } catch (\RuntimeException $e) {
                $this->assertSame('database unavailable', $e->getMessage());
            } finally {
                DB::swap($database);
            }

            $this->assertSame('working', file_get_contents($testPublicPath . '/theme/Misaka/app.js'));
            $this->assertSame([], glob($testPublicPath . '/theme/.Misaka.*'));
            $this->assertSame('Misaka', admin_setting('frontend_theme'));
            $this->assertSame('Misaka', admin_setting('current_theme'));
        } finally {
            $this->app->usePublicPath($originalPublicPath);
            File::deleteDirectory($testPublicPath);
        }
    }

    public function test_resolver_falls_back_to_legacy_current_theme(): void
    {
        admin_setting(['frontend_theme' => null, 'current_theme' => 'Misaka']);

        $this->assertSame('Misaka', app(ThemeService::class)->resolveCurrentTheme());
    }
}
