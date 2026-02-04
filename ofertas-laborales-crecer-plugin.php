<?php
/**
 * Plugin Name: Ofertas Laborales - Crecer (v7)
 * Description: Plugin completo para gestión de ofertas y postulaciones. Versión v7 (estable).
 * Version: 1.0.0-v7
 * Author: Equipo Crecer
 * Text Domain: ofertas-laborales-crecer
 */

if (!defined('ABSPATH')) exit;

define('OLC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('OLC_PLUGIN_FILE', __FILE__);

// Clase principal
require_once OLC_PLUGIN_DIR . 'includes/class-olc-plugin.php';

// Hooks de activación y desactivación
register_activation_hook(__FILE__, array('OLC_Plugin', 'activate'));
register_deactivation_hook(__FILE__, array('OLC_Plugin', 'deactivate'));

// ✅ Cargar el panel de administración
if (is_admin()) {
    require_once OLC_PLUGIN_DIR . 'admin/class-olc-admin.php';
    OLC_Admin::init();
}

// 🔹 Encolar estilos públicosñ
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style(
        'olc-styles',
        plugin_dir_url(__FILE__) . 'assets/css/olc-styles.css',
        array(),
        filemtime(plugin_dir_path(__FILE__) . 'assets/css/olc-styles.css')
    );
});


// Inicializar el plugin principal
OLC_Plugin::init();
