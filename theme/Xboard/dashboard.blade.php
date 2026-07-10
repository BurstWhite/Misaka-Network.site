<!doctype html>
<html lang="zh-CN">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,minimum-scale=1,user-scalable=no" />
  <title>{{$title}}</title>
	  @php
	    $logoUrl = trim((string) $logo);
	    $backgroundUrl = trim((string) ($theme_config['background_url'] ?? ''));
	    $dashboardPath = base_path("theme/{$theme}/dashboard.blade.php");
	    $assetVersion = is_file($dashboardPath) ? substr(md5_file($dashboardPath), 0, 12) : $version;
	    $assetOrigins = [];
	    foreach ([$logoUrl, $backgroundUrl] as $assetUrl) {
	      $parts = parse_url($assetUrl);
	      if (!empty($parts['scheme']) && !empty($parts['host'])) {
        $origin = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
        $assetOrigins[$origin] = $origin;
      }
    }
  @endphp
  @foreach($assetOrigins as $origin)
    <link rel="preconnect" href="{{ $origin }}" crossorigin />
    <link rel="dns-prefetch" href="{{ $origin }}" />
  @endforeach
  @if($logoUrl !== '')
    <link rel="icon" href="{{ $logoUrl }}" />
    <link rel="apple-touch-icon" href="{{ $logoUrl }}" />
    <link rel="preload" as="image" href="{{ $logoUrl }}" fetchpriority="high" />
  @endif
  <script>
    (function () {
      var state = {
        submitting: false,
        button: null,
        authHeader: ''
      };

      function getAccessToken() {
        var match = document.cookie.match(/(?:^|;\s*)access_token=([^;]+)/);
        if (match) return decodeURIComponent(match[1]);
        var keys = ['access_token', 'token', 'Authorization', 'authorization'];
        for (var i = 0; i < keys.length; i++) {
          try {
            var value = localStorage.getItem(keys[i]) || sessionStorage.getItem(keys[i]);
            if (value) return value.replace(/^Bearer\s+/i, '');
          } catch (e) {}
        }
        return '';
      }

      function rememberAuthHeader(value) {
        if (value && /^Bearer\s+/i.test(value)) {
          state.authHeader = value;
        }
      }

      function readFetchAuthHeader(input, init) {
        var headers = init && init.headers;
        if (!headers && input && typeof input !== 'string') {
          headers = input.headers;
        }
        if (!headers) return;
        if (typeof Headers !== 'undefined' && headers instanceof Headers) {
          rememberAuthHeader(headers.get('Authorization') || headers.get('authorization'));
          return;
        }
        if (Array.isArray(headers)) {
          headers.forEach(function (item) {
            if (item && String(item[0]).toLowerCase() === 'authorization') {
              rememberAuthHeader(String(item[1] || ''));
            }
          });
          return;
        }
        rememberAuthHeader(headers.Authorization || headers.authorization);
      }

      function request(path, data) {
        var token = getAccessToken();
        var headers = {
          'Accept': 'application/json',
          'Content-Type': 'application/json'
        };
        if (state.authHeader) {
          headers.Authorization = state.authHeader;
        } else if (token) {
          headers.Authorization = 'Bearer ' + token;
        }
        return fetch(path, {
          method: 'POST',
          credentials: 'same-origin',
          headers: headers,
          body: JSON.stringify(data || {})
        }).then(function (response) {
          return response.json().catch(function () {
            return {};
          }).then(function (body) {
            if (!response.ok || body.status === 'fail') {
              throw new Error(body.message || '请求失败');
            }
            return body;
          });
        });
      }

      function notify(type, message) {
        if (window.$message && typeof window.$message[type] === 'function') {
          window.$message[type](message);
        } else {
          window.alert(message);
        }
      }

      function resetButton() {
        state.submitting = false;
        if (state.button) {
          state.button.disabled = false;
          state.button.textContent = '人工提交订单';
        }
      }

      function extractTradeNo(body) {
        if (!body) return '';
        if (typeof body.data === 'string') return body.data;
        if (body.data && typeof body.data.trade_no === 'string') return body.data.trade_no;
        if (typeof body.trade_no === 'string') return body.trade_no;
        return '';
      }

      function submitManualOrder(tradeNo) {
        if (!tradeNo) {
          resetButton();
          return;
        }
        request('/api/v1/user/order/manual-submit', { trade_no: tradeNo })
          .then(function () {
            notify('success', '人工订单已提交，请等待管理员处理');
          })
          .catch(function (error) {
            notify('error', error.message || '人工订单提交失败');
          })
          .finally(resetButton);
      }

      function handleOrderSaveResponse(url, responseText) {
        if (!state.submitting || !url || url.indexOf('/order/save') === -1) {
          return;
        }
        try {
          submitManualOrder(extractTradeNo(JSON.parse(responseText)));
        } catch (e) {
          resetButton();
        }
      }

      if (window.XMLHttpRequest) {
        var originalOpen = XMLHttpRequest.prototype.open;
        var originalSend = XMLHttpRequest.prototype.send;
        var originalSetRequestHeader = XMLHttpRequest.prototype.setRequestHeader;
        XMLHttpRequest.prototype.open = function (method, url) {
          this.__manualOrderUrl = String(url || '');
          return originalOpen.apply(this, arguments);
        };
        XMLHttpRequest.prototype.setRequestHeader = function (name, value) {
          if (String(name || '').toLowerCase() === 'authorization') {
            rememberAuthHeader(String(value || ''));
          }
          return originalSetRequestHeader.apply(this, arguments);
        };
        XMLHttpRequest.prototype.send = function () {
          var xhr = this;
          xhr.addEventListener('loadend', function () {
            handleOrderSaveResponse(xhr.__manualOrderUrl, xhr.responseText);
          });
          return originalSend.apply(this, arguments);
        };
      }

      if (window.fetch) {
        var originalFetch = window.fetch;
        window.fetch = function (input, init) {
          var url = typeof input === 'string' ? input : (input && input.url) || '';
          readFetchAuthHeader(input, init);
          return originalFetch.apply(this, arguments).then(function (response) {
            if (state.submitting && String(url).indexOf('/order/save') !== -1) {
              response.clone().text().then(function (text) {
                handleOrderSaveResponse(String(url), text);
              });
            }
            return response;
          });
        };
      }

      function findOrderButton() {
        var buttons = Array.prototype.slice.call(document.querySelectorAll('button'));
        for (var i = buttons.length - 1; i >= 0; i--) {
          var text = (buttons[i].textContent || '').trim();
          if (text === '下单' || text.indexOf('下单') !== -1) {
            return buttons[i];
          }
        }
        return null;
      }

      function mountManualButton() {
        if (location.hash.indexOf('/plan/') === -1) {
          var exists = document.getElementById('xboard-manual-submit-order');
          if (exists) exists.remove();
          return;
        }
        if (document.getElementById('xboard-manual-submit-order')) {
          return;
        }
        var orderButton = findOrderButton();
        if (!orderButton || !orderButton.parentElement) {
          return;
        }
        var button = document.createElement('button');
        button.id = 'xboard-manual-submit-order';
        button.type = 'button';
        button.textContent = '人工提交订单';
        button.style.cssText = [
          'width:100%',
          'height:42px',
          'margin-top:12px',
          'border:1px solid rgba(255,255,255,.35)',
          'border-radius:4px',
          'background:#f59e0b',
          'color:#111827',
          'font-size:15px',
          'font-weight:600',
          'cursor:pointer'
        ].join(';');
        button.addEventListener('click', function () {
          if (state.submitting) return;
          var currentOrderButton = findOrderButton();
          if (!currentOrderButton) {
            notify('error', '未找到下单按钮');
            return;
          }
          state.submitting = true;
          state.button = button;
          button.disabled = true;
          button.textContent = '正在提交...';
          currentOrderButton.click();
        });
        orderButton.parentElement.appendChild(button);
      }

      var manualButtonFrame = 0;
      function scheduleManualButton() {
        if (manualButtonFrame) return;
        var run = window.requestAnimationFrame || function (callback) { return setTimeout(callback, 80); };
        manualButtonFrame = run(function () {
          manualButtonFrame = 0;
          mountManualButton();
        });
      }

      document.addEventListener('DOMContentLoaded', function () {
        mountManualButton();
        new MutationObserver(scheduleManualButton).observe(document.body, { childList: true, subtree: true });
        window.addEventListener('hashchange', function () {
          setTimeout(scheduleManualButton, 200);
        });
      });
    })();
  </script>
  <style>
    body.xboard-auth-page {
      --login-bg: #f7fafc;
      --login-bg-overlay: linear-gradient(135deg, rgba(248, 250, 252, .96), rgba(239, 246, 255, .9) 54%, rgba(236, 253, 245, .88));
      --login-card-bg: rgba(255, 255, 255, .94);
      --login-text: #0f172a;
      --login-muted: #64748b;
      --login-border: rgba(148, 163, 184, .26);
      --login-input-bg: rgba(255, 255, 255, .96);
      --login-input-border: rgba(148, 163, 184, .48);
      --login-input-focus: #2563eb;
      --login-placeholder: #94a3b8;
      --login-primary: #0f6fdc;
      --login-link-bg: rgba(248, 250, 252, .82);
      margin: 0;
      background: var(--login-bg);
      color: var(--login-text);
    }

    body.xboard-auth-page .xboard-auth-shell {
      position: relative;
      min-height: 100vh;
      padding: 28px;
      overflow: auto;
      background-color: var(--login-bg);
      display: flex;
      align-items: center;
      justify-content: center;
    }

	    body.xboard-auth-page .xboard-auth-shell::before {
	      content: "";
	      position: absolute;
	      inset: 0;
	      background:
	        var(--login-bg-overlay),
	        var(--xboard-client-bg-image, none);
	      background-position: center;
	      background-size: cover;
	      backdrop-filter: none;
	    }

    body.xboard-auth-page .xboard-auth-shell > * {
      position: relative;
      z-index: 1;
    }

    body.xboard-auth-page .xboard-auth-card {
      width: min(440px, calc(100vw - 32px)) !important;
      border: 1px solid var(--login-border) !important;
      border-radius: 18px !important;
      box-shadow: 0 24px 70px rgba(15, 23, 42, .14) !important;
      background: var(--login-card-bg) !important;
      overflow: hidden;
      backdrop-filter: none;
    }

    body.xboard-auth-page .xboard-auth-card .n-card__content {
      padding: 34px 34px 24px !important;
    }

    body.xboard-auth-page .xboard-auth-card:not(.n-card) {
      box-sizing: border-box;
      padding: 34px 34px 24px !important;
    }

    body.xboard-auth-page .xboard-auth-brand {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 8px;
      margin-bottom: 24px !important;
      text-align: center;
    }

    body.xboard-auth-page .xboard-auth-logo {
      width: auto !important;
      max-width: min(180px, 58vw) !important;
      max-height: 64px !important;
      margin: 0 !important;
      object-fit: contain;
      border-radius: 4px;
    }

    body.xboard-auth-page .xboard-auth-logo-source {
      display: none !important;
    }

    body.xboard-auth-page .xboard-auth-card img:not(.xboard-auth-logo) {
      max-width: 100% !important;
      max-height: 64px !important;
      object-fit: contain !important;
    }

    body.xboard-auth-page .xboard-auth-title {
      margin: 0;
      color: var(--login-text);
      font-size: 28px;
      font-weight: 800;
      line-height: 1.18;
    }

    body.xboard-auth-page .xboard-auth-description {
      margin-top: 2px !important;
      color: var(--login-muted) !important;
      font-size: 14px !important;
      line-height: 1.55 !important;
    }

	    body.xboard-auth-page .xboard-auth-card input {
	      min-height: 42px;
	    }

	    body.xboard-auth-page .xboard-auth-card .n-input {
	      --n-color: var(--login-input-bg) !important;
	      --n-color-focus: var(--login-input-bg) !important;
	      --n-color-disabled: rgba(241, 245, 249, .9) !important;
	      --n-text-color: var(--login-text) !important;
	      --n-placeholder-color: var(--login-placeholder) !important;
	      --n-border: 1px solid var(--login-input-border) !important;
	      --n-border-hover: 1px solid rgba(59, 130, 246, .72) !important;
	      --n-border-focus: 1px solid var(--login-input-focus) !important;
	      --n-box-shadow-focus: 0 0 0 2px rgba(37, 99, 235, .16) !important;
	      background: var(--login-input-bg) !important;
	    }

	    body.xboard-auth-page .xboard-auth-card .n-input__input-el,
	    body.xboard-auth-page .xboard-auth-card .n-input__textarea-el {
	      color: var(--login-text) !important;
	      -webkit-text-fill-color: var(--login-text) !important;
	    }

	    html.dark body.xboard-auth-page {
	      --login-bg: #0f172a;
	      --login-bg-overlay: linear-gradient(135deg, rgba(15, 23, 42, .92), rgba(30, 41, 59, .84) 52%, rgba(6, 78, 59, .58));
	      --login-card-bg: rgba(15, 23, 42, .9);
	      --login-text: #f8fafc;
	      --login-muted: #cbd5e1;
	      --login-border: rgba(148, 163, 184, .22);
	      --login-input-bg: rgba(15, 23, 42, .78);
	      --login-input-border: rgba(148, 163, 184, .38);
	      --login-input-focus: #60a5fa;
	      --login-placeholder: #94a3b8;
	      --login-primary: #3b82f6;
	      --login-link-bg: rgba(30, 41, 59, .62);
	      background: var(--login-bg);
	      color: var(--login-text);
	    }

	    html.dark body.xboard-auth-page .xboard-auth-shell {
	      background-color: var(--login-bg);
	    }

	    html.dark body.xboard-auth-page .xboard-auth-shell::before {
	      background:
	        var(--login-bg-overlay),
	        var(--xboard-client-bg-image, none);
	      background-position: center;
	      background-size: cover;
	    }

	    html.dark body.xboard-auth-page .xboard-auth-card {
	      border-color: var(--login-border) !important;
	      background: var(--login-card-bg) !important;
	      box-shadow: 0 28px 80px rgba(0, 0, 0, .38) !important;
	    }

	    html.dark body.xboard-auth-page .xboard-auth-title {
	      color: var(--login-text);
	    }

	    html.dark body.xboard-auth-page .xboard-auth-description {
	      color: var(--login-muted) !important;
	    }

	    html.dark body.xboard-auth-page .xboard-auth-card .n-input {
	      --n-color: var(--login-input-bg) !important;
	      --n-color-focus: rgba(15, 23, 42, .96) !important;
	      --n-color-disabled: rgba(30, 41, 59, .72) !important;
	      --n-text-color: var(--login-text) !important;
	      --n-placeholder-color: var(--login-placeholder) !important;
	      --n-border: 1px solid var(--login-input-border) !important;
	      --n-border-hover: 1px solid rgba(96, 165, 250, .72) !important;
	      --n-border-focus: 1px solid var(--login-input-focus) !important;
	      --n-box-shadow-focus: 0 0 0 2px rgba(96, 165, 250, .18) !important;
	      background: var(--login-input-bg) !important;
	    }

	    html.dark body.xboard-auth-page .xboard-auth-card .n-input__input-el,
	    html.dark body.xboard-auth-page .xboard-auth-card .n-input__textarea-el {
	      color: var(--login-text) !important;
	      -webkit-text-fill-color: var(--login-text) !important;
	    }

	    body.xboard-auth-page .xboard-auth-card button {
	      border-radius: 8px;
	    }

	    body.xboard-auth-page .xboard-auth-card .n-button--primary-type,
	    body.xboard-auth-page .xboard-auth-card button[type="submit"] {
	      --n-color: var(--login-primary) !important;
	      --n-color-hover: var(--login-primary) !important;
	      --n-color-pressed: var(--login-primary) !important;
	      --n-color-focus: var(--login-primary) !important;
	      --n-border: 1px solid var(--login-primary) !important;
	      --n-border-hover: 1px solid var(--login-primary) !important;
	      --n-border-pressed: 1px solid var(--login-primary) !important;
	      --n-border-focus: 1px solid var(--login-primary) !important;
	      --n-text-color: #ffffff !important;
	      background: var(--login-primary) !important;
	      color: #ffffff !important;
	    }

	    body.xboard-auth-page .xboard-auth-card a,
	    body.xboard-auth-page .xboard-auth-card .n-button:not(.n-button--primary-type) {
	      color: var(--login-muted) !important;
	    }

	    body.xboard-auth-page .xboard-auth-card .xboard-auth-links {
	      display: flex !important;
	      align-items: center !important;
	      justify-content: space-between !important;
	      gap: 12px !important;
	      margin: 24px -1px 0 !important;
	      padding: 16px 22px !important;
	      border-radius: 0 !important;
	      background: var(--login-link-bg) !important;
	      color: var(--login-muted) !important;
	    }

	    body.xboard-auth-page .xboard-auth-card .xboard-auth-links > * {
	      min-width: 0;
	    }

	    body.xboard-client-page {
	      min-height: 100vh;
	      background-color: #f8fafc;
	      background-image:
	        linear-gradient(135deg, rgba(248, 250, 252, .82), rgba(239, 246, 255, .72) 48%, rgba(236, 253, 245, .62)),
	        var(--xboard-client-bg-image, url('/theme/{{$theme}}/assets/images/background.svg'));
	      background-attachment: scroll;
	      background-position: center;
	      background-size: cover;
	    }

	    html.dark body.xboard-client-page {
	      background-color: #0f172a;
	      background-image:
	        linear-gradient(135deg, rgba(15, 23, 42, .92), rgba(30, 41, 59, .84) 50%, rgba(6, 78, 59, .66)),
	        var(--xboard-client-bg-image, url('/theme/{{$theme}}/assets/images/background.svg'));
	    }

	    body.xboard-client-page #app,
	    body.xboard-client-page .n-layout,
	    body.xboard-client-page .n-layout-scroll-container,
	    body.xboard-client-page .n-layout-content {
	      background: transparent !important;
	    }

	    body.xboard-client-page .n-card {
	      background: rgba(255, 255, 255, .94) !important;
	      border-color: rgba(148, 163, 184, .24) !important;
	      border-radius: 16px !important;
	      box-shadow: 0 8px 22px rgba(15, 23, 42, .06) !important;
	      overflow: hidden;
	    }

	    html.dark body.xboard-client-page .n-card {
	      background: var(--n-color) !important;
	      border-color: var(--n-border-color) !important;
	      box-shadow: none !important;
	    }

	    body.xboard-client-page .n-alert,
	    body.xboard-client-page .n-button,
	    body.xboard-client-page .n-tag,
	    body.xboard-client-page .n-input,
	    body.xboard-client-page .n-base-selection {
	      border-radius: 12px !important;
	    }

	    html:not(.dark) body.xboard-client-page .n-card,
	    html:not(.dark) body.xboard-client-page .n-data-table,
	    html:not(.dark) body.xboard-client-page .n-input,
	    html:not(.dark) body.xboard-client-page .n-base-selection,
	    html:not(.dark) body.xboard-client-page .n-tabs,
	    html:not(.dark) body.xboard-client-page .n-tabs-tab,
	    html:not(.dark) body.xboard-client-page .n-button:not(.n-button--primary-type) {
	      --n-border-color: rgba(148, 163, 184, .22) !important;
	      --n-border-color-hover: rgba(37, 99, 235, .46) !important;
	      --n-border-color-focus: rgba(37, 99, 235, .62) !important;
	      --n-border-color-active: #2563eb !important;
	      --n-color: rgba(255, 255, 255, .94) !important;
	      --n-color-modal: rgba(255, 255, 255, .94) !important;
	      --n-color-popover: rgba(255, 255, 255, .96) !important;
	      --n-text-color: #0f172a !important;
	    }

	    html:not(.dark) body.xboard-client-page .n-card-header,
	    html:not(.dark) body.xboard-client-page .n-card__content,
	    html:not(.dark) body.xboard-client-page .n-card__footer {
	      border-color: rgba(148, 163, 184, .18) !important;
	    }

	    html:not(.dark) body.xboard-client-page .n-data-table {
	      --n-border-color: rgba(148, 163, 184, .22) !important;
	      --n-td-color: rgba(255, 255, 255, .58) !important;
	      --n-td-color-hover: rgba(239, 246, 255, .78) !important;
	      --n-th-color: rgba(241, 245, 249, .82) !important;
	      --n-th-text-color: #0f172a !important;
	    }

	    html:not(.dark) body.xboard-client-page .n-card .n-data-table-wrapper,
	    html:not(.dark) body.xboard-client-page .n-card .n-data-table-base-table,
	    html:not(.dark) body.xboard-client-page .n-card .n-data-table-base-table-body,
	    html:not(.dark) body.xboard-client-page .n-card .n-data-table-base-table-header {
	      background: transparent !important;
	      border-radius: 0 !important;
	      box-shadow: none !important;
	    }

	    html:not(.dark) body.xboard-client-page .n-card .n-data-table-wrapper {
	      border: 0 !important;
	      overflow-x: auto !important;
	      overflow-y: visible !important;
	    }

	    html:not(.dark) body.xboard-client-page .n-card .n-data-table {
	      background: transparent !important;
	      box-shadow: none !important;
	    }

	    html:not(.dark) body.xboard-client-page .n-card .n-data-table .n-data-table-empty {
	      background: transparent !important;
	    }

	    body.xboard-client-page .n-modal .n-card,
	    body.xboard-client-page .n-card.n-modal,
	    body.xboard-client-page .n-dialog,
	    body.xboard-client-page .n-drawer-content,
	    body.xboard-client-page .n-popover,
	    body.xboard-client-page .n-dropdown-menu,
	    body.xboard-client-page .n-date-panel,
	    body.xboard-client-page .n-time-panel,
	    body.xboard-client-page .n-color-picker-panel,
	    body.xboard-client-page .n-base-select-menu,
	    body.xboard-client-page .n-cascader-menu,
	    body.xboard-client-page .n-image-preview-toolbar {
	      position: relative;
	      overflow: hidden;
	      border: 1px solid rgba(203, 213, 225, .58) !important;
	      backdrop-filter: none !important;
	      -webkit-backdrop-filter: none !important;
	    }

	    body.xboard-client-page .n-modal .n-card::before,
	    body.xboard-client-page .n-card.n-modal::before,
	    body.xboard-client-page .n-dialog::before,
	    body.xboard-client-page .n-drawer-content::before,
	    body.xboard-client-page .n-popover::before,
	    body.xboard-client-page .n-dropdown-menu::before,
	    body.xboard-client-page .n-date-panel::before,
	    body.xboard-client-page .n-time-panel::before,
	    body.xboard-client-page .n-color-picker-panel::before,
	    body.xboard-client-page .n-base-select-menu::before,
	    body.xboard-client-page .n-cascader-menu::before,
	    body.xboard-client-page .n-image-preview-toolbar::before {
	      display: none;
	    }

	    body.xboard-client-page .n-modal .n-card > *,
	    body.xboard-client-page .n-card.n-modal > *,
	    body.xboard-client-page .n-dialog > *,
	    body.xboard-client-page .n-drawer-content > *,
	    body.xboard-client-page .n-popover > *,
	    body.xboard-client-page .n-dropdown-menu > *,
	    body.xboard-client-page .n-date-panel > *,
	    body.xboard-client-page .n-time-panel > *,
	    body.xboard-client-page .n-color-picker-panel > *,
	    body.xboard-client-page .n-base-select-menu > *,
	    body.xboard-client-page .n-cascader-menu > *,
	    body.xboard-client-page .n-image-preview-toolbar > * {
	      position: relative;
	      z-index: 1;
	    }

	    html:not(.dark) body.xboard-client-page .bg-gray-800 {
	      background: rgba(255, 255, 255, .86) !important;
	      border: 1px solid rgba(148, 163, 184, .22) !important;
	      box-shadow: 0 8px 20px rgba(15, 23, 42, .06) !important;
	    }

	    html:not(.dark) body.xboard-client-page .text-white,
	    html:not(.dark) body.xboard-client-page .text-gray-900,
	    html:not(.dark) body.xboard-client-page .text-gray-100 {
	      color: #0f172a !important;
	    }

	    html:not(.dark) body.xboard-client-page .text-gray-300,
	    html:not(.dark) body.xboard-client-page .text-gray-400,
	    html:not(.dark) body.xboard-client-page .text-gray-500,
	    html:not(.dark) body.xboard-client-page .text-gray-600,
	    html:not(.dark) body.xboard-client-page .text-gray-700 {
	      color: #475569 !important;
	    }

	    html:not(.dark) body.xboard-client-page .border-gray-600 {
	      border-color: rgba(148, 163, 184, .22) !important;
	    }

	    html:not(.dark) body.xboard-client-page .placeholder-gray-400::placeholder {
	      color: #94a3b8 !important;
	    }

	    html:not(.dark) body.xboard-client-page input.bg-transparent,
	    html:not(.dark) body.xboard-client-page textarea.bg-transparent {
	      color: #0f172a !important;
	      -webkit-text-fill-color: #0f172a !important;
	    }

	    html:not(.dark) body.xboard-client-page input.bg-transparent {
	      background: transparent !important;
	    }

	    html:not(.dark) body.xboard-client-page .n-modal-mask {
	      background-color: rgba(15, 23, 42, .18) !important;
	      backdrop-filter: none !important;
	      -webkit-backdrop-filter: none !important;
	    }

	    html:not(.dark) body.xboard-client-page .n-image-preview-overlay {
	      background-color: rgba(15, 23, 42, .16) !important;
	      backdrop-filter: none !important;
	      -webkit-backdrop-filter: none !important;
	    }

	    html:not(.dark) body.xboard-client-page .n-modal .n-card,
	    html:not(.dark) body.xboard-client-page .n-card.n-modal,
	    html:not(.dark) body.xboard-client-page .n-dialog,
	    html:not(.dark) body.xboard-client-page .n-drawer-content,
	    html:not(.dark) body.xboard-client-page .n-popover,
	    html:not(.dark) body.xboard-client-page .n-dropdown-menu,
	    html:not(.dark) body.xboard-client-page .n-date-panel,
	    html:not(.dark) body.xboard-client-page .n-time-panel,
	    html:not(.dark) body.xboard-client-page .n-color-picker-panel,
	    html:not(.dark) body.xboard-client-page .n-base-select-menu,
	    html:not(.dark) body.xboard-client-page .n-cascader-menu,
	    html:not(.dark) body.xboard-client-page .n-image-preview-toolbar {
	      --n-color: rgba(255, 255, 255, .94) !important;
	      --n-color-modal: rgba(255, 255, 255, .94) !important;
	      --n-color-popover: rgba(255, 255, 255, .95) !important;
	      --n-text-color: #0f172a !important;
	      --n-title-text-color: #0f172a !important;
	      --n-border-color: rgba(203, 213, 225, .62) !important;
	      background-color: rgba(255, 255, 255, .94) !important;
	      background-image:
	        linear-gradient(135deg, rgba(255, 255, 255, .96), rgba(248, 250, 252, .90) 42%, rgba(207, 250, 254, .42) 100%),
	        radial-gradient(circle at 82% 28%, rgba(187, 247, 208, .32), transparent 44%) !important;
	      color: #0f172a !important;
	      text-shadow: none !important;
	      box-shadow: 0 18px 44px rgba(15, 23, 42, .10) !important;
	    }

	    html:not(.dark) body.xboard-client-page .n-modal .n-card .n-card-header,
	    html:not(.dark) body.xboard-client-page .n-card.n-modal .n-card-header,
	    html:not(.dark) body.xboard-client-page .n-dialog .n-dialog__title,
	    html:not(.dark) body.xboard-client-page .n-drawer-content .n-drawer-header {
	      background: rgba(248, 250, 252, .68) !important;
	      border-color: rgba(203, 213, 225, .46) !important;
	    }

	    html:not(.dark) body.xboard-client-page .n-modal .n-card .markdown-body,
	    html:not(.dark) body.xboard-client-page .n-card.n-modal .markdown-body,
	    html:not(.dark) body.xboard-client-page .n-dialog .markdown-body,
	    html:not(.dark) body.xboard-client-page .n-drawer-content .markdown-body,
	    html:not(.dark) body.xboard-client-page .n-popover .markdown-body {
	      --color-fg-default: #0f172a;
	      --color-fg-muted: #334155;
	      --color-canvas-default: rgba(255, 255, 255, .96);
	      --color-canvas-subtle: rgba(248, 250, 252, .96);
	      background: transparent !important;
	      color: #0f172a !important;
	    }

	    html:not(.dark) body.xboard-client-page .n-modal .n-card .markdown-body table,
	    html:not(.dark) body.xboard-client-page .n-card.n-modal .markdown-body table,
	    html:not(.dark) body.xboard-client-page .n-dialog .markdown-body table {
	      background: rgba(255, 255, 255, .98) !important;
	    }

	    html.dark body.xboard-client-page .n-modal .n-card,
	    html.dark body.xboard-client-page .n-card.n-modal,
	    html.dark body.xboard-client-page .n-dialog,
	    html.dark body.xboard-client-page .n-drawer-content,
	    html.dark body.xboard-client-page .n-popover,
	    html.dark body.xboard-client-page .n-dropdown-menu,
	    html.dark body.xboard-client-page .n-date-panel,
	    html.dark body.xboard-client-page .n-time-panel,
	    html.dark body.xboard-client-page .n-color-picker-panel,
	    html.dark body.xboard-client-page .n-base-select-menu,
	    html.dark body.xboard-client-page .n-cascader-menu,
	    html.dark body.xboard-client-page .n-image-preview-toolbar {
	      --n-color: rgba(15, 23, 42, .96) !important;
	      --n-color-modal: rgba(15, 23, 42, .96) !important;
	      --n-color-popover: rgba(15, 23, 42, .96) !important;
	      --n-text-color: #f8fafc !important;
	      --n-title-text-color: #ffffff !important;
	      --n-border-color: rgba(148, 163, 184, .22) !important;
	      background-color: rgba(15, 23, 42, .96) !important;
	      background-image:
	        linear-gradient(135deg, rgba(15, 23, 42, .98), rgba(30, 41, 59, .94) 52%, rgba(6, 78, 59, .34)) !important;
	      border-color: rgba(148, 163, 184, .22) !important;
	      color: #f8fafc !important;
	      box-shadow: 0 24px 64px rgba(0, 0, 0, .38) !important;
	    }

	    html.dark body.xboard-client-page .n-modal .n-card::before,
	    html.dark body.xboard-client-page .n-card.n-modal::before,
	    html.dark body.xboard-client-page .n-dialog::before,
	    html.dark body.xboard-client-page .n-drawer-content::before,
	    html.dark body.xboard-client-page .n-popover::before,
	    html.dark body.xboard-client-page .n-dropdown-menu::before,
	    html.dark body.xboard-client-page .n-date-panel::before,
	    html.dark body.xboard-client-page .n-time-panel::before,
	    html.dark body.xboard-client-page .n-color-picker-panel::before,
	    html.dark body.xboard-client-page .n-base-select-menu::before,
	    html.dark body.xboard-client-page .n-cascader-menu::before,
	    html.dark body.xboard-client-page .n-image-preview-toolbar::before {
	      display: none;
	    }

	    html.dark body.xboard-client-page .n-image-preview-overlay {
	      background-color: rgba(0, 0, 0, .62) !important;
	    }

	    html.dark body.xboard-client-page .n-modal .n-card,
	    html.dark body.xboard-client-page .n-card.n-modal,
	    html.dark body.xboard-client-page .n-dialog,
	    html.dark body.xboard-client-page .n-drawer-content,
	    html.dark body.xboard-client-page .n-popover,
	    html.dark body.xboard-client-page .n-dropdown-menu,
	    html.dark body.xboard-client-page .n-date-panel,
	    html.dark body.xboard-client-page .n-time-panel,
	    html.dark body.xboard-client-page .n-color-picker-panel,
	    html.dark body.xboard-client-page .n-base-select-menu,
	    html.dark body.xboard-client-page .n-cascader-menu,
	    html.dark body.xboard-client-page .n-image-preview-toolbar,
	    html.dark body.xboard-client-page .n-modal .n-card .markdown-body,
	    html.dark body.xboard-client-page .n-card.n-modal .markdown-body,
	    html.dark body.xboard-client-page .n-dialog .markdown-body,
	    html.dark body.xboard-client-page .n-drawer-content .markdown-body,
	    html.dark body.xboard-client-page .n-popover .markdown-body {
	      color: #f8fafc !important;
	    }

	    :root {
	      --xboard-overlay-revision: "soft-card-20260709-2";
	    }

	    html:not(.dark) .n-modal-mask {
	      background-color: rgba(15, 23, 42, .16) !important;
	      backdrop-filter: none !important;
	      -webkit-backdrop-filter: none !important;
	    }

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

	    html:not(.dark) .n-modal-container .n-card::before,
	    html:not(.dark) .n-modal-container .n-card::after,
	    html:not(.dark) .n-modal-container .n-dialog::before,
	    html:not(.dark) .n-modal-container .n-dialog::after {
	      content: none !important;
	      display: none !important;
	    }

	    html:not(.dark) .n-modal-container .n-card *,
	    html:not(.dark) .n-modal-container .n-dialog * {
	      text-shadow: none !important;
	      backdrop-filter: none !important;
	      -webkit-backdrop-filter: none !important;
	    }

	    html:not(.dark) .n-modal-container .n-card-header,
	    html:not(.dark) .n-modal-container .n-card__content,
	    html:not(.dark) .n-modal-container .n-card__footer,
	    html:not(.dark) .n-modal-container .n-dialog__content,
	    html:not(.dark) .n-modal-container .markdown-body {
	      background: transparent !important;
	      color: #0f172a !important;
	    }

	    html:not(.dark) .n-modal-container [class*="bg-gray-800"],
	    html:not(.dark) .n-modal-container [class*="bg-gray-900"],
	    html:not(.dark) .n-modal-container [class*="bg-slate-"],
	    html:not(.dark) .n-modal-container [class*="bg-black"],
	    html:not(.dark) .n-modal-container [class*="bg-dark"] {
	      background: transparent !important;
	      color: #0f172a !important;
	    }

	    html:not(.dark) .n-modal-container [class*="text-white"],
	    html:not(.dark) .n-modal-container [class*="text-gray-100"],
	    html:not(.dark) .n-modal-container [class*="text-gray-200"],
	    html:not(.dark) .n-modal-container [class*="color-#f8f9fa"] {
	      color: #0f172a !important;
	    }

	    html:not(.dark) .n-modal-container .markdown-body table,
	    html:not(.dark) .n-modal-container .markdown-body th,
	    html:not(.dark) .n-modal-container .markdown-body td {
	      background-color: rgba(255, 255, 255, .92) !important;
	      color: #0f172a !important;
	    }

	    html.dark .n-modal-container .n-modal,
	    html.dark .n-modal-container .n-card,
	    html.dark .n-modal-container .n-dialog {
	      background-color: rgba(15, 23, 42, .96) !important;
	      background-image: linear-gradient(135deg, rgba(15, 23, 42, .98), rgba(30, 41, 59, .94) 58%, rgba(6, 78, 59, .30)) !important;
	      backdrop-filter: none !important;
	      -webkit-backdrop-filter: none !important;
	    }

	    body.xboard-client-page .carousel-img,
	    body.xboard-client-page .xboard-notice-hero {
	      background: transparent !important;
	    }

	    body.xboard-client-page .carousel-img > div:last-child,
	    body.xboard-client-page .xboard-notice-hero-meta {
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

	    body.xboard-client-page .carousel-img > div:last-child p,
	    body.xboard-client-page .xboard-notice-hero-meta p {
	      margin: 0;
	      color: #fff !important;
	    }

	    body.xboard-client-page .carousel-img > div:last-child p:first-child,
	    body.xboard-client-page .xboard-notice-hero-meta p:first-child {
	      font-weight: 800;
	      line-height: 1.25;
	    }

	    body.xboard-client-page .carousel-img > div:last-child p + p,
	    body.xboard-client-page .xboard-notice-hero-meta p + p {
	      margin-top: 10px;
	      color: rgba(255, 255, 255, .86) !important;
	    }

    @media (max-width: 640px) {
      body.xboard-auth-page .xboard-auth-shell {
        padding: 18px;
        align-items: flex-start !important;
        padding-top: 42px;
      }

      body.xboard-auth-page .xboard-auth-card .n-card__content {
        padding: 28px 22px 20px !important;
      }

      body.xboard-auth-page .xboard-auth-card:not(.n-card) {
        padding: 28px 22px 20px !important;
      }

      body.xboard-auth-page .xboard-auth-title {
        font-size: 24px;
      }

	      body.xboard-client-page .carousel-img > div:last-child,
	      body.xboard-client-page .xboard-notice-hero-meta {
	        max-width: min(88%, 420px);
	        padding: 10px 12px;
	      }
    }
  </style>
  <script type="module" crossorigin src="/theme/{{$theme}}/assets/umi.js?v={{ $assetVersion }}"></script>
</head>

<body>

  <script>
    window.routerBase = "/";
    window.settings = {
      title: '{{$title}}',
      assets_path: '/theme/{{$theme}}/assets',
      theme: {
        color: '{{ $theme_config['theme_color'] ?? "default" }}',
      },
      version: '{{$version}}',
      background_url: '{{$theme_config['background_url']}}',
      description: '{{$description}}',
      i18n: [
        'zh-CN',
        'en-US',
        'ja-JP',
        'vi-VN',
        'ko-KR',
        'zh-TW',
        'fa-IR'
      ],
      logo: '{{$logo}}'
    }
  </script>
  <div id="app"></div>
	  <script>
	    (function () {
	      var enhanceFrame = 0;

	      function cssUrl(value) {
	        value = String(value || '').trim();
	        if (!value) return '';
	        return 'url("' + value.replace(/"/g, '\\"') + '")';
	      }

	      function syncClientBackground() {
	        var settings = window.settings || {};
	        var background = cssUrl(settings.background_url);
	        if (background) {
	          document.body.style.setProperty('--xboard-client-bg-image', background);
	        }
	      }

	      function isAuthRoute() {
	        return /^#\/(?:login|register|forgetpassword)(?:[/?#]|$)/.test(window.location.hash || '');
	      }

	      function isClientRoute() {
	        var hash = window.location.hash || '';
	        return hash && !isAuthRoute();
	      }

	      function enhanceNoticeCarousel() {
	        if (!isClientRoute()) return;
	        Array.prototype.forEach.call(document.querySelectorAll('.carousel-img'), function (hero) {
	          hero.classList.add('xboard-notice-hero');
	          hero.style.setProperty('background', 'transparent', 'important');
	          var meta = hero.lastElementChild;
	          if (meta) meta.classList.add('xboard-notice-hero-meta');
	        });
	      }

      function closestCard(element) {
        var node = element;
        while (node && node !== document.body) {
          if (node.classList && (node.classList.contains('n-card') || /(?:^|\s)n-card(?:\s|$)/.test(node.className || ''))) {
            return node;
          }
          node = node.parentElement;
        }
        return null;
      }

      function findAuthCard() {
        var inputs = Array.prototype.slice.call(document.querySelectorAll('input[type="email"],input[type="password"],input[autocomplete*="email"],input[autocomplete*="password"]'));
        for (var i = 0; i < inputs.length; i++) {
          var card = closestCard(inputs[i]);
          if (card) return card;
        }
        var password = inputs.find(function (input) {
          return String(input.type || '').toLowerCase() === 'password' || /password|密码/i.test(input.autocomplete || input.placeholder || '');
        });
        var email = inputs.find(function (input) {
          return input !== password && (String(input.type || '').toLowerCase() === 'email' || /mail|邮箱|email/i.test(input.autocomplete || input.placeholder || ''));
        }) || inputs.find(function (input) { return input !== password; });
        if (!password || !email) return null;
        var controls = [email, password];
        var best = null;
        var bestArea = Infinity;
        var bestComplete = null;
        var bestCompleteArea = Infinity;
        var node = email.parentElement;
        while (node && node !== document.body) {
          if (node.id === 'app') break;
          var containsControls = controls.every(function (input) { return node.contains(input); });
          var hasAction = node.querySelector('button,[role="button"],input[type="submit"]');
          if (containsControls && hasAction) {
            var rect = node.getBoundingClientRect();
            var area = Math.max(rect.width, 1) * Math.max(rect.height, 1);
            var text = String(node.textContent || '').replace(/\s+/g, ' ');
            var hasFullAuthSurface = node.querySelector('img,h1,h5') || /注册|忘记密码|简体中文|English|语言|language|南海|凤凰|让于/i.test(text);
            if (hasFullAuthSurface && area < bestCompleteArea) {
              bestComplete = node;
              bestCompleteArea = area;
            }
            if (area < bestArea) {
              best = node;
              bestArea = area;
            }
          }
          node = node.parentElement;
        }
        return bestComplete || best;
      }

      function authLogoCandidate(card) {
        var images = Array.prototype.slice.call(card.querySelectorAll('img')).filter(function (image) {
          return !image.closest || !image.closest('.xboard-auth-brand');
        });
        if (!images.length) return null;
        images.sort(function (a, b) {
          var ar = a.getBoundingClientRect();
          var br = b.getBoundingClientRect();
          return (br.width * br.height) - (ar.width * ar.height);
        });
        return images[0];
      }

      function normalizedImageUrl(value) {
        if (!value) return '';
        var anchor = document.createElement('a');
        anchor.href = value;
        return anchor.href;
      }

      function ensureAuthLogo(brand, card) {
        var settings = window.settings || {};
        var logo = brand.querySelector('[data-xboard-auth-logo="1"]');
        var source = authLogoCandidate(card);
        if (!logo) {
          var sourceUrl = settings.logo || (source && (source.currentSrc || source.src));
          if (!sourceUrl) return null;
          logo = document.createElement('img');
          logo.src = sourceUrl;
          logo.alt = settings.title || 'XBoard';
          logo.setAttribute('data-xboard-auth-logo', '1');
          brand.insertBefore(logo, brand.firstChild || null);
        }
        logo.classList.add('xboard-auth-logo');
        logo.loading = 'eager';
        logo.decoding = 'async';
        if ('fetchPriority' in logo) logo.fetchPriority = 'high';

        var logoUrl = normalizedImageUrl(logo.currentSrc || logo.src);
        Array.prototype.forEach.call(card.querySelectorAll('img'), function (image) {
          if (image === logo || (image.closest && image.closest('.xboard-auth-brand'))) return;
          if (logoUrl && normalizedImageUrl(image.currentSrc || image.src) === logoUrl) {
            image.classList.add('xboard-auth-logo-source');
          }
        });
        return logo;
      }

      function findAuthDescription(card) {
        return Array.prototype.slice.call(card.querySelectorAll('h5,p,small,div,span')).find(function (node) {
          if (node.closest && node.closest('.xboard-auth-brand')) return false;
          var text = String(node.textContent || '').trim();
          return text && text.length <= 40 && /南海|凤凰|让于/i.test(text);
        }) || null;
      }

      function markAuthLinks(card) {
        var candidates = Array.prototype.slice.call(card.querySelectorAll('div,section,footer')).filter(function (node) {
          if (node.classList.contains('xboard-auth-brand')) return false;
          var text = String(node.textContent || '').replace(/\s+/g, ' ');
          return /注册|忘记密码|简体中文|English|语言|language/i.test(text)
            && node.querySelectorAll('a,button,[role="button"]').length >= 1;
        });
        if (!candidates.length) return;
        candidates.sort(function (a, b) {
          return b.getBoundingClientRect().top - a.getBoundingClientRect().top;
        });
        candidates[0].classList.add('xboard-auth-links');
      }

	      function ensureBrand(card) {
        var settings = window.settings || {};
        var title = (settings.title || document.title || 'XBoard').replace(/^登录页\s*\|\s*/i, '');
        var heading = card.querySelector('h1');
        var brand = card.querySelector('.xboard-auth-brand');
        if (!brand) {
          brand = document.createElement('div');
          brand.className = 'xboard-auth-brand';
          card.insertBefore(brand, card.firstElementChild || null);
        }

        ensureAuthLogo(brand, card);
        if (!brand.querySelector('[data-xboard-auth-title="1"]')) {
          var titleNode = document.createElement('div');
          titleNode.setAttribute('data-xboard-auth-title', '1');
          titleNode.className = 'xboard-auth-title';
          titleNode.textContent = title;
          brand.appendChild(titleNode);
        }
        if (heading && heading.parentElement !== brand) heading.style.display = 'none';

        var description = findAuthDescription(card);
        if (description) {
          description.classList.add('xboard-auth-description');
          if (description.parentElement !== brand) brand.appendChild(description);
        }
        markAuthLinks(card);
      }

	      function enhanceAuthPage() {
	        var auth = isAuthRoute();
	        document.body.classList.toggle('xboard-auth-page', auth);
	        document.body.classList.toggle('xboard-client-page', !auth && isClientRoute());
	        syncClientBackground();
	        if (!auth) {
	          enhanceNoticeCarousel();
          Array.prototype.forEach.call(document.querySelectorAll('.xboard-auth-shell'), function (shell) {
            shell.classList.remove('xboard-auth-shell');
          });
          return;
        }

        var card = findAuthCard();
        if (!card) return;
        card.classList.add('xboard-auth-card');
        var shell = card.closest('.wh-full') || card.parentElement;
        if (shell) shell.classList.add('xboard-auth-shell');
        ensureBrand(card);
      }

	      function scheduleEnhanceAuthPage() {
	        if (enhanceFrame) return;
	        var run = window.requestAnimationFrame || function (callback) { return setTimeout(callback, 80); };
	        enhanceFrame = run(function () {
	          enhanceFrame = 0;
	          enhanceAuthPage();
	        });
	      }

	      document.addEventListener('DOMContentLoaded', function () {
	        enhanceAuthPage();
	        new MutationObserver(scheduleEnhanceAuthPage).observe(document.body, { childList: true, subtree: true });
	        window.addEventListener('hashchange', function () {
	          setTimeout(scheduleEnhanceAuthPage, 120);
	        });
	      });
    })();
  </script>
  {!! $theme_config['custom_html'] !!}
</body>

</html>
