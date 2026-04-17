/**
 * PSP Global JS — Panamá Sin Pobreza
 * Disponible en todas las páginas donde psp-core esté activo.
 * Provee: Supabase client init, helpers, realtime, PWA install prompt.
 */
(function () {
  'use strict';

  /* ── Supabase client (lazy init) ──────────────────────────── */
  window.PSPSupabase = null;

  function getSupabase() {
    if (window.PSPSupabase) return window.PSPSupabase;
    if (typeof window.supabase !== 'undefined' && PSP_CONFIG.supabase_url && PSP_CONFIG.supabase_key) {
      window.PSPSupabase = window.supabase.createClient(
        PSP_CONFIG.supabase_url,
        PSP_CONFIG.supabase_key
      );
    }
    return window.PSPSupabase;
  }

  /* ── Cookie helpers ───────────────────────────────────────── */
  window.PSPCookie = {
    get: function (name) {
      var match = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
      return match ? decodeURIComponent(match[1]) : null;
    },
    set: function (name, value, days) {
      var expires = '';
      if (days) {
        var d = new Date();
        d.setTime(d.getTime() + days * 86400000);
        expires = '; expires=' + d.toUTCString();
      }
      document.cookie = name + '=' + encodeURIComponent(value) + expires + '; path=/; SameSite=Strict';
    },
    del: function (name) {
      this.set(name, '', -1);
    }
  };

  /* ── JWT helpers ──────────────────────────────────────────── */
  window.PSPAuth = window.PSPAuth || {
    getJWT: function () { return PSPCookie.get('psp_jwt'); },
    getMiembroId: function () { return PSPCookie.get('psp_miembro_id'); },
    isLoggedIn: function () { return !!this.getJWT(); },
    logout: function () {
      PSPCookie.del('psp_jwt');
      PSPCookie.del('psp_miembro_id');
      window.location.href = '/';
    }
  };

  /* ── Realtime subscriptions ───────────────────────────────── */
  window.PSPRealtime = {
    subscribeKPIs: function (callback) {
      var sb = getSupabase();
      if (!sb) return;
      sb.channel('public:miembros')
        .on('postgres_changes', { event: 'INSERT', schema: 'public', table: 'miembros' }, callback)
        .subscribe();
      sb.channel('public:pagos')
        .on('postgres_changes', { event: 'UPDATE', schema: 'public', table: 'pagos' }, callback)
        .subscribe();
    }
  };

  /* ── UI helpers ───────────────────────────────────────────── */
  window.PSPToast = {
    show: function (msg, tipo) {
      tipo = tipo || 'success';
      var colors = { success: '#0B5E43', error: '#C9381A', info: '#0C447C', warn: '#EF9F27' };
      var t = document.createElement('div');
      t.style.cssText = 'position:fixed;bottom:20px;right:20px;z-index:99999;'
        + 'background:' + (colors[tipo] || colors.success) + ';color:#fff;'
        + 'padding:12px 22px;border-radius:10px;font-size:14px;font-weight:600;'
        + 'box-shadow:0 4px 20px rgba(0,0,0,.2);animation:pspFadeIn .3s ease;max-width:320px;';
      t.textContent = msg;
      document.body.appendChild(t);
      setTimeout(function () {
        t.style.opacity = '0';
        t.style.transition = 'opacity .4s';
        setTimeout(function () { t.parentNode && t.parentNode.removeChild(t); }, 400);
      }, 3500);
    }
  };

  /* ── PWA install prompt ───────────────────────────────────── */
  var deferredPrompt = null;
  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();
    deferredPrompt = e;
    var btn = document.getElementById('psp-pwa-install-btn');
    if (btn) btn.style.display = 'flex';
  });

  window.PSPInstallPWA = function () {
    if (!deferredPrompt) return;
    deferredPrompt.prompt();
    deferredPrompt.userChoice.then(function (r) {
      if (r.outcome === 'accepted') PSPToast.show('¡App instalada! 🚀');
      deferredPrompt = null;
    });
  };

  /* ── Service Worker registration ─────────────────────────── */
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register('/pwa/service-worker.js')
        .catch(function () { /* SW not critical */ });
    });
  }

  /* ── Countdown global ─────────────────────────────────────── */
  window.PSPCountdown = function (targetDate, elements) {
    function tick() {
      var t = new Date(targetDate).getTime() - Date.now();
      if (t < 0) return;
      if (elements.d) elements.d.textContent = String(Math.floor(t / 86400000)).padStart(2, '0');
      if (elements.h) elements.h.textContent = String(Math.floor(t % 86400000 / 3600000)).padStart(2, '0');
      if (elements.m) elements.m.textContent = String(Math.floor(t % 3600000 / 60000)).padStart(2, '0');
      if (elements.s) elements.s.textContent = String(Math.floor(t % 60000 / 1000)).padStart(2, '0');
    }
    tick();
    return setInterval(tick, 1000);
  };

  /* ── Número formateado ────────────────────────────────────── */
  window.PSPFmt = {
    num: function (n) { return Number(n || 0).toLocaleString('es-PA'); },
    monto: function (n) { return '$' + Number(n || 0).toLocaleString('es-PA', {minimumFractionDigits:2,maximumFractionDigits:2}); },
    pct: function (n, total) { return total > 0 ? ((n / total) * 100).toFixed(2) + '%' : '0%'; }
  };

  /* ── Capturar código referido de URL ──────────────────────── */
  var urlRef = new URLSearchParams(window.location.search).get('ref');
  if (urlRef) PSPCookie.set('psp_ref', urlRef, 30);

  /* ── CSS animation keyframe ───────────────────────────────── */
  if (!document.getElementById('psp-global-anim')) {
    var style = document.createElement('style');
    style.id  = 'psp-global-anim';
    style.textContent = '@keyframes pspFadeIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}';
    document.head.appendChild(style);
  }

})();
