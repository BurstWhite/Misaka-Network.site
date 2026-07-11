<?php

namespace Tests\Unit\Services;

use App\Services\ThemeService;
use Tests\TestCase;

class ThemeServiceTest extends TestCase
{
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
}
