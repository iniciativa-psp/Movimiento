<?php
/**
 * PSP Core — WP REST API endpoints (namespace: psp/v1)
 *
 * Endpoints disponibles:
 *   GET  /wp-json/psp/v1/me              → perfil del miembro autenticado
 *   GET  /wp-json/psp/v1/wa-group        → grupo WhatsApp asignado al miembro
 *   POST /wp-json/psp/v1/registro        → registro de nuevo miembro (para PWA)
 *   POST /wp-json/psp/v1/pago-confirmar  → confirmar pago y activar membresía
 *   GET  /wp-json/psp/v1/kpis            → KPIs públicos de la campaña
 */

if (!defined('ABSPATH')) exit;

add_action('rest_api_init', 'psp_register_rest_routes');

function psp_register_rest_routes(): void {
    $ns = PSP_REST_NAMESPACE;

    // Perfil del miembro autenticado
    register_rest_route($ns, '/me', [
        'methods'             => 'GET',
        'callback'            => 'psp_rest_me',
        'permission_callback' => 'psp_rest_auth_required',
    ]);

    // Grupo WhatsApp asignado al miembro según territorio
    register_rest_route($ns, '/wa-group', [
        'methods'             => 'GET',
        'callback'            => 'psp_rest_wa_group',
        'permission_callback' => 'psp_rest_auth_required',
    ]);

    // Registro (para PWA sin form HTML)
    register_rest_route($ns, '/registro', [
        'methods'             => 'POST',
        'callback'            => 'psp_rest_registro',
        'permission_callback' => '__return_true',
        'args'                => [
            'nombre'           => ['required' => true,  'sanitize_callback' => 'sanitize_text_field'],
            'celular'          => ['required' => true,  'sanitize_callback' => 'sanitize_text_field'],
            'email'            => ['required' => false, 'sanitize_callback' => 'sanitize_email'],
            'tipo_miembro'     => ['required' => false, 'sanitize_callback' => 'sanitize_text_field', 'default' => 'nacional'],
            'provincia_id'     => ['required' => false, 'sanitize_callback' => 'sanitize_text_field'],
            'distrito_id'      => ['required' => false, 'sanitize_callback' => 'sanitize_text_field'],
            'corregimiento_id' => ['required' => false, 'sanitize_callback' => 'sanitize_text_field'],
            'comunidad_id'     => ['required' => false, 'sanitize_callback' => 'sanitize_text_field'],
            'pais_id'          => ['required' => false, 'sanitize_callback' => 'sanitize_text_field', 'default' => 'PA'],
            'ciudad'           => ['required' => false, 'sanitize_callback' => 'sanitize_text_field'],
            'ref'              => ['required' => false, 'sanitize_callback' => 'sanitize_text_field'],
        ],
    ]);

    // Confirmar pago de membresía (server-side)
    register_rest_route($ns, '/pago-confirmar', [
        'methods'             => 'POST',
        'callback'            => 'psp_rest_pago_confirmar',
        'permission_callback' => 'psp_rest_auth_required',
        'args'                => [
            'metodo'     => ['required' => true,  'sanitize_callback' => 'sanitize_text_field'],
            'monto'      => ['required' => true,  'validate_callback' => 'is_numeric'],
            'referencia' => ['required' => false, 'sanitize_callback' => 'sanitize_text_field'],
            'miembro_id' => ['required' => true,  'sanitize_callback' => 'sanitize_text_field'],
        ],
    ]);

    // KPIs públicos
    register_rest_route($ns, '/kpis', [
        'methods'             => 'GET',
        'callback'            => 'psp_rest_kpis',
        'permission_callback' => '__return_true',
    ]);
}

// ── Middleware: requiere JWT de Supabase en Authorization header ──────────────
function psp_rest_auth_required(WP_REST_Request $request): bool|WP_Error {
    $jwt = psp_extract_jwt($request);
    if (!$jwt) {
        return new WP_Error('psp_unauthorized', 'Se requiere autenticación. Envía el JWT de Supabase en el header Authorization: Bearer <token>.', ['status' => 401]);
    }
    return true;
}

