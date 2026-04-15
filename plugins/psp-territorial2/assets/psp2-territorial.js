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
     * Recoge los territorios padre ya seleccionados para contexto del mailto.
     * @param {string} nivel  nivel actual
     * @param {string} prefix
     * @returns {Object}
     */
    function _gatherParents(nivel, prefix) {
        var levels  = ['provincia', 'distrito', 'corregimiento', 'comunidad'];
        var idx     = levels.indexOf(nivel);
        var parents = {};
        for (var i = 0; i < idx; i++) {
            var selEl = document.getElementById(prefix + 'psp2_' + levels[i]);
            if (selEl && selEl.value && selEl.selectedIndex > 0) {
                parents[levels[i]] = selEl.options[selEl.selectedIndex].text;
            }
        }
        return parents;
    }

    /**
     * Muestra un bloque de ayuda con enlace mailto bajo el contenedor indicado.
     * @param {HTMLElement} container
     * @param {string}      nivel   'provincia'|'distrito'|'corregimiento'|'comunidad'
     * @param {string}      prefix
     */
    function showMissingHelp(container, nivel, prefix) {
        var helpId = 'psp2-missing-' + (container.id || nivel);
        if (document.getElementById(helpId)) return; // ya visible

        var parents   = _gatherParents(nivel, prefix);
        var parentStr = Object.keys(parents).map(function (k) { return k + ': ' + parents[k]; }).join(', ');
        var subj      = 'Solicitud: ' + nivel + ' faltante en formulario de registro';
        var bodyText  = 'Hola,\n\nNo encontré mi ' + nivel + ' en el formulario de registro.\n' +
            (parentStr ? 'Datos seleccionados: ' + parentStr + '\n' : '') +
            '\nPor favor, ¿pueden agregarlo?\n\nGracias.';
        var href = 'mailto:admin@panamasinpobreza.org' +
            '?subject=' + encodeURIComponent(subj) +
            '&body='    + encodeURIComponent(bodyText);

        var block = document.createElement('div');
        block.id  = helpId;
        block.style.cssText = 'margin-top:6px;padding:8px 10px;background:#FEF3C7;border:1px solid #FCD34D;border-radius:6px;font-size:12px;color:#92400E;';

        var text = document.createTextNode('\u26A0\uFE0F \xBFNo encuentras tu ' + nivel + '? ');
        var link = document.createElement('a');
        link.setAttribute('href', href);
        link.style.cssText = 'color:#92400E;font-weight:600;text-decoration:underline;';
        link.textContent = 'Notificar al administrador';

        block.appendChild(text);
        block.appendChild(link);
        container.appendChild(block);
    }

    function _removeMissingHelp(container) {
        if (!container) return;
        var helpId = 'psp2-missing-' + (container.id || '');
        var el = document.getElementById(helpId);
        if (el && el.parentNode) el.parentNode.removeChild(el);
        // Also remove any "not found" link
        var linkId = 'psp2-notfound-' + (container.id || '');
        var lnk = document.getElementById(linkId);
        if (lnk && lnk.parentNode) lnk.parentNode.removeChild(lnk);
    }

    /**
     * Agrega un enlace "No encuentro mi X..." que al pulsar muestra el bloque de ayuda.
     * @param {HTMLElement} container
     * @param {string}      nivel
     * @param {string}      prefix
     */
    function _addNotFoundLink(container, nivel, prefix) {
        var linkId = 'psp2-notfound-' + (container.id || nivel);
        if (document.getElementById(linkId)) return;
        var p = document.createElement('p');
        p.id  = linkId;
        p.style.cssText = 'margin:4px 0 0;font-size:12px;';

        var a = document.createElement('a');
        a.setAttribute('href', '#');
        a.style.color = '#9CA3AF';
        a.textContent = 'No encuentro mi ' + nivel + '...';
        a.addEventListener('click', (function (n, pfx) {
            return function (e) {
                e.preventDefault();
                reportMissing(null, n, pfx || '');
            };
        }(nivel, prefix)));

        p.appendChild(a);
        container.appendChild(p);
    }

    /**
     * Público: llamado al hacer clic en "No encuentro mi X".
     * @param {Event}  event
     * @param {string} nivel
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
        }

        if (!parentId) return;

        var items = await fetchTerr(childTipo, parentId);

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

        var items = await fetchTerr('provincia', '');

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
