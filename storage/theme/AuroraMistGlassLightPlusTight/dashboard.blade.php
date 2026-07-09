@php
    $themeName = $theme ?? 'AuroraMistGlassLightPlusTight';
    $themeConfig = $theme_config ?? [];

    // AuroraMistGlassLightPlusTight is an overlay theme. It reuses the bundled XBoard SPA assets
    // from the built-in Xboard theme, then enables its own CSS only when the page is in light mode.
    try {
        $file = \Illuminate\Support\Facades\File::class;
        $publicAssetsPath = public_path('theme/' . $themeName . '/assets');
        $systemAssetsPath = base_path('theme/Xboard/assets');
        if (!$file::exists($publicAssetsPath . '/umi.js') && $file::exists($systemAssetsPath . '/umi.js')) {
            if (!$file::isDirectory($publicAssetsPath)) {
                $file::makeDirectory($publicAssetsPath, 0755, true, true);
            }
            $file::copyDirectory($systemAssetsPath, $publicAssetsPath);
        }
    } catch (\Throwable $e) {
        // Do not interrupt rendering; the page will show a friendly asset warning if umi.js is unavailable.
    }

    $allowedThemeColors = ['default', 'blue', 'black', 'darkblue'];
    $themeColor = $themeConfig['theme_color'] ?? 'blue';
    if (!in_array($themeColor, $allowedThemeColors, true)) {
        $themeColor = 'blue';
    }

    $accent = $themeConfig['accent_color'] ?? '#6D92E8';
    if (!is_string($accent) || !preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $accent)) {
        $accent = '#6D92E8';
    }

    $secondary = $themeConfig['secondary_color'] ?? '#72C7C0';
    if (!is_string($secondary) || !preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $secondary)) {
        $secondary = '#72C7C0';
    }

    $hexToRgb = function ($hex) {
        $value = ltrim($hex, '#');
        if (strlen($value) === 3) {
            $value = $value[0] . $value[0] . $value[1] . $value[1] . $value[2] . $value[2];
        }
        return hexdec(substr($value, 0, 2)) . ', ' . hexdec(substr($value, 2, 2)) . ', ' . hexdec(substr($value, 4, 2));
    };

    $glassStrength = $themeConfig['glass_strength'] ?? 'medium';
    $glassOpacityMap = ['solid' => '0.94', 'medium' => '0.86', 'airy' => '0.78'];
    $glassOpacity = $glassOpacityMap[$glassStrength] ?? $glassOpacityMap['medium'];

    $radiusScale = $themeConfig['radius_scale'] ?? 'round';
    $radiusMap = ['balanced' => '16px', 'round' => '18px', 'soft' => '22px'];
    $radius = $radiusMap[$radiusScale] ?? $radiusMap['round'];

    $visualDepth = $themeConfig['visual_depth'] ?? 'plus';
    $allowedDepth = ['calm', 'liquid', 'plus', 'bloom'];
    if (!in_array($visualDepth, $allowedDepth, true)) {
        $visualDepth = 'plus';
    }

    $backgroundUrl = trim((string)($themeConfig['background_url'] ?? ''));
    $backgroundUrlJson = json_encode($backgroundUrl, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    $dashboardPath = base_path("storage/theme/{$themeName}/dashboard.blade.php");
    $assetVersion = is_file($dashboardPath) ? substr(md5_file($dashboardPath), 0, 12) : $version;
@endphp
<!doctype html>
<html lang="zh-CN" data-amg-theme="light-only-plus" data-amg-active="off" data-amg-scheme="default">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,minimum-scale=1,user-scalable=no" />
    <meta name="theme-color" content="{{ $accent }}" />
    <meta name="color-scheme" content="light dark" />
    <title>{{ $title }}</title>
    <link rel="icon" href="/theme/{{ $themeName }}/assets/images/favicon.svg" type="image/svg+xml" />
    <link rel="preload" href="/theme/{{ $themeName }}/assets/umi.js?v={{ $assetVersion }}" as="script" crossorigin />
    <link id="amg-light-css" rel="stylesheet" href="/theme/{{ $themeName }}/assets/aurora-mist-light-plus.css?v={{ $assetVersion }}" />
    <style>
        :root {
            --amg-accent: {{ $accent }};
            --amg-accent-rgb: {{ $hexToRgb($accent) }};
            --amg-accent-2: {{ $secondary }};
            --amg-accent-2-rgb: {{ $hexToRgb($secondary) }};
            --amg-glass-opacity: .96;
            --amg-radius: {{ $radius }};
            --amg-overlay-revision: "soft-card-20260709-actual-theme";
        }
        @if($backgroundUrl !== '')
        html[data-amg-active="light"] body.amg-custom-bg::after {
            background-image:
                linear-gradient(135deg, rgba(248, 251, 255, .86), rgba(239, 248, 247, .66)),
                url({!! $backgroundUrlJson !!});
        }
        @endif
        html[data-amg-active="light"] .n-modal-mask,
        html:not(.dark) .n-modal-mask {
            background-color: rgba(15, 23, 42, .16) !important;
            backdrop-filter: none !important;
            -webkit-backdrop-filter: none !important;
        }
        html[data-amg-active="light"] .n-modal-container .n-modal,
        html[data-amg-active="light"] .n-modal-container .n-card,
        html[data-amg-active="light"] .n-modal-container .n-dialog,
        html:not(.dark) .n-modal-container .n-modal,
        html:not(.dark) .n-modal-container .n-card,
        html:not(.dark) .n-modal-container .n-dialog {
            --n-color: rgba(255, 255, 255, .96) !important;
            --n-color-modal: rgba(255, 255, 255, .96) !important;
            --n-text-color: #0f172a !important;
            --n-title-text-color: #0f172a !important;
            --n-border-color: rgba(203, 213, 225, .64) !important;
            background-color: rgba(255, 255, 255, .96) !important;
            background-image:
                linear-gradient(135deg, rgba(255, 255, 255, .98), rgba(248, 250, 252, .92) 42%, rgba(207, 250, 254, .45) 100%),
                radial-gradient(circle at 84% 24%, rgba(187, 247, 208, .30), transparent 42%) !important;
            border: 1px solid rgba(203, 213, 225, .64) !important;
            box-shadow: 0 18px 42px rgba(15, 23, 42, .10) !important;
            color: #0f172a !important;
            opacity: 1 !important;
            text-shadow: none !important;
            backdrop-filter: none !important;
            -webkit-backdrop-filter: none !important;
        }
        html[data-amg-active="light"] .n-modal-container .n-card::before,
        html[data-amg-active="light"] .n-modal-container .n-card::after,
        html[data-amg-active="light"] .n-modal-container .n-dialog::before,
        html[data-amg-active="light"] .n-modal-container .n-dialog::after,
        html:not(.dark) .n-modal-container .n-card::before,
        html:not(.dark) .n-modal-container .n-card::after,
        html:not(.dark) .n-modal-container .n-dialog::before,
        html:not(.dark) .n-modal-container .n-dialog::after {
            content: none !important;
            display: none !important;
        }
        html[data-amg-active="light"] .n-modal-container .n-card *,
        html[data-amg-active="light"] .n-modal-container .n-dialog *,
        html:not(.dark) .n-modal-container .n-card *,
        html:not(.dark) .n-modal-container .n-dialog * {
            text-shadow: none !important;
            backdrop-filter: none !important;
            -webkit-backdrop-filter: none !important;
        }
        html[data-amg-active="light"] .n-modal-container .n-card-header,
        html[data-amg-active="light"] .n-modal-container .n-card__content,
        html[data-amg-active="light"] .n-modal-container .n-card__footer,
        html[data-amg-active="light"] .n-modal-container .n-dialog__content,
        html[data-amg-active="light"] .n-modal-container .markdown-body,
        html:not(.dark) .n-modal-container .n-card-header,
        html:not(.dark) .n-modal-container .n-card__content,
        html:not(.dark) .n-modal-container .n-card__footer,
        html:not(.dark) .n-modal-container .n-dialog__content,
        html:not(.dark) .n-modal-container .markdown-body {
            background: transparent !important;
            color: #0f172a !important;
        }
        html[data-amg-active="light"] .n-modal-container [class*="bg-gray-800"],
        html[data-amg-active="light"] .n-modal-container [class*="bg-gray-900"],
        html[data-amg-active="light"] .n-modal-container [class*="bg-slate-"],
        html[data-amg-active="light"] .n-modal-container [class*="bg-black"],
        html[data-amg-active="light"] .n-modal-container [class*="bg-dark"],
        html:not(.dark) .n-modal-container [class*="bg-gray-800"],
        html:not(.dark) .n-modal-container [class*="bg-gray-900"],
        html:not(.dark) .n-modal-container [class*="bg-slate-"],
        html:not(.dark) .n-modal-container [class*="bg-black"],
        html:not(.dark) .n-modal-container [class*="bg-dark"] {
            background: transparent !important;
            color: #0f172a !important;
        }
        html[data-amg-active="light"] .n-modal-container [class*="text-white"],
        html[data-amg-active="light"] .n-modal-container [class*="text-gray-100"],
        html[data-amg-active="light"] .n-modal-container [class*="text-gray-200"],
        html[data-amg-active="light"] .n-modal-container [class*="color-#f8f9fa"],
        html:not(.dark) .n-modal-container [class*="text-white"],
        html:not(.dark) .n-modal-container [class*="text-gray-100"],
        html:not(.dark) .n-modal-container [class*="text-gray-200"],
        html:not(.dark) .n-modal-container [class*="color-#f8f9fa"] {
            color: #0f172a !important;
        }
        html[data-amg-active="light"] .n-modal-container .markdown-body table,
        html[data-amg-active="light"] .n-modal-container .markdown-body th,
        html[data-amg-active="light"] .n-modal-container .markdown-body td,
        html:not(.dark) .n-modal-container .markdown-body table,
        html:not(.dark) .n-modal-container .markdown-body th,
        html:not(.dark) .n-modal-container .markdown-body td {
            background-color: rgba(255, 255, 255, .92) !important;
            color: #0f172a !important;
        }

        .carousel-img.xboard-notice-hero,
        .carousel-img {
            background: transparent !important;
        }

        .carousel-img > div:last-child,
        .xboard-notice-hero-meta {
            align-self: flex-start;
            max-width: min(78%, 520px);
            padding: 12px 16px;
            border: 1px solid rgba(255, 255, 255, .38);
            border-radius: 8px;
            background: rgba(255, 255, 255, .26);
            box-shadow: 0 12px 30px rgba(15, 23, 42, .18);
            color: #fff !important;
            text-shadow: 0 1px 3px rgba(15, 23, 42, .42);
            backdrop-filter: blur(14px) saturate(135%);
            -webkit-backdrop-filter: blur(14px) saturate(135%);
        }

        .carousel-img > div:last-child p,
        .xboard-notice-hero-meta p {
            margin: 0;
            color: #fff !important;
        }

        .carousel-img > div:last-child p:first-child,
        .xboard-notice-hero-meta p:first-child {
            font-weight: 800;
            line-height: 1.25;
        }

        .carousel-img > div:last-child p + p,
        .xboard-notice-hero-meta p + p {
            margin-top: 10px;
            color: rgba(255, 255, 255, .86) !important;
        }

        @media (max-width: 640px) {
            .carousel-img > div:last-child,
            .xboard-notice-hero-meta {
                max-width: min(88%, 420px);
                padding: 10px 12px;
            }
        }
    </style>
    <script type="module" crossorigin src="/theme/{{ $themeName }}/assets/umi.js?v={{ $assetVersion }}"></script>
</head>
<body class="amg-depth-{{ $visualDepth }}{{ $backgroundUrl !== '' ? ' amg-custom-bg' : '' }}">
    <script>
        window.routerBase = "/";
        window.settings = {
            title: @json($title),
            assets_path: "/theme/{{ $themeName }}/assets",
            theme: {
                color: @json($themeColor),
                auroraMistLightPlus: {
                    mode: "light-only-plus",
                    accent: @json($accent),
                    secondary: @json($secondary),
                    glassStrength: @json($glassStrength),
                    radiusScale: @json($radiusScale),
                    visualDepth: @json($visualDepth)
                }
            },
            version: @json($version),
            background_url: @json($backgroundUrl),
            description: @json($description),
            i18n: ['zh-CN', 'en-US', 'ja-JP', 'vi-VN', 'ko-KR', 'zh-TW', 'fa-IR'],
            logo: @json($logo)
        };
    </script>

    <div id="app"></div>

    <script>
        (function () {
            function polishNoticeCarousel() {
                Array.prototype.forEach.call(document.querySelectorAll('.carousel-img'), function (hero) {
                    hero.classList.add('xboard-notice-hero');
                    hero.style.setProperty('background', 'transparent', 'important');
                    var meta = hero.lastElementChild;
                    if (meta) meta.classList.add('xboard-notice-hero-meta');
                });
            }

            var pending = 0;
            function schedulePolish() {
                if (pending) return;
                pending = 1;
                var run = window.requestAnimationFrame || function (callback) { return setTimeout(callback, 80); };
                run(function () {
                    pending = 0;
                    polishNoticeCarousel();
                });
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', schedulePolish);
            } else {
                schedulePolish();
            }
            window.addEventListener('hashchange', schedulePolish);
            new MutationObserver(schedulePolish).observe(document.documentElement, { childList: true, subtree: true });
        })();
    </script>

    <script defer src="/theme/{{ $themeName }}/assets/aurora-mist-light-plus.js?v={{ $assetVersion }}"></script>
    <noscript>
        <div style="padding:16px;margin:16px;border-radius:16px;background:#ffffff;color:#253246;border:1px solid rgba(129,151,177,.24);font-family:system-ui,-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;">
            当前浏览器未启用 JavaScript，XBoard 前端无法正常运行。
        </div>
    </noscript>
    {!! $themeConfig['custom_html'] ?? '' !!}
</body>
</html>
