<?php
/**
 * Plugin Name: PSP Dashboard
 * Plugin URI:  https://panamasinpobreza.org
 * Description: Dashboard en tiempo real con KPIs, ranking territorial, mapa interactivo y gráficas. Incluye termómetro de progreso y countdown al lanzamiento.
 * Version:     1.0.2
 * Author:      PSP Dev Team
 * Requires PHP: 7.4
 * Text Domain: psp-dashboard
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// ── Aviso dependencia ─────────────────────────────────────────────────────────
add_action( 'admin_notices', 'psp_dashboard_check_core' );
function psp_dashboard_check_core() {
    if ( ! class_exists( 'PSP_Supabase' ) ) {
        echo '<div class="notice notice-error"><p>'
           . '<strong>PSP Dashboard:</strong> Requiere que <strong>PSP Core</strong> est&eacute; activado primero.</p></div>';
    }
}

// ── Encolar scripts (solo en front-end) ──────────────────────────────────────
add_action( 'wp_enqueue_scripts', 'psp_dashboard_enqueue' );
function psp_dashboard_enqueue() {
    wp_enqueue_script(
        'chartjs',
        'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js',
        [],
        '4.4.1',
        true
    );
    wp_enqueue_script(
        'leafletjs',
        'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
        [],
        '1.9.4',
        true
    );
    wp_enqueue_style(
        'leafletcss',
        'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
        [],
        '1.9.4'
    );
}

// ── Shortcodes ────────────────────────────────────────────────────────────────
add_shortcode( 'psp_dashboard_publico', 'psp_dashboard_publico_sc' );
add_shortcode( 'psp_dashboard_admin',   'psp_dashboard_admin_sc' );
add_shortcode( 'psp_termometro',        'psp_termometro_sc' );
add_shortcode( 'psp_mapa',              'psp_mapa_sc' );
add_shortcode( 'psp_countdown',         'psp_countdown_sc' );

// ── Dashboard público completo ────────────────────────────────────────────────
function psp_dashboard_publico_sc( $atts = [] ) {
    static $n = 0; $n++;
    ob_start();
    ?>
    <div id="psp-dash-<?php echo $n; ?>" class="psp-dashboard-wrap">

      <!-- COUNTDOWN -->
      <div class="psp-countdown-bar" id="psp-cd-bar-<?php echo $n; ?>">
        <span>&#x23F3; Tiempo al lanzamiento:</span>
        <span class="psp-cd-unit"><b class="psp-cd-d">--</b> d&iacute;as</span>
        <span class="psp-cd-unit"><b class="psp-cd-h">--</b> h</span>
        <span class="psp-cd-unit"><b class="psp-cd-m">--</b> min</span>
        <span class="psp-cd-unit"><b class="psp-cd-s">--</b> seg</span>
      </div>

      <!-- KPIs -->
      <div class="psp-kpi-grid">
        <div class="psp-kpi-card">
          <div class="psp-kpi-icon">&#x1F465;</div>
          <div class="psp-kpi-valor" id="kpi-miembros-<?php echo $n; ?>">&#x2014;</div>
          <div class="psp-kpi-label">Miembros</div>
          <div class="psp-kpi-prog"><div class="psp-prog-bar" id="prog-m-<?php echo $n; ?>"></div></div>
          <div class="psp-kpi-meta">Meta: 1,000,000</div>
        </div>
        <div class="psp-kpi-card">
          <div class="psp-kpi-icon">&#x1F4B0;</div>
          <div class="psp-kpi-valor" id="kpi-recaudado-<?php echo $n; ?>">&#x2014;</div>
          <div class="psp-kpi-label">Recaudado</div>
          <div class="psp-kpi-prog"><div class="psp-prog-bar" id="prog-r-<?php echo $n; ?>"></div></div>
          <div class="psp-kpi-meta">Meta: $1,000,000</div>
        </div>
        <div class="psp-kpi-card">
          <div class="psp-kpi-icon">&#x1F517;</div>
          <div class="psp-kpi-valor" id="kpi-referidos-<?php echo $n; ?>">&#x2014;</div>
          <div class="psp-kpi-label">Referidos activos</div>
        </div>
        <div class="psp-kpi-card">
          <div class="psp-kpi-icon">&#x1F4C8;</div>
          <div class="psp-kpi-valor" id="kpi-hoy-<?php echo $n; ?>">&#x2014;</div>
          <div class="psp-kpi-label">Nuevos hoy</div>
        </div>
        <div class="psp-kpi-card">
          <div class="psp-kpi-icon">&#x1F5FA;&#xFE0F;</div>
          <div class="psp-kpi-valor" id="kpi-provincias-<?php echo $n; ?>">&#x2014;</div>
          <div class="psp-kpi-label">Provincias activas</div>
        </div>
        <div class="psp-kpi-card">
          <div class="psp-kpi-icon">&#x1F30E;</div>
          <div class="psp-kpi-valor" id="kpi-paises-<?php echo $n; ?>">&#x2014;</div>
          <div class="psp-kpi-label">Pa&iacute;ses</div>
        </div>
      </div>

      <!-- GRÁFICAS -->
      <div class="psp-charts-row">
        <div class="psp-chart-box">
          <h4>Crecimiento diario</h4>
          <canvas id="chart-crec-<?php echo $n; ?>" height="200"></canvas>
        </div>
        <div class="psp-chart-box">
          <h4>Distribuci&oacute;n por tipo</h4>
          <canvas id="chart-tipo-<?php echo $n; ?>" height="200"></canvas>
        </div>
        <div class="psp-chart-box">
          <h4>Recaudaci&oacute;n acumulada</h4>
          <canvas id="chart-rec-<?php echo $n; ?>"  height="200"></canvas>
        </div>
      </div>

      <!-- MAPA -->
      <div class="psp-map-box">
        <h4>&#x1F5FA;&#xFE0F; Mapa del Movimiento</h4>
        <div id="psp-map-<?php echo $n; ?>" style="height:380px;border-radius:10px"></div>
      </div>

      <!-- RANKING -->
      <div class="psp-ranking-wrap">
        <h4>&#x1F3C6; Ranking en tiempo real</h4>
        <div class="psp-ranking-tabs">
          <button class="psp-rtab active"
                  onclick="PSPDashboard.ranking('<?php echo $n; ?>','provincia',this)">
            Provincias
          </button>
          <button class="psp-rtab"
                  onclick="PSPDashboard.ranking('<?php echo $n; ?>','pais',this)">
            Pa&iacute;ses
          </button>
          <button class="psp-rtab"
                  onclick="PSPDashboard.ranking('<?php echo $n; ?>','embajador',this)">
            Embajadores
          </button>
        </div>
        <div id="psp-rank-lista-<?php echo $n; ?>">
          <p style="color:#888;padding:16px">Cargando ranking...</p>
        </div>
      </div>

    </div><!-- /#psp-dash -->

    <script>
    (function() {
      var UID    = '<?php echo esc_js( (string) $n ); ?>';
      var launch = '<?php echo esc_js( get_option('psp_launch_date','2026-05-12T09:00:00') ); ?>';
      var charts = {};

      /* ── COUNTDOWN ───────────────────────────────────── */
      function tick() {
        var t = new Date(launch).getTime() - Date.now();
        if (t < 0) return;
        var bar = document.getElementById('psp-cd-bar-' + UID);
        if (!bar) return;
        bar.querySelector('.psp-cd-d').textContent = String(Math.floor(t/86400000)).padStart(2,'0');
        bar.querySelector('.psp-cd-h').textContent = String(Math.floor(t%86400000/3600000)).padStart(2,'0');
        bar.querySelector('.psp-cd-m').textContent = String(Math.floor(t%3600000/60000)).padStart(2,'0');
        bar.querySelector('.psp-cd-s').textContent = String(Math.floor(t%60000/1000)).padStart(2,'0');
      }
      tick(); setInterval(tick, 1000);

      /* ── KPIs ────────────────────────────────────────── */
      async function loadKPIs() {
        try {
          var r = await fetch(PSP_CONFIG.ajax_url, {
            method : 'POST',
            body   : new URLSearchParams({action:'psp_dash_kpis', psp_nonce:PSP_CONFIG.nonce})
          });
          var d = await r.json();
          if (!d.success) return;
          var k = d.data;

          setText('kpi-miembros-'  + UID, Number(k.total_miembros  ||0).toLocaleString('es-PA'));
          setText('kpi-recaudado-' + UID, '$' + Number(k.total_recaudado||0).toLocaleString('es-PA'));
          setText('kpi-referidos-' + UID, Number(k.total_referidos ||0).toLocaleString('es-PA'));
          setText('kpi-hoy-'       + UID, Number(k.nuevos_hoy      ||0).toLocaleString('es-PA'));
          setText('kpi-provincias-'+ UID, k.provincias_activas || 0);
          setText('kpi-paises-'    + UID, k.paises_activos     || 0);

          var pm = Math.min(((k.total_miembros  ||0)/1000000)*100,100);
          var pr = Math.min(((k.total_recaudado ||0)/1000000)*100,100);
          setWidth('prog-m-'+UID, pm); setWidth('prog-r-'+UID, pr);

          buildCharts(k);
        } catch(e) { console.warn('PSP Dashboard KPI error:', e); }
      }

      function setText(id, val) {
        var el = document.getElementById(id); if (el) el.textContent = val;
      }
      function setWidth(id, pct) {
        var el = document.getElementById(id); if (el) el.style.width = pct + '%';
      }

      /* ── CHARTS ──────────────────────────────────────── */
      function buildCharts(k) {
        if (typeof Chart === 'undefined') return;
        var baseOpts = { responsive:true, maintainAspectRatio:false,
                         plugins:{legend:{display:false}} };

        if (k.crecimiento_diario && k.crecimiento_diario.length) {
          var labels = k.crecimiento_diario.map(function(r){ return r.fecha; });
          var vals   = k.crecimiento_diario.map(function(r){ return r.total; });
          var cId    = 'chart-crec-' + UID;
          if (charts[cId]) { charts[cId].data.datasets[0].data=vals; charts[cId].update(); }
          else {
            charts[cId] = new Chart(document.getElementById(cId), {
              type:'line',
              data:{ labels:labels, datasets:[{data:vals, borderColor:'#0B5E43',
                     backgroundColor:'rgba(11,94,67,.06)', fill:true, tension:.4, pointRadius:2}] },
              options: baseOpts
            });
          }
        }

        if (k.por_tipo) {
          var tipos = Object.keys(k.por_tipo);
          var valsT = Object.values(k.por_tipo);
          var tId   = 'chart-tipo-' + UID;
          var COLORS = ['#0B5E43','#1D9E75','#0C447C','#EF9F27','#C9381A','#9FE1CB','#B5D4F4','#F59E0B','#8B5CF6','#EC4899'];
          if (!charts[tId] && tipos.length) {
            charts[tId] = new Chart(document.getElementById(tId), {
              type:'doughnut',
              data:{ labels:tipos, datasets:[{data:valsT, backgroundColor:COLORS}] },
              options:{ responsive:true, maintainAspectRatio:false, plugins:{legend:{position:'bottom',labels:{font:{size:10}}}} }
            });
          }
        }
      }

      /* ── RANKING ─────────────────────────────────────── */
      window.PSPDashboard = window.PSPDashboard || {};
      PSPDashboard.ranking = async function(uid, tipo, btn) {
        var wrap = document.getElementById('psp-dash-' + uid);
        if (wrap) wrap.querySelectorAll('.psp-rtab').forEach(function(t){t.classList.remove('active');});
        if (btn) btn.classList.add('active');
        var lista = document.getElementById('psp-rank-lista-' + uid);
        if (!lista) return;
        lista.innerHTML = '<p style="color:#888;padding:12px">&#x23F3; Cargando...</p>';
        try {
          var r = await fetch(PSP_CONFIG.ajax_url, {
            method:'POST',
            body: new URLSearchParams({action:'psp_dash_ranking', tipo:tipo, psp_nonce:PSP_CONFIG.nonce})
          });
          var d = await r.json();
          if (!d.success || !d.data || !d.data.length) {
            lista.innerHTML='<p style="color:#888;padding:16px">Sin datos a&uacute;n. &#x1F680;</p>'; return;
          }
          var medals = ['&#x1F947;','&#x1F948;','&#x1F949;'];
          var html = '';
          for (var i=0;i<d.data.length;i++) {
            var item = d.data[i];
            var pos  = i < 3 ? medals[i] : '#'+(i+1);
            var bar  = Math.min((item.total / (d.data[0].total||1))*100,100);
            html += '<div class="psp-rank-item '+(i<3?'psp-rank-top':'')+'">'
              + '<span class="psp-rank-pos">'+pos+'</span>'
              + '<span class="psp-rank-nombre">'+(item.nombre||'&#x2014;')+'</span>'
              + '<span class="psp-rank-val">'+Number(item.total||0).toLocaleString('es-PA')+' miembros</span>'
              + '<div class="psp-rank-bar-wrap"><div class="psp-rank-bar" style="width:'+bar+'%"></div></div>'
              + '</div>';
          }
          lista.innerHTML = html;
        } catch(e) {
          lista.innerHTML = '<p style="color:#c00;padding:12px">Error cargando ranking.</p>';
        }
      };

      /* ── MAPA ────────────────────────────────────────── */
      function initMapa() {
        if (typeof L === 'undefined') { setTimeout(initMapa, 500); return; }
        var mapDiv = document.getElementById('psp-map-' + UID);
        if (!mapDiv || mapDiv._leaflet_id) return;
        var map = L.map('psp-map-' + UID).setView([8.4,  -80.1], 7);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
          {attribution:'&copy; OpenStreetMap'}).addTo(map);
        fetch(PSP_CONFIG.ajax_url, {
          method:'POST',
          body: new URLSearchParams({action:'psp_dash_mapa', psp_nonce:PSP_CONFIG.nonce})
        }).then(function(r){return r.json();}).then(function(d) {
          if (!d.success || !d.data) return;
          d.data.forEach(function(p) {
            if (!p.lat || !p.lng) return;
            L.circleMarker([parseFloat(p.lat), parseFloat(p.lng)], {
              radius: Math.max(6, Math.log((p.total||1)+1)*5),
              fillColor:'#0B5E43', color:'#fff', weight:2, fillOpacity:.75
            }).bindPopup('<strong>'+p.nombre+'</strong><br>'+(p.total||0)+' miembros').addTo(map);
          });
        }).catch(function(){});
      }

      /* ── INIT ────────────────────────────────────────── */
      document.addEventListener('DOMContentLoaded', function() {
        loadKPIs();
        PSPDashboard.ranking(UID, 'provincia');
        initMapa();
        setInterval(loadKPIs, 30000);
      });
    })();
    </script>
    <?php
    return ob_get_clean();
}

