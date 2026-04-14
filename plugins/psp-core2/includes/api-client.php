<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * PSP2_Supabase — cliente HTTP para Supabase REST API.
 * Usa wp_remote_request (no depende de Supabase Auth / psp_jwt).
 */
class PSP2_Supabase {

    /** Realiza una petición al REST API de Supabase.
     *
     * @param string $endpoint  Tabla o RPC, ej: "miembros" o "rpc/fn_name"
     * @param string $method    GET|POST|PATCH|DELETE
     * @param array  $data      Body para POST/PATCH
     * @param array  $params    Query params (filtros PostgREST)
     * @param bool   $service   Usar service-role key (bypass RLS)
     * @return array|null       Array decodificado o null en error
     */
    public static function request(
        string $endpoint,
        string $method  = 'GET',
        array  $data    = [],
        array  $params  = [],
        bool   $service = false
    ): ?array {
        $base_url = get_option( 'psp2_supabase_url', '' );
        $anon_key = get_option( 'psp2_supabase_anon_key', '' );
        $svc_key  = get_option( 'psp2_supabase_service_key', '' );

        if ( empty( $base_url ) ) {
            error_log( '[PSP2 API] Supabase URL no configurada.' );
            return null;
        }

        $apikey = $service ? $svc_key : $anon_key;
        if ( empty( $apikey ) ) {
            error_log( '[PSP2 API] API key no configurada (service=' . ($service ? 'true' : 'false') . ').' );
            return null;
        }

        $url = trailingslashit( $base_url ) . 'rest/v1/' . $endpoint;
        if ( $params ) {
            $url .= '?' . http_build_query( $params );
        }

        $args = [
            'method'  => strtoupper( $method ),
            'timeout' => 15,
            'headers' => [
                'apikey'        => $apikey,
                'Authorization' => 'Bearer ' . $apikey,
                'Content-Type'  => 'application/json',
                'Prefer'        => 'return=representation',
            ],
        ];

        if ( $data && in_array( strtoupper( $method ), [ 'POST', 'PATCH', 'PUT' ], true ) ) {
            $args['body'] = wp_json_encode( $data );
        }

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            error_log( '[PSP2 API ERROR] ' . $response->get_error_message() );
            return null;
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code >= 400 ) {
            error_log( "[PSP2 API HTTP {$code}] " . wp_json_encode( $body ) );
            return null;
        }

        return is_array( $body ) ? $body : null;
    }

    /** SELECT (GET) sobre una tabla con filtros PostgREST. */
    public static function select( string $table, array $params = [] ): ?array {
        return self::request( $table, 'GET', [], $params );
    }

    /** INSERT (POST) en una tabla. Devuelve la fila insertada. */
    public static function insert( string $table, array $data, bool $svc = false ): ?array {
        return self::request( $table, 'POST', $data, [], $svc );
    }

    /** UPDATE (PATCH) en una tabla con filtros en query string. */
    public static function update( string $table, array $data, array $filter, bool $svc = true ): ?array {
        $qs = http_build_query( $filter );
        return self::request( $table . '?' . $qs, 'PATCH', $data, [], $svc );
    }

    /** DELETE en una tabla con filtros en query string. */
    public static function delete( string $table, array $filter, bool $svc = true ): ?array {
        $qs = http_build_query( $filter );
        return self::request( $table . '?' . $qs, 'DELETE', [], [], $svc );
    }

    /** RPC: llamada a función PostgreSQL via POST. */
    public static function rpc( string $fn, array $params = [], bool $svc = true ): ?array {
        return self::request( 'rpc/' . $fn, 'POST', $params, [], $svc );
    }
}
