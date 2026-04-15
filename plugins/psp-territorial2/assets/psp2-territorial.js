/* globals PSP2_TERR */
'use strict';

var PSP2Terr = (function () {

    var _cache = {}; // Cache en memoria: 'tipo_parentId' → items[]

    function _cfg() {
        return (typeof PSP2_TERR !== 'undefined') ? PSP2_TERR : { ajax_url: '', nonce: '' };
    }

    /**
     * Obtiene territorios vía admin-ajax (action: psp2_terr_get).
     * El handler PHP decide si usar PSP Territorial V2 o el JSON de fallback.
     *
     * @param {string} tipo      'provincia'|'distrito'|'corregimiento'|'comunidad'
     * @param {string} parentId
     * @returns {Promise<Array<{id:string,nombre:string}>>}
     */
    async function fetchTerr(tipo, parentId) {
        var key = tipo + '_' + (parentId || 'root');
        if (Object.prototype.hasOwnProperty.call(_cache, key)) return _cache[key];

        var cfg = _cfg();
        if (!cfg.ajax_url) { _cache[key] = []; return []; }

        var body = new URLSearchParams({
            action    : 'psp2_terr_get',
            psp2_nonce: cfg.nonce,
            tipo      : tipo,
            parent_id : parentId || '',
        });

        try {
            var res  = await fetch(cfg.ajax_url, { method: 'POST', body: body });
            var json = await res.json();
            _cache[key] = (json.success && Array.isArray(json.data)) ? json.data : [];
        } catch (e) {
            _cache[key] = [];
        }
        return _cache[key];
    }

    /**
     * Fetches territory children via WP AJAX (pspv2_rest mode).
     * The server handles REST calls + transient caching.
     * @param {string} tipo
     * @param {string} parentId
     * @returns {Promise<Array<{id:string,nombre:string}>>}
     */
    async function getChildrenRest(tipo, parentId) {
        var cfg = (typeof PSP2_TERR !== 'undefined') ? PSP2_TERR : {};
        var fd = new FormData();
        fd.append('action', 'psp2_terr_get');
        fd.append('psp2_nonce', cfg.nonce || '');
        fd.append('tipo', tipo);
        fd.append('parent_id', parentId || '');
        try {
            var res = await fetch(cfg.ajax_url || '/wp-admin/admin-ajax.php', { method: 'POST', body: fd });
            var data = await res.json();
            if (data.success && Array.isArray(data.data)) {
                return data.data;
            }
        } catch (e) { /* silencioso */ }
        return [];
    }

    /**
     * Obtiene territorios hijos vía AJAX (el servidor decide la fuente).
     * Siempre delega al backend para soportar todos los modos (bundled, json_url, pspv2_rest).
     * @param {string} tipo
     * @param {string} parentId
     * @returns {Promise<Array<{id:string,nombre:string}>>}
     */
    async function getChildren(tipo, parentId) {
        return fetchTerr(tipo, parentId);
    }

    /**
     * Muestra un mensaje de "territorio no encontrado" con mailto al admin.
     * @param {HTMLElement} container  Elemento padre donde insertar el mensaje
     * @param {string}      nivel      'provincia'|'distrito'|'corregimiento'|'comunidad'
     * @param {string}      msgId      ID único para el elemento del mensaje
     */
    function showMissingMessage(container, nivel, msgId) {
        var existing = document.getElementById(msgId);
        if (existing) { existing.style.display = 'block'; return; }

        var cfg      = (typeof PSP2_TERR !== 'undefined') ? PSP2_TERR : {};
        var email    = cfg.contact || 'admin@panamasinpobreza.org';
        var subject  = encodeURIComponent('Solicitud: agregar ' + nivel + ' faltante');
        var bodyParts = [
            'Hola,',
            '',
            'No encontré mi ' + nivel + ' en el formulario de registro del Movimiento PSP.',
            'Por favor agregar:',
            '',
            'Nombre del/la ' + nivel + ': [escribe aquí]',
            'Padre (si aplica): [escribe aquí]',
            '',
            'Gracias.'
        ];
        var body     = encodeURIComponent( bodyParts.join('\n') );
        var href     = 'mailto:' + email + '?subject=' + subject + '&body=' + body;

        var p = document.createElement('p');
        p.id = msgId;
        p.className = 'psp2-terr-missing';
        p.style.cssText = 'font-size:12px;color:#6B7280;margin:4px 0 0;';
        p.innerHTML = '¿No encuentras tu ' + nivel + '? <a href="' + href + '" style="color:#1D4ED8">Escríbenos para agregarlo</a>.';
        container.appendChild(p);
    }

    /**
     * Oculta el mensaje de territorio faltante si existe.
     * @param {string} msgId
     */
    function hideMissingMessage(msgId) {
        var el = document.getElementById(msgId);
        if (el) el.style.display = 'none';
    }

    /**
     * Popula un <select> con los hijos del territorio seleccionado.
     * @param {HTMLSelectElement} selectEl
     * @param {string} childTipo  'distrito'|'corregimiento'|'comunidad'
     * @param {string} prefix
     */
    function reportMissing(event, nivel, prefix) {
        if (event) event.preventDefault();
        var rowEl = document.getElementById((prefix || '') + 'row-' + nivel);
        if (!rowEl) return;
        showMissingHelp(rowEl, nivel, prefix || '');
    }

    /**
     * Popula un <select> hijo basado en el cambio de un select padre.
     * @param {HTMLSelectElement} selectEl
     * @param {string}            childTipo  'distrito'|'corregimiento'|'comunidad'
     * @param {string}            prefix
     */
    async function load(selectEl, childTipo, prefix) {
        var parentId = selectEl.value;
        var childRow = document.getElementById(prefix + 'row-' + childTipo);
        var childSel = document.getElementById(prefix + 'psp2_' + childTipo);

        if (!childSel) return;

        // Limpiar hijos en cascada
        var cascade = ['distrito', 'corregimiento', 'comunidad'];
        var idx = cascade.indexOf(childTipo);
        for (var i = idx; i < cascade.length; i++) {
            var rowEl = document.getElementById(prefix + 'row-' + cascade[i]);
            var selEl = document.getElementById(prefix + 'psp2_' + cascade[i]);
            if (rowEl) { rowEl.style.display = 'none'; _removeMissingHelp(rowEl); }
            if (selEl) { selEl.innerHTML = '<option value="">-- Cargando... --</option>'; selEl.value = ''; }
            hideMissingMessage(prefix + 'msg-missing-' + cascade[i]);
        }

        if (!parentId) return;

        var items = await getChildren(childTipo, parentId);

        if (!items.length) {
            if (childRow) childRow.style.display = 'block';
            childSel.innerHTML = '<option value="">-- No disponible --</option>';
            showMissingMessage(childRow || childSel.parentNode, childTipo, prefix + 'msg-missing-' + childTipo);
            return;
        }

        hideMissingMessage(prefix + 'msg-missing-' + childTipo);
        childSel.innerHTML = '<option value="">-- Selecciona ' + childTipo + ' --</option>';
        items.forEach(function (item) {
            var opt = document.createElement('option');
            opt.value = item.id;
            opt.textContent = item.nombre;
            childSel.appendChild(opt);
        });

        if (childRow) {
            childRow.style.display = 'block';
            _removeMissingHelp(childRow);
            if (!items.length) {
                showMissingHelp(childRow, childTipo, prefix);
            } else {
                _addNotFoundLink(childRow, childTipo, prefix);
            }
        }
    }

    /**
     * Inicializa el <select> de provincias.
     * @param {string} prefix
     */
    async function initProvincias(prefix) {
        var sel   = document.getElementById(prefix + 'psp2_provincia');
        var rowEl = document.getElementById(prefix + 'row-provincia');
        if (!sel) return;
        var items = await getChildren('provincia', '');
        if (!items.length) {
            showMissingMessage(sel.parentNode, 'provincia', prefix + 'msg-missing-provincia');
            return;
        }
        hideMissingMessage(prefix + 'msg-missing-provincia');
        sel.innerHTML = '<option value="">-- Selecciona provincia --</option>';
        items.forEach(function (item) {
            var opt = document.createElement('option');
            opt.value = item.id;
            opt.textContent = item.nombre;
            sel.appendChild(opt);
        });

        var container = rowEl || sel.parentElement;
        if (container) {
            if (!container.id) container.id = prefix + 'row-provincia';
            _removeMissingHelp(container);
            if (!items.length) {
                showMissingHelp(container, 'provincia', prefix);
            } else {
                _addNotFoundLink(container, 'provincia', prefix);
            }
        }
    }

    /**
     * Toggle Panamá / Internacional.
     * @param {HTMLInputElement} radio
     * @param {string}           tipo  'panama'|'internacional'
     */
    function switchTipo(radio, tipo) {
        var wrap = radio.closest('.psp2-terr-wrap');
        if (!wrap) return;
        var panama = wrap.querySelector('.psp2-terr-panama');
        var inter  = wrap.querySelector('.psp2-terr-inter');
        if (tipo === 'panama') {
            if (panama) panama.style.display = 'block';
            if (inter)  inter.style.display  = 'none';
        } else {
            if (panama) panama.style.display = 'none';
            if (inter)  inter.style.display  = 'block';
        }
    }

    // Auto-init en DOMContentLoaded
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.psp2-terr-wrap').forEach(function (wrap) {
            var provSel = wrap.querySelector('[id$="psp2_provincia"]');
            if (!provSel) return;
            var idStr  = provSel.id;
            var prefix = idStr.replace('psp2_provincia', '');
            initProvincias(prefix);
        });
    });

    return {
        load          : load,
        switchTipo    : switchTipo,
        initProvincias: initProvincias,
        reportMissing : reportMissing,
    };
})();