// ── Termómetro ────────────────────────────────────────────────────────────────
function psp_termometro_sc( $atts = [] ) {
    ob_start();
    ?>
    <div class="psp-termometro-wrap">
      <div class="psp-termometro-container">
        <div class="psp-termometro-bulb"></div>
        <div class="psp-termometro-tubo">
          <div class="psp-termometro-fill" id="psp-termo-fill" style="height:0%"></div>
        </div>
      </div>
      <div class="psp-termometro-labels">
        <div class="psp-termo-val" id="psp-termo-val">0</div>
        <div style="font-size:13px;color:#555">de 1,000,000 miembros</div>
        <div class="psp-termo-pct" id="psp-termo-pct">0%</div>
      </div>
    </div>
    <script>
    (function() {
      fetch(PSP_CONFIG.ajax_url, {
        method:'POST',
        body: new URLSearchParams({action:'psp_dash_kpis', psp_nonce:PSP_CONFIG.nonce})
      }).then(function(r){return r.json();}).then(function(d) {
        if (!d.success) return;
        var v   = parseInt(d.data.total_miembros || 0);
        var pct = Math.min((v/1000000)*100, 100);
        var fill = document.getElementById('psp-termo-fill');
        var val  = document.getElementById('psp-termo-val');
        var pp   = document.getElementById('psp-termo-pct');
        if (fill) fill.style.height = pct + '%';
        if (val)  val.textContent   = v.toLocaleString('es-PA');
        if (pp)   pp.textContent    = pct.toFixed(2) + '%';
      }).catch(function(){});
    })();
    </script>
    <?php
    return ob_get_clean();
}

