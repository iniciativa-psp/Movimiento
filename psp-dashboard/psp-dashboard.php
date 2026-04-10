<?php
/**
 * Plugin Name: PSP Dashboard
 * Description: Dashboard en tiempo real con KPIs, ranking territorial, mapa interactivo y gráficas tipo Power BI.
 * Version:     1.0.0
 */
if (!defined('ABSPATH')) exit;

add_shortcode('psp_dashboard_publico', 'psp_dashboard_publico_shortcode');
add_shortcode('psp_dashboard_admin',   'psp_dashboard_admin_shortcode');
add_shortcode('psp_termometro',        'psp_termometro_shortcode');
add_shortcode('psp_ranking',           'psp_ranking_shortcode');
add_shortcode('psp_mapa',              'psp_mapa_shortcode');

add_action('wp_enqueue_scripts', 'psp_dashboard_enqueue');
function psp_dashboard_enqueue(): void {
    wp_enqueue_script('chart-js', 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js', [], null, true);
    wp_enqueue_script('leaflet',  'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', [], null, true);
    wp_enqueue_style('leaflet',   'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css');
    wp_enqueue_script('psp-dash', plugin_dir_url(__FILE__) . 'assets/dashboard.js', ['chart-js','leaflet'], PSP_VERSION, true);
}

function psp_dashboard_publico_shortcode(): string {
    ob_start(); ?>
    <div id="psp-dashboard" class="psp-dashboard-wrap">

      <!-- KPI Cards -->
      <div class="psp-kpi-grid">
        <div class="psp-kpi-card psp-kpi-miembros">
          <div class="psp-kpi-icon">👥</div>
          <div class="psp-kpi-valor" id="kpi-miembros">—</div>
          <div class="psp-kpi-label">Miembros</div>
          <div class="psp-kpi-prog"><div class="psp-prog-bar" id="prog-miembros"></div></div>
          <div class="psp-kpi-meta">Meta: 1,000,000</div>
        </div>
        <div class="psp-kpi-card psp-kpi-recaudado">
          <div class="psp-kpi-icon">💰</div>
          <div class="psp-kpi-valor" id="kpi-recaudado">—</div>
          <div class="psp-kpi-label">Recaudado</div>
          <div class="psp-kpi-prog"><div class="psp-prog-bar" id="prog-recaudado"></div></div>
          <div class="psp-kpi-meta">Meta: $1,000,000</div>
        </div>
        <div class="psp-kpi-card psp-kpi-referidos">
          <div class="psp-kpi-icon">🔗</div>
          <div class="psp-kpi-valor" id="kpi-referidos">—</div>
          <div class="psp-kpi-label">Referidos activos</div>
        </div>
        <div class="psp-kpi-card psp-kpi-hoy">
          <div class="psp-kpi-icon">📈</div>
          <div class="psp-kpi-valor" id="kpi-hoy">—</div>
          <div class="psp-kpi-label">Nuevos hoy</div>
        </div>
        <div class="psp-kpi-card psp-kpi-provincias">
          <div class="psp-kpi-icon">🗺️</div>
          <div class="psp-kpi-valor" id="kpi-provincias">—</div>
          <div class="psp-kpi-label">Provincias activas</div>
        </div>
        <div class="psp-kpi-card psp-kpi-paises">
          <div class="psp-kpi-icon">🌎</div>
          <div class="psp-kpi-valor" id="kpi-paises">—</div>
          <div class="psp-kpi-label">Países</div>
        </div>
      </div>

      <!-- Countdown -->
      <div class="psp-countdown-bar">
        <span>⏳ Tiempo restante:</span>
        <span class="psp-cd-unit"><b id="cd-d">--</b> días</span>
        <span class="psp-cd-unit"><b id="cd-h">--</b> h</span>
        <span class="psp-cd-unit"><b id="cd-m">--</b> min</span>
        <span class="psp-cd-unit"><b id="cd-s">--</b> seg</span>
      </div>

      <!-- Charts Row -->
      <div class="psp-charts-row">
        <div class="psp-chart-box">
          <h4>Crecimiento diario</h4>
          <canvas id="chart-crecimiento" height="200"></canvas>
        </div>
        <div class="psp-chart-box">
          <h4>Distribución por tipo</h4>
          <canvas id="chart-tipos" height="200"></canvas>
        </div>
        <div class="psp-chart-box">
          <h4>Recaudación acumulada</h4>
          <canvas id="chart-recaudacion" height="200"></canvas>
        </div>
      </div>

      <!-- Mapa -->
      <div class="psp-map-box">
        <h4>Mapa del Movimiento</h4>
        <div id="psp-mapa-leaflet" style="height:400px;border-radius:12px"></div>
      </div>

      <!-- Ranking -->
      <div class="psp-ranking-wrap">
        <h4>🏆 Ranking en tiempo real</h4>
        <div class="psp-ranking-tabs">
          <button class="psp-rtab active" onclick="PSPDash.loadRanking('provincia',this)">Provincias</button>
          <button class="psp-rtab" onclick="PSPDash.loadRanking('pais',this)">Países</button>
          <button class="psp-rtab" onclick="PSPDash.loadRanking('embajador',this)">Embajadores</button>
        </div>
        <div id="psp-ranking-list"></div>
      </div>

    </div>

    <script>
    const PSPDash = {
      supabase: null,
      charts: {},

      init() {
        this.supabase = window.supabase?.createClient(PSP_CONFIG.supabase_url, PSP_CONFIG.supabase_key);
        this.loadKPIs();
        this.loadRanking('provincia');
        this.initCountdown();
        this.initMapa();
        this.subscribeRealtime();
        setInterval(() => this.loadKPIs(), 30000);
      },

      async loadKPIs() {
        try {
          const r = await fetch(PSP_CONFIG.ajax_url, {
            method:'POST',
            body: new URLSearchParams({action:'psp_get_kpis', psp_nonce: PSP_CONFIG.nonce})
          });
          const d = await r.json();
          if (!d.success) return;
          const k = d.data;

          document.getElementById('kpi-miembros').textContent  = k.total_miembros.toLocaleString();
          document.getElementById('kpi-recaudado').textContent = '$' + k.total_recaudado.toLocaleString();
          document.getElementById('kpi-referidos').textContent = k.total_referidos.toLocaleString();
          document.getElementById('kpi-hoy').textContent       = k.nuevos_hoy.toLocaleString();
          document.getElementById('kpi-provincias').textContent= k.provincias_activas;
          document.getElementById('kpi-paises').textContent    = k.paises_activos;

          const pm = Math.min((k.total_miembros / 1000000) * 100, 100);
          const pr = Math.min((k.total_recaudado / 1000000) * 100, 100);
          document.getElementById('prog-miembros').style.width  = pm + '%';
          document.getElementById('prog-recaudado').style.width = pr + '%';

          this.updateCharts(k);
        } catch(e) { console.error('KPIs error:', e); }
      },

      updateCharts(k) {
        if (k.crecimiento_diario) {
          const labels = k.crecimiento_diario.map(r=>r.fecha);
          const vals   = k.crecimiento_diario.map(r=>r.total);
          if (this.charts.crec) {
            this.charts.crec.data.labels = labels;
            this.charts.crec.data.datasets[0].data = vals;
            this.charts.crec.update();
          } else {
            this.charts.crec = new Chart(document.getElementById('chart-crecimiento'), {
              type: 'line',
              data: {labels, datasets:[{data:vals, borderColor:'#0B5E43', backgroundColor:'rgba(11,94,67,.08)', fill:true, tension:.4, pointRadius:3}]},
              options: {plugins:{legend:{display:false}}, scales:{x:{ticks:{maxTicksLimit:7}}, y:{beginAtZero:true}}, responsive:true, maintainAspectRatio:false}
            });
          }
        }
        if (k.por_tipo) {
          const tipos = Object.keys(k.por_tipo);
          const vals  = Object.values(k.por_tipo);
          if (!this.charts.tipo) {
            this.charts.tipo = new Chart(document.getElementById('chart-tipos'), {
              type: 'doughnut',
              data: {labels: tipos, datasets:[{data: vals, backgroundColor:['#0B5E43','#1D9E75','#0C447C','#EF9F27','#C9381A','#9FE1CB','#B5D4F4']}]},
              options: {plugins:{legend:{position:'bottom'}}, responsive:true, maintainAspectRatio:false}
            });
          }
        }
      },

      async loadRanking(tipo, btn) {
        document.querySelectorAll('.psp-rtab').forEach(t=>t.classList.remove('active'));
        if (btn) btn.classList.add('active');
        const r = await fetch(PSP_CONFIG.ajax_url, {
          method:'POST',
          body: new URLSearchParams({action:'psp_get_ranking', tipo, psp_nonce: PSP_CONFIG.nonce})
        });
        const d = await r.json();
        const lista = document.getElementById('psp-ranking-list');
        if (!d.success || !d.data?.length) { lista.innerHTML = '<p>Sin datos aún</p>'; return; }
        lista.innerHTML = d.data.map((item,i) => `
          <div class="psp-rank-item ${i<3?'psp-rank-top':''}">
            <span class="psp-rank-pos">${i===0?'🥇':i===1?'🥈':i===2?'🥉':'#'+(i+1)}</span>
            <span class="psp-rank-nombre">${item.nombre}</span>
            <span class="psp-rank-val">${item.total.toLocaleString()} miembros</span>
          </div>`).join('');
      },

      initCountdown() {
        const fn = () => {
          const t = new Date(PSP_CONFIG.launch_date).getTime() - Date.now();
          if (t < 0) return;
          document.getElementById('cd-d').textContent = String(Math.floor(t/86400000)).padStart(2,'0');
          document.getElementById('cd-h').textContent = String(Math.floor(t%86400000/3600000)).padStart(2,'0');
          document.getElementById('cd-m').textContent = String(Math.floor(t%3600000/60000)).padStart(2,'0');
          document.getElementById('cd-s').textContent = String(Math.floor(t%60000/1000)).padStart(2,'0');
        };
        fn(); setInterval(fn, 1000);
      },

      initMapa() {
        const map = L.map('psp-mapa-leaflet').setView([8.9936, -79.5197], 7);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
          attribution: '© OpenStreetMap contributors'
        }).addTo(map);
        this.loadMapData(map);
      },

      async loadMapData(map) {
        const r = await fetch(PSP_CONFIG.ajax_url, {
          method:'POST',
          body: new URLSearchParams({action:'psp_get_mapa_data', psp_nonce: PSP_CONFIG.nonce})
        });
        const d = await r.json();
        if (!d.success) return;
        d.data.forEach(p => {
          if (!p.lat || !p.lng) return;
          L.circleMarker([p.lat, p.lng], {
            radius: Math.max(5, Math.log(p.total+1)*4),
            fillColor:'#0B5E43', color:'#fff', weight:2, fillOpacity:0.7
          }).bindPopup(`<b>${p.nombre}</b><br>${p.total} miembros`).addTo(map);
        });
      },

      subscribeRealtime() {
        if (!this.supabase) return;
        this.supabase.channel('public:miembros')
          .on('postgres_changes', {event:'INSERT', schema:'public', table:'miembros'}, () => {
            this.loadKPIs();
          }).subscribe();
      }
    };
    document.addEventListener('DOMContentLoaded', () => PSPDash.init());
    </script>
    <?php
    return ob_get_clean();
}

