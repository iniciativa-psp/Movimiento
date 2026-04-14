<?php
/**
 * Plugin Name: PSP Dashboard 2
 * Plugin URI:  https://panamasinpobreza.org
 * Description: Dashboard público v2. KPIs de campaña y contador regresivo. Datos desde psp/v2/kpis. Requiere PSP Core 2.
 * Version:     2.0.0
 * Author:      PSP Dev Team
 * Requires PHP: 7.4
 * Text Domain: psp-dashboard2
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ── Aviso si PSP Core 2 no está activo ───────────────────────────────────────
add_action( 'admin_notices', 'psp2_dashboard_check_core' );
function psp2_dashboard_check_core(): void {
    if ( ! class_exists( 'PSP2_Supabase' ) ) {
        echo '<div class="notice notice-warning"><p><strong>PSP Dashboard 2:</strong> Requiere <strong>PSP Core 2</strong> activo.</p></div>';
    }
}

// ── Encolar assets front-end ─────────────────────────────────────────────────
add_action( 'wp_enqueue_scripts', 'psp2_dashboard_enqueue' );
function psp2_dashboard_enqueue(): void {
    wp_enqueue_style(
        'psp2-dashboard',
        plugin_dir_url( __FILE__ ) . 'assets/psp2-dashboard.css',
        [ 'psp2-global' ],
        '2.0.0'
    );
    wp_enqueue_script(
        'psp2-dashboard',
        plugin_dir_url( __FILE__ ) . 'assets/psp2-dashboard.js',
        [ 'psp2-global' ],
        '2.0.0',
        true
    );
}

// ── Shortcode [psp2_kpis] ────────────────────────────────────────────────────
add_shortcode( 'psp2_kpis', 'psp2_kpis_shortcode' );
function psp2_kpis_shortcode( array $atts = [] ): string {
    ob_start(); ?>
    <div class="psp2-kpis-wrap" id="psp2-kpis-container">
      <div class="psp2-kpi-card">
        <div class="psp2-kpi-label">Miembros Activos</div>
        <div class="psp2-kpi-value" id="psp2-kpi-activos">&#x23F3;</div>
      </div>
      <div class="psp2-kpi-card">
        <div class="psp2-kpi-label">Registros Pendientes</div>
        <div class="psp2-kpi-value" id="psp2-kpi-pendientes">&#x23F3;</div>
      </div>
      <div class="psp2-kpi-card">
        <div class="psp2-kpi-label">Meta Miembros</div>
        <div class="psp2-kpi-value" id="psp2-kpi-meta">&#x23F3;</div>
      </div>
    </div>
    <script>
    (function(){
      async function loadKpis() {
        try {
          var data = await PSP2.api('kpis');
          if (!data || !data.kpis) return;
          var k = data.kpis;
          var el = function(id){ return document.getElementById(id); };
          if (el('psp2-kpi-activos'))   el('psp2-kpi-activos').textContent   = (k.miembros_activos   || 0).toLocaleString();
          if (el('psp2-kpi-pendientes')) el('psp2-kpi-pendientes').textContent = (k.miembros_pendientes || 0).toLocaleString();
          if (el('psp2-kpi-meta'))      el('psp2-kpi-meta').textContent      = (data.meta_miembros   || 0).toLocaleString();
        } catch(e) {
          console.warn('[PSP2 KPIs]', e.message);
        }
      }
      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', loadKpis);
      } else {
        loadKpis();
      }
    })();
    </script>
    <?php
    return ob_get_clean();
}

// ── Shortcode [psp2_countdown] ───────────────────────────────────────────────
add_shortcode( 'psp2_countdown', 'psp2_countdown_shortcode' );
function psp2_countdown_shortcode( array $atts = [] ): string {
    $atts = shortcode_atts( [
        'target' => get_option( 'psp2_campaign_end', '2026-05-18T23:59:59' ),
        'label'  => 'Fin de campa&ntilde;a',
    ], $atts, 'psp2_countdown' );

    $target  = esc_js( $atts['target'] );
    $label   = esc_html( $atts['label'] );
    $uid     = 'psp2cd-' . wp_unique_id();

    ob_start(); ?>
    <div class="psp2-countdown-wrap" id="<?php echo esc_attr( $uid ); ?>">
      <p class="psp2-countdown-label"><?php echo $label; ?></p>
      <div class="psp2-countdown-digits">
        <div class="psp2-digit-box"><span class="psp2-digit" data-unit="d">00</span><span class="psp2-unit">D&iacute;as</span></div>
        <div class="psp2-digit-sep">:</div>
        <div class="psp2-digit-box"><span class="psp2-digit" data-unit="h">00</span><span class="psp2-unit">Horas</span></div>
        <div class="psp2-digit-sep">:</div>
        <div class="psp2-digit-box"><span class="psp2-digit" data-unit="m">00</span><span class="psp2-unit">Minutos</span></div>
        <div class="psp2-digit-sep">:</div>
        <div class="psp2-digit-box"><span class="psp2-digit" data-unit="s">00</span><span class="psp2-unit">Segundos</span></div>
      </div>
    </div>
    <script>
    (function(){
      var end = new Date('<?php echo $target; ?>').getTime();
      var wrap = document.getElementById('<?php echo esc_js( $uid ); ?>');
      if (!wrap || isNaN(end)) return;
      function pad(n){ return String(n).padStart(2,'0'); }
      function tick(){
        var diff = end - Date.now();
        if (diff <= 0) { wrap.querySelector('.psp2-countdown-digits').innerHTML = '<span style="font-size:22px;color:#0B5E43;font-weight:700">&#x2705; Campa&ntilde;a finalizada</span>'; return; }
        var d = Math.floor(diff/864e5),
            h = Math.floor(diff%864e5/36e5),
            m = Math.floor(diff%36e5/6e4),
            s = Math.floor(diff%6e4/1e3);
        var map = {d:d,h:h,m:m,s:s};
        Object.keys(map).forEach(function(u){
          var el = wrap.querySelector('.psp2-digit[data-unit='+u+']');
          if (el) el.textContent = pad(map[u]);
        });
        setTimeout(tick, 1000);
      }
      tick();
    })();
    </script>
    <?php
    return ob_get_clean();
}
