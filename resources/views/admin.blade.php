<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>{{ $title }}</title>
  @php
    $faviconUrl = trim((string) $logo);
  @endphp
  @if($faviconUrl !== '')
    <link rel="icon" href="{{ $faviconUrl }}" />
    <link rel="apple-touch-icon" href="{{ $faviconUrl }}" />
  @endif
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
      if (window.__xboardAdminKnowledgeTools) return;
      window.__xboardAdminKnowledgeTools = true;

      var state = {
        authHeader: '',
        input: null,
        uploading: false,
        activeDialog: null,
        activeTrigger: null,
        activeEditor: null,
        activeElement: null,
        editors: [],
        monacoPatched: false,
	        appleModal: null,
	        appleAccounts: [],
	        appleActiveId: null,
	        activeUrlInput: null,
	        activeMirrorUrlInput: null,
	        noticeItems: [],
	        themeItems: {},
	        activeTheme: '',
	        toolsScheduled: false,
	        lastToolsRunAt: 0
	      };

      function getAccessToken() {
        var match = document.cookie.match(/(?:^|;\s*)access_token=([^;]+)/);
        if (match) return decodeURIComponent(match[1]).replace(/^Bearer\s+/i, '');
        var keys = ['access_token', 'token', 'Authorization', 'authorization'];
        for (var i = 0; i < keys.length; i++) {
          var value = localStorage.getItem(keys[i]) || sessionStorage.getItem(keys[i]);
          if (value) return String(value).replace(/^Bearer\s+/i, '');
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

      function notify(type, message) {
        if (window.$message && typeof window.$message[type] === 'function') {
          window.$message[type](message);
        } else {
          window.alert(message);
        }
      }

	      function isNoticeSaveUrl(url) {
	        return /\/notice\/(?:save|update)(?:\?|$)/.test(String(url || ''));
	      }

	      function isNoticeFetchUrl(url) {
	        return /\/notice\/fetch(?:\?|$)/.test(String(url || ''));
	      }

	      function isThemeFetchUrl(url) {
	        return /\/theme\/getThemes(?:\?|$)/.test(String(url || ''));
	      }

	      function rememberThemeItems(body) {
	        var data = body && body.data;
	        if (data && data.data) data = data.data;
	        if (!data || !data.themes) return;
	        state.themeItems = data.themes || {};
	        state.activeTheme = data.active || '';
	        scheduleKnowledgeTools(60);
	      }

	      function rememberNoticeItems(body) {
	        var data = body && body.data;
	        if (data && data.data) data = data.data;
	        if (Array.isArray(data)) {
	          state.noticeItems = data;
	        }
	      }

      function findCurrentNoticeBackgroundInput() {
        var dialog = findKnowledgeDialog(document.activeElement);
        if (dialog && dialogUploadType(dialog) === 'notice') {
          var input = dialog.querySelector('[data-xboard-notice-background-input="1"]') || findNoticeImageUrlInput(dialog);
          if (input) return input;
        }

        var panels = Array.prototype.slice.call(document.querySelectorAll('[data-xboard-notice-background-panel="1"]'));
        for (var i = 0; i < panels.length; i++) {
          if (!visible(panels[i])) continue;
          var panelInput = panels[i].querySelector('[data-xboard-notice-background-input="1"]');
          if (panelInput) return panelInput;
        }
        return null;
      }

      function injectNoticeImageUrl(body) {
        var imageInput = findCurrentNoticeBackgroundInput();
        if (!imageInput) return body;
        var imageUrl = String(imageInput.value || '').trim();

        if (typeof FormData !== 'undefined' && body instanceof FormData) {
          body.set('img_url', imageUrl);
          return body;
        }
        if (typeof URLSearchParams !== 'undefined' && body instanceof URLSearchParams) {
          body.set('img_url', imageUrl);
          return body;
        }
        if (typeof body === 'string') {
          try {
            var json = JSON.parse(body);
            if (json && typeof json === 'object') {
              json.img_url = imageUrl;
              return JSON.stringify(json);
            }
          } catch (e) {
            if (body.indexOf('=') !== -1) {
              var params = new URLSearchParams(body);
              params.set('img_url', imageUrl);
              return params.toString();
            }
          }
        }
        if (body == null) {
          var fallbackParams = new URLSearchParams();
          fallbackParams.set('img_url', imageUrl);
          return fallbackParams.toString();
        }
        return body;
      }

      function patchRequestAuthCapture() {
        if (window.__xboardKnowledgeAuthPatched) return;
        window.__xboardKnowledgeAuthPatched = true;

        if (window.XMLHttpRequest) {
          var originalOpen = XMLHttpRequest.prototype.open;
          var originalSend = XMLHttpRequest.prototype.send;
          var originalSetRequestHeader = XMLHttpRequest.prototype.setRequestHeader;
	          XMLHttpRequest.prototype.open = function (method, url) {
	            this.__xboardAdminUrl = String(url || '');
	            return originalOpen.apply(this, arguments);
	          };
	          XMLHttpRequest.prototype.send = function (body) {
	            if (isNoticeSaveUrl(this.__xboardAdminUrl)) {
	              body = injectNoticeImageUrl(body);
	            }
	            if (isNoticeFetchUrl(this.__xboardAdminUrl)) {
	              this.addEventListener('loadend', function () {
	                try {
	                  rememberNoticeItems(JSON.parse(this.responseText));
	                } catch (e) {}
	              });
	            }
	            if (isThemeFetchUrl(this.__xboardAdminUrl)) {
	              this.addEventListener('loadend', function () {
	                try {
	                  rememberThemeItems(JSON.parse(this.responseText));
	                } catch (e) {}
	              });
	            }
	            return originalSend.call(this, body);
	          };
          XMLHttpRequest.prototype.setRequestHeader = function (name, value) {
            if (String(name || '').toLowerCase() === 'authorization') {
              rememberAuthHeader(String(value || ''));
            }
            return originalSetRequestHeader.apply(this, arguments);
          };
        }

        if (window.fetch) {
          var originalFetch = window.fetch;
          window.fetch = function (input, init) {
            readFetchAuthHeader(input, init);
	            var url = typeof input === 'string' ? input : (input && input.url) || '';
	            if (isNoticeSaveUrl(url) && init && Object.prototype.hasOwnProperty.call(init, 'body')) {
	              init = Object.assign({}, init, { body: injectNoticeImageUrl(init.body) });
	            }
	            return originalFetch.apply(this, arguments).then(function (response) {
	              if (isNoticeFetchUrl(url)) {
	                response.clone().json().then(rememberNoticeItems).catch(function () {});
	              }
	              if (isThemeFetchUrl(url)) {
	                response.clone().json().then(rememberThemeItems).catch(function () {});
	              }
	              return response;
	            });
	          };
	        }
	      }

      function apiFetch(path, options) {
        var securePath = window.settings && window.settings.secure_path ? window.settings.secure_path : '';
        var token = getAccessToken();
        var headers = options && options.headers ? options.headers : {};
        headers.Accept = headers.Accept || 'application/json';
        if (state.authHeader) {
          headers.Authorization = state.authHeader;
        } else if (token) {
          headers.Authorization = 'Bearer ' + token;
        }

        return fetch('/api/v2/' + securePath + path, Object.assign({
          credentials: 'same-origin',
          headers: headers
        }, options || {})).then(function (response) {
          return response.json().catch(function () {
            return {};
          }).then(function (body) {
            if (!response.ok || body.status === 'fail') {
              throw new Error(body.message || '请求失败');
            }
            return body.data;
          });
        });
      }

      function visible(element) {
        if (!element || !element.getBoundingClientRect) return false;
        var rect = element.getBoundingClientRect();
        var style = window.getComputedStyle(element);
        return rect.width > 0 && rect.height > 0 && style.visibility !== 'hidden' && style.display !== 'none';
      }

	      function ensureAdminUiStyles() {
	        if (document.getElementById('xboard-admin-polish-style')) return;
	        var style = document.createElement('style');
	        style.id = 'xboard-admin-polish-style';
	        style.textContent = [
	          '[data-xboard-theme-actions="1"]{display:flex!important;align-items:center!important;justify-content:space-between!important;gap:8px!important;flex-wrap:wrap!important;width:100%!important}',
	          '[data-xboard-theme-actions-left="1"],[data-xboard-theme-actions-right="1"]{display:flex;align-items:center;gap:8px;flex-wrap:wrap}',
	          '[data-xboard-theme-actions-left="1"]{margin-right:auto}',
	          '[data-xboard-theme-actions-right="1"]{margin-left:auto}',
	          '@media (max-width:820px){[data-xboard-notice-background-row="1"]{flex-wrap:wrap!important}[data-xboard-notice-background-row="1"] input{flex-basis:100%!important}}'
	        ].join('\n');
	        document.head.appendChild(style);
	      }

      function normalizedText(element) {
        return String((element && (element.textContent || '')) || '').replace(/\s+/g, ' ');
      }

      function routeText() {
        return String(location.pathname || '') + String(location.hash || '') + String(location.search || '');
      }

      function visiblePageTextMatches(pattern) {
        var nodes = document.querySelectorAll('h1,h2,h3,h4,[class*="title"],[class*="Title"],button,a');
        for (var i = 0; i < nodes.length; i++) {
          if (visible(nodes[i]) && pattern.test(normalizedText(nodes[i]))) return true;
        }
        return false;
      }

      function isKnowledgePage() {
        var route = routeText();
        if (/knowledge/i.test(route)) return true;
        return document.body && visiblePageTextMatches(/知识库|Knowledge Base/i);
      }

      function isNoticePage() {
        var route = routeText();
        if (/notice/i.test(route)) return true;
        return document.body && visiblePageTextMatches(/公告管理|添加公告|编辑公告|Notice/i);
      }

      function isThemePage() {
        var route = routeText();
        if (/theme/i.test(route)) return true;
        return document.body && visiblePageTextMatches(/主题配置|主题设置|主题管理|Theme/i);
      }

      function trackEditor(editor) {
        if (!editor || state.editors.indexOf(editor) !== -1) return editor;
        state.editors.push(editor);
        if (typeof editor.onDidFocusEditorText === 'function') {
          editor.onDidFocusEditorText(function () {
            state.activeEditor = editor;
          });
        }
        return editor;
      }

      function patchMonaco() {
        var monaco = window.monaco;
        if (state.monacoPatched || !monaco || !monaco.editor || typeof monaco.editor.create !== 'function') {
          return;
        }
        state.monacoPatched = true;
        var originalCreate = monaco.editor.create;
        monaco.editor.create = function () {
          return trackEditor(originalCreate.apply(this, arguments));
        };
      }

      function getFocusedMonacoEditor() {
        for (var i = state.editors.length - 1; i >= 0; i--) {
          var editor = state.editors[i];
          if (editor && typeof editor.hasTextFocus === 'function' && editor.hasTextFocus()) {
            return editor;
          }
        }
        return state.activeEditor;
      }

      function findKnowledgeDialog(from) {
        var selectors = [
          '[role="dialog"]',
          '.ant-modal',
          '.arco-modal',
          '.n-modal',
          '.el-dialog',
          '.modal',
          '.modal-content'
        ].join(',');
        var candidates = [];
        if (from && from.closest) {
          var closest = from.closest(selectors);
          if (closest) candidates.push(closest);
        }
        Array.prototype.forEach.call(document.querySelectorAll(selectors), function (dialog) {
          if (candidates.indexOf(dialog) === -1) candidates.push(dialog);
        });

        for (var i = 0; i < candidates.length; i++) {
          var dialog = candidates[i];
          if (!visible(dialog)) continue;
          var text = normalizedText(dialog);
          var isKnowledge = text.indexOf('添加知识') !== -1
            || text.indexOf('编辑知识') !== -1
            || (text.indexOf('标题') !== -1 && text.indexOf('分类') !== -1 && text.indexOf('内容') !== -1);
          var isNotice = text.indexOf('添加公告') !== -1
            || text.indexOf('编辑公告') !== -1
            || (isNoticePage() && text.indexOf('标题') !== -1 && text.indexOf('内容') !== -1);
          if (isKnowledge || isNotice) return dialog;
        }
        return null;
      }

      function dialogUploadType(dialog) {
        if (!dialog) return isNoticePage() ? 'notice' : 'knowledge';
        var text = normalizedText(dialog);
        if (text.indexOf('添加公告') !== -1 || text.indexOf('编辑公告') !== -1 || isNoticePage()) {
          return 'notice';
        }
        return 'knowledge';
      }

      function toolbarSignal(button) {
        return [
          button.getAttribute('aria-label'),
          button.getAttribute('title'),
          button.getAttribute('data-tooltip'),
          button.getAttribute('data-title'),
          button.getAttribute('data-name'),
          button.className,
          button.innerHTML
        ].join(' ').toLowerCase();
      }

      function isImageToolbarButton(button) {
        if (!button || !button.matches || !button.matches('button,[role="button"],.button,[class*="button-type-"],span[title]')) return false;
        if (button.getAttribute('data-xboard-knowledge-image-upload') === '1') return true;
        var signal = toolbarSignal(button);
        return signal.indexOf('image') !== -1
          || signal.indexOf('picture') !== -1
          || signal.indexOf('photo') !== -1
          || signal.indexOf('图片') !== -1
          || signal.indexOf('图像') !== -1;
      }

      function findToolbars(dialog) {
        var controlSelector = 'button,[role="button"],.button,[class*="button-type-"],span[title]';
        var toolbars = Array.prototype.slice.call(dialog.querySelectorAll('[role="toolbar"], [class*="toolbar"], [class*="Toolbar"], .rc-md-navigation, [class*="navigation"]'))
          .filter(visible);

        if (toolbars.length) return toolbars;

        return Array.prototype.slice.call(dialog.querySelectorAll('div,section,header'))
          .filter(function (item) {
            return visible(item) && item.querySelectorAll(controlSelector).length >= 6;
          });
      }

      function markImageUploadButtons() {
        patchMonaco();
        if (!document.body) return;

        var dialogs = Array.prototype.slice.call(document.querySelectorAll('[role="dialog"], .ant-modal, .arco-modal, .n-modal, .el-dialog, .modal, .modal-content'))
          .map(findKnowledgeDialog)
          .filter(function (dialog, index, list) {
            return dialog && list.indexOf(dialog) === index;
          });

        dialogs.forEach(function (dialog) {
          var toolbars = findToolbars(dialog);
          var controls = [];
          toolbars.forEach(function (toolbar) {
            controls = controls.concat(Array.prototype.slice.call(toolbar.querySelectorAll('button,[role="button"],.button,[class*="button-type-"],span[title]')).filter(visible));
          });
          controls = controls.filter(function (control, index, list) {
            return list.indexOf(control) === index;
          });

          var imageButtons = controls.filter(function (button) {
            return button.getAttribute('data-xboard-knowledge-image-upload-fallback') !== '1'
              && isImageToolbarButton(button);
          });

          imageButtons.forEach(function (button) {
            button.setAttribute('data-xboard-knowledge-image-upload', '1');
            if (!button.getAttribute('title')) button.setAttribute('title', '上传图片');
            if (!button.getAttribute('aria-label')) button.setAttribute('aria-label', '上传图片');
            if (!button.getAttribute('role')) button.setAttribute('role', 'button');
            button.style.color = '#2563eb';
            button.style.cursor = 'pointer';
          });

          if (imageButtons.length) {
            Array.prototype.forEach.call(dialog.querySelectorAll('[data-xboard-knowledge-image-upload-fallback="1"]'), function (fallback) {
              fallback.remove();
            });
            mountNoticeImageUrlUploads(dialog);
            return;
          }

          if (toolbars[0] && !dialog.querySelector('[data-xboard-knowledge-image-upload-fallback="1"]')) {
            var fallback = document.createElement('button');
            fallback.type = 'button';
            fallback.textContent = '上传图片';
            fallback.setAttribute('data-xboard-knowledge-image-upload', '1');
            fallback.setAttribute('data-xboard-knowledge-image-upload-fallback', '1');
            fallback.setAttribute('title', '上传图片');
            fallback.style.cssText = [
              'height:28px',
              'padding:0 10px',
              'border:1px solid #cbd5e1',
              'border-radius:4px',
              'background:#fff',
              'color:#2563eb',
              'font-size:13px',
              'cursor:pointer'
            ].join(';');
            toolbars[0].appendChild(fallback);
          }

          mountNoticeImageUrlUploads(dialog);
        });
      }

      function inputSignal(input) {
        var labelText = '';
        if (input.id) {
          var label = document.querySelector('label[for="' + input.id.replace(/"/g, '\\"') + '"]');
          labelText = normalizedText(label);
        }
        var wrapper = input.closest ? input.closest('label,.ant-form-item,.arco-form-item,.n-form-item,.el-form-item,.form-item,.form-group') : null;
        return [
          labelText,
          normalizedText(wrapper),
          input.getAttribute('name'),
          input.getAttribute('id'),
          input.getAttribute('placeholder'),
          input.getAttribute('aria-label')
        ].join(' ').toLowerCase();
      }

      function isNoticeImageUrlInput(input) {
        if (!input || !input.matches || !input.matches('input')) return false;
        var type = String(input.getAttribute('type') || 'text').toLowerCase();
        if (['hidden', 'file', 'checkbox', 'radio', 'submit', 'button'].indexOf(type) !== -1) return false;
        var signal = inputSignal(input);
        if (signal.indexOf('img_url') !== -1 || signal.indexOf('image_url') !== -1) return true;
        return (signal.indexOf('图片') !== -1 || signal.indexOf('封面') !== -1 || signal.indexOf('image') !== -1 || signal.indexOf('cover') !== -1)
          && (signal.indexOf('url') !== -1 || signal.indexOf('链接') !== -1 || signal.indexOf('地址') !== -1);
      }

      function isNoticeImageField(input) {
        if (!input || !input.matches || !input.matches('input')) return false;
        var signal = inputSignal(input);
        return signal.indexOf('img_url') !== -1
          || signal.indexOf('image_url') !== -1
          || signal.indexOf('背景图片') !== -1
          || signal.indexOf('公告背景') !== -1;
      }

	      function findNoticeImageUrlInput(dialog) {
	        var inputs = Array.prototype.slice.call((dialog || document).querySelectorAll('input'));
	        for (var i = 0; i < inputs.length; i++) {
	          if (isNoticeImageField(inputs[i]) || isNoticeImageUrlInput(inputs[i])) return inputs[i];
	        }
	        return null;
	      }

	      function findNoticeTitleValue(dialog) {
	        var inputs = Array.prototype.slice.call((dialog || document).querySelectorAll('input')).filter(visible);
	        for (var i = 0; i < inputs.length; i++) {
	          if (inputs[i].closest && inputs[i].closest('[data-xboard-notice-background-panel="1"]')) continue;
	          var signal = inputSignal(inputs[i]);
	          if (signal.indexOf('标题') !== -1 || signal.indexOf('title') !== -1) {
	            return String(inputs[i].value || '').trim();
	          }
	        }
	        for (var j = 0; j < inputs.length; j++) {
	          if (inputs[j].closest && inputs[j].closest('[data-xboard-notice-background-panel="1"]')) continue;
	          var value = String(inputs[j].value || '').trim();
	          if (value) return value;
	        }
	        return '';
	      }

	      function inferNoticeImageUrl(dialog) {
	        var title = findNoticeTitleValue(dialog);
	        if (!title || !state.noticeItems.length) return '';
	        var matched = state.noticeItems.find(function (item) {
	          return item && String(item.title || '').trim() === title;
	        });
	        return matched && matched.img_url ? String(matched.img_url) : '';
	      }

      function setInputValue(input, value) {
        var descriptor = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value');
        if (descriptor && descriptor.set) {
          descriptor.set.call(input, value);
        } else {
          input.value = value;
        }
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
        input.focus();
      }

      function updateNoticeBackgroundPreview(panel, value) {
        var preview = panel && panel.querySelector('[data-xboard-notice-background-preview]');
        var clear = panel && panel.querySelector('[data-xboard-notice-background-clear]');
        var empty = panel && panel.querySelector('[data-xboard-notice-background-empty]');
        if (!preview || !clear || !empty) return;
        value = String(value || '').trim();
        clear.disabled = !value;
        clear.style.opacity = value ? '1' : '.48';
        if (!value) {
          preview.style.display = 'none';
          preview.removeAttribute('src');
          empty.style.display = 'flex';
          return;
        }
        preview.src = value;
        preview.style.display = '';
        empty.style.display = 'none';
      }

	      function hideNoticeBackgroundMirrorInput(input) {
	        if (!input || !input.closest || input.closest('[data-xboard-notice-background-panel="1"]')) return;
	        var formItem = closestFormItem(input);
	        var target = formItem && formItem !== document.body ? formItem : input.parentElement;
	        if (!target || target.getAttribute('data-xboard-notice-background-hidden') === '1') return;
	        target.setAttribute('data-xboard-notice-background-hidden', '1');
	        target.style.display = 'none';
	      }

      function createUrlUploadButton(input, mirrorInput) {
	        var button = document.createElement('button');
        button.type = 'button';
        button.textContent = '上传图片';
        button.setAttribute('data-xboard-notice-image-url-upload', '1');
        button.style.cssText = [
          'height:34px',
          'padding:0 14px',
          'border:1px solid #2563eb',
          'border-radius:6px',
          'background:#2563eb',
          'color:#fff',
          'font-size:14px',
          'font-weight:600',
          'cursor:pointer',
          'white-space:nowrap'
        ].join(';');
        button.__xboardUrlInput = input;
	        button.__xboardMirrorUrlInput = mirrorInput || null;
	        return button;
	      }

	      function closestFormItem(element) {
	        if (!element || !element.closest) return null;
	        return element.closest('.ant-form-item,.arco-form-item,.n-form-item,.el-form-item,.form-item,.form-group,label') || element.parentElement;
	      }

	      function findNoticeContentAnchor(dialog) {
	        var fields = Array.prototype.slice.call(dialog.querySelectorAll('textarea,.monaco-editor,.CodeMirror,[contenteditable="true"],.cm-editor'));
	        for (var i = 0; i < fields.length; i++) {
	          if (!visible(fields[i])) continue;
	          var item = closestFormItem(fields[i]);
	          return item || fields[i];
	        }
	        return null;
	      }

	      function findNoticeSubmitButton(dialog) {
	        var buttons = Array.prototype.slice.call(dialog.querySelectorAll('button,[role="button"]')).filter(visible);
	        var blocked = /取消|关闭|返回|上传|清除|删除|预览|Apple ID/i;
	        var preferred = /发布公告|保存公告|保存|确定|确认|提交|发布|新增|添加/i;
	        for (var i = buttons.length - 1; i >= 0; i--) {
	          var button = buttons[i];
	          if (button.getAttribute('data-xboard-notice-publish-button') === '1') continue;
	          var text = normalizedText(button);
	          if (!text || blocked.test(text)) continue;
	          if (preferred.test(text) || String(button.getAttribute('type') || '').toLowerCase() === 'submit') {
	            return button;
	          }
	        }
	        return null;
	      }

	      function ensureNoticePublishActions(dialog) {
	        if (!dialog || dialogUploadType(dialog) !== 'notice') return;
	        var existing = dialog.querySelector('[data-xboard-notice-actions="1"]');
	        var originalButton = findNoticeSubmitButton(dialog);
	        if (originalButton) {
	          if (existing) existing.remove();
	          return;
	        }
	        if (existing) {
	          var existingButton = existing.querySelector('[data-xboard-notice-publish-button="1"]');
	          if (existingButton) existingButton.textContent = /编辑公告/.test(normalizedText(dialog)) ? '保存公告' : '发布公告';
	          return;
	        }

	        var actions = document.createElement('div');
	        actions.setAttribute('data-xboard-notice-actions', '1');
	        actions.style.cssText = [
	          'position:sticky',
	          'bottom:0',
	          'z-index:20',
	          'display:flex',
	          'align-items:center',
	          'justify-content:flex-end',
	          'gap:10px',
	          'margin:16px -2px -2px',
	          'padding:12px 0 0',
	          'background:linear-gradient(180deg, rgba(255,255,255,.72), #fff 34%)'
	        ].join(';');
	        actions.innerHTML = [
	          '<button data-xboard-notice-publish-button="1" type="button" style="height:38px;min-width:112px;border:0;border-radius:8px;background:#111827;color:#fff;font-size:14px;font-weight:700;box-shadow:0 10px 22px rgba(15,23,42,.16);cursor:pointer">',
	          /编辑公告/.test(normalizedText(dialog)) ? '保存公告' : '发布公告',
	          '</button>'
	        ].join('');

	        actions.querySelector('button').addEventListener('click', function () {
	          var button = findNoticeSubmitButton(dialog) || originalButton;
	          if (!button) {
	            notify('error', '未找到原始发布按钮，请刷新后台后重试');
	            return;
	          }
	          button.click();
	        });

	        var footer = dialog.querySelector('.ant-modal-footer,.arco-modal-footer,.n-card__footer,.el-dialog__footer');
	        if (footer) {
	          footer.appendChild(actions);
	        } else {
	          var target = dialog.querySelector('form') || dialog.querySelector('.ant-modal-body,.arco-modal-content,.n-card__content,.el-dialog__body') || dialog;
	          target.appendChild(actions);
	        }
	      }

	      function ensureNoticeBackgroundPanel(dialog, mirrorInput) {
	        hideNoticeBackgroundMirrorInput(mirrorInput);
	        var existing = dialog.querySelector('[data-xboard-notice-background-panel="1"]');
	        if (existing) {
          var existingInput = existing.querySelector('[data-xboard-notice-background-input="1"]');
          hideNoticeBackgroundMirrorInput(mirrorInput);
          if (existingInput && mirrorInput && !existingInput.value && mirrorInput.value) {
            setInputValue(existingInput, mirrorInput.value);
          }
          updateNoticeBackgroundPreview(existing, existingInput ? existingInput.value : '');
          return existingInput;
        }

	        var panel = document.createElement('div');
	        panel.setAttribute('data-xboard-notice-background-panel', '1');
	        panel.style.cssText = [
	          'margin:16px 0 0',
	          'padding:0',
	          'color:#111827',
	          'box-sizing:border-box',
	          'width:100%'
	        ].join(';');
	        panel.innerHTML = [
	          '<div style="margin-bottom:8px;color:#64748b;font-size:14px;font-weight:700;line-height:1.4">公告背景图片</div>',
	          '<div style="margin-bottom:8px;color:#94a3b8;font-size:12px;line-height:1.5">上传或粘贴图片 URL 后，用户端公告卡片将显示这张背景图。</div>',
	          '<div style="display:flex;align-items:flex-start;gap:12px;flex-wrap:wrap">',
	          '  <div style="flex:1 1 520px;min-width:0;max-width:620px">',
	          '    <div data-xboard-notice-background-row="1" style="display:flex;align-items:center;gap:8px;flex-wrap:nowrap">',
	          '      <input data-xboard-notice-background-input="1" type="text" placeholder="图片 URL" style="flex:1 1 auto;min-width:0;height:38px;border:1px solid #dbe3ef;border-radius:8px;padding:0 12px;background:#fff;color:#111827;outline:none;box-shadow:0 1px 2px rgba(15,23,42,.04)">',
	          '    </div>',
	          '    <div data-xboard-notice-background-empty style="margin-top:10px;height:92px;border:1px dashed #cbd5e1;border-radius:8px;background:#f8fafc;display:flex;align-items:center;justify-content:center;padding:12px;text-align:center;color:#94a3b8;font-size:12px;line-height:1.5">暂无背景图，可上传或粘贴图片 URL。</div>',
	          '    <img data-xboard-notice-background-preview style="display:none;margin-top:10px;width:100%;height:120px;border-radius:8px;border:1px solid #dbe3ef;background:#f8fafc;object-fit:cover">',
	          '  </div>',
	          '</div>',
	        ].join('');

        var panelInput = panel.querySelector('[data-xboard-notice-background-input="1"]');
	        var initialImageUrl = mirrorInput && mirrorInput.value ? mirrorInput.value : inferNoticeImageUrl(dialog);
	        if (initialImageUrl) panelInput.value = initialImageUrl;
        var uploadButton = createUrlUploadButton(panelInput, mirrorInput);
        var clearButton = document.createElement('button');
        clearButton.type = 'button';
        clearButton.textContent = '清除';
        clearButton.setAttribute('data-xboard-notice-background-clear', '1');
        clearButton.style.cssText = [
          'height:34px',
          'padding:0 12px',
          'border:1px solid #d1d5db',
          'border-radius:6px',
          'background:#f9fafb',
          'color:#4b5563',
          'font-size:13px',
          'font-weight:600',
          'cursor:pointer',
          'white-space:nowrap'
        ].join(';');
        var panelRow = panel.querySelector('[data-xboard-notice-background-row="1"]');
        panelRow.appendChild(uploadButton);
        panelRow.appendChild(clearButton);

        panelInput.addEventListener('input', function () {
          if (mirrorInput && mirrorInput !== panelInput) setInputValue(mirrorInput, panelInput.value);
          updateNoticeBackgroundPreview(panel, panelInput.value);
        });
        clearButton.addEventListener('click', function () {
          setInputValue(panelInput, '');
          if (mirrorInput && mirrorInput !== panelInput) setInputValue(mirrorInput, '');
          updateNoticeBackgroundPreview(panel, '');
        });

	        var anchor = findNoticeContentAnchor(dialog);
	        if (anchor && anchor.parentElement) {
	          anchor.insertAdjacentElement('afterend', panel);
	        } else {
	          var target = dialog.querySelector('form') || dialog.querySelector('.ant-modal-body,.arco-modal-content,.n-card__content,.el-dialog__body') || dialog;
	          target.appendChild(panel);
	        }
	        updateNoticeBackgroundPreview(panel, panelInput.value);
	        return panelInput;
	      }

	      function mountNoticeImageUrlUploads(dialog) {
	        if (dialogUploadType(dialog) !== 'notice') return;
	        var mirrorInput = findNoticeImageUrlInput(dialog);
	        var panelInput = ensureNoticeBackgroundPanel(dialog, mirrorInput);
	        ensureNoticePublishActions(dialog);
	        if (panelInput) return;

        Array.prototype.forEach.call(dialog.querySelectorAll('input'), function (input) {
          if (input.closest && input.closest('[data-xboard-notice-background-panel="1"]')) return;
          if (!visible(input) || !isNoticeImageUrlInput(input)) return;
          if (input.getAttribute('data-xboard-notice-image-url-bound') === '1') return;
          input.setAttribute('data-xboard-notice-image-url-bound', '1');

          var button = createUrlUploadButton(input);
          var parent = input.parentElement;
          if (parent && parent.style) {
            var display = window.getComputedStyle(parent).display;
            if (display !== 'flex' && display !== 'inline-flex') {
              parent.style.display = 'flex';
              parent.style.alignItems = 'center';
              parent.style.gap = parent.style.gap || '8px';
            }
          }
          input.insertAdjacentElement('afterend', button);
        });
      }

      function setTextareaValue(textarea, value) {
        var descriptor = Object.getOwnPropertyDescriptor(window.HTMLTextAreaElement.prototype, 'value');
        if (descriptor && descriptor.set) {
          descriptor.set.call(textarea, value);
        } else {
          textarea.value = value;
        }
      }

      function insertIntoTextArea(markdown, scope) {
        var active = state.activeElement || document.activeElement;
        var textarea = active && active.tagName === 'TEXTAREA' && (!scope || scope.contains(active)) ? active : null;

        if (!textarea) {
          var root = scope || document;
          var textareas = Array.prototype.slice.call(root.querySelectorAll('textarea'))
            .filter(function (item) {
              return visible(item) && !item.disabled && !item.readOnly;
            })
            .sort(function (a, b) {
              return b.clientHeight - a.clientHeight;
            });
          textarea = textareas[0] || null;
        }

        if (!textarea) return false;

        var start = textarea.selectionStart || 0;
        var end = textarea.selectionEnd || start;
        var value = textarea.value || '';
        var prefix = start > 0 && value.charAt(start - 1) !== '\n' ? '\n' : '';
        var suffix = value.charAt(end) && value.charAt(end) !== '\n' ? '\n' : '';
        var text = prefix + markdown + suffix;
        setTextareaValue(textarea, value.slice(0, start) + text + value.slice(end));
        textarea.selectionStart = textarea.selectionEnd = start + text.length;
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
        textarea.dispatchEvent(new Event('change', { bubbles: true }));
        textarea.focus();
        return true;
      }

      function insertIntoCodeMirror(markdown, scope) {
        var active = state.activeElement || document.activeElement;
        var root = scope || document;
        var cmRoot = active && active.closest ? active.closest('.CodeMirror') : null;
        cmRoot = cmRoot || root.querySelector('.CodeMirror-focused') || root.querySelector('.CodeMirror');
        if (cmRoot && cmRoot.CodeMirror && typeof cmRoot.CodeMirror.replaceSelection === 'function') {
          cmRoot.CodeMirror.focus();
          cmRoot.CodeMirror.replaceSelection('\n' + markdown + '\n');
          return true;
        }
        return false;
      }

      function insertIntoContentEditable(markdown, scope) {
        var active = state.activeElement || document.activeElement;
        var root = scope || document;
        var editable = active && active.isContentEditable && (!scope || scope.contains(active))
          ? active
          : root.querySelector('[contenteditable="true"], .cm-content');
        if (editable && document.execCommand) {
          editable.focus();
          return document.execCommand('insertText', false, '\n' + markdown + '\n');
        }
        return false;
      }

      function insertMarkdown(markdown) {
        var scope = state.activeDialog || findKnowledgeDialog(document.activeElement);
        var editor = getFocusedMonacoEditor();
        if (editor && typeof editor.executeEdits === 'function' && typeof editor.getSelection === 'function') {
          editor.focus();
          editor.executeEdits('admin-image-upload', [{
            range: editor.getSelection(),
            text: '\n' + markdown + '\n',
            forceMoveMarkers: true
          }]);
          return true;
        }

        return insertIntoCodeMirror(markdown, scope)
          || insertIntoTextArea(markdown, scope)
          || insertIntoContentEditable(markdown, scope);
      }

      function copyMarkdown(markdown) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
          return navigator.clipboard.writeText(markdown).then(function () {
            return true;
          }).catch(function () {
            return false;
          });
        }
        return Promise.resolve(false);
      }

      function uploadImage(file) {
        var formData = new FormData();
        formData.append('image', file);
        var type = dialogUploadType(state.activeDialog);
        return apiFetch('/' + type + '/upload-image', {
          method: 'POST',
          body: formData
        });
      }

      function resetUploadState() {
        state.uploading = false;
        if (state.activeTrigger) {
          state.activeTrigger.disabled = false;
          if (state.activeTrigger.getAttribute('data-xboard-knowledge-image-upload-fallback') === '1') {
            state.activeTrigger.textContent = '上传图片';
          }
          if (state.activeTrigger.getAttribute('data-xboard-notice-image-url-upload') === '1') {
            state.activeTrigger.textContent = '上传图片';
          }
        }
        state.activeUrlInput = null;
        state.activeMirrorUrlInput = null;
        if (state.input) state.input.value = '';
      }

      function handleFile(file) {
        if (!file || state.uploading) return;
        state.uploading = true;
        if (state.activeTrigger) {
          state.activeTrigger.disabled = true;
          if (state.activeTrigger.getAttribute('data-xboard-knowledge-image-upload-fallback') === '1') {
            state.activeTrigger.textContent = '上传中...';
          }
          if (state.activeTrigger.getAttribute('data-xboard-notice-image-url-upload') === '1') {
            state.activeTrigger.textContent = '上传中...';
          }
        }

        uploadImage(file).then(function (data) {
          if (state.activeUrlInput) {
            var url = data.absolute_url || data.url;
            if (!url) throw new Error('上传成功，但未返回图片地址');
            setInputValue(state.activeUrlInput, url);
            if (state.activeMirrorUrlInput && state.activeMirrorUrlInput !== state.activeUrlInput) {
              setInputValue(state.activeMirrorUrlInput, url);
            }
            var panel = state.activeUrlInput.closest ? state.activeUrlInput.closest('[data-xboard-notice-background-panel="1"]') : null;
            updateNoticeBackgroundPreview(panel, url);
            notify('success', '图片已上传并填入公告图片地址');
            return;
          }

          var markdown = data.markdown || (data.url ? '![图片](' + data.url + ')' : '');
          if (!markdown) throw new Error('上传成功，但未返回图片地址');
          if (insertMarkdown(markdown)) {
            notify('success', '图片已上传并插入文档');
            return;
          }
          return copyMarkdown(markdown).then(function (copied) {
            notify(copied ? 'success' : 'warning', copied ? '图片已上传，Markdown 已复制' : '图片已上传，请复制返回链接后插入');
          });
        }).catch(function (error) {
          notify('error', error.message || '图片上传失败');
        }).finally(resetUploadState);
      }

      function ensureUploadInput() {
        if (state.input) return state.input;
        var input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/jpeg,image/png,image/gif,image/webp';
        input.style.display = 'none';
        input.addEventListener('change', function () {
          handleFile(input.files && input.files[0]);
        });
        document.body.appendChild(input);
        state.input = input;
        return input;
      }

      function handleToolbarClick(event) {
        var button = event.target && event.target.closest
          ? event.target.closest('[data-xboard-knowledge-image-upload="1"],[data-xboard-notice-image-url-upload="1"]')
          : null;
        if (!button) return;
        var dialog = findKnowledgeDialog(button);
        if (!dialog) return;

        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation();
        state.activeDialog = dialog;
        state.activeTrigger = button;
        state.activeUrlInput = button.getAttribute('data-xboard-notice-image-url-upload') === '1'
          ? button.__xboardUrlInput || (button.previousElementSibling && button.previousElementSibling.matches('input') ? button.previousElementSibling : null)
          : null;
        state.activeMirrorUrlInput = button.getAttribute('data-xboard-notice-image-url-upload') === '1'
          ? button.__xboardMirrorUrlInput || null
          : null;
        state.activeElement = document.activeElement;
        state.activeEditor = getFocusedMonacoEditor() || state.activeEditor;
        ensureUploadInput().click();
      }

      function findAddKnowledgeButton() {
        var buttons = Array.prototype.slice.call(document.querySelectorAll('button,[role="button"]')).filter(visible);
        for (var i = 0; i < buttons.length; i++) {
          var text = normalizedText(buttons[i]);
          if (text.indexOf('添加知识') !== -1 || /add knowledge/i.test(text)) return buttons[i];
        }
        return null;
      }

      function createAppleButton(anchor) {
        var button = document.createElement('button');
        button.id = 'xboard-apple-id-manage-button';
        button.type = 'button';
        button.textContent = 'Apple ID';
        button.className = anchor && anchor.className ? anchor.className : '';
        button.style.cssText = [
          'margin-left:8px',
          'height:36px',
          'padding:0 14px',
          'border:1px solid #cbd5e1',
          'border-radius:6px',
          'background:#fff',
          'color:#111827',
          'font-size:14px',
          'font-weight:600',
          'cursor:pointer'
        ].join(';');
        button.addEventListener('click', openAppleModal);
        return button;
      }

      function mountAppleButton() {
        var existing = document.getElementById('xboard-apple-id-manage-button');
        if (!isKnowledgePage()) {
          if (existing) existing.remove();
          return;
        }

        var anchor = findAddKnowledgeButton();
        if (!anchor || !anchor.parentElement) return;
        if (existing && existing.parentElement === anchor.parentElement) return;
        if (existing) existing.remove();
        anchor.insertAdjacentElement('afterend', createAppleButton(anchor));
      }

      function modalStyles() {
        if (document.getElementById('xboard-apple-id-style')) return;
        var style = document.createElement('style');
        style.id = 'xboard-apple-id-style';
        style.textContent = [
          '#xboard-apple-id-modal{position:fixed;inset:0;z-index:100000;background:rgba(15,23,42,.46);display:flex;align-items:center;justify-content:center;padding:24px}',
          '#xboard-apple-id-modal *{box-sizing:border-box}',
          '#xboard-apple-id-panel{width:min(920px,96vw);max-height:88vh;background:#fff;border-radius:8px;box-shadow:0 24px 70px rgba(15,23,42,.32);display:flex;flex-direction:column;overflow:hidden;color:#0f172a}',
          '#xboard-apple-id-head{height:58px;display:flex;align-items:center;justify-content:space-between;padding:0 20px;border-bottom:1px solid #e5e7eb}',
          '#xboard-apple-id-head strong{font-size:17px}',
          '#xboard-apple-id-body{display:grid;grid-template-columns:minmax(220px,280px) 1fr;gap:18px;padding:18px;overflow:auto}',
          '#xboard-apple-id-list{display:flex;flex-direction:column;gap:8px}',
          '.xboard-apple-card{width:100%;text-align:left;border:1px solid #e5e7eb;background:#fff;border-radius:6px;padding:10px 12px;cursor:pointer}',
          '.xboard-apple-card.active{border-color:#2563eb;background:#eff6ff}',
          '.xboard-apple-card small{display:block;color:#64748b;margin-top:4px;word-break:break-all}',
          '#xboard-apple-id-form{display:grid;grid-template-columns:1fr 1fr;gap:12px}',
          '#xboard-apple-id-form label{display:flex;flex-direction:column;gap:6px;font-size:13px;font-weight:600;color:#334155}',
          '#xboard-apple-id-form input,#xboard-apple-id-form textarea{width:100%;border:1px solid #cbd5e1;border-radius:6px;padding:9px 10px;font-size:14px;color:#0f172a}',
          '#xboard-apple-id-form textarea{min-height:78px;resize:vertical}',
          '.xboard-apple-span{grid-column:1/-1}',
          '.xboard-apple-actions{grid-column:1/-1;display:flex;gap:10px;justify-content:flex-end;margin-top:4px}',
          '.xboard-apple-actions button,#xboard-apple-id-head button,#xboard-apple-id-new{height:36px;border:1px solid #cbd5e1;border-radius:6px;background:#fff;padding:0 14px;cursor:pointer;font-weight:600}',
          '.xboard-apple-actions button.primary{background:#111827;color:#fff;border-color:#111827}',
          '.xboard-apple-actions button.danger{color:#b91c1c}',
          '#xboard-apple-id-placeholders{grid-column:1/-1;display:flex;flex-wrap:wrap;gap:8px;margin-top:2px}',
          '#xboard-apple-id-placeholders button{height:30px;border:1px solid #bfdbfe;background:#eff6ff;color:#1d4ed8;border-radius:6px;padding:0 9px;cursor:pointer;font-size:12px}',
          '@media (max-width:760px){#xboard-apple-id-modal{padding:12px;align-items:flex-start}#xboard-apple-id-body{grid-template-columns:1fr}#xboard-apple-id-form{grid-template-columns:1fr}}'
        ].join('\n');
        document.head.appendChild(style);
      }

      function openAppleModal() {
        modalStyles();
        if (!state.appleModal) {
          state.appleModal = document.createElement('div');
          state.appleModal.id = 'xboard-apple-id-modal';
          state.appleModal.innerHTML = [
            '<div id="xboard-apple-id-panel">',
            '  <div id="xboard-apple-id-head"><strong>Apple ID</strong><button type="button" data-apple-close>关闭</button></div>',
            '  <div id="xboard-apple-id-body">',
            '    <div><button type="button" id="xboard-apple-id-new">新增 Apple ID</button><div id="xboard-apple-id-list"></div></div>',
            '    <form id="xboard-apple-id-form">',
            '      <label>标签<input name="label" placeholder="Shadowrocket 下载账号"></label>',
            '      <label>区域<input name="region" placeholder="US"></label>',
            '      <label class="xboard-apple-span">Apple ID<input name="account" autocomplete="off" required></label>',
            '      <label class="xboard-apple-span">密码<input name="password" autocomplete="new-password" required></label>',
            '      <label class="xboard-apple-span">备注<textarea name="note"></textarea></label>',
            '      <label><span>启用</span><input name="enabled" type="checkbox" checked></label>',
            '      <div id="xboard-apple-id-placeholders"></div>',
            '      <div class="xboard-apple-actions"><button type="button" class="danger" data-apple-delete>删除</button><button type="submit" class="primary">保存</button></div>',
            '    </form>',
            '  </div>',
            '</div>'
          ].join('');
          document.body.appendChild(state.appleModal);
          bindAppleModalEvents();
        }
        state.appleModal.style.display = 'flex';
        loadAppleAccounts();
      }

      function closeAppleModal() {
        if (state.appleModal) state.appleModal.style.display = 'none';
      }

      function bindAppleModalEvents() {
        state.appleModal.addEventListener('click', function (event) {
          if (event.target === state.appleModal || event.target.hasAttribute('data-apple-close')) {
            closeAppleModal();
          }
        });
        state.appleModal.querySelector('#xboard-apple-id-new').addEventListener('click', function () {
          state.appleActiveId = null;
          renderAppleModal();
        });
        state.appleModal.querySelector('[data-apple-delete]').addEventListener('click', deleteAppleAccount);
        state.appleModal.querySelector('#xboard-apple-id-form').addEventListener('submit', saveAppleAccount);
      }

      function loadAppleAccounts() {
        apiFetch('/apple-id/fetch').then(function (accounts) {
          state.appleAccounts = Array.isArray(accounts) ? accounts : [];
          if (state.appleActiveId && !state.appleAccounts.some(function (item) { return item.id === state.appleActiveId; })) {
            state.appleActiveId = null;
          }
          renderAppleModal();
        }).catch(function (error) {
          notify('error', error.message || 'Apple ID 加载失败');
          renderAppleModal();
        });
      }

      function selectedAppleAccount() {
        return state.appleAccounts.find(function (item) {
          return item.id === state.appleActiveId;
        }) || null;
      }

      function renderAppleModal() {
        if (!state.appleModal) return;
        var list = state.appleModal.querySelector('#xboard-apple-id-list');
        list.innerHTML = '';
        state.appleAccounts.forEach(function (account) {
          var card = document.createElement('button');
          card.type = 'button';
          card.className = 'xboard-apple-card' + (account.id === state.appleActiveId ? ' active' : '');
          card.innerHTML = '<strong>' + escapeHtml(account.label || account.region || 'Apple ID') + '</strong>'
            + '<small>' + escapeHtml(account.account || '') + '</small>'
            + '<small>' + (account.enabled ? '已启用' : '已停用') + '</small>';
          card.addEventListener('click', function () {
            state.appleActiveId = account.id;
            renderAppleModal();
          });
          list.appendChild(card);
        });

        var form = state.appleModal.querySelector('#xboard-apple-id-form');
        var current = selectedAppleAccount() || { label: '', region: '', account: '', password: '', note: '', enabled: true };
        form.elements.label.value = current.label || '';
        form.elements.region.value = current.region || '';
        form.elements.account.value = current.account || '';
        form.elements.password.value = current.password || '';
        form.elements.note.value = current.note || '';
        form.elements.enabled.checked = current.enabled !== false;
        form.querySelector('[data-apple-delete]').style.display = current.id ? '' : 'none';
        renderPlaceholderButtons();
      }

      function renderPlaceholderButtons() {
        var wrap = state.appleModal.querySelector('#xboard-apple-id-placeholders');
        wrap.innerHTML = '';
        ['@{{appleIdAccount}}', '@{{appleIdPassword}}', '@{{appleIds}}'].forEach(function (placeholder) {
          var button = document.createElement('button');
          button.type = 'button';
          button.textContent = placeholder;
          button.addEventListener('click', function () {
            copyText(placeholder).then(function (copied) {
              notify(copied ? 'success' : 'warning', copied ? '占位符已复制' : '复制失败');
            });
          });
          wrap.appendChild(button);
        });
      }

      function saveAppleAccount(event) {
        event.preventDefault();
        var form = state.appleModal.querySelector('#xboard-apple-id-form');
        var payload = {
          id: state.appleActiveId,
          label: form.elements.label.value,
          region: form.elements.region.value,
          account: form.elements.account.value,
          password: form.elements.password.value,
          note: form.elements.note.value,
          enabled: form.elements.enabled.checked
        };
        apiFetch('/apple-id/save', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        }).then(function (accounts) {
          state.appleAccounts = Array.isArray(accounts) ? accounts : [];
          var matched = state.appleAccounts.find(function (item) {
            return item.account === payload.account;
          });
          state.appleActiveId = matched ? matched.id : state.appleActiveId;
          renderAppleModal();
          notify('success', 'Apple ID 已保存');
        }).catch(function (error) {
          notify('error', error.message || 'Apple ID 保存失败');
        });
      }

      function deleteAppleAccount() {
        if (!state.appleActiveId) return;
        if (!window.confirm('确认删除这个 Apple ID？')) return;
        apiFetch('/apple-id/drop', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ id: state.appleActiveId })
        }).then(function (accounts) {
          state.appleAccounts = Array.isArray(accounts) ? accounts : [];
          state.appleActiveId = null;
          renderAppleModal();
          notify('success', 'Apple ID 已删除');
        }).catch(function (error) {
          notify('error', error.message || 'Apple ID 删除失败');
        });
      }

      function copyText(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
          return navigator.clipboard.writeText(text).then(function () {
            return true;
          }).catch(function () {
            return false;
          });
        }
        return Promise.resolve(false);
      }

      function escapeHtml(value) {
        return String(value == null ? '' : value)
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;')
          .replace(/"/g, '&quot;')
          .replace(/'/g, '&#039;');
      }

      function themeEntries() {
        return Object.keys(state.themeItems || {}).map(function (name) {
          var item = state.themeItems[name] || {};
          item.__name = item.name || name;
          return item;
        });
      }

      function findThemeContainer(themeName) {
        var selectors = [
          'tr',
          'li',
          '.ant-card',
          '.arco-card',
          '.n-card',
          '.el-card',
          '[class*="card"]',
          '[class*="Card"]',
          '[class*="item"]',
          '[class*="Item"]',
          'section',
          'article',
          'div'
        ].join(',');
        var best = null;
        var bestArea = Infinity;
        var bestAction = null;
        var bestActionArea = Infinity;
        var nodes = Array.prototype.slice.call(document.querySelectorAll(selectors));
        for (var i = 0; i < nodes.length; i++) {
          var node = nodes[i];
          if (!visible(node)) continue;
          if (node.closest && node.closest('[data-xboard-theme-delete-button="1"]')) continue;
          var text = normalizedText(node);
          if (!text || text.indexOf(themeName) === -1) continue;
          var rect = node.getBoundingClientRect();
          if (rect.height < 28 || rect.width < 120 || rect.height > 520) continue;
          var area = rect.width * rect.height;
          if (text.indexOf('主题设置') !== -1 || /theme settings?/i.test(text)) {
            if (area < bestActionArea) {
              bestAction = node;
              bestActionArea = area;
            }
            continue;
          }
          if (area < bestArea) {
            best = node;
            bestArea = area;
          }
        }
        return bestAction || best;
      }

      function createThemeDeleteButton(themeName) {
        var button = document.createElement('button');
        button.type = 'button';
        button.textContent = '删除主题';
        button.setAttribute('data-xboard-theme-delete-button', '1');
        button.setAttribute('data-theme-name', themeName);
        button.style.cssText = [
          'height:32px',
          'padding:0 12px',
          'border:1px solid #fecaca',
          'border-radius:6px',
          'background:#fff',
          'color:#dc2626',
          'font-size:13px',
          'font-weight:700',
          'cursor:pointer',
          'white-space:nowrap'
        ].join(';');
        return button;
      }

      function appendThemeDeleteButton(container, themeName) {
        if (!container) return;
        var existingButtons = container.querySelectorAll('[data-xboard-theme-delete-button="1"]');
        for (var i = 0; i < existingButtons.length; i++) {
          if (existingButtons[i].getAttribute('data-theme-name') === themeName) {
            existingButtons[i].remove();
          }
        }
        var button = createThemeDeleteButton(themeName);
        button.style.marginRight = '8px';
        var tag = String(container.tagName || '').toLowerCase();
        if (tag === 'tr') {
          var cells = container.querySelectorAll('td,th');
          var targetCell = cells[cells.length - 1] || null;
          if (!targetCell) {
            targetCell = document.createElement('td');
            container.appendChild(targetCell);
          }
          targetCell.appendChild(button);
          return;
        }

        var settingButton = Array.prototype.slice.call(container.querySelectorAll('button,[role="button"],a'))
          .filter(visible)
          .find(function (item) {
            var text = normalizedText(item);
            return text.indexOf('主题设置') !== -1 || /theme settings?/i.test(text);
          });
        if (settingButton && settingButton.parentElement) {
          var buttonRow = settingButton.parentElement;
          if (buttonRow.style) {
            buttonRow.style.display = 'flex';
            buttonRow.style.alignItems = 'center';
            buttonRow.style.gap = buttonRow.style.gap || '8px';
            buttonRow.style.flexWrap = buttonRow.style.flexWrap || 'wrap';
          }
          buttonRow.insertBefore(button, settingButton);
          return;
        }

        var actionTarget = Array.prototype.slice.call(container.querySelectorAll('[class*="action"],[class*="Action"],[class*="footer"],[class*="Footer"]'))
          .filter(visible)
          .pop();
        if (!actionTarget) {
          actionTarget = Array.prototype.slice.call(container.querySelectorAll('button,[role="button"]'))
            .filter(visible)
            .map(function (item) { return item.parentElement; })
            .filter(Boolean)
            .pop();
        }
        actionTarget = actionTarget || container;
        var rightButtons = Array.prototype.slice.call(actionTarget.children || [])
          .filter(function (item) {
            var tagName = String(item.tagName || '').toLowerCase();
            var role = item.getAttribute && item.getAttribute('role');
            return item !== button
              && ['button', 'a', 'span', 'div'].indexOf(tagName) !== -1
              && role !== 'presentation'
              && visible(item)
              && item.getAttribute('data-xboard-theme-actions-left') !== '1';
          });
        var leftGroup = actionTarget.querySelector('[data-xboard-theme-actions-left="1"]');
        var rightGroup = actionTarget.querySelector('[data-xboard-theme-actions-right="1"]');
        if (!leftGroup || !rightGroup) {
          leftGroup = document.createElement('div');
          rightGroup = document.createElement('div');
          leftGroup.setAttribute('data-xboard-theme-actions-left', '1');
          rightGroup.setAttribute('data-xboard-theme-actions-right', '1');
          rightButtons.forEach(function (item) {
            rightGroup.appendChild(item);
          });
          actionTarget.appendChild(leftGroup);
          actionTarget.appendChild(rightGroup);
        }
        actionTarget.setAttribute('data-xboard-theme-actions', '1');
        leftGroup.appendChild(button);
      }

      function mountThemeDeleteButtons() {
        if (!isThemePage()) {
          Array.prototype.forEach.call(document.querySelectorAll('[data-xboard-theme-delete-button="1"]'), function (button) {
            button.remove();
          });
          return;
        }

        themeEntries().forEach(function (theme) {
          var name = theme.__name;
          if (!name || !theme.can_delete || name === state.activeTheme) return;
          appendThemeDeleteButton(findThemeContainer(name), name);
        });
      }

      function handleThemeDeleteClick(event) {
        var button = event.target && event.target.closest
          ? event.target.closest('[data-xboard-theme-delete-button="1"]')
          : null;
        if (!button) return;
        var themeName = button.getAttribute('data-theme-name');
        if (!themeName) return;
        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation();
        if (!window.confirm('确认删除主题「' + themeName + '」？删除后主题文件和公开资源会被移除。')) return;

        button.disabled = true;
        button.textContent = '删除中...';
        apiFetch('/theme/delete', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ name: themeName })
        }).then(function () {
          notify('success', '主题已删除');
          delete state.themeItems[themeName];
          window.setTimeout(function () {
            window.location.reload();
          }, 500);
        }).catch(function (error) {
          button.disabled = false;
          button.textContent = '删除主题';
          notify('error', error.message || '主题删除失败');
        });
      }

      function mountKnowledgeTools() {
        ensureAdminUiStyles();
        mountThemeDeleteButtons();
        if (!isKnowledgePage() && !isNoticePage()) {
          var existingAppleButton = document.getElementById('xboard-apple-id-manage-button');
          if (existingAppleButton) existingAppleButton.remove();
          return;
        }
        markImageUploadButtons();
        mountAppleButton();
      }

      function scheduleKnowledgeTools(delay) {
        if (state.toolsScheduled) return;
        state.toolsScheduled = true;
        window.setTimeout(function () {
          state.toolsScheduled = false;
          var now = Date.now();
          if (now - state.lastToolsRunAt < 120) {
            scheduleKnowledgeTools(120);
            return;
          }
          state.lastToolsRunAt = now;
          mountKnowledgeTools();
        }, delay || 80);
      }

      patchRequestAuthCapture();
      window.setInterval(patchMonaco, 250);
      document.addEventListener('click', handleToolbarClick, true);
      document.addEventListener('click', handleThemeDeleteClick, true);

      document.addEventListener('DOMContentLoaded', function () {
        new MutationObserver(function () {
          scheduleKnowledgeTools(80);
        }).observe(document.body, { childList: true, subtree: true });
        window.addEventListener('hashchange', function () {
          scheduleKnowledgeTools(0);
        });
        window.setInterval(function () {
          scheduleKnowledgeTools(0);
        }, 1500);
        scheduleKnowledgeTools(0);
      });
    })();
  </script>
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
