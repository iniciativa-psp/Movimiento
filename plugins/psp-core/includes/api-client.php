<?php
if (!defined('ABSPATH')) exit;

/**
 * Cliente HTTP para Supabase REST API
 */
class PSP_Supabase {

    private static string $url;
    private static string $key;
    private static string $svc;

    public static function init(): void {
        self::$url = PSP_SUPABASE_URL;
        self::$key = PSP_SUPABASE_KEY;
        self::$svc = PSP_SUPABASE_SVC;
    }

    /**
     * @param string $endpoint   tabla o RPC, ej: "miembros" o "rpc/fn_name"
     * @param string $method     GET|POST|PATCH|DELETE
     * @param array  $data       body para POST/PATCH
     * @param array  $params     query params (filtros, select, order, limit)
     * @param bool   $service    usar service key (bypass RLS)
     */
    public static function request(
        string $endpoint,
        string $method = 'GET',
        array  $data   = [],
        array  $params = [],
        bool   $service = false
    ) {
        self::init();

        $apikey = $service ? self::$svc : self::$key;
        $url    = self::$url . '/rest/v1/' . $endpoint;

        if ($params) {
            $url .= '?' . http_build_query($params);
        }

        $args = [
            'method'  => $method,
            'timeout' => 15,
            'headers' => [
                'apikey'        => $apikey,
                'Authorization' => 'Bearer ' . $apikey,
                'Content-Type'  => 'application/json',
                'Prefer'        => 'return=representation',
            ],
        ];

        if ($data && in_array($method, ['POST', 'PATCH', 'PUT'])) {
            $args['body'] = wp_json_encode($data);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            error_log('[PSP API ERROR] ' . $response->get_error_message());
            return null;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code >= 400) {
            error_log("[PSP API HTTP $code] " . wp_json_encode($body));
            return null;
        }

        return $body;
    }

    public static function select(string $table, array $params = []): ?array {
        return self::request($table, 'GET', [], $params);
    }

    public static function insert(string $table, array $data, bool $svc = false): ?array {
        return self::request($table, 'POST', $data, [], $svc);
    }

    public static function update(string $table, array $data, array $filter): ?array {
        return self::request($table . '?' . http_build_query($filter), 'PATCH', $data, [], true);
    }

    public static function rpc(string $fn, array $params = []): mixed {
        return self::request('rpc/' . $fn, 'POST', $params, [], true);
    }

    /** Llamada autenticada con JWT del usuario actual */
    public static function auth_request(string $endpoint, string $jwt, string $method = 'GET', array $data = []) {
        self::init();
        $url  = self::$url . '/rest/v1/' . $endpoint;
        $args = [
            'method'  => $method,
            'timeout' => 15,
            'headers' => [
                'apikey'        => self::$key,
                'Authorization' => 'Bearer ' . $jwt,
                'Content-Type'  => 'application/json',
                'Prefer'        => 'return=representation',
            ],
        ];
        if ($data) $args['body'] = wp_json_encode($data);
        $response = wp_remote_request($url, $args);
        if (is_wp_error($response)) return null;
        return json_decode(wp_remote_retrieve_body($response), true);
    }
}
