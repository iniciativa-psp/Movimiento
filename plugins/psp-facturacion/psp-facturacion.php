<?php
/**
 * Plugin Name: PSP Facturación
 * Plugin URI:  https://panamasinpobreza.org
 * Description: Facturación electrónica DGI Panamá + PAC. XML fiscal, envío al PAC, asiento contable automático.
 * Version:     1.0.2
 * Author:      PSP Dev Team
 * Requires PHP: 7.4
 * Text Domain: psp-facturacion
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// ── Aviso si psp-core no está activo ─────────────────────────────────────────
add_action( 'admin_notices', 'psp_facturacion_check_core' );
function psp_facturacion_check_core() {
    if ( ! class_exists( 'PSP_Supabase' ) ) {
        echo '<div class="notice notice-error"><p>'
           . '<strong>PSP Facturación:</strong> Requiere que <strong>PSP Core</strong> esté activado primero.</p></div>';
    }
}

// ── Cargar sub-archivos después de que todos los plugins carguen ──────────────
add_action( 'plugins_loaded', 'psp_facturacion_load_files' );
function psp_facturacion_load_files() {
    require_once plugin_dir_path( __FILE__ ) . 'includes/factura-generator.php';
    require_once plugin_dir_path( __FILE__ ) . 'includes/factura-ajax.php';
    require_once plugin_dir_path( __FILE__ ) . 'includes/factura-admin.php';
}

// ── Activación ────────────────────────────────────────────────────────────────
register_activation_hook( __FILE__, 'psp_facturacion_activate' );
function psp_facturacion_activate() {
    add_option( 'psp_ruc',          '' );
    add_option( 'psp_dv',           '' );
    add_option( 'psp_pac_url',      '' );
    add_option( 'psp_pac_token',    '' );
    add_option( 'psp_itbms',        '0' );
    add_option( 'psp_razon_social', 'Iniciativa Panamá Sin Pobreza' );
}

// ── Shortcodes ────────────────────────────────────────────────────────────────
add_shortcode( 'psp_mis_facturas', 'psp_mis_facturas_shortcode' );

function psp_mis_facturas_shortcode( $atts = [] ) {
    ob_start();
    ?>
    <div id="psp-mis-facturas" class="psp-card">
      <h3>&#x1F9FE; Mis Facturas</h3>
      <div id="psp-facturas-lista">
        <p style="color:#888">Cargando tus facturas...</p>
      </div>
    </div>

    <style>
    .psp-estado-badge { padding:3px 10px; border-radius:20px; font-size:12px; font-weight:600; }
    .psp-estado-emitida,.psp-estado-aceptada_dgi { background:#f0fdf4; color:#166534; }
    .psp-estado-pendiente,.psp-estado-enviada_pac { background:#fefce8; color:#854d0e; }
    .psp-estado-rechazada,.psp-estado-anulada     { background:#fef2f2; color:#991b1b; }
    </style>

    <script>
    (function() {
      function pspEstadoLabel(e) {
        var m = {
          pendiente    : '&#x23F3; Pendiente',
          emitida      : '&#x2705; Emitida',
          enviada_pac  : '&#x1F4E1; En PAC',
          aceptada_dgi : '&#x1F3DB; DGI OK',
          rechazada    : '&#x274C; Rechazada',
          anulada      : '&#x1F6AB; Anulada'
        };
        return m[e] || e;
      }

      window.PSPFacturas = {
        descargar: async function(id) {
          var r = await fetch(PSP_CONFIG.ajax_url, {
            method : 'POST',
            body   : new URLSearchParams({
              action    : 'psp_descargar_factura',
              id        : id,
              psp_nonce : PSP_CONFIG.nonce
            })
          });
          var d = await r.json();
          if (d.success && d.data && d.data.xml) {
            var blob = new Blob([d.data.xml], {type:'application/xml'});
            var a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = 'factura-' + (d.data.numero_factura || id) + '.xml';
            a.click();
          }
        }
      };

      async function cargarFacturas() {
        var el = document.getElementById('psp-facturas-lista');
        var jwt = (document.cookie.match(/psp_jwt=([^;]+)/) || [])[1];
        if (!jwt) {
          el.innerHTML = '<p>Inicia sesi&oacute;n para ver tus facturas.</p>';
          return;
        }
        var r = await fetch(PSP_CONFIG.ajax_url, {
          method : 'POST',
          body   : new URLSearchParams({
            action    : 'psp_get_mis_facturas',
            psp_nonce : PSP_CONFIG.nonce,
            jwt       : jwt
          })
        });
        var d = await r.json();
        if (!d.success || !d.data || !d.data.length) {
          el.innerHTML = '<p style="color:#888">A&uacute;n no tienes facturas. Realiza tu primer aporte.</p>';
          return;
        }
        var html = '<table class="psp-table">'
          + '<thead><tr><th>N&uacute;mero</th><th>Fecha</th><th>Total</th><th>Estado</th><th>XML</th></tr></thead><tbody>';
        for (var i = 0; i < d.data.length; i++) {
          var f    = d.data[i];
          var fecha = f.fecha_emision ? new Date(f.fecha_emision).toLocaleDateString('es-PA') : '—';
          var total = parseFloat(f.total || 0).toFixed(2);
          var btn  = f.estado !== 'pendiente'
            ? '<button onclick="PSPFacturas.descargar(\'' + f.id + '\')" class="psp-btn psp-btn-sm">&#x1F4C4; XML</button>'
            : '<span style="color:#aaa;font-size:12px">Pendiente</span>';
          html += '<tr>'
            + '<td><strong>' + (f.numero_factura || '—') + '</strong></td>'
            + '<td>' + fecha + '</td>'
            + '<td><strong>$' + total + '</strong></td>'
            + '<td><span class="psp-estado-badge psp-estado-' + (f.estado||'') + '">'
            +   pspEstadoLabel(f.estado) + '</span></td>'
            + '<td>' + btn + '</td>'
            + '</tr>';
        }
        html += '</tbody></table>';
        el.innerHTML = html;
      }

      document.addEventListener('DOMContentLoaded', cargarFacturas);
    })();
    </script>
    <?php
    return ob_get_clean();
}
