<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1" />
  <meta name="description" content="{{ $description }}" />
  <title>{{ $title }}</title>
  <script>
    (function () {
      var mode = localStorage.getItem('misaka.theme-mode') || 'system';
      var dark = mode === 'dark' || (mode === 'system' && matchMedia('(prefers-color-scheme: dark)').matches);
      document.documentElement.classList.toggle('dark', dark);
      document.documentElement.dataset.theme = dark ? 'dark' : 'light';
      document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
      window.__MISAKA_CONFIG__ = {!! $runtime_config_json !!};
      document.documentElement.style.setProperty('--accent', window.__MISAKA_CONFIG__.theme.primaryColor || '#3155ee');
    })();
  </script>
  <link rel="stylesheet" href="/theme/{{ $theme }}/assets/app.css?v={{ $version }}" />
  <script type="module" src="/theme/{{ $theme }}/assets/{{ $entry_asset }}"></script>
</head>
<body><div id="app"></div>{!! $theme_config['custom_html'] ?? '' !!}</body>
</html>
