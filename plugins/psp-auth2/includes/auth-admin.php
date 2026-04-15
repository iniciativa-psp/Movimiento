<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Asegurar is_plugin_active() en contexto admin
if ( is_admin() && ! function_exists( 'is_plugin_active' ) ) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

add_action( 'admin_menu', 'psp2_auth_admin_menu' );
function psp2_auth_admin_menu(): void {
    $parent = is_plugin_active( 'psp-core2/psp-core2.php' ) ? 'psp-core2' : null;

    if ( $parent ) {
        add_submenu_page(
            $parent,
            'Auth 2 / Registro',
            '&#x1F511; Auth 2',
            'manage_options',
            'psp2-auth',
            'psp2_auth_admin_page'
        );
    } else {
        add_menu_page(
            'PSP Auth 2',
            '&#x1F511; PSP Auth 2',
            'manage_options',
            'psp2-auth',
            'psp2_auth_admin_page',
            'dashicons-id-alt',
            51
        );
    }
}

function psp2_auth_admin_page(): void {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'No autorizado', 'psp-auth2' ) );
    }

    if ( isset( $_POST['psp2_auth_save'] ) && check_admin_referer( 'psp2_auth_settings' ) ) {
        update_option( 'psp2_privacy_url', sanitize_url( wp_unslash( $_POST['privacy_url'] ?? '' ) ) );
        echo '<div class="updated"><p>&#x2705; Configuraci&oacute;n guardada.</p></div>';
    }
    ?>
    <div class="wrap">
      <h1>&#x1F511; PSP Auth 2 &mdash; Configuraci&oacute;n</h1>
      <form method="post">
        <?php wp_nonce_field( 'psp2_auth_settings' ); ?>
        <table class="form-table">
          <tr>
            <th scope="row"><label for="privacy_url">URL Pol&iacute;tica de Privacidad</label></th>
            <td>
              <input type="url" id="privacy_url" name="privacy_url" class="regular-text"
                     value="<?php echo esc_attr( get_option( 'psp2_privacy_url', home_url( '/privacidad/' ) ) ); ?>"
                     placeholder="<?php echo esc_attr( home_url( '/privacidad/' ) ); ?>">
              <p class="description">
                URL de la p&aacute;gina de pol&iacute;tica de privacidad. Se muestra como enlace en el pie del formulario de registro.
                Deja en blanco para ocultar el enlace (solo se muestra el texto).
              </p>
            </td>
          </tr>
        </table>
        <p><button class="button button-primary" name="psp2_auth_save">&#x1F4BE; Guardar</button></p>
      </form>
    </div>
    <?php
}