// ── AJAX handlers ────────────────────────────────────────────────────────────
add_action('wp_ajax_psp_get_kpis',        'psp_ajax_get_kpis');
add_action('wp_ajax_nopriv_psp_get_kpis', 'psp_ajax_get_kpis');
function psp_ajax_get_kpis(): void {
    $cached = get_transient('psp_kpis');
    if ($cached) { wp_send_json_success($cached); return; }

    $stats = PSP_Supabase::rpc('get_dashboard_kpis', ['p_tenant_id' => get_option('psp_tenant_id','panama')]);

    if (!$stats) {
        // Fallback directo
        $miembros   = PSP_Supabase::select('miembros', ['select' => 'count', 'estado' => 'eq.activo']);
        $recaudado  = PSP_Supabase::select('pagos',    ['select' => 'monto.sum()', 'estado' => 'eq.completado']);
        $stats = [
            'total_miembros'   => $miembros[0]['count']  ?? 0,
            'total_recaudado'  => $recaudado[0]['sum']   ?? 0,
            'total_referidos'  => 0,
            'nuevos_hoy'       => 0,
            'provincias_activas'=> 0,
            'paises_activos'   => 0,
            'crecimiento_diario'=> [],
            'por_tipo'         => [],
        ];
    }

    set_transient('psp_kpis', $stats, 30);
    wp_send_json_success($stats);
}

