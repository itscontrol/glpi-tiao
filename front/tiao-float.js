/* Tião — botão flutuante com timer ativo (injetado em todas as páginas GLPI) */
(function () {
  'use strict';

  var url = (window.__TIAO_URL__ || '').replace(/\/$/, '');
  if (!url) return;

  // Aguarda o DOM estar pronto
  function init() {
    if (document.getElementById('tiao-float-root')) return;

    var root = document.createElement('div');
    root.id = 'tiao-float-root';
    root.style.cssText = [
      'position:fixed',
      'bottom:20px',
      'right:20px',
      'z-index:99999',
      'width:280px',
      'height:56px',
      'border-radius:12px',
      'overflow:hidden',
      'box-shadow:0 4px 24px rgba(0,0,0,0.6)',
      'border:1px solid rgba(255,255,255,0.12)',
      'background:#06162B',
    ].join(';');

    var iframe = document.createElement('iframe');
    iframe.src = url + '/timer-widget';
    iframe.style.cssText = 'width:100%;height:100%;border:none;display:block;';
    iframe.setAttribute('scrolling', 'no');
    iframe.setAttribute('title', 'Tião Timer');

    root.appendChild(iframe);
    document.body.appendChild(root);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
