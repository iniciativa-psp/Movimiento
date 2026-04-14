<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * AJAX: devuelve la lista de territorios hijos (JSON).
 * action: psp2_terr_get
 * params: tipo (distrito|corregimiento|comunidad), parent_id
 */
add_action( 'wp_ajax_nopriv_psp2_terr_get', 'psp2_terr_ajax_get' );
add_action( 'wp_ajax_psp2_terr_get',        'psp2_terr_ajax_get' );
function psp2_terr_ajax_get(): void {
    $nonce = sanitize_text_field( wp_unslash( $_GET['psp2_nonce'] ?? $_POST['psp2_nonce'] ?? '' ) );
    if ( ! wp_verify_nonce( $nonce, 'psp2_nonce' ) ) {
        wp_send_json_error( [ 'message' => 'Nonce inválido' ] );
    }

    $tipo      = sanitize_key( $_REQUEST['tipo']      ?? '' );
    $parent_id = sanitize_text_field( wp_unslash( $_REQUEST['parent_id'] ?? '' ) );
    $json_url  = get_option( 'psp2_territorial_json_url', '' );

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

    $result = psp2_terr_filter( $json_data, $tipo, $parent_id );
    wp_send_json_success( $result );
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
