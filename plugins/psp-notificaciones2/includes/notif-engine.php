<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Motor central de notificaciones PSP
 * Soporta: email, sms (Twilio), whatsapp (360dialog), push (Supabase), interna
 */
class PSP_Notificaciones {

    /**
     * Enviar notificación a un miembro por uno o más canales
     *
     * @param string $miembro_id UUID
     * @param string $tipo       pago|referido|nivel|bienvenida|ranking|reto|sistema
     * @param string $mensaje    Texto del mensaje
     * @param array  $canales    ['email','sms','whatsapp','push','interna']
     * @param array  $datos      Datos adicionales para templates
     */
    public static function enviar( $miembro_id, $tipo, $mensaje, $canales = ['interna'], $datos = [] ) {
        if ( ! get_option('psp_notif_activo','1') ) return;
        if ( ! class_exists('PSP_Supabase') ) return;

        // Guardar notificación interna siempre
        PSP_Supabase::insert('notificaciones', [
            'miembro_id' => $miembro_id,
            'tipo'       => $tipo,
            'mensaje'    => $mensaje,
            'datos'      => wp_json_encode($datos),
            'leida'      => false,
            'tenant_id'  => get_option('psp_tenant_id','panama'),
        ], true);

        // Obtener datos del miembro si hay canales externos
        $miembro = null;
        if ( array_intersect($canales, ['email','sms','whatsapp','push']) ) {
            $rows = PSP_Supabase::select('miembros', ['id'=>'eq.'.$miembro_id,'select'=>'nombre,email,celular','limit'=>1]);
            $miembro = $rows ? $rows[0] : null;
        }

        if ( ! $miembro ) return;

        foreach ( $canales as $canal ) {
            switch ($canal) {
                case 'email':
                    if ( $miembro['email'] ) {
                        self::enviarEmail($miembro['email'], $miembro['nombre'], $tipo, $mensaje, $datos);
                    }
                    break;
                case 'sms':
                    if ( $miembro['celular'] ) {
                        self::enviarSMS($miembro['celular'], $mensaje);
                    }
                    break;
                case 'whatsapp':
                    if ( $miembro['celular'] ) {
                        self::enviarWhatsApp($miembro['celular'], $miembro['nombre'], $tipo, $mensaje, $datos);
                    }
                    break;
            }
        }
    }

    /** Email con template HTML */
    public static function enviarEmail( $to, $nombre, $tipo, $mensaje, $datos = [] ) {
        $from    = get_option('psp_notif_email_from', get_option('admin_email'));
        $subject = self::getSubject($tipo, $datos);
        $body    = self::getEmailTemplate($nombre, $tipo, $mensaje, $datos);

        add_filter('wp_mail_content_type', function(){ return 'text/html'; });
        wp_mail($to, $subject, $body, ['From: Panamá Sin Pobreza <'.$from.'>']);
        remove_filter('wp_mail_content_type', function(){ return 'text/html'; });
    }

    /** SMS vía Twilio */
    public static function enviarSMS( $to, $mensaje ) {
        $sid   = get_option('psp_twilio_sid',   '');
        $token = get_option('psp_twilio_token', '');
        $from  = get_option('psp_twilio_from',  '');
        if ( ! $sid || ! $token || ! $from ) return false;

        // Normalizar número
        $phone = preg_replace('/[^0-9+]/', '', $to);
        if ( ! str_starts_with($phone, '+') ) $phone = '+507' . ltrim($phone,'507');

        $url = "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json";
        wp_remote_post($url, [
            'headers' => ['Authorization' => 'Basic ' . base64_encode("{$sid}:{$token}"), 'Content-Type' => 'application/x-www-form-urlencoded'],
            'body'    => http_build_query(['From'=>$from, 'To'=>$phone, 'Body'=>$mensaje]),
        ]);
        return true;
    }

    /** WhatsApp vía 360dialog o Meta API */
    public static function enviarWhatsApp( $to, $nombre, $tipo, $mensaje, $datos = [] ) {
        $token = get_option('psp_wa_360_token', '');
        $url   = get_option('psp_wa_360_url',   'https://waba.360dialog.io/v1/messages');
        if ( ! $token ) return false;

        $phone = preg_replace('/[^0-9]/', '', $to);
        if ( substr($phone,0,3) !== '507' ) $phone = '507' . $phone;

        $body = [
            'messaging_product' => 'whatsapp',
            'to'                => $phone,
            'type'              => 'text',
            'text'              => ['body' => $mensaje],
        ];

        wp_remote_post($url, [
            'headers' => ['D360-API-KEY' => $token, 'Content-Type' => 'application/json'],
            'body'    => wp_json_encode($body),
            'timeout' => 10,
        ]);
        return true;
    }

