<?php
if (!defined('ABSPATH')) exit;

add_action('admin_menu', 'psp_core_admin_menu');
function psp_core_admin_menu(): void {
    add_menu_page(
        'PSP Sistema',
        '🇵🇦 PSP Sistema',
        'manage_options',
        'psp-core',
        'psp_core_settings_page',
        'dashicons-groups',
        2
    );
}

function psp_core_settings_page(): void {
    if (isset($_POST['psp_save']) && check_admin_referer('psp_core_settings')) {
        update_option('psp_supabase_url',         sanitize_url($_POST['supabase_url']));
        update_option('psp_supabase_anon_key',    sanitize_text_field($_POST['supabase_anon_key']));
        update_option('psp_supabase_service_key', sanitize_text_field($_POST['supabase_service_key']));
        update_option('psp_tenant_id',            sanitize_text_field($_POST['tenant_id']));
        update_option('psp_launch_date',          sanitize_text_field($_POST['launch_date']));
        echo '<div class="updated"><p>✅ Configuración guardada.</p></div>';
    }
    ?>
    <div class="wrap" style="font-family:sans-serif">
    <h1>🇵🇦 PSP — Configuración Central</h1>
    <form method="post">
    <?php wp_nonce_field('psp_core_settings'); ?>
    <table class="form-table">
        <tr><th>Supabase URL</th><td><input class="regular-text" name="supabase_url" value="<?= esc_attr(get_option('psp_supabase_url')) ?>"></td></tr>
        <tr><th>Anon Key</th><td><input class="regular-text" name="supabase_anon_key" value="<?= esc_attr(get_option('psp_supabase_anon_key')) ?>"></td></tr>
        <tr><th>Service Key</th><td><input class="regular-text" name="supabase_service_key" type="password" value="<?= esc_attr(get_option('psp_supabase_service_key')) ?>"></td></tr>
        <tr><th>Tenant ID</th><td><input name="tenant_id" value="<?= esc_attr(get_option('psp_tenant_id','panama')) ?>"></td></tr>
        <tr><th>Fecha Lanzamiento</th><td><input name="launch_date" value="<?= esc_attr(get_option('psp_launch_date','2026-05-12T09:00:00')) ?>"></td></tr>
    </table>
    <p><button class="button button-primary" name="psp_save">💾 Guardar</button></p>
    </form>
    </div>
    <?php
}

// ── Sistema Status page ───────────────────────────────────────────────────────
add_action('admin_menu', 'psp_core_status_menu');
function psp_core_status_menu() {
    add_submenu_page('psp-core','Estado del Sistema','&#x1F4CB; Estado','manage_options','psp-status','psp_core_status_page');
}

function psp_core_status_page() {
    $plugins_requeridos = [
        'psp-core/psp-core.php'             => 'PSP Core',
        'psp-auth/psp-auth.php'             => 'PSP Auth',
        'psp-territorial/psp-territorial.php'=> 'PSP Territorial',
        'psp-payments/psp-payments.php'     => 'PSP Payments',
        'psp-membresias/psp-membresias.php' => 'PSP Membresías',
        'psp-productos/psp-productos.php'   => 'PSP Productos',
        'psp-dashboard/psp-dashboard.php'   => 'PSP Dashboard',
        'psp-ranking/psp-ranking.php'       => 'PSP Ranking',
        'psp-referidos/psp-referidos.php'   => 'PSP Referidos',
        'psp-erp/psp-erp.php'              => 'PSP ERP',
        'psp-facturacion/psp-facturacion.php'=> 'PSP Facturación',
        'psp-whatsapp/psp-whatsapp.php'     => 'PSP WhatsApp',
        'psp-notificaciones/psp-notificaciones.php' => 'PSP Notificaciones',
        'psp-pwa/psp-pwa.php'               => 'PSP PWA',
    ];

    $checks = [
        'Supabase URL'      => ! empty( get_option('psp_supabase_url') ),
        'Supabase Anon Key' => ! empty( get_option('psp_supabase_anon_key') ),
        'Supabase Svc Key'  => ! empty( get_option('psp_supabase_service_key') ),
        'RUC (Facturación)' => ! empty( get_option('psp_ruc') ),
        'JSON Territorial'  => ! empty( get_option('psp_territorial_json_url') ) || ! empty( get_option('psp_territorial_json_path') ),
        'Yappy configurado' => ! empty( get_option('psp_yappy_numero') ),
        'PagueloFacil key'  => ! empty( get_option('psp_paguelofacil_key') ),
    ];
    ?>
    <div class="wrap">
      <h1>&#x1F4CB; Estado del Sistema PSP</h1>

      <h2>Plugins PSP</h2>
      <table class="wp-list-table widefat" style="max-width:600px">
        <thead><tr><th>Plugin</th><th>Estado</th></tr></thead>
        <tbody>
          <?php foreach ($plugins_requeridos as $file => $name):
            $activo = is_plugin_active($file);
          ?>
          <tr>
            <td><?php echo esc_html($name); ?></td>
            <td><?php echo $activo
              ? '<span style="color:#166534;font-weight:700">&#x2705; Activo</span>'
              : '<span style="color:#991b1b">&#x274C; No activo</span>'; ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <h2 style="margin-top:24px">Configuración</h2>
      <table class="wp-list-table widefat" style="max-width:600px">
        <thead><tr><th>Elemento</th><th>Estado</th></tr></thead>
        <tbody>
          <?php foreach ($checks as $label => $ok): ?>
          <tr>
            <td><?php echo esc_html($label); ?></td>
            <td><?php echo $ok
              ? '<span style="color:#166534;font-weight:700">&#x2705; Configurado</span>'
              : '<span style="color:#EF9F27">&#x26A0;&#xFE0F; Pendiente</span>'; ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <h2 style="margin-top:24px">Test conexi&oacute;n Supabase</h2>
      <button onclick="testSupa()" class="button button-primary">&#x1F9EA; Probar conexi&oacute;n</button>
      <div id="supa-test-res" style="margin-top:8px;font-size:13px"></div>
    </div>
    <script>
    async function testSupa() {
      var res = document.getElementById('supa-test-res');
      res.textContent = '&#x23F3; Probando...';
      var r = await fetch(ajaxurl+'?action=psp_test_supabase&psp_nonce=<?= wp_create_nonce("psp_nonce") ?>', {method:'POST'});
      var d = await r.json();
      res.innerHTML = d.success
        ? '&#x2705; Conexi&oacute;n exitosa. Supabase responde correctamente.'
        : '&#x274C; Error: ' + (d.data&&d.data.message?d.data.message:'Sin respuesta');
    }
    </script>
    <?php
}

add_action('wp_ajax_psp_test_supabase', function(){
    if (!current_user_can('manage_options')) wp_send_json_error();
    if (!class_exists('PSP_Supabase')) wp_send_json_error(['message'=>'PSP Core no activo']);
    $res = PSP_Supabase::select('configuracion', ['tenant_id'=>'eq.panama','limit'=>1]);
    if ($res !== null) wp_send_json_success();
    else wp_send_json_error(['message'=>'No se pudo conectar a Supabase. Verifica URL y keys.']);
});
