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
        appleActiveId: null
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

      function patchRequestAuthCapture() {
        if (window.__xboardKnowledgeAuthPatched) return;
        window.__xboardKnowledgeAuthPatched = true;

        if (window.XMLHttpRequest) {
          var originalSetRequestHeader = XMLHttpRequest.prototype.setRequestHeader;
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
            return originalFetch.apply(this, arguments);
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

      function normalizedText(element) {
        return String((element && (element.innerText || element.textContent)) || '').replace(/\s+/g, ' ');
      }

      function isKnowledgePage() {
        var route = String(location.pathname || '') + String(location.hash || '');
        if (/knowledge/i.test(route)) return true;
        return document.body && /知识库|Knowledge Base/i.test(normalizedText(document.body));
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
          if (isKnowledge) return dialog;
        }
        return null;
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
          editor.executeEdits('knowledge-image-upload', [{
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
        return apiFetch('/knowledge/upload-image', {
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
        }
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
        }

        uploadImage(file).then(function (data) {
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
        markImageUploadButtons();
        var button = event.target && event.target.closest
          ? event.target.closest('[data-xboard-knowledge-image-upload="1"]')
          : null;
        if (!button) return;
        var dialog = findKnowledgeDialog(button);
        if (!dialog) return;

        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation();
        state.activeDialog = dialog;
        state.activeTrigger = button;
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

      function mountKnowledgeTools() {
        markImageUploadButtons();
        mountAppleButton();
      }

      patchRequestAuthCapture();
      window.setInterval(patchMonaco, 250);
      document.addEventListener('click', handleToolbarClick, true);

      document.addEventListener('DOMContentLoaded', function () {
        new MutationObserver(mountKnowledgeTools).observe(document.body, { childList: true, subtree: true });
        window.addEventListener('hashchange', mountKnowledgeTools);
        window.setInterval(mountKnowledgeTools, 1000);
        mountKnowledgeTools();
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
