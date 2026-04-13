<?php
/**
 * Plugin Name: PSP Ranking
 * Plugin URI:  https://panamasinpobreza.org
 * Description: Ranking territorial y global en tiempo real — provincias, países, embajadores y ciudades.
 * Version:     1.0.2
 * Author:      PSP Dev Team
 * Requires PHP: 7.4
 * Text Domain: psp-ranking
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// Verificar que psp-core esté activo
add_action( 'admin_notices', 'psp_ranking_check_core' );
function psp_ranking_check_core() {
    if ( ! class_exists( 'PSP_Supabase' ) ) {
        echo '<div class="notice notice-error"><p><strong>PSP Ranking:</strong> Requiere que el plugin <strong>PSP Core</strong> esté activado primero.</p></div>';
    }
}

// Cargar AJAX solo si psp-core está disponible
add_action( 'plugins_loaded', 'psp_ranking_init' );
function psp_ranking_init() {
    require_once plugin_dir_path( __FILE__ ) . 'includes/ranking-ajax.php';
}

// Registrar shortcodes
add_shortcode( 'psp_ranking',           'psp_ranking_shortcode' );
add_shortcode( 'psp_ranking_provincia', 'psp_ranking_provincia_sc' );
add_shortcode( 'psp_ranking_paises',    'psp_ranking_paises_sc' );
add_shortcode( 'psp_ranking_embajador', 'psp_ranking_embajador_sc' );
add_shortcode( 'psp_mi_posicion',       'psp_mi_posicion_shortcode' );

/**
 * Shortcode principal con tabs
 * Uso: [psp_ranking tipo="provincia" limite="20"]
 */
