<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * AJAX: devuelve la lista de territorios hijos.
 * action: psp2_terr_get
 * params: tipo (provincia|distrito|corregimiento|comunidad), parent_id
 *
 * Modos soportados (opción psp2_territorial_modo):
 *   json_url   - lee JSON externo configurado en ajustes
 *   pspv2_rest - consulta WP REST API del plugin PSP Territorial V2
 */
add_action( 'wp_ajax_nopriv_psp2_terr_get', 'psp2_terr_ajax_get' );
add_action( 'wp_ajax_psp2_terr_get',        'psp2_terr_ajax_get' );
function psp2_terr_ajax_get(): void {
    $nonce = sanitize_text_field( wp_unslash( $_POST['psp2_nonce'] ?? '' ) );
    if ( ! wp_verify_nonce( $nonce, 'psp2_nonce' ) ) {
        wp_send_json_error( [ 'message' => 'Nonce inv&aacute;lido' ] );
    }

    $tipo      = sanitize_key( wp_unslash( $_POST['tipo']      ?? '' ) );
    $parent_id = sanitize_text_field( wp_unslash( $_POST['parent_id'] ?? '' ) );
    $modo      = get_option( 'psp2_territorial_modo', 'json_url' );

    if ( $modo === 'pspv2_rest' ) {
        $result = psp2_terr_get_from_rest( $tipo, $parent_id );
        wp_send_json_success( $result );
    }

    // Modo json_url (predeterminado)
    $json_url = get_option( 'psp2_territorial_json_url', '' );

    if ( empty( $json_url ) ) {
        wp_send_json_error( [ 'message' => 'JSON territorial no configurado.' ] );
    }

    // Obtener JSON con caché 1 hora
    $cache_key = 'psp2_terr_json_' . md5( $json_url );
    $json_data = get_transient( $cache_key );

    if ( false === $json_data ) {
        $response = wp_remote_get( $json_url, [ 'timeout' => 15 ] );
        if ( is_wp_error( $response ) ) {
            wp_send_json_error( [ 'message' => 'No se pudo cargar el JSON territorial.' ] );
        }
        $json_data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $json_data ) ) {
            wp_send_json_error( [ 'message' => 'JSON territorial inv&aacute;lido.' ] );
        }
        set_transient( $cache_key, $json_data, HOUR_IN_SECONDS );
    }

    $result = psp2_terr_filter( $json_data, $tipo, $parent_id );
    wp_send_json_success( $result );
}

/**
 * Obtiene territorios desde el plugin PSP Territorial V2 vía WP REST API interno.
 * Cachea con transients para performance.
 *
 * @param string $tipo       'provincia'|'distrito'|'corregimiento'|'comunidad'
 * @param string $parent_id  ID del padre (vacío para provincias)
 * @return array<array{id:string,nombre:string}>
 */
function psp2_terr_get_from_rest( string $tipo, string $parent_id ): array {
    $allowed = [ 'provincia', 'distrito', 'corregimiento', 'comunidad' ];
    if ( ! in_array( $tipo, $allowed, true ) ) {
        return [];
    }

    // Mapeo tipo → endpoint del plugin PSP Territorial V2
    $endpoints = [
        'provincia'    => 'provincias',
        'distrito'     => 'distritos',
        'corregimiento'=> 'corregimientos',
        'comunidad'    => 'comunidades',
    ];
    $endpoint = $endpoints[ $tipo ];

    // Clave de caché incluyendo parent
    $cache_key = 'psp2_terr_rest_' . $tipo . '_' . md5( $parent_id );
    $cached    = get_transient( $cache_key );
    if ( false !== $cached ) {
        return $cached;
    }

    // Intentar mediante REST interno (más eficiente, evita HTTP local)
    $request = new WP_REST_Request( 'GET', '/psp-territorial/v2/' . $endpoint );
    if ( $parent_id !== '' ) {
        $request->set_param( 'parent_id', $parent_id );
    }

    $response = rest_do_request( $request );

    $items = [];
    if ( ! is_wp_error( $response ) && $response->get_status() === 200 ) {
        $body = $response->get_data();
        if ( is_array( $body ) ) {
            foreach ( $body as $row ) {
                if ( ! is_array( $row ) ) continue;
                $id     = (string) ( $row['id']     ?? $row['ID']     ?? '' );
                $nombre = (string) ( $row['nombre']  ?? $row['name']   ?? $row['nombre_territorio'] ?? '' );
                if ( $id !== '' && $nombre !== '' ) {
                    $items[] = [ 'id' => $id, 'nombre' => $nombre ];
                }
            }
        }
    } else {
        // Fallback: llamada HTTP si WP REST interno no resolvió (plugin no instalado)
        $url  = rest_url( 'psp-territorial/v2/' . $endpoint );
        if ( $parent_id !== '' ) {
            $url = add_query_arg( 'parent_id', $parent_id, $url );
        }
        $resp = wp_remote_get( $url, [ 'timeout' => 10 ] );
        if ( ! is_wp_error( $resp ) ) {
            $body = json_decode( wp_remote_retrieve_body( $resp ), true );
            if ( is_array( $body ) ) {
                foreach ( $body as $row ) {
                    if ( ! is_array( $row ) ) continue;
                    $id     = (string) ( $row['id']     ?? $row['ID']     ?? '' );
                    $nombre = (string) ( $row['nombre']  ?? $row['name']   ?? $row['nombre_territorio'] ?? '' );
                    if ( $id !== '' && $nombre !== '' ) {
                        $items[] = [ 'id' => $id, 'nombre' => $nombre ];
                    }
                }
            }
        }
    }

    set_transient( $cache_key, $items, HOUR_IN_SECONDS );
    return $items;
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
