<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use Exception;
use ZipArchive;

class ThemeService
{
    private const SYSTEM_THEME_DIR = 'theme/';
    private const USER_THEME_DIR = '/storage/theme/';
    private const CONFIG_FILE = 'config.json';
    private const SETTING_PREFIX = 'theme_';
    private const SYSTEM_THEMES = ['Xboard', 'v2board', 'Misaka'];
    private const PUBLICATION_LOCK = 'theme_publication';
    private const PUBLICATION_MARKER = '.published-version';

    public function __construct()
    {
        $this->registerThemeViewPaths();
    }

    /**
     * Register theme view paths
     */
    private function registerThemeViewPaths(): void
    {
        $systemPath = base_path(self::SYSTEM_THEME_DIR);
        if (File::exists($systemPath)) {
            View::addNamespace('theme', $systemPath);
        }

        $userPath = base_path(self::USER_THEME_DIR);
        if (File::exists($userPath)) {
            View::prependNamespace('theme', $userPath);
        }
    }

    /**
     * Get theme view path
     */
    public function getThemeViewPath(string $theme): ?string
    {
        $themePath = $this->getThemePath($theme);
        if (!$themePath) {
            return null;
        }
        return $themePath . '/dashboard.blade.php';
    }

    /**
     * Get all available themes
     */
    public function getList(): array
    {
        $themes = [];

        // 获取系统主题
        $systemPath = base_path(self::SYSTEM_THEME_DIR);
        if (File::exists($systemPath)) {
            $themes = $this->getThemesFromPath($systemPath, false);
        }

        // 获取用户主题
        $userPath = base_path(self::USER_THEME_DIR);
        if (File::exists($userPath)) {
            $themes = array_merge($themes, $this->getThemesFromPath($userPath, true));
        }

        return $themes;
    }

    /**
     * Get themes from specified path
     */
    private function getThemesFromPath(string $path, bool $canDelete): array
    {
        return collect(File::directories($path))
            ->mapWithKeys(function ($dir) use ($canDelete) {
                $name = basename($dir);
                if (
                    !File::exists($dir . '/' . self::CONFIG_FILE) ||
                    !File::exists($dir . '/dashboard.blade.php')
                ) {
                    return [];
                }
                $config = $this->readConfigFile($name);
                if (!$config) {
                    return [];
                }

                $config['can_delete'] = $canDelete && $name !== admin_setting('current_theme');
                $config['is_system'] = !$canDelete;
                return [$name => $config];
            })->toArray();
    }

    /**
     * Upload new theme
     */
    public function upload(UploadedFile $file): bool
    {
        $zip = new ZipArchive;
        $tmpPath = storage_path('tmp/' . uniqid());

        try {
            if ($zip->open($file->path()) !== true) {
                throw new Exception('Invalid theme package');
            }

            $configEntry = collect(range(0, $zip->numFiles - 1))
                ->map(fn($i) => $zip->getNameIndex($i))
                ->first(fn($name) => basename($name) === self::CONFIG_FILE);

            if (!$configEntry) {
                throw new Exception('Theme config file not found');
            }

            $zip->extractTo($tmpPath);
            $zip->close();

            $sourcePath = $tmpPath . '/' . rtrim(dirname($configEntry), '.');
            $configFile = $sourcePath . '/' . self::CONFIG_FILE;

            if (!File::exists($configFile)) {
                throw new Exception('Theme config file not found');
            }

            $config = json_decode(File::get($configFile), true);
            if (empty($config['name'])) {
                throw new Exception('Theme name not configured');
            }

            if (in_array($config['name'], self::SYSTEM_THEMES)) {
                throw new Exception('Cannot upload theme with same name as system theme');
            }

            if (!File::exists($sourcePath . '/dashboard.blade.php')) {
                throw new Exception('Missing required theme file: dashboard.blade.php');
            }

            $userThemePath = base_path(self::USER_THEME_DIR);
            if (!File::exists($userThemePath)) {
                File::makeDirectory($userThemePath, 0755, true);
            }

            $targetPath = $userThemePath . $config['name'];
            if (File::exists($targetPath)) {
                $oldConfigFile = $targetPath . '/config.json';
                if (!File::exists($oldConfigFile)) {
                    throw new Exception('Existing theme missing config file');
                }
                $oldConfig = json_decode(File::get($oldConfigFile), true);
                $oldVersion = $oldConfig['version'] ?? '0.0.0';
                $newVersion = $config['version'] ?? '0.0.0';
                if (version_compare($newVersion, $oldVersion, '>')) {
                    $this->cleanupThemeFiles($config['name']);
                    File::deleteDirectory($targetPath);
                    File::copyDirectory($sourcePath, $targetPath);
                    // 更新主题时保留用户配置
                    $this->initConfig($config['name'], true);
                    return true;
                } else {
                    throw new Exception('Theme exists and not a newer version');
                }
            }

            File::copyDirectory($sourcePath, $targetPath);
            $this->initConfig($config['name']);

            return true;

        } catch (Exception $e) {
            throw $e;
        } finally {
            if (File::exists($tmpPath)) {
                File::deleteDirectory($tmpPath);
            }
        }
    }

