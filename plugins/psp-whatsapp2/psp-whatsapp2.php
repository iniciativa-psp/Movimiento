<?php
/**
 * Plugin Name: PSP WhatsApp 2
 * Plugin URI:  https://panamasinpobreza.org
 * Description: Grupos WhatsApp v2 (solo lectura). Shortcode [psp2_wa_grupos] muestra los grupos asignados al miembro según su territorio. Requiere PSP Core 2.
 * Version:     2.0.0
 * Author:      PSP Dev Team
 * Requires PHP: 7.4
 * Text Domain: psp-whatsapp2
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ── Aviso si PSP Core 2 no está activo ───────────────────────────────────────
add_action( 'admin_notices', 'psp2_whatsapp_check_core' );
function psp2_whatsapp_check_core(): void {
    if ( ! class_exists( 'PSP2_Supabase' ) ) {
        echo '<div class="notice notice-warning"><p><strong>PSP WhatsApp 2:</strong> Requiere <strong>PSP Core 2</strong> activo.</p></div>';
    }
}

// ── Shortcode [psp2_wa_grupos] ───────────────────────────────────────────────
add_shortcode( 'psp2_wa_grupos', 'psp2_wa_grupos_shortcode' );
function psp2_wa_grupos_shortcode( array $atts = [] ): string {
    if ( ! is_user_logged_in() ) {
        return '<div class="psp2-card" style="text-align:center"><p>&#x1F512; <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">Inicia sesi&oacute;n</a> para ver tus grupos.</p></div>';
    }

    // Delegar al endpoint REST psp/v2/wa-group si Core 2 está activo
    if ( ! class_exists( 'PSP2_Supabase' ) ) {
        return '<div class="psp2-card"><p>&#x26A0;&#xFE0F; Grupos WhatsApp no disponibles en este momento.</p></div>';
    }

    $wp_user_id = (int) get_current_user_id();

    $miembro_row = PSP2_Supabase::select( 'miembros', [
        'wp_user_id' => 'eq.' . $wp_user_id,
        'select'     => 'id,provincia_id,distrito_id,corregimiento_id,comunidad_id',
        'limit'      => 1,
    ] );

    if ( empty( $miembro_row ) ) {
        return '<div class="psp2-card"><p>&#x26A0;&#xFE0F; Perfil no encontrado.</p></div>';
    }

    $m      = $miembro_row[0];
    $grupos = [];

    // Buscar grupo territorial de mayor granularidad
    $terr_ids = array_filter( [
        $m['comunidad_id']     ?? null,
        $m['corregimiento_id'] ?? null,
        $m['distrito_id']      ?? null,
        $m['provincia_id']     ?? null,
    ] );

    foreach ( $terr_ids as $terr_id ) {
        $res = PSP2_Supabase::select( 'whatsapp_grupos', [
            'territorio_id' => 'eq.' . $terr_id,
            'activo'        => 'eq.true',
            'tipo'          => 'eq.territorial',
            'select'        => 'id,nombre,link,tipo,miembros_actual,miembros_max',
            'order'         => 'miembros_actual.asc',
            'limit'         => 1,
        ] );
        if ( ! empty( $res ) ) {
            $grupos[] = array_merge( $res[0], [ 'categoria' => 'Territorial' ] );
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
            $grupos[] = array_merge( $g, [ 'categoria' => 'General' ] );
        }
    }

    if ( empty( $grupos ) ) {
        return '<div class="psp2-card psp2-alert psp2-alert-info">&#x1F4AC; No hay grupos WhatsApp disponibles a&uacute;n. Pronto estar&aacute;n listos.</div>';
    }

    ob_start(); ?>
    <div class="psp2-card psp2-wa-wrap">
      <h2 style="margin-top:0">&#x1F4AC; Grupos WhatsApp</h2>
      <div style="display:flex;flex-direction:column;gap:12px">
        <?php foreach ( $grupos as $g ) :
            $lleno = isset( $g['miembros_actual'], $g['miembros_max'] )
                     && $g['miembros_actual'] >= $g['miembros_max'];
        ?>
        <div style="padding:14px;border:1.5px solid #D1FAE5;border-radius:10px;background:#F0FDF4">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:6px">
            <div>
              <p style="font-weight:700;margin:0 0 4px;font-size:14px"><?php echo esc_html( $g['nombre'] ?? '—' ); ?></p>
              <p style="font-size:12px;color:#6B7280;margin:0">
                <?php echo esc_html( $g['categoria'] ); ?>
                <?php if ( isset( $g['miembros_actual'], $g['miembros_max'] ) ) : ?>
                  &nbsp;&bull;&nbsp; <?php echo esc_html( $g['miembros_actual'] . '/' . $g['miembros_max'] ); ?> miembros
                <?php endif; ?>
              </p>
            </div>
            <?php if ( ! empty( $g['link'] ) && ! $lleno ) : ?>
            <a href="<?php echo esc_url( $g['link'] ); ?>" target="_blank" rel="noopener"
               class="psp2-btn psp2-btn-primary" style="padding:8px 14px;font-size:13px;white-space:nowrap">
              &#x1F517; Unirse
            </a>
            <?php elseif ( $lleno ) : ?>
            <span style="font-size:12px;color:#991B1B;font-weight:700">Grupo lleno</span>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php
    return ob_get_clean();
}