add_action('wp_ajax_psp_get_ranking',        'psp_ajax_get_ranking');
add_action('wp_ajax_nopriv_psp_get_ranking', 'psp_ajax_get_ranking');
function psp_ajax_get_ranking(): void {
    $tipo = sanitize_text_field($_POST['tipo'] ?? 'provincia');
    $data = PSP_Supabase::rpc('get_ranking', ['p_tipo' => $tipo, 'p_limit' => 20]);
    wp_send_json_success($data ?? []);
}

add_action('wp_ajax_psp_get_mapa_data',        'psp_ajax_get_mapa_data');
add_action('wp_ajax_nopriv_psp_get_mapa_data', 'psp_ajax_get_mapa_data');
function psp_ajax_get_mapa_data(): void {
    $data = PSP_Supabase::rpc('get_mapa_puntos', ['p_tenant_id' => get_option('psp_tenant_id','panama')]);
    wp_send_json_success($data ?? []);
}

function psp_termometro_shortcode(): string {
    return '<div class="psp-termometro-wrap">
      <div class="psp-termometro-container">
        <div class="psp-termometro-bulb"></div>
        <div class="psp-termometro-tubo">
          <div class="psp-termometro-fill" id="termo-fill" style="height:0%"></div>
        </div>
      </div>
      <div class="psp-termometro-labels">
        <div class="psp-termo-val" id="termo-val">0</div>
        <div>de 1,000,000 miembros</div>
        <div class="psp-termo-pct" id="termo-pct">0%</div>
      </div>
    </div>
    <script>
    fetch(PSP_CONFIG.ajax_url,{method:"POST",body:new URLSearchParams({action:"psp_get_kpis",psp_nonce:PSP_CONFIG.nonce})})
    .then(r=>r.json()).then(d=>{
      const v = d.data?.total_miembros||0, pct=Math.min(v/1000000*100,100);
      document.getElementById("termo-fill").style.height=pct+"%";
      document.getElementById("termo-val").textContent=v.toLocaleString();
      document.getElementById("termo-pct").textContent=pct.toFixed(2)+"%";
    });
    </script>';
}

