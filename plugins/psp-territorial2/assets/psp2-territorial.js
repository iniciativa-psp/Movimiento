/* globals PSP2_TERR */
'use strict';

var PSP2Terr = (function () {

    var _json = null; // Datos JSON cacheados en memoria

    /**
     * Carga los datos JSON si aún no están en memoria.
     * @returns {Promise<Array>}
     */
    async function getJson() {
        if (_json) return _json;
        var url = (typeof PSP2_TERR !== 'undefined') ? PSP2_TERR.json_url : '';
        if (!url) return [];
        try {
            var res = await fetch(url);
            _json = await res.json();
        } catch (e) {
            _json = [];
        }
        return _json || [];
    }

    /**
     * Filtra el JSON por tipo y parent_id.
     * @param {string} tipo
     * @param {string} parentId
     * @returns {Promise<Array<{id:string,nombre:string}>>}
     */
    async function getChildren(tipo, parentId) {
        var data = await getJson();
        return data.filter(function (item) {
            if (tipo === 'provincia') return item.tipo === 'provincia';
            return item.tipo === tipo && String(item.parent_id) === String(parentId);
        }).map(function (item) {
            return { id: item.id, nombre: item.nombre };
        });
    }

    /**
     * Popula un <select> con los hijos del territorio seleccionado.
     * @param {HTMLSelectElement} selectEl
     * @param {string} childTipo  'distrito'|'corregimiento'|'comunidad'
     * @param {string} prefix
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
            if (rowEl) rowEl.style.display = 'none';
            if (selEl) { selEl.innerHTML = '<option value="">-- Cargando... --</option>'; selEl.value = ''; }
        }

        if (!parentId) return;

        var items = await getChildren(childTipo, parentId);
        if (!items.length) return;

        childSel.innerHTML = '<option value="">-- Selecciona ' + childTipo + ' --</option>';
        items.forEach(function (item) {
            var opt = document.createElement('option');
            opt.value = item.id;
            opt.textContent = item.nombre;
            childSel.appendChild(opt);
        });

        if (childRow) childRow.style.display = 'block';
    }

    /**
     * Inicializa el <select> de provincias.
     * @param {string} prefix
     */
    async function initProvincias(prefix) {
        var sel = document.getElementById(prefix + 'psp2_provincia');
        if (!sel) return;
        var items = await getChildren('provincia', '');
        if (!items.length) return;
        sel.innerHTML = '<option value="">-- Selecciona provincia --</option>';
        items.forEach(function (item) {
            var opt = document.createElement('option');
            opt.value = item.id;
            opt.textContent = item.nombre;
            sel.appendChild(opt);
        });
    }

    /**
     * Toggle Panamá / Internacional.
     * @param {HTMLInputElement} radio
     * @param {string} tipo  'panama'|'internacional'
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
            // Intentar inferir prefix por el ID del first select
            var provSel = wrap.querySelector('[id$="psp2_provincia"]');
            if (!provSel) return;
            var idStr = provSel.id;
            var prefix = idStr.replace('psp2_provincia', '');
            initProvincias(prefix);
        });
    });

    return { load: load, switchTipo: switchTipo, initProvincias: initProvincias };
})();
