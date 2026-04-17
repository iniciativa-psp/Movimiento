<?php
/**
 * Plugin Name: PSP Payments
 * Plugin URI:  https://panamasinpobreza.org
 * Description: Sistema completo de pagos: Yappy, ACH, Tarjetas (Banco General), Sistema Clave (PagueloFacil), PuntoPago, Transferencias, Efectivo. Precios sincronizados con psp-membresias y psp-productos.
 * Version:     1.0.2
 * Author:      PSP Dev Team
 * Requires PHP: 7.4
 * Text Domain: psp-payments
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_notices', 'psp_payments_check_core' );
function psp_payments_check_core() {
    if ( ! class_exists('PSP_Supabase') ) {
        echo '<div class="notice notice-error"><p><strong>PSP Payments:</strong> Requiere <strong>PSP Core</strong> activo.</p></div>';
    }
}

add_action( 'plugins_loaded', 'psp_payments_load' );
function psp_payments_load() {
    require_once plugin_dir_path(__FILE__) . 'includes/payment-core.php';
    require_once plugin_dir_path(__FILE__) . 'includes/yappy.php';
    require_once plugin_dir_path(__FILE__) . 'includes/paguelofacil.php';
    require_once plugin_dir_path(__FILE__) . 'includes/tarjetas-bg.php';
    require_once plugin_dir_path(__FILE__) . 'includes/transferencia.php';
    require_once plugin_dir_path(__FILE__) . 'webhooks/webhook-handler.php';
}

add_shortcode( 'psp_pagos', 'psp_pagos_shortcode' );

function psp_pagos_shortcode( $atts = [] ) {
    $atts = shortcode_atts([
        'tipo'     => '',   // si viene predefinido (ej: desde URL ?tipo=nacional)
        'cantidad' => '1',
        'mostrar'  => 'todos', // todos | membresias | productos
    ], $atts );

    // Tipo desde URL
    $tipo_url = sanitize_text_field( $_GET['tipo'] ?? $atts['tipo'] );

    // Construir opciones dinámicamente desde psp-membresias y psp-productos
    $opciones_mem   = [];
    $opciones_prod  = [];

    if ( function_exists('psp_get_membresias_config') ) {
        foreach ( psp_get_membresias_config() as $m ) {
            $precio = (float) get_option( 'psp_precio_' . $m['tipo'], $m['precio_default'] );
            $opciones_mem[] = [
                'valor'   => $m['tipo'],
                'label'   => $m['icono'] . ' ' . $m['nombre'] . ( $precio > 0 ? ' — $' . number_format($precio,2) : ' — Gratis' ),
                'precio'  => $precio,
                'calc'    => ! empty( $m['calculadora'] ),
                'icono'   => $m['icono'],
                'tipo'    => 'membresia',
            ];
        }
    } else {
        // Fallback si psp-membresias no está activo
        $opciones_mem = [
            ['valor'=>'nacional',      'label'=>'&#x1F1F5;&#x1F1E6; Miembro Nacional — $5',    'precio'=>5,  'calc'=>false,'icono'=>'&#x1F1F5;&#x1F1E6;','tipo'=>'membresia'],
            ['valor'=>'internacional', 'label'=>'&#x1F30E; Internacional — $10',               'precio'=>10, 'calc'=>false,'icono'=>'&#x1F30E;',          'tipo'=>'membresia'],
            ['valor'=>'actor',         'label'=>'&#x1F3AD; Actor / Coalición — $25',           'precio'=>25, 'calc'=>false,'icono'=>'&#x1F3AD;',          'tipo'=>'membresia'],
            ['valor'=>'sector',        'label'=>'&#x1F3E2; Sector / Empresa — $50',            'precio'=>50, 'calc'=>false,'icono'=>'&#x1F3E2;',          'tipo'=>'membresia'],
            ['valor'=>'hogar_solidario','label'=>'&#x1F3E0; Hogar Solidario — desde $15',      'precio'=>15, 'calc'=>true, 'icono'=>'&#x1F3E0;',          'tipo'=>'membresia'],
            ['valor'=>'productor',     'label'=>'&#x1F33E; Productor Beneficiario — $20',      'precio'=>20, 'calc'=>true, 'icono'=>'&#x1F33E;',          'tipo'=>'membresia'],
        ];
    }

    if ( function_exists('psp_get_productos_config') ) {
        foreach ( psp_get_productos_config() as $p ) {
            $precio = (float) get_option( $p['opcion_precio'], $p['precio_default'] );
            $opciones_prod[] = [
                'valor'  => $p['slug'],
                'label'  => $p['icono'] . ' ' . $p['nombre'] . ( $precio > 0 ? ' — $' . number_format($precio,2) : '' ),
                'precio' => $precio,
                'calc'   => ($p['slug'] === 'plantones'),
                'icono'  => $p['icono'],
                'tipo'   => 'producto',
            ];
        }
    } else {
        $opciones_prod = [
            ['valor'=>'planton','label'=>'&#x1F331; Plant&oacute;n Reforestaci&oacute;n — $2/u','precio'=>2,'calc'=>true,'icono'=>'&#x1F331;','tipo'=>'producto'],
        ];
    }

    $todas_opciones = array_merge( $opciones_mem, $opciones_prod );
    if ( $atts['mostrar'] === 'membresias' ) $todas_opciones = $opciones_mem;
    if ( $atts['mostrar'] === 'productos'  ) $todas_opciones = $opciones_prod;

    ob_start();
    ?>
    <div id="psp-pagos-wrap" class="psp-card">
      <h2>&#x1F4B3; Realiza tu Aporte</h2>

      <div class="psp-pagos-selector">
        <label style="font-size:14px;font-weight:600;color:#374151;display:block;margin-bottom:6px">
          Tipo de membres&iacute;a o producto:
        </label>
        <select id="psp-tipo-sel" class="psp-input"
                onchange="PSPPagos.onChangeTipo(this)">
          <?php if ( $opciones_mem && $opciones_prod ) : ?>
            <optgroup label="&#x1F3F7;&#xFE0F; Membres&iacute;as">
              <?php foreach ($opciones_mem as $o) : ?>
                <option value="<?php echo esc_attr($o['valor']); ?>"
                        data-precio="<?php echo esc_attr($o['precio']); ?>"
                        data-calc="<?php echo $o['calc'] ? '1' : '0'; ?>"
                        <?php selected($tipo_url,$o['valor']); ?>>
                  <?php echo $o['label']; ?>
                </option>
              <?php endforeach; ?>
            </optgroup>
            <optgroup label="&#x1F6CD;&#xFE0F; Productos">
              <?php foreach ($opciones_prod as $o) : ?>
                <option value="<?php echo esc_attr($o['valor']); ?>"
                        data-precio="<?php echo esc_attr($o['precio']); ?>"
                        data-calc="<?php echo $o['calc'] ? '1' : '0'; ?>"
                        <?php selected($tipo_url,$o['valor']); ?>>
                  <?php echo $o['label']; ?>
                </option>
              <?php endforeach; ?>
            </optgroup>
          <?php else : ?>
            <?php foreach ($todas_opciones as $o) : ?>
              <option value="<?php echo esc_attr($o['valor']); ?>"
                      data-precio="<?php echo esc_attr($o['precio']); ?>"
                      data-calc="<?php echo $o['calc'] ? '1' : '0'; ?>"
                      <?php selected($tipo_url,$o['valor']); ?>>
                <?php echo $o['label']; ?>
              </option>
            <?php endforeach; ?>
          <?php endif; ?>
        </select>
      </div>

      <!-- Calculadora para tipos con cantidad -->
      <div id="psp-pagos-calc" style="display:none;background:#F0FDF4;border-radius:8px;padding:14px;margin-bottom:14px">
        <label style="font-size:13px;font-weight:600;color:#166534">Cantidad:</label>
        <div style="display:flex;align-items:center;gap:8px;margin:8px 0">
          <button type="button" class="psp-btn psp-btn-sm" onclick="PSPPagos.changeQty(-1)">&#x2212;</button>
          <input type="number" id="psp-pagos-qty" value="1" min="1" style="width:80px;text-align:center;padding:8px;border:1.5px solid #BBF7D0;border-radius:8px;font-size:15px;font-weight:700;color:#166534"
                 oninput="PSPPagos.recalcular()">
          <button type="button" class="psp-btn psp-btn-sm" onclick="PSPPagos.changeQty(1)">&#x2B;</button>
        </div>
        <div id="psp-pagos-calc-res" style="font-size:13px;color:#166534"></div>
      </div>

      <!-- Monto final -->
      <div style="font-size:20px;font-weight:800;color:#111;margin-bottom:20px">
        Total: <span id="psp-monto-final" style="color:#0B5E43">$5.00</span>
      </div>

      <!-- Métodos de pago -->
      <h3 style="font-size:15px;font-weight:700;margin-bottom:12px">Selecciona c&oacute;mo pagar:</h3>
      <div class="psp-metodos-grid">
        <button onclick="PSPPagos.iniciar('yappy')"        class="psp-metodo-btn">&#x1F7E1; Yappy</button>
        <button onclick="PSPPagos.iniciar('clave')"        class="psp-metodo-btn">&#x1F511; Sistema Clave</button>
        <button onclick="PSPPagos.iniciar('tarjeta')"      class="psp-metodo-btn">&#x1F4B3; Tarjeta</button>
        <button onclick="PSPPagos.iniciar('ach')"          class="psp-metodo-btn">&#x1F3E6; ACH Panam&aacute;</button>
        <button onclick="PSPPagos.iniciar('puntopago')"    class="psp-metodo-btn">&#x1F3EA; PuntoPago</button>
        <button onclick="PSPPagos.iniciar('transferencia')"class="psp-metodo-btn">&#x1F504; Transferencia</button>
        <button onclick="PSPPagos.iniciar('efectivo')"     class="psp-metodo-btn">&#x1F4B5; Efectivo</button>
      </div>

      <div id="psp-pago-detalle"></div>
      <div id="psp-pago-status"  style="margin-top:12px"></div>
    </div>

    <script>
    var PSPPagos = {
      monto   : <?php echo (float)(psp_get_precio($tipo_url ?: 'nacional')); ?>,
      tipo    : '<?php echo esc_js($tipo_url ?: 'nacional'); ?>',
      cantidad: 1,

      onChangeTipo: function(sel) {
        var opt      = sel.options[sel.selectedIndex];
        this.tipo    = sel.value;
        this.monto   = parseFloat(opt.dataset.precio) || 0;
        this.cantidad = 1;
        document.getElementById('psp-pagos-qty').value = 1;

        var calcWrap = document.getElementById('psp-pagos-calc');
        calcWrap.style.display = opt.dataset.calc === '1' ? 'block' : 'none';
        this.recalcular();
      },

      changeQty: function(delta) {
        var inp = document.getElementById('psp-pagos-qty');
        inp.value = Math.max(1, parseInt(inp.value||1) + delta);
        this.recalcular();
      },

      recalcular: function() {
        this.cantidad = parseInt(document.getElementById('psp-pagos-qty').value) || 1;
        var total = this.monto * this.cantidad;
        document.getElementById('psp-monto-final').textContent = '$' + total.toFixed(2);

        var res   = document.getElementById('psp-pagos-calc-res');
        var tipo  = this.tipo;
        var emp   = 0, per = 0;
        if (tipo==='hogar_solidario'){ emp=Math.floor(this.cantidad*.15); per=Math.floor(this.cantidad*3.8); }
        else if (tipo==='productor') { emp=Math.floor(this.cantidad*1);   per=Math.floor(this.cantidad*4.2); }
        else if (tipo==='planton')   { emp=(this.cantidad*.02).toFixed(1); per=(this.cantidad*.1).toFixed(1); }

        if (res) {
          res.innerHTML = '<strong>Total: $'+total.toFixed(2)+'</strong>'
            + (emp ? ' &nbsp;|&nbsp; &#x1F454; Empleos: ~'+emp : '')
            + (per ? ' &nbsp;|&nbsp; &#x1F46B; Personas: ~'+per : '');
        }
      },

      async iniciar(metodo) {
        var jwt      = (document.cookie.match(/psp_jwt=([^;]+)/)||[])[1];
        var membId   = (document.cookie.match(/psp_miembro_id=([^;]+)/)||[])[1];
        var det      = document.getElementById('psp-pago-detalle');
        var st       = document.getElementById('psp-pago-status');
        st.innerHTML = '';

        if (!jwt && !membId) {
          det.innerHTML = '<div class="psp-error">&#x1F512; Debes <a href="/mi-cuenta/">iniciar sesi&oacute;n</a> o <a href="/registro/">registrarte</a> primero.</div>';
          return;
        }

        det.innerHTML = '<p>&#x23F3; Preparando pago...</p>';
        var total     = this.monto * this.cantidad;

        try {
          var r = await fetch(PSP_CONFIG.ajax_url, {
            method:'POST',
            body: new URLSearchParams({
              action    : 'psp_crear_pago',
              metodo    : metodo,
              monto     : total,
              tipo      : this.tipo,
              cantidad  : this.cantidad,
              miembro_id: membId || '',
              psp_nonce : PSP_CONFIG.nonce
            })
          });
          var d = await r.json();
          if (d.success) {
            this.renderMetodo(metodo, d.data);
          } else {
            det.innerHTML = '<div class="psp-error">&#x274C; '+(d.data&&d.data.message?d.data.message:'Error')+'</div>';
          }
        } catch(e) {
          det.innerHTML = '<div class="psp-error">&#x274C; Error de conexi&oacute;n.</div>';
        }
      },

      renderMetodo: function(metodo, data) {
        var c   = document.getElementById('psp-pago-detalle');
        var ref = data.referencia || '—';
        var mnt = parseFloat(data.monto||0).toFixed(2);
        var tid = data.pago_id || '';

        var tpls = {
          yappy: '<div class="psp-instrucciones"><h4>&#x1F7E1; Pago por Yappy</h4>'
            + '<p>Env&iacute;a <strong>$'+mnt+'</strong> al n&uacute;mero Yappy:</p>'
            + '<div style="font-size:22px;font-weight:800;color:#0B5E43;padding:12px;background:#F0FDF4;border-radius:8px;text-align:center">'+(data.numero_yappy||'Configura en PSP Core')+'</div>'
            + '<p style="margin-top:8px">Nombre: <strong>'+(data.nombre_yappy||'Panamá Sin Pobreza')+'</strong></p>'
            + '<p>Referencia: <code>'+ref+'</code></p>'
            + '<button onclick="PSPPagos.confirmarManual(\''+tid+'\')" class="psp-btn psp-btn-primary" style="margin-top:12px">Ya pagu&eacute; &#x2714;</button></div>',

          clave: '<div class="psp-instrucciones"><h4>&#x1F511; Sistema Clave / PagueloFacil</h4>'
            + (data.checkout_url
              ? '<a href="'+data.checkout_url+'" target="_blank" class="psp-btn psp-btn-primary">Ir a PagueloFacil &#x2192;</a>'
              : '<p>Referencia: <code>'+ref+'</code><br><small>Configura PagueloFacil en PSP &rarr; Pagos.</small>')
            + '</div>',

          tarjeta: '<div class="psp-instrucciones"><h4>&#x1F4B3; Pago con Tarjeta</h4>'
            + (data.checkout_url
              ? '<a href="'+data.checkout_url+'" target="_blank" class="psp-btn psp-btn-primary">Pagar con Tarjeta &#x2192;</a>'
              : '<p>Referencia: <code>'+ref+'</code><br><small>Configura el pasarela en PSP &rarr; Pagos.</small>')
            + '</div>',

          ach: '<div class="psp-instrucciones"><h4>&#x1F3E6; Transferencia ACH Panam&aacute;</h4>'
            + '<p>Banco: <strong>'+(data.banco||'')+'</strong></p>'
            + '<p>Cuenta: <strong>'+(data.cuenta||'—')+'</strong></p>'
            + '<p>Titular: <strong>'+(data.titular||'Iniciativa Panamá Sin Pobreza')+'</strong></p>'
            + '<p>Referencia: <code>'+ref+'</code></p>'
            + '<p>Monto: <strong>$'+mnt+'</strong></p>'
            + '<button onclick="PSPPagos.subirComprobante(\''+tid+'\')" class="psp-btn" style="margin-top:10px">&#x1F4CE; Subir comprobante</button></div>',

          transferencia: '<div class="psp-instrucciones"><h4>&#x1F504; Transferencia Internacional</h4>'
            + '<p>Referencia: <code>'+ref+'</code> | Monto: <strong>$'+mnt+'</strong></p>'
            + '<p>Contacta a <a href="mailto:'+('<?php echo esc_js(get_option("admin_email")); ?>')+'">admin</a> para datos SWIFT/IBAN.</p>'
            + '<button onclick="PSPPagos.subirComprobante(\''+tid+'\')" class="psp-btn" style="margin-top:10px">&#x1F4CE; Subir comprobante</button></div>',

          puntopago: '<div class="psp-instrucciones"><h4>&#x1F3EA; PuntoPago</h4>'
            + '<p>Presenta este c&oacute;digo en cualquier punto autorizado:</p>'
            + '<div style="font-size:28px;font-weight:900;color:#0B5E43;text-align:center;padding:12px;background:#F0FDF4;border-radius:8px">'+ref+'</div>'
            + '<p style="margin-top:8px;font-size:13px">Monto a pagar: <strong>$'+mnt+'</strong></p></div>',

          efectivo: '<div class="psp-instrucciones"><h4>&#x1F4B5; Pago en Efectivo</h4>'
            + '<p>Acércate a tu coordinador territorial con este c&oacute;digo:</p>'
            + '<div style="font-size:24px;font-weight:900;color:#0B5E43;text-align:center;padding:12px;background:#F0FDF4;border-radius:8px">'+ref+'</div></div>',
        };

        c.innerHTML = tpls[metodo] || '<p>M&eacute;todo en configuraci&oacute;n.</p>';
      },

      async confirmarManual(pago_id) {
        var r = await fetch(PSP_CONFIG.ajax_url,{method:'POST',body:new URLSearchParams({action:'psp_confirmar_pago_manual',pago_id,psp_nonce:PSP_CONFIG.nonce})});
        var d = await r.json();
        document.getElementById('psp-pago-status').innerHTML = d.success
          ? '<div class="psp-success">&#x2705; Pago registrado y pendiente de validaci&oacute;n. &#x1F64F; Gracias por tu apoyo!</div>'
          : '<div class="psp-error">&#x274C; '+(d.data&&d.data.message?d.data.message:'Error')+'</div>';
      },

      subirComprobante(pago_id) {
        var c = document.getElementById('psp-pago-detalle');
        c.innerHTML += '<div class="psp-upload" style="margin-top:12px"><label style="font-size:13px;font-weight:600">Sube tu comprobante (imagen o PDF):</label>'
          +'<input type="file" id="psp-comp-file" accept="image/*,.pdf" style="display:block;margin:6px 0">'
          +'<button onclick="PSPPagos.enviarComprobante(\''+pago_id+'\')" class="psp-btn psp-btn-primary psp-btn-sm">Enviar comprobante</button></div>';
      },

      async enviarComprobante(pago_id) {
        var file = document.getElementById('psp-comp-file');
        if (!file||!file.files[0]){alert('Selecciona un archivo');return;}
        var fd=new FormData();
        fd.append('action','psp_subir_comprobante');
        fd.append('pago_id',pago_id);
        fd.append('comprobante',file.files[0]);
        fd.append('psp_nonce',PSP_CONFIG.nonce);
        var r=await fetch(PSP_CONFIG.ajax_url,{method:'POST',body:fd});
        var d=await r.json();
        document.getElementById('psp-pago-status').innerHTML=d.success
          ?'<div class="psp-success">&#x2705; Comprobante enviado. Verificaremos pronto.</div>'
          :'<div class="psp-error">&#x274C; Error subiendo archivo.</div>';
      }
    };

    // Init: aplicar tipo de URL si viene predefinido
    document.addEventListener('DOMContentLoaded', function(){
      var sel = document.getElementById('psp-tipo-sel');
      if (sel) PSPPagos.onChangeTipo(sel);
    });
    </script>
    <?php
    return ob_get_clean();
}
