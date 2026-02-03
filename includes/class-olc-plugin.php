<?php
if (!defined('ABSPATH')) exit;

class OLC_Plugin {

    public static function init() {
        // Load i18n early
        add_action('init', array(__CLASS__, 'load_textdomain'));

        // Register activation/deactivation hooks (files loaded only if exist)
        if (file_exists(OLC_PLUGIN_DIR . 'includes/class-olc-activator.php')) {
            require_once OLC_PLUGIN_DIR . 'includes/class-olc-activator.php';
        }
        if (file_exists(OLC_PLUGIN_DIR . 'includes/class-olc-deactivator.php')) {
            require_once OLC_PLUGIN_DIR . 'includes/class-olc-deactivator.php';
        }
        if (file_exists(OLC_PLUGIN_DIR . 'includes/class-olc-cron.php')) {
            require_once OLC_PLUGIN_DIR . 'includes/class-olc-cron.php';
        }

        // Delay loading admin/public classes until plugins_loaded. This avoids
        // fatal errors caused by files that require WP fully loaded or other dependencies.
        add_action('plugins_loaded', array(__CLASS__, 'load_components'), 20);
    }

    public static function load_textdomain() {
        load_plugin_textdomain('ofertas-laborales-crecer', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    public static function load_components() {
        // Admin class (only if file exists)
        if (is_admin()) {
            $admin_file = OLC_PLUGIN_DIR . 'admin/class-olc-admin.php';
            if (file_exists($admin_file)) {
                include_once $admin_file;
                if (class_exists('OLC_Admin')) {
                    // Init admin only in admin context
                    OLC_Admin::init();
                }
            }
        }

        // Public class (frontend)
        $public_file = OLC_PLUGIN_DIR . 'public/class-olc-public.php';
        if (file_exists($public_file)) {
            include_once $public_file;
            if (!is_admin() && class_exists('OLC_Public')) {
                OLC_Public::init();
            }
        }
    }

    public static function activate() {
        // Load activator class if exists and run
        if (file_exists(OLC_PLUGIN_DIR . 'includes/class-olc-activator.php')) {
            include_once OLC_PLUGIN_DIR . 'includes/class-olc-activator.php';
            if (class_exists('OLC_Activator')) {
                OLC_Activator::activate();
            }
        }
    }

    public static function deactivate() {
        // Load deactivator class if exists and run
        if (file_exists(OLC_PLUGIN_DIR . 'includes/class-olc-deactivator.php')) {
            include_once OLC_PLUGIN_DIR . 'includes/class-olc-deactivator.php';
            if (class_exists('OLC_Deactivator')) {
                OLC_Deactivator::deactivate();
            }
        }
    }
    
    public function calcular_estado_oferta($oferta) {
        $hoy = current_time('Y-m-d');
    
        $fecha_inicio = $oferta->fecha_inicio;
        $fecha_fin    = $oferta->fecha_fin;
        $ganador      = $oferta->ganador; // o el campo equivalente
    
        if ($hoy >= $fecha_inicio && $hoy <= $fecha_fin) {
            return 'activo';
        }
    
        if ($hoy > $fecha_fin && empty($ganador)) {
            return 'en_evaluacion';
        }
    
        if ($hoy > $fecha_fin && !empty($ganador)) {
            return 'finalizado';
        }
    
        return 'indefinido';
    }

}
