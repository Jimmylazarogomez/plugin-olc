<?php
/**
 * Uninstall script for Ofertas Laborales - Crecer
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;
$prefix = $wpdb->prefix;

// =========================
// ELIMINAR TABLAS DEL PLUGIN
// =========================
$tables = [
    "{$prefix}olc_ofertas",
    "{$prefix}olc_postulaciones",
    "{$prefix}olc_postulantes",
    "{$prefix}olc_puntuaciones",
];

foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS $table");
}

// =========================
// ELIMINAR OPCIONES
// =========================
$options = [
    'olc_ccvv_max_mb',
    'olc_ccvv_retention_days',
    'olc_threshold_A',
    'olc_threshold_B',
    'olc_score_age',
    'olc_score_banco',
    'olc_score_profesion',
    'olc_score_experiencia',
    'olc_wa_template_confirm',
    'olc_wa_template_convocar',
    'olc_wa_template_result_ganador',
    'olc_wa_template_result_reserva',
    'olc_wa_template_result_descartado',
    'olc_wa_mode',
];

foreach ($options as $option) {
    delete_option($option);
}

// =========================
// ELIMINAR ROLES
// =========================
remove_role('ofertas_admin_principal');
remove_role('ofertas_admin_asistente');
remove_role('postulante');

// =========================
// (OPCIONAL) ELIMINAR PÁGINAS
// =========================
$pages = [
    'Ofertas Laborales',
    'Detalle Oferta',
    'Mis Postulaciones',
];

foreach ($pages as $page_title) {
    $page = get_page_by_title($page_title);
    if ($page) {
        wp_delete_post($page->ID, true);
    }
}
