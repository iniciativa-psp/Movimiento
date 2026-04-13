<?php
/**
 * Plugin Name: PSP WhatsApp
 * Description: Gestión de grupos WhatsApp por territorio, sector y embajador.
 * Version:     1.0.0
 */
if (!defined('ABSPATH')) exit;

add_action( 'admin_notices', 'psp_whatsapp_check_core' );
function psp_whatsapp_check_core() {
    if ( ! class_exists( 'PSP_Supabase' ) )
        echo '<div class="notice notice-error"><p><strong>psp-whatsapp:</strong> Requiere <strong>PSP Core</strong> activo.</p></div>';
}

add_action( 'plugins_loaded', 'psp_whatsapp_late_init' );
function psp_whatsapp_late_init() {
    if ( ! class_exists( 'PSP_Supabase' ) ) return;
}



add_shortcode('psp_whatsapp_grupos',   'psp_whatsapp_shortcode');
add_shortcode('psp_mi_grupo_wa',       'psp_mi_grupo_wa_shortcode');
add_action('admin_menu', 'psp_whatsapp_admin_menu');

function psp_whatsapp_admin_menu() {
    add_submenu_page('psp-core','WhatsApp Grupos','💬 WhatsApp','manage_options','psp-whatsapp','psp_whatsapp_admin_page');
}

function psp_whatsapp_admin_page() { ?>
<div class="wrap">
<h1>💬 Grupos de WhatsApp</h1>
<button id="psp-add-grupo" class="button button-primary">+ Añadir grupo</button>
<div id="psp-wa-tabla" style="margin-top:20px">Cargando...</div>
<div id="psp-wa-form" style="display:none;background:#f9f9f9;padding:20px;border:1px solid #ddd;border-radius:8px;margin-top:16px">
  <h3>Nuevo Grupo</h3>
  <label>Nombre: <input type="text" id="wa-nombre" class="regular-text"></label><br><br>
  <label>Link WhatsApp: <input type="url" id="wa-link" class="regular-text" placeholder="https://chat.whatsapp.com/..."></label><br><br>
  <label>Tipo:
    <select id="wa-tipo"><option value="territorial">Territorial</option><option value="sector">Sector</option><option value="embajador">Embajador</option><option value="general">General</option></select>
  </label><br><br>
  <button onclick="PSPWAAdmin.guardar()" class="button button-primary">Guardar</button>
  <button onclick="document.getElementById('psp-wa-form').style.display='none'" class="button">Cancelar</button>
</div>
</div>
<script>
jQuery(function($) {
  function loadGrupos() {
    $.post(ajaxurl,{action:'psp_get_wa_grupos',psp_nonce:'<?= wp_create_nonce("psp_nonce") ?>'},function(d){
      if (!d.success) return;
      let h = '<table class="wp-list-table widefat"><thead><tr><th>Nombre</th><th>Tipo</th><th>Miembros</th><th>Link</th><th>Acción</th></tr></thead><tbody>';
      (d.data||[]).forEach(g => {
        h += `<tr><td>${g.nombre}</td><td>${g.tipo}</td><td>${g.miembros_actual||0}/${g.miembros_max||256}</td>
          <td><a href="${g.link}" target="_blank">Abrir</a></td>
          <td><button onclick="PSPWAAdmin.eliminar('${g.id}')" class="button">Eliminar</button></td></tr>`;
      });
      h += '</tbody></table>';
      $('#psp-wa-tabla').html(h);
    });
  }
  loadGrupos();
  $('#psp-add-grupo').click(() => $('#psp-wa-form').toggle());
  window.PSPWAAdmin = {
    guardar() {
      $.post(ajaxurl,{action:'psp_crear_wa_grupo',nombre:$('#wa-nombre').val(),link:$('#wa-link').val(),tipo:$('#wa-tipo').val(),psp_nonce:'<?= wp_create_nonce("psp_nonce") ?>'},function(d){
        if(d.success){$('#psp-wa-form').hide();loadGrupos();}
        else alert('Error: '+d.data?.message);
      });
    },
    eliminar(id) {
      if(!confirm('¿Eliminar?')) return;
      $.post(ajaxurl,{action:'psp_del_wa_grupo',id,psp_nonce:'<?= wp_create_nonce("psp_nonce") ?>'},function(){loadGrupos();});
    }
  };
});
</script>
<?php }

