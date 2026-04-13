<?php
/**
 * Plugin Name: PSP PWA
 * Plugin URI:  https://panamasinpobreza.org
 * Description: Progressive Web App para Panamá Sin Pobreza. Inyecta manifest, service worker, prompt de instalación, página offline y soporte push notifications.
 * Version:     1.0.0
 * Author:      PSP Dev Team
 * Requires PHP: 7.4
 * Text Domain: psp-pwa
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// ── Head tags ─────────────────────────────────────────────────────────────────
add_action( 'wp_head', 'psp_pwa_head_tags', 1 );
function psp_pwa_head_tags() {
    $theme_color = '#0B5E43';
    $icon_url    = plugin_dir_url( __FILE__ ) . 'assets/icon-192.png';
    ?>
    <link rel="manifest" href="<?php echo esc_url( home_url('/psp-manifest.json') ); ?>">
    <meta name="theme-color" content="<?php echo esc_attr($theme_color); ?>">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="PSP">
    <link rel="apple-touch-icon" href="<?php echo esc_url($icon_url); ?>">
    <?php
}

// ── Servir manifest.json dinámicamente ────────────────────────────────────────
add_action( 'init', 'psp_pwa_register_routes' );
function psp_pwa_register_routes() {
    add_rewrite_rule( '^psp-manifest\.json$', 'index.php?psp_manifest=1', 'top' );
    add_rewrite_rule( '^psp-sw\.js$',         'index.php?psp_sw=1',       'top' );
    add_rewrite_rule( '^offline/?$',           'index.php?psp_offline=1',  'top' );
}

add_filter( 'query_vars', function($vars){ $vars[]='psp_manifest'; $vars[]='psp_sw'; $vars[]='psp_offline'; return $vars; } );

add_action( 'template_redirect', 'psp_pwa_serve_files' );
function psp_pwa_serve_files() {
    if ( get_query_var('psp_manifest') ) {
        header('Content-Type: application/manifest+json; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        $icon = plugin_dir_url( __FILE__ ) . 'assets/';
        echo wp_json_encode([
            'name'             => get_bloginfo('name') ?: 'Panamá Sin Pobreza',
            'short_name'       => 'PSP',
            'description'      => 'Únete al Movimiento Nacional. 1 millón de panameños. 1 millón de dólares.',
            'start_url'        => '/',
            'display'          => 'standalone',
            'background_color' => '#FFFFFF',
            'theme_color'      => '#0B5E43',
            'orientation'      => 'portrait-primary',
            'lang'             => 'es',
            'scope'            => '/',
            'icons'            => [
                ['src' => $icon.'icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any maskable'],
                ['src' => $icon.'icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
            ],
            'shortcuts' => [
                ['name'=>'Registrarme', 'url'=>'/registro/', 'icons'=>[['src'=>$icon.'icon-192.png','sizes'=>'192x192']]],
                ['name'=>'Apoyar',      'url'=>'/apoyar/',   'icons'=>[['src'=>$icon.'icon-192.png','sizes'=>'192x192']]],
                ['name'=>'Mi Cuenta',   'url'=>'/mi-cuenta/','icons'=>[['src'=>$icon.'icon-192.png','sizes'=>'192x192']]],
                ['name'=>'Ranking',     'url'=>'/ranking/',  'icons'=>[['src'=>$icon.'icon-192.png','sizes'=>'192x192']]],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
        exit;
    }

    if ( get_query_var('psp_sw') ) {
        header('Content-Type: application/javascript; charset=utf-8');
        header('Cache-Control: no-cache');
        readfile( plugin_dir_path(__FILE__) . 'assets/sw.js' );
        exit;
    }

    if ( get_query_var('psp_offline') ) {
        wp_head(); // load theme
        echo psp_pwa_offline_page();
        wp_footer();
        exit;
    }
}

// ── Encolar script de instalación ────────────────────────────────────────────
add_action( 'wp_enqueue_scripts', 'psp_pwa_enqueue' );
function psp_pwa_enqueue() {
    wp_enqueue_script( 'psp-pwa', plugin_dir_url(__FILE__) . 'assets/psp-pwa.js', [], '1.0.0', true );
    wp_localize_script( 'psp-pwa', 'PSP_PWA', [
        'sw_url'   => home_url('/psp-sw.js'),
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('psp_nonce'),
    ]);
}

// ── Shortcode botón instalar ──────────────────────────────────────────────────
add_shortcode('psp_instalar_app', 'psp_instalar_app_sc');
function psp_instalar_app_sc( $atts = [] ) {
    return '<button id="psp-pwa-install-btn" onclick="PSPInstallPWA()"
             class="psp-btn psp-btn-primary" style="display:none">
               &#x1F4F1; Instalar App
             </button>';
}

// ── Admin: push VAPID key config ──────────────────────────────────────────────
add_action('admin_menu', 'psp_pwa_admin_menu');
function psp_pwa_admin_menu() {
    add_submenu_page('psp-core','PWA','&#x1F4F1; PWA','manage_options','psp-pwa','psp_pwa_admin_page');
}

function psp_pwa_admin_page() {
    if (isset($_POST['psp_save_pwa']) && check_admin_referer('psp_pwa')) {
        update_option('psp_vapid_public',  sanitize_text_field($_POST['vapid_public']  ?? ''));
        update_option('psp_vapid_private', sanitize_text_field($_POST['vapid_private'] ?? ''));
        // Flush rewrite rules
        flush_rewrite_rules();
        echo '<div class="updated"><p>&#x2705; Guardado. <a href="'.home_url('/psp-manifest.json').'" target="_blank">Ver manifest</a></p></div>';
    }
    if (isset($_POST['psp_flush']) && check_admin_referer('psp_pwa')) {
        flush_rewrite_rules();
        echo '<div class="updated"><p>&#x2705; Rutas actualizadas.</p></div>';
    }
    ?>
    <div class="wrap">
      <h1>&#x1F4F1; Configuración PWA</h1>
      <p>
        <strong>Manifest:</strong>
        <a href="<?php echo esc_url(home_url('/psp-manifest.json')); ?>" target="_blank">
          <?php echo esc_url(home_url('/psp-manifest.json')); ?>
        </a>
      </p>
      <form method="post">
        <?php wp_nonce_field('psp_pwa'); ?>
        <table class="form-table">
          <tr>
            <th>VAPID Public Key (Push)</th>
            <td><input class="large-text" name="vapid_public"
                       value="<?php echo esc_attr(get_option('psp_vapid_public','')); ?>"
                       placeholder="Genera en: web-push-codelab.glitch.me">
                <p class="description">Para notificaciones push. Opcional.</p></td>
          </tr>
          <tr>
            <th>VAPID Private Key</th>
            <td><input class="large-text" name="vapid_private" type="password"
                       value="<?php echo esc_attr(get_option('psp_vapid_private','')); ?>"></td>
          </tr>
        </table>
        <p>
          <button class="button button-primary" name="psp_save_pwa">&#x1F4BE; Guardar</button>
          &nbsp;
          <button class="button" name="psp_flush">&#x1F504; Actualizar rutas</button>
        </p>
      </form>
    </div>
    <?php
}

function psp_pwa_offline_page() {
    return '<div style="min-height:100vh;display:flex;align-items:center;justify-content:center;font-family:sans-serif;background:#fff;text-align:center;padding:40px">
      <div>
        <div style="font-size:64px">&#x1F4F5;</div>
        <h1 style="font-size:24px;font-weight:800;color:#0B5E43;margin:16px 0 8px">Sin conexi&oacute;n</h1>
        <p style="color:#555;max-width:320px;margin:0 auto 20px">
          Parece que no tienes internet. Vuelve a intentarlo cuando tengas conexi&oacute;n.
        </p>
        <button onclick="location.reload()"
                style="background:#0B5E43;color:#fff;border:none;padding:12px 28px;border-radius:8px;font-size:15px;font-weight:700;cursor:pointer">
          &#x1F504; Reintentar
        </button>
      </div>
    </div>';
}

// ── Flush rewrite on activation ───────────────────────────────────────────────
register_activation_hook(__FILE__, function(){ flush_rewrite_rules(); });
register_deactivation_hook(__FILE__, function(){ flush_rewrite_rules(); });
