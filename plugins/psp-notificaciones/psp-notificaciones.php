<?php
/**
 * Plugin Name: PSP Notificaciones
 * Plugin URI:  https://panamasinpobreza.org
 * Description: Sistema centralizado de notificaciones: email, WhatsApp (Twilio/360dialog), push web y notificaciones internas del sistema.
 * Version:     1.0.0
 * Author:      PSP Dev Team
 * Requires PHP: 7.4
 * Text Domain: psp-notificaciones
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_notices', 'psp_notif_check_core' );
function psp_notif_check_core() {
    if ( ! class_exists( 'PSP_Supabase' ) )
        echo '<div class="notice notice-error"><p><strong>PSP Notificaciones:</strong> Requiere <strong>PSP Core</strong> activo.</p></div>';
}

add_action( 'plugins_loaded', 'psp_notif_load' );
function psp_notif_load() {
    require_once plugin_dir_path( __FILE__ ) . 'includes/notif-engine.php';
    require_once plugin_dir_path( __FILE__ ) . 'includes/notif-admin.php';
    require_once plugin_dir_path( __FILE__ ) . 'includes/notif-hooks.php';
}

register_activation_hook( __FILE__, function() {
    add_option('psp_twilio_sid',        '');
    add_option('psp_twilio_token',      '');
    add_option('psp_twilio_from',       '');
    add_option('psp_wa_360_token',      '');
    add_option('psp_wa_360_url',        '');
    add_option('psp_notif_email_from',  get_option('admin_email'));
    add_option('psp_notif_activo',      '1');
});

// ── Shortcode: banner de notificaciones del usuario ───────────────────────────
add_shortcode( 'psp_mis_notificaciones', 'psp_mis_notificaciones_sc' );
function psp_mis_notificaciones_sc( $atts = [] ) {
    ob_start(); ?>
    <div id="psp-notif-wrap" class="psp-card">
      <h3>&#x1F514; Mis Notificaciones</h3>
      <div id="psp-notif-lista"><p style="color:#888">Cargando...</p></div>
    </div>
    <script>
    (async function() {
      var jwt = typeof PSPCookie!=='undefined' ? PSPCookie.get('psp_jwt')
              : (document.cookie.match(/psp_jwt=([^;]+)/)||[])[1];
      var el = document.getElementById('psp-notif-lista');
      if (!jwt) { el.innerHTML='<p>Inicia sesi&oacute;n para ver tus notificaciones.</p>'; return; }
      var r = await fetch(PSP_CONFIG.ajax_url, {
        method:'POST',
        body: new URLSearchParams({action:'psp_get_mis_notif', psp_nonce:PSP_CONFIG.nonce, jwt:jwt})
      });
      var d = await r.json();
      if (!d.success||!d.data||!d.data.length) {
        el.innerHTML='<p style="color:#888">Sin notificaciones nuevas.</p>'; return;
      }
      el.innerHTML = d.data.map(function(n) {
        return '<div class="psp-notif-item '+(n.leida?'':'psp-notif-nueva')+'">'
          + '<span class="psp-notif-ico">'+( n.tipo==='pago'?'&#x1F4B3;':n.tipo==='referido'?'&#x1F517;':n.tipo==='nivel'?'&#x1F31F;':'&#x1F514;' )+'</span>'
          + '<div><div class="psp-notif-msg">'+n.mensaje+'</div>'
          + '<div class="psp-notif-fecha" style="font-size:11px;color:#888">'+(n.created_at?new Date(n.created_at).toLocaleString('es-PA'):'')+'</div></div>'
          + '</div>';
      }).join('');
    })();
    </script>
    <style>
    .psp-notif-item{display:flex;gap:12px;padding:12px 0;border-bottom:1px solid #E2E8F0;align-items:flex-start}
    .psp-notif-nueva{background:rgba(11,94,67,.04);border-radius:8px;padding:12px}
    .psp-notif-ico{font-size:22px;flex-shrink:0}
    .psp-notif-msg{font-size:14px;color:#111;font-weight:500}
    </style>
    <?php
    return ob_get_clean();
}