function psp_extract_jwt(WP_REST_Request $request): ?string {
    $header = $request->get_header('Authorization');
    if ($header && preg_match('/Bearer\s+(.+)/i', $header, $m)) {
        return sanitize_text_field($m[1]);
    }
    return null;
}

// ── GET /me ───────────────────────────────────────────────────────────────────
function psp_rest_me(WP_REST_Request $request): WP_REST_Response|WP_Error {
    if (!class_exists('PSP_Supabase')) {
        return new WP_Error('psp_core_missing', 'PSP Core no está inicializado', ['status' => 500]);
    }

    $jwt = psp_extract_jwt($request);

    // Obtener user_id desde Supabase Auth
    $user_res = wp_remote_get(PSP_SUPABASE_URL . '/auth/v1/user', [
        'headers' => [
            'apikey'        => PSP_SUPABASE_KEY,
            'Authorization' => 'Bearer ' . $jwt,
        ],
        'timeout' => 10,
    ]);

    if (is_wp_error($user_res)) {
        return new WP_Error('psp_auth_error', 'Error verificando token', ['status' => 401]);
    }

    $user_data = json_decode(wp_remote_retrieve_body($user_res), true);
    if (empty($user_data['id'])) {
        return new WP_Error('psp_unauthorized', 'Token inválido o expirado', ['status' => 401]);
    }

    $user_id = sanitize_text_field($user_data['id']);

    $miembro = PSP_Supabase::select('miembros', [
        'user_id' => 'eq.' . $user_id,
        'select'  => 'id,nombre,celular,email,tipo_miembro,estado,codigo_referido_propio,puntos_total,nivel,provincia_id,distrito_id,corregimiento_id,comunidad_id,pais_id,ciudad,created_at',
        'limit'   => 1,
    ]);

    if (empty($miembro)) {
        return new WP_Error('psp_not_found', 'Miembro no encontrado. Completa tu registro.', ['status' => 404]);
    }

    $m = $miembro[0];

    // Enlace de referido
    $ref_link = home_url('/?ref=' . urlencode($m['codigo_referido_propio']));

    // Contar referidos directos
    $refs = PSP_Supabase::select('referidos_log', [
        'referidor_id' => 'eq.' . $m['id'],
        'select'       => 'count',
    ]);
    $total_referidos = is_array($refs) ? count($refs) : 0;

    // Ranking del miembro
    $rank_row = PSP_Supabase::select('ranking', [
        'miembro_id' => 'eq.' . $m['id'],
        'select'     => 'posicion_nacional,posicion_provincial',
        'limit'      => 1,
    ]);

    return new WP_REST_Response([
        'success' => true,
        'miembro' => array_merge($m, [
            'ref_link'           => $ref_link,
            'total_referidos'    => $total_referidos,
            'posicion_nacional'  => $rank_row[0]['posicion_nacional'] ?? null,
            'posicion_provincial'=> $rank_row[0]['posicion_provincial'] ?? null,
        ]),
    ], 200);
}

