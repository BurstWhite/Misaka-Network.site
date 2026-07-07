<!doctype html>
<html lang="zh-CN">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,minimum-scale=1,user-scalable=no" />
  <title>{{$title}}</title>
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
      background: #f7fafc;
      color: #0f172a;
    }

    body.xboard-auth-page .xboard-auth-shell {
      position: relative;
      min-height: 100vh;
      padding: 28px;
      overflow: hidden;
      background-color: #f7fafc;
    }

    body.xboard-auth-page .xboard-auth-shell::before {
      content: "";
      position: absolute;
      inset: 0;
      background:
        linear-gradient(135deg, rgba(248, 250, 252, .96), rgba(239, 246, 255, .9) 54%, rgba(236, 253, 245, .88));
      backdrop-filter: blur(1px);
    }

    body.xboard-auth-page .xboard-auth-shell > * {
      position: relative;
      z-index: 1;
    }

    body.xboard-auth-page .xboard-auth-card {
      width: min(440px, calc(100vw - 32px)) !important;
      border: 1px solid rgba(148, 163, 184, .26) !important;
      border-radius: 18px !important;
      box-shadow: 0 24px 70px rgba(15, 23, 42, .14) !important;
      background: rgba(255, 255, 255, .94) !important;
      overflow: hidden;
      backdrop-filter: blur(12px);
    }

    body.xboard-auth-page .xboard-auth-card .n-card__content {
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
    }

    body.xboard-auth-page .xboard-auth-title {
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

    body.xboard-auth-page .xboard-auth-card input {
      min-height: 42px;
    }

    body.xboard-auth-page .xboard-auth-card button {
      border-radius: 8px;
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
      function isAuthRoute() {
        return /^#\/(?:login|register|forgetpassword)(?:[/?#]|$)/.test(window.location.hash || '');
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

      document.addEventListener('DOMContentLoaded', function () {
        enhanceAuthPage();
        new MutationObserver(enhanceAuthPage).observe(document.body, { childList: true, subtree: true });
        window.addEventListener('hashchange', function () {
          setTimeout(enhanceAuthPage, 120);
        });
      });
    })();
  </script>
  {!! $theme_config['custom_html'] !!}
</body>

</html>
