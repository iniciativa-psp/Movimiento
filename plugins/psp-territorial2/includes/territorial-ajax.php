<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * AJAX: devuelve la lista de territorios hijos.
 * action: psp2_terr_get
 * params: tipo (provincia|distrito|corregimiento|comunidad), parent_id
 *
 * Modos soportados (opción psp2_territorial_modo):
 *   bundled    - lee el JSON local incluido en el plugin (por defecto)
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
    $modo      = get_option( 'psp2_territorial_modo', 'pspv2_rest' );

    // Auto-upgrade: when PSP Territorial V2 is active, always use pspv2_rest regardless of saved setting.
    if ( psp2_terr_use_pspv2() ) {
        $modo = 'pspv2_rest';
    }

    if ( $modo === 'pspv2_rest' ) {
        // psp2_terr_from_pspv2() correctly handles the PSP V2 envelope {success,count,data}
        $result = psp2_terr_from_pspv2( $tipo, $parent_id );
        wp_send_json_success( $result );
        return;
    }

    if ( $modo === 'bundled' ) {
        $result = psp2_terr_get_bundled( $tipo, $parent_id );
        wp_send_json_success( $result );
        return;
    }

    // Modo json_url
    $json_url = get_option( 'psp2_territorial_json_url', '' );

    if ( empty( $json_url ) ) {
        // Fallback: si no hay URL configurada, usar el JSON bundled
        $result = psp2_terr_get_bundled( $tipo, $parent_id );
        wp_send_json_success( $result );
        return;
    }

    // Obtener JSON con caché 1 hora
    $cache_key = 'psp2_terr_json_' . md5( $json_url );
    $json_data = get_transient( $cache_key );

    if ( false === $json_data ) {
        $response = wp_remote_get( $json_url, [ 'timeout' => 15 ] );
        if ( is_wp_error( $response ) ) {
            wp_send_json_error( [ 'message' => 'No se pudo cargar el JSON territorial.' ] );
        }
        $raw = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $raw ) ) {
            wp_send_json_error( [ 'message' => 'JSON territorial inv&aacute;lido.' ] );
        }
        // Soportar tanto JSON plano (array) como { data: [...] }
        $json_data = psp2_terr_extract_rows( $raw );
        set_transient( $cache_key, $json_data, HOUR_IN_SECONDS );
    }

    wp_send_json_success( psp2_terr_filter( $json_data, $tipo, $parent_id ) );
}

/**
 * Obtiene territorios desde el JSON local integrado en el plugin.
 * Carga el archivo una vez y almacena el resultado normalizado en un transient (24h).
 *
 * El JSON bundled tiene campos en inglés (type/name/parent_id):
 *   type: province|district|corregimiento|community
 *
 * Se mapea a los tipos internos:
 *   province → provincia, district → distrito,
 *   corregimiento → corregimiento, community → comunidad
 *
 * @param string $tipo       'provincia'|'distrito'|'corregimiento'|'comunidad'
 * @param string $parent_id  ID del padre (vacío para provincias)
 * @return array<array{id:string,nombre:string}>
 */
function psp2_terr_get_bundled( string $tipo, string $parent_id ): array {
    $allowed = [ 'provincia', 'distrito', 'corregimiento', 'comunidad' ];
    if ( ! in_array( $tipo, $allowed, true ) ) {
        return [];
    }

    // Mapeo tipo ES → tipo EN del dataset bundled
    $type_map_es_to_en = [
        'provincia'     => 'province',
        'distrito'      => 'district',
        'corregimiento' => 'corregimiento',
        'comunidad'     => 'community',
    ];
    $eng_type = $type_map_es_to_en[ $tipo ];

    // Caché por tipo + parent_id (24 horas)
    $cache_key = 'psp2_terr_bundled_' . $tipo . '_' . ( $parent_id !== '' ? md5( $parent_id ) : 'root' );
    $cached    = get_transient( $cache_key );
    if ( false !== $cached ) {
        return $cached;
    }

    // Cargar el dataset completo desde caché o disco
    $all_rows = psp2_terr_load_bundled_all();

    // Filtrar por type y parent_id
    $result = [];
    foreach ( $all_rows as $row ) {
        if ( ( $row['type'] ?? '' ) !== $eng_type ) {
            continue;
        }
        $row_parent = isset( $row['parent_id'] ) ? (string) $row['parent_id'] : '';
        if ( $tipo === 'provincia' ) {
            // Provincias: parent_id es null en el dataset
            if ( $row_parent !== '' && $row_parent !== '0' ) {
                continue;
            }
        } else {
            if ( $row_parent !== $parent_id ) {
                continue;
            }
        }
        $result[] = [
            'id'     => (string) $row['id'],
            'nombre' => (string) ( $row['name'] ?? '' ),
        ];
    }

    set_transient( $cache_key, $result, 24 * HOUR_IN_SECONDS );
    return $result;
}

/**
 * Carga y normaliza todos los registros del JSON bundled.
 * Almacena el array completo en un transient (24h) para evitar
 * decodificar el archivo en cada request.
 *
 * @return array  Array plano de items: [ { id, name, type, parent_id, ... }, ... ]
 */
function psp2_terr_load_bundled_all(): array {
    $cache_key = 'psp2_terr_bundled_all';
    $cached    = get_transient( $cache_key );
    if ( false !== $cached ) {
        return $cached;
    }

    $file = PSP2_TERR_DIR . 'assets/data/panama_full_geography.clean.json';
    if ( ! file_exists( $file ) ) {
        return [];
    }

    $contents = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
    if ( false === $contents ) {
        return [];
    }

    $decoded = json_decode( $contents, true );
    if ( ! is_array( $decoded ) ) {
        return [];
    }

    // El JSON bundled viene envuelto: { meta: {...}, data: [...] }
    $rows = psp2_terr_extract_rows( $decoded );

    set_transient( $cache_key, $rows, 24 * HOUR_IN_SECONDS );
    return $rows;
}

/**
 * Extrae el array de registros de un JSON que puede ser:
 *   - un array plano: [ {...}, ... ]
 *   - un objeto con clave 'data': { "data": [...], ... }
 * También normaliza campos en inglés (name, type) a español (nombre, tipo)
 * para compatibilidad con psp2_terr_filter().
 *
 * @param array $raw
 * @return array
 */
function psp2_terr_extract_rows( array $raw ): array {
    // Si es un objeto envelope { data: [...] }
    if ( isset( $raw['data'] ) && is_array( $raw['data'] ) ) {
        $rows = $raw['data'];
    } else {
        $rows = $raw;
    }

    // Normalizar campos: aceptar tanto inglés como español
    $normalized = [];
    foreach ( $rows as $row ) {
        if ( ! is_array( $row ) ) {
            continue;
        }
        // Mantener todos los campos originales y añadir aliases
        if ( ! isset( $row['nombre'] ) && isset( $row['name'] ) ) {
            $row['nombre'] = $row['name'];
        }
        if ( ! isset( $row['tipo'] ) && isset( $row['type'] ) ) {
            // Mapear tipos inglés → español para psp2_terr_filter
            $type_map = [
                'province'      => 'provincia',
                'district'      => 'distrito',
                'corregimiento' => 'corregimiento',
                'community'     => 'comunidad',
            ];
            $row['tipo'] = $type_map[ $row['type'] ] ?? $row['type'];
        }
        $normalized[] = $row;
    }
    return $normalized;
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
