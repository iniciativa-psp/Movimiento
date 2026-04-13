<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Hooks automáticos que disparan notificaciones en eventos del sistema
 */

// ── Nuevo miembro registrado ──────────────────────────────────────────────────
add_action('wp_ajax_psp_registro',        'psp_notif_on_registro', 20);
add_action('wp_ajax_nopriv_psp_registro', 'psp_notif_on_registro', 20);
function psp_notif_on_registro() {
    // Se dispara después del handler principal (prioridad 20)
    // El miembro_id ya fue creado; lo buscamos por celular
    $celular = sanitize_text_field($_POST['celular']??'');
    if (!$celular || !class_exists('PSP_Supabase')) return;
    $miembro = PSP_Supabase::select('miembros', ['celular'=>'eq.'.$celular,'select'=>'id,nombre','limit'=>1]);
    if (!$miembro) return;
    $m   = $miembro[0];
    $msg = "¡Bienvenido al Movimiento Panamá Sin Pobreza, {$m['nombre']}! 🇵🇦 Tu código PSP ya está activo. Compártelo y suma puntos.";
    psp_notificar($m['id'], 'bienvenida', $msg, ['interna','email']);
}

// ── Pago confirmado ───────────────────────────────────────────────────────────
add_filter('psp_pago_completado', 'psp_notif_on_pago', 10, 1);
function psp_notif_on_pago( $pago ) {
    if (!function_exists('psp_notificar')) return $pago;
    $msg = "✅ Tu aporte de \${$pago['monto']} USD fue confirmado. ¡Gracias por apoyar a Panamá Sin Pobreza!";
    psp_notificar($pago['miembro_id'], 'pago', $msg, ['interna','email','whatsapp']);
    return $pago;
}

// ── Nuevo referido ────────────────────────────────────────────────────────────
add_filter('psp_referido_registrado', 'psp_notif_on_referido', 10, 2);
function psp_notif_on_referido( $referidor_id, $referido_nombre ) {
    if (!function_exists('psp_notificar')) return;
    $msg = "🔗 ¡{$referido_nombre} se unió al Movimiento usando tu código! Ganaste puntos extra.";
    psp_notificar($referidor_id, 'referido', $msg, ['interna']);
}

// ── Subida de nivel ───────────────────────────────────────────────────────────
add_filter('psp_nivel_actualizado', 'psp_notif_on_nivel', 10, 3);
function psp_notif_on_nivel( $miembro_id, $nivel_anterior, $nivel_nuevo ) {
    if ($nivel_anterior === $nivel_nuevo) return;
    if (!function_exists('psp_notificar')) return;
    $msg = "🌟 ¡Subiste de nivel! Ahora eres <strong>{$nivel_nuevo}</strong> en el Movimiento Panamá Sin Pobreza.";
    psp_notificar($miembro_id, 'nivel', $msg, ['interna','email']);
}
