<?php
/**
 * Plugin Name: PSP Membresías
 * Plugin URI:  https://panamasinpobreza.org
 * Description: Gestión completa de membresías del Movimiento Panamá Sin Pobreza: nacionales, internacionales, actores, sectores, hogares solidarios, productores. Calculadora de impacto, precios, activación y control de estados.
 * Version:     1.0.0
 * Author:      PSP Dev Team
 * Requires PHP: 7.4
 * Text Domain: psp-membresias
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// ── Aviso dependencia ─────────────────────────────────────────────────────────
add_action( 'admin_notices', 'psp_membresias_check_core' );
function psp_membresias_check_core() {
    if ( ! class_exists( 'PSP_Supabase' ) ) {
        echo '<div class="notice notice-error"><p>'
           . '<strong>PSP Membresías:</strong> Requiere que <strong>PSP Core</strong> esté activado primero.</p></div>';
    }
}

// ── Cargar sub-archivos ───────────────────────────────────────────────────────
add_action( 'plugins_loaded', 'psp_membresias_load' );
function psp_membresias_load() {
    require_once plugin_dir_path( __FILE__ ) . 'includes/membresias-config.php';
    require_once plugin_dir_path( __FILE__ ) . 'includes/membresias-ajax.php';
    require_once plugin_dir_path( __FILE__ ) . 'includes/membresias-admin.php';
}

// ── Activación ────────────────────────────────────────────────────────────────
register_activation_hook( __FILE__, 'psp_membresias_activate' );
function psp_membresias_activate() {
    // Precios por defecto
    $defaults = [
        'psp_precio_nacional'      => '5',
        'psp_precio_internacional' => '10',
        'psp_precio_actor'         => '25',
        'psp_precio_sector'        => '50',
        'psp_precio_hogar'         => '15',
        'psp_precio_productor'     => '20',
        'psp_precio_comunicador'   => '15',
        'psp_precio_influencer'    => '25',
        'psp_precio_embajador'     => '0',
        'psp_precio_voluntario'    => '0',
    ];
    foreach ( $defaults as $key => $val ) {
        add_option( $key, $val );
    }
}

// ── Shortcodes ────────────────────────────────────────────────────────────────
add_shortcode( 'psp_membresias',         'psp_membresias_shortcode' );
add_shortcode( 'psp_membresia_card',     'psp_membresia_card_sc' );
add_shortcode( 'psp_calculadora_impacto','psp_calculadora_impacto_sc' );
add_shortcode( 'psp_mi_membresia',       'psp_mi_membresia_sc' );

// ── Shortcode principal: tabla de membresías ──────────────────────────────────
function psp_membresias_shortcode( $atts = [] ) {
    $atts = shortcode_atts([
        'mostrar'  => 'todas',   // todas | nacionales | actores | especiales
        'columnas' => '3',
        'boton'    => 'Unirme',
        'destino'  => '/registro/',
    ], $atts );

    $membresias = psp_get_membresias_config();
    if ( $atts['mostrar'] !== 'todas' ) {
        $grupos = [
            'nacionales' => [ 'nacional', 'internacional' ],
            'actores'    => [ 'actor', 'sector', 'comunicador', 'influencer', 'embajador', 'voluntario' ],
            'especiales' => [ 'hogar_solidario', 'productor' ],
        ];
        $filtro = $grupos[ $atts['mostrar'] ] ?? [];
        $membresias = array_filter( $membresias, fn($m) => in_array( $m['tipo'], $filtro ) );
    }

    ob_start();
    ?>
    <div class="psp-membresias-wrap">
      <div class="psp-mem-grid psp-mem-cols-<?php echo esc_attr( $atts['columnas'] ); ?>">
        <?php foreach ( $membresias as $m ) :
            $precio_opt = get_option( 'psp_precio_' . $m['tipo'], $m['precio_default'] );
            $precio     = (float) $precio_opt;
            $es_gratis  = $precio <= 0;
        ?>
        <div class="psp-mem-card <?php echo $m['destacada'] ? 'psp-mem-destacada' : ''; ?>">
          <?php if ( $m['destacada'] ) : ?>
            <div class="psp-mem-badge">&#x1F525; Más popular</div>
          <?php endif; ?>
          <div class="psp-mem-icono"><?php echo $m['icono']; ?></div>
          <div class="psp-mem-tipo"><?php echo esc_html( $m['nombre'] ); ?></div>
          <div class="psp-mem-precio">
            <?php if ( $es_gratis ) : ?>
              <span class="psp-mem-gratis">Gratis</span>
            <?php else : ?>
              <span class="psp-mem-monto">$<?php echo number_format( $precio, 2 ); ?></span>
              <span class="psp-mem-periodo"><?php echo esc_html( $m['periodo'] ); ?></span>
            <?php endif; ?>
          </div>
          <div class="psp-mem-desc"><?php echo esc_html( $m['descripcion'] ); ?></div>
          <ul class="psp-mem-beneficios">
            <?php foreach ( $m['beneficios'] as $b ) : ?>
              <li>&#x2714;&#xFE0F; <?php echo esc_html( $b ); ?></li>
            <?php endforeach; ?>
          </ul>
          <?php if ( ! empty( $m['calculadora'] ) ) : ?>
            <div class="psp-mem-calc-mini" data-tipo="<?php echo esc_attr( $m['tipo'] ); ?>">
              <label>Cantidad:
                <input type="number" class="psp-mem-qty" min="1" value="1"
                       onchange="PSPMem.calcMini(this,'<?php echo esc_js( $m['tipo'] ); ?>')">
              </label>
              <div class="psp-mem-calc-res"></div>
            </div>
          <?php endif; ?>
          <a href="<?php echo esc_url( $atts['destino'] . '?tipo=' . $m['tipo'] ); ?>"
             class="psp-btn psp-btn-primary psp-mem-btn">
            <?php echo esc_html( $es_gratis ? 'Registrarme' : $atts['boton'] ); ?>
          </a>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <script>
    var PSPMem = PSPMem || {
      calcMini: function(input, tipo) {
        var qty     = parseInt(input.value) || 1;
        var res     = input.closest('.psp-mem-calc-mini').querySelector('.psp-mem-calc-res');
        var precios = <?php echo wp_json_encode( psp_get_precios_js() ); ?>;
        var precio  = precios[tipo] || 0;
        var total   = qty * precio;
        var empleos = 0, personas = 0;

        if (tipo === 'hogar_solidario') {
          empleos  = Math.floor(qty * 0.15);
          personas = Math.floor(qty * 3.8);
        } else if (tipo === 'productor') {
          empleos  = Math.floor(qty * 1);
          personas = Math.floor(qty * 4.2);
        }

        var html = '<div class="psp-calc-mini-r">'
          + '<span>&#x1F4B0; Total: <strong>$' + total.toFixed(2) + '</strong></span>';
        if (empleos > 0) html += ' <span>&#x1F454; Empleos: <strong>~' + empleos + '</strong></span>';
        if (personas > 0) html += ' <span>&#x1F46B; Personas: <strong>~' + personas + '</strong></span>';
        html += '</div>';
        res.innerHTML = html;
      }
    };
    </script>
    <?php
    return ob_get_clean();
}

// ── Card individual ───────────────────────────────────────────────────────────
function psp_membresia_card_sc( $atts = [] ) {
    $atts = shortcode_atts([ 'tipo' => 'nacional', 'destino' => '/registro/' ], $atts );
    $mems = psp_get_membresias_config();
    $m    = null;
    foreach ( $mems as $mem ) {
        if ( $mem['tipo'] === $atts['tipo'] ) { $m = $mem; break; }
    }
    if ( ! $m ) return '<p>Tipo de membresía no encontrado.</p>';
    $precio = (float) get_option( 'psp_precio_' . $m['tipo'], $m['precio_default'] );

    ob_start();
    ?>
    <div class="psp-mem-card <?php echo $m['destacada'] ? 'psp-mem-destacada' : ''; ?>" style="max-width:320px">
      <div class="psp-mem-icono"><?php echo $m['icono']; ?></div>
      <div class="psp-mem-tipo"><?php echo esc_html( $m['nombre'] ); ?></div>
      <div class="psp-mem-precio">
        <span class="psp-mem-monto">$<?php echo number_format($precio,2); ?></span>
      </div>
      <div class="psp-mem-desc"><?php echo esc_html( $m['descripcion'] ); ?></div>
      <a href="<?php echo esc_url( $atts['destino'] . '?tipo=' . $m['tipo'] ); ?>"
         class="psp-btn psp-btn-primary psp-mem-btn">Unirme</a>
    </div>
    <?php
    return ob_get_clean();
}

// ── Calculadora de impacto completa ──────────────────────────────────────────
function psp_calculadora_impacto_sc( $atts = [] ) {
    ob_start();
    ?>
    <div class="psp-calc-impacto psp-card">
      <h3>&#x1F4CA; Calculadora de Impacto</h3>
      <p style="color:#555;font-size:14px">Selecciona el tipo y cantidad para ver el impacto real de tu contribución.</p>

      <div class="psp-calc-row">
        <label class="psp-calc-label">Tipo de membresía:</label>
        <select id="psp-calc-tipo" class="psp-input" onchange="PSPCalc.actualizar()">
          <option value="nacional">&#x1F1F5;&#x1F1E6; Miembro Nacional — $5</option>
          <option value="internacional">&#x1F30E; Internacional — $10</option>
          <option value="actor">&#x1F3AD; Actor / Coalición — $25</option>
          <option value="sector">&#x1F3E2; Sector / Empresa — $50</option>
          <option value="hogar_solidario" selected>&#x1F3E0; Hogar Solidario — $15</option>
          <option value="productor">&#x1F33E; Productor Beneficiario — $20</option>
          <option value="comunicador">&#x1F4E2; Comunicador — $15</option>
          <option value="influencer">&#x1F4F1; Influencer — $25</option>
          <option value="embajador">&#x1F31F; Embajador — Gratis</option>
          <option value="voluntario">&#x1F91D; Voluntario — Gratis</option>
        </select>
      </div>

      <div class="psp-calc-row">
        <label class="psp-calc-label">Cantidad:</label>
        <input type="number" id="psp-calc-qty" class="psp-input" min="1" value="1"
               oninput="PSPCalc.actualizar()" style="max-width:120px">
      </div>

      <div class="psp-calc-row">
        <label class="psp-calc-label">Personas que invitas:</label>
        <input type="number" id="psp-calc-red" class="psp-input" min="0" value="5"
               oninput="PSPCalc.actualizar()" style="max-width:120px">
      </div>

      <div class="psp-calc-resultados">
        <div class="psp-calc-kpi">
          <div class="psp-calc-kpi-val" id="calc-total">$15.00</div>
          <div class="psp-calc-kpi-lbl">Tu aporte</div>
        </div>
        <div class="psp-calc-kpi">
          <div class="psp-calc-kpi-val" id="calc-empleos">&#x2014;</div>
          <div class="psp-calc-kpi-lbl">Empleos generados</div>
        </div>
        <div class="psp-calc-kpi">
          <div class="psp-calc-kpi-val" id="calc-personas">&#x2014;</div>
          <div class="psp-calc-kpi-lbl">Personas sin pobreza</div>
        </div>
        <div class="psp-calc-kpi">
          <div class="psp-calc-kpi-val" id="calc-red">$0</div>
          <div class="psp-calc-kpi-lbl">Impacto con tu red</div>
        </div>
      </div>

      <div id="psp-calc-nota" class="psp-calc-nota"></div>

      <a id="psp-calc-btn" href="/registro/?tipo=hogar_solidario"
         class="psp-btn psp-btn-primary psp-btn-full" style="margin-top:16px">
        &#x1F680; Quiero generar este impacto
      </a>
    </div>

    <script>
    var PSPCalc = {
      precios: <?php echo wp_json_encode( psp_get_precios_js() ); ?>,

      impacto: {
        hogar_solidario : { empleo_ratio: 0.15, persona_ratio: 3.8 },
        productor       : { empleo_ratio: 1.0,  persona_ratio: 4.2 },
        sector          : { empleo_ratio: 5.0,  persona_ratio: 20  },
        actor           : { empleo_ratio: 2.0,  persona_ratio: 8   },
        nacional        : { empleo_ratio: 0,    persona_ratio: 0   },
        internacional   : { empleo_ratio: 0,    persona_ratio: 0   },
        comunicador     : { empleo_ratio: 0,    persona_ratio: 0   },
        influencer      : { empleo_ratio: 0,    persona_ratio: 0   },
        embajador       : { empleo_ratio: 0,    persona_ratio: 0   },
        voluntario      : { empleo_ratio: 0,    persona_ratio: 0   },
      },

      actualizar: function() {
        var tipo    = document.getElementById('psp-calc-tipo').value;
        var qty     = parseInt(document.getElementById('psp-calc-qty').value) || 1;
        var red_qty = parseInt(document.getElementById('psp-calc-red').value) || 0;
        var precio  = this.precios[tipo] || 0;
        var imp     = this.impacto[tipo] || { empleo_ratio:0, persona_ratio:0 };

        var total   = qty * precio;
        var empleos = Math.floor(qty * imp.empleo_ratio);
        var personas= Math.floor(qty * imp.persona_ratio);
        var red_tot = red_qty * precio;

        document.getElementById('calc-total').textContent =
          '$' + total.toFixed(2);
        document.getElementById('calc-empleos').textContent =
          empleos > 0 ? '~' + empleos.toLocaleString('es-PA') : '—';
        document.getElementById('calc-personas').textContent =
          personas > 0 ? '~' + personas.toLocaleString('es-PA') : '—';
        document.getElementById('calc-red').textContent =
          '$' + (total + red_tot).toFixed(2);

        var nota = '';
        if (empleos > 0 || personas > 0) {
          nota = 'Con ' + qty + ' ' + tipo.replace('_',' ') + (qty>1?'s':'') + ', generas aprox. '
               + (empleos>0 ? empleos + ' empleos ' : '')
               + (personas>0 ? 'y sacas ~' + personas + ' personas de la pobreza.' : '');
        }
        document.getElementById('psp-calc-nota').textContent = nota;

        var btn = document.getElementById('psp-calc-btn');
        if (btn) btn.href = '/registro/?tipo=' + tipo + '&qty=' + qty;
      }
    };
    document.addEventListener('DOMContentLoaded', function() { PSPCalc.actualizar(); });
    </script>
    <?php
    return ob_get_clean();
}

// ── Mi membresía (panel usuario) ─────────────────────────────────────────────
function psp_mi_membresia_sc( $atts = [] ) {
    ob_start();
    ?>
    <div id="psp-mi-mem-wrap" class="psp-card">
      <div id="psp-mi-mem-loading">&#x23F3; Cargando tu membresía...</div>
      <div id="psp-mi-mem-contenido" style="display:none">
        <div style="display:flex;align-items:center;gap:16px;margin-bottom:20px">
          <div id="psp-mi-mem-icono" style="font-size:40px">&#x1F3F7;&#xFE0F;</div>
          <div>
            <div id="psp-mi-mem-tipo"
                 style="font-size:20px;font-weight:800;color:#0B5E43"></div>
            <div id="psp-mi-mem-estado"
                 style="font-size:13px;margin-top:2px"></div>
          </div>
          <div id="psp-mi-mem-badge"
               style="margin-left:auto;padding:6px 16px;border-radius:20px;font-size:12px;font-weight:700"></div>
        </div>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:16px">
          <div class="psp-stat">
            <strong id="psp-mi-nivel">&#x1F331;</strong>
            <span>Nivel</span>
          </div>
          <div class="psp-stat">
            <strong id="psp-mi-puntos" style="color:#EF9F27">0</strong>
            <span>Puntos</span>
          </div>
          <div class="psp-stat">
            <strong id="psp-mi-refs" style="color:#0B5E43">0</strong>
            <span>Referidos</span>
          </div>
        </div>
        <div id="psp-mi-mem-vencimiento"
             style="font-size:13px;color:#888;border-top:1px solid #e5e7eb;padding-top:12px"></div>
        <div style="margin-top:14px;display:flex;gap:10px;flex-wrap:wrap">
          <a href="/apoyar/" class="psp-btn psp-btn-primary">&#x1F4B3; Renovar / Subir nivel</a>
          <a href="/mi-referido/" class="psp-btn">&#x1F517; Mi código de referido</a>
        </div>
      </div>
      <div id="psp-mi-mem-noauth" style="display:none">
        <p>&#x1F512; <a href="/mi-cuenta/">Inicia sesi&oacute;n</a> para ver tu membres&iacute;a.</p>
      </div>
    </div>
    <script>
    (async function() {
      var jwt = (document.cookie.match(/psp_jwt=([^;]+)/) || [])[1];
      var loading = document.getElementById('psp-mi-mem-loading');
      var cont    = document.getElementById('psp-mi-mem-contenido');
      var noauth  = document.getElementById('psp-mi-mem-noauth');

      if (!jwt) {
        loading.style.display = 'none';
        noauth.style.display  = 'block';
        return;
      }

      var r = await fetch(PSP_CONFIG.ajax_url, {
        method : 'POST',
        body   : new URLSearchParams({
          action    : 'psp_get_mi_membresia',
          psp_nonce : PSP_CONFIG.nonce,
          jwt       : jwt
        })
      });
      var d = await r.json();
      loading.style.display = 'none';

      if (!d.success || !d.data) {
        noauth.style.display = 'block';
        return;
      }

      var m    = d.data;
      var mems = <?php echo wp_json_encode( psp_get_membresias_nombres() ); ?>;

      document.getElementById('psp-mi-mem-icono').textContent =
        m.icono || '&#x1F3F7;';
      document.getElementById('psp-mi-mem-tipo').textContent =
        mems[m.tipo_miembro] || m.tipo_miembro || '—';
      document.getElementById('psp-mi-mem-estado').textContent =
        'Miembro desde ' + (m.created_at ? new Date(m.created_at).toLocaleDateString('es-PA') : '—');
      document.getElementById('psp-mi-nivel').textContent  = m.nivel || '&#x1F331;';
      document.getElementById('psp-mi-puntos').textContent =
        Number(m.puntos_total || 0).toLocaleString('es-PA');
      document.getElementById('psp-mi-refs').textContent   = m.refs || 0;

      var badge  = document.getElementById('psp-mi-mem-badge');
      var estados = {
        activo          : { txt: '&#x2705; Activo',   bg: '#f0fdf4', color: '#166534' },
        pendiente_pago  : { txt: '&#x23F3; Pendiente',bg: '#fefce8', color: '#854d0e' },
        inactivo        : { txt: '&#x26AA; Inactivo', bg: '#f3f4f6', color: '#374151' },
        suspendido      : { txt: '&#x1F534; Suspendido',bg:'#fef2f2',color:'#991b1b'  },
      };
      var est = estados[m.estado] || estados['inactivo'];
      badge.innerHTML         = est.txt;
      badge.style.background  = est.bg;
      badge.style.color       = est.color;

      cont.style.display = 'block';
    })();
    </script>
    <?php
    return ob_get_clean();
}

// ── Encolar CSS ───────────────────────────────────────────────────────────────
add_action( 'wp_enqueue_scripts', 'psp_membresias_enqueue' );
function psp_membresias_enqueue() {
    wp_enqueue_style(
        'psp-membresias',
        plugin_dir_url( __FILE__ ) . 'assets/membresias.css',
        [],
        '1.0.0'
    );
}
