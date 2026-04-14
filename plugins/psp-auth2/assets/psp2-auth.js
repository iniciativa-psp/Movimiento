/* PSP Auth 2 — registro form JS */
/* globals PSP2_CONFIG */
(function () {
    'use strict';

    function initRegistroForm() {
        var form = document.getElementById('psp2-form-registro');
        if (!form) return;

        form.addEventListener('submit', async function (e) {
            e.preventDefault();
            var btn = document.getElementById('psp2-reg-btn');
            var msg = document.getElementById('psp2-reg-msg');

            if (btn) { btn.disabled = true; btn.textContent = '\u23F3 Registrando\u2026'; }
            if (msg) { msg.style.display = 'none'; }

            var fd = new FormData(form);
            fd.set('action', 'psp2_register');

            try {
                var res = await fetch(
                    (typeof PSP2_CONFIG !== 'undefined' ? PSP2_CONFIG.ajax_url : '/wp-admin/admin-ajax.php'),
                    { method: 'POST', body: fd }
                );
                var data = await res.json();

                if (data.success) {
                    if (msg) {
                        msg.className = 'psp2-alert psp2-alert-success';
                        msg.innerHTML = data.data.message || '\u2705 \u00a1Registro exitoso!';
                        msg.style.display = 'block';
                    }
                    setTimeout(function () {
                        window.location.href = data.data.redirect || '/mi-cuenta/';
                    }, 1800);
                } else {
                    if (msg) {
                        msg.className = 'psp2-alert psp2-alert-error';
                        msg.innerHTML = (data.data && data.data.message) ? data.data.message : '\u274C Error al registrar.';
                        msg.style.display = 'block';
                    }
                    if (btn) { btn.disabled = false; btn.textContent = '\uD83D\uDCE9 Registrarme'; }
                }
            } catch (err) {
                if (msg) {
                    msg.className = 'psp2-alert psp2-alert-error';
                    msg.innerHTML = '\u274C Error de red: ' + err.message;
                    msg.style.display = 'block';
                }
                if (btn) { btn.disabled = false; btn.textContent = '\uD83D\uDCE9 Registrarme'; }
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initRegistroForm);
    } else {
        initRegistroForm();
    }
})();
