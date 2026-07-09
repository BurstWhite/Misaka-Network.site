<?php

use App\Services\ThemeService;
use App\Services\UpdateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

function inject_auth_layout_patch(string $html, array $settings): string
{
    if (str_contains($html, 'data-xboard-auth-layout-patch') || str_contains($html, 'xboard-client-page')) {
        return $html;
    }

    $payload = json_encode([
        'title' => $settings['title'] ?? 'XBoard',
        'description' => $settings['description'] ?? '',
        'logo' => $settings['logo'] ?? '',
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $style = <<<HTML
<style data-xboard-auth-layout-patch="1">
  html[data-amg-active="light"] body.amg-theme,body.xboard-auth-page{--xboard-liquid-bg:radial-gradient(circle at 8% 6%,rgba(var(--amg-accent-rgb,109,146,232),.17) 0,transparent 30%),radial-gradient(circle at 92% 16%,rgba(var(--amg-accent-2-rgb,114,199,192),.146) 0,transparent 31%),radial-gradient(circle at 76% 92%,rgba(240,182,158,.126) 0,transparent 33%),linear-gradient(135deg,var(--amg-bg-0,#f8fbff),var(--amg-bg-1,#eef7fb) 48%,var(--amg-bg-2,#f7f3ee))}
  html[data-amg-active="light"] body.amg-theme{background:var(--xboard-liquid-bg)!important;background-attachment:scroll!important}
  body.xboard-auth-page{--login-bg:#f8fbff;--login-bg-overlay:var(--xboard-liquid-bg);--login-card-bg:rgba(255,255,255,.86);--login-text:#142139;--login-muted:#617188;--login-border:rgba(126,147,176,.22);--login-input-bg:rgba(255,255,255,.82);--login-input-border:rgba(126,147,176,.30);--login-input-focus:#4f75cf;--login-placeholder:#8c9aad;--login-primary:#0f6fdc;--login-link-bg:rgba(250,253,255,.72);margin:0;background:var(--login-bg)!important;color:var(--login-text)!important}
  html.dark body.xboard-auth-page{--login-bg:#0f172a;--login-bg-overlay:linear-gradient(135deg,rgba(15,23,42,.92),rgba(30,41,59,.84) 52%,rgba(6,78,59,.58));--login-card-bg:rgba(15,23,42,.9);--login-text:#f8fafc;--login-muted:#cbd5e1;--login-border:rgba(148,163,184,.22);--login-input-bg:rgba(15,23,42,.78);--login-input-border:rgba(148,163,184,.38);--login-input-focus:#60a5fa;--login-placeholder:#94a3b8;--login-primary:#3b82f6;--login-link-bg:rgba(30,41,59,.62)}
  body.xboard-auth-page .xboard-auth-shell{position:relative!important;min-height:100vh!important;padding:28px!important;overflow:auto!important;background:var(--login-bg-overlay)!important;display:flex!important;align-items:center!important;justify-content:center!important}
  body.xboard-auth-page .xboard-auth-shell>*{position:relative;z-index:1}
  body.xboard-auth-page .xboard-auth-card{box-sizing:border-box!important;width:min(440px,calc(100vw - 32px))!important;max-width:min(440px,calc(100vw - 32px))!important;margin:auto!important;padding:34px 34px 24px!important;border:1px solid var(--login-border)!important;border-radius:18px!important;background:var(--login-card-bg)!important;box-shadow:0 12px 34px rgba(15,23,42,.10)!important;overflow:hidden!important;backdrop-filter:none!important}
  html.dark body.xboard-auth-page .xboard-auth-card{box-shadow:0 16px 44px rgba(0,0,0,.30)!important}
  body.xboard-auth-page .xboard-auth-card>*{max-width:100%!important}
  body.xboard-auth-page .xboard-auth-brand{display:flex!important;flex-direction:column!important;align-items:center!important;gap:8px!important;margin-bottom:24px!important;text-align:center!important}
  body.xboard-auth-page .xboard-auth-logo{width:auto!important;max-width:min(180px,58vw)!important;max-height:64px!important;margin:0!important;object-fit:contain!important;border-radius:4px!important}
  body.xboard-auth-page .xboard-auth-title{margin:0!important;color:var(--login-text)!important;font-size:28px!important;font-weight:800!important;line-height:1.18!important}
  body.xboard-auth-page .xboard-auth-description{margin-top:2px!important;color:var(--login-muted)!important;font-size:14px!important;line-height:1.55!important}
  body.xboard-auth-page .xboard-auth-card input{min-height:42px!important;color:var(--login-text)!important;-webkit-text-fill-color:var(--login-text)!important}
  body.xboard-auth-page .xboard-auth-card .n-input,body.xboard-auth-page .xboard-auth-card input:not([type=checkbox]):not([type=radio]){--n-color:var(--login-input-bg)!important;--n-color-focus:var(--login-input-bg)!important;--n-text-color:var(--login-text)!important;--n-placeholder-color:var(--login-placeholder)!important;--n-border:1px solid var(--login-input-border)!important;--n-border-hover:1px solid rgba(59,130,246,.72)!important;--n-border-focus:1px solid var(--login-input-focus)!important;--n-box-shadow-focus:0 0 0 2px rgba(37,99,235,.16)!important;background:var(--login-input-bg)!important;border-color:var(--login-input-border)!important}
  body.xboard-auth-page .xboard-auth-card button,body.xboard-auth-page .xboard-auth-card .n-button{border-radius:8px!important}
  body.xboard-auth-page .xboard-auth-card .n-button--primary-type,body.xboard-auth-page .xboard-auth-card button[type=submit]{--n-color:var(--login-primary)!important;--n-color-hover:var(--login-primary)!important;--n-color-pressed:var(--login-primary)!important;--n-color-focus:var(--login-primary)!important;--n-border:1px solid var(--login-primary)!important;--n-border-hover:1px solid var(--login-primary)!important;--n-border-pressed:1px solid var(--login-primary)!important;--n-border-focus:1px solid var(--login-primary)!important;--n-text-color:#fff!important;background:var(--login-primary)!important;border-color:var(--login-primary)!important;color:#fff!important}
  body.xboard-auth-page .xboard-auth-card a,body.xboard-auth-page .xboard-auth-card .n-button:not(.n-button--primary-type){color:var(--login-muted)!important}
  body.xboard-auth-page .xboard-auth-links{display:flex!important;align-items:center!important;justify-content:space-between!important;gap:12px!important;margin:24px -34px -24px!important;padding:16px 34px!important;background:var(--login-link-bg)!important;color:var(--login-muted)!important}
  @media (max-width:640px){body.xboard-auth-page .xboard-auth-shell{align-items:flex-start!important;padding:42px 18px 18px!important}body.xboard-auth-page .xboard-auth-card{padding:28px 22px 20px!important}body.xboard-auth-page .xboard-auth-title{font-size:24px!important}body.xboard-auth-page .xboard-auth-links{margin:24px -22px -20px!important;padding:16px 22px!important}}
</style>
HTML;

    $script = <<<HTML
<script data-xboard-auth-layout-patch="1">
(function(){
  var settings = {$payload};
  function isAuthRoute(){return /^#\/(?:login|register|forgetpassword)(?:[/?#]|$)/.test(location.hash||'')}
  function visible(el){if(!el||!el.getBoundingClientRect)return false;var r=el.getBoundingClientRect();var s=getComputedStyle(el);return r.width>0&&r.height>0&&s.display!=='none'&&s.visibility!=='hidden'}
  function closestCard(el){var n=el;while(n&&n!==document.body){if(n.classList&&(n.classList.contains('n-card')||/(^|\s)n-card(\s|$)/.test(n.className||'')))return n;n=n.parentElement}return null}
  function findCard(){var inputs=[].slice.call(document.querySelectorAll('input[type=email],input[type=password],input[autocomplete*=email],input[autocomplete*=password]')).filter(visible);for(var i=0;i<inputs.length;i++){var c=closestCard(inputs[i]);if(c)return c}var pass=inputs.find(function(x){return String(x.type||'').toLowerCase()==='password'||/password|密码/i.test(x.autocomplete||x.placeholder||'')});var email=inputs.find(function(x){return x!==pass&&(String(x.type||'').toLowerCase()==='email'||/mail|邮箱|email/i.test(x.autocomplete||x.placeholder||''))})||inputs.find(function(x){return x!==pass});if(!pass||!email)return null;var best=null,area=Infinity,complete=null,completeArea=Infinity,n=email.parentElement;while(n&&n!==document.body){if(n.id==='app')break;var ok=n.contains(email)&&n.contains(pass)&&n.querySelector('button,[role=button],input[type=submit]');if(ok){var r=n.getBoundingClientRect(),a=Math.max(r.width,1)*Math.max(r.height,1),text=String(n.textContent||'').replace(/\s+/g,' '),full=n.querySelector('img,h1,h5')||/注册|忘记密码|简体中文|English|语言|language|南海|凤凰|让于/i.test(text);if(full&&a<completeArea){complete=n;completeArea=a}if(a<area){best=n;area=a}}n=n.parentElement}return complete||best}
  function logoCandidate(card){var imgs=[].slice.call(card.querySelectorAll('img')).filter(visible);imgs.sort(function(a,b){var ar=a.getBoundingClientRect(),br=b.getBoundingClientRect();return br.width*br.height-ar.width*ar.height});return imgs[0]||null}
  function ensureLogo(brand,card){var logo=logoCandidate(card);if(!logo&&settings.logo){logo=document.createElement('img');logo.src=settings.logo;logo.alt=settings.title||'XBoard'}if(!logo)return;if(logo.parentElement!==brand)brand.insertBefore(logo,brand.firstChild||null);logo.classList.add('xboard-auth-logo');logo.loading='eager';logo.decoding='async'}
  function ensureBrand(card){var brand=card.querySelector('.xboard-auth-brand');if(!brand){brand=document.createElement('div');brand.className='xboard-auth-brand';card.insertBefore(brand,card.firstElementChild||null)}ensureLogo(brand,card);if(!brand.querySelector('[data-xboard-auth-title]')){var t=document.createElement('div');t.className='xboard-auth-title';t.dataset.xboardAuthTitle='1';t.textContent=String(settings.title||document.title||'XBoard').replace(/^登录页\s*\|\s*/i,'');brand.appendChild(t)}var desc=[].slice.call(card.querySelectorAll('h5,p,small,div,span')).find(function(n){if(n.closest&&n.closest('.xboard-auth-brand'))return false;var text=String(n.textContent||'').trim();return text&&text.length<=40&&/南海|凤凰|让于/i.test(text)});if(desc){desc.classList.add('xboard-auth-description');if(desc.parentElement!==brand)brand.appendChild(desc)}}
  function markLinks(card){[].slice.call(card.querySelectorAll('.xboard-auth-links')).forEach(function(n){n.classList.remove('xboard-auth-links')});var list=[].slice.call(card.querySelectorAll('div,section,footer')).filter(function(n){if(n.classList.contains('xboard-auth-brand')||n.querySelector('input,textarea'))return false;var text=String(n.textContent||'').replace(/\s+/g,' ');return /注册|忘记密码|简体中文|English|语言|language/i.test(text)&&n.querySelectorAll('a,button,[role=button]').length});if(!list.length)return;list.sort(function(a,b){function score(n){var text=String(n.textContent||'').replace(/\s+/g,' '),r=n.getBoundingClientRect();return (/注册|忘记密码/i.test(text)&&/简体中文|English|语言|language/i.test(text)?100000:0)+r.width*2+r.height-r.top}return score(b)-score(a)});list[0].classList.add('xboard-auth-links')}
  function apply(){var auth=isAuthRoute();document.body.classList.toggle('xboard-auth-page',auth);if(!auth)return;var card=findCard();if(!card)return;card.classList.add('xboard-auth-card');var shell=card.closest('.wh-full')||card.parentElement;if(shell)shell.classList.add('xboard-auth-shell');ensureBrand(card);markLinks(card)}
  var pending=0;function schedule(){if(pending)return;pending=1;var run=window.requestAnimationFrame||function(cb){return setTimeout(cb,80)};run(function(){pending=0;apply();setTimeout(apply,300);setTimeout(apply,900)})}
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',schedule);else schedule();
  addEventListener('hashchange',schedule);new MutationObserver(schedule).observe(document.documentElement,{childList:true,subtree:true});
})();
</script>
HTML;

    if (str_contains($html, '</head>')) {
        $html = str_replace('</head>', $style . "\n</head>", $html);
    } else {
        $html = $style . $html;
    }

    if (str_contains($html, '</body>')) {
        return str_replace('</body>', $script . "\n</body>", $html);
    }

    return $html . $script;
}

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

    $theme = admin_setting('frontend_theme', 'Xboard');
    $themeService = new ThemeService();

    try {
        if (!$themeService->exists($theme)) {
            if ($theme !== 'Xboard') {
                Log::warning('Theme not found, switching to default theme', ['theme' => $theme]);
                $theme = 'Xboard';
                admin_setting(['frontend_theme' => $theme]);
            }
            $themeService->switch($theme);
        }

        if (!$themeService->getThemeViewPath($theme)) {
            throw new Exception('主题视图文件不存在');
        }

        $publicThemePath = public_path('theme/' . $theme);
        if (!File::exists($publicThemePath)) {
            $themePath = $themeService->getThemePath($theme);
            if (!$themePath || !File::copyDirectory($themePath, $publicThemePath)) {
                throw new Exception('主题初始化失败');
            }
            Log::info('Theme initialized in public directory', ['theme' => $theme]);
        }

        $renderParams = [
            'title' => admin_setting('app_name', 'Xboard'),
            'theme' => $theme,
            'version' => app(UpdateService::class)->getCurrentVersion(),
            'description' => admin_setting('app_description', 'Xboard is best'),
            'logo' => admin_setting('logo'),
            'theme_config' => $themeService->getConfig($theme)
        ];
        $html = view('theme::' . $theme . '.dashboard', $renderParams)->render();
        return response(inject_auth_layout_patch($html, $renderParams))
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    } catch (Exception $e) {
        Log::error('Theme rendering failed', [
            'theme' => $theme,
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