function psp_whatsapp_shortcode(): string {
    ob_start(); ?>
    <div id="psp-wa-grupos" class="psp-card">
      <h3>💬 Grupos de WhatsApp del Movimiento</h3>
      <div class="psp-wa-filtros">
        <button onclick="PSPWAFront.cargar('todos')" class="psp-rtab active">Todos</button>
        <button onclick="PSPWAFront.cargar('territorial')" class="psp-rtab">Por Provincia</button>
        <button onclick="PSPWAFront.cargar('sector')" class="psp-rtab">Por Sector</button>
        <button onclick="PSPWAFront.cargar('embajador')" class="psp-rtab">Embajadores</button>
      </div>
      <div id="psp-wa-lista">Cargando grupos...</div>
    </div>
    <script>
    const PSPWAFront = {
      async cargar(tipo) {
        document.querySelectorAll('.psp-wa-filtros .psp-rtab').forEach(b=>b.classList.remove('active'));
        event?.target?.classList.add('active');
        const r = await fetch(PSP_CONFIG.ajax_url,{method:'POST',body:new URLSearchParams({action:'psp_get_wa_grupos_front',tipo,psp_nonce:PSP_CONFIG.nonce})});
        const d = await r.json();
        const el = document.getElementById('psp-wa-lista');
        if(!d.success||!d.data?.length){el.innerHTML='<p style="color:#888;padding:16px">No hay grupos disponibles aún.</p>';return;}
        el.innerHTML = d.data.map(g=>`
          <div style="display:flex;align-items:center;gap:14px;padding:14px 0;border-bottom:1px solid var(--psp-border)">
            <span style="font-size:24px">💬</span>
            <div style="flex:1"><div style="font-weight:700;font-size:14px">${g.nombre}</div><div style="font-size:12px;color:#888">${g.tipo} · ${g.miembros_actual||0} miembros</div></div>
            <a href="${g.link}" target="_blank" style="background:#25D366;color:#fff;padding:8px 16px;border-radius:8px;text-decoration:none;font-weight:700;font-size:13px">Unirme</a>
          </div>`).join('');
      }
    };
    document.addEventListener('DOMContentLoaded',()=>PSPWAFront.cargar('todos'));
    </script>
    <?php return ob_get_clean();
}

// AJAX handlers
add_action('wp_ajax_psp_get_wa_grupos',       'psp_ajax_get_wa_admin');
function psp_ajax_get_wa_admin() {
    if (!current_user_can('manage_options')) wp_send_json_error();
    wp_send_json_success(PSP_Supabase::select('whatsapp_grupos', ['order' => 'created_at.desc', 'limit' => 200]) ?? []);
}

add_action('wp_ajax_psp_crear_wa_grupo', 'psp_ajax_crear_wa');
function psp_ajax_crear_wa() {
    if (!current_user_can('manage_options')) wp_send_json_error();
    $data = ['nombre' => sanitize_text_field($_POST['nombre']), 'link' => esc_url_raw($_POST['link']), 'tipo' => sanitize_text_field($_POST['tipo']),'tenant_id'=>get_option('psp_tenant_id','panama')];
    $r = PSP_Supabase::insert('whatsapp_grupos', $data, true);
    $r ? wp_send_json_success() : wp_send_json_error(['message'=>'Error guardando']);
}

add_action('wp_ajax_psp_del_wa_grupo', 'psp_ajax_del_wa');
function psp_ajax_del_wa() {
    if (!current_user_can('manage_options')) wp_send_json_error();
    $id = sanitize_text_field($_POST['id'] ?? '');
    PSP_Supabase::request('whatsapp_grupos?id=eq.'.$id, 'DELETE', [], [], true);
    wp_send_json_success();
}

add_action('wp_ajax_psp_get_wa_grupos_front',        'psp_ajax_wa_front');
add_action('wp_ajax_nopriv_psp_get_wa_grupos_front', 'psp_ajax_wa_front');
function psp_ajax_wa_front() {
    $tipo = sanitize_text_field($_POST['tipo'] ?? 'todos');
    $params = ['activo' => 'eq.true', 'order' => 'nombre.asc', 'limit' => 100];
    if ($tipo !== 'todos') $params['tipo'] = 'eq.' . $tipo;
    wp_send_json_success(PSP_Supabase::select('whatsapp_grupos', $params) ?? []);
}

/**
 * Shortcode: muestra el grupo de WhatsApp territorial asignado al miembro autenticado
 * y grupos generales/sectoriales.
 * Uso: [psp_mi_grupo_wa]
 */
