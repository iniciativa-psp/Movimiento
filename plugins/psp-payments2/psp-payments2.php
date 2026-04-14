<?php
/**
 * Plugin Name: PSP Payments 2
 * Plugin URI:  https://panamasinpobreza.org
 * Description: Pagos de membresía v2. Registra intent de pago en Supabase como `pendiente_verificacion`. Confirmación manual para MVP. Requiere PSP Core 2.
 * Version:     2.0.0
 * Author:      PSP Dev Team
 * Requires PHP: 7.4
 * Text Domain: psp-payments2
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ── Aviso si PSP Core 2 no está activo ───────────────────────────────────────
add_action( 'admin_notices', 'psp2_payments_check_core' );
function psp2_payments_check_core(): void {
    if ( ! class_exists( 'PSP2_Supabase' ) ) {
        echo '<div class="notice notice-warning"><p><strong>PSP Payments 2:</strong> Requiere <strong>PSP Core 2</strong> activo para funcionar completamente.</p></div>';
    }
}

// ── Shortcode [psp2_pago] ────────────────────────────────────────────────────
add_shortcode( 'psp2_pago', 'psp2_pago_shortcode' );
function psp2_pago_shortcode( array $atts = [] ): string {
    if ( ! is_user_logged_in() ) {
        return '<div class="psp2-card" style="text-align:center"><p>&#x1F512; <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">Inicia sesi&oacute;n</a> para pagar.</p></div>';
    }

    $fee = (float) get_option( 'psp2_membership_fee', '1.00' );

    ob_start(); ?>
    <div class="psp2-card" id="psp2-pago-wrap">
      <h2 style="margin-top:0">&#x1F4B3; Activar Membres&iacute;a</h2>
      <p>Monto: <strong><?php echo esc_html( 'B/.' . number_format( $fee, 2 ) ); ?></strong></p>

      <div id="psp2-pago-msg" style="display:none"></div>

      <form id="psp2-form-pago" autocomplete="off">
        <label class="psp2-label">M&eacute;todo de pago <span class="psp2-req">*</span></label>
        <select name="metodo" class="psp2-input" required>
          <option value="">-- Selecciona --</option>
          <option value="yappy">Yappy</option>
          <option value="clave">Clave</option>
          <option value="tarjeta">Tarjeta (Bancard)</option>
          <option value="puntopago">PuntoPago</option>
          <option value="paypal">PayPal</option>
          <option value="transferencia_nacional">Transferencia nacional</option>
          <option value="transferencia_internacional">Transferencia internacional</option>
          <option value="efectivo">Efectivo</option>
        </select>

        <label class="psp2-label">N&uacute;mero de referencia / confirmaci&oacute;n <small>(opcional)</small></label>
        <input name="referencia" type="text" class="psp2-input" placeholder="Ej: 123456789">

        <input type="hidden" name="monto" value="<?php echo esc_attr( $fee ); ?>">
        <input type="hidden" name="action" value="psp2_pago_intent">
        <input type="hidden" name="psp2_nonce" value="<?php echo esc_attr( wp_create_nonce( 'psp2_nonce' ) ); ?>">

        <button type="submit" class="psp2-btn psp2-btn-primary" style="width:100%;margin-top:8px">
          &#x1F4B8; Registrar pago de <?php echo esc_html( 'B/.' . number_format( $fee, 2 ) ); ?>
        </button>
      </form>

      <p style="font-size:12px;color:#6B7280;margin-top:12px;text-align:center">
        &#x26A0;&#xFE0F; Tu membres&iacute;a ser&aacute; activada despu&eacute;s de verificar el pago (1-24 h).
      </p>
    </div>
    <script>
    (function(){
      var form = document.getElementById('psp2-form-pago');
      if (!form) return;
      form.addEventListener('submit', async function(e){
        e.preventDefault();
        var btn = form.querySelector('button[type=submit]');
        var msg = document.getElementById('psp2-pago-msg');
        if (btn) btn.disabled = true;
        if (msg) msg.style.display = 'none';
        var fd = new FormData(form);
        try {
          var res = await fetch(
            (typeof PSP2_CONFIG !== 'undefined' ? PSP2_CONFIG.ajax_url : '/wp-admin/admin-ajax.php'),
            { method: 'POST', body: fd }
          );
          var d = await res.json();
          if (msg) {
            msg.className = 'psp2-alert psp2-alert-' + (d.success ? 'success' : 'error');
            msg.innerHTML = (d.data && d.data.message) ? d.data.message : (d.success ? '\u2705 Pago registrado.' : '\u274C Error.');
            msg.style.display = 'block';
          }
          if (!d.success && btn) btn.disabled = false;
        } catch(err) {
          if (msg) { msg.className='psp2-alert psp2-alert-error'; msg.textContent = '\u274C '+err.message; msg.style.display='block'; }
          if (btn) btn.disabled = false;
        }
      });
    })();
    </script>
    <?php
    return ob_get_clean();
}

// ── AJAX: registrar payment intent ──────────────────────────────────────────
add_action( 'wp_ajax_psp2_pago_intent', 'psp2_ajax_pago_intent' );
function psp2_ajax_pago_intent(): void {
    if ( ! is_user_logged_in() ) {
        wp_send_json_error( [ 'message' => 'Debes iniciar sesión.' ] );
    }
    $nonce = sanitize_text_field( wp_unslash( $_POST['psp2_nonce'] ?? '' ) );
    if ( ! wp_verify_nonce( $nonce, 'psp2_nonce' ) ) {
        wp_send_json_error( [ 'message' => 'Nonce inválido.' ] );
    }
    if ( ! class_exists( 'PSP2_Supabase' ) ) {
        wp_send_json_error( [ 'message' => 'Sistema de pagos no disponible. Intenta más tarde.' ] );
    }

    $wp_user_id = (int) get_current_user_id();
    $metodo     = sanitize_text_field( wp_unslash( $_POST['metodo']    ?? '' ) );
    $monto      = (float) ( $_POST['monto'] ?? 0 );
    $referencia = sanitize_text_field( wp_unslash( $_POST['referencia'] ?? '' ) );

    $metodos_validos = [ 'yappy', 'clave', 'tarjeta', 'puntopago', 'paypal', 'transferencia_nacional', 'transferencia_internacional', 'efectivo' ];
    if ( ! in_array( $metodo, $metodos_validos, true ) ) {
        wp_send_json_error( [ 'message' => 'Método de pago no válido.' ] );
    }

    $fee_min = (float) get_option( 'psp2_membership_fee', '1.00' );
    if ( $monto < $fee_min ) {
        wp_send_json_error( [ 'message' => sprintf( 'El monto mínimo es B/.%.2f', $fee_min ) ] );
    }

    // Obtener miembro
    $miembro_row = PSP2_Supabase::select( 'miembros', [
        'wp_user_id' => 'eq.' . $wp_user_id,
        'select'     => 'id,estado',
        'limit'      => 1,
    ] );

    if ( empty( $miembro_row ) ) {
        wp_send_json_error( [ 'message' => 'Perfil de miembro no encontrado.' ] );
    }

    $miembro = $miembro_row[0];

    if ( ( $miembro['estado'] ?? '' ) === 'activo' ) {
        wp_send_json_error( [ 'message' => 'Tu membresía ya está activa. ¡Gracias!' ] );
    }

    // Métodos que siempre requieren verificación manual
    $requiere_verif = in_array( $metodo, [ 'transferencia_nacional', 'transferencia_internacional', 'efectivo' ], true );

    $pago = PSP2_Supabase::insert( 'pagos', [
        'miembro_id' => $miembro['id'],
        'tenant_id'  => get_option( 'psp2_tenant_id', 'panama' ),
        'monto'      => $monto,
        'moneda'     => 'USD',
        'metodo'     => $metodo,
        'referencia' => $referencia ?: null,
        'estado'     => 'pendiente_verificacion',
        'concepto'   => 'membresia',
    ], true );

    if ( ! $pago ) {
        wp_send_json_error( [ 'message' => 'Error al registrar el pago. Intenta de nuevo.' ] );
    }

    wp_send_json_success( [
        'message'  => '&#x2705; Pago registrado. Tu membres&iacute;a ser&aacute; activada una vez verificado el pago (normalmente en menos de 24 h).',
        'pago_id'  => $pago[0]['id'] ?? null,
        'estado'   => 'pendiente_verificacion',
    ] );
}

// ── REST: POST /wp-json/psp/v2/pago-intent ───────────────────────────────────
add_action( 'rest_api_init', 'psp2_payments_rest_routes' );
function psp2_payments_rest_routes(): void {
    $ns = defined( 'PSP2_REST_NS' ) ? PSP2_REST_NS : 'psp/v2';
    register_rest_route( $ns, '/pago-intent', [
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'psp2_rest_pago_intent',
        'permission_callback' => function () {
            return is_user_logged_in();
        },
        'args' => [
            'metodo'     => [ 'required' => true,  'sanitize_callback' => 'sanitize_text_field' ],
            'monto'      => [ 'required' => true,  'validate_callback' => 'is_numeric' ],
            'referencia' => [ 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
        ],
    ] );
}

function psp2_rest_pago_intent( WP_REST_Request $request ): WP_REST_Response|WP_Error {
    if ( ! class_exists( 'PSP2_Supabase' ) ) {
        return new WP_Error( 'psp2_core_missing', 'PSP Core 2 no activo.', [ 'status' => 500 ] );
    }

    $wp_user_id = (int) get_current_user_id();
    $metodo     = $request->get_param( 'metodo' );
    $monto      = (float) $request->get_param( 'monto' );
    $referencia = $request->get_param( 'referencia' ) ?: '';

    $metodos_validos = [ 'yappy', 'clave', 'tarjeta', 'puntopago', 'paypal', 'transferencia_nacional', 'transferencia_internacional', 'efectivo' ];
    if ( ! in_array( $metodo, $metodos_validos, true ) ) {
        return new WP_Error( 'psp2_invalid_method', 'Método de pago no válido.', [ 'status' => 400 ] );
    }

    $fee_min = (float) get_option( 'psp2_membership_fee', '1.00' );
    if ( $monto < $fee_min ) {
        return new WP_Error( 'psp2_amount_low', sprintf( 'Monto mínimo: B/.%.2f', $fee_min ), [ 'status' => 400 ] );
    }

    $miembro_row = PSP2_Supabase::select( 'miembros', [
        'wp_user_id' => 'eq.' . $wp_user_id,
        'select'     => 'id,estado',
        'limit'      => 1,
    ] );
    if ( empty( $miembro_row ) ) {
        return new WP_Error( 'psp2_not_found', 'Miembro no encontrado.', [ 'status' => 404 ] );
    }
    $miembro = $miembro_row[0];
    if ( ( $miembro['estado'] ?? '' ) === 'activo' ) {
        return new WP_Error( 'psp2_already_active', 'Membresía ya activa.', [ 'status' => 409 ] );
    }

    $pago = PSP2_Supabase::insert( 'pagos', [
        'miembro_id' => $miembro['id'],
        'tenant_id'  => get_option( 'psp2_tenant_id', 'panama' ),
        'monto'      => $monto,
        'moneda'     => 'USD',
        'metodo'     => $metodo,
        'referencia' => $referencia ?: null,
        'estado'     => 'pendiente_verificacion',
        'concepto'   => 'membresia',
    ], true );

    if ( ! $pago ) {
        return new WP_Error( 'psp2_db_error', 'Error al registrar el pago.', [ 'status' => 500 ] );
    }

    return new WP_REST_Response( [
        'success' => true,
        'pago_id' => $pago[0]['id'] ?? null,
        'estado'  => 'pendiente_verificacion',
        'mensaje' => 'Pago registrado. Tu membresía será activada una vez verificado.',
    ], 201 );
}
