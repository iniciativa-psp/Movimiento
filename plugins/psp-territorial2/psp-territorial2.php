<?php
/**
 * Plugin Name: PSP Territorial 2
 * Plugin URI:  https://panamasinpobreza.org
 * Description: Selector territorial encadenado v2 (provincia → distrito → corregimiento → comunidad). Carga datos desde URL JSON configurable. Independiente de PSP Core.
 * Version:     2.0.0
 * Author:      PSP Dev Team
 * Requires PHP: 7.4
 * Text Domain: psp-territorial2
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'PSP2_TERR_DIR', plugin_dir_path( __FILE__ ) );
define( 'PSP2_TERR_URL', plugin_dir_url( __FILE__ ) );

// ── Activación ────────────────────────────────────────────────────────────────
register_activation_hook( __FILE__, 'psp2_territorial_activate' );
function psp2_territorial_activate(): void {
    add_option( 'psp2_territorial_json_url', '' );
    add_option( 'psp2_territorial_modo',     'pspv2_rest' ); // 'json_url' | 'pspv2_rest'
}

// ── Carga de includes ─────────────────────────────────────────────────────────
add_action( 'plugins_loaded', 'psp2_territorial_load' );
function psp2_territorial_load(): void {
    require_once PSP2_TERR_DIR . 'includes/territorial-ajax.php';
    require_once PSP2_TERR_DIR . 'includes/territorial-admin.php';
}

// ── Encolar assets ─────────────────────────────────────────────────────────────
add_action( 'wp_enqueue_scripts', 'psp2_territorial_enqueue' );
function psp2_territorial_enqueue(): void {
    wp_enqueue_script(
        'psp2-territorial',
        PSP2_TERR_URL . 'assets/psp2-territorial.js',
        [],
        '2.0.0',
        true
    );

    // Smart effective mode: when PSP Territorial V2 is active always use pspv2_rest.
    // Otherwise respect the saved setting (default pspv2_rest; map legacy 'bundled' → 'pspv2_rest').
    // Use active_plugins option directly to avoid loading wp-admin/includes/plugin.php on every front-end request.
    $active_plugins = (array) get_option( 'active_plugins', [] );
    if ( in_array( 'psp-territorial-v2/psp-territorial-v2.php', $active_plugins, true ) ) {
        $modo = 'pspv2_rest';
    } else {
        $saved = get_option( 'psp2_territorial_modo', 'pspv2_rest' );
        $modo  = ( $saved === 'bundled' || $saved === '' ) ? 'pspv2_rest' : $saved;
    }

    wp_localize_script( 'psp2-territorial', 'PSP2_TERR', [
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'psp2_nonce' ),
        'json_url' => get_option( 'psp2_territorial_json_url', '' ),
        'modo'     => $modo,
        'contact'  => 'admin@panamasinpobreza.org',
    ] );
}

// ── Shortcodes ────────────────────────────────────────────────────────────────
add_shortcode( 'psp2_territorial_selector', 'psp2_territorial_shortcode' );
add_shortcode( 'psp2_territorial',          'psp2_territorial_shortcode' ); // alias

/**
 * Selector territorial encadenado.
 * Atts:
 *   prefix=""                        prefijo para name/id de los campos
 *   mostrar_internacional="si|no"    mostrar toggle Internacional (default: si)
 *   required="si|no"                 campos required (default: si)
 */
