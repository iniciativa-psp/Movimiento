<?php
if (!defined('ABSPATH')) exit;
// Placeholder para transferencias manuales — no requiere API
function psp_transferencia_instrucciones(): array {
    return [
        'banco'   => get_option('psp_banco_nombre', 'Banco General'),
        'cuenta'  => get_option('psp_banco_cuenta', ''),
        'titular' => 'Iniciativa Panamá Sin Pobreza',
        'ruc'     => get_option('psp_ruc', ''),
        'swift'   => get_option('psp_swift', ''),
    ];
}
