<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ── Shortcode: Login ─────────────────────────────────────────────────────────
function psp2_login_shortcode( array $atts = [] ): string {
    $atts = shortcode_atts( [ 'redirect' => '' ], $atts, 'psp2_login' );

    if ( is_user_logged_in() ) {
        $dest = $atts['redirect'] ?: home_url( '/mi-cuenta/' );
        ob_start(); ?>
        <div class="psp2-card" style="text-align:center">
          <div style="font-size:40px">&#x2705;</div>
          <p style="font-weight:700;margin-top:8px">&#xa1;Sesi&oacute;n iniciada!</p>
          <a href="<?php echo esc_url( $dest ); ?>" class="psp2-btn psp2-btn-primary" style="margin-top:12px">
            Ver mi perfil &rarr;
          </a>
        </div>
        <?php
        return ob_get_clean();
    }

    $redirect_url = $atts['redirect'] ?: home_url( '/mi-cuenta/' );
    ob_start();
    wp_login_form( [
        'redirect'       => esc_url( $redirect_url ),
        'form_id'        => 'psp2-wp-login-form',
        'label_username' => 'Tel&eacute;fono o correo',
        'label_password' => 'Contrase&ntilde;a',
        'label_remember' => 'Recordarme',
        'label_log_in'   => 'Iniciar sesi&oacute;n',
        'remember'       => true,
    ] );
    ?>
    <p style="font-size:13px;text-align:center;margin-top:10px">
      <a href="<?php echo esc_url( wp_lostpassword_url() ); ?>">&#x1F511; &iquest;Olvidaste tu contrase&ntilde;a?</a>
      &nbsp;&bull;&nbsp;
      <a href="<?php echo esc_url( home_url( '/registro/' ) ); ?>">Registrarse</a>
    </p>
    <?php
    return ob_get_clean();
}

// ── Shortcode: Registro completo ─────────────────────────────────────────────
function psp2_registro_completo_shortcode( array $atts = [] ): string {
    if ( is_user_logged_in() ) {
        return '<div class="psp2-card" style="text-align:center"><p>&#x2705; Ya tienes cuenta. <a href="' . esc_url( home_url( '/mi-cuenta/' ) ) . '">Ver mi perfil</a></p></div>';
    }

    $ref_code = isset( $_COOKIE['psp2_ref'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['psp2_ref'] ) ) : '';

    ob_start(); ?>
    <div class="psp2-card" id="psp2-reg-wrap">
      <h2 style="margin-top:0">&#x1F91D; &Uacute;nete al Movimiento</h2>

      <div id="psp2-reg-msg" style="display:none"></div>

      <form id="psp2-form-registro" autocomplete="off" novalidate>
        <label class="psp2-label">Nombre completo <span class="psp2-req">*</span></label>
        <input name="nombre" type="text" class="psp2-input" required placeholder="Tu nombre completo">

        <label class="psp2-label">Tel&eacute;fono celular <span class="psp2-req">*</span></label>
        <input name="celular" type="tel" class="psp2-input" required placeholder="Ej: 60001234">

        <label class="psp2-label">Correo electr&oacute;nico <small>(opcional)</small></label>
        <input name="email" type="email" class="psp2-input" placeholder="tucorreo@email.com">

        <label class="psp2-label">Contrase&ntilde;a <span class="psp2-req">*</span></label>
        <input name="password" type="password" class="psp2-input" required placeholder="M&iacute;nimo 8 caracteres" minlength="8">

        <label class="psp2-label">Tipo de miembro</label>
        <select name="tipo_miembro" class="psp2-input">
          <option value="nacional">Nacional</option>
          <option value="internacional">Internacional</option>
          <option value="hogar_solidario">Hogar Solidario</option>
          <option value="lider">L&iacute;der Comunitario</option>
          <option value="voluntario">Voluntario</option>
          <option value="coordinador">Coordinador</option>
        </select>

        <?php if ( shortcode_exists( 'psp2_territorial_selector' ) ) : ?>
          <?php echo do_shortcode( '[psp2_territorial_selector]' ); ?>
        <?php else : ?>
          <label class="psp2-label">Provincia</label>
          <input name="provincia_id" class="psp2-input" placeholder="Provincia (opcional)">
        <?php endif; ?>

        <input type="hidden" name="ref" value="<?php echo esc_attr( $ref_code ); ?>">
        <input type="hidden" name="action" value="psp2_register">
        <input type="hidden" name="psp2_nonce" value="<?php echo esc_attr( wp_create_nonce( 'psp2_nonce' ) ); ?>">

        <button type="submit" class="psp2-btn psp2-btn-primary" style="width:100%;margin-top:8px" id="psp2-reg-btn">
          &#x1F4E9; Registrarme
        </button>
      </form>

      <p style="font-size:12px;text-align:center;margin-top:12px;color:#6B7280">
        &#x1F512; Tus datos est&aacute;n protegidos. No compartimos tu informaci&oacute;n.
      </p>
    </div>
    <?php
    return ob_get_clean();
}