// ── Mapa standalone ───────────────────────────────────────────────────────────
function psp_mapa_sc( $atts = [] ) {
    static $mn = 0; $mn++;
    $mid = 'psp-mapa-' . $mn;
    ob_start();
    ?>
    <div id="<?php echo esc_attr($mid); ?>" style="height:400px;border-radius:12px"></div>
    <script>
    (function() {
      function initMap() {
        if (typeof L === 'undefined') { setTimeout(initMap, 400); return; }
        var el = document.getElementById('<?php echo esc_js($mid); ?>');
        if (!el || el._leaflet_id) return;
        var map = L.map('<?php echo esc_js($mid); ?>').setView([8.4,-80.1],7);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
          {attribution:'&copy; OpenStreetMap'}).addTo(map);
        fetch(PSP_CONFIG.ajax_url,{
          method:'POST',
          body:new URLSearchParams({action:'psp_dash_mapa',psp_nonce:PSP_CONFIG.nonce})
        }).then(function(r){return r.json();}).then(function(d){
          if(!d.success||!d.data) return;
          d.data.forEach(function(p){
            if(!p.lat||!p.lng) return;
            L.circleMarker([parseFloat(p.lat),parseFloat(p.lng)],{
              radius:Math.max(6,Math.log((p.total||1)+1)*5),
              fillColor:'#0B5E43',color:'#fff',weight:2,fillOpacity:.75
            }).bindPopup('<b>'+p.nombre+'</b><br>'+(p.total||0)+' miembros').addTo(map);
          });
        }).catch(function(){});
      }
      document.addEventListener('DOMContentLoaded', initMap);
    })();
    </script>
    <?php
    return ob_get_clean();
}