// ── GET /wa-group ─────────────────────────────────────────────────────────────
function psp_rest_wa_group(WP_REST_Request $request): WP_REST_Response|WP_Error {
    if (!class_exists('PSP_Supabase')) {
        return new WP_Error('psp_core_missing', 'PSP Core no está inicializado', ['status' => 500]);
    }

    $jwt = psp_extract_jwt($request);

    $user_res = wp_remote_get(PSP_SUPABASE_URL . '/auth/v1/user', [
        'headers' => [
            'apikey'        => PSP_SUPABASE_KEY,
            'Authorization' => 'Bearer ' . $jwt,
        ],
        'timeout' => 10,
    ]);

    if (is_wp_error($user_res)) {
        return new WP_Error('psp_auth_error', 'Error verificando token', ['status' => 401]);
    }

    $user_data = json_decode(wp_remote_retrieve_body($user_res), true);
    if (empty($user_data['id'])) {
        return new WP_Error('psp_unauthorized', 'Token inválido o expirado', ['status' => 401]);
    }

    $user_id = sanitize_text_field($user_data['id']);

    $miembro = PSP_Supabase::select('miembros', [
        'user_id' => 'eq.' . $user_id,
        'select'  => 'id,provincia_id,distrito_id,corregimiento_id,comunidad_id,pais_id',
        'limit'   => 1,
    ]);

    if (empty($miembro)) {
        return new WP_Error('psp_not_found', 'Miembro no encontrado', ['status' => 404]);
    }

    $m = $miembro[0];
    $grupos = [];

    // Buscar grupo por nivel de granularidad (comunidad → corregimiento → distrito → provincia)
    $niveles = [
        ['campo' => 'comunidad_id',      'territorio_id' => $m['comunidad_id']],
        ['campo' => 'corregimiento_id',  'territorio_id' => $m['corregimiento_id']],
        ['campo' => 'distrito_id',       'territorio_id' => $m['distrito_id']],
        ['campo' => 'provincia_id',      'territorio_id' => $m['provincia_id']],
    ];

    $grupo_territorial = null;
    foreach ($niveles as $nivel) {
        if (empty($nivel['territorio_id'])) continue;
        $res = PSP_Supabase::select('whatsapp_grupos', [
            'territorio_id' => 'eq.' . $nivel['territorio_id'],
            'activo'        => 'eq.true',
            'tipo'          => 'eq.territorial',
            'select'        => 'id,nombre,link,tipo,miembros_actual,miembros_max',
            'order'         => 'miembros_actual.asc',
            'limit'         => 1,
        ]);
        if (!empty($res)) {
            $grupo_territorial = $res[0];
            break;
        }
    }

    if ($grupo_territorial) {
        $grupos[] = array_merge($grupo_territorial, ['categoria' => 'territorial']);
    }

    // Grupos sectoriales/generales (no territoriales)
    $generales = PSP_Supabase::select('whatsapp_grupos', [
        'activo'  => 'eq.true',
        'tipo'    => 'neq.territorial',
        'select'  => 'id,nombre,link,tipo,miembros_actual,miembros_max',
        'order'   => 'nombre.asc',
        'limit'   => 5,
    ]);

    if (!empty($generales)) {
        foreach ($generales as $g) {
            $grupos[] = array_merge($g, ['categoria' => 'general']);
        }
    }

    return new WP_REST_Response([
        'success' => true,
        'grupos'  => $grupos,
    ], 200);
}

