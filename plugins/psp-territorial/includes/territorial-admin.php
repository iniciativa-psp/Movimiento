<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_menu', 'psp_territorial_menu' );
function psp_territorial_menu() {
    add_submenu_page( 'psp-core','Territorial','&#x1F5FA;&#xFE0F; Territorial','manage_options','psp-territorial','psp_territorial_admin' );
}

function psp_territorial_admin() {
    if ( isset($_POST['psp_save_terr']) && check_admin_referer('psp_territorial') ) {
        update_option( 'psp_territorial_json_url',  sanitize_url( $_POST['json_url']  ?? '' ) );
        update_option( 'psp_territorial_json_path', sanitize_text_field( $_POST['json_path'] ?? '' ) );
        update_option( 'psp_territorial_modo',      sanitize_text_field( $_POST['modo']      ?? 'json_externo' ) );
        // Limpiar cache de territorios
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_psp_terr_%'");
        echo '<div class="updated"><p>&#x2705; Configuraci&oacute;n guardada y cach&eacute; limpiada.</p></div>';
    }

    if ( isset($_POST['psp_approve_terr']) && check_admin_referer('psp_territorial') ) {
        $id = sanitize_text_field( $_POST['solicitud_id'] ?? '' );
        if ( $id && class_exists('PSP_Supabase') ) {
            PSP_Supabase::update('territorios_solicitudes',['estado'=>'aprobado'],['id'=>'eq.'.$id]);
            echo '<div class="updated"><p>&#x2705; Territorio aprobado.</p></div>';
        }
    }
    ?>
    <div class="wrap">
      <h1>&#x1F5FA;&#xFE0F; Configuraci&oacute;n Territorial</h1>

      <form method="post">
        <?php wp_nonce_field('psp_territorial'); ?>
        <h2>Fuente de datos territorial</h2>
        <table class="form-table">
          <tr>
            <th>Modo de carga</th>
            <td>
              <select name="modo" id="terr-modo" onchange="toggleTerr(this.value)">
                <option value="json_externo"  <?php selected(get_option('psp_territorial_modo','json_externo'),'json_externo'); ?>>
                  URL JSON externo (tu plugin territorial)
                </option>
                <option value="json_local" <?php selected(get_option('psp_territorial_modo'),'json_local'); ?>>
                  Archivo JSON local (ruta en servidor)
                </option>
                <option value="supabase"   <?php selected(get_option('psp_territorial_modo'),'supabase'); ?>>
                  Supabase (tabla territorios)
                </option>
              </select>
            </td>
          </tr>
          <tr id="row-json-url">
            <th>URL del JSON externo</th>
            <td>
              <input class="large-text" name="json_url"
                     value="<?php echo esc_attr(get_option('psp_territorial_json_url','')); ?>"
                     placeholder="https://tudominio.com/wp-json/territorial/v1/data">
              <p class="description">
                URL de la API REST o archivo JSON de tu plugin territorial existente.<br>
                Formatos soportados:<br>
                &bull; <code>{"provincias":[...],"distritos":[...],"corregimientos":[...],"comunidades":[...]}</code><br>
                &bull; <code>[{"tipo":"provincia","id":1,"nombre":"Panamá"}, ...]</code>
              </p>
            </td>
          </tr>
          <tr id="row-json-path" style="display:none">
            <th>Ruta del archivo JSON</th>
            <td>
              <input class="large-text" name="json_path"
                     value="<?php echo esc_attr(get_option('psp_territorial_json_path','')); ?>"
                     placeholder="/var/www/html/wp-content/plugins/tu-plugin/data/panama.json">
              <p class="description">Ruta absoluta al archivo .json en el servidor.</p>
            </td>
          </tr>
        </table>
        <p>
          <button class="button button-primary" name="psp_save_terr">&#x1F4BE; Guardar</button>
          &nbsp;
          <button type="button" class="button" onclick="testTerr()">&#x1F9EA; Probar conexi&oacute;n</button>
        </p>
        <div id="terr-test-result" style="margin-top:8px;font-size:13px"></div>
      </form>

      <hr>
      <h2>Solicitudes de nuevos territorios</h2>
      <div id="psp-solicitudes-terr">Cargando...</div>
    </div>

    <script>
    function toggleTerr(val) {
      document.getElementById('row-json-url').style.display  = val==='json_externo'?'table-row':'none';
      document.getElementById('row-json-path').style.display = val==='json_local'  ?'table-row':'none';
    }
    toggleTerr(document.getElementById('terr-modo').value);

    function testTerr() {
      var url = document.querySelector('[name="json_url"]').value;
      var res = document.getElementById('terr-test-result');
      if (!url) { res.textContent='Ingresa una URL primero'; return; }
      res.textContent = '&#x23F3; Probando...';
      fetch(url).then(r=>r.json()).then(d=>{
        var tipos = Object.keys(d);
        res.innerHTML = '&#x2705; Conexi&oacute;n exitosa. Campos encontrados: <code>'+tipos.join(', ')+'</code>';
      }).catch(e=>{ res.innerHTML='&#x274C; Error: '+e.message; });
    }

    jQuery(function($){
      $.post(ajaxurl,{action:'psp_terr_get_solicitudes',psp_nonce:'<?= wp_create_nonce("psp_nonce") ?>'},function(d){
        var el=$('#psp-solicitudes-terr');
        if(!d.success||!d.data||!d.data.length){el.text('Sin solicitudes pendientes.');return;}
        var h='<table class="wp-list-table widefat"><thead><tr><th>Nombre</th><th>Tipo</th><th>Estado</th><th>Fecha</th><th>Acción</th></tr></thead><tbody>';
        d.data.forEach(function(s){
          h+='<tr><td><strong>'+s.nombre+'</strong></td><td>'+s.tipo+'</td><td>'+s.estado+'</td>'
            +'<td>'+(s.created_at?new Date(s.created_at).toLocaleDateString('es-PA'):'—')+'</td>'
            +'<td>'+(s.estado==='pendiente'
              ?'<form method="post" style="display:inline"><input type="hidden" name="solicitud_id" value="'+s.id+'"><button name="psp_approve_terr" class="button button-primary">Aprobar</button><?php wp_nonce_field("psp_territorial"); ?></form>'
              :'&#x2705; '+s.estado)+'</td></tr>';
        });
        h+='</tbody></table>';
        el.html(h);
      });
    });
    </script>
    <?php
}

add_action('wp_ajax_psp_terr_get_solicitudes', function(){
    if(!current_user_can('manage_options')) wp_send_json_error();
    if(!class_exists('PSP_Supabase')){ wp_send_json_success([]); return; }
    $data = PSP_Supabase::select('territorios_solicitudes',['order'=>'created_at.desc','limit'=>100]);
    wp_send_json_success($data??[]);
});
