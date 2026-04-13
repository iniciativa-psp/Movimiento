<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_menu', 'psp_productos_admin_menu' );
function psp_productos_admin_menu() {
    add_submenu_page( 'psp-core', 'Productos', '&#x1F6CD;&#xFE0F; Productos', 'manage_options', 'psp-productos', 'psp_productos_admin_page' );
    add_submenu_page( 'psp-core', 'SIGS Solicitudes', '&#x1F4BC; SIGS', 'manage_options', 'psp-sigs', 'psp_sigs_solicitudes_page' );
}

function psp_productos_admin_page() {
    if ( isset($_POST['psp_save_prod']) && check_admin_referer('psp_productos_config') ) {
        $campos = ['psp_precio_planton','psp_stock_plantones','psp_precio_sigs_basico','psp_precio_sigs_base','psp_precio_sigs_emp'];
        foreach ($campos as $c) {
            if (isset($_POST[$c])) update_option($c, sanitize_text_field($_POST[$c]));
        }
        echo '<div class="updated"><p>&#x2705; Configuración guardada.</p></div>';
    }
    ?>
    <div class="wrap">
      <h1>&#x1F6CD;&#xFE0F; Gestión de Productos</h1>

      <h2>&#x1F4B2; Precios y Stock</h2>
      <form method="post">
        <?php wp_nonce_field('psp_productos_config'); ?>
        <table class="form-table">
          <tr><th>&#x1F331; Precio por plantón (USD)</th>
              <td><input name="psp_precio_planton" type="number" step="0.01" min="0" style="width:100px"
                         value="<?php echo esc_attr(get_option('psp_precio_planton','2')); ?>"></td></tr>
          <tr><th>&#x1F4E6; Stock disponible (plantones)</th>
              <td><input name="psp_stock_plantones" type="number" min="0" style="width:120px"
                         value="<?php echo esc_attr(get_option('psp_stock_plantones','10000')); ?>"></td></tr>
          <tr><th>&#x1F4BC; SIGS Plan Básico (USD/mes)</th>
              <td><input name="psp_precio_sigs_basico" type="number" step="0.01" min="0" style="width:100px"
                         value="<?php echo esc_attr(get_option('psp_precio_sigs_basico','300')); ?>"></td></tr>
          <tr><th>&#x1F4BC; SIGS Plan Estándar (USD/mes)</th>
              <td><input name="psp_precio_sigs_base" type="number" step="0.01" min="0" style="width:100px"
                         value="<?php echo esc_attr(get_option('psp_precio_sigs_base','500')); ?>"></td></tr>
          <tr><th>&#x1F4BC; SIGS Plan Empresarial (USD/mes)</th>
              <td><input name="psp_precio_sigs_emp" type="number" step="0.01" min="0" style="width:100px"
                         value="<?php echo esc_attr(get_option('psp_precio_sigs_emp','1200')); ?>"></td></tr>
        </table>
        <p><button class="button button-primary" name="psp_save_prod">&#x1F4BE; Guardar</button></p>
      </form>

      <hr>
      <h2>&#x1F6D2; Pedidos de Plantones</h2>
      <div id="psp-pedidos-admin">Cargando...</div>
    </div>
    <script>
    jQuery(function($){
      $.post(ajaxurl,{action:'psp_admin_get_pedidos',psp_nonce:'<?= wp_create_nonce("psp_nonce") ?>'},function(d){
        if(!d.success){$('#psp-pedidos-admin').text('Error');return;}
        if(!d.data||!d.data.length){$('#psp-pedidos-admin').text('Sin pedidos todavía.');return;}
        var h='<table class="wp-list-table widefat"><thead><tr><th>Referencia</th><th>Producto</th><th>Cantidad</th><th>Total</th><th>Estado</th><th>Fecha</th></tr></thead><tbody>';
        d.data.forEach(function(p){
          h+='<tr><td><code>'+(p.referencia||'—')+'</code></td><td>'+(p.producto_nombre||'—')+'</td>'
            +'<td>'+(p.cantidad||1)+'</td><td><strong>$'+parseFloat(p.total||0).toFixed(2)+'</strong></td>'
            +'<td>'+(p.estado||'—')+'</td>'
            +'<td>'+(p.created_at?new Date(p.created_at).toLocaleDateString('es-PA'):'—')+'</td></tr>';
        });
        h+='</tbody></table>';
        $('#psp-pedidos-admin').html(h);
      });
    });
    </script>
    <?php
}