function psp2_territorial_shortcode( array $atts = [] ): string {
    $atts = shortcode_atts( [
        'prefix'                => '',
        'mostrar_internacional' => 'si',
        'required'              => 'si',
    ], $atts, 'psp2_territorial_selector' );

    $req = ( $atts['required'] === 'si' ) ? 'required' : '';
    $p   = esc_attr( $atts['prefix'] );
    $uid = esc_attr( 'psp2t-' . wp_unique_id() );

    ob_start();
    ?>
    <div class="psp2-terr-wrap" id="<?php echo $uid; ?>">

      <?php if ( $atts['mostrar_internacional'] === 'si' ) : ?>
      <div class="psp2-terr-toggle" style="display:flex;gap:20px;margin-bottom:14px;font-size:14px;font-weight:600">
        <label style="display:flex;align-items:center;gap:6px;cursor:pointer">
          <input type="radio" name="<?php echo $p; ?>ubicacion_tipo" value="panama" checked
                 onchange="PSP2Terr.switchTipo(this,'panama')">
          &#x1F1F5;&#x1F1E6; Soy de Panam&aacute;
        </label>
        <label style="display:flex;align-items:center;gap:6px;cursor:pointer">
          <input type="radio" name="<?php echo $p; ?>ubicacion_tipo" value="internacional"
                 onchange="PSP2Terr.switchTipo(this,'internacional')">
          &#x1F30E; Internacional
        </label>
      </div>
      <?php endif; ?>

      <!-- Selector Panamá -->
      <div class="psp2-terr-panama" id="<?php echo $uid; ?>-panama">

        <div id="<?php echo $p; ?>row-provincia">
          <label class="psp2-label">Provincia <?php if ( $req ) echo '<span class="psp2-req">*</span>'; ?></label>
          <select name="<?php echo $p; ?>provincia_id"
                  id="<?php echo $p; ?>psp2_provincia"
                  class="psp2-input psp2-terr-select"
                  <?php echo $req; ?>
                  onchange="PSP2Terr.load(this,'distrito','<?php echo $p; ?>')">
            <option value="">-- Selecciona provincia --</option>
          </select>
        </div>

        <div id="<?php echo $p; ?>row-distrito" style="display:none">
          <label class="psp2-label">Distrito <span class="psp2-req">*</span></label>
          <select name="<?php echo $p; ?>distrito_id"
                  id="<?php echo $p; ?>psp2_distrito"
                  class="psp2-input psp2-terr-select"
                  <?php echo $req; ?>
                  onchange="PSP2Terr.load(this,'corregimiento','<?php echo $p; ?>')">
            <option value="">-- Selecciona distrito --</option>
          </select>
        </div>

        <div id="<?php echo $p; ?>row-corregimiento" style="display:none">
          <label class="psp2-label">Corregimiento <?php if ( $req ) echo '<span class="psp2-req">*</span>'; ?></label>
          <select name="<?php echo $p; ?>corregimiento_id"
                  id="<?php echo $p; ?>psp2_corregimiento"
                  class="psp2-input psp2-terr-select"
                  <?php echo $req; ?>
                  onchange="PSP2Terr.load(this,'comunidad','<?php echo $p; ?>')">
            <option value="">-- Selecciona corregimiento --</option>
          </select>
        </div>

        <div id="<?php echo $p; ?>row-comunidad" style="display:none">
          <label class="psp2-label">Comunidad / Barrio <?php if ( $req ) echo '<span class="psp2-req">*</span>'; ?></label>
          <select name="<?php echo $p; ?>comunidad_id"
                  id="<?php echo $p; ?>psp2_comunidad"
                  class="psp2-input psp2-terr-select"
                  <?php echo $req; ?>>
            <option value="">-- Selecciona comunidad --</option>
          </select>
        </div>

      </div><!-- /.psp2-terr-panama -->

      <!-- Selector Internacional -->
      <div class="psp2-terr-inter" id="<?php echo $uid; ?>-inter" style="display:none">
        <label class="psp2-label">Pa&iacute;s <span class="psp2-req">*</span></label>
        <select name="<?php echo $p; ?>pais_id" class="psp2-input">
          <option value="">-- Selecciona pa&iacute;s --</option>
          <?php echo psp2_terr_opciones_paises(); ?>
        </select>
        <label class="psp2-label" style="margin-top:8px">Ciudad</label>
        <input type="text" name="<?php echo $p; ?>ciudad" class="psp2-input" placeholder="Ciudad o estado">
      </div>

    </div><!-- /.psp2-terr-wrap -->
    <?php
    return ob_get_clean();
}

/**
 * Genera las <option> de países.
 */
function psp2_terr_opciones_paises(): string {
    $paises = [
        'PA' => 'Panam&aacute;', 'US' => 'Estados Unidos', 'ES' => 'Espa&ntilde;a', 'MX' => 'M&eacute;xico',
        'CO' => 'Colombia',      'VE' => 'Venezuela',       'CR' => 'Costa Rica',   'GT' => 'Guatemala',
        'DO' => 'Rep. Dominicana','EC' => 'Ecuador',        'PE' => 'Per&uacute;',   'AR' => 'Argentina',
        'CL' => 'Chile',         'CU' => 'Cuba',            'HN' => 'Honduras',     'NI' => 'Nicaragua',
        'SV' => 'El Salvador',   'BO' => 'Bolivia',         'PY' => 'Paraguay',     'UY' => 'Uruguay',
        'BR' => 'Brasil',        'CA' => 'Canad&aacute;',   'GB' => 'Reino Unido',  'FR' => 'Francia',
        'DE' => 'Alemania',      'IT' => 'Italia',          'PT' => 'Portugal',     'NL' => 'Pa&iacute;ses Bajos',
        'AU' => 'Australia',     'JP' => 'Jap&oacute;n',    'ZZ' => 'Otro',
    ];
    $html = '';
    foreach ( $paises as $code => $name ) {
        $html .= '<option value="' . esc_attr( $code ) . '">' . $name . '</option>';
    }
    return $html;
}
