<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_menu', 'psp_membresias_admin_menu' );
function psp_membresias_admin_menu() {
    add_submenu_page( 'psp-core', 'Membresías', '&#x1F3F7;&#xFE0F; Membresías', 'manage_options', 'psp-membresias', 'psp_membresias_admin_page' );
}

function psp_membresias_admin_page() {
    // Guardar precios
    if ( isset( $_POST['psp_save_precios'] ) && check_admin_referer( 'psp_membresias_precios' ) ) {
        foreach ( psp_get_membresias_config() as $m ) {
            $key = 'psp_precio_' . $m['tipo'];
            if ( isset( $_POST[ $key ] ) ) {
                update_option( $key, sanitize_text_field( $_POST[ $key ] ) );
            }
        }
        echo '<div class="updated"><p>&#x2705; Precios actualizados.</p></div>';
    }
    ?>
    <div class="wrap">
      <h1>&#x1F3F7;&#xFE0F; Gestión de Membresías</h1>

      <!-- KPIs -->
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:16px;margin:20px 0" id="mem-kpis">
        <div class="psp-erp-kpi"><h3>Total activos</h3><div class="erp-val" id="k-total">—</div></div>
        <div class="psp-erp-kpi"><h3>Nacionales</h3><div class="erp-val" id="k-nac">—</div></div>
        <div class="psp-erp-kpi"><h3>Internacionales</h3><div class="erp-val" id="k-int">—</div></div>
        <div class="psp-erp-kpi"><h3>Actores</h3><div class="erp-val" id="k-act">—</div></div>
        <div class="psp-erp-kpi"><h3>Hogares</h3><div class="erp-val" id="k-hog">—</div></div>
        <div class="psp-erp-kpi"><h3>Productores</h3><div class="erp-val" id="k-pro">—</div></div>
      </div>

      <hr>

      <!-- Tabla de precios -->
      <h2>&#x1F4B2; Precios por Tipo de Membresía</h2>
      <form method="post">
        <?php wp_nonce_field( 'psp_membresias_precios' ); ?>
        <table class="wp-list-table widefat" style="max-width:700px">
          <thead><tr><th>Icono</th><th>Tipo</th><th>Descripción</th><th>Precio (USD)</th></tr></thead>
          <tbody>
            <?php foreach ( psp_get_membresias_config() as $m ) : ?>
            <tr>
              <td style="font-size:22px"><?php echo $m['icono']; ?></td>
              <td><strong><?php echo esc_html( $m['nombre'] ); ?></strong></td>
              <td style="font-size:12px;color:#555"><?php echo esc_html( $m['descripcion'] ); ?></td>
              <td>
                $<input type="number" name="psp_precio_<?php echo esc_attr( $m['tipo'] ); ?>"
                        value="<?php echo esc_attr( get_option( 'psp_precio_' . $m['tipo'], $m['precio_default'] ) ); ?>"
                        min="0" step="0.01" style="width:80px">
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <p><button class="button button-primary" name="psp_save_precios">&#x1F4BE; Guardar precios</button></p>
      </form>

      <hr>

      <!-- Listado de miembros -->
      <h2>&#x1F465; Listado de Miembros</h2>
      <div style="margin-bottom:12px;display:flex;gap:8px;flex-wrap:wrap">
        <button onclick="loadMiembros('activo')"          class="button button-primary">Activos</button>
        <button onclick="loadMiembros('pendiente_pago')"  class="button">Pendientes de pago</button>
        <button onclick="loadMiembros('inactivo')"        class="button">Inactivos</button>
        <button onclick="loadMiembros('')"                class="button">Todos</button>
      </div>
      <div id="psp-miembros-tabla">Haz clic en un filtro para cargar miembros.</div>
    </div>

    <script>
    jQuery(function($) {
      // Cargar KPIs
      $.post(ajaxurl, {action:'psp_mem_kpis', psp_nonce:'<?= wp_create_nonce("psp_nonce") ?>'}, function(d) {
        if (!d.success) return;
        $('#k-total').text(d.data.total  || 0);
        $('#k-nac').text(d.data.nacional || 0);
        $('#k-int').text(d.data.internacional || 0);
        $('#k-act').text(d.data.actor    || 0);
        $('#k-hog').text(d.data.hogar_solidario || 0);
        $('#k-pro').text(d.data.productor || 0);
      });
    });

    function loadMiembros(estado) {
      var tabla = document.getElementById('psp-miembros-tabla');
      tabla.innerHTML = '&#x23F3; Cargando...';
      jQuery.post(ajaxurl, {action:'psp_mem_lista', estado:estado, psp_nonce:'<?= wp_create_nonce("psp_nonce") ?>'}, function(d) {
        if (!d.success || !d.data || !d.data.length) { tabla.innerHTML='<p>Sin resultados.</p>'; return; }
        var h = '<table class="wp-list-table widefat"><thead><tr><th>Nombre</th><th>Tipo</th><th>Estado</th><th>Celular</th><th>Provincia</th><th>Fecha</th></tr></thead><tbody>';
        d.data.forEach(function(m) {
          h += '<tr>'
            + '<td><strong>' + (m.nombre||'—') + '</strong></td>'
            + '<td>' + (m.tipo_miembro||'—') + '</td>'
            + '<td>' + (m.estado||'—') + '</td>'
            + '<td>' + (m.celular||'—') + '</td>'
            + '<td>' + (m.provincia_nombre||'—') + '</td>'
            + '<td>' + (m.created_at ? new Date(m.created_at).toLocaleDateString('es-PA') : '—') + '</td>'
            + '</tr>';
        });
        h += '</tbody></table>';
        tabla.innerHTML = h;
      });
    }
    </script>
    <?php
}

// AJAX: KPIs de membresías
add_action( 'wp_ajax_psp_mem_kpis', function() {
    if ( ! current_user_can('manage_options') ) wp_send_json_error();
    if ( ! class_exists('PSP_Supabase') ) wp_send_json_error(['message'=>'PSP Core no activo']);
    $rows = PSP_Supabase::select( 'miembros', ['select'=>'tipo_miembro,estado','limit'=>99999] );
    $counts = [];
    $total  = 0;
    foreach ( $rows ?? [] as $r ) {
        if ( $r['estado'] === 'activo' ) {
            $t = $r['tipo_miembro'] ?? 'otro';
            $counts[$t] = ( $counts[$t] ?? 0 ) + 1;
            $total++;
        }
    }
    wp_send_json_success( array_merge( ['total'=>$total], $counts ) );
});

// AJAX: listado de miembros
add_action( 'wp_ajax_psp_mem_lista', function() {
    if ( ! current_user_can('manage_options') ) wp_send_json_error();
    if ( ! class_exists('PSP_Supabase') ) wp_send_json_error(['message'=>'PSP Core no activo']);
    $estado = sanitize_text_field( $_POST['estado'] ?? '' );
    $params = ['select'=>'nombre,tipo_miembro,estado,celular,created_at,territorios(nombre)','order'=>'created_at.desc','limit'=>200];
    if ($estado) $params['estado'] = 'eq.' . $estado;
    $rows = PSP_Supabase::select( 'miembros', $params );
    $result = [];
    foreach ( $rows ?? [] as $r ) {
        $r['provincia_nombre'] = $r['territorios']['nombre'] ?? '—';
        unset($r['territorios']);
        $result[] = $r;
    }
    wp_send_json_success( $result );
});
