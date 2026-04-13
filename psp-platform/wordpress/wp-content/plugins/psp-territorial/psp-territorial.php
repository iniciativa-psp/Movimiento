<?php
/**
 * Plugin Name: PSP Territorial
 * Plugin URI:  https://panamasinpobreza.org
 * Description: Selector territorial encadenado para Panamá: provincia → distrito → corregimiento → comunidad. Carga desde JSON externo o desde Supabase. Compatible con cualquier plugin territorial que exponga JSON.
 * Version:     1.0.2
 * Author:      PSP Dev Team
 * Requires PHP: 7.4
 * Text Domain: psp-territorial
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'PSP_TERR_DIR', plugin_dir_path( __FILE__ ) );
define( 'PSP_TERR_URL', plugin_dir_url( __FILE__ ) );

add_action( 'plugins_loaded', 'psp_territorial_load' );
function psp_territorial_load() {
    require_once PSP_TERR_DIR . 'includes/territorial-ajax.php';
    require_once PSP_TERR_DIR . 'includes/territorial-admin.php';
}

// ── Activación ────────────────────────────────────────────────────────────────
register_activation_hook( __FILE__, 'psp_territorial_activate' );
function psp_territorial_activate() {
    // Opción para la URL del JSON externo del plugin territorial que ya tienen
    add_option( 'psp_territorial_json_url', '' );
    // O ruta al archivo JSON local dentro del plugin externo
    add_option( 'psp_territorial_json_path', '' );
    // Modo: 'json_externo' | 'supabase' | 'json_local'
    add_option( 'psp_territorial_modo', 'json_externo' );
}

// ── Encolar assets ─────────────────────────────────────────────────────────────
add_action( 'wp_enqueue_scripts', 'psp_territorial_enqueue' );
function psp_territorial_enqueue() {
    wp_enqueue_script(
        'psp-territorial',
        PSP_TERR_URL . 'assets/territorial.js',
        [],
        '1.0.2',
        true
    );
    wp_localize_script( 'psp-territorial', 'PSP_TERR', [
        'ajax_url'   => admin_url( 'admin-ajax.php' ),
        'nonce'      => wp_create_nonce( 'psp_nonce' ),
        'modo'       => get_option( 'psp_territorial_modo', 'json_externo' ),
        'json_url'   => get_option( 'psp_territorial_json_url', '' ),
    ]);
}

// ── Shortcodes ────────────────────────────────────────────────────────────────
add_shortcode( 'psp_territorial_selector', 'psp_territorial_shortcode' );
add_shortcode( 'psp_territorial',          'psp_territorial_shortcode' ); // alias

/**
 * Shortcode: selector territorial encadenado
 * Atts:
 *   mostrar_internacional="si|no"  (default: si)
 *   required="si|no"               (default: si)
 *   prefix=""                      (prefijo para ids y names de los campos)
 */
