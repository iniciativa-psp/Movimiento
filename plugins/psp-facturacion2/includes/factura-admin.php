<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_menu', 'psp_facturacion_admin_menu' );
function psp_facturacion_admin_menu() {
    add_submenu_page(
        'psp-core',
        'Facturación DGI',
        '&#x1F9FE; Facturación DGI',
        'manage_options',
        'psp-facturacion-config',
        'psp_facturacion_config_page'
    );
}

function psp_facturacion_config_page() {
    if ( isset( $_POST['psp_save_factura'] ) && check_admin_referer( 'psp_facturacion' ) ) {
        update_option( 'psp_ruc',          sanitize_text_field( $_POST['ruc']          ?? '' ) );
        update_option( 'psp_dv',           sanitize_text_field( $_POST['dv']           ?? '' ) );
        update_option( 'psp_razon_social', sanitize_text_field( $_POST['razon_social'] ?? '' ) );
        update_option( 'psp_pac_url',      sanitize_url( $_POST['pac_url']             ?? '' ) );
        update_option( 'psp_pac_token',    sanitize_text_field( $_POST['pac_token']    ?? '' ) );
        update_option( 'psp_itbms',        sanitize_text_field( $_POST['itbms']        ?? '0' ) );
        echo '<div class="updated"><p>&#x2705; Configuraci&oacute;n guardada.</p></div>';
    }
    ?>
    <div class="wrap">
      <h1>&#x1F9FE; Facturación Electrónica DGI Panamá</h1>

      <form method="post">
        <?php wp_nonce_field( 'psp_facturacion' ); ?>
        <table class="form-table">
          <tr>
            <th scope="row">RUC Empresa</th>
            <td>
              <input class="regular-text" name="ruc"
                     value="<?php echo esc_attr( get_option( 'psp_ruc', '' ) ); ?>"
                     placeholder="Ej: 8-888-88888">
              <p class="description">RUC de Iniciativa Panamá Sin Pobreza</p>
            </td>
          </tr>
          <tr>
            <th scope="row">Dígito Verificador (DV)</th>
            <td>
              <input name="dv" style="width:80px"
                     value="<?php echo esc_attr( get_option( 'psp_dv', '' ) ); ?>"
                     placeholder="Ej: 88">
            </td>
          </tr>
          <tr>
            <th scope="row">Razón Social</th>
            <td>
              <input class="regular-text" name="razon_social"
                     value="<?php echo esc_attr( get_option( 'psp_razon_social', 'Iniciativa Panamá Sin Pobreza' ) ); ?>">
            </td>
          </tr>
          <tr>
            <th scope="row">URL del PAC</th>
            <td>
              <input class="regular-text" name="pac_url"
                     value="<?php echo esc_attr( get_option( 'psp_pac_url', '' ) ); ?>"
                     placeholder="https://tu-pac.com/api/factura">
              <p class="description">URL del Proveedor Autorizado de Certificación (PAC) de la DGI</p>
            </td>
          </tr>
          <tr>
            <th scope="row">Token PAC</th>
            <td>
              <input class="regular-text" name="pac_token" type="password"
                     value="<?php echo esc_attr( get_option( 'psp_pac_token', '' ) ); ?>">
              <p class="description">Token de autenticación entregado por tu PAC</p>
            </td>
          </tr>
          <tr>
            <th scope="row">Tasa ITBMS (%)</th>
            <td>
              <input name="itbms" type="number" step="0.01" style="width:100px"
                     value="<?php echo esc_attr( get_option( 'psp_itbms', '0' ) ); ?>"
                     placeholder="0">
              <p class="description">0% si el servicio está exento. Consulta con tu contador o la DGI.</p>
            </td>
          </tr>
        </table>
        <p>
          <button class="button button-primary" name="psp_save_factura">
            &#x1F4BE; Guardar configuración DGI
          </button>
        </p>
      </form>

      <hr>

      <h2>&#x1F527; Generar factura manualmente</h2>
      <p>Si un pago se completó pero no generó factura automáticamente, ingresa el ID del pago:</p>
      <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <input type="text" id="psp-pago-id-manual"
               placeholder="UUID del pago (ej: a1b2c3d4-...)"
               style="width:380px" class="regular-text">
        <button onclick="pspGenerarFactura()" class="button button-primary">
          Generar Factura
        </button>
      </div>
      <div id="psp-result-factura" style="margin-top:12px"></div>

      <script>
      function pspGenerarFactura() {
        var id  = document.getElementById('psp-pago-id-manual').value.trim();
        var out = document.getElementById('psp-result-factura');
        if ( ! id ) { alert('Ingresa el UUID del pago'); return; }
        out.innerHTML = '<p>&#x23F3; Generando...</p>';
        jQuery.post(
          ajaxurl,
          {
            action    : 'psp_generar_factura_manual',
            pago_id   : id,
            psp_nonce : '<?php echo wp_create_nonce( 'psp_nonce' ); ?>'
          },
          function(d) {
            if (d.success) {
              out.innerHTML = '<div style="color:green;padding:10px;background:#f0fdf4;border-radius:6px">'
                + '&#x2705; Factura generada: <strong>' + d.data.numero + '</strong></div>';
            } else {
              out.innerHTML = '<div style="color:#991b1b;padding:10px;background:#fef2f2;border-radius:6px">'
                + '&#x274C; Error: ' + ( d.data && d.data.message ? d.data.message : 'desconocido' ) + '</div>';
            }
          }
        );
      }
      </script>

      <hr>

      <h2>&#x1F4CB; PACs autorizados por la DGI Panamá</h2>
      <ul style="list-style:disc;padding-left:20px;line-height:2.2">
        <li>
          <strong>e-Factura DGI</strong> (sistema oficial) &mdash;
          <a href="https://efactura.dgi.gob.pa" target="_blank">efactura.dgi.gob.pa</a>
        </li>
        <li><strong>Global PAC</strong> &mdash; proveedor privado autorizado</li>
        <li><strong>Soluciones Fiscales</strong> &mdash; integración vía API REST</li>
      </ul>
      <p><strong>Nota:</strong> Debes contratar un PAC para emitir facturas electrónicas con validez legal ante la DGI.</p>
    </div>
    <?php
}
