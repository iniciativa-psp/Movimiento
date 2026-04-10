<?php
/**
 * Plugin Name: PSP ERP
 * Description: Sistema ERP completo: clientes, transacciones, libro diario e informes financieros.
 * Version:     1.0.0
 */
if (!defined('ABSPATH')) exit;

add_action('admin_menu', 'psp_erp_menu');
function psp_erp_menu(): void {
    add_submenu_page('psp-core', 'ERP Financiero', '📊 ERP', 'manage_options', 'psp-erp', 'psp_erp_page');
    add_submenu_page('psp-core', 'Facturas',        '🧾 Facturas', 'manage_options', 'psp-facturas', 'psp_facturas_page');
    add_submenu_page('psp-core', 'Pagos', '💰 Pagos', 'manage_options', 'psp-pagos-admin', 'psp_pagos_admin_page');
}

function psp_erp_page(): void { ?>
<div class="wrap">
<h1>📊 ERP Panamá Sin Pobreza</h1>
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:20px;margin:20px 0">
  <div id="erp-kpi-ingresos" class="psp-erp-kpi">Cargando...</div>
  <div id="erp-kpi-egresos"  class="psp-erp-kpi">Cargando...</div>
  <div id="erp-kpi-balance"  class="psp-erp-kpi">Cargando...</div>
</div>
<h2>Libro Diario</h2>
<div id="erp-libro-tabla">Cargando...</div>
<h2>Transacciones recientes</h2>
<div id="erp-trans-tabla">Cargando...</div>
<script>
jQuery(function($) {
  $.post(ajaxurl, {action:'psp_erp_resumen', psp_nonce: '<?= wp_create_nonce("psp_nonce") ?>'}, function(d) {
    if (!d.success) return;
    const r = d.data;
    $('#erp-kpi-ingresos').html('<h3>Ingresos</h3><div class="erp-val">$'+Number(r.ingresos||0).toLocaleString()+'</div>');
    $('#erp-kpi-egresos').html('<h3>Egresos</h3><div class="erp-val">$'+Number(r.egresos||0).toLocaleString()+'</div>');
    $('#erp-kpi-balance').html('<h3>Balance</h3><div class="erp-val">$'+Number((r.ingresos||0)-(r.egresos||0)).toLocaleString()+'</div>');
    let html = '<table class="wp-list-table widefat"><thead><tr><th>Fecha</th><th>Descripción</th><th>Debe</th><th>Haber</th></tr></thead><tbody>';
    (r.libro||[]).forEach(t => {
      html += `<tr><td>${t.fecha}</td><td>${t.descripcion}</td><td>$${Number(t.debe).toFixed(2)}</td><td>$${Number(t.haber).toFixed(2)}</td></tr>`;
    });
    html += '</tbody></table>';
    $('#erp-libro-tabla').html(html);

    let html2 = '<table class="wp-list-table widefat"><thead><tr><th>Fecha</th><th>Tipo</th><th>Descripción</th><th>Monto</th></tr></thead><tbody>';
    (r.transacciones||[]).forEach(t => {
      html2 += `<tr><td>${t.fecha}</td><td>${t.tipo}</td><td>${t.descripcion}</td><td>$${Number(t.monto).toFixed(2)}</td></tr>`;
    });
    html2 += '</tbody></table>';
    $('#erp-trans-tabla').html(html2);
  });
});
</script>
</div>
<?php }

function psp_facturas_page(): void { ?>
<div class="wrap">
<h1>🧾 Facturas Electrónicas DGI</h1>
<div id="psp-facturas-admin">Cargando...</div>
<script>
jQuery(function($) {
  $.post(ajaxurl, {action:'psp_get_facturas_admin', psp_nonce:'<?= wp_create_nonce("psp_nonce") ?>'}, function(d) {
    if (!d.success) return;
    let html = '<table class="wp-list-table widefat"><thead><tr><th>Número</th><th>Cliente</th><th>Total</th><th>Estado</th><th>Fecha</th><th>Acciones</th></tr></thead><tbody>';
    (d.data||[]).forEach(f => {
      html += `<tr>
        <td>${f.numero_factura}</td>
        <td>${f.nombre||'—'}</td>
        <td>$${Number(f.total).toFixed(2)}</td>
        <td><span class="psp-badge">${f.estado}</span></td>
        <td>${new Date(f.fecha_emision).toLocaleDateString('es-PA')}</td>
        <td><a href="#" onclick="PSPAdmin.verXML('${f.id}')">Ver XML</a></td>
      </tr>`;
    });
    html += '</tbody></table>';
    $('#psp-facturas-admin').html(html);
  });
});
</script>
</div>
<?php }