function psp_sigs_solicitudes_page() { ?>
    <div class="wrap">
      <h1>&#x1F4BC; Solicitudes SIGS</h1>
      <div id="psp-sigs-admin">Cargando solicitudes...</div>
    </div>
    <script>
    jQuery(function($){
      $.post(ajaxurl,{action:'psp_admin_get_sigs',psp_nonce:'<?= wp_create_nonce("psp_nonce") ?>'},function(d){
        var el=$('#psp-sigs-admin');
        if(!d.success||!d.data||!d.data.length){el.text('Sin solicitudes todavía.');return;}
        var h='<table class="wp-list-table widefat"><thead><tr><th>Nombre</th><th>Org.</th><th>Plan</th><th>Email</th><th>Estado</th><th>Fecha</th></tr></thead><tbody>';
        d.data.forEach(function(s){
          h+='<tr><td><strong>'+(s.nombre||'—')+'</strong></td><td>'+(s.organizacion||'—')+'</td>'
           +'<td>'+(s.plan||'—')+'</td><td>'+(s.email||'—')+'</td>'
           +'<td><select onchange="updateSIGS(\''+s.id+'\',this.value)">'
           +'<option '+(s.estado==='nueva'?'selected':'')+'  value="nueva">Nueva</option>'
           +'<option '+(s.estado==='contactado'?'selected':'')+'value="contactado">Contactado</option>'
           +'<option '+(s.estado==='en_proceso'?'selected':'')+'value="en_proceso">En proceso</option>'
           +'<option '+(s.estado==='cerrado'?'selected':'')+'  value="cerrado">Cerrado</option>'
           +'</select></td>'
           +'<td>'+(s.created_at?new Date(s.created_at).toLocaleDateString('es-PA'):'—')+'</td></tr>';
        });
        h+='</tbody></table>';
        el.html(h);
      });
    });
    function updateSIGS(id,estado){
      jQuery.post(ajaxurl,{action:'psp_update_sigs_estado',id:id,estado:estado,psp_nonce:'<?= wp_create_nonce("psp_nonce") ?>'},function(){});
    }
    </script>
<?php }

// AJAX admin pedidos
add_action('wp_ajax_psp_admin_get_pedidos', function(){
    if(!current_user_can('manage_options')) wp_send_json_error();
    if(!class_exists('PSP_Supabase')) wp_send_json_error(['message'=>'PSP Core no activo']);
    $pedidos = PSP_Supabase::select('pedidos_productos',['order'=>'created_at.desc','limit'=>200]);
    wp_send_json_success($pedidos??[]);
});

// AJAX admin SIGS
add_action('wp_ajax_psp_admin_get_sigs', function(){
    if(!current_user_can('manage_options')) wp_send_json_error();
    if(!class_exists('PSP_Supabase')) wp_send_json_error(['message'=>'PSP Core no activo']);
    $sigs = PSP_Supabase::select('sigs_solicitudes',['order'=>'created_at.desc','limit'=>200]);
    wp_send_json_success($sigs??[]);
});

// AJAX update estado SIGS
add_action('wp_ajax_psp_update_sigs_estado', function(){
    if(!current_user_can('manage_options')) wp_send_json_error();
    $id     = sanitize_text_field($_POST['id']??'');
    $estado = sanitize_text_field($_POST['estado']??'');
    PSP_Supabase::update('sigs_solicitudes',['estado'=>$estado],['id'=>'eq.'.$id]);
    wp_send_json_success();
});