// ── POST /registro ────────────────────────────────────────────────────────────
function psp_rest_registro(WP_REST_Request $request): WP_REST_Response|WP_Error {
    if (!class_exists('PSP_Supabase')) {
        return new WP_Error('psp_core_missing', 'PSP Core no está inicializado', ['status' => 500]);
    }

    // Rate limiting básico por IP
    $ip = sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? '');
    if (function_exists('psp_rate_limit') && !psp_rate_limit('rest_registro_' . md5($ip), 3, 300)) {
        return new WP_Error('psp_rate_limit', 'Demasiados intentos. Espera 5 minutos.', ['status' => 429]);
    }

    $nombre           = $request->get_param('nombre');
    $celular          = $request->get_param('celular');
    $email            = $request->get_param('email');
    $tipo_miembro     = $request->get_param('tipo_miembro') ?: 'nacional';
    $provincia_id     = $request->get_param('provincia_id');
    $distrito_id      = $request->get_param('distrito_id');
    $corregimiento_id = $request->get_param('corregimiento_id');
    $comunidad_id     = $request->get_param('comunidad_id');
    $pais_id          = $request->get_param('pais_id') ?: 'PA';
    $ciudad           = $request->get_param('ciudad');
    $ref_code         = $request->get_param('ref');

    if (!$nombre || !$celular) {
        return new WP_Error('psp_validation', 'Nombre y celular son obligatorios', ['status' => 400]);
    }

    // Verificar duplicado
    $existe_celular = PSP_Supabase::select('miembros', ['celular' => 'eq.' . $celular, 'limit' => 1]);
    if ($existe_celular) {
        return new WP_Error('psp_duplicate', 'Este celular ya está registrado', ['status' => 409]);
    }
    if ($email) {
        $existe_email = PSP_Supabase::select('miembros', ['email' => 'eq.' . $email, 'limit' => 1]);
        if ($existe_email) {
            return new WP_Error('psp_duplicate', 'Este email ya está registrado', ['status' => 409]);
        }
    }

    $codigo = function_exists('psp_generar_codigo') ? psp_generar_codigo('PSP') : strtoupper('PSP-' . bin2hex(random_bytes(4)));

    $data = [
        'nombre'              => $nombre,
        'celular'             => $celular,
        'email'               => $email ?: null,
        'tipo_miembro'        => in_array($tipo_miembro, ['nacional','internacional','actor','sector','hogar_solidario','productor','planton','comunicador','influencer','embajador','lider','voluntario','coordinador'], true) ? $tipo_miembro : 'nacional',
        'provincia_id'        => $provincia_id ?: null,
        'distrito_id'         => $distrito_id ?: null,
        'corregimiento_id'    => $corregimiento_id ?: null,
        'comunidad_id'        => $comunidad_id ?: null,
        'pais_id'             => $pais_id,
        'ciudad'              => $ciudad ?: null,
        'codigo_referido_propio' => $codigo,
        'tenant_id'           => get_option('psp_tenant_id', 'panama'),
        'estado'              => 'pendiente_pago',
        'ip_registro'         => $ip,
    ];

    // Resolver referidor
    if ($ref_code) {
        $referidor = PSP_Supabase::select('miembros', ['codigo_referido_propio' => 'eq.' . $ref_code, 'limit' => 1]);
        if (!empty($referidor)) {
            $data['referido_por'] = $referidor[0]['id'];
        }
    }

    $nuevo = PSP_Supabase::insert('miembros', $data, true);
    if (!$nuevo) {
        return new WP_Error('psp_db_error', 'Error al registrar. Intenta de nuevo.', ['status' => 500]);
    }

    $miembro_id = $nuevo[0]['id'];

    // Log de auditoría
    if (function_exists('psp_audit_log')) {
        psp_audit_log('registro_rest', ['tipo' => $tipo_miembro], $miembro_id);
    }

    $fee = PSP_MEMBERSHIP_FEE;

    return new WP_REST_Response([
        'success'    => true,
        'miembro_id' => $miembro_id,
        'codigo'     => $codigo,
        'ref_link'   => home_url('/?ref=' . urlencode($codigo)),
        'mensaje'    => sprintf('¡Registro exitoso! Para activar tu membresía realiza el pago de B/.%.2f.', $fee),
        'pago_requerido' => [
            'monto'   => $fee,
            'moneda'  => 'USD',
            'metodos' => ['yappy', 'clave', 'tarjeta', 'puntopago', 'paypal', 'transferencia', 'efectivo'],
        ],
    ], 201);
}

