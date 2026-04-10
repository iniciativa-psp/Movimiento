<?php
if (!defined('ABSPATH')) exit;

add_action('admin_menu', 'psp_core_admin_menu');
function psp_core_admin_menu(): void {
    add_menu_page(
        'PSP Sistema',
        '🇵🇦 PSP Sistema',
        'manage_options',
        'psp-core',
        'psp_core_settings_page',
        'dashicons-groups',
        2
    );
}

function psp_core_settings_page(): void {
    if (isset($_POST['psp_save']) && check_admin_referer('psp_core_settings')) {
        update_option('psp_supabase_url',         sanitize_url($_POST['supabase_url']));
        update_option('psp_supabase_anon_key',    sanitize_text_field($_POST['supabase_anon_key']));
        update_option('psp_supabase_service_key', sanitize_text_field($_POST['supabase_service_key']));
        update_option('psp_tenant_id',            sanitize_text_field($_POST['tenant_id']));
        update_option('psp_launch_date',          sanitize_text_field($_POST['launch_date']));
        echo '<div class="updated"><p>✅ Configuración guardada.</p></div>';
    }
    ?>
    <div class="wrap" style="font-family:sans-serif">
    <h1>🇵🇦 PSP — Configuración Central</h1>
    <form method="post">
    <?php wp_nonce_field('psp_core_settings'); ?>
    <table class="form-table">
        <tr><th>Supabase URL</th><td><input class="regular-text" name="supabase_url" value="<?= esc_attr(get_option('psp_supabase_url')) ?>"></td></tr>
        <tr><th>Anon Key</th><td><input class="regular-text" name="supabase_anon_key" value="<?= esc_attr(get_option('psp_supabase_anon_key')) ?>"></td></tr>
        <tr><th>Service Key</th><td><input class="regular-text" name="supabase_service_key" type="password" value="<?= esc_attr(get_option('psp_supabase_service_key')) ?>"></td></tr>
        <tr><th>Tenant ID</th><td><input name="tenant_id" value="<?= esc_attr(get_option('psp_tenant_id','panama')) ?>"></td></tr>
        <tr><th>Fecha Lanzamiento</th><td><input name="launch_date" value="<?= esc_attr(get_option('psp_launch_date','2026-05-12T09:00:00')) ?>"></td></tr>
    </table>
    <p><button class="button button-primary" name="psp_save">💾 Guardar</button></p>
    </form>
    </div>
    <?php
}
