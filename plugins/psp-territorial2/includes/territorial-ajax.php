<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * AJAX: devuelve la lista de territorios hijos (JSON).
 * action: psp2_terr_get
 * params: tipo (provincia|distrito|corregimiento|comunidad), parent_id
 *
 * Si el plugin PSP Territorial V2 (psp-territorial-v2/psp-territorial-v2.php) está activo,
 * los datos se obtienen desde su REST API local. En caso contrario, se usa la URL JSON configurada.
 */
add_action( 'wp_ajax_nopriv_psp2_terr_get', 'psp2_terr_ajax_get' );
add_action( 'wp_ajax_psp2_terr_get',        'psp2_terr_ajax_get' );
function psp2_terr_ajax_get(): void {
    $nonce = sanitize_text_field( wp_unslash( $_POST['psp2_nonce'] ?? '' ) );
    if ( ! wp_verify_nonce( $nonce, 'psp2_nonce' ) ) {
        wp_send_json_error( [ 'message' => 'Nonce inválido' ] );
    }

    $tipo      = sanitize_key( $_POST['tipo']      ?? '' );
    $parent_id = sanitize_text_field( wp_unslash( $_POST['parent_id'] ?? '' ) );

    // Validar tipo permitido
    $tipos_validos = [ 'provincia', 'distrito', 'corregimiento', 'comunidad' ];
    if ( ! in_array( $tipo, $tipos_validos, true ) ) {
        wp_send_json_error( [ 'message' => 'Tipo de territorio no válido.' ] );
    }

    // Si PSP Territorial V2 está activo, usar su REST API
    if ( psp2_terr_use_pspv2() ) {
        wp_send_json_success( psp2_terr_from_pspv2( $tipo, $parent_id ) );
    }

    // Fallback: JSON URL configurado en ajustes
    $json_url = get_option( 'psp2_territorial_json_url', '' );

    if ( empty( $json_url ) ) {
        wp_send_json_error( [ 'message' => 'JSON territorial no configurado.' ] );
    }

    // Obtener JSON con caché 1 hora
    $cache_key  = 'psp2_terr_json_' . md5( $json_url );
    $json_data  = get_transient( $cache_key );

    if ( false === $json_data ) {
        $response  = wp_remote_get( $json_url, [ 'timeout' => 15 ] );
        if ( is_wp_error( $response ) ) {
            wp_send_json_error( [ 'message' => 'No se pudo cargar el JSON territorial.' ] );
        }
        $json_data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $json_data ) ) {
            wp_send_json_error( [ 'message' => 'JSON territorial inválido.' ] );
        }
        set_transient( $cache_key, $json_data, HOUR_IN_SECONDS );
    }

    wp_send_json_success( psp2_terr_filter( $json_data, $tipo, $parent_id ) );
}

/**
 * Returns true if the PSP Territorial V2 plugin is active.
 * Result is cached in a static-like variable after the first call.
 */
function psp2_terr_use_pspv2(): bool {
    static $result = null;
    if ( null !== $result ) {
        return $result;
    }
    if ( ! function_exists( 'is_plugin_active' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    $result = is_plugin_active( 'psp-territorial-v2/psp-territorial-v2.php' );
    return $result;
}

/**
 * Fetches territory data from the PSP Territorial V2 REST API via rest_do_request().
 * Maps interno tipo → endpoint:
 *   provincia     → /psp-territorial/v2/provincias
 *   distrito      → /psp-territorial/v2/distritos?parent_id=
 *   corregimiento → /psp-territorial/v2/corregimientos?parent_id=
 *   comunidad     → /psp-territorial/v2/comunidades?parent_id=
 *
 * PSP Territorial V2 returns: { success: true, data: [ { id, name, ... }, ... ] }
 *
 * @param string $tipo      'provincia'|'distrito'|'corregimiento'|'comunidad'
 * @param string $parent_id Numeric parent ID (empty for provincias)
 * @return array            [ [ 'id' => ..., 'nombre' => ... ], ... ]
 */
function psp2_terr_from_pspv2( string $tipo, string $parent_id ): array {
    $endpoint_map = [
        'provincia'     => 'provincias',
        'distrito'      => 'distritos',
        'corregimiento' => 'corregimientos',
        'comunidad'     => 'comunidades',
    ];

    if ( ! isset( $endpoint_map[ $tipo ] ) ) {
        return [];
    }

    $cache_key = 'psp2_terr_v2_' . $tipo . '_' . md5( $parent_id );
    $cached    = get_transient( $cache_key );
    if ( false !== $cached ) {
        return $cached;
    }

    $rest_path = '/psp-territorial/v2/' . $endpoint_map[ $tipo ];
    $request   = new WP_REST_Request( 'GET', $rest_path );
    if ( $parent_id !== '' ) {
        $request->set_param( 'parent_id', absint( $parent_id ) );
    }

    $response = rest_do_request( $request );
    $body     = $response->get_data();

    if ( empty( $body['success'] ) || ! is_array( $body['data'] ) ) {
        set_transient( $cache_key, [], 5 * MINUTE_IN_SECONDS );
        return [];
    }

    $result = array_map( function ( $item ) {
        return [
            'id'     => $item['id']     ?? '',
            'nombre' => $item['name']   ?? $item['nombre'] ?? '',
        ];
    }, $body['data'] );

    set_transient( $cache_key, $result, HOUR_IN_SECONDS );
    return $result;
}

/**
 * Filtra el array JSON de territorios por tipo y parent_id.
 * Se espera que el JSON tenga la estructura:
 * [ { id, nombre, tipo, parent_id }, ... ]
 *
 * @param array  $data
 * @param string $tipo       'provincia'|'distrito'|'corregimiento'|'comunidad'
 * @param string $parent_id  ID del padre (vacío para provincias)
 * @return array
 */
function psp2_terr_filter( array $data, string $tipo, string $parent_id ): array {
    $result = [];
    foreach ( $data as $item ) {
        if ( ! is_array( $item ) ) {
            continue;
        }
        $item_tipo   = $item['tipo']      ?? '';
        $item_parent = (string) ( $item['parent_id'] ?? '' );
        $item_id     = $item['id']        ?? '';
        $item_nombre = $item['nombre']    ?? '';

        // Para provincias no se filtra por parent_id
        if ( $tipo === 'provincia' && $item_tipo === 'provincia' ) {
            $result[] = [ 'id' => $item_id, 'nombre' => $item_nombre ];
            continue;
        }

        if ( $item_tipo === $tipo && $item_parent === $parent_id ) {
            $result[] = [ 'id' => $item_id, 'nombre' => $item_nombre ];
        }
    }
    return $result;
}
