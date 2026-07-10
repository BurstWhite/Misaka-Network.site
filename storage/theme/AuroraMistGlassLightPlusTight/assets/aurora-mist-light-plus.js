(function () {
  'use strict';

  var root = document.documentElement;
  var media = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

  function applyThemeState() {
    var isDark = root.classList.contains('dark');
    root.setAttribute('data-amg-active', isDark ? 'off' : 'light');
    if (document.body) document.body.classList.add('amg-theme');
  }

  function schedule() {
    if (window.requestAnimationFrame) {
      window.requestAnimationFrame(applyThemeState);
    } else {
      window.setTimeout(applyThemeState, 0);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', applyThemeState, { once: true });
  } else {
    applyThemeState();
  }

  new MutationObserver(schedule).observe(root, {
    attributes: true,
    attributeFilter: ['class']
  });

  if (media) {
    if (typeof media.addEventListener === 'function') media.addEventListener('change', schedule);
    else if (typeof media.addListener === 'function') media.addListener(schedule);
  }
})();