// ── Countdown standalone ──────────────────────────────────────────────────────
function psp_countdown_sc( $atts = [] ) {
    $launch = get_option('psp_launch_date','2026-05-12T09:00:00');
    ob_start();
    ?>
    <div class="psp-countdown-bar" id="psp-standalone-cd">
      <span>&#x23F3;</span>
      <span class="psp-cd-unit"><b id="scd-d">--</b> d&iacute;as</span>
      <span class="psp-cd-unit"><b id="scd-h">--</b> h</span>
      <span class="psp-cd-unit"><b id="scd-m">--</b> min</span>
      <span class="psp-cd-unit"><b id="scd-s">--</b> seg</span>
    </div>
    <script>
    (function(){
      var L='<?php echo esc_js($launch); ?>';
      function tick(){
        var t=new Date(L).getTime()-Date.now(); if(t<0)return;
        document.getElementById('scd-d').textContent=String(Math.floor(t/86400000)).padStart(2,'0');
        document.getElementById('scd-h').textContent=String(Math.floor(t%86400000/3600000)).padStart(2,'0');
        document.getElementById('scd-m').textContent=String(Math.floor(t%3600000/60000)).padStart(2,'0');
        document.getElementById('scd-s').textContent=String(Math.floor(t%60000/1000)).padStart(2,'0');
      }
      tick(); setInterval(tick,1000);
    })();
    </script>
    <?php
    return ob_get_clean();
}

