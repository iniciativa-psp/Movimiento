<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Asegurar is_plugin_active() en contexto admin
if ( is_admin() && ! function_exists( 'is_plugin_active' ) ) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

// ── Menú principal PSP ───────────────────────────────────────────────────────
add_action( 'admin_menu', 'psp2_admin_menu' );
function psp2_admin_menu(): void {
    add_menu_page(
        'PSP Sistema v2',
        '&#x1F1F5;&#x1F1E6; PSP v2',
        'manage_options',
        'psp-core2',
        'psp2_settings_page',
        'dashicons-groups',
        3
    );
    add_submenu_page(
        'psp-core2',
        'Estado del Sistema',
        '&#x1F4CB; Estado',
        'manage_options',
        'psp2-status',
        'psp2_status_page'
    );
    add_submenu_page(
        'psp-core2',
        'Estado del Sistema (Legacy)',
        '&#x1F4CB; Estado (Legacy)',
        'manage_options',
        'psp-status-legacy',
        'psp2_legacy_status_page'
    );
}

// ── Página de configuración ──────────────────────────────────────────────────
function psp2_settings_page(): void {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'No autorizado', 'psp-core2' ) );
    }

    if ( isset( $_POST['psp2_save'] ) && check_admin_referer( 'psp2_core_settings' ) ) {
        update_option( 'psp2_supabase_url',         sanitize_url( wp_unslash( $_POST['supabase_url'] ?? '' ) ) );
        update_option( 'psp2_supabase_anon_key',    sanitize_text_field( wp_unslash( $_POST['supabase_anon_key'] ?? '' ) ) );
        update_option( 'psp2_supabase_service_key', sanitize_text_field( wp_unslash( $_POST['supabase_service_key'] ?? '' ) ) );
        update_option( 'psp2_tenant_id',            sanitize_text_field( wp_unslash( $_POST['tenant_id'] ?? 'panama' ) ) );
        update_option( 'psp2_launch_date',          sanitize_text_field( wp_unslash( $_POST['launch_date'] ?? '' ) ) );
        update_option( 'psp2_campaign_start',       sanitize_text_field( wp_unslash( $_POST['campaign_start'] ?? '' ) ) );
        update_option( 'psp2_campaign_end',         sanitize_text_field( wp_unslash( $_POST['campaign_end'] ?? '' ) ) );
        update_option( 'psp2_membership_fee',       number_format( (float) ( $_POST['membership_fee'] ?? 1.00 ), 2, '.', '' ) );
        update_option( 'psp2_meta_miembros',        absint( $_POST['meta_miembros'] ?? 1000000 ) );
        update_option( 'psp2_meta_monto',           absint( $_POST['meta_monto'] ?? 1000000 ) );
        echo '<div class="updated"><p>&#x2705; Configuraci&oacute;n guardada.</p></div>';
    }
    ?>
    <div class="wrap">
    <h1>&#x1F1F5;&#x1F1E6; PSP v2 &mdash; Configuraci&oacute;n Central</h1>
    <form method="post">
    <?php wp_nonce_field( 'psp2_core_settings' ); ?>

    <h2>Supabase</h2>
    <table class="form-table">
      <tr>
        <th scope="row">Supabase URL</th>
        <td><input class="regular-text" name="supabase_url"
                   value="<?php echo esc_attr( get_option( 'psp2_supabase_url' ) ); ?>"
                   placeholder="https://xxxx.supabase.co"></td>
      </tr>
      <tr>
        <th scope="row">Anon Key (p&uacute;blica)</th>
        <td><input class="regular-text" name="supabase_anon_key"
                   value="<?php echo esc_attr( get_option( 'psp2_supabase_anon_key' ) ); ?>"></td>
      </tr>
      <tr>
        <th scope="row">Service Role Key <small>(solo servidor)</small></th>
        <td><input class="regular-text" name="supabase_service_key" type="password"
                   value="<?php echo esc_attr( get_option( 'psp2_supabase_service_key' ) ); ?>"></td>
      </tr>
      <tr>
        <th scope="row">Tenant ID</th>
        <td><input name="tenant_id"
                   value="<?php echo esc_attr( get_option( 'psp2_tenant_id', 'panama' ) ); ?>"></td>
      </tr>
    </table>

    <h2>Campa&ntilde;a</h2>
    <table class="form-table">
      <tr>
        <th scope="row">Inicio de Campa&ntilde;a</th>
        <td>
          <input type="datetime-local" name="campaign_start"
                 value="<?php echo esc_attr( str_replace( ' ', 'T', get_option( 'psp2_campaign_start', '2026-04-14T00:00:00' ) ) ); ?>">
          <p class="description">Fecha/hora UTC. Defecto: 14 abr 2026 00:00.</p>
        </td>
      </tr>
      <tr>
        <th scope="row">Fin de Campa&ntilde;a</th>
        <td>
          <input type="datetime-local" name="campaign_end"
                 value="<?php echo esc_attr( str_replace( ' ', 'T', get_option( 'psp2_campaign_end', '2026-05-18T23:59:59' ) ) ); ?>">
          <p class="description">Defecto: 18 may 2026 23:59.</p>
        </td>
      </tr>
      <tr>
        <th scope="row">Cuota de Membres&iacute;a (B/.)</th>
        <td>
          <input type="number" name="membership_fee" min="0.01" step="0.01"
                 style="width:100px"
                 value="<?php echo esc_attr( get_option( 'psp2_membership_fee', '1.00' ) ); ?>">
        </td>
      </tr>
      <tr>
        <th scope="row">Meta Miembros</th>
        <td><input type="number" name="meta_miembros" style="width:140px"
                   value="<?php echo esc_attr( get_option( 'psp2_meta_miembros', 1000000 ) ); ?>"></td>
      </tr>
      <tr>
        <th scope="row">Meta Recaudaci&oacute;n (B/.)</th>
        <td><input type="number" name="meta_monto" style="width:140px"
                   value="<?php echo esc_attr( get_option( 'psp2_meta_monto', 1000000 ) ); ?>"></td>
      </tr>
      <tr>
        <th scope="row">Fecha Lanzamiento P&uacute;blico</th>
        <td>
          <input name="launch_date"
                 value="<?php echo esc_attr( get_option( 'psp2_launch_date', '2026-04-14T00:00:00' ) ); ?>">
          <p class="description">ISO 8601 &mdash; usado por el contador regresivo.</p>
        </td>
      </tr>
    </table>

    <p><button class="button button-primary" name="psp2_save">&#x1F4BE; Guardar configuraci&oacute;n</button></p>
    </form>
    </div>
    <?php
}

