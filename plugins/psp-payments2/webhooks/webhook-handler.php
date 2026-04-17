<?php
if (!defined('ABSPATH')) exit;

// REST API webhook endpoints
add_action('rest_api_init', 'psp_register_webhook_routes');
function psp_register_webhook_routes(): void {
    $proveedores = ['paguelofacil', 'yappy', 'ach', 'puntopago'];
    foreach ($proveedores as $proveedor) {
        register_rest_route('psp/v1', "/webhook/$proveedor", [
            'methods'             => 'POST',
            'callback'            => fn($req) => psp_handle_webhook($req, $proveedor),
            'permission_callback' => '__return_true',
        ]);
    }
}

function psp_handle_webhook(WP_REST_Request $request, string $proveedor): WP_REST_Response {
    $payload   = $request->get_body();
    $signature = $request->get_header('x-signature') ?? $request->get_header('x-webhook-signature') ?? '';

    // Log siempre el webhook
    PSP_Supabase::insert('webhooks_logs', [
        'proveedor' => $proveedor,
        'payload'   => $payload,
        'signature' => $signature,
        'ip'        => sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? ''),
        'estado'    => 'recibido',
    ], true);

    $secret = get_option("psp_{$proveedor}_webhook_secret", '');
    if ($secret && !psp_validate_webhook_signature($payload, $signature, $secret)) {
        return new WP_REST_Response(['error' => 'Signature inválida'], 401);
    }

    $data = json_decode($payload, true);
    psp_procesar_pago_confirmado($data, $proveedor);

    return new WP_REST_Response(['ok' => true], 200);
}

function psp_procesar_pago_confirmado(array $data, string $proveedor): void {
    // Mapear campos por proveedor
    $referencia = match($proveedor) {
        'paguelofacil' => $data['orderId']        ?? '',
        'yappy'        => $data['reference']       ?? '',
        'ach'          => $data['transactionRef']  ?? '',
        default        => $data['referencia']       ?? '',
    };

    $estado_proveedor = match($proveedor) {
        'paguelofacil' => $data['status'] ?? '',
        'yappy'        => $data['status'] ?? '',
        default        => $data['estado'] ?? '',
    };

    $aprobado = in_array(strtolower($estado_proveedor), ['approved', 'success', 'completed', 'aprobado']);

    if (!$referencia) return;

    // Actualizar pago
    $nuevo_estado = $aprobado ? 'completado' : 'fallido';
    PSP_Supabase::update('pagos', [
        'estado'            => $nuevo_estado,
        'provider_response' => wp_json_encode($data),
        'transaction_id'    => $data['transactionId'] ?? $data['id'] ?? '',
    ], ['referencia' => 'eq.' . $referencia]);

    // Actualizar log
    PSP_Supabase::update('webhooks_logs', ['estado' => 'procesado'], ['payload' => 'like.%' . $referencia . '%']);

    // Si fue exitoso, disparar post-pago (referidos, ranking, notificación)
    if ($aprobado) {
        $pago = PSP_Supabase::select('pagos', ['referencia' => 'eq.' . $referencia, 'limit' => 1]);
        if ($pago) {
            psp_post_pago_completado($pago[0]);
        }
    }
}

function psp_post_pago_completado(array $pago): void {
    $miembro_id = $pago['miembro_id'];
    $monto      = (float) $pago['monto'];

    // 1. Activar membresía
    PSP_Supabase::update('miembros', ['estado' => 'activo'], ['id' => 'eq.' . $miembro_id]);

    // 2. Sumar puntos (100 pts por $1)
    $puntos = (int)($monto * 100);
    PSP_Supabase::rpc('sumar_puntos', ['p_miembro_id' => $miembro_id, 'p_puntos' => $puntos]);

    // 3. Procesar referido
    $miembro = PSP_Supabase::select('miembros', ['id' => 'eq.' . $miembro_id, 'limit' => 1]);
    if ($miembro && isset($miembro[0]['referido_por'])) {
        $puntos_referido = (int)($monto * 20); // 20% en puntos al referidor
        PSP_Supabase::rpc('sumar_puntos', [
            'p_miembro_id' => $miembro[0]['referido_por'],
            'p_puntos'     => $puntos_referido,
        ]);
        PSP_Supabase::insert('referidos_log', [
            'referidor_id'  => $miembro[0]['referido_por'],
            'referido_id'   => $miembro_id,
            'pago_id'       => $pago['id'],
            'puntos_ganados'=> $puntos_referido,
        ], true);
    }

    // 4. Trigger facturación via Edge Function
    wp_remote_post(PSP_SUPABASE_URL . '/functions/v1/factura-generar', [
        'headers' => [
            'Authorization' => 'Bearer ' . PSP_SUPABASE_SVC,
            'Content-Type'  => 'application/json',
        ],
        'body' => wp_json_encode(['pago_id' => $pago['id']]),
    ]);
}
