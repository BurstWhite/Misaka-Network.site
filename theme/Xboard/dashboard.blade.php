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
  {!! $theme_config['custom_html'] !!}
</body>

</html>
