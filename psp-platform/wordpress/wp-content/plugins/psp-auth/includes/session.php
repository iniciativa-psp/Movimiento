<?php
if (!defined('ABSPATH')) exit;
// Iniciar sesión si no está activa
add_action('init', function() {
    if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
        session_start();
    }
});