function psp_ranking_shortcode( $atts = [] ) {
    $atts = shortcode_atts( [
        'tipo'   => 'provincia',
        'limite' => 20,
        'titulo' => '',
    ], $atts );

    static $n = 0;
    $n++;
    $uid      = 'psprank' . $n;
    $tipo_esc = esc_js( $atts['tipo'] );

    ob_start();
    ?>
    <div id="<?php echo esc_attr( $uid ); ?>" class="psp-ranking-widget psp-card">

      <?php if ( $atts['titulo'] ) : ?>
        <h3 class="psp-ranking-titulo"><?php echo esc_html( $atts['titulo'] ); ?></h3>
      <?php endif; ?>

      <div class="psp-ranking-tabs">
        <button class="psp-rtab active"
                onclick="PSPRanking.load('<?php echo esc_js( $uid ); ?>','provincia',this)">
          🗺️ Provincias
        </button>
        <button class="psp-rtab"
                onclick="PSPRanking.load('<?php echo esc_js( $uid ); ?>','pais',this)">
          🌎 Países
        </button>
        <button class="psp-rtab"
                onclick="PSPRanking.load('<?php echo esc_js( $uid ); ?>','embajador',this)">
          🌟 Embajadores
        </button>
        <button class="psp-rtab"
                onclick="PSPRanking.load('<?php echo esc_js( $uid ); ?>','ciudad',this)">
          🏙️ Ciudades
        </button>
      </div>

      <div class="psp-ranking-lista" id="<?php echo esc_attr( $uid ); ?>-lista">
        <div class="psp-ranking-loading">⏳ Cargando ranking...</div>
      </div>

      <div class="psp-ranking-footer">
        Actualizado en tiempo real &middot;
        <span id="<?php echo esc_attr( $uid ); ?>-ts">—</span>
      </div>
    </div>

    <script>
    (function() {
      if ( typeof window.PSPRanking === 'undefined' ) {
        window.PSPRanking = {
          async load( uid, tipo, btn ) {
            var wrap = document.getElementById( uid );
            if ( wrap ) {
              wrap.querySelectorAll('.psp-rtab').forEach(function(t){ t.classList.remove('active'); });
            }
            if ( btn ) btn.classList.add('active');

            var lista = document.getElementById( uid + '-lista' );
            if ( ! lista ) return;
            lista.innerHTML = '<div class="psp-ranking-loading">⏳ Cargando...</div>';

            try {
              var r = await fetch( PSP_CONFIG.ajax_url, {
                method : 'POST',
                body   : new URLSearchParams({
                  action    : 'psp_ranking_get',
                  tipo      : tipo,
                  limite    : 20,
                  psp_nonce : PSP_CONFIG.nonce
                })
              });
              var d = await r.json();

              var ts = document.getElementById( uid + '-ts' );
              if ( ts ) ts.textContent = new Date().toLocaleTimeString('es-PA');

              if ( ! d.success || ! d.data || ! d.data.length ) {
                lista.innerHTML = '<p style="padding:20px;color:#888;text-align:center">¡Sé el primero en aparecer aquí! 🚀</p>';
                return;
              }

              var medals = ['🥇','🥈','🥉'];
              var html   = '';
              for ( var i = 0; i < d.data.length; i++ ) {
                var item    = d.data[i];
                var topCls  = i < 3 ? ' psp-rank-top-' + (i+1) : '';
                var pos     = i < 3 ? medals[i] : '#' + (i+1);
                var total   = Number( item.total || 0 ).toLocaleString('es-PA');
                var nombre  = item.nombre || '—';
                var barPct  = Math.min( ( item.total / ( d.data[0].total || 1 ) ) * 100, 100 );

                html += '<div class="psp-rank-row' + topCls + '">'
                      + '<span class="psp-rank-pos">'    + pos    + '</span>'
                      + '<span class="psp-rank-nombre">' + nombre + '</span>'
                      + '<span class="psp-rank-total">'  + total  + ' miembros</span>'
                      + '<div class="psp-rank-bar-wrap"><div class="psp-rank-bar" style="width:' + barPct + '%"></div></div>'
                      + '</div>';
              }
              lista.innerHTML = html;

            } catch(e) {
              lista.innerHTML = '<p style="color:#c00;padding:16px">Error cargando ranking.</p>';
            }
          }
        };
      }

      document.addEventListener('DOMContentLoaded', function() {
        PSPRanking.load( '<?php echo esc_js( $uid ); ?>', '<?php echo $tipo_esc; ?>' );
      });
    })();
    </script>

    <style>
    .psp-ranking-widget  { margin-bottom:24px; }
    .psp-ranking-titulo  { font-size:18px; font-weight:700; margin-bottom:16px; }
    .psp-ranking-lista   { min-height:100px; }
    .psp-ranking-loading { padding:32px; text-align:center; color:#888; }
    .psp-ranking-footer  { font-size:11px; color:#aaa; margin-top:12px; padding-top:10px; border-top:1px solid #e5e7eb; }
    .psp-rank-row        { display:flex; align-items:center; gap:10px; padding:10px 6px; border-bottom:1px solid #e5e7eb; flex-wrap:wrap; }
    .psp-rank-row:last-child { border-bottom:none; }
    .psp-rank-top-1      { background:rgba(239,159,39,.07); border-radius:8px; }
    .psp-rank-top-2      { background:rgba(148,163,184,.07); border-radius:8px; }
    .psp-rank-top-3      { background:rgba(205,127,50,.05); border-radius:8px; }
    .psp-rank-pos        { font-size:20px; width:36px; text-align:center; flex-shrink:0; }
    .psp-rank-nombre     { flex:1; font-weight:600; font-size:14px; min-width:80px; }
    .psp-rank-total      { font-size:13px; font-weight:700; color:#0B5E43; white-space:nowrap; }
    .psp-rank-bar-wrap   { width:100%; height:4px; background:#e5e7eb; border-radius:2px; overflow:hidden; }
    .psp-rank-bar        { height:100%; background:#0B5E43; border-radius:2px; transition:width .6s; }
    </style>
    <?php
    return ob_get_clean();
}

function psp_ranking_provincia_sc( $atts = [] ) {
    $atts['tipo'] = 'provincia';
    return psp_ranking_shortcode( $atts );
}
function psp_ranking_paises_sc( $atts = [] ) {
    $atts['tipo'] = 'pais';
    return psp_ranking_shortcode( $atts );
}
function psp_ranking_embajador_sc( $atts = [] ) {
    $atts['tipo'] = 'embajador';
    return psp_ranking_shortcode( $atts );
}

/**
 * Muestra la posición del usuario actual
 */
function psp_mi_posicion_shortcode( $atts = [] ) {
    ob_start();
    ?>
    <div id="psp-mi-posicion" class="psp-card" style="text-align:center">
      <div style="font-size:13px;color:#888;margin-bottom:4px">Tu posición en el ranking</div>
      <div id="psp-mi-pos-num"
           style="font-size:48px;font-weight:800;color:#0B5E43">—</div>
      <div style="display:flex;gap:20px;justify-content:center;margin-top:16px">
        <div>
          <div id="psp-mi-puntos"
               style="font-size:22px;font-weight:800;color:#EF9F27">0</div>
          <div style="font-size:11px;color:#888">Puntos</div>
        </div>
        <div>
          <div id="psp-mi-refs"
               style="font-size:22px;font-weight:800;color:#0B5E43">0</div>
          <div style="font-size:11px;color:#888">Referidos</div>
        </div>
        <div>
          <div id="psp-mi-nivel" style="font-size:22px">🌱</div>
          <div style="font-size:11px;color:#888">Nivel</div>
        </div>
      </div>
    </div>
    <script>
    (async function() {
      var jwt = (document.cookie.match(/psp_jwt=([^;]+)/) || [])[1];
      if ( ! jwt ) return;

      var r = await fetch( PSP_CONFIG.ajax_url, {
        method : 'POST',
        body   : new URLSearchParams({
          action    : 'psp_mi_posicion_ranking',
          psp_nonce : PSP_CONFIG.nonce,
          jwt       : jwt
        })
      });
      var d = await r.json();
      if ( ! d.success ) return;

      document.getElementById('psp-mi-pos-num').textContent =
        d.data.posicion ? '#' + d.data.posicion : '—';
      document.getElementById('psp-mi-puntos').textContent =
        Number( d.data.puntos || 0 ).toLocaleString('es-PA');
      document.getElementById('psp-mi-refs').textContent =
        d.data.referidos || 0;
      document.getElementById('psp-mi-nivel').textContent =
        d.data.nivel_icon || '🌱';
    })();
    </script>
    <?php
    return ob_get_clean();
}