function psp_territorial_shortcode( $atts = [] ) {
    $atts = shortcode_atts([
        'mostrar_internacional' => 'si',
        'required'              => 'si',
        'prefix'                => '',
    ], $atts );

    $req = $atts['required'] === 'si' ? 'required' : '';
    $p   = esc_attr( $atts['prefix'] );

    ob_start();
    ?>
    <div class="psp-terr-wrap" id="psp-terr-<?php echo esc_attr( uniqid() ); ?>">

      <!-- Toggle Panamá / Internacional -->
      <?php if ( $atts['mostrar_internacional'] === 'si' ) : ?>
      <div class="psp-terr-toggle">
        <label>
          <input type="radio" name="<?php echo $p; ?>ubicacion_tipo"
                 value="panama" checked
                 onchange="PSPTerr.switchTipo(this,'panama')">
          &#x1F1F5;&#x1F1E6; Soy de Panam&aacute;
        </label>
        <label>
          <input type="radio" name="<?php echo $p; ?>ubicacion_tipo"
                 value="internacional"
                 onchange="PSPTerr.switchTipo(this,'internacional')">
          &#x1F30E; Soy internacional
        </label>
      </div>
      <?php endif; ?>

      <!-- Selector Panamá -->
      <div class="psp-terr-panama" id="psp-terr-panama">
        <div class="psp-terr-row">
          <label class="psp-terr-label">Provincia <span class="psp-req">*</span></label>
          <select name="<?php echo $p; ?>provincia_id"
                  id="<?php echo $p; ?>psp_provincia"
                  class="psp-input psp-terr-select"
                  <?php echo $req; ?>
                  onchange="PSPTerr.loadDistritos(this)">
            <option value="">-- Selecciona provincia --</option>
          </select>
        </div>

        <div class="psp-terr-row" id="<?php echo $p; ?>row-distrito" style="display:none">
          <label class="psp-terr-label">Distrito <span class="psp-req">*</span></label>
          <select name="<?php echo $p; ?>distrito_id"
                  id="<?php echo $p; ?>psp_distrito"
                  class="psp-input psp-terr-select"
                  <?php echo $req; ?>
                  onchange="PSPTerr.loadCorregimientos(this)">
            <option value="">-- Selecciona distrito --</option>
          </select>
        </div>

        <div class="psp-terr-row" id="<?php echo $p; ?>row-corregimiento" style="display:none">
          <label class="psp-terr-label">Corregimiento</label>
          <select name="<?php echo $p; ?>corregimiento_id"
                  id="<?php echo $p; ?>psp_corregimiento"
                  class="psp-input psp-terr-select"
                  onchange="PSPTerr.loadComunidades(this)">
            <option value="">-- Selecciona corregimiento --</option>
          </select>
        </div>

        <div class="psp-terr-row" id="<?php echo $p; ?>row-comunidad" style="display:none">
          <label class="psp-terr-label">Comunidad / Barrio</label>
          <select name="<?php echo $p; ?>comunidad_id"
                  id="<?php echo $p; ?>psp_comunidad"
                  class="psp-input psp-terr-select">
            <option value="">-- Selecciona (opcional) --</option>
          </select>
        </div>

        <!-- Solicitar nuevo territorio -->
        <div class="psp-terr-nuevo-link">
          <a href="#" onclick="PSPTerr.mostrarFormNuevo(this.closest('.psp-terr-wrap'));return false;"
             style="font-size:12px;color:#0B5E43">
            &#x2795; ¿No encuentras tu comunidad? Solicitar agregar
          </a>
        </div>
        <div class="psp-terr-form-nuevo" style="display:none;margin-top:10px;padding:14px;background:#F8F9FA;border-radius:8px;border:1px solid #E2E8F0">
          <strong style="font-size:13px">Solicitar nuevo territorio</strong>
          <input type="text"  class="psp-input psp-terr-nuevo-nombre"
                 placeholder="Nombre de la comunidad / corregimiento" style="margin-top:8px">
          <select class="psp-input psp-terr-nuevo-tipo" style="margin-top:6px">
            <option value="comunidad">Comunidad / Barrio</option>
            <option value="corregimiento">Corregimiento</option>
            <option value="distrito">Distrito</option>
          </select>
          <button type="button" class="psp-btn psp-btn-primary psp-btn-sm"
                  style="margin-top:8px"
                  onclick="PSPTerr.enviarSolicitudNuevo(this.closest('.psp-terr-wrap'))">
            Enviar solicitud
          </button>
          <div class="psp-terr-nuevo-msg" style="margin-top:6px;font-size:12px"></div>
        </div>
      </div>

      <!-- Selector Internacional -->
      <div class="psp-terr-inter" id="psp-terr-inter" style="display:none">
        <div class="psp-terr-row">
          <label class="psp-terr-label">Pa&iacute;s <span class="psp-req">*</span></label>
          <select name="<?php echo $p; ?>pais_id"
                  id="<?php echo $p; ?>psp_pais"
                  class="psp-input psp-terr-select"
                  onchange="PSPTerr.loadCiudades(this)">
            <option value="">-- Selecciona pa&iacute;s --</option>
            <?php echo psp_terr_opciones_paises(); ?>
          </select>
        </div>
        <div class="psp-terr-row" id="<?php echo $p; ?>row-ciudad" style="display:none">
          <label class="psp-terr-label">Ciudad</label>
          <input type="text" name="<?php echo $p; ?>ciudad"
                 id="<?php echo $p; ?>psp_ciudad"
                 class="psp-input" placeholder="Ciudad o estado">
        </div>
      </div>

      <!-- Campos ocultos para guardar IDs numéricos del JSON -->
      <input type="hidden" name="<?php echo $p; ?>provincia_nombre"    id="<?php echo $p; ?>psp_prov_nombre">
      <input type="hidden" name="<?php echo $p; ?>distrito_nombre"     id="<?php echo $p; ?>psp_dist_nombre">
      <input type="hidden" name="<?php echo $p; ?>corregimiento_nombre" id="<?php echo $p; ?>psp_corr_nombre">

    </div><!-- /.psp-terr-wrap -->

    <style>
    .psp-terr-toggle{display:flex;gap:20px;margin-bottom:14px;font-size:14px;font-weight:600}
    .psp-terr-toggle label{display:flex;align-items:center;gap:6px;cursor:pointer}
    .psp-terr-row{margin-bottom:12px}
    .psp-terr-label{display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:4px}
    .psp-req{color:#C9381A}
    .psp-terr-nuevo-link{margin-top:6px}
    </style>
    <?php
    return ob_get_clean();
}

/**
 * Genera opciones HTML de países
 */
function psp_terr_opciones_paises() {
    $paises = [
        'PA'=>'Panamá','US'=>'Estados Unidos','ES'=>'España','MX'=>'México',
        'CO'=>'Colombia','VE'=>'Venezuela','CR'=>'Costa Rica','GT'=>'Guatemala',
        'DO'=>'Rep. Dominicana','EC'=>'Ecuador','PE'=>'Perú','AR'=>'Argentina',
        'CL'=>'Chile','CU'=>'Cuba','HN'=>'Honduras','NI'=>'Nicaragua',
        'SV'=>'El Salvador','BO'=>'Bolivia','PY'=>'Paraguay','UY'=>'Uruguay',
        'BR'=>'Brasil','CA'=>'Canadá','GB'=>'Reino Unido','FR'=>'Francia',
        'DE'=>'Alemania','IT'=>'Italia','PT'=>'Portugal','NL'=>'Países Bajos',
        'AU'=>'Australia','JP'=>'Japón','CN'=>'China','KR'=>'Corea del Sur',
        'AE'=>'Emiratos Árabes','ZZ'=>'Otro',
    ];
    $html = '';
    foreach ( $paises as $code => $name ) {
        $html .= '<option value="' . esc_attr($code) . '">' . esc_html($name) . '</option>';
    }
    return $html;
}
