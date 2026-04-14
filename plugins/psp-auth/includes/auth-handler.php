<?php
if (!defined('ABSPATH')) exit;

// AJAX: registro
add_action('wp_ajax_nopriv_psp_registro', 'psp_ajax_registro');
add_action('wp_ajax_psp_registro',        'psp_ajax_registro');
function psp_ajax_registro(): void {
    if (!psp_verify_nonce()) wp_send_json_error(['message' => 'Sesión inválida']);
    if (!psp_rate_limit('registro', 3, 300)) wp_send_json_error(['message' => 'Demasiados intentos']);

    $nombre           = sanitize_text_field($_POST['nombre']           ?? '');
    $celular          = sanitize_text_field($_POST['celular']          ?? '');
    $email            = sanitize_email($_POST['email']                 ?? '');
    $password         = $_POST['password']                             ?? '';
    $tipo_miembro     = sanitize_text_field($_POST['tipo_miembro']     ?? 'nacional');
    $provincia_id     = sanitize_text_field($_POST['provincia_id']     ?? '');
    $distrito_id      = sanitize_text_field($_POST['distrito_id']      ?? '');
    $corregimiento_id = sanitize_text_field($_POST['corregimiento_id'] ?? '');
    $comunidad_id     = sanitize_text_field($_POST['comunidad_id']     ?? '');
    $pais_id          = sanitize_text_field($_POST['pais_id']          ?? 'PA');
    $ciudad           = sanitize_text_field($_POST['ciudad']           ?? '');
    $codigo_referido  = sanitize_text_field($_POST['codigo_referido']  ?? '');

    if (!$nombre || !$celular) {
        wp_send_json_error(['message' => 'Nombre y celular son obligatorios']);
    }
    if (!$email) {
        wp_send_json_error(['message' => 'El correo electrónico es obligatorio']);
    }
    if (strlen($password) < 8) {
        wp_send_json_error(['message' => 'La contraseña debe tener al menos 8 caracteres']);
    }

    // Verificar duplicados en Supabase
    $existe_celular = PSP_Supabase::select('miembros', ['celular' => 'eq.' . $celular, 'limit' => 1]);
    if ($existe_celular) {
        wp_send_json_error(['message' => 'Este celular ya está registrado']);
    }
    $existe_email = PSP_Supabase::select('miembros', ['email' => 'eq.' . $email, 'limit' => 1]);
    if ($existe_email) {
        wp_send_json_error(['message' => 'Este correo ya está registrado']);
    }

    // Verificar que el email no esté ya en WordPress
    if (email_exists($email)) {
        wp_send_json_error(['message' => 'Este correo ya tiene una cuenta. Inicia sesión.']);
    }

    // Derivar user_login del email (parte antes del @, con sufijo único si ya existe)
    $at_pos = strpos($email, '@');
    $user_login_base = $at_pos !== false
        ? sanitize_user(substr($email, 0, $at_pos), true)
        : 'miembro';
    if (empty($user_login_base)) {
        $user_login_base = 'miembro';
    }
    $user_login = $user_login_base;
    $suffix     = 1;
    while (username_exists($user_login)) {
        $user_login = $user_login_base . $suffix;
        $suffix++;
    }

    $wp_user_id = wp_create_user($user_login, $password, $email);
    if (is_wp_error($wp_user_id)) {
        wp_send_json_error(['message' => 'Error creando cuenta: ' . $wp_user_id->get_error_message()]);
    }

    // Actualizar nombre para mostrar
    wp_update_user([
        'ID'           => $wp_user_id,
        'display_name' => $nombre,
        'first_name'   => $nombre,
    ]);

    // Registrar miembro en Supabase con wp_user_id
    $codigo = psp_generar_codigo('PSP');

    $tipos_validos = ['nacional','internacional','actor','sector','hogar_solidario','productor','planton',
                      'comunicador','influencer','embajador','lider','voluntario','coordinador'];
    $supabase_data = [
        'nombre'                 => $nombre,
        'celular'                => $celular,
        'email'                  => $email,
        'tipo_miembro'           => in_array($tipo_miembro, $tipos_validos, true) ? $tipo_miembro : 'nacional',
        'provincia_id'           => $provincia_id ?: null,
        'distrito_id'            => $distrito_id  ?: null,
        'corregimiento_id'       => $corregimiento_id ?: null,
        'comunidad_id'           => $comunidad_id ?: null,
        'pais_id'                => $pais_id ?: 'PA',
        'ciudad'                 => $ciudad ?: null,
        'codigo_referido_propio' => $codigo,
        'tenant_id'              => get_option('psp_tenant_id', 'panama'),
        'estado'                 => 'pendiente_pago',
        'wp_user_id'             => $wp_user_id,
        'ip_registro'            => function_exists('psp_get_client_ip') ? psp_get_client_ip() : sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? ''),
    ];

    // Resolver referidor
    if ($codigo_referido) {
        $referidor = PSP_Supabase::select('miembros', ['codigo_referido_propio' => 'eq.' . $codigo_referido, 'limit' => 1]);
        if ($referidor) {
            $supabase_data['referido_por'] = $referidor[0]['id'];
        }
    }

    $nuevo = PSP_Supabase::insert('miembros', $supabase_data, true);
    if (!$nuevo) {
        // Revertir creación del usuario WP si Supabase falla
        wp_delete_user($wp_user_id);
        wp_send_json_error(['message' => 'Error al registrar. Intenta de nuevo.']);
    }

    $miembro_id = $nuevo[0]['id'];

    if (function_exists('psp_audit_log')) {
        psp_audit_log('registro', ['tipo' => $tipo_miembro], $miembro_id);
    }

    // Auto-login: establecer sesión de WordPress
    wp_set_current_user($wp_user_id);
    wp_set_auth_cookie($wp_user_id, true);

    wp_send_json_success([
        'codigo'     => $codigo,
        'miembro_id' => $miembro_id,
        'wp_user_id' => $wp_user_id,
    ]);
}