function psp_ranking_shortcode(array $atts): string {
    $atts = shortcode_atts(['tipo'=>'provincia','limite'=>10], $atts);
    return '<div id="psp-ranking-widget" data-tipo="' . esc_attr($atts['tipo']) . '" data-limite="' . esc_attr($atts['limite']) . '">
      <div id="psp-ranking-widget-list">Cargando ranking...</div>
    </div>
    <script>
    (async()=>{
      const el = document.getElementById("psp-ranking-widget");
      const r = await fetch(PSP_CONFIG.ajax_url,{method:"POST",body:new URLSearchParams({action:"psp_get_ranking",tipo:el.dataset.tipo,psp_nonce:PSP_CONFIG.nonce})});
      const d = await r.json();
      const list = document.getElementById("psp-ranking-widget-list");
      if(!d.data?.length){list.textContent="Sin datos"; return;}
      list.innerHTML = d.data.slice(0,parseInt(el.dataset.limite)).map((item,i)=>`
        <div class="psp-rank-item">${i<3?"🥇🥈🥉"[i]:"#"+(i+1)} <b>${item.nombre}</b> — ${item.total.toLocaleString()}</div>`).join("");
    })();
    </script>';
}

function psp_mapa_shortcode(): string {
    static $n = 0; $n++;
    return '<div id="psp-mapa-'.$n.'" style="height:400px;border-radius:12px"></div>
    <script>
    document.addEventListener("DOMContentLoaded",()=>{
      const map'.$n.' = L.map("psp-mapa-'.$n.'").setView([8.9936,-79.5197],7);
      L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png",{attribution:"© OSM"}).addTo(map'.$n.');
      fetch(PSP_CONFIG.ajax_url,{method:"POST",body:new URLSearchParams({action:"psp_get_mapa_data",psp_nonce:PSP_CONFIG.nonce})})
      .then(r=>r.json()).then(d=>{d.data?.forEach(p=>{
        if(!p.lat||!p.lng)return;
        L.circleMarker([p.lat,p.lng],{radius:Math.max(5,Math.log((p.total||1)+1)*4),fillColor:"#0B5E43",color:"#fff",weight:2,fillOpacity:0.7}).bindPopup("<b>"+p.nombre+"</b><br>"+(p.total||0)+" miembros").addTo(map'.$n.');
      });});
    });
    </script>';
}
