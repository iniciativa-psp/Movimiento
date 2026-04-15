<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Asegurar is_plugin_active() en admin
if ( is_admin() && ! function_exists( 'is_plugin_active' ) ) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

add_action( 'admin_menu', 'psp2_territorial_admin_menu' );
function psp2_territorial_admin_menu(): void {
    // Si PSP Core2 está activo, registrar como submenú; si no, menú propio
    $parent = is_plugin_active( 'psp-core2/psp-core2.php' ) ? 'psp-core2' : null;

    if ( $parent ) {
        add_submenu_page(
            $parent,
            'Territorial 2',
            '&#x1F5FA;&#xFE0F; Territorial 2',
            'manage_options',
            'psp2-territorial',
            'psp2_territorial_admin_page'
        );
    } else {
        add_menu_page(
            'PSP Territorial 2',
            '&#x1F5FA;&#xFE0F; PSP Territorial 2',
            'manage_options',
            'psp2-territorial',
            'psp2_territorial_admin_page',
            'dashicons-location-alt',
            50
        );
    }
}

function psp2_territorial_admin_page(): void {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'No autorizado', 'psp-territorial2' ) );
    }

    if ( isset( $_POST['psp2_terr_save'] ) && check_admin_referer( 'psp2_territorial_settings' ) ) {
        update_option( 'psp2_territorial_json_url', sanitize_url( wp_unslash( $_POST['json_url'] ?? '' ) ) );
        $modo_new = sanitize_key( wp_unslash( $_POST['modo'] ?? 'json_url' ) );
        if ( ! in_array( $modo_new, [ 'json_url', 'pspv2_rest', 'inline' ], true ) ) {
            $modo_new = 'json_url';
        }
        update_option( 'psp2_territorial_modo', $modo_new );
        // Limpiar caché
        $cache_key = 'psp2_terr_json_' . md5( get_option( 'psp2_territorial_json_url' ) );
        delete_transient( $cache_key );
        // Limpiar caché REST
        foreach ( [ 'provincia', 'distrito', 'corregimiento', 'comunidad' ] as $t ) {
            global $wpdb;
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                    '_transient_psp2_terr_rest_' . $t . '_%'
                )
            );
        }
        echo '<div class="updated"><p>&#x2705; Configuraci&oacute;n guardada.</p></div>';
    }
    ?>
    <div class="wrap">
      <h1>&#x1F5FA;&#xFE0F; PSP Territorial 2 &mdash; Configuraci&oacute;n</h1>
      <form method="post">
        <?php wp_nonce_field( 'psp2_territorial_settings' ); ?>
        <table class="form-table">
          <tr id="psp2-json-row" <?php echo get_option( 'psp2_territorial_modo', 'json_url' ) !== 'json_url' ? 'style="display:none"' : ''; ?>>
            <th scope="row">URL del JSON territorial</th>
            <td>
              <input class="regular-text" name="json_url"
                     value="<?php echo esc_attr( get_option( 'psp2_territorial_json_url', '' ) ); ?>"
                     placeholder="https://ejemplo.com/panama.json">
              <p class="description">
                Solo requerida en modo <strong>JSON externo</strong>.
                JSON con estructura: <code>[{"id":"1","nombre":"Panam&aacute;","tipo":"provincia","parent_id":""},...]</code>
              </p>
            </td>
          </tr>
          <tr>
            <th scope="row">Modo de carga</th>
            <td>
              <select name="modo" id="psp2_terr_modo" onchange="document.getElementById('psp2-json-row').style.display=this.value==='json_url'?'':'none'">
                <option value="json_url"   <?php selected( get_option( 'psp2_territorial_modo', 'json_url' ), 'json_url' ); ?>>JSON externo (URL)</option>
                <option value="pspv2_rest" <?php selected( get_option( 'psp2_territorial_modo', 'json_url' ), 'pspv2_rest' ); ?>>PSP Territorial V2 (REST local)</option>
                <option value="inline"     <?php selected( get_option( 'psp2_territorial_modo', 'json_url' ), 'inline' ); ?>>Inline (JS directo)</option>
              </select>
              <p class="description">
                <strong>PSP Territorial V2 (REST local)</strong>: usa los endpoints REST del plugin <em>PSP Territorial V2</em>
                (<code>/wp-json/psp-territorial/v2/...</code>). No requiere URL JSON. Si ese plugin no est&aacute; instalado,
                los selectores quedar&aacute;n vac&iacute;os con mensaje de contacto.
              </p>
            </td>
          </tr>
        </table>
        <p><button class="button button-primary" name="psp2_terr_save">&#x1F4BE; Guardar</button></p>
      </form>
    </div>
    <?php
}
