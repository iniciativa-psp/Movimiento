<?php
/**
 * Plugin Name: PSP Payments
 * Description: Sistema completo de pagos: Yappy, ACH Panamá, Tarjetas (Banco General), Sistema Clave (PagueloFacil), PuntoPago, Transferencias, Efectivo. Webhooks + facturación automática.
 * Version:     1.0.0
 */
if (!defined('ABSPATH')) exit;

require_once plugin_dir_path(__FILE__) . 'includes/payment-core.php';
require_once plugin_dir_path(__FILE__) . 'includes/yappy.php';
require_once plugin_dir_path(__FILE__) . 'includes/paguelofacil.php';
require_once plugin_dir_path(__FILE__) . 'includes/tarjetas-bg.php';
require_once plugin_dir_path(__FILE__) . 'includes/transferencia.php';
require_once plugin_dir_path(__FILE__) . 'webhooks/webhook-handler.php';

add_shortcode('psp_pagos', 'psp_pagos_shortcode');

function psp_pagos_shortcode(array $atts = []): string {
    $atts = shortcode_atts(['miembro_id' => '', 'monto' => '5', 'tipo' => 'nacional'], $atts);
    ob_start(); ?>
    <div id="psp-pagos-wrap" class="psp-card">
      <h2>💳 Realiza tu Aporte</h2>
      <div class="psp-membresia-selector">
        <label>Tipo de membresía:</label>
        <select id="psp-tipo-membresia" onchange="PSPPagos.calcularMonto(this.value)">
          <option value="nacional:5">🇵🇦 Miembro Nacional — $5.00</option>
          <option value="internacional:10">🌎 Miembro Internacional — $10.00</option>
          <option value="actor:25">🎭 Actor / Coalición — $25.00</option>
          <option value="sector:50">🏢 Sector / Empresa — $50.00</option>
          <option value="hogar_solidario:15">🏠 Hogar Solidario — desde $15.00</option>
          <option value="productor:20">🌾 Productor Beneficiario — $20.00</option>
          <option value="planton:2">🌱 Plantón (Reforestación) — $2.00 c/u</option>
        </select>
      </div>
      <div id="psp-calculadora-hogares" style="display:none" class="psp-calc-box">
        <label>Cantidad de hogares: <input type="number" id="psp-qty-hogares" min="1" value="1" onchange="PSPPagos.calcHogares(this.value)"></label>
        <div id="psp-calc-resultado"></div>
      </div>
      <div class="psp-monto-display">
        Monto a pagar: <span id="psp-monto-final"><strong>$5.00</strong></span>
      </div>
      <h3>Selecciona método de pago:</h3>
      <div class="psp-metodos-grid">
        <button onclick="PSPPagos.iniciar('yappy')"     class="psp-metodo-btn psp-yappy">🟡 Yappy</button>
        <button onclick="PSPPagos.iniciar('clave')"     class="psp-metodo-btn psp-clave">🔑 Sistema Clave</button>
        <button onclick="PSPPagos.iniciar('tarjeta')"   class="psp-metodo-btn psp-tarjeta">💳 Tarjeta</button>
        <button onclick="PSPPagos.iniciar('ach')"       class="psp-metodo-btn psp-ach">🏦 ACH Panamá</button>
        <button onclick="PSPPagos.iniciar('puntopago')" class="psp-metodo-btn psp-pp">🏪 PuntoPago</button>
        <button onclick="PSPPagos.iniciar('transferencia')" class="psp-metodo-btn psp-transfer">🔄 Transferencia</button>
        <button onclick="PSPPagos.iniciar('efectivo')"  class="psp-metodo-btn psp-efectivo">💵 Efectivo</button>
      </div>
      <div id="psp-pago-form-container"></div>
      <div id="psp-pago-status"></div>
    </div>

    <script>
    const PSPPagos = {
      monto: 5,
      tipo: 'nacional',
      montos: {nacional:5, internacional:10, actor:25, sector:50, hogar_solidario:15, productor:20, planton:2},

      calcularMonto(val) {
        const [tipo, monto] = val.split(':');
        this.tipo = tipo; this.monto = parseFloat(monto);
        document.getElementById('psp-monto-final').innerHTML = '<strong>$' + this.monto.toFixed(2) + '</strong>';
        document.getElementById('psp-calculadora-hogares').style.display =
          (tipo === 'hogar_solidario' || tipo === 'planton') ? 'block' : 'none';
        this.calcHogares(document.getElementById('psp-qty-hogares')?.value || 1);
      },

      calcHogares(qty) {
        qty = parseInt(qty) || 1;
        const tipo = this.tipo;
        let empleos = 0, personas = 0;
        if (tipo === 'hogar_solidario') {
          empleos   = Math.floor(qty * 0.15);
          personas  = Math.floor(qty * 3.8);
          this.monto = qty * 15;
        } else if (tipo === 'planton') {
          empleos  = Math.floor(qty * 0.02);
          personas = Math.floor(qty * 0.1);
          this.monto = qty * 2;
        }
        const r = document.getElementById('psp-calc-resultado');
        if (r) r.innerHTML = `
          <div class="psp-calc-stats">
            <span>💰 Total: <b>$${this.monto.toFixed(2)}</b></span>
            <span>👔 Empleos generados: <b>~${empleos.toLocaleString()}</b></span>
            <span>👨‍👩‍👧 Personas fuera de pobreza: <b>~${personas.toLocaleString()}</b></span>
          </div>`;
        document.getElementById('psp-monto-final').innerHTML = '<strong>$' + this.monto.toFixed(2) + '</strong>';
      },

      async iniciar(metodo) {
        const miembro_id = document.cookie.match(/psp_miembro_id=([^;]+)/)?.[1];
        if (!miembro_id) { return alert('Debes registrarte primero para realizar el pago.'); }
        const container = document.getElementById('psp-pago-form-container');
        container.innerHTML = '<p>⏳ Preparando pago...</p>';

        try {
          const r = await fetch(PSP_CONFIG.ajax_url, {
            method: 'POST',
            body: new URLSearchParams({
              action: 'psp_crear_pago',
              metodo, monto: this.monto, tipo: this.tipo,
              miembro_id, psp_nonce: PSP_CONFIG.nonce
            })
          });
          const d = await r.json();
          if (d.success) {
            this.renderMetodo(metodo, d.data);
          } else {
            container.innerHTML = '<p>❌ ' + (d.data?.message || 'Error') + '</p>';
          }
        } catch(e) { container.innerHTML = '<p>❌ Error de conexión</p>'; }
      },

      renderMetodo(metodo, data) {
        const c = document.getElementById('psp-pago-form-container');
        const maps = {
          yappy:       `<div class="psp-instrucciones"><h4>🟡 Pago por Yappy</h4><p>Escanea el QR o usa el número:</p><div class="psp-qr-placeholder">QR: ${data.referencia}</div><p><b>Número Yappy:</b> ${data.numero_yappy || '+507-XXXX-XXXX'}</p><p><b>Monto:</b> $${data.monto}</p><p><b>Referencia:</b> ${data.referencia}</p><button onclick="PSPPagos.confirmarManual('${data.pago_id}')" class="psp-btn">Ya pagué — Confirmar</button></div>`,
          clave:       `<div class="psp-instrucciones"><h4>🔑 Sistema Clave / PagueloFacil</h4>${data.checkout_url ? '<a href="'+data.checkout_url+'" target="_blank" class="psp-btn psp-btn-primary">Ir a PagueloFacil</a>' : '<p>Referencia: '+data.referencia+'</p>'}</div>`,
          tarjeta:     `<div class="psp-instrucciones"><h4>💳 Pago con Tarjeta (Banco General)</h4>${data.checkout_url ? '<a href="'+data.checkout_url+'" target="_blank" class="psp-btn psp-btn-primary">Pagar con Tarjeta</a>' : '<p>Ref: '+data.referencia+'</p>'}</div>`,
          ach:         `<div class="psp-instrucciones"><h4>🏦 Transferencia ACH Panamá</h4><p><b>Banco:</b> ${data.banco||'Banco General'}</p><p><b>Cuenta:</b> ${data.cuenta||'XX-XXX-XXXXXX-X'}</p><p><b>Referencia:</b> ${data.referencia}</p><p><b>Monto:</b> $${data.monto}</p><button onclick="PSPPagos.subirComprobante('${data.pago_id}')" class="psp-btn">Subir comprobante</button></div>`,
          transferencia:`<div class="psp-instrucciones"><h4>🔄 Transferencia Internacional</h4><p>SWIFT / IBAN disponible por correo. Referencia: <b>${data.referencia}</b></p><button onclick="PSPPagos.subirComprobante('${data.pago_id}')" class="psp-btn">Subir comprobante</button></div>`,
          puntopago:   `<div class="psp-instrucciones"><h4>🏪 PuntoPago</h4><p>Código para pagar en cualquier punto:</p><h2>${data.referencia}</h2><p>Monto: $${data.monto}</p></div>`,
          efectivo:    `<div class="psp-instrucciones"><h4>💵 Pago en Efectivo</h4><p>Acércate a tu coordinador territorial o a las oficinas de PSP con esta referencia:</p><h2>${data.referencia}</h2></div>`,
        };
        c.innerHTML = maps[metodo] || '<p>Método en configuración</p>';
      },

      async confirmarManual(pago_id) {
        const r = await fetch(PSP_CONFIG.ajax_url, {
          method:'POST',
          body: new URLSearchParams({action:'psp_confirmar_pago_manual', pago_id, psp_nonce: PSP_CONFIG.nonce})
        });
        const d = await r.json();
        document.getElementById('psp-pago-status').innerHTML = d.success
          ? '<div class="psp-success">✅ Pago registrado. Pendiente de validación. ¡Gracias por tu apoyo!</div>'
          : '<div class="psp-error">❌ ' + (d.data?.message || 'Error') + '</div>';
      },

      subirComprobante(pago_id) {
        const c = document.getElementById('psp-pago-form-container');
        c.innerHTML += `<div class="psp-upload"><label>Sube tu comprobante:</label>
          <input type="file" id="psp-comprobante-file" accept="image/*,.pdf">
          <button onclick="PSPPagos.enviarComprobante('${pago_id}')" class="psp-btn">Enviar comprobante</button></div>`;
      },

      async enviarComprobante(pago_id) {
        const file = document.getElementById('psp-comprobante-file').files[0];
        if (!file) return alert('Selecciona un archivo');
        const fd = new FormData();
        fd.append('action', 'psp_subir_comprobante');
        fd.append('pago_id', pago_id);
        fd.append('comprobante', file);
        fd.append('psp_nonce', PSP_CONFIG.nonce);
        const r = await fetch(PSP_CONFIG.ajax_url, {method:'POST', body: fd});
        const d = await r.json();
        document.getElementById('psp-pago-status').textContent = d.success ? '✅ Comprobante enviado. En revisión.' : '❌ Error';
      }
    };
    </script>
    <?php
    return ob_get_clean();
}