// ── Shortcode: Perfil ─────────────────────────────────────────────────────────
function psp2_perfil_shortcode( array $atts = [] ): string {
    if ( ! is_user_logged_in() ) {
        return '<div class="psp2-card" style="text-align:center"><p>&#x1F512; Debes <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">iniciar sesi&oacute;n</a> para ver tu perfil.</p></div>';
    }

    if ( ! class_exists( 'PSP2_Supabase' ) ) {
        return '<div class="psp2-card"><p>&#x26A0;&#xFE0F; Sistema en mantenimiento. Int&eacute;ntalo de nuevo m&aacute;s tarde.</p></div>';
    }

    $wp_user    = wp_get_current_user();
    $wp_user_id = (int) $wp_user->ID;

    $rows = PSP2_Supabase::select( 'miembros', [
        'wp_user_id' => 'eq.' . $wp_user_id,
        'select'     => 'id,nombre,celular,email,tipo_miembro,estado,codigo_referido_propio,puntos_total,nivel,created_at',
        'limit'      => 1,
    ] );

    if ( empty( $rows ) ) {
        ob_start(); ?>
        <div class="psp2-card" style="text-align:center">
          <p>&#x26A0;&#xFE0F; No encontramos tu perfil de miembro.</p>
          <p style="font-size:13px;color:#6B7280">Si acabas de registrarte, espera unos momentos y recarga la p&aacute;gina.</p>
          <a href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>" class="psp2-btn psp2-btn-secondary" style="margin-top:12px">
            Salir
          </a>
        </div>
        <?php
        return ob_get_clean();
    }

    $m = $rows[0];
    $ref_link = home_url( '/?ref=' . rawurlencode( $m['codigo_referido_propio'] ?? '' ) );

    // Contador de referidos
    $refs = PSP2_Supabase::select( 'referidos_log', [
        'referidor_id' => 'eq.' . $m['id'],
        'select'       => 'id',
    ] );
    $total_ref = is_array( $refs ) ? count( $refs ) : 0;

    $estado_labels = [
        'activo'                  => '<span style="color:#166534;font-weight:700">&#x2705; Activo</span>',
        'pendiente_pago'          => '<span style="color:#92400E;font-weight:700">&#x23F3; Pendiente de pago</span>',
        'pendiente_verificacion'  => '<span style="color:#1E40AF;font-weight:700">&#x1F4CB; En verificaci&oacute;n</span>',
        'inactivo'                => '<span style="color:#991B1B">&#x274C; Inactivo</span>',
    ];
    $estado_html = $estado_labels[ $m['estado'] ?? '' ] ?? esc_html( $m['estado'] ?? '—' );

    ob_start(); ?>
    <div class="psp2-card">
      <h2 style="margin-top:0">&#x1F464; Mi Perfil</h2>

      <table style="width:100%;border-collapse:collapse;font-size:14px">
        <tr>
          <td style="padding:8px 0;color:#6B7280;width:40%">Nombre</td>
          <td style="padding:8px 0;font-weight:600"><?php echo esc_html( $m['nombre'] ?? '—' ); ?></td>
        </tr>
        <tr style="border-top:1px solid #F3F4F6">
          <td style="padding:8px 0;color:#6B7280">Tel&eacute;fono</td>
          <td style="padding:8px 0"><?php echo esc_html( $m['celular'] ?? '—' ); ?></td>
        </tr>
        <tr style="border-top:1px solid #F3F4F6">
          <td style="padding:8px 0;color:#6B7280">Correo</td>
          <td style="padding:8px 0"><?php echo esc_html( $m['email'] ?? '—' ); ?></td>
        </tr>
        <tr style="border-top:1px solid #F3F4F6">
          <td style="padding:8px 0;color:#6B7280">Tipo</td>
          <td style="padding:8px 0"><?php echo esc_html( ucfirst( str_replace( '_', ' ', $m['tipo_miembro'] ?? 'nacional' ) ) ); ?></td>
        </tr>
        <tr style="border-top:1px solid #F3F4F6">
          <td style="padding:8px 0;color:#6B7280">Estado</td>
          <td style="padding:8px 0"><?php echo $estado_html; ?></td>
        </tr>
        <tr style="border-top:1px solid #F3F4F6">
          <td style="padding:8px 0;color:#6B7280">Referidos</td>
          <td style="padding:8px 0;font-weight:700"><?php echo esc_html( $total_ref ); ?></td>
        </tr>
        <tr style="border-top:1px solid #F3F4F6">
          <td style="padding:8px 0;color:#6B7280">Puntos</td>
          <td style="padding:8px 0;font-weight:700"><?php echo esc_html( $m['puntos_total'] ?? 0 ); ?></td>
        </tr>
      </table>

      <?php if ( ! empty( $m['codigo_referido_propio'] ) ) : ?>
      <div style="margin-top:18px;padding:14px;background:#F0FDF4;border-radius:8px;border:1px solid #BBF7D0">
        <p style="margin:0 0 6px;font-size:13px;font-weight:700;color:#166534">&#x1F517; Tu enlace de referido</p>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
          <input type="text" value="<?php echo esc_attr( $ref_link ); ?>"
                 id="psp2-ref-link" readonly
                 style="flex:1;font-size:12px;padding:8px;border:1px solid #D1D5DB;border-radius:6px;min-width:0">
          <button onclick="psp2CopyRef()" class="psp2-btn psp2-btn-primary" style="padding:8px 14px;font-size:13px">
            &#x1F4CB; Copiar
          </button>
        </div>
      </div>
      <script>
      function psp2CopyRef() {
        var el = document.getElementById('psp2-ref-link');
        if (!el) return;
        el.select(); el.setSelectionRange(0, 999);
        document.execCommand('copy');
        var btn = el.nextElementSibling;
        if (btn) { btn.textContent = '\u2705 Copiado'; setTimeout(function(){ btn.textContent = '\uD83D\uDCCB Copiar'; }, 2000); }
      }
      </script>
      <?php endif; ?>

      <div style="margin-top:18px;display:flex;gap:10px;flex-wrap:wrap">
        <?php if ( ( $m['estado'] ?? '' ) === 'pendiente_pago' && shortcode_exists( 'psp2_pago' ) ) : ?>
          <a href="<?php echo esc_url( home_url( '/pago/' ) ); ?>" class="psp2-btn psp2-btn-primary">
            &#x1F4B3; Activar membres&iacute;a
          </a>
        <?php endif; ?>
        <a href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>" class="psp2-btn psp2-btn-secondary">
          Cerrar sesi&oacute;n
        </a>
      </div>
    </div>
    <?php
    return ob_get_clean();
}

