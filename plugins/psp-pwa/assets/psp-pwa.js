/* PSP PWA Client JS */
(function() {
  'use strict';

  // Register SW
  if ('serviceWorker' in navigator && typeof PSP_PWA !== 'undefined') {
    navigator.serviceWorker.register(PSP_PWA.sw_url, {scope:'/'})
      .then(function(reg) {
        window._pspSwReg = reg;
      })
      .catch(function(){});
  }

  // Install prompt
  var deferred = null;
  window.addEventListener('beforeinstallprompt', function(e) {
    e.preventDefault();
    deferred = e;
    var btn = document.getElementById('psp-pwa-install-btn');
    if (btn) btn.style.display = 'flex';
  });

  window.PSPInstallPWA = function() {
    if (!deferred) return;
    deferred.prompt();
    deferred.userChoice.then(function(r) {
      if (r.outcome === 'accepted' && typeof PSPToast !== 'undefined')
        PSPToast.show('¡App instalada! Búscala en tu pantalla de inicio 🚀');
      deferred = null;
      var btn = document.getElementById('psp-pwa-install-btn');
      if (btn) btn.style.display = 'none';
    });
  };

  // Push notification subscription
  window.PSPSubscribePush = async function() {
    if (!('PushManager' in window) || !window._pspSwReg) return;
    var vapidKey = document.querySelector('meta[name="psp-vapid-key"]');
    if (!vapidKey) return;

    try {
      var sub = await window._pspSwReg.pushManager.subscribe({
        userVisibleOnly     : true,
        applicationServerKey: vapidKey.content
      });
      await fetch(PSP_PWA.ajax_url, {
        method: 'POST',
        body  : new URLSearchParams({
          action      : 'psp_save_push_sub',
          subscription: JSON.stringify(sub),
          psp_nonce   : PSP_PWA.nonce
        })
      });
      if (typeof PSPToast !== 'undefined') PSPToast.show('✅ Notificaciones activadas');
    } catch(e) {
      console.warn('Push subscription failed:', e);
    }
  };

})();