// ── Dashboard admin (panel WP) ────────────────────────────────────────────────
function psp_dashboard_admin_sc( $atts = [] ) {
    if ( ! current_user_can('manage_options') ) return '<p>Sin permisos.</p>';
    return psp_dashboard_publico_sc( $atts );
}

// ── AJAX: KPIs ────────────────────────────────────────────────────────────────
add_action( 'wp_ajax_psp_dash_kpis',        'psp_ajax_dash_kpis' );
add_action( 'wp_ajax_nopriv_psp_dash_kpis', 'psp_ajax_dash_kpis' );
function psp_ajax_dash_kpis() {
    if ( ! psp_verify_nonce() ) wp_send_json_error();

    $cached = get_transient('psp_dash_kpis');
    if ( $cached !== false ) { wp_send_json_success($cached); return; }

    if ( ! class_exists('PSP_Supabase') ) {
        wp_send_json_success([
            'total_miembros'=>0,'total_recaudado'=>0,'total_referidos'=>0,
            'nuevos_hoy'=>0,'provincias_activas'=>0,'paises_activos'=>0,
            'crecimiento_diario'=>[],'por_tipo'=>[],
        ]);
        return;
    }

    // Intentar RPC
    $stats = PSP_Supabase::rpc('get_dashboard_kpis', ['p_tenant_id'=>get_option('psp_tenant_id','panama')]);

    if ( ! $stats ) {
        // Fallback directo
        $mrows = PSP_Supabase::select('miembros', ['select'=>'tipo_miembro,pais_id,provincia_id,created_at','limit'=>99999]) ?? [];
        $prows = PSP_Supabase::select('pagos',    ['select'=>'monto','estado'=>'eq.completado','limit'=>99999]) ?? [];

        $total_m = count( array_filter($mrows, function($r){ return ($r['estado']??'')!=='baja'; }) );
        $total_r = array_sum( array_column($prows,'monto') );
        $hoy     = date('Y-m-d');
        $hoy_c   = count( array_filter($mrows, function($r) use ($hoy){ return substr($r['created_at']??'',0,10)===$hoy; }) );
        $provs   = count( array_unique( array_filter( array_column($mrows,'provincia_id') ) ) );
        $paises  = count( array_unique( array_filter( array_column($mrows,'pais_id') ) ) );
        $por_tipo= array_count_values( array_column($mrows,'tipo_miembro') );

        // Crecimiento últimos 14 días
        $crec = [];
        for ($i=13;$i>=0;$i--) {
            $d = date('Y-m-d', strtotime("-{$i} days"));
            $crec[] = ['fecha'=>$d,'total'=>count(array_filter($mrows,function($r) use($d){ return substr($r['created_at']??'',0,10)===$d; }))];
        }

        $stats = [
            'total_miembros'    => $total_m,
            'total_recaudado'   => $total_r,
            'total_referidos'   => 0,
            'nuevos_hoy'        => $hoy_c,
            'provincias_activas'=> $provs,
            'paises_activos'    => $paises,
            'crecimiento_diario'=> $crec,
            'por_tipo'          => $por_tipo,
        ];
    }

    set_transient('psp_dash_kpis', $stats, 30);
    wp_send_json_success($stats);
}

