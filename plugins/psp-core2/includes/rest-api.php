<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * PSP Core 2 — REST API (namespace: psp/v2)
 *
 * Autenticación: WordPress nativo (cookie session + X-WP-Nonce).
 * Sin dependencia de Supabase JWT.
 *
 * Endpoints:
 *   GET  /wp-json/psp/v2/kpis        → KPIs públicos de campaña
 *   GET  /wp-json/psp/v2/me          → perfil del miembro autenticado
 *   GET  /wp-json/psp/v2/wa-group    → grupos WhatsApp del miembro
 */

add_action( 'rest_api_init', 'psp2_register_rest_routes' );

function psp2_register_rest_routes(): void {
    $ns = PSP2_REST_NS;

    // KPIs públicos
    register_rest_route( $ns, '/kpis', [
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'psp2_rest_kpis',
        'permission_callback' => '__return_true',
    ] );

    // Perfil del miembro autenticado
    register_rest_route( $ns, '/me', [
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'psp2_rest_me',
        'permission_callback' => 'psp2_rest_auth_required',
    ] );

    // Grupos WhatsApp del miembro
    register_rest_route( $ns, '/wa-group', [
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'psp2_rest_wa_group',
        'permission_callback' => 'psp2_rest_auth_required',
    ] );
}

// ── Middleware: requiere sesión WP activa ────────────────────────────────────
function psp2_rest_auth_required(): bool|WP_Error {
    if ( ! is_user_logged_in() ) {
        return new WP_Error(
            'psp2_unauthorized',
            'Debes iniciar sesi&oacute;n para acceder.',
            [ 'status' => 401 ]
        );
    }
    return true;
}

// ── GET /kpis ────────────────────────────────────────────────────────────────
function psp2_rest_kpis(): WP_REST_Response|WP_Error {
    if ( ! class_exists( 'PSP2_Supabase' ) ) {
        return new WP_Error( 'psp2_core_missing', 'PSP Core 2 no inicializado.', [ 'status' => 500 ] );
    }

    $tenant = get_option( 'psp2_tenant_id', 'panama' );

    // Intentar función RPC primero
    $kpis = PSP2_Supabase::rpc( 'get_dashboard_kpis', [ 'p_tenant_id' => $tenant ] );

    if ( ! $kpis ) {
        // Fallback: contar manualmente
        $activos    = PSP2_Supabase::select( 'miembros', [
            'estado'    => 'eq.activo',
            'tenant_id' => 'eq.' . $tenant,
            'select'    => 'id',
        ] );
        $pendientes = PSP2_Supabase::select( 'miembros', [
            'estado'    => 'eq.pendiente_pago',
            'tenant_id' => 'eq.' . $tenant,
            'select'    => 'id',
        ] );
        $kpis = [
            'miembros_activos'    => is_array( $activos )    ? count( $activos )    : 0,
            'miembros_pendientes' => is_array( $pendientes ) ? count( $pendientes ) : 0,
        ];
    }

    return new WP_REST_Response( [
        'success'        => true,
        'kpis'           => $kpis,
        'campaign_start' => get_option( 'psp2_campaign_start', '2026-04-14T00:00:00' ),
        'campaign_end'   => get_option( 'psp2_campaign_end',   '2026-05-18T23:59:59' ),
        'launch_date'    => get_option( 'psp2_launch_date',    '2026-04-14T00:00:00' ),
        'meta_miembros'  => (int) get_option( 'psp2_meta_miembros', 1000000 ),
        'meta_monto'     => (int) get_option( 'psp2_meta_monto',    1000000 ),
        'timestamp'      => gmdate( 'c' ),
    ], 200 );
}

// ── GET /me ──────────────────────────────────────────────────────────────────
function psp2_rest_me(): WP_REST_Response|WP_Error {
    if ( ! class_exists( 'PSP2_Supabase' ) ) {
        return new WP_Error( 'psp2_core_missing', 'PSP Core 2 no inicializado.', [ 'status' => 500 ] );
    }

    $wp_user_id = (int) get_current_user_id();

    $rows = PSP2_Supabase::select( 'miembros', [
        'wp_user_id' => 'eq.' . $wp_user_id,
        'select'     => 'id,nombre,celular,email,tipo_miembro,estado,codigo_referido_propio,puntos_total,nivel,provincia_id,distrito_id,corregimiento_id,comunidad_id,pais_id,ciudad,created_at',
        'limit'      => 1,
    ] );

    if ( empty( $rows ) ) {
        return new WP_Error( 'psp2_not_found', 'Miembro no encontrado.', [ 'status' => 404 ] );
    }

    $m = $rows[0];
    $ref_link = home_url( '/?ref=' . rawurlencode( $m['codigo_referido_propio'] ?? '' ) );

    // Contar referidos
    $refs           = PSP2_Supabase::select( 'referidos_log', [
        'referidor_id' => 'eq.' . $m['id'],
        'select'       => 'id',
    ] );
    $total_referidos = is_array( $refs ) ? count( $refs ) : 0;

    return new WP_REST_Response( [
        'success' => true,
        'miembro' => array_merge( $m, [
            'ref_link'        => $ref_link,
            'total_referidos' => $total_referidos,
        ] ),
    ], 200 );
}

// ── GET /wa-group ─────────────────────────────────────────────────────────────
function psp2_rest_wa_group(): WP_REST_Response|WP_Error {
    if ( ! class_exists( 'PSP2_Supabase' ) ) {
        return new WP_Error( 'psp2_core_missing', 'PSP Core 2 no inicializado.', [ 'status' => 500 ] );
    }

    $wp_user_id = (int) get_current_user_id();

    $rows = PSP2_Supabase::select( 'miembros', [
        'wp_user_id' => 'eq.' . $wp_user_id,
        'select'     => 'id,provincia_id,distrito_id,corregimiento_id,comunidad_id,pais_id',
        'limit'      => 1,
    ] );

    if ( empty( $rows ) ) {
        return new WP_Error( 'psp2_not_found', 'Miembro no encontrado.', [ 'status' => 404 ] );
    }

    $m      = $rows[0];
    $grupos = [];

    // Buscar grupo por nivel de granularidad
    $niveles = [
        $m['comunidad_id']     ?? null,
        $m['corregimiento_id'] ?? null,
        $m['distrito_id']      ?? null,
        $m['provincia_id']     ?? null,
    ];

    foreach ( $niveles as $terr_id ) {
        if ( empty( $terr_id ) ) {
            continue;
        }
        $res = PSP2_Supabase::select( 'whatsapp_grupos', [
            'territorio_id' => 'eq.' . $terr_id,
            'activo'        => 'eq.true',
            'tipo'          => 'eq.territorial',
            'select'        => 'id,nombre,link,tipo,miembros_actual,miembros_max',
            'order'         => 'miembros_actual.asc',
            'limit'         => 1,
        ] );
        if ( ! empty( $res ) ) {
            $grupos[] = array_merge( $res[0], [ 'categoria' => 'territorial' ] );
            break;
        }
    }

    // Grupos generales
    $generales = PSP2_Supabase::select( 'whatsapp_grupos', [
        'activo' => 'eq.true',
        'tipo'   => 'neq.territorial',
        'select' => 'id,nombre,link,tipo,miembros_actual,miembros_max',
        'order'  => 'nombre.asc',
        'limit'  => 5,
    ] );
    if ( ! empty( $generales ) ) {
        foreach ( $generales as $g ) {
            $grupos[] = array_merge( $g, [ 'categoria' => 'general' ] );
        }
    }

    return new WP_REST_Response( [
        'success' => true,
        'grupos'  => $grupos,
    ], 200 );
}