function psp_pagos_admin_page(): void { ?>
<div class="wrap">
<h1>💰 Gestión de Pagos</h1>
<div style="margin-bottom:16px">
  <strong>Pendientes de validación:</strong>
  <span id="psp-pagos-pendientes-count">—</span>
</div>
<div id="psp-pagos-admin-tabla">Cargando...</div>
<script>
jQuery(function($) {
  function loadPagos(estado = 'pendiente_validacion') {
    $.post(ajaxurl, {action:'psp_get_pagos_admin', estado, psp_nonce:'<?= wp_create_nonce("psp_nonce") ?>'}, function(d) {
      if (!d.success) return;
      $('#psp-pagos-pendientes-count').text(d.data.length);
      let html = '<table class="wp-list-table widefat"><thead><tr><th>Referencia</th><th>Miembro</th><th>Monto</th><th>Método</th><th>Estado</th><th>Comprobante</th><th>Acción</th></tr></thead><tbody>';
      (d.data||[]).forEach(p => {
        html += `<tr>
          <td>${p.referencia}</td>
          <td>${p.nombre||'—'}</td>
          <td>$${Number(p.monto).toFixed(2)}</td>
          <td>${p.metodo}</td>
          <td>${p.estado}</td>
          <td>${p.comprobante_url ? '<a href="'+p.comprobante_url+'" target="_blank">Ver</a>' : '—'}</td>
          <td>
            <button onclick="PSPAdmin.validarPago('${p.id}')" class="button button-primary">✅ Validar</button>
            <button onclick="PSPAdmin.rechazarPago('${p.id}')" class="button">❌ Rechazar</button>
          </td>
        </tr>`;
      });
      html += '</tbody></table>';
      $('#psp-pagos-admin-tabla').html(html);
    });
  }
  loadPagos();

  window.PSPAdmin = {
    validarPago(id) {
      if (!confirm('¿Validar este pago?')) return;
      $.post(ajaxurl, {action:'psp_admin_validar_pago', pago_id: id, psp_nonce:'<?= wp_create_nonce("psp_nonce") ?>'}, function(d) {
        if (d.success) { alert('✅ Pago validado'); loadPagos(); }
        else alert('Error: ' + d.data.message);
      });
    },
    rechazarPago(id) {
      if (!confirm('¿Rechazar este pago?')) return;
      $.post(ajaxurl, {action:'psp_admin_rechazar_pago', pago_id: id, psp_nonce:'<?= wp_create_nonce("psp_nonce") ?>'}, function(d) {
        if (d.success) { alert('Pago rechazado'); loadPagos(); }
      });
    }
  };
});
</script>
</div>
<?php }

// AJAX handlers
add_action('wp_ajax_psp_erp_resumen', function() {
    if (!current_user_can('manage_options')) wp_send_json_error();
    $ingresos = PSP_Supabase::select('erp_transacciones', ['tipo' => 'eq.ingreso', 'select' => 'monto.sum()']);
    $egresos  = PSP_Supabase::select('erp_transacciones', ['tipo' => 'eq.egreso',  'select' => 'monto.sum()']);
    $libro    = PSP_Supabase::select('erp_libro_diario',  ['order' => 'fecha.desc', 'limit' => 50]);
    $trans    = PSP_Supabase::select('erp_transacciones', ['order' => 'created_at.desc', 'limit' => 50]);
    wp_send_json_success([
        'ingresos'     => $ingresos[0]['sum'] ?? 0,
        'egresos'      => $egresos[0]['sum']  ?? 0,
        'libro'        => $libro ?? [],
        'transacciones'=> $trans ?? [],
    ]);
});

add_action('wp_ajax_psp_get_facturas_admin', function() {
    if (!current_user_can('manage_options')) wp_send_json_error();
    $facturas = PSP_Supabase::select('facturas', ['select' => '*,erp_clientes(nombre)', 'order' => 'created_at.desc', 'limit' => 100]);
    wp_send_json_success($facturas ?? []);
});

add_action('wp_ajax_psp_get_pagos_admin', function() {
    if (!current_user_can('manage_options')) wp_send_json_error();
    $estado = sanitize_text_field($_POST['estado'] ?? 'pendiente_validacion');
    $pagos  = PSP_Supabase::select('pagos', ['select' => '*,miembros(nombre)', 'estado' => 'eq.' . $estado, 'order' => 'created_at.desc', 'limit' => 100]);
    wp_send_json_success($pagos ?? []);
});

add_action('wp_ajax_psp_admin_validar_pago', function() {
    if (!current_user_can('manage_options')) wp_send_json_error();
    $pago_id = sanitize_text_field($_POST['pago_id'] ?? '');
    PSP_Supabase::update('pagos', ['estado' => 'completado', 'validado_por' => get_current_user_id()], ['id' => 'eq.' . $pago_id]);
    wp_send_json_success(['ok' => true]);
});

add_action('wp_ajax_psp_admin_rechazar_pago', function() {
    if (!current_user_can('manage_options')) wp_send_json_error();
    $pago_id = sanitize_text_field($_POST['pago_id'] ?? '');
    PSP_Supabase::update('pagos', ['estado' => 'fallido'], ['id' => 'eq.' . $pago_id]);
    wp_send_json_success(['ok' => true]);
});
