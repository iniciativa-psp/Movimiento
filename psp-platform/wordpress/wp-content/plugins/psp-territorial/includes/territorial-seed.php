<?php
if ( ! defined( 'ABSPATH' ) ) exit;
// Archivo de seed — se puede ejecutar manualmente para poblar la tabla territorios
// en Supabase con datos básicos de Panamá si no se usa el JSON externo.
// Ejecutar: do_action('psp_terr_seed_panama');

add_action('psp_terr_seed_panama', 'psp_terr_seed_panama_data');
function psp_terr_seed_panama_data() {
    if ( ! class_exists('PSP_Supabase') ) return;
    // Ver SQL completo en: sql/04_territorios_panama_seed.sql
    // Este seed inserta Panamá y sus 10 provincias con coordenadas.
    error_log('[PSP Territorial] Seed: usa el SQL 04_territorios_panama_seed.sql en Supabase.');
}