// ── AJAX: Registro ────────────────────────────────────────────────────────────
add_action( 'wp_ajax_nopriv_psp2_register', 'psp2_ajax_register' );
add_action( 'wp_ajax_psp2_register',        'psp2_ajax_register' );
function psp2_ajax_register(): void {
    // Verificar nonce
    $nonce = sanitize_text_field( wp_unslash( $_POST['psp2_nonce'] ?? '' ) );
    if ( ! wp_verify_nonce( $nonce, 'psp2_nonce' ) ) {
        wp_send_json_error( [ 'message' => 'Nonce inv&aacute;lido. Recarga la p&aacute;gina.' ] );
    }

    // Rate limit: 3 registros por IP cada 10 min
    if ( function_exists( 'psp2_rate_limit' ) && ! psp2_rate_limit( 'psp2_register', 3, 600 ) ) {
        wp_send_json_error( [ 'message' => 'Demasiados intentos. Espera 10 minutos.' ] );
    }

    $nombre    = sanitize_text_field( wp_unslash( $_POST['nombre']    ?? '' ) );
    $celular   = sanitize_text_field( wp_unslash( $_POST['celular']   ?? '' ) );
    $email     = sanitize_email( wp_unslash( $_POST['email']          ?? '' ) );
    $password  = wp_unslash( $_POST['password'] ?? '' );
    $tipo      = sanitize_text_field( wp_unslash( $_POST['tipo_miembro'] ?? 'nacional' ) );
    $prov_id   = sanitize_text_field( wp_unslash( $_POST['provincia_id']      ?? '' ) );
    $dist_id   = sanitize_text_field( wp_unslash( $_POST['distrito_id']       ?? '' ) );
    $corr_id   = sanitize_text_field( wp_unslash( $_POST['corregimiento_id']  ?? '' ) );
    $com_id    = sanitize_text_field( wp_unslash( $_POST['comunidad_id']      ?? '' ) );
    $pais_id   = sanitize_text_field( wp_unslash( $_POST['pais_id']           ?? 'PA' ) );
    $ciudad    = sanitize_text_field( wp_unslash( $_POST['ciudad']            ?? '' ) );
    $ref_code  = sanitize_text_field( wp_unslash( $_POST['ref']               ?? '' ) );

    if ( ! $nombre || ! $celular ) {
        wp_send_json_error( [ 'message' => 'Nombre y tel&eacute;fono son obligatorios.' ] );
    }
    if ( strlen( $password ) < 8 ) {
        wp_send_json_error( [ 'message' => 'La contrase&ntilde;a debe tener al menos 8 caracteres.' ] );
    }

    // Normalizar celular: solo dígitos
    $celular_clean = preg_replace( '/[^0-9]/', '', $celular );
    if ( ! $celular_clean ) {
        wp_send_json_error( [ 'message' => 'N&uacute;mero de tel&eacute;fono inv&aacute;lido.' ] );
    }

    // Verificar duplicado en WP (user_login = celular)
    if ( username_exists( $celular_clean ) ) {
        wp_send_json_error( [ 'message' => 'Este tel&eacute;fono ya est&aacute; registrado. <a href="' . esc_url( wp_login_url() ) . '">Inicia sesi&oacute;n</a>.' ] );
    }
    if ( $email && email_exists( $email ) ) {
        wp_send_json_error( [ 'message' => 'Este correo ya est&aacute; registrado.' ] );
    }

    // Crear usuario WP
    $user_data = [
        'user_login' => $celular_clean,
        'user_pass'  => $password,
        'first_name' => $nombre,
        'role'       => 'subscriber',
    ];
    if ( $email ) {
        $user_data['user_email'] = $email;
    }

    $user_id = wp_insert_user( $user_data );

    if ( is_wp_error( $user_id ) ) {
        wp_send_json_error( [ 'message' => esc_html( $user_id->get_error_message() ) ] );
    }

    // Guardar metadatos en usermeta
    update_user_meta( $user_id, 'psp2_celular', $celular_clean );
    update_user_meta( $user_id, 'psp2_nombre',  $nombre );

    // Crear fila en Supabase miembros
    $miembro_id = null;
    if ( class_exists( 'PSP2_Supabase' ) ) {
        $codigo = function_exists( 'psp2_generar_codigo' ) ? psp2_generar_codigo( 'PSP' ) : strtoupper( 'PSP-' . bin2hex( random_bytes( 5 ) ) );

        $tipos_validos = [ 'nacional', 'internacional', 'hogar_solidario', 'lider', 'voluntario', 'coordinador', 'actor', 'sector', 'productor', 'planton', 'comunicador', 'influencer', 'embajador' ];
        $tipo_sanitized = in_array( $tipo, $tipos_validos, true ) ? $tipo : 'nacional';

        $miembro_data = [
            'wp_user_id'             => $user_id,
            'nombre'                 => $nombre,
            'celular'                => $celular_clean,
            'tipo_miembro'           => $tipo_sanitized,
            'estado'                 => 'pendiente_pago',
            'codigo_referido_propio' => $codigo,
            'tenant_id'              => get_option( 'psp2_tenant_id', 'panama' ),
            'ip_registro'            => function_exists( 'psp2_get_client_ip' ) ? psp2_get_client_ip() : ( sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' ) ),
        ];
        if ( $email ) {
            $miembro_data['email'] = $email;
        }
        if ( $prov_id )  $miembro_data['provincia_id']       = $prov_id;
        if ( $dist_id )  $miembro_data['distrito_id']        = $dist_id;
        if ( $corr_id )  $miembro_data['corregimiento_id']   = $corr_id;
        if ( $com_id )   $miembro_data['comunidad_id']       = $com_id;
        if ( $pais_id )  $miembro_data['pais_id']            = $pais_id;
        if ( $ciudad )   $miembro_data['ciudad']             = $ciudad;

        // Atribución de referido
        if ( $ref_code ) {
            $referidor = PSP2_Supabase::select( 'miembros', [
                'codigo_referido_propio' => 'eq.' . $ref_code,
                'limit'                  => 1,
                'select'                 => 'id',
            ] );
            if ( ! empty( $referidor ) ) {
                $miembro_data['referido_por'] = $referidor[0]['id'];
            }
        }

        $nuevo = PSP2_Supabase::insert( 'miembros', $miembro_data, true );
        if ( $nuevo && isset( $nuevo[0]['id'] ) ) {
            $miembro_id = $nuevo[0]['id'];
            update_user_meta( $user_id, 'psp2_miembro_id', $miembro_id );
        }

        // Log de auditoría
        if ( function_exists( 'psp2_audit_log' ) ) {
            psp2_audit_log( 'registro_wp', [ 'tipo' => $tipo_sanitized ], $miembro_id );
        }
    }

    // Auto-login
    wp_set_current_user( $user_id );
    wp_set_auth_cookie( $user_id, true );

    $fee = (float) get_option( 'psp2_membership_fee', '1.00' );

    wp_send_json_success( [
        'message'    => sprintf( '&#x1F389; &iexcl;Registro exitoso! Para activar tu membres&iacute;a, realiza el pago de B/.%.2f.', $fee ),
        'redirect'   => home_url( '/mi-cuenta/' ),
        'miembro_id' => $miembro_id,
    ] );
}