// ── AJAX: Ranking ──────────────────────────────────────────────────────────────
add_action( 'wp_ajax_psp_dash_ranking',        'psp_ajax_dash_ranking' );
add_action( 'wp_ajax_nopriv_psp_dash_ranking', 'psp_ajax_dash_ranking' );
function psp_ajax_dash_ranking() {
    if ( ! psp_verify_nonce() ) wp_send_json_error();
    $tipo = sanitize_text_field($_POST['tipo'] ?? 'provincia');

    $cached = get_transient('psp_rank_'.$tipo);
    if ( $cached !== false ) { wp_send_json_success($cached); return; }

    $data = null;
    if ( class_exists('PSP_Supabase') ) {
        $data = PSP_Supabase::rpc('get_ranking', ['p_tipo'=>$tipo,'p_limit'=>20]);
    }

    set_transient('psp_rank_'.$tipo, $data ?? [], 60);
    wp_send_json_success($data ?? []);
}

// ── AJAX: Mapa ────────────────────────────────────────────────────────────────
add_action( 'wp_ajax_psp_dash_mapa',        'psp_ajax_dash_mapa' );
add_action( 'wp_ajax_nopriv_psp_dash_mapa', 'psp_ajax_dash_mapa' );
function psp_ajax_dash_mapa() {
    if ( ! psp_verify_nonce() ) wp_send_json_error();

    $cached = get_transient('psp_mapa_puntos');
    if ( $cached !== false ) { wp_send_json_success($cached); return; }

    $data = null;
    if ( class_exists('PSP_Supabase') ) {
        $data = PSP_Supabase::rpc('get_mapa_puntos', ['p_tenant_id'=>get_option('psp_tenant_id','panama')]);
    }

    // Si no hay datos aún, devolver puntos de provincias de Panamá con coordenadas hardcoded
    if ( ! $data ) {
        $data = [
            ['nombre'=>'Panam&aacute;',     'lat'=>8.9943,  'lng'=>-79.5188, 'total'=>0],
            ['nombre'=>'Col&oacute;n',       'lat'=>9.3588,  'lng'=>-79.9007, 'total'=>0],
            ['nombre'=>'Chiriqu&iacute;',    'lat'=>8.4277,  'lng'=>-82.4308, 'total'=>0],
            ['nombre'=>'Cocl&eacute;',       'lat'=>8.5272,  'lng'=>-80.3500, 'total'=>0],
            ['nombre'=>'Herrera',           'lat'=>7.7761,  'lng'=>-80.7229, 'total'=>0],
            ['nombre'=>'Los Santos',        'lat'=>7.9329,  'lng'=>-80.4138, 'total'=>0],
            ['nombre'=>'Veraguas',          'lat'=>8.1000,  'lng'=>-81.0836, 'total'=>0],
            ['nombre'=>'Bocas del Toro',    'lat'=>9.3338,  'lng'=>-82.4049, 'total'=>0],
            ['nombre'=>'Dari&eacute;n',      'lat'=>7.7383,  'lng'=>-77.8938, 'total'=>0],
            ['nombre'=>'Ember&aacute;',      'lat'=>8.0786,  'lng'=>-77.3500, 'total'=>0],
            ['nombre'=>'Kuna Yala',         'lat'=>9.2307,  'lng'=>-78.0000, 'total'=>0],
            ['nombre'=>'Ng&auml;be-Bugl&eacute;','lat'=>8.3000,'lng'=>-81.9000,'total'=>0],
        ];
    }

    set_transient('psp_mapa_puntos', $data, 120);
    wp_send_json_success($data);
}
