<?php
/**
 * Plugin Name: PSP Ranking 2
 * Plugin URI:  https://panamasinpobreza.org
 * Description: Widget de ranking v2 (solo lectura). Shortcodes para tablas de líderes. Requiere PSP Core 2.
 * Version:     2.0.0
 * Author:      PSP Dev Team
 * Requires PHP: 7.4
 * Text Domain: psp-ranking2
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ── Aviso si PSP Core 2 no está activo ───────────────────────────────────────
add_action( 'admin_notices', 'psp2_ranking_check_core' );
function psp2_ranking_check_core(): void {
    if ( ! class_exists( 'PSP2_Supabase' ) ) {
        echo '<div class="notice notice-warning"><p><strong>PSP Ranking 2:</strong> Requiere <strong>PSP Core 2</strong> activo.</p></div>';
    }
}

// ── Shortcode [psp2_ranking_nacional] ────────────────────────────────────────
add_shortcode( 'psp2_ranking_nacional', 'psp2_ranking_nacional_shortcode' );
function psp2_ranking_nacional_shortcode( array $atts = [] ): string {
    $atts = shortcode_atts( [ 'limit' => 20 ], $atts, 'psp2_ranking_nacional' );
    $limit = max( 1, min( 100, (int) $atts['limit'] ) );

    if ( ! class_exists( 'PSP2_Supabase' ) ) {
        return '<div class="psp2-card"><p>&#x26A0;&#xFE0F; Ranking no disponible.</p></div>';
    }

    $rows = PSP2_Supabase::select( 'ranking', [
        'order'  => 'posicion_nacional.asc',
        'select' => 'posicion_nacional,miembros!miembro_id(nombre,provincia_id)',
        'limit'  => $limit,
    ] );

    if ( empty( $rows ) ) {
        return '<div class="psp2-card psp2-alert psp2-alert-info">El ranking a&uacute;n no tiene datos.</div>';
    }

    ob_start(); ?>
    <div class="psp2-card psp2-ranking-wrap">
      <h2 style="margin-top:0">&#x1F3C6; Ranking Nacional</h2>
      <table style="width:100%;border-collapse:collapse;font-size:13px">
        <thead>
          <tr style="border-bottom:2px solid #E5E7EB">
            <th style="text-align:center;padding:8px 4px;width:40px">#</th>
            <th style="text-align:left;padding:8px 4px">Nombre</th>
            <th style="text-align:left;padding:8px 4px">Provincia</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ( $rows as $r ) :
              $pos    = $r['posicion_nacional'] ?? '—';
              $nombre = $r['miembros']['nombre']      ?? '—';
              $prov   = $r['miembros']['provincia_id'] ?? '—';
          ?>
          <tr style="border-bottom:1px solid #F3F4F6">
            <td style="text-align:center;padding:7px 4px;font-weight:700;color:<?php echo $pos <= 3 ? '#B45309' : '#374151'; ?>">
              <?php echo $pos <= 1 ? '&#x1F947;' : ( $pos == 2 ? '&#x1F948;' : ( $pos == 3 ? '&#x1F949;' : esc_html( $pos ) ) ); ?>
            </td>
            <td style="padding:7px 4px"><?php echo esc_html( $nombre ); ?></td>
            <td style="padding:7px 4px;color:#9CA3AF"><?php echo esc_html( $prov ); ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php
    return ob_get_clean();
}

// ── Shortcode [psp2_mi_posicion] ──────────────────────────────────────────────
add_shortcode( 'psp2_mi_posicion', 'psp2_mi_posicion_shortcode' );
function psp2_mi_posicion_shortcode( array $atts = [] ): string {
    if ( ! is_user_logged_in() || ! class_exists( 'PSP2_Supabase' ) ) {
        return '';
    }
    $wp_user_id = (int) get_current_user_id();

    $miembro_row = PSP2_Supabase::select( 'miembros', [
        'wp_user_id' => 'eq.' . $wp_user_id,
        'select'     => 'id',
        'limit'      => 1,
    ] );
    if ( empty( $miembro_row ) ) return '';

    $rank_row = PSP2_Supabase::select( 'ranking', [
        'miembro_id' => 'eq.' . $miembro_row[0]['id'],
        'select'     => 'posicion_nacional,posicion_provincial,puntos',
        'limit'      => 1,
    ] );
    if ( empty( $rank_row ) ) {
        return '<div class="psp2-card psp2-alert psp2-alert-info">Tu posici&oacute;n a&uacute;n no est&aacute; disponible.</div>';
    }

    $r = $rank_row[0];
    ob_start(); ?>
    <div class="psp2-card" style="text-align:center">
      <p style="font-size:13px;color:#6B7280;margin-bottom:4px">Posici&oacute;n Nacional</p>
      <p style="font-size:42px;font-weight:800;color:#0B5E43;margin:0">#<?php echo esc_html( $r['posicion_nacional'] ?? '—' ); ?></p>
      <p style="font-size:13px;color:#6B7280;margin-top:4px">Provincial: #<?php echo esc_html( $r['posicion_provincial'] ?? '—' ); ?></p>
      <p style="font-size:13px;color:#374151">Puntos: <strong><?php echo esc_html( $r['puntos'] ?? 0 ); ?></strong></p>
    </div>
    <?php
    return ob_get_clean();
}