// ── Página de estado del sistema ─────────────────────────────────────────────
function psp2_status_page(): void {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'No autorizado', 'psp-core2' ) );
    }

    $plugins_v2 = [
        'psp-core2/psp-core2.php'           => 'PSP Core 2',
        'psp-auth2/psp-auth2.php'           => 'PSP Auth 2',
        'psp-territorial2/psp-territorial2.php' => 'PSP Territorial 2',
        'psp-payments2/psp-payments2.php'   => 'PSP Payments 2',
        'psp-referidos2/psp-referidos2.php' => 'PSP Referidos 2',
        'psp-ranking2/psp-ranking2.php'     => 'PSP Ranking 2',
        'psp-whatsapp2/psp-whatsapp2.php'   => 'PSP WhatsApp 2',
        'psp-dashboard2/psp-dashboard2.php' => 'PSP Dashboard 2',
    ];

    $checks = [
        'Supabase URL'      => ! empty( get_option( 'psp2_supabase_url' ) ),
        'Supabase Anon Key' => ! empty( get_option( 'psp2_supabase_anon_key' ) ),
        'Supabase Svc Key'  => ! empty( get_option( 'psp2_supabase_service_key' ) ),
        'Tenant ID'         => ! empty( get_option( 'psp2_tenant_id' ) ),
    ];
    ?>
    <div class="wrap">
      <h1>&#x1F4CB; Estado del Sistema PSP v2</h1>

      <h2>Plugins PSP v2</h2>
      <table class="wp-list-table widefat" style="max-width:600px">
        <thead><tr><th>Plugin</th><th>Estado</th></tr></thead>
        <tbody>
          <?php foreach ( $plugins_v2 as $file => $name ) :
              $activo = is_plugin_active( $file );
          ?>
          <tr>
            <td><?php echo esc_html( $name ); ?></td>
            <td><?php echo $activo
                ? '<span style="color:#166534;font-weight:700">&#x2705; Activo</span>'
                : '<span style="color:#991b1b">&#x274C; No activo</span>'; ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <h2 style="margin-top:24px">Configuraci&oacute;n</h2>
      <table class="wp-list-table widefat" style="max-width:600px">
        <thead><tr><th>Elemento</th><th>Estado</th></tr></thead>
        <tbody>
          <?php foreach ( $checks as $label => $ok ) : ?>
          <tr>
            <td><?php echo esc_html( $label ); ?></td>
            <td><?php echo $ok
                ? '<span style="color:#166534;font-weight:700">&#x2705; Configurado</span>'
                : '<span style="color:#EF9F27">&#x26A0;&#xFE0F; Pendiente</span>'; ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <h2 style="margin-top:24px">Test conexi&oacute;n Supabase</h2>
      <button onclick="psp2TestSupa()" class="button button-primary">&#x1F9EA; Probar conexi&oacute;n</button>
      <div id="psp2-supa-res" style="margin-top:8px;font-size:13px"></div>
    </div>
    <script>
    async function psp2TestSupa() {
      var el = document.getElementById('psp2-supa-res');
      el.textContent = '\u23F3 Probando\u2026';
      try {
        var r = await fetch(ajaxurl + '?action=psp2_test_supabase&psp2_nonce=<?php echo esc_js( wp_create_nonce( 'psp2_nonce' ) ); ?>', { method: 'POST' });
        var d = await r.json();
        el.innerHTML = d.success
          ? '&#x2705; Conexi&oacute;n exitosa.'
          : '&#x274C; Error: ' + (d.data && d.data.message ? d.data.message : 'Sin respuesta');
      } catch(e) {
        el.textContent = '&#x274C; Error de red: ' + e.message;
      }
    }
    </script>
    <?php
}

