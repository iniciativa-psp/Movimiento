/**
 * PSP Territorial — JS del selector encadenado
 * Conecta con JSON externo (tu plugin territorial) o Supabase
 * v1.0.2
 */
(function () {
  'use strict';

  // Cache para no repetir peticiones
  var cache = {};

  window.PSPTerr = {

    /* ── INIT: cargar provincias al cargar la página ─────────── */
    init: function () {
      var selects = document.querySelectorAll('#psp_provincia, [id$="psp_provincia"]');
      selects.forEach(function (sel) {
        PSPTerr.loadProvincias(sel);
      });
    },

    /* ── OBTENER DATOS DEL JSON EXTERNO O AJAX ──────────────── */
    getData: function (tipo, parentId, callback) {
      var key = tipo + '_' + (parentId || 'root');
      if (cache[key]) { callback(cache[key]); return; }

      var modo    = (typeof PSP_TERR !== 'undefined') ? PSP_TERR.modo     : 'ajax';
      var jsonUrl = (typeof PSP_TERR !== 'undefined') ? PSP_TERR.json_url : '';

      if (modo === 'json_externo' && jsonUrl) {
        // Carga directa desde el JSON del plugin externo
        fetch(jsonUrl)
          .then(function (r) { return r.json(); })
          .then(function (data) {
            var result = PSPTerr.filtrarJSON(data, tipo, parentId);
            cache[key] = result;
            callback(result);
          })
          .catch(function (e) {
            console.warn('PSP Territorial - Error JSON externo:', e);
            PSPTerr.getDataAjax(tipo, parentId, key, callback);
          });
      } else {
        PSPTerr.getDataAjax(tipo, parentId, key, callback);
      }
    },

    /* ── FILTRAR DATOS DEL JSON EXTERNO ─────────────────────── */
    /* El JSON externo puede tener múltiples formatos — este método
       intenta los más comunes. Ajusta según el formato de tu plugin. */
    filtrarJSON: function (data, tipo, parentId) {
      // Formato 1: { provincias: [...], distritos: [...], corregimientos: [...], comunidades: [...] }
      if (data[tipo]) {
        var items = data[tipo];
        if (parentId) {
          items = items.filter(function (i) {
            return String(i.parent_id || i.provincia_id || i.distrito_id || i.corregimiento_id) === String(parentId);
          });
        }
        return items.map(function (i) {
          return { id: i.id || i.codigo, nombre: i.nombre || i.name || i.title };
        });
      }

      // Formato 2: Array plano con campo "tipo"
      if (Array.isArray(data)) {
        var items2 = data.filter(function (i) { return i.tipo === tipo; });
        if (parentId) {
          items2 = items2.filter(function (i) { return String(i.parent_id) === String(parentId); });
        }
        return items2.map(function (i) {
          return { id: i.id || i.codigo, nombre: i.nombre || i.name };
        });
      }

      // Formato 3: { data: [...] }
      if (data.data && Array.isArray(data.data)) {
        return PSPTerr.filtrarJSON(data.data, tipo, parentId);
      }

      return [];
    },

    /* ── FALLBACK: AJAX al backend PSP ──────────────────────── */
    getDataAjax: function (tipo, parentId, cacheKey, callback) {
      if (typeof PSP_TERR === 'undefined') { callback([]); return; }
      fetch(PSP_TERR.ajax_url, {
        method : 'POST',
        body   : new URLSearchParams({
          action    : 'psp_terr_get',
          tipo      : tipo,
          parent_id : parentId || '',
          psp_nonce : PSP_TERR.nonce
        })
      })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        var result = (d.success && d.data) ? d.data : [];
        cache[cacheKey] = result;
        callback(result);
      })
      .catch(function () { callback([]); });
    },

    /* ── POBLAR UN SELECT ────────────────────────────────────── */
    poblarSelect: function (select, items, placeholder) {
      select.innerHTML = '<option value="">' + placeholder + '</option>';
      items.forEach(function (item) {
        var opt   = document.createElement('option');
        opt.value = item.id;
        opt.textContent = item.nombre;
        select.appendChild(opt);
      });
      select.disabled = items.length === 0;
    },

    /* ── MOSTRAR / OCULTAR ROW ───────────────────────────────── */
    showRow: function (rowId) {
      var el = document.getElementById(rowId);
      if (el) el.style.display = 'block';
    },
    hideRow: function (rowId) {
      var el = document.getElementById(rowId);
      if (el) el.style.display = 'none';
    },

    /* ── OBTENER PREFIJO DE UN SELECT ───────────────────────── */
    prefix: function (sel) {
      var id = sel.id || '';
      // Si el id es "psp_provincia" el prefix es ""
      // Si es "pref_psp_provincia" el prefix es "pref_"
      var match = id.match(/^(.*)psp_provincia$/);
      return match ? match[1] : '';
    },

    /* ── CARGAR PROVINCIAS ──────────────────────────────────── */
    loadProvincias: function (sel) {
      PSPTerr.getData('provincias', null, function (items) {
        PSPTerr.poblarSelect(sel, items, '-- Selecciona provincia --');
      });
    },

    /* ── ON CHANGE: provincia → distritos ──────────────────── */
    loadDistritos: function (sel) {
      var p        = PSPTerr.prefix(sel);
      var provId   = sel.value;
      var provName = sel.options[sel.selectedIndex] ? sel.options[sel.selectedIndex].textContent : '';

      // Guardar nombre
      var nomEl = document.getElementById(p + 'psp_prov_nombre');
      if (nomEl) nomEl.value = provName;

      // Reset niveles inferiores
      ['distrito','corregimiento','comunidad'].forEach(function (t) {
        var s = document.getElementById(p + 'psp_' + t);
        if (s) { s.innerHTML = '<option value="">-- Selecciona --</option>'; }
        PSPTerr.hideRow(p + 'row-' + t);
      });

      if (!provId) return;

      PSPTerr.getData('distritos', provId, function (items) {
        var distSel = document.getElementById(p + 'psp_distrito');
        if (!distSel) return;
        PSPTerr.poblarSelect(distSel, items, '-- Selecciona distrito --');
        PSPTerr.showRow(p + 'row-distrito');
      });
    },

    /* ── ON CHANGE: distrito → corregimientos ───────────────── */
    loadCorregimientos: function (sel) {
      var p       = PSPTerr.prefix(sel.id.replace('distrito','provincia').replace('psp_distrito','psp_provincia'));
      // Recalcular prefix desde el id real del select distrito
      var rawId   = sel.id;
      var match   = rawId.match(/^(.*)psp_distrito$/);
      p           = match ? match[1] : '';

      var distId   = sel.value;
      var distName = sel.options[sel.selectedIndex] ? sel.options[sel.selectedIndex].textContent : '';
      var nomEl    = document.getElementById(p + 'psp_dist_nombre');
      if (nomEl) nomEl.value = distName;

      // Reset
      ['corregimiento','comunidad'].forEach(function (t) {
        var s = document.getElementById(p + 'psp_' + t);
        if (s) s.innerHTML = '<option value="">-- Selecciona --</option>';
        PSPTerr.hideRow(p + 'row-' + t);
      });

      if (!distId) return;

      PSPTerr.getData('corregimientos', distId, function (items) {
        var corrSel = document.getElementById(p + 'psp_corregimiento');
        if (!corrSel) return;
        PSPTerr.poblarSelect(corrSel, items, '-- Selecciona corregimiento --');
        PSPTerr.showRow(p + 'row-corregimiento');
      });
    },

    /* ── ON CHANGE: corregimiento → comunidades ─────────────── */
    loadComunidades: function (sel) {
      var rawId = sel.id;
      var match = rawId.match(/^(.*)psp_corregimiento$/);
      var p     = match ? match[1] : '';

      var corrId   = sel.value;
      var corrName = sel.options[sel.selectedIndex] ? sel.options[sel.selectedIndex].textContent : '';
      var nomEl    = document.getElementById(p + 'psp_corr_nombre');
      if (nomEl) nomEl.value = corrName;

      var comSel = document.getElementById(p + 'psp_comunidad');
      if (comSel) comSel.innerHTML = '<option value="">-- Selecciona --</option>';
      PSPTerr.hideRow(p + 'row-comunidad');

      if (!corrId) return;

      PSPTerr.getData('comunidades', corrId, function (items) {
        if (!items.length) return;
        var comSel2 = document.getElementById(p + 'psp_comunidad');
        if (!comSel2) return;
        PSPTerr.poblarSelect(comSel2, items, '-- Selecciona comunidad (opcional) --');
        PSPTerr.showRow(p + 'row-comunidad');
      });
    },

    /* ── CIUDADES INTERNACIONAL ─────────────────────────────── */
    loadCiudades: function (sel) {
      var rawId = sel.id;
      var match = rawId.match(/^(.*)psp_pais$/);
      var p     = match ? match[1] : '';
      var rowId = p + 'row-ciudad';
      PSPTerr.showRow(rowId);
    },

    /* ── SWITCH PANAMÁ / INTERNACIONAL ─────────────────────── */
    switchTipo: function (radio, tipo) {
      var wrap   = radio.closest('.psp-terr-wrap');
      var panama = wrap.querySelector('.psp-terr-panama');
      var inter  = wrap.querySelector('.psp-terr-inter');
      if (!panama || !inter) return;
      if (tipo === 'panama') {
        panama.style.display = 'block';
        inter.style.display  = 'none';
      } else {
        panama.style.display = 'none';
        inter.style.display  = 'block';
      }
    },

    /* ── MOSTRAR FORM NUEVO TERRITORIO ──────────────────────── */
    mostrarFormNuevo: function (wrap) {
      var form = wrap.querySelector('.psp-terr-form-nuevo');
      if (form) form.style.display = form.style.display === 'none' ? 'block' : 'none';
    },

    /* ── ENVIAR SOLICITUD NUEVO TERRITORIO ──────────────────── */
    enviarSolicitudNuevo: async function (wrap) {
      var nombre = (wrap.querySelector('.psp-terr-nuevo-nombre') || {}).value || '';
      var tipo   = (wrap.querySelector('.psp-terr-nuevo-tipo')   || {}).value || 'comunidad';
      var msg    = wrap.querySelector('.psp-terr-nuevo-msg');
      if (!nombre.trim()) { if (msg) msg.textContent = 'Ingresa el nombre.'; return; }
      if (typeof PSP_TERR === 'undefined') return;

      try {
        var r = await fetch(PSP_TERR.ajax_url, {
          method : 'POST',
          body   : new URLSearchParams({
            action    : 'psp_terr_solicitud',
            nombre    : nombre,
            tipo      : tipo,
            psp_nonce : PSP_TERR.nonce
          })
        });
        var d = await r.json();
        if (msg) {
          msg.textContent = d.success
            ? '✅ Solicitud enviada. La revisaremos pronto.'
            : '❌ Error: ' + ((d.data && d.data.message) ? d.data.message : 'intenta de nuevo');
        }
      } catch (e) {
        if (msg) msg.textContent = '❌ Error de conexión.';
      }
    }
  };

  /* ── AUTO-INIT al cargar ────────────────────────────────────── */
  document.addEventListener('DOMContentLoaded', function () {
    PSPTerr.init();
  });

})();