// ── POST /pago-confirmar ──────────────────────────────────────────────────────
function psp_rest_pago_confirmar(WP_REST_Request $request): WP_REST_Response|WP_Error {
    if (!class_exists('PSP_Supabase')) {
        return new WP_Error('psp_core_missing', 'PSP Core no está inicializado', ['status' => 500]);
    }

    $jwt = psp_extract_jwt($request);

    // Verificar JWT con Supabase
    $user_res = wp_remote_get(PSP_SUPABASE_URL . '/auth/v1/user', [
        'headers' => [
            'apikey'        => PSP_SUPABASE_KEY,
            'Authorization' => 'Bearer ' . $jwt,
        ],
        'timeout' => 10,
    ]);

    if (is_wp_error($user_res)) {
        return new WP_Error('psp_auth_error', 'Error verificando token', ['status' => 401]);
    }

    $user_data = json_decode(wp_remote_retrieve_body($user_res), true);
    if (empty($user_data['id'])) {
        return new WP_Error('psp_unauthorized', 'Token inválido o expirado', ['status' => 401]);
    }

    $metodos_validos = ['yappy', 'clave', 'tarjeta_bg', 'puntopago', 'paypal', 'transferencia_nacional', 'transferencia_internacional', 'efectivo'];
    $metodo      = $request->get_param('metodo');
    $monto       = (float) $request->get_param('monto');
    $referencia  = $request->get_param('referencia') ?: '';
    $miembro_id  = $request->get_param('miembro_id');

    if (!in_array($metodo, $metodos_validos, true)) {
        return new WP_Error('psp_validation', 'Método de pago no válido', ['status' => 400]);
    }

    $fee_min = PSP_MEMBERSHIP_FEE;
    if ($monto < $fee_min) {
        return new WP_Error('psp_validation', sprintf('El monto mínimo de membresía es B/.%.2f', $fee_min), ['status' => 400]);
    }

    // Verificar que el miembro pertenece al usuario autenticado
    $miembro = PSP_Supabase::select('miembros', [
        'id'      => 'eq.' . $miembro_id,
        'user_id' => 'eq.' . $user_data['id'],
        'limit'   => 1,
    ]);

    if (empty($miembro)) {
        return new WP_Error('psp_forbidden', 'No tienes permiso para confirmar este pago', ['status' => 403]);
    }

    if ($miembro[0]['estado'] === 'activo') {
        return new WP_Error('psp_already_active', 'Esta membresía ya está activa', ['status' => 409]);
    }

    // Registrar pago con estado pendiente_verificacion (se activa manualmente o via webhook)
    $pago = PSP_Supabase::insert('pagos', [
        'miembro_id'  => $miembro_id,
        'tenant_id'   => get_option('psp_tenant_id', 'panama'),
        'monto'       => $monto,
        'moneda'      => 'USD',
        'metodo'      => $metodo,
        'referencia'  => $referencia ?: null,
        'estado'      => psp_pago_requiere_verificacion($metodo) ? 'pendiente_verificacion' : 'pendiente',
        'concepto'    => 'membresia',
    ], true);

    if (!$pago) {
        return new WP_Error('psp_db_error', 'Error registrando pago. Intenta de nuevo.', ['status' => 500]);
    }

    // Para métodos con confirmación automática (placeholders para cuando haya API real)
    $mensaje = 'Pago registrado. Será verificado y tu membresía se activará en breve.';

    if (function_exists('psp_audit_log')) {
        psp_audit_log('pago_registrado', ['pago_id' => $pago[0]['id'], 'metodo' => $metodo, 'monto' => $monto], $miembro_id);
    }

    return new WP_REST_Response([
        'success'  => true,
        'pago_id'  => $pago[0]['id'],
        'estado'   => $pago[0]['estado'],
        'mensaje'  => $mensaje,
    ], 201);
}

function psp_pago_requiere_verificacion(string $metodo): bool {
    // Métodos que requieren confirmación manual
    return in_array($metodo, ['transferencia_nacional', 'transferencia_internacional', 'efectivo'], true);
}

// ── GET /kpis ─────────────────────────────────────────────────────────────────
function psp_rest_kpis(WP_REST_Request $request): WP_REST_Response|WP_Error {
    if (!class_exists('PSP_Supabase')) {
        return new WP_Error('psp_core_missing', 'PSP Core no está inicializado', ['status' => 500]);
    }

    // Intentar desde función Supabase (más eficiente)
    $kpis = PSP_Supabase::rpc('get_dashboard_kpis', ['p_tenant_id' => get_option('psp_tenant_id', 'panama')]);

    if (!$kpis) {
        // Fallback manual
        $miembros   = PSP_Supabase::select('miembros',   ['estado' => 'eq.activo',   'tenant_id' => 'eq.' . get_option('psp_tenant_id', 'panama'), 'select' => 'count']);
        $pendientes = PSP_Supabase::select('miembros',   ['estado' => 'eq.pendiente_pago', 'tenant_id' => 'eq.' . get_option('psp_tenant_id', 'panama'), 'select' => 'count']);

        $kpis = [
            'miembros_activos'   => is_array($miembros)   ? count($miembros)   : 0,
            'miembros_pendientes'=> is_array($pendientes) ? count($pendientes) : 0,
        ];
    }

    return new WP_REST_Response([
        'success'        => true,
        'kpis'           => $kpis,
        'campaign_start' => PSP_CAMPAIGN_START,
        'campaign_end'   => PSP_CAMPAIGN_END,
        'meta_miembros'  => (int) get_option('psp_meta_objetivo_miembros', 1000000),
        'meta_monto'     => (int) get_option('psp_meta_objetivo_monto',    1000000),
        'timestamp'      => gmdate('c'),
    ], 200);
}
