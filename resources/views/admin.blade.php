<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>{{ $title }}</title>
  <script>
    window.settings = {
      base_url: "/",
      title: "{{ $title }}",
      version: "{{ $version }}",
      logo: "{{ $logo }}",
      secure_path: "{{ $secure_path }}",
    };
  </script>
  @php
    $manifestPath = public_path('assets/admin/manifest.json');
    $manifest = file_exists($manifestPath) ? json_decode(file_get_contents($manifestPath), true) : null;
    $entry = is_array($manifest) ? ($manifest['index.html'] ?? null) : null;
    $scripts = [];
    $styles = [];
    $locales = [];

    if (is_array($entry)) {
      $visited = [];
      $collectAssets = function ($chunkName) use (&$collectAssets, &$manifest, &$visited, &$scripts, &$styles) {
        if (isset($visited[$chunkName]) || !isset($manifest[$chunkName]) || !is_array($manifest[$chunkName])) {
          return;
        }

        $visited[$chunkName] = true;
        $chunk = $manifest[$chunkName];

        if (!empty($chunk['css']) && is_array($chunk['css'])) {
          foreach ($chunk['css'] as $cssFile) {
            $styles[$cssFile] = $cssFile;
          }
        }

        if (!empty($chunk['imports']) && is_array($chunk['imports'])) {
          foreach ($chunk['imports'] as $import) {
            $collectAssets($import);
          }
        }

        if (!empty($chunk['isEntry']) && !empty($chunk['file'])) {
          $scripts[$chunk['file']] = $chunk['file'];
        }
      };

      $collectAssets('index.html');
    }

    foreach (glob(public_path('assets/admin/locales/*.js')) ?: [] as $localeFile) {
      $locales[] = 'locales/' . basename($localeFile);
    }
    sort($locales);
  @endphp

  @if($entry && count($scripts) > 0)
    @foreach($styles as $css)
      <link rel="stylesheet" crossorigin href="/assets/admin/{{ $css }}" />
    @endforeach
    @foreach($locales as $locale)
      <script src="/assets/admin/{{ $locale }}"></script>
    @endforeach
    @foreach($scripts as $js)
      <script type="module" crossorigin src="/assets/admin/{{ $js }}"></script>
    @endforeach
  @else
    {{-- Fallback: hardcoded paths for backward compatibility --}}
    <script type="module" crossorigin src="/assets/admin/assets/index.js"></script>
    <link rel="stylesheet" crossorigin href="/assets/admin/assets/index.css" />
    <link rel="stylesheet" crossorigin href="/assets/admin/assets/vendor.css">
    <script src="/assets/admin/locales/en-US.js"></script>
    <script src="/assets/admin/locales/zh-CN.js"></script>
    <script src="/assets/admin/locales/ko-KR.js"></script>
  @endif
  <script>
    (function () {
      var latestManualOrder = null;

      function getAccessToken() {
        var match = document.cookie.match(/(?:^|;\s*)access_token=([^;]+)/);
        return match ? decodeURIComponent(match[1]) : '';
      }

      function notify(type, message) {
        if (window.$message && typeof window.$message[type] === 'function') {
          window.$message[type](message);
        } else {
          window.alert(message);
        }
      }

      function parseOrderDetail(url, text) {
        if (!url || url.indexOf('/order/detail') === -1 || !text) {
          return;
        }
        try {
          var body = JSON.parse(text);
          var order = body && body.data;
          if (order && order.trade_no) {
            latestManualOrder = order;
            renderManualProcessButton();
          }
        } catch (e) {
          // Ignore non-json responses.
        }
      }

      if (window.XMLHttpRequest) {
        var originalOpen = XMLHttpRequest.prototype.open;
        var originalSend = XMLHttpRequest.prototype.send;
        XMLHttpRequest.prototype.open = function (method, url) {
          this.__manualProcessUrl = String(url || '');
          return originalOpen.apply(this, arguments);
        };
        XMLHttpRequest.prototype.send = function () {
          var xhr = this;
          xhr.addEventListener('loadend', function () {
            parseOrderDetail(xhr.__manualProcessUrl, xhr.responseText);
          });
          return originalSend.apply(this, arguments);
        };
      }

      if (window.fetch) {
        var originalFetch = window.fetch;
        window.fetch = function (input) {
          var url = typeof input === 'string' ? input : (input && input.url) || '';
          return originalFetch.apply(this, arguments).then(function (response) {
            if (String(url).indexOf('/order/detail') !== -1) {
              response.clone().text().then(function (text) {
                parseOrderDetail(String(url), text);
              });
            }
            return response;
          });
        };
      }

      function shouldShowButton() {
        if (!latestManualOrder) return false;
        if (Number(latestManualOrder.status) !== 0) return false;
        if (Number(latestManualOrder.manual_status) !== 1) return false;
        return document.body && document.body.innerText.indexOf(latestManualOrder.trade_no) !== -1;
      }

      function renderManualProcessButton() {
        var button = document.getElementById('xboard-admin-manual-process-order');
        if (!shouldShowButton()) {
          if (button) button.remove();
          return;
        }
        if (button) return;
        button = document.createElement('button');
        button.id = 'xboard-admin-manual-process-order';
        button.type = 'button';
        button.textContent = '人工处理';
        button.style.cssText = [
          'position:fixed',
          'right:32px',
          'bottom:32px',
          'z-index:99999',
          'height:40px',
          'padding:0 18px',
          'border:0',
          'border-radius:6px',
          'background:#f59e0b',
          'color:#111827',
          'font-size:14px',
          'font-weight:600',
          'box-shadow:0 10px 24px rgba(0,0,0,.18)',
          'cursor:pointer'
        ].join(';');
        button.addEventListener('click', function () {
          if (!latestManualOrder || button.disabled) return;
          if (!window.confirm('确认人工处理该订单并开通套餐？')) return;
          button.disabled = true;
          button.textContent = '处理中...';
          var token = getAccessToken();
          var securePath = window.settings && window.settings.secure_path ? window.settings.secure_path : '';
          var headers = {
            'Accept': 'application/json',
            'Content-Type': 'application/json'
          };
          if (token) {
            headers.Authorization = 'Bearer ' + token;
          }
          fetch('/api/v2/' + securePath + '/order/manual-process', {
            method: 'POST',
            credentials: 'same-origin',
            headers: headers,
            body: JSON.stringify({ trade_no: latestManualOrder.trade_no })
          }).then(function (response) {
            return response.json().catch(function () {
              return {};
            }).then(function (body) {
              if (!response.ok || body.status === 'fail') {
                throw new Error(body.message || '处理失败');
              }
              return body;
            });
          }).then(function () {
            notify('success', '人工处理完成，套餐已开通');
            latestManualOrder.manual_status = 2;
            latestManualOrder.status = 3;
            button.remove();
          }).catch(function (error) {
            notify('error', error.message || '人工处理失败');
            button.disabled = false;
            button.textContent = '人工处理';
          });
        });
        document.body.appendChild(button);
      }

      document.addEventListener('DOMContentLoaded', function () {
        new MutationObserver(renderManualProcessButton).observe(document.body, { childList: true, subtree: true });
        window.setInterval(renderManualProcessButton, 1000);
      });
    })();
  </script>
</head>

<body>
  <div id="root"></div>
</body>

</html>
