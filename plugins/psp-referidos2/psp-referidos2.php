<?php
/**
 * Plugin Name: PSP Referidos 2
 * Plugin URI:  https://panamasinpobreza.org
 * Description: Referidos v2. Shortcode [psp2_mis_referidos] para mostrar la tabla de referidos del miembro actual. Requiere PSP Core 2.
 * Version:     2.0.0
 * Author:      PSP Dev Team
 * Requires PHP: 7.4
 * Text Domain: psp-referidos2
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ── Aviso si PSP Core 2 no está activo ───────────────────────────────────────
add_action( 'admin_notices', 'psp2_referidos_check_core' );
function psp2_referidos_check_core(): void {
    if ( ! class_exists( 'PSP2_Supabase' ) ) {
        echo '<div class="notice notice-warning"><p><strong>PSP Referidos 2:</strong> Requiere <strong>PSP Core 2</strong> activo.</p></div>';
    }
}

// ── Shortcode [psp2_mis_referidos] ───────────────────────────────────────────
add_shortcode( 'psp2_mis_referidos', 'psp2_mis_referidos_shortcode' );
function psp2_mis_referidos_shortcode( array $atts = [] ): string {
    if ( ! is_user_logged_in() ) {
        return '<div class="psp2-card" style="text-align:center"><p>&#x1F512; <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">Inicia sesi&oacute;n</a> para ver tus referidos.</p></div>';
    }
    if ( ! class_exists( 'PSP2_Supabase' ) ) {
        return '<div class="psp2-card"><p>&#x26A0;&#xFE0F; Sistema no disponible. Intenta m&aacute;s tarde.</p></div>';
    }

    $wp_user_id = (int) get_current_user_id();

    // Obtener miembro
    $miembro_row = PSP2_Supabase::select( 'miembros', [
        'wp_user_id' => 'eq.' . $wp_user_id,
        'select'     => 'id,codigo_referido_propio',
        'limit'      => 1,
    ] );

    if ( empty( $miembro_row ) ) {
        return '<div class="psp2-card"><p>&#x26A0;&#xFE0F; Perfil no encontrado.</p></div>';
    }

    $miembro_id = $miembro_row[0]['id'];

    // Obtener referidos
    $refs = PSP2_Supabase::select( 'referidos_log', [
        'referidor_id' => 'eq.' . $miembro_id,
        'select'       => 'id,referido_id,created_at,miembros!referido_id(nombre,estado)',
        'order'        => 'created_at.desc',
        'limit'        => 50,
    ] );

    ob_start(); ?>
    <div class="psp2-card">
      <h2 style="margin-top:0">&#x1F465; Mis Referidos</h2>

      <?php if ( empty( $refs ) ) : ?>
        <div class="psp2-alert psp2-alert-info">
          A&uacute;n no tienes referidos. &iexcl;Comparte tu enlace para sumar!
        </div>
      <?php else : ?>
        <p style="font-size:14px;color:#374151">Total: <strong><?php echo esc_html( count( $refs ) ); ?></strong></p>
        <table style="width:100%;border-collapse:collapse;font-size:13px">
          <thead>
            <tr style="border-bottom:2px solid #E5E7EB">
              <th style="text-align:left;padding:8px 4px">#</th>
              <th style="text-align:left;padding:8px 4px">Nombre</th>
              <th style="text-align:left;padding:8px 4px">Estado</th>
              <th style="text-align:left;padding:8px 4px">Fecha</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ( $refs as $i => $ref ) :
                $nombre  = $ref['miembros']['nombre'] ?? '—';
                $estado  = $ref['miembros']['estado'] ?? '—';
                $fecha   = isset( $ref['created_at'] ) ? gmdate( 'd/m/Y', strtotime( $ref['created_at'] ) ) : '—';
                $estado_badge = ( $estado === 'activo' )
                    ? '<span style="color:#166534;font-weight:700">&#x2705; Activo</span>'
                    : '<span style="color:#92400E">&#x23F3; ' . esc_html( ucfirst( $estado ) ) . '</span>';
            ?>
            <tr style="border-bottom:1px solid #F3F4F6">
              <td style="padding:7px 4px;color:#9CA3AF"><?php echo esc_html( $i + 1 ); ?></td>
              <td style="padding:7px 4px"><?php echo esc_html( $nombre ); ?></td>
              <td style="padding:7px 4px"><?php echo $estado_badge; ?></td>
              <td style="padding:7px 4px;color:#9CA3AF"><?php echo esc_html( $fecha ); ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

// ── Shortcode [psp2_mi_enlace_ref] ───────────────────────────────────────────
add_shortcode( 'psp2_mi_enlace_ref', 'psp2_mi_enlace_ref_shortcode' );
function psp2_mi_enlace_ref_shortcode( array $atts = [] ): string {
    if ( ! is_user_logged_in() || ! class_exists( 'PSP2_Supabase' ) ) {
        return '';
    }
    $wp_user_id = (int) get_current_user_id();
    $row = PSP2_Supabase::select( 'miembros', [
        'wp_user_id' => 'eq.' . $wp_user_id,
        'select'     => 'codigo_referido_propio',
        'limit'      => 1,
    ] );
    if ( empty( $row ) || empty( $row[0]['codigo_referido_propio'] ) ) {
        return '';
    }
    $ref_link = home_url( '/?ref=' . rawurlencode( $row[0]['codigo_referido_propio'] ) );
    return '<input type="text" value="' . esc_attr( $ref_link ) . '" readonly onclick="this.select()" style="width:100%;padding:8px;border:1px solid #D1D5DB;border-radius:6px;font-size:13px">';
}
