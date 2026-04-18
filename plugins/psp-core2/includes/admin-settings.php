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
    // Legacy slug kept for backward-compatibility redirects; hidden from menu.
    add_submenu_page(
        null,
        'Estado del Sistema (Legacy)',
        '&#x1F4CB; Estado (Legacy)',
        'manage_options',
        'psp-status-legacy',
        'psp2_status_legacy_page'
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

// ── Página de estado del sistema (unificada) ─────────────────────────────────
function psp2_status_page(): void {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'No autorizado', 'psp-core2' ) );
    }

    // ── PSP v2 plugins ────────────────────────────────────────────────────────
    $plugins_v2 = [
        'psp-core2/psp-core2.php'                     => 'PSP Core 2',
        'psp-auth2/psp-auth2.php'                     => 'PSP Auth 2',
        'psp-territorial2/psp-territorial2.php'       => 'PSP Territorial 2',
        'psp-dashboard2/psp-dashboard.php'            => 'PSP Dashboard 2',
        'psp-payments2/psp-payments.php'              => 'PSP Payments 2',
        'psp-ranking2/psp-ranking.php'                => 'PSP Ranking 2',
        'psp-referidos2/psp-referidos.php'            => 'PSP Referidos 2',
        'psp-whatsapp2/psp-whatsapp.php'              => 'PSP WhatsApp 2',
        'psp-erp2/psp-erp.php'                        => 'PSP ERP 2',
        'psp-notificaciones2/psp-notificaciones.php'  => 'PSP Notificaciones 2',
        'psp-membresias2/psp-membresias.php'          => 'PSP Membres&iacute;as 2',
        'psp-productos2/psp-productos.php'            => 'PSP Productos 2',
        'psp-facturacion2/psp-facturacion.php'        => 'PSP Facturaci&oacute;n 2',
        'psp-pwa2/psp-pwa.php'                        => 'PSP PWA 2',
    ];

    // ── Legacy / compatibility plugin list ───────────────────────────────────
    $plugins_legacy = [
        'psp-core/psp-core.php'             => [
            'name'     => 'PSP Core',
            'v2'       => 'psp-core2/psp-core2.php',
            'settings' => 'psp-core2',
        ],
        'psp-auth/psp-auth.php'             => [
            'name'     => 'PSP Auth',
            'v2'       => 'psp-auth2/psp-auth2.php',
            'settings' => 'psp2-auth',
        ],
        'psp-territorial/psp-territorial.php' => [
            'name'     => 'PSP Territorial',
            'v2'       => 'psp-territorial2/psp-territorial2.php',
            'settings' => 'psp2-territorial',
        ],
        'psp-payments/psp-payments.php'     => [
            'name'     => 'PSP Payments',
            'v2'       => 'psp-payments2/psp-payments.php',
            'settings' => null,
        ],
        'psp-membresias/psp-membresias.php' => [
            'name'     => 'PSP Membres&iacute;as',
            'v2'       => 'psp-membresias2/psp-membresias.php',
            'settings' => null,
        ],
        'psp-productos/psp-productos.php'   => [
            'name'     => 'PSP Productos',
            'v2'       => 'psp-productos2/psp-productos.php',
            'settings' => null,
        ],
        'psp-dashboard/psp-dashboard.php'   => [
            'name'     => 'PSP Dashboard',
            'v2'       => 'psp-dashboard2/psp-dashboard.php',
            'settings' => null,
        ],
        'psp-ranking/psp-ranking.php'       => [
            'name'     => 'PSP Ranking',
            'v2'       => 'psp-ranking2/psp-ranking.php',
            'settings' => null,
        ],
        'psp-referidos/psp-referidos.php'   => [
            'name'     => 'PSP Referidos',
            'v2'       => 'psp-referidos2/psp-referidos.php',
            'settings' => null,
        ],
        'psp-erp/psp-erp.php'               => [
            'name'     => 'PSP ERP',
            'v2'       => 'psp-erp2/psp-erp.php',
            'settings' => 'psp-erp',
        ],
        'psp-facturacion/psp-facturacion.php' => [
            'name'     => 'PSP Facturaci&oacute;n',
            'v2'       => 'psp-facturacion2/psp-facturacion.php',
            'settings' => 'psp-facturacion-config',
        ],
        'psp-whatsapp/psp-whatsapp.php'     => [
            'name'     => 'PSP WhatsApp',
            'v2'       => 'psp-whatsapp2/psp-whatsapp.php',
            'settings' => 'psp-whatsapp',
        ],
        'psp-notificaciones/psp-notificaciones.php' => [
            'name'     => 'PSP Notificaciones',
            'v2'       => 'psp-notificaciones2/psp-notificaciones.php',
            'settings' => 'psp-notificaciones',
        ],
        'psp-pwa/psp-pwa.php'               => [
            'name'     => 'PSP PWA',
            'v2'       => 'psp-pwa2/psp-pwa.php',
            'settings' => 'psp-pwa',
        ],
    ];

    // "JSON Territorial" ok when legacy JSON options set OR REST pipeline active.
    $territorial_rest_ok = is_plugin_active( 'psp-territorial2/psp-territorial2.php' )
        && is_plugin_active( 'psp-territorial-v2/psp-territorial-v2.php' );

    // Payments 2 "Configurar" link (reused for Yappy and PagueloFácil rows).
    $payments_link = is_plugin_active( 'psp-payments2/psp-payments.php' )
        ? admin_url( 'plugins.php?s=psp-payments2' )
        : admin_url( 'plugins.php' );

    // ── Centralized configuration items (ordered by priority) ────────────────
    // prioridad: 1 = Crítica, 2 = Alta, 3 = Media
    $config_central = [
        [
            'label'    => 'Supabase URL',
            'ok'       => ! empty( get_option( 'psp2_supabase_url' ) ) || ! empty( get_option( 'psp_supabase_url' ) ),
            'uso'      => 'Core 2, Auth 2, Payments 2, Referidos 2, Ranking 2',
            'prioridad'=> 'Cr&iacute;tica',
            'prio_num' => 1,
            'link'     => admin_url( 'admin.php?page=psp-core2' ),
        ],
        [
            'label'    => 'Supabase Anon Key',
            'ok'       => ! empty( get_option( 'psp2_supabase_anon_key' ) ) || ! empty( get_option( 'psp_supabase_anon_key' ) ),
            'uso'      => 'Core 2 (lecturas p&uacute;blicas, front-end)',
            'prioridad'=> 'Cr&iacute;tica',
            'prio_num' => 1,
            'link'     => admin_url( 'admin.php?page=psp-core2' ),
        ],
        [
            'label'    => 'Supabase Service Key',
            'ok'       => ! empty( get_option( 'psp2_supabase_service_key' ) ) || ! empty( get_option( 'psp_supabase_service_key' ) ),
            'uso'      => 'Core 2 (solo servidor)',
            'prioridad'=> 'Cr&iacute;tica',
            'prio_num' => 1,
            'link'     => admin_url( 'admin.php?page=psp-core2' ),
        ],
        [
            'label'    => 'Tenant ID',
            'ok'       => ! empty( get_option( 'psp2_tenant_id' ) ) || ! empty( get_option( 'psp_tenant_id' ) ),
            'uso'      => 'Core 2, Territorial 2',
            'prioridad'=> 'Cr&iacute;tica',
            'prio_num' => 1,
            'link'     => admin_url( 'admin.php?page=psp-core2' ),
        ],
        [
            'label'    => 'PAC URL (Facturaci&oacute;n)',
            'ok'       => ! empty( get_option( 'psp_pac_url' ) ),
            'uso'      => 'Facturaci&oacute;n 2 (DGI)',
            'prioridad'=> 'Cr&iacute;tica',
            'prio_num' => 1,
            'link'     => is_plugin_active( 'psp-facturacion2/psp-facturacion.php' )
                       ? admin_url( 'admin.php?page=psp-facturacion-config' )
                       : admin_url( 'plugins.php' ),
        ],
        [
            'label'    => 'PAC Token (Facturaci&oacute;n)',
            'ok'       => ! empty( get_option( 'psp_pac_token' ) ),
            'uso'      => 'Facturaci&oacute;n 2 (DGI)',
            'prioridad'=> 'Cr&iacute;tica',
            'prio_num' => 1,
            'link'     => is_plugin_active( 'psp-facturacion2/psp-facturacion.php' )
                       ? admin_url( 'admin.php?page=psp-facturacion-config' )
                       : admin_url( 'plugins.php' ),
        ],
        [
            'label'    => 'Yappy n&uacute;mero',
            'ok'       => ! empty( get_option( 'psp_yappy_numero' ) ) || ! empty( get_option( 'psp2_yappy_numero' ) ),
            'uso'      => 'Payments 2 (Yappy)',
            'prioridad'=> 'Alta',
            'prio_num' => 2,
            'link'     => $payments_link,
        ],
        [
            'label'    => 'PagueloF&aacute;cil key',
            'ok'       => ! empty( get_option( 'psp_paguelofacil_key' ) ) || ! empty( get_option( 'psp2_paguelofacil_key' ) ),
            'uso'      => 'Payments 2 (PagueloF&aacute;cil)',
            'prioridad'=> 'Alta',
            'prio_num' => 2,
            'link'     => $payments_link,
        ],
        [
            'label'    => 'Twilio SID (SMS)',
            'ok'       => ! empty( get_option( 'psp_twilio_sid' ) ),
            'uso'      => 'Notificaciones 2 (SMS)',
            'prioridad'=> 'Alta',
            'prio_num' => 2,
            'link'     => is_plugin_active( 'psp-notificaciones2/psp-notificaciones.php' )
                       ? admin_url( 'admin.php?page=psp-notificaciones' )
                       : admin_url( 'plugins.php' ),
        ],
        [
            'label'    => 'Twilio Token (SMS)',
            'ok'       => ! empty( get_option( 'psp_twilio_token' ) ),
            'uso'      => 'Notificaciones 2 (SMS)',
            'prioridad'=> 'Alta',
            'prio_num' => 2,
            'link'     => is_plugin_active( 'psp-notificaciones2/psp-notificaciones.php' )
                       ? admin_url( 'admin.php?page=psp-notificaciones' )
                       : admin_url( 'plugins.php' ),
        ],
        [
            'label'    => 'WhatsApp 360dialog Token',
            'ok'       => ! empty( get_option( 'psp_wa_360_token' ) ),
            'uso'      => 'Notificaciones 2 (WhatsApp)',
            'prioridad'=> 'Alta',
            'prio_num' => 2,
            'link'     => is_plugin_active( 'psp-notificaciones2/psp-notificaciones.php' )
                       ? admin_url( 'admin.php?page=psp-notificaciones' )
                       : admin_url( 'plugins.php' ),
        ],
        [
            'label'    => 'VAPID Public Key (PWA Push)',
            'ok'       => ! empty( get_option( 'psp_vapid_public' ) ),
            'uso'      => 'PWA 2 (notificaciones push)',
            'prioridad'=> 'Alta',
            'prio_num' => 2,
            'link'     => is_plugin_active( 'psp-pwa2/psp-pwa.php' )
                       ? admin_url( 'admin.php?page=psp-pwa' )
                       : admin_url( 'plugins.php' ),
        ],
        [
            'label'    => 'VAPID Private Key (PWA Push)',
            'ok'       => ! empty( get_option( 'psp_vapid_private' ) ),
            'uso'      => 'PWA 2 (notificaciones push)',
            'prioridad'=> 'Alta',
            'prio_num' => 2,
            'link'     => is_plugin_active( 'psp-pwa2/psp-pwa.php' )
                       ? admin_url( 'admin.php?page=psp-pwa' )
                       : admin_url( 'plugins.php' ),
        ],
        [
            'label'    => 'RUC (Facturaci&oacute;n)',
            'ok'       => ! empty( get_option( 'psp_ruc' ) ) || ! empty( get_option( 'psp2_ruc' ) ),
            'uso'      => 'Facturaci&oacute;n 2 (DGI)',
            'prioridad'=> 'Media',
            'prio_num' => 3,
            'link'     => is_plugin_active( 'psp-facturacion2/psp-facturacion.php' )
                       ? admin_url( 'admin.php?page=psp-facturacion-config' )
                       : admin_url( 'plugins.php' ),
        ],
        [
            'label'    => 'JSON Territorial',
            'ok'       => ! empty( get_option( 'psp_territorial_json_url' ) )
                       || ! empty( get_option( 'psp_territorial_json_path' ) )
                       || $territorial_rest_ok,
            'uso'      => 'Territorial (datos geogr&aacute;ficos)',
            'prioridad'=> 'Media',
            'prio_num' => 3,
            'link'     => is_plugin_active( 'psp-territorial2/psp-territorial2.php' )
                       ? admin_url( 'admin.php?page=psp2-territorial' )
                       : admin_url( 'plugins.php' ),
        ],
        [
            'label'    => 'Auth 2 &mdash; URL Privacidad',
            'ok'       => ! empty( get_option( 'psp2_privacy_url' ) ),
            'uso'      => 'Auth 2 (formulario de registro)',
            'prioridad'=> 'Media',
            'prio_num' => 3,
            'link'     => is_plugin_active( 'psp-auth2/psp-auth2.php' )
                       ? admin_url( 'admin.php?page=psp2-auth' )
                       : admin_url( 'plugins.php' ),
        ],
    ];

    // Priority badge styles.
    $prio_style = [
        1 => 'background:#fef2f2;color:#991b1b;border:1px solid #fca5a5;padding:2px 7px;border-radius:4px;font-size:11px;font-weight:700',
        2 => 'background:#fffbeb;color:#92400e;border:1px solid #fcd34d;padding:2px 7px;border-radius:4px;font-size:11px;font-weight:700',
        3 => 'background:#f0fdf4;color:#166534;border:1px solid #86efac;padding:2px 7px;border-radius:4px;font-size:11px',
    ];
    ?>
    <div class="wrap">
      <h1>&#x1F4CB; Estado del Sistema PSP v2</h1>
      <p class="description">Vista unificada: detecta plugins v2 y sus equivalentes legacy (v1). Los valores secretos nunca se muestran.</p>

      <h2>Plugins PSP v2</h2>
      <table class="wp-list-table widefat" style="max-width:660px">
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

      <h2 style="margin-top:28px">Plugins PSP (Compatibilidad Legacy)</h2>
      <p class="description">Verifica tanto la versi&oacute;n legacy (v1) como su equivalente v2.</p>
      <table class="wp-list-table widefat" style="max-width:960px">
        <thead>
          <tr><th>Plugin</th><th>Estado</th><th>Gestionar</th></tr>
        </thead>
        <tbody>
          <?php foreach ( $plugins_legacy as $legacy_file => $info ) :
              $v2_file       = $info['v2'];
              $settings_slug = $info['settings'];

              $legacy_active   = is_plugin_active( $legacy_file );
              $v2_installed    = file_exists( WP_PLUGIN_DIR . '/' . $v2_file );
              $v2_active       = $v2_installed && is_plugin_active( $v2_file );

              if ( $legacy_active ) {
                  $status_html = '<span style="color:#166534;font-weight:700">&#x2705; Activo</span>';
                  $active_file = $legacy_file;
              } elseif ( $v2_active ) {
                  $status_html = '<span style="color:#166534;font-weight:700">&#x2705; Activo</span>'
                               . ' <span style="color:#1d4ed8;font-size:11px">(v2)</span>';
                  $active_file = $v2_file;
              } else {
                  $status_html = '<span style="color:#991b1b">&#x274C; No activo</span>';
                  $active_file = null;
              }

              $manage_links = [];
              if ( null !== $active_file ) {
                  $folder         = dirname( $active_file );
                  $manage_links[] = '<a href="' . esc_url( admin_url( 'plugins.php?s=' . rawurlencode( $folder ) ) ) . '">'
                                  . esc_html__( 'Ver en Plugins', 'psp-core2' ) . '</a>';
              } else {
                  $legacy_installed = file_exists( WP_PLUGIN_DIR . '/' . $legacy_file );
                  if ( $legacy_installed ) {
                      $act_url        = wp_nonce_url(
                          admin_url( 'plugins.php?action=activate&plugin=' . $legacy_file ),
                          'activate-plugin_' . $legacy_file
                      );
                      $manage_links[] = '<a href="' . esc_url( $act_url ) . '">'
                                      . esc_html__( 'Activar (legacy)', 'psp-core2' ) . '</a>';
                  } elseif ( $v2_installed ) {
                      $act_url        = wp_nonce_url(
                          admin_url( 'plugins.php?action=activate&plugin=' . $v2_file ),
                          'activate-plugin_' . $v2_file
                      );
                      $manage_links[] = '<a href="' . esc_url( $act_url ) . '">'
                                      . esc_html__( 'Activar (v2)', 'psp-core2' ) . '</a>';
                  } else {
                      $manage_links[] = '<span style="color:#6b7280">'
                                      . esc_html__( 'No instalado', 'psp-core2' ) . '</span>';
                      $manage_links[] = '<a href="' . esc_url( admin_url( 'plugin-install.php' ) ) . '">'
                                      . esc_html__( 'Buscar plugins', 'psp-core2' ) . '</a>';
                  }
              }
              if ( null !== $settings_slug && null !== $active_file ) {
                  $manage_links[] = '<a href="' . esc_url( admin_url( 'admin.php?page=' . $settings_slug ) ) . '">'
                                  . esc_html__( 'Configurar', 'psp-core2' ) . '</a>';
              }
          ?>
          <tr>
            <td><?php echo wp_kses_post( $info['name'] ); ?></td>
            <td><?php echo wp_kses_post( $status_html ); ?></td>
            <td><?php echo wp_kses_post( implode( ' &nbsp;|&nbsp; ', $manage_links ) ); ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <h2 style="margin-top:28px">Configuraci&oacute;n (Centralizada)</h2>
      <p class="description">
        Resumen priorizado de todos los elementos de configuraci&oacute;n del ecosistema PSP.
        Los valores secretos <strong>no se muestran</strong>; solo se indica si est&aacute;n configurados o pendientes.
      </p>
      <table class="wp-list-table widefat" style="max-width:960px">
        <thead>
          <tr>
            <th>Elemento</th>
            <th>Estado</th>
            <th>D&oacute;nde se usa</th>
            <th>Prioridad</th>
            <th>Configurar</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ( $config_central as $item ) : ?>
          <tr>
            <td><?php echo wp_kses_post( $item['label'] ); ?></td>
            <td><?php echo $item['ok']
                ? '<span style="color:#166534;font-weight:700">&#x2705; Configurado</span>'
                : '<span style="color:#EF9F27;font-weight:700">&#x26A0;&#xFE0F; Pendiente</span>'; ?></td>
            <td style="color:#374151;font-size:12px"><?php echo wp_kses_post( $item['uso'] ); ?></td>
            <td><span style="<?php echo esc_attr( $prio_style[ $item['prio_num'] ] ); ?>"><?php echo wp_kses_post( $item['prioridad'] ); ?></span></td>
            <td>
              <?php if ( ! empty( $item['link'] ) ) : ?>
              <a href="<?php echo esc_url( $item['link'] ); ?>"><?php esc_html_e( 'Configurar', 'psp-core2' ); ?></a>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <h2 style="margin-top:28px">Test conexi&oacute;n Supabase</h2>
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
          ? '&#x2705; Conexi&oacute;n exitosa. Supabase responde correctamente.'
          : '&#x274C; Error: ' + (d.data && d.data.message ? d.data.message : 'Sin respuesta');
      } catch(e) {
        el.textContent = '&#x274C; Error de red: ' + e.message;
      }
    }
    </script>
    <?php
}

// ── Página de estado del sistema (Legacy) — shim de compatibilidad ───────────
// Esta página redirige silenciosamente a la nueva vista unificada psp2-status.
// El slug 'psp-status-legacy' se mantiene registrado (sin aparecer en el menú)
// para que los marcadores/enlaces antiguos sigan funcionando.
function psp2_status_legacy_page(): void {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'No autorizado', 'psp-core2' ) );
    }
    wp_safe_redirect( admin_url( 'admin.php?page=psp2-status' ) );
    exit;
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
