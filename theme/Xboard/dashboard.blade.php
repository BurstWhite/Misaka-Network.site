<!doctype html>
<html lang="zh-CN">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,minimum-scale=1,user-scalable=no" />
  <title>{{$title}}</title>
  @php
    $logoUrl = trim((string) $logo);
    $backgroundUrl = trim((string) ($theme_config['background_url'] ?? ''));
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

      document.addEventListener('DOMContentLoaded', function () {
        mountManualButton();
        new MutationObserver(mountManualButton).observe(document.body, { childList: true, subtree: true });
        window.addEventListener('hashchange', function () {
          setTimeout(mountManualButton, 200);
        });
      });
    })();
  </script>
  <style>
    body.xboard-auth-page {
      margin: 0;
      background:
        radial-gradient(circle at 24% 18%, rgba(147, 197, 253, .2), transparent 32%),
        radial-gradient(circle at 76% 78%, rgba(125, 211, 252, .18), transparent 34%),
        linear-gradient(135deg, #f8fbff 0%, #edf6ff 50%, #f0fdfa 100%);
      color: #0f172a;
    }

    body.xboard-auth-page .xboard-auth-shell {
      position: relative;
      min-height: 100vh;
      display: flex !important;
      flex-direction: column !important;
      align-items: stretch !important;
      justify-content: flex-start !important;
      padding: 0 clamp(24px, 6vw, 72px) 36px !important;
      overflow-x: hidden;
      overflow-y: auto;
      background:
        radial-gradient(circle at 24% 18%, rgba(147, 197, 253, .18), transparent 32%),
        radial-gradient(circle at 76% 78%, rgba(125, 211, 252, .16), transparent 34%),
        linear-gradient(135deg, #f8fbff 0%, #edf6ff 50%, #f0fdfa 100%);
    }

	    body.xboard-auth-page .xboard-auth-shell::before {
	      content: "";
	      display: block;
	      flex: 0 0 min(50vh, 610px);
	      width: 100%;
	      margin: 0 auto clamp(26px, 4vw, 58px);
	      background: var(--xboard-client-bg-image, linear-gradient(135deg, rgba(191, 219, 254, .42), rgba(224, 242, 254, .86) 52%, rgba(204, 251, 241, .72))) center center / cover no-repeat;
	      background-position: center;
	      background-size: cover;
	    }

    body.xboard-auth-page .xboard-auth-shell > * {
      position: relative;
      z-index: 1;
    }

    body.xboard-auth-page .xboard-auth-card {
      width: 100% !important;
      max-width: none !important;
      margin: 0 !important;
      border: 0 !important;
      border-radius: 0 !important;
      box-shadow: none !important;
      background: transparent !important;
      overflow: visible;
      backdrop-filter: none;
    }

    body.xboard-auth-page .xboard-auth-card .n-card__content {
      padding: 0 !important;
    }

    body.xboard-auth-page .xboard-auth-brand {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 8px;
      margin-bottom: 28px !important;
      text-align: center;
    }

    body.xboard-auth-page .xboard-auth-logo {
      display: none !important;
    }

    body.xboard-auth-page .xboard-auth-title {
      display: none !important;
      margin: 0;
      color: #0f172a;
      font-size: 28px;
      font-weight: 800;
      line-height: 1.18;
    }

    body.xboard-auth-page .xboard-auth-description {
      margin-top: 2px !important;
      color: #64748b !important;
      font-size: 14px !important;
      line-height: 1.55 !important;
    }

	    body.xboard-auth-page .xboard-auth-card .n-form,
	    body.xboard-auth-page .xboard-auth-card form {
	      width: 100% !important;
	      max-width: none !important;
	    }

	    body.xboard-auth-page .xboard-auth-card input {
	      min-height: 48px;
	    }

	    body.xboard-auth-page .xboard-auth-card .n-input {
	      --n-color: rgba(255, 255, 255, .96) !important;
	      --n-color-focus: #ffffff !important;
	      --n-color-disabled: rgba(241, 245, 249, .9) !important;
	      --n-text-color: #0f172a !important;
	      --n-placeholder-color: #94a3b8 !important;
	      --n-border: 1px solid rgba(148, 163, 184, .48) !important;
	      --n-border-hover: 1px solid rgba(59, 130, 246, .72) !important;
	      --n-border-focus: 1px solid #2563eb !important;
	      --n-box-shadow-focus: 0 0 0 2px rgba(37, 99, 235, .16) !important;
	      background: rgba(255, 255, 255, .96) !important;
	      width: 100% !important;
	      border-radius: 14px !important;
	      box-shadow: 0 8px 28px rgba(42, 74, 118, .08) !important;
	    }

	    body.xboard-auth-page .xboard-auth-card .n-input__input-el,
	    body.xboard-auth-page .xboard-auth-card .n-input__textarea-el {
	      color: #0f172a !important;
	      -webkit-text-fill-color: #0f172a !important;
	    }

	    html.dark body.xboard-auth-page {
	      background:
	        radial-gradient(circle at 28% 22%, rgba(37, 99, 235, .22), transparent 34%),
	        radial-gradient(circle at 76% 78%, rgba(20, 184, 166, .16), transparent 34%),
	        linear-gradient(135deg, #0f172a 0%, #172033 52%, #082f49 100%);
	      color: #e5e7eb;
	    }

	    html.dark body.xboard-auth-page .xboard-auth-shell {
	      background:
	        radial-gradient(circle at 28% 22%, rgba(37, 99, 235, .22), transparent 34%),
	        radial-gradient(circle at 76% 78%, rgba(20, 184, 166, .16), transparent 34%),
	        linear-gradient(135deg, #0f172a 0%, #172033 52%, #082f49 100%);
	    }

	    html.dark body.xboard-auth-page .xboard-auth-shell::before {
	      background: var(--xboard-client-bg-image, linear-gradient(135deg, rgba(30, 64, 175, .48), rgba(30, 41, 59, .84) 52%, rgba(20, 83, 45, .58))) center center / cover no-repeat;
	      background-position: center;
	      background-size: cover;
	    }

	    html.dark body.xboard-auth-page .xboard-auth-card {
	      border-color: transparent !important;
	      background: transparent !important;
	      box-shadow: none !important;
	    }

	    html.dark body.xboard-auth-page .xboard-auth-title {
	      color: #f8fafc;
	    }

	    html.dark body.xboard-auth-page .xboard-auth-description {
	      color: #cbd5e1 !important;
	    }

	    html.dark body.xboard-auth-page .xboard-auth-card .n-input {
	      --n-color: rgba(15, 23, 42, .78) !important;
	      --n-color-focus: rgba(15, 23, 42, .96) !important;
	      --n-color-disabled: rgba(30, 41, 59, .72) !important;
	      --n-text-color: #f8fafc !important;
	      --n-placeholder-color: #94a3b8 !important;
	      --n-border: 1px solid rgba(148, 163, 184, .38) !important;
	      --n-border-hover: 1px solid rgba(96, 165, 250, .72) !important;
	      --n-border-focus: 1px solid #60a5fa !important;
	      --n-box-shadow-focus: 0 0 0 2px rgba(96, 165, 250, .18) !important;
	      background: rgba(15, 23, 42, .78) !important;
	    }

	    html.dark body.xboard-auth-page .xboard-auth-card .n-input__input-el,
	    html.dark body.xboard-auth-page .xboard-auth-card .n-input__textarea-el {
	      color: #f8fafc !important;
	      -webkit-text-fill-color: #f8fafc !important;
	    }

	    body.xboard-auth-page .xboard-auth-card button {
	      min-height: 54px;
	      border-radius: 16px;
	      width: 100%;
	    }

	    body.xboard-client-page {
	      min-height: 100vh;
	      background-color: #f8fafc;
	      background-image:
	        linear-gradient(135deg, rgba(248, 250, 252, .94), rgba(239, 246, 255, .86) 48%, rgba(236, 253, 245, .78)),
	        var(--xboard-client-bg-image, url('/theme/{{$theme}}/assets/images/background.svg'));
	      background-attachment: fixed;
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
	      background: rgba(255, 255, 255, .82) !important;
	      border-color: rgba(148, 163, 184, .24) !important;
	      border-radius: 16px !important;
	      box-shadow: 0 14px 36px rgba(15, 23, 42, .08) !important;
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
	      --n-color: rgba(255, 255, 255, .84) !important;
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

	    html:not(.dark) body.xboard-client-page .bg-gray-800 {
	      background: rgba(255, 255, 255, .86) !important;
	      border: 1px solid rgba(148, 163, 184, .22) !important;
	      box-shadow: 0 14px 34px rgba(15, 23, 42, .08) !important;
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

	    body.xboard-client-page .xboard-notice-background-dialog {
	      background-position: center !important;
	      background-size: cover !important;
	      overflow: hidden;
	    }

	    body.xboard-client-page .xboard-notice-background-dialog .markdown-body {
	      background: transparent !important;
	    }

    @media (max-width: 640px) {
      body.xboard-auth-page .xboard-auth-shell {
        padding: 0 14px 24px !important;
        align-items: stretch !important;
      }

      body.xboard-auth-page .xboard-auth-shell::before {
        flex-basis: 34vh;
        margin-bottom: 24px;
      }

      body.xboard-auth-page .xboard-auth-card .n-card__content {
        padding: 0 !important;
      }

      body.xboard-auth-page .xboard-auth-title {
        font-size: 24px;
      }
    }
  </style>
  <script type="module" crossorigin src="/theme/{{$theme}}/assets/umi.js"></script>
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
	      var noticeItems = [];

	      function cssUrl(value) {
	        value = String(value || '').trim();
	        if (!value) return '';
	        return 'url("' + value.replace(/"/g, '\\"') + '")';
	      }

	      function syncClientBackground() {
	        var settings = window.settings || {};
	        var background = cssUrl(settings.background_url || settings.logo);
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
        var inputs = Array.prototype.slice.call(document.querySelectorAll('input[type="email"],input[type="password"]'));
        for (var i = 0; i < inputs.length; i++) {
          var card = closestCard(inputs[i]);
          if (card) return card;
        }
        return null;
      }

	      function ensureBrand(card) {
        var settings = window.settings || {};
        var title = settings.title || document.title || 'XBoard';
        var logo = card.querySelector('img');
        var heading = card.querySelector('h1');
        var brand = logo ? logo.parentElement : (heading ? heading.parentElement : card.firstElementChild);
        if (!brand) return;

        brand.classList.add('xboard-auth-brand');
	        if (logo) {
	          logo.classList.add('xboard-auth-logo');
	          logo.loading = 'eager';
	          logo.decoding = 'async';
	          if ('fetchPriority' in logo) logo.fetchPriority = 'high';
	          if (!brand.querySelector('[data-xboard-auth-title="1"]')) {
            var titleNode = document.createElement('div');
            titleNode.setAttribute('data-xboard-auth-title', '1');
            titleNode.className = 'xboard-auth-title';
            titleNode.textContent = title;
            logo.insertAdjacentElement('afterend', titleNode);
          }
        } else if (heading) {
          heading.classList.add('xboard-auth-title');
          heading.textContent = title;
        }

        var description = brand.parentElement && brand.parentElement.querySelector('h5');
        if (description) description.classList.add('xboard-auth-description');
      }

	      function enhanceAuthPage() {
	        var auth = isAuthRoute();
	        document.body.classList.toggle('xboard-auth-page', auth);
	        document.body.classList.toggle('xboard-client-page', !auth && isClientRoute());
	        syncClientBackground();
	        if (!auth) {
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

	      function rememberNotices(body) {
	        var data = body && body.data;
	        if (data && data.data) data = data.data;
	        if (!Array.isArray(data)) return;
	        noticeItems = data.filter(function (item) {
	          return item && item.img_url;
	        });
	        setTimeout(applyNoticeBackgrounds, 60);
	      }

	      function patchNoticeResponseCapture() {
	        if (window.__xboardNoticeResponseCapture) return;
	        window.__xboardNoticeResponseCapture = true;

	        if (window.XMLHttpRequest) {
	          var originalOpen = XMLHttpRequest.prototype.open;
	          var originalSend = XMLHttpRequest.prototype.send;
	          XMLHttpRequest.prototype.open = function (method, url) {
	            this.__xboardNoticeUrl = String(url || '');
	            return originalOpen.apply(this, arguments);
	          };
	          XMLHttpRequest.prototype.send = function () {
	            var xhr = this;
	            xhr.addEventListener('loadend', function () {
	              if (String(xhr.__xboardNoticeUrl || '').indexOf('/user/notice/fetch') === -1) return;
	              try {
	                rememberNotices(JSON.parse(xhr.responseText));
	              } catch (e) {}
	            });
	            return originalSend.apply(this, arguments);
	          };
	        }

	        if (window.fetch) {
	          var originalFetch = window.fetch;
	          window.fetch = function (input) {
	            var url = typeof input === 'string' ? input : (input && input.url) || '';
	            return originalFetch.apply(this, arguments).then(function (response) {
	              if (String(url).indexOf('/user/notice/fetch') !== -1) {
	                response.clone().json().then(rememberNotices).catch(function () {});
	              }
	              return response;
	            });
	          };
	        }
	      }

	      function textOf(element) {
	        return String((element && (element.innerText || element.textContent)) || '').replace(/\s+/g, ' ');
	      }

	      function applyNoticeBackgrounds() {
	        if (!noticeItems.length) return;
	        var dialogs = Array.prototype.slice.call(document.querySelectorAll('.n-modal, .n-card, [role="dialog"]'));
	        dialogs.forEach(function (dialog) {
	          if (!dialog || dialog.getAttribute('data-xboard-notice-background-applied') === '1') return;
	          var text = textOf(dialog);
	          var notice = noticeItems.find(function (item) {
	            return item.title && text.indexOf(item.title) !== -1;
	          });
	          if (!notice || !notice.img_url) return;
	          var card = dialog.classList && dialog.classList.contains('n-card') ? dialog : dialog.querySelector('.n-card') || dialog;
	          card.classList.add('xboard-notice-background-dialog');
	          card.setAttribute('data-xboard-notice-background-applied', '1');
	          card.style.backgroundImage = [
	            'linear-gradient(135deg, rgba(255,255,255,.92), rgba(255,247,237,.86))',
	            cssUrl(notice.img_url)
	          ].join(',');
	        });
	      }

	      document.addEventListener('DOMContentLoaded', function () {
	        patchNoticeResponseCapture();
	        enhanceAuthPage();
	        new MutationObserver(function () {
	          enhanceAuthPage();
	          applyNoticeBackgrounds();
	        }).observe(document.body, { childList: true, subtree: true });
	        window.addEventListener('hashchange', function () {
	          setTimeout(enhanceAuthPage, 120);
	        });
      });
    })();
  </script>
  {!! $theme_config['custom_html'] !!}
</body>

</html>
