<?php
/**
 * Plugin Name: PSP Referidos
 * Plugin URI:  https://panamasinpobreza.org
 * Description: Sistema de referidos multinivel con códigos únicos, puntos, niveles y gamificación.
 * Version:     1.0.2
 * Author:      PSP Dev Team
 * Requires PHP: 7.4
 * Text Domain: psp-referidos
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_notices', 'psp_referidos_check_core' );
function psp_referidos_check_core() {
    if ( ! class_exists( 'PSP_Supabase' ) )
        echo '<div class="notice notice-error"><p><strong>PSP Referidos:</strong> Requiere <strong>PSP Core</strong> activo.</p></div>';
}

add_action( 'plugins_loaded', 'psp_referidos_init' );
function psp_referidos_init() {
add_shortcode('psp_mis_referidos', 'psp_mis_referidos_shortcode');

// Capturar código de referido desde URL
add_action('init', 'psp_capturar_ref_url');
function psp_capturar_ref_url(): void {
    if (isset($_GET['ref'])) {
        $codigo = sanitize_text_field($_GET['ref']);
        setcookie('psp_ref', $codigo, time() + 86400 * 30, '/');
    }
}

function psp_mi_referido_shortcode(): string {
    ob_start(); ?>
    <div id="psp-mi-referido" class="psp-card">
      <div id="psp-referido-loading">Cargando tu código...</div>
      <div id="psp-referido-contenido" style="display:none">
        <h3>🔗 Tu Código de Referido</h3>
        <div class="psp-codigo-box">
          <span id="psp-codigo-display" class="psp-codigo-grande"></span>
          <button onclick="PSPRef.copiar()" class="psp-btn psp-btn-sm">📋 Copiar</button>
        </div>
        <div class="psp-share-link">
          <span>Tu enlace: </span>
          <span id="psp-link-display" class="psp-link-text"></span>
          <button onclick="PSPRef.copiarLink()" class="psp-btn psp-btn-sm">📋</button>
        </div>
        <div class="psp-share-row">
          <button onclick="PSPRef.shareWA()"   class="psp-btn psp-btn-wa">WhatsApp</button>
          <button onclick="PSPRef.shareTG()"   class="psp-btn psp-btn-tg">Telegram</button>
          <button onclick="PSPRef.shareFB()"   class="psp-btn psp-btn-fb">Facebook</button>
          <button onclick="PSPRef.shareIG()"   class="psp-btn psp-btn-ig">Instagram</button>
          <button onclick="PSPRef.shareTW()"   class="psp-btn psp-btn-tw">Twitter/X</button>
        </div>
        <div class="psp-nivel-box">
          <div class="psp-nivel-label">Tu nivel: <strong id="psp-nivel-actual">—</strong></div>
          <div class="psp-puntos-label">Puntos: <strong id="psp-puntos-actual">0</strong></div>
          <div class="psp-prog"><div class="psp-prog-fill" id="psp-prog-nivel"></div></div>
          <div class="psp-siguiente-nivel" id="psp-siguiente-nivel"></div>
        </div>
        <div class="psp-stats-row">
          <div class="psp-stat"><strong id="ref-directos">0</strong><span>Directos</span></div>
          <div class="psp-stat"><strong id="ref-activos">0</strong><span>Activos</span></div>
          <div class="psp-stat"><strong id="ref-puntos">0</strong><span>Pts ganados</span></div>
        </div>
      </div>
    </div>

    <script>
    const NIVELES = [
      {nivel:'Simpatizante', min:0,    max:499,   icon:'🌱'},
      {nivel:'Promotor',     min:500,  max:1999,  icon:'⭐'},
      {nivel:'Embajador',    min:2000, max:4999,  icon:'🌟'},
      {nivel:'Líder',        min:5000, max:9999,  icon:'💫'},
      {nivel:'Champion',     min:10000,max:Infinity,icon:'🏆'},
    ];

    const PSPRef = {
      codigo: '', link: '', puntos: 0,

      async init() {
        const jwt = document.cookie.match(/psp_jwt=([^;]+)/)?.[1];
        if (!jwt) { document.getElementById('psp-referido-loading').textContent = '⚠️ Inicia sesión para ver tu código.'; return; }

        const r = await fetch(PSP_CONFIG.ajax_url, {
          method:'POST',
          body: new URLSearchParams({action:'psp_get_mi_perfil_ref', psp_nonce: PSP_CONFIG.nonce, jwt})
        });
        const d = await r.json();
        if (!d.success) { document.getElementById('psp-referido-loading').textContent = 'Error cargando perfil.'; return; }

        this.codigo = d.data.codigo;
        this.link   = location.origin + '/?ref=' + this.codigo;
        this.puntos = d.data.puntos || 0;

        document.getElementById('psp-referido-loading').style.display = 'none';
        document.getElementById('psp-referido-contenido').style.display = 'block';
        document.getElementById('psp-codigo-display').textContent = this.codigo;
        document.getElementById('psp-link-display').textContent   = this.link;
        document.getElementById('ref-directos').textContent = d.data.ref_directos || 0;
        document.getElementById('ref-activos').textContent  = d.data.ref_activos  || 0;
        document.getElementById('ref-puntos').textContent   = this.puntos.toLocaleString();

        const nivel = NIVELES.find(n => this.puntos >= n.min && this.puntos <= n.max) || NIVELES[0];
        const sig   = NIVELES[NIVELES.indexOf(nivel)+1];
        const pct   = sig ? Math.min(((this.puntos - nivel.min)/(sig.min - nivel.min))*100, 100) : 100;
        document.getElementById('psp-nivel-actual').textContent   = nivel.icon + ' ' + nivel.nivel;
        document.getElementById('psp-puntos-actual').textContent  = this.puntos.toLocaleString();
        document.getElementById('psp-prog-nivel').style.width     = pct + '%';
        document.getElementById('psp-siguiente-nivel').textContent = sig ? `${sig.min - this.puntos} pts para ${sig.nivel}` : '¡Nivel máximo!';
      },

      copiar()     { navigator.clipboard.writeText(this.codigo); },
      copiarLink() { navigator.clipboard.writeText(this.link); },
      shareWA()    { window.open('https://wa.me/?text=' + encodeURIComponent('¡Únete al Movimiento Panamá Sin Pobreza! Juntos vamos a erradicar la pobreza. Registrate aquí: ' + this.link), '_blank'); },
      shareTG()    { window.open('https://t.me/share/url?url=' + encodeURIComponent(this.link) + '&text=' + encodeURIComponent('¡Únete a #PanamáSinPobreza!'), '_blank'); },
      shareFB()    { window.open('https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(this.link), '_blank'); },
      shareIG()    { navigator.clipboard.writeText(this.link); alert('Enlace copiado. Pégalo en tu bio de Instagram.'); },
      shareTW()    { window.open('https://twitter.com/intent/tweet?text=' + encodeURIComponent('Me uní a #PanamáSinPobreza 🇵🇦 ¡Tú también puedes! ' + this.link), '_blank'); },
    };
    document.addEventListener('DOMContentLoaded', () => PSPRef.init());
    </script>
    <?php return ob_get_clean();
}

// AJAX: obtener perfil/referido
add_action('wp_ajax_nopriv_psp_get_mi_perfil_ref', 'psp_ajax_get_perfil_ref');
add_action('wp_ajax_psp_get_mi_perfil_ref',        'psp_ajax_get_perfil_ref');
function psp_ajax_get_perfil_ref(): void {
    if (!psp_verify_nonce()) wp_send_json_error();
    $jwt = sanitize_text_field($_POST['jwt'] ?? '');
    if (!$jwt) wp_send_json_error(['message' => 'JWT requerido']);

    // Obtener usuario de Supabase con JWT
    $user_res = wp_remote_get(PSP_SUPABASE_URL . '/auth/v1/user', [
        'headers' => ['apikey' => PSP_SUPABASE_KEY, 'Authorization' => 'Bearer ' . $jwt],
    ]);
    $user_data = json_decode(wp_remote_retrieve_body($user_res), true);
    if (!isset($user_data['id'])) wp_send_json_error(['message' => 'Token inválido']);

    $miembro = PSP_Supabase::select('miembros', ['user_id' => 'eq.' . $user_data['id'], 'limit' => 1]);
    if (!$miembro) wp_send_json_error(['message' => 'Miembro no encontrado']);

    $m    = $miembro[0];
    $refs = PSP_Supabase::select('referidos_log', ['referidor_id' => 'eq.' . $m['id'], 'select' => 'referido_id,puntos_ganados']);

    wp_send_json_success([
        'codigo'        => $m['codigo_referido_propio'],
        'puntos'        => $m['puntos_total'] ?? 0,
        'nivel'         => $m['nivel'] ?? 'Simpatizante',
        'ref_directos'  => count($refs ?? []),
        'ref_activos'   => count(array_filter($refs ?? [], fn($r) => isset($r['puntos_ganados']) && $r['puntos_ganados'] > 0)),
        'ref_puntos'    => array_sum(array_column($refs ?? [], 'puntos_ganados')),
    ]);
}

function psp_mis_referidos_shortcode(): string {
    return '<div id="psp-mis-refs"><p>Cargando tus referidos...</p></div>
    <script>
    (async () => {
      const jwt = document.cookie.match(/psp_jwt=([^;]+)/)?.[1];
      if (!jwt) { document.getElementById("psp-mis-refs").textContent = "Inicia sesión"; return; }
      const r = await fetch(PSP_CONFIG.ajax_url,{method:"POST",body:new URLSearchParams({action:"psp_get_mis_referidos",psp_nonce:PSP_CONFIG.nonce,jwt})});
      const d = await r.json();
      const el = document.getElementById("psp-mis-refs");
      if (!d.success || !d.data?.length) { el.textContent="Aún no tienes referidos"; return; }
      el.innerHTML = "<table class=\'psp-table\'><tr><th>Nombre</th><th>Estado</th><th>Pts Ganados</th></tr>" +
        d.data.map(ref=>`<tr><td>${ref.nombre||"—"}</td><td>${ref.estado||"pendiente"}</td><td>${ref.puntos_ganados||0}</td></tr>`).join("") + "</table>";
    })();
    </script>';
}

add_action('wp_ajax_psp_get_mis_referidos',        'psp_ajax_get_mis_refs');
add_action('wp_ajax_nopriv_psp_get_mis_referidos', 'psp_ajax_get_mis_refs');
function psp_ajax_get_mis_refs(): void {
    if (!psp_verify_nonce()) wp_send_json_error();
    $jwt = sanitize_text_field($_POST['jwt'] ?? '');
    $user_res = wp_remote_get(PSP_SUPABASE_URL . '/auth/v1/user', [
        'headers' => ['apikey' => PSP_SUPABASE_KEY, 'Authorization' => 'Bearer ' . $jwt],
    ]);
    $user_data = json_decode(wp_remote_retrieve_body($user_res), true);
    if (!isset($user_data['id'])) wp_send_json_error();
    $miembro = PSP_Supabase::select('miembros', ['user_id' => 'eq.' . $user_data['id'], 'limit' => 1]);
    if (!$miembro) wp_send_json_error();
    $refs = PSP_Supabase::rpc('get_mis_referidos', ['p_miembro_id' => $miembro[0]['id']]);
    wp_send_json_success($refs ?? []);
}
}
