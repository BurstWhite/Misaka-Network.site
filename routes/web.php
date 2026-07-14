<?php

use App\Services\ThemeService;
use App\Services\UpdateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/uploads/knowledge/{year}/{month}/{filename}', function (string $year, string $month, string $filename) {
    $path = base_path(".docker/.data/uploads/knowledge/{$year}/{$month}/{$filename}");

    if (!File::isFile($path)) {
        abort(404);
    }

    return response()->file($path, [
        'Cache-Control' => 'public, max-age=31536000, immutable',
    ]);
})->where([
    'year' => '[0-9]{4}',
    'month' => '[0-9]{2}',
    'filename' => '[A-Za-z0-9._-]+',
]);

Route::get('/uploads/notice/{year}/{month}/{filename}', function (string $year, string $month, string $filename) {
    $path = base_path(".docker/.data/uploads/notice/{$year}/{$month}/{$filename}");

    if (!File::isFile($path)) {
        abort(404);
    }

    return response()->file($path, [
        'Cache-Control' => 'public, max-age=31536000, immutable',
    ]);
})->where([
    'year' => '[0-9]{4}',
    'month' => '[0-9]{2}',
    'filename' => '[A-Za-z0-9._-]+',
]);

Route::get('/', function (Request $request) {
    if (admin_setting('app_url') && admin_setting('safe_mode_enable', 0)) {
        $requestHost = $request->getHost();
        $configHost = parse_url(admin_setting('app_url'), PHP_URL_HOST);
        
        if ($requestHost !== $configHost) {
            abort(403);
        }
    }

    $themeService = new ThemeService();
    $theme = $themeService->resolveCurrentTheme();

    try {
        $themeViewPath = $themeService->getThemeViewPath($theme);
        if (!$themeService->exists($theme) || !$themeViewPath || !File::isFile($themeViewPath)) {
            if ($theme !== 'Xboard') {
                Log::warning('Theme not found, switching to default theme', ['theme' => $theme]);
                $theme = 'Xboard';
                admin_setting(['frontend_theme' => $theme]);
            }
        }

        $themeViewPath = $themeService->getThemeViewPath($theme);
        if (!$themeViewPath || !File::isFile($themeViewPath)) {
            throw new Exception('主题视图文件不存在');
        }

        if (admin_setting('current_theme') !== $theme || !$themeService->isPublished($theme)) {
            if (!$themeService->refreshCurrentTheme()) {
                throw new Exception('主题初始化失败');
            }
            $theme = $themeService->resolveCurrentTheme();
            Log::info('Theme initialized in public directory', ['theme' => $theme]);
        }

        $themeConfig = $themeService->getConfig($theme) ?? [];
        $runtimeVersion = app(UpdateService::class)->getCurrentVersion();
        if (str_ends_with($runtimeVersion, '-unknown')) {
            $runtimeVersion = (string) config('app.version', $runtimeVersion);
        }
        $runtimeConfig = [
            'apiBase' => '/api/v1',
            'assetsBase' => "/theme/{$theme}/assets",
            'appName' => admin_setting('app_name', 'Xboard'),
            'description' => admin_setting('app_description', 'Xboard is best'),
            'logo' => admin_setting('logo'),
            'version' => $runtimeVersion,
            'supportedLocales' => ['zh-CN', 'zh-TW', 'en-US', 'ja-JP', 'vi-VN', 'ko-KR', 'ru-RU', 'fa-IR'],
            'theme' => [
                'primaryColor' => $themeConfig['primary_color'] ?? '#3155ee',
                'backgroundUrl' => $themeConfig['background_url'] ?? '',
            ],
            // Keep the runtime contract object-shaped; the frontend treats this as a feature map.
            'features' => new \stdClass(),
        ];

        $entryAsset = 'app.js';
        if ($theme === 'Misaka') {
            $manifestPath = $themeService->getThemePath($theme) . '/assets/.vite/manifest.json';
            $manifest = File::isFile($manifestPath) ? json_decode(File::get($manifestPath), true) : null;
            $entryAsset = is_array($manifest) ? ($manifest['src/main.ts']['file'] ?? null) : null;
            if (!is_string($entryAsset) || !preg_match('/^app-[A-Za-z0-9_-]+\.js$/', $entryAsset)) {
                throw new Exception('Misaka 主题入口文件无效');
            }
        }

        $renderParams = [
            'title' => admin_setting('app_name', 'Xboard'),
            'theme' => $theme,
            'version' => $runtimeConfig['version'],
            'description' => admin_setting('app_description', 'Xboard is best'),
            'logo' => admin_setting('logo'),
            'theme_config' => $themeConfig,
            'entry_asset' => $entryAsset,
            // Encode outside the Blade directive parser. This also keeps closing
            // script tags and quotes from custom settings inert inside JavaScript.
            'runtime_config_json' => json_encode(
                $runtimeConfig,
                JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE
            ),
        ];
        $html = view('theme::' . $theme . '.dashboard', $renderParams)->render();
        return response($html)
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    } catch (\Throwable $e) {
        Log::error('Theme rendering failed', [
            'theme' => $theme,
            'exception' => get_class($e),
            'error' => $e->getMessage()
        ]);
        abort(500, '主题加载失败');
    }
});

//TODO:: 兼容
Route::get('/' . admin_setting('secure_path', admin_setting('frontend_admin_path', hash('crc32b', config('app.key')))), function () {
    return view('admin', [
        'title' => admin_setting('app_name', 'XBoard'),
        'theme_sidebar' => admin_setting('frontend_theme_sidebar', 'light'),
        'theme_header' => admin_setting('frontend_theme_header', 'dark'),
        'theme_color' => admin_setting('frontend_theme_color', 'default'),
        'background_url' => admin_setting('frontend_background_url'),
        'version' => app(UpdateService::class)->getCurrentVersion(),
        'logo' => admin_setting('logo'),
        'secure_path' => admin_setting('secure_path', admin_setting('frontend_admin_path', hash('crc32b', config('app.key'))))
    ]);
});

Route::get('/' . (admin_setting('subscribe_path', 's')) . '/{token}', [\App\Http\Controllers\V1\Client\ClientController::class, 'subscribe'])
    ->middleware('client')
    ->name('client.subscribe');