    /**
     * Switch theme
     */
    public function switch(string|null $theme): bool
    {
        if ($theme === null) {
            return true;
        }

        try {
            return Cache::lock(self::PUBLICATION_LOCK, 60)
                ->block(10, fn() => $this->publishTheme($theme));

        } catch (\Throwable $e) {
            Log::error('Theme switch failed', ['theme' => $theme, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Resolve the configured frontend theme, including legacy installations.
     */
    public function resolveCurrentTheme(): string
    {
        return (string) (admin_setting('frontend_theme') ?: admin_setting('current_theme') ?: 'Xboard');
    }

    /**
     * Determine whether the public copy was fully published for this build.
     */
    public function isPublished(string $theme): bool
    {
        $targetPath = public_path('theme/' . $theme);
        $markerPath = $targetPath . '/' . self::PUBLICATION_MARKER;

        return $this->hasRequiredPublicFiles($theme, $targetPath)
            && File::isFile($markerPath)
            && hash_equals($this->publicationVersion($theme), trim(File::get($markerPath)));
    }

    /**
     * Publish a complete theme copy while the publication lock is held.
     */
    private function publishTheme(string $theme): bool
    {
        $themePath = $this->getThemePath($theme);
        if (!$themePath) {
            throw new Exception('Theme not found');
        }

        if (!File::isFile($themePath . '/dashboard.blade.php')) {
            throw new Exception('Theme view file not found');
        }

        $currentTheme = admin_setting('current_theme');
        $publicRoot = public_path('theme');
        File::ensureDirectoryExists($publicRoot, 0755, true);

        $suffix = Str::uuid()->toString();
        $targetPath = $publicRoot . '/' . $theme;
        $stagingPath = $publicRoot . '/.' . $theme . '.staging-' . $suffix;
        $backupPath = $publicRoot . '/.' . $theme . '.backup-' . $suffix;
        $hadTarget = File::isDirectory($targetPath);
        $swapped = false;
        $committed = false;

        try {
            if (!File::copyDirectory($themePath, $stagingPath)) {
                throw new Exception('Failed to copy theme files');
            }

            if (!$this->hasRequiredPublicFiles($theme, $stagingPath)) {
                throw new Exception('Published theme is incomplete');
            }

            if (File::put($stagingPath . '/' . self::PUBLICATION_MARKER, $this->publicationVersion($theme)) === false) {
                throw new Exception('Failed to write theme publication marker');
            }

            if ($hadTarget && !File::moveDirectory($targetPath, $backupPath)) {
                throw new Exception('Failed to preserve current theme files');
            }

            if (!File::moveDirectory($stagingPath, $targetPath)) {
                throw new Exception('Failed to publish theme files');
            }
            $swapped = true;

            DB::transaction(fn() => admin_setting([
                'frontend_theme' => $theme,
                'current_theme' => $theme,
            ]));
            $committed = true;

            try {
                if ($hadTarget) {
                    File::deleteDirectory($backupPath);
                }
                if ($currentTheme && $currentTheme !== $theme) {
                    $this->cleanupThemeFiles($currentTheme);
                }
                cache()->forget("theme_{$theme}_assets");
            } catch (\Throwable $e) {
                Log::warning('Theme published with cleanup warning', [
                    'theme' => $theme,
                    'error' => $e->getMessage(),
                ]);
            }

            return true;
        } catch (\Throwable $e) {
            if (File::isDirectory($stagingPath)) {
                File::deleteDirectory($stagingPath);
            }
            if (!$committed && $swapped && File::isDirectory($targetPath)) {
                File::deleteDirectory($targetPath);
            }
            if (!$committed && File::isDirectory($backupPath) && !File::moveDirectory($backupPath, $targetPath)) {
                Log::critical('Failed to restore theme after publication error', ['theme' => $theme]);
            }
            throw $e;
        }
    }

    private function hasRequiredPublicFiles(string $theme, string $path): bool
    {
        if (!File::isFile($path . '/config.json') || !File::isFile($path . '/dashboard.blade.php')) {
            return false;
        }

        if ($theme === 'Misaka') {
            $manifestPath = $path . '/assets/.vite/manifest.json';
            if (!File::isFile($manifestPath)) {
                return false;
            }
            $manifest = json_decode(File::get($manifestPath), true);
            $entry = is_array($manifest) ? ($manifest['src/main.ts']['file'] ?? null) : null;
            if (!is_string($entry) || !preg_match('/^app-[A-Za-z0-9_-]+\.js$/', $entry) || !File::isFile($path . '/assets/' . $entry)) {
                return false;
            }
        }

        $viewPath = $this->getThemeViewPath($theme);
        if (!$viewPath || !File::isFile($viewPath)) {
            return false;
        }

        preg_match_all('~assets/[A-Za-z0-9._/-]+\.(?:css|js)~', File::get($viewPath), $matches);
        foreach (array_unique($matches[0]) as $asset) {
            if (!File::isFile($path . '/' . $asset)) {
                return false;
            }
        }

        return true;
    }

    private function publicationVersion(string $theme): string
    {
        $themePath = $this->getThemePath($theme);
        $manifestPath = $themePath ? $themePath . '/assets/.vite/manifest.json' : null;

        return hash('sha256', implode('|', [
            (string) config('app.version', 'unknown'),
            $theme,
            $themePath && File::isFile($themePath . '/config.json') ? hash_file('sha256', $themePath . '/config.json') : '',
            $themePath && File::isFile($themePath . '/dashboard.blade.php') ? hash_file('sha256', $themePath . '/dashboard.blade.php') : '',
            $manifestPath && File::isFile($manifestPath) ? hash_file('sha256', $manifestPath) : '',
        ]));
    }

    /**
     * Delete theme
     */
    public function delete(string $theme): bool
    {
        try {
            if (in_array($theme, self::SYSTEM_THEMES)) {
                throw new Exception('System theme cannot be deleted');
            }

            if ($theme === admin_setting('current_theme')) {
                throw new Exception('Current theme cannot be deleted');
            }

            $themePath = base_path(self::USER_THEME_DIR . $theme);
            if (!File::exists($themePath)) {
                throw new Exception('Theme not found');
            }

            $this->cleanupThemeFiles($theme);
            File::deleteDirectory($themePath);
            admin_setting([self::SETTING_PREFIX . $theme => null]);
            return true;

        } catch (Exception $e) {
            Log::error('Theme deletion failed', ['theme' => $theme, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Check if theme exists
     */
    public function exists(string $theme): bool
    {
        return $this->getThemePath($theme) !== null;
    }

    /**
     * Get theme path
     */
    public function getThemePath(string $theme): ?string
    {
        $systemPath = base_path(self::SYSTEM_THEME_DIR . $theme);
        if (File::exists($systemPath)) {
            return $systemPath;
        }

        $userPath = base_path(self::USER_THEME_DIR . $theme);
        if (File::exists($userPath)) {
            return $userPath;
        }

        return null;
    }

    /**
     * Get theme config
     */
    public function getConfig(string $theme): ?array
    {
        $config = admin_setting(self::SETTING_PREFIX . $theme);
        if ($config === null) {
            $this->initConfig($theme);
            $config = admin_setting(self::SETTING_PREFIX . $theme);
        }

        // Older installations may still have theme settings cached as their
        // serialized JSON string. Normalize that value before Blade accesses it.
        if (is_string($config)) {
            $decoded = json_decode($config, true);
            $config = json_last_error() === JSON_ERROR_NONE ? $decoded : [];
        }

        return is_array($config) ? $config : [];
    }

    /**
     * Update theme config
     */
    public function updateConfig(string $theme, array $config): bool
    {
        try {
            if (!$this->getThemePath($theme)) {
                throw new Exception('Theme not found');
            }

            $schema = $this->readConfigFile($theme);
            if (!$schema) {
                throw new Exception('Invalid theme config file');
            }

            $validFields = collect($schema['configs'] ?? [])->pluck('field_name')->toArray();
            $validConfig = collect($config)
                ->only($validFields)
                ->toArray();

            $currentConfig = $this->getConfig($theme) ?? [];
            $newConfig = array_merge($currentConfig, $validConfig);

            admin_setting([self::SETTING_PREFIX . $theme => $newConfig]);
            return true;

        } catch (Exception $e) {
            Log::error('Config update failed', ['theme' => $theme, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Read theme config file
     */
    private function readConfigFile(string $theme): ?array
    {
        $themePath = $this->getThemePath($theme);
        if (!$themePath) {
            return null;
        }

        $file = $themePath . '/' . self::CONFIG_FILE;
        return File::exists($file) ? json_decode(File::get($file), true) : null;
    }

    /**
     * Clean up theme files including public directory
     */
    public function cleanupThemeFiles(string $theme): void
    {
        try {
            $publicThemePath = public_path('theme/' . $theme);
            if (File::exists($publicThemePath)) {
                File::deleteDirectory($publicThemePath);
                Log::info('Cleaned up public theme files', ['theme' => $theme, 'path' => $publicThemePath]);
            }

            $cacheKey = "theme_{$theme}_assets";
            if (cache()->has($cacheKey)) {
                cache()->forget($cacheKey);
                Log::info('Cleaned up theme cache', ['theme' => $theme, 'cache_key' => $cacheKey]);
            }

        } catch (Exception $e) {
            Log::warning('Failed to cleanup theme files', [
                'theme' => $theme,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Force refresh current theme public files
     */
    public function refreshCurrentTheme(): bool
    {
        $theme = null;

        try {
            $result = Cache::lock(self::PUBLICATION_LOCK, 60)->block(10, function () use (&$theme): bool {
                $theme = $this->resolveCurrentTheme();
                return $this->publishTheme($theme);
            });
            Log::info('Refreshed current theme files', ['theme' => $theme]);
            return $result;

        } catch (\Throwable $e) {
            Log::error('Failed to refresh current theme', [
                'theme' => $theme,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Initialize theme config
     * 
     * @param string $theme 主题名称
     * @param bool $preserveExisting 是否保留现有配置（更新主题时使用）
     */
    private function initConfig(string $theme, bool $preserveExisting = false): void
    {
        $config = $this->readConfigFile($theme);
        if (!$config) {
            return;
        }

        $defaults = collect($config['configs'] ?? [])
            ->mapWithKeys(fn($col) => [$col['field_name'] => $col['default_value'] ?? ''])
            ->toArray();

        if ($preserveExisting) {
            $existingConfig = admin_setting(self::SETTING_PREFIX . $theme) ?? [];
            $mergedConfig = array_merge($defaults, $existingConfig);
            admin_setting([self::SETTING_PREFIX . $theme => $mergedConfig]);
        } else {
            admin_setting([self::SETTING_PREFIX . $theme => $defaults]);
        }
    }
}