function psp_mi_grupo_wa_shortcode(): string {
    ob_start();
    ?>
    <div id="psp-mi-wa" class="psp-card">
      <h3 style="margin-top:0;color:#0B5E43">&#x1F4AC; Tu Grupo de WhatsApp</h3>
      <div id="psp-mi-wa-loading" style="color:#6B7280;font-size:13px">Cargando grupos...</div>
      <div id="psp-mi-wa-content" style="display:none"></div>
    </div>
    <script>
    (function() {
      var jwt = (typeof PSP_CONFIG !== 'undefined' && PSP_CONFIG.jwt) ? PSP_CONFIG.jwt : (document.cookie.match(/psp_jwt=([^;]+)/) || [])[1];

      function renderGrupos(grupos) {
        if (!grupos || !grupos.length) {
          return '<p style="color:#888;font-size:13px">&#x26A0;&#xFE0F; A&uacute;n no hay grupos disponibles para tu zona. Vuelve pronto.</p>';
        }
        return grupos.map(function(g) {
          var badge = g.categoria === 'territorial'
            ? '<span style="background:#0B5E43;color:#fff;padding:2px 8px;border-radius:20px;font-size:11px">Tu zona</span>'
            : '<span style="background:#6B7280;color:#fff;padding:2px 8px;border-radius:20px;font-size:11px">' + (g.tipo || 'general') + '</span>';
          return '<div style="display:flex;align-items:center;gap:14px;padding:14px 0;border-bottom:1px solid #E5E7EB">'
            + '<span style="font-size:28px">&#x1F4AC;</span>'
            + '<div style="flex:1"><div style="font-weight:700;font-size:14px">' + g.nombre + ' ' + badge + '</div>'
            + '<div style="font-size:12px;color:#888;margin-top:2px">' + (g.miembros_actual || 0) + '/' + (g.miembros_max || 256) + ' miembros</div></div>'
            + '<a href="' + g.link + '" target="_blank" rel="noopener" '
            + 'style="background:#25D366;color:#fff;padding:10px 18px;border-radius:8px;text-decoration:none;font-weight:700;font-size:13px;white-space:nowrap">'
            + '&#x1F4F2; Unirme</a></div>';
        }).join('');
      }

      async function loadGrupos() {
        var content = document.getElementById('psp-mi-wa-content');
        var loading = document.getElementById('psp-mi-wa-loading');

        if (!jwt) {
          loading.innerHTML = '<p style="color:#888;font-size:13px">Inicia sesi&oacute;n para ver tu grupo de WhatsApp territorial.</p>';
          return;
        }

        try {
          var restUrl = (typeof PSP_CONFIG !== 'undefined' && PSP_CONFIG.rest_url)
            ? PSP_CONFIG.rest_url + 'wa-group'
            : '/wp-json/psp/v1/wa-group';

          var r = await fetch(restUrl, {
            headers: {
              'Authorization': 'Bearer ' + jwt,
              'X-WP-Nonce': (typeof PSP_CONFIG !== 'undefined' && PSP_CONFIG.rest_nonce) ? PSP_CONFIG.rest_nonce : '',
            }
          });
          var d = await r.json();

          loading.style.display = 'none';
          content.style.display = 'block';

          if (!d.success || !d.grupos || !d.grupos.length) {
            content.innerHTML = '<p style="color:#888;font-size:13px">&#x26A0;&#xFE0F; No hay grupos asignados a tu zona todav&iacute;a. Vuelve pronto o consulta los grupos generales.</p>';
          } else {
            content.innerHTML = renderGrupos(d.grupos);
          }
        } catch (e) {
          loading.innerHTML = '<p style="color:#c00;font-size:13px">Error cargando grupos. Intenta recargar la p&aacute;gina.</p>';
        }
      }

      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', loadGrupos);
      } else {
        loadGrupos();
      }
    })();
    </script>
    <?php
    return ob_get_clean();
}

// AJAX: asociar grupo a un territorio desde el admin
add_action('wp_ajax_psp_asignar_wa_territorio', 'psp_ajax_asignar_wa_territorio');
function psp_ajax_asignar_wa_territorio() {
    if (!current_user_can('manage_options') || !check_admin_referer('psp_nonce', 'psp_nonce')) {
        wp_send_json_error(['message' => 'No autorizado']);
    }
    $grupo_id       = sanitize_text_field($_POST['grupo_id']      ?? '');
    $territorio_id  = sanitize_text_field($_POST['territorio_id'] ?? '');
    if (!$grupo_id || !$territorio_id) {
        wp_send_json_error(['message' => 'Datos incompletos']);
    }
    $r = PSP_Supabase::update('whatsapp_grupos', ['territorio_id' => $territorio_id], ['id' => 'eq.' . $grupo_id]);
    $r ? wp_send_json_success() : wp_send_json_error(['message' => 'Error actualizando']);
}