// ── Página de estado del sistema (Legacy) ────────────────────────────────────
function psp2_legacy_status_page(): void {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'No autorizado', 'psp-core2' ) );
    }

    // Legacy slugs → display name + optional v2 equivalent slug
    $plugins_legacy = [
        'psp-core/psp-core.php'                     => [ 'PSP Core',          'psp-core2/psp-core2.php' ],
        'psp-auth/psp-auth.php'                     => [ 'PSP Auth',          'psp-auth2/psp-auth2.php' ],
        'psp-territorial/psp-territorial.php'       => [ 'PSP Territorial',   'psp-territorial2/psp-territorial2.php' ],
        'psp-payments/psp-payments.php'             => [ 'PSP Payments',      'psp-payments2/psp-payments2.php' ],
        'psp-membresias/psp-membresias.php'         => [ 'PSP Membresías',    'psp-membresias2/psp-membresias2.php' ],
        'psp-productos/psp-productos.php'           => [ 'PSP Productos',     'psp-productos2/psp-productos2.php' ],
        'psp-dashboard/psp-dashboard.php'           => [ 'PSP Dashboard',     'psp-dashboard2/psp-dashboard2.php' ],
        'psp-ranking/psp-ranking.php'               => [ 'PSP Ranking',       'psp-ranking2/psp-ranking2.php' ],
        'psp-referidos/psp-referidos.php'           => [ 'PSP Referidos',     'psp-referidos2/psp-referidos2.php' ],
        'psp-erp/psp-erp.php'                       => [ 'PSP ERP',           'psp-erp2/psp-erp2.php' ],
        'psp-facturacion/psp-facturacion.php'       => [ 'PSP Facturación',   'psp-facturacion2/psp-facturacion2.php' ],
        'psp-whatsapp/psp-whatsapp.php'             => [ 'PSP WhatsApp',      'psp-whatsapp2/psp-whatsapp2.php' ],
        'psp-notificaciones/psp-notificaciones.php' => [ 'PSP Notificaciones','psp-notificaciones2/psp-notificaciones2.php' ],
        'psp-pwa/psp-pwa.php'                       => [ 'PSP PWA',           'psp-pwa2/psp-pwa2.php' ],
    ];

    // JSON Territorial: configured if legacy option set OR if territorial2 is active (REST mode)
    $terr_legacy_ok = ! empty( get_option( 'psp_territorial_json_url' ) )
                   || ! empty( get_option( 'psp_territorial_json_path' ) );
    $terr_v2_ok     = is_plugin_active( 'psp-territorial2/psp-territorial2.php' );
    $terr_ok        = $terr_legacy_ok || $terr_v2_ok;

    // Configuration checks using legacy option names; Supabase falls back to v2 options when legacy are absent
    $checks = [
        'Supabase URL'      => ! empty( get_option( 'psp_supabase_url' ) ) || ! empty( get_option( 'psp2_supabase_url' ) ),
        'Supabase Anon Key' => ! empty( get_option( 'psp_supabase_anon_key' ) ) || ! empty( get_option( 'psp2_supabase_anon_key' ) ),
        'Supabase Svc Key'  => ! empty( get_option( 'psp_supabase_service_key' ) ) || ! empty( get_option( 'psp2_supabase_service_key' ) ),
        'RUC (Facturación)' => ! empty( get_option( 'psp_ruc' ) ),
        'JSON Territorial'  => $terr_ok,
        'Yappy configurado' => ! empty( get_option( 'psp_yappy_numero' ) ),
        'PagueloFacil key'  => ! empty( get_option( 'psp_paguelofacil_key' ) ),
    ];

    $terr_note = ( ! $terr_legacy_ok && $terr_v2_ok )
        ? wp_kses( ' <em style="font-size:11px;color:#166534">(psp-territorial2 REST activo)</em>', [ 'em' => [ 'style' => [] ] ] )
        : '';
    ?>
    <div class="wrap">
      <h1>&#x1F4CB; Estado del Sistema PSP</h1>
      <p style="color:#555;font-size:13px">Vista de compatibilidad con el ecosistema PSP v1. Los plugins v2 activos cuentan como equivalentes legacy.</p>

      <h2>Plugins PSP</h2>
      <table class="wp-list-table widefat" style="max-width:640px">
        <thead><tr><th>Plugin</th><th>Estado</th></tr></thead>
        <tbody>
          <?php foreach ( $plugins_legacy as $legacy_file => $info ) :
              [ $name, $v2_file ] = $info;
              $legacy_active = is_plugin_active( $legacy_file );
              $v2_active     = ! empty( $v2_file ) && is_plugin_active( $v2_file );
          ?>
          <tr>
            <td><?php echo esc_html( $name ); ?></td>
            <td>
              <?php if ( $legacy_active ) : ?>
                <span style="color:#166534;font-weight:700">&#x2705; Activo</span>
              <?php elseif ( $v2_active ) : ?>
                <span style="color:#166534;font-weight:700">&#x2705; Activo</span>
                <em style="font-size:11px;color:#166534">(v2)</em>
              <?php else : ?>
                <span style="color:#991b1b">&#x274C; No activo</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <h2 style="margin-top:24px">Configuraci&oacute;n</h2>
      <table class="wp-list-table widefat" style="max-width:640px">
        <thead><tr><th>Elemento</th><th>Estado</th></tr></thead>
        <tbody>
          <?php foreach ( $checks as $label => $ok ) : ?>
          <tr>
            <td>
              <?php echo esc_html( $label ); ?>
              <?php if ( 'JSON Territorial' === $label ) echo $terr_note; // safe: constructed with wp_kses() ?>
            </td>
            <td><?php echo $ok
                ? '<span style="color:#166534;font-weight:700">&#x2705; Configurado</span>'
                : '<span style="color:#EF9F27">&#x26A0;&#xFE0F; Pendiente</span>'; ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <h2 style="margin-top:24px">Test conexi&oacute;n Supabase</h2>
      <button onclick="pspLegacyTestSupabase()" class="button button-primary">&#x1F9EA; Probar conexi&oacute;n</button>
      <div id="psp-legacy-supa-res" style="margin-top:8px;font-size:13px"></div>
    </div>
    <script>
    async function pspLegacyTestSupabase() {
      var el = document.getElementById('psp-legacy-supa-res');
      el.textContent = '\u23F3 Probando\u2026';
      try {
        var r = await fetch(ajaxurl + '?action=psp2_test_supabase&psp2_nonce=<?php echo esc_js( wp_create_nonce( 'psp2_nonce' ) ); ?>', { method: 'POST' });
        var d = await r.json();
        el.innerHTML = d.success
          ? '&#x2705; Conexi&oacute;n exitosa. Supabase responde correctamente.'
          : '&#x274C; Error: ' + (d.data && d.data.message ? d.data.message : 'Sin respuesta');
      } catch(e) {
        el.textContent = '&#x274C; Error de red: ' + e.message;
      }
    }
    </script>
    <?php
}

// ── AJAX: test conexión Supabase ─────────────────────────────────────────────
add_action( 'wp_ajax_psp2_test_supabase', 'psp2_ajax_test_supabase' );
function psp2_ajax_test_supabase(): void {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'No autorizado' ] );
    }
    if ( ! check_ajax_referer( 'psp2_nonce', 'psp2_nonce', false ) ) {
        wp_send_json_error( [ 'message' => 'Nonce inválido' ] );
    }
    if ( ! class_exists( 'PSP2_Supabase' ) ) {
        wp_send_json_error( [ 'message' => 'PSP Core 2 no activo' ] );
    }
    $res = PSP2_Supabase::select( 'configuracion', [
        'tenant_id' => 'eq.' . get_option( 'psp2_tenant_id', 'panama' ),
        'limit'     => 1,
    ] );
    if ( $res !== null ) {
        wp_send_json_success();
    } else {
        wp_send_json_error( [ 'message' => 'No se pudo conectar a Supabase. Verifica URL y keys.' ] );
    }
}
