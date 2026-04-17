<?php
/**
 * Plugin Name: PSP Productos
 * Plugin URI:  https://panamasinpobreza.org
 * Description: Catálogo completo de productos y servicios del Movimiento Panamá Sin Pobreza: Plantones de Reforestación y el Servicio Integral de Gestión Social (SIGS). Tienda, calculadora de impacto ambiental y gestión de pedidos.
 * Version:     1.0.0
 * Author:      PSP Dev Team
 * Requires PHP: 7.4
 * Text Domain: psp-productos
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// ── Aviso dependencia ─────────────────────────────────────────────────────────
add_action( 'admin_notices', 'psp_productos_check_core' );
function psp_productos_check_core() {
    if ( ! class_exists( 'PSP_Supabase' ) ) {
        echo '<div class="notice notice-error"><p>'
           . '<strong>PSP Productos:</strong> Requiere que <strong>PSP Core</strong> esté activado primero.</p></div>';
    }
}

// ── Cargar sub-archivos ───────────────────────────────────────────────────────
add_action( 'plugins_loaded', 'psp_productos_load' );
function psp_productos_load() {
    require_once plugin_dir_path( __FILE__ ) . 'includes/productos-config.php';
    require_once plugin_dir_path( __FILE__ ) . 'includes/productos-ajax.php';
    require_once plugin_dir_path( __FILE__ ) . 'includes/productos-admin.php';
}

// ── Activación ────────────────────────────────────────────────────────────────
register_activation_hook( __FILE__, 'psp_productos_activate' );
function psp_productos_activate() {
    add_option( 'psp_precio_planton',   '2' );
    add_option( 'psp_precio_sigs_base', '500' );
    add_option( 'psp_precio_sigs_mes',  '150' );
    add_option( 'psp_stock_plantones',  '10000' );
}

// ── CSS ───────────────────────────────────────────────────────────────────────
add_action( 'wp_enqueue_scripts', 'psp_productos_enqueue' );
function psp_productos_enqueue() {
    wp_enqueue_style(
        'psp-productos',
        plugin_dir_url( __FILE__ ) . 'assets/productos.css',
        [],
        '1.0.0'
    );
}

// ── Shortcodes ────────────────────────────────────────────────────────────────
add_shortcode( 'psp_productos',         'psp_productos_shortcode' );
add_shortcode( 'psp_tienda_plantones',  'psp_tienda_plantones_sc' );
add_shortcode( 'psp_sigs',              'psp_sigs_shortcode' );
add_shortcode( 'psp_mis_pedidos',       'psp_mis_pedidos_sc' );

// ── Shortcode catálogo completo ───────────────────────────────────────────────
function psp_productos_shortcode( $atts = [] ) {
    $atts = shortcode_atts([
        'mostrar' => 'todos',  // todos | plantones | sigs
    ], $atts );

    $productos = psp_get_productos_config();
    if ( $atts['mostrar'] !== 'todos' ) {
        $productos = array_filter( $productos, fn($p) => $p['categoria'] === $atts['mostrar'] );
    }

    ob_start();
    ?>
    <div class="psp-prod-catalogo">
      <?php foreach ( $productos as $prod ) : ?>
        <div class="psp-prod-card" id="prod-<?php echo esc_attr( $prod['slug'] ); ?>">
          <?php if ( ! empty( $prod['nuevo'] ) ) : ?>
            <div class="psp-prod-tag psp-prod-tag-nuevo">&#x1F195; Nuevo</div>
          <?php elseif ( ! empty( $prod['destacado'] ) ) : ?>
            <div class="psp-prod-tag psp-prod-tag-dest">&#x2B50; Destacado</div>
          <?php endif; ?>

          <div class="psp-prod-icono"><?php echo $prod['icono']; ?></div>
          <div class="psp-prod-cat"><?php echo esc_html( $prod['categoria_label'] ); ?></div>
          <h3 class="psp-prod-nombre"><?php echo esc_html( $prod['nombre'] ); ?></h3>
          <p class="psp-prod-desc"><?php echo esc_html( $prod['descripcion_corta'] ); ?></p>

          <?php if ( $prod['tipo_precio'] === 'unitario' ) : ?>
            <div class="psp-prod-precio">
              <span class="psp-prod-monto">
                $<?php echo number_format( (float) get_option( $prod['opcion_precio'], $prod['precio_default'] ), 2 ); ?>
              </span>
              <span class="psp-prod-unidad"><?php echo esc_html( $prod['unidad'] ); ?></span>
            </div>
          <?php elseif ( $prod['tipo_precio'] === 'desde' ) : ?>
            <div class="psp-prod-precio">
              <span class="psp-prod-desde">Desde</span>
              <span class="psp-prod-monto">
                $<?php echo number_format( (float) get_option( $prod['opcion_precio'], $prod['precio_default'] ), 2 ); ?>
              </span>
            </div>
          <?php endif; ?>

          <ul class="psp-prod-features">
            <?php foreach ( $prod['features'] as $f ) : ?>
              <li><?php echo $f; ?></li>
            <?php endforeach; ?>
          </ul>

          <?php
          // Renderizar el componente específico del producto
          if ( $prod['slug'] === 'plantones' ) {
              echo psp_widget_plantones_inline();
          } elseif ( $prod['slug'] === 'sigs' ) {
              echo psp_widget_sigs_inline();
          }
          ?>

          <a href="<?php echo esc_url( $prod['url_compra'] ); ?>"
             class="psp-btn psp-btn-primary psp-prod-btn">
            <?php echo esc_html( $prod['boton_texto'] ); ?>
          </a>
        </div>
      <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}

// ── Widget calculadora plantones ──────────────────────────────────────────────
function psp_widget_plantones_inline() {
    $precio = (float) get_option( 'psp_precio_planton', '2' );
    ob_start();
    ?>
    <div class="psp-planton-calc" data-precio="<?php echo esc_attr( $precio ); ?>">
      <label class="psp-planton-label">&#x1F4CA; ¿Cuántos plantones quieres sembrar?</label>
      <div class="psp-planton-ctrl">
        <button type="button" class="psp-planton-menos" onclick="PSPProd.cambiarQty(this,-1)">&#x2212;</button>
        <input  type="number"  class="psp-planton-qty" value="10" min="1"
                onchange="PSPProd.actualizarCalc(this.closest('.psp-planton-calc'))">
        <button type="button" class="psp-planton-mas"  onclick="PSPProd.cambiarQty(this,1)">&#x2B;</button>
      </div>
      <div class="psp-planton-res">
        <div class="psp-planton-kpi">
          <span class="psp-planton-kpi-val" data-campo="total">$20.00</span>
          <span class="psp-planton-kpi-lbl">Total</span>
        </div>
        <div class="psp-planton-kpi">
          <span class="psp-planton-kpi-val" data-campo="co2">&#x2248; 1.5 ton</span>
          <span class="psp-planton-kpi-lbl">CO&#x2082; absorbido/año</span>
        </div>
        <div class="psp-planton-kpi">
          <span class="psp-planton-kpi-val" data-campo="empleos">&#x2248; 0.2</span>
          <span class="psp-planton-kpi-lbl">Empleos generados</span>
        </div>
      </div>
      <input type="hidden" class="psp-planton-qty-hidden" value="10">
    </div>
    <script>
    var PSPProd = PSPProd || {
      cambiarQty: function(btn, delta) {
        var wrap = btn.closest('.psp-planton-calc');
        var inp  = wrap.querySelector('.psp-planton-qty');
        var val  = Math.max(1, parseInt(inp.value||1) + delta);
        inp.value = val;
        this.actualizarCalc(wrap);
      },
      actualizarCalc: function(wrap) {
        var qty    = parseInt(wrap.querySelector('.psp-planton-qty').value) || 1;
        var precio = parseFloat(wrap.dataset.precio) || 2;
        var total  = qty * precio;
        var co2    = (qty * 0.15).toFixed(1);
        var emp    = (qty * 0.02).toFixed(1);
        wrap.querySelector('[data-campo="total"]').textContent = '$' + total.toFixed(2);
        wrap.querySelector('[data-campo="co2"]').textContent   = '&#x2248; ' + co2 + ' ton';
        wrap.querySelector('[data-campo="empleos"]').textContent = '&#x2248; ' + emp;
        var h = wrap.querySelector('.psp-planton-qty-hidden');
        if (h) h.value = qty;
      }
    };
    document.querySelectorAll('.psp-planton-calc').forEach(function(w) { PSPProd.actualizarCalc(w); });
    </script>
    <?php
    return ob_get_clean();
}

// ── Shortcode tienda de plantones ─────────────────────────────────────────────
function psp_tienda_plantones_sc( $atts = [] ) {
    $precio = (float) get_option( 'psp_precio_planton', '2' );
    $stock  = (int)   get_option( 'psp_stock_plantones', '10000' );

    ob_start();
    ?>
    <div class="psp-prod-card psp-card" style="max-width:520px">
      <div class="psp-prod-icono" style="font-size:52px">&#x1F331;</div>
      <h2 style="font-size:22px;font-weight:800;color:#0B5E43;margin-bottom:8px">
        Plantones de Reforestación
      </h2>
      <p style="font-size:14px;color:#555;margin-bottom:16px">
        Cada plantón que compras es un árbol que se siembra en Panamá, genera empleo rural
        y absorbe CO&#x2082; del ambiente. El regalo perfecto para el país.
      </p>

      <div style="background:#F0FDF4;border-radius:10px;padding:16px;margin-bottom:16px;font-size:13px;color:#166534">
        &#x1F4CB; Stock disponible: <strong><?php echo number_format($stock,'','.',','); ?> plantones</strong>
        &nbsp;|&nbsp; Precio: <strong>$<?php echo number_format($precio,2); ?> por plantón</strong>
      </div>

      <?php echo psp_widget_plantones_inline(); ?>

      <div style="margin-top:16px">
        <button onclick="PSPProdOrder.comprarPlantones()"
                class="psp-btn psp-btn-primary psp-btn-full" style="font-size:16px;padding:14px">
          &#x1F331; Sembrar mis plantones
        </button>
      </div>
      <div id="psp-planton-msg" style="margin-top:12px"></div>
    </div>

    <script>
    var PSPProdOrder = PSPProdOrder || {
      comprarPlantones: async function() {
        var jwt = (document.cookie.match(/psp_jwt=([^;]+)/) || [])[1];
        if (!jwt) {
          window.location.href = '/mi-cuenta/?redirect=' + encodeURIComponent(window.location.href);
          return;
        }
        var wrap = document.querySelector('.psp-planton-calc');
        var qty  = parseInt(wrap?.querySelector('.psp-planton-qty')?.value || 1);
        var msg  = document.getElementById('psp-planton-msg');
        msg.innerHTML = '&#x23F3; Procesando...';

        var r = await fetch(PSP_CONFIG.ajax_url, {
          method : 'POST',
          body   : new URLSearchParams({
            action    : 'psp_crear_pedido_planton',
            cantidad  : qty,
            psp_nonce : PSP_CONFIG.nonce,
            jwt       : jwt
          })
        });
        var d = await r.json();
        if (d.success) {
          msg.innerHTML = '<div style="background:#f0fdf4;border:1px solid #86efac;padding:12px;border-radius:8px;color:#166534">'
            + '&#x2705; Pedido creado. Referencia: <strong>' + d.data.referencia + '</strong><br>'
            + 'Total: <strong>$' + d.data.total.toFixed(2) + '</strong><br>'
            + '<a href="/apoyar/?ref=' + d.data.pago_id + '" class="psp-btn psp-btn-primary" style="margin-top:10px;display:inline-flex">&#x1F4B3; Ir a pagar</a>'
            + '</div>';
        } else {
          msg.innerHTML = '<div style="background:#fef2f2;padding:12px;border-radius:8px;color:#991b1b">'
            + '&#x274C; ' + ((d.data && d.data.message) ? d.data.message : 'Error al procesar') + '</div>';
        }
      }
    };
    </script>
    <?php
    return ob_get_clean();
}

// ── Widget SIGS inline ─────────────────────────────────────────────────────────
function psp_widget_sigs_inline() {
    ob_start();
    ?>
    <div class="psp-sigs-planes">
      <?php foreach ( psp_get_sigs_planes() as $plan ) :
          $precio = (float) get_option( $plan['opcion_precio'], $plan['precio_default'] );
      ?>
      <div class="psp-sigs-plan <?php echo $plan['destacado'] ? 'psp-sigs-plan-dest' : ''; ?>">
        <?php if ($plan['destacado']) : ?><div class="psp-sigs-plan-badge">&#x1F31F; Recomendado</div><?php endif; ?>
        <div class="psp-sigs-plan-nombre"><?php echo esc_html($plan['nombre']); ?></div>
        <div class="psp-sigs-plan-precio">
          $<?php echo number_format($precio,0); ?>
          <span><?php echo esc_html($plan['periodo_label']); ?></span>
        </div>
        <a href="/contacto/?sigs=<?php echo esc_attr($plan['slug']); ?>"
           class="psp-btn <?php echo $plan['destacado'] ? 'psp-btn-primary' : ''; ?>"
           style="width:100%;justify-content:center;margin-top:10px">
          Solicitar
        </a>
      </div>
      <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}

// ── Shortcode SIGS completo ───────────────────────────────────────────────────
function psp_sigs_shortcode( $atts = [] ) {
    ob_start();
    ?>
    <div class="psp-sigs-wrap psp-card">
      <div style="display:flex;align-items:flex-start;gap:20px;margin-bottom:24px;flex-wrap:wrap">
        <div style="font-size:56px">&#x1F4BC;</div>
        <div>
          <h2 style="font-size:22px;font-weight:800;color:#0B5E43;margin-bottom:6px">
            Servicio Integral de Gestión Social
          </h2>
          <p style="font-size:14px;color:#555;max-width:580px;line-height:1.6">
            El SIGS es el servicio profesional de Iniciativa Panamá Sin Pobreza para
            organizaciones, empresas e instituciones que quieren implementar programas
            de responsabilidad social, impacto comunitario o reducción de pobreza
            con metodología probada y seguimiento de resultados.
          </p>
        </div>
      </div>

      <!-- Qué incluye -->
      <h3 style="font-size:16px;font-weight:700;margin-bottom:14px">&#x2705; ¿Qué incluye el SIGS?</h3>
      <div class="psp-sigs-features-grid">
        <?php foreach ( psp_get_sigs_features() as $f ) : ?>
        <div class="psp-sigs-feature">
          <span class="psp-sigs-feature-ico"><?php echo $f['icono']; ?></span>
          <div>
            <div class="psp-sigs-feature-titulo"><?php echo esc_html( $f['titulo'] ); ?></div>
            <div class="psp-sigs-feature-desc"><?php echo esc_html( $f['desc'] ); ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Planes -->
      <h3 style="font-size:16px;font-weight:700;margin:24px 0 14px">&#x1F4B0; Planes y Precios</h3>
      <?php echo psp_widget_sigs_inline(); ?>

      <!-- CTA -->
      <div style="margin-top:24px;padding:20px;background:#F0FDF4;border-radius:12px;border-left:4px solid #0B5E43">
        <strong style="font-size:15px">&#x1F4DE; ¿Necesitas una propuesta personalizada?</strong>
        <p style="font-size:13px;color:#555;margin:6px 0 12px">
          Diseñamos el plan exacto para tu organización, comunidad o empresa.
        </p>
        <div style="display:flex;gap:12px;flex-wrap:wrap">
          <a href="/contacto/?sigs=personalizado" class="psp-btn psp-btn-primary">
            &#x1F4E7; Solicitar propuesta
          </a>
          <a href="https://wa.me/?text=Hola,%20me%20interesa%20el%20SIGS%20de%20Panam%C3%A1%20Sin%20Pobreza"
             target="_blank" class="psp-btn psp-btn-wa">
            &#x1F4AC; WhatsApp
          </a>
        </div>
      </div>
    </div>
    <?php
    return ob_get_clean();
}

// ── Mis pedidos ───────────────────────────────────────────────────────────────
function psp_mis_pedidos_sc( $atts = [] ) {
    ob_start();
    ?>
    <div id="psp-mis-pedidos" class="psp-card">
      <h3>&#x1F6D2; Mis Pedidos</h3>
      <div id="psp-pedidos-lista"><p style="color:#888">Cargando pedidos...</p></div>
    </div>
    <script>
    (async function() {
      var jwt = (document.cookie.match(/psp_jwt=([^;]+)/) || [])[1];
      var el  = document.getElementById('psp-pedidos-lista');
      if (!jwt) { el.innerHTML='<p>Inicia sesi&oacute;n para ver tus pedidos.</p>'; return; }

      var r = await fetch(PSP_CONFIG.ajax_url, {
        method : 'POST',
        body   : new URLSearchParams({action:'psp_get_mis_pedidos', psp_nonce:PSP_CONFIG.nonce, jwt:jwt})
      });
      var d = await r.json();

      if (!d.success || !d.data || !d.data.length) {
        el.innerHTML = '<p style="color:#888">No tienes pedidos todav&iacute;a.</p>';
        return;
      }
      var h = '<table class="psp-table"><thead><tr><th>Referencia</th><th>Producto</th>'
            + '<th>Cantidad</th><th>Total</th><th>Estado</th><th>Fecha</th></tr></thead><tbody>';
      d.data.forEach(function(p) {
        h += '<tr>'
           + '<td><code>' + (p.referencia||'—') + '</code></td>'
           + '<td>' + (p.producto_nombre||'—') + '</td>'
           + '<td>' + (p.cantidad||1) + '</td>'
           + '<td><strong>$' + parseFloat(p.total||0).toFixed(2) + '</strong></td>'
           + '<td><span class="psp-estado-badge psp-estado-'+(p.estado||'')+'">'+(p.estado||'—')+'</span></td>'
           + '<td>' + (p.created_at ? new Date(p.created_at).toLocaleDateString('es-PA') : '—') + '</td>'
           + '</tr>';
      });
      h += '</tbody></table>';
      el.innerHTML = h;
    })();
    </script>
    <?php
    return ob_get_clean();
}
