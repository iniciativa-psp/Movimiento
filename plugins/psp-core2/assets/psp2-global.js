/* PSP v2 — global JS helpers */
/* globals PSP2_CONFIG */
window.PSP2 = window.PSP2 || {};

/**
 * Realiza una petición autenticada a la REST API psp/v2.
 * @param {string} path  Ruta relativa, ej: "me" o "kpis"
 * @param {object} opts  Opciones fetch (method, body, etc.)
 * @returns {Promise<any>}
 */
PSP2.api = async function (path, opts) {
    opts = opts || {};
    var headers = Object.assign({
        'Content-Type': 'application/json',
        'X-WP-Nonce': (typeof PSP2_CONFIG !== 'undefined') ? PSP2_CONFIG.rest_nonce : ''
    }, opts.headers || {});

    var url = (typeof PSP2_CONFIG !== 'undefined')
        ? PSP2_CONFIG.rest_url + path
        : '/wp-json/psp/v2/' + path;

    var res = await fetch(url, Object.assign({}, opts, { headers: headers }));
    if (!res.ok) {
        var err = await res.json().catch(function () { return {}; });
        throw new Error(err.message || ('HTTP ' + res.status));
    }
    return res.json();
};

/**
 * Muestra un mensaje temporal en un contenedor.
 * @param {HTMLElement} el
 * @param {string} msg
 * @param {'success'|'error'|'info'} type
 */
PSP2.showMsg = function (el, msg, type) {
    if (!el) return;
    el.className = 'psp2-alert psp2-alert-' + (type || 'info');
    el.innerHTML = msg;
    el.style.display = 'block';
};