    private static function getSubject( $tipo, $datos ) {
        $subjects = [
            'bienvenida' => '¡Bienvenido a Panamá Sin Pobreza! 🇵🇦',
            'pago'       => '✅ Tu aporte fue confirmado — Panamá Sin Pobreza',
            'referido'   => '🔗 ¡Alguien se unió con tu código!',
            'nivel'      => '🌟 ¡Subiste de nivel en PSP!',
            'ranking'    => '🏆 Tu posición en el ranking',
            'reto'       => '🎯 Nuevo reto disponible',
        ];
        return $subjects[$tipo] ?? 'Notificación — Panamá Sin Pobreza';
    }

    private static function getEmailTemplate( $nombre, $tipo, $mensaje, $datos ) {
        $color = '#0B5E43';
        return '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#f5f5f5;font-family:sans-serif">
<table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:32px 16px">
<table width="580" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.08)">
  <tr><td style="background:'.$color.';padding:24px 32px;text-align:center">
    <h1 style="color:#fff;margin:0;font-size:22px;font-weight:800">🇵🇦 Panamá Sin Pobreza</h1>
  </td></tr>
  <tr><td style="padding:32px">
    <p style="color:#374151;font-size:15px">Hola, <strong>'.esc_html($nombre).'</strong>:</p>
    <p style="color:#374151;font-size:15px;line-height:1.7">'.esc_html($mensaje).'</p>
    <div style="text-align:center;margin-top:24px">
      <a href="'.home_url('/mi-cuenta/').'" style="background:'.$color.';color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:700;font-size:15px">
        Ver mi cuenta
      </a>
    </div>
  </td></tr>
  <tr><td style="background:#f9fafb;padding:16px 32px;text-align:center;font-size:12px;color:#9ca3af">
    Panamá Sin Pobreza · '.home_url().' · <a href="'.home_url('/baja-notificaciones/').'" style="color:#9ca3af">Dar de baja</a>
  </td></tr>
</table></td></tr></table></body></html>';
    }
}

// ── Función global shortcut ───────────────────────────────────────────────────
function psp_notificar( $miembro_id, $tipo, $mensaje, $canales = ['interna'], $datos = [] ) {
    PSP_Notificaciones::enviar( $miembro_id, $tipo, $mensaje, $canales, $datos );
}

// ── AJAX: mis notificaciones ──────────────────────────────────────────────────
add_action('wp_ajax_psp_get_mis_notif',        'psp_ajax_get_mis_notif');
add_action('wp_ajax_nopriv_psp_get_mis_notif', 'psp_ajax_get_mis_notif');
function psp_ajax_get_mis_notif() {
    if ( ! psp_verify_nonce() ) wp_send_json_error();
    $jwt = sanitize_text_field($_POST['jwt']??'');
    if (!$jwt) wp_send_json_error();
    $user_res  = wp_remote_get(PSP_SUPABASE_URL.'/auth/v1/user', ['headers'=>['apikey'=>PSP_SUPABASE_KEY,'Authorization'=>'Bearer '.$jwt]]);
    $user_data = json_decode(wp_remote_retrieve_body($user_res), true);
    if (empty($user_data['id'])) wp_send_json_error();
    $miembro = PSP_Supabase::select('miembros', ['user_id'=>'eq.'.$user_data['id'],'select'=>'id','limit'=>1]);
    if (!$miembro) wp_send_json_error();
    $notifs = PSP_Supabase::select('notificaciones', ['miembro_id'=>'eq.'.$miembro[0]['id'],'order'=>'created_at.desc','limit'=>20]);
    wp_send_json_success($notifs??[]);
}

// ── AJAX: guardar push subscription ──────────────────────────────────────────
add_action('wp_ajax_psp_save_push_sub',        'psp_ajax_save_push_sub');
add_action('wp_ajax_nopriv_psp_save_push_sub', 'psp_ajax_save_push_sub');
function psp_ajax_save_push_sub() {
    if (!psp_verify_nonce()) wp_send_json_error();
    $sub      = sanitize_text_field($_POST['subscription']??'');
    $jwt      = sanitize_text_field($_POST['jwt']??'');
    $miembro_id = null;
    if ($jwt) {
        $user_res  = wp_remote_get(PSP_SUPABASE_URL.'/auth/v1/user', ['headers'=>['apikey'=>PSP_SUPABASE_KEY,'Authorization'=>'Bearer '.$jwt]]);
        $user_data = json_decode(wp_remote_retrieve_body($user_res), true);
        if (!empty($user_data['id'])) {
            $m = PSP_Supabase::select('miembros', ['user_id'=>'eq.'.$user_data['id'],'select'=>'id','limit'=>1]);
            $miembro_id = $m ? $m[0]['id'] : null;
        }
    }
    PSP_Supabase::insert('push_subscriptions', ['miembro_id'=>$miembro_id,'subscription'=>$sub,'tenant_id'=>get_option('psp_tenant_id','panama')], true);
    wp_send_json_success();
}
