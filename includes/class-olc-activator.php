<?php
if (!defined('ABSPATH')) exit;

class OLC_Activator {

    public static function activate() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix;

        // =========================
        // TABLA: OFERTAS
        // =========================
        $sql_ofertas = "CREATE TABLE {$prefix}olc_ofertas (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            titulo VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            descripcion TEXT,
            funciones TEXT,
            perfil TEXT,
            ciudad VARCHAR(100),
            profesion_requerida VARCHAR(150),
            sueldo VARCHAR(50),
            tipo_contrato VARCHAR(50),
            horario VARCHAR(100),
            beneficios TEXT,
            estado VARCHAR(50) DEFAULT 'borrador',
            ganador_id BIGINT UNSIGNED NULL,
            etapa_actual TINYINT DEFAULT 1,
            fecha_inicio DATETIME NULL,
            fecha_fin DATETIME NULL,
            creado_por BIGINT UNSIGNED NULL,
            meta LONGTEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";

        // =========================
        // TABLA: POSTULACIONES
        // =========================
        $sql_postulaciones = "CREATE TABLE {$prefix}olc_postulaciones (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            etapa TINYINT DEFAULT 1,
            oferta_id BIGINT UNSIGNED NOT NULL,
            postulante_id BIGINT UNSIGNED NULL,
            user_id BIGINT UNSIGNED NULL,
            nombre VARCHAR(255),
            dni VARCHAR(50),
            telefono VARCHAR(50),
            email VARCHAR(150),
            fecha_nacimiento DATE,
            ciudad VARCHAR(100),
            profesion VARCHAR(150),
            anteriores_puestos TEXT,
            experiencia_anios INT DEFAULT 0,
            disponibilidad VARCHAR(100),
            ccvv_path VARCHAR(255),
            estado_postulacion VARCHAR(50) DEFAULT 'Enviado',
            puntaje_total FLOAT DEFAULT 0,
            puntaje_entrevista INT DEFAULT 0,
            estado_entrevista VARCHAR(50) DEFAULT 'No convocado',
            resultado_final VARCHAR(50),
            etiquetas LONGTEXT,
            fecha_postulacion DATETIME DEFAULT CURRENT_TIMESTAMP,
            fecha_ccvv_subida DATETIME NULL,
            PRIMARY KEY (id),
            KEY oferta_id (oferta_id),
            KEY user_id (user_id),
            KEY postulante_id (postulante_id)
        ) $charset_collate;";

        // =========================
        // TABLA: POSTULANTES (NUEVA)
        // =========================
        $sql_postulantes = "CREATE TABLE {$prefix}olc_postulantes (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NULL,
            nombre VARCHAR(255),
            dni VARCHAR(50),
            telefono VARCHAR(50),
            email VARCHAR(150),
            fecha_nacimiento DATE,
            ciudad VARCHAR(100),
            profesion VARCHAR(150),
            experiencia TEXT,
            pretension_salarial VARCHAR(50),
            sabe_moto ENUM('tiene_licencia','disponibilidad','no_sabe') DEFAULT 'no_sabe',
            disponibilidad VARCHAR(100),
            ccvv VARCHAR(255),
            fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
            experiencia_anios INT DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY email_unique (email)
        ) $charset_collate;";

        // =========================
        // TABLA: PUNTUACIONES
        // =========================
        $sql_puntuaciones = "CREATE TABLE {$prefix}olc_puntuaciones (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            postulacion_id BIGINT UNSIGNED NOT NULL,
            criterio VARCHAR(100),
            valor FLOAT,
            comentario TEXT,
            creado_por BIGINT UNSIGNED NULL,
            fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY postulacion_id (postulacion_id)
        ) $charset_collate;";

        dbDelta($sql_ofertas);
        dbDelta($sql_postulaciones);
        dbDelta($sql_postulantes);
        dbDelta($sql_puntuaciones);

        self::create_pages();
        self::create_roles();
        self::default_options();
    }

    // =========================
    // PÁGINAS
    // =========================
    private static function create_pages() {
        $pages = [
            'Ofertas Laborales' => '[olc_ofertas]',
            'Detalle Oferta' => '[olc_detalle_oferta]',
            'Mis Postulaciones' => '[olc_mis_postulaciones]',
        ];

        foreach ($pages as $title => $content) {
            if (!get_page_by_title($title)) {
                wp_insert_post([
                    'post_title' => $title,
                    'post_content' => $content,
                    'post_status' => 'publish',
                    'post_type' => 'page',
                ]);
            }
        }
    }

    // =========================
    // ROLES
    // =========================
    private static function create_roles() {
        add_role('ofertas_admin_principal', 'Ofertas Admin Principal', [
            'read' => true,
            'manage_options' => true,
        ]);

        add_role('ofertas_admin_asistente', 'Ofertas Admin Asistente', [
            'read' => true,
        ]);

        add_role('postulante', 'Postulante', [
            'read' => true,
        ]);
    }

    // =========================
    // OPCIONES POR DEFECTO
    // =========================
    private static function default_options() {
        add_option('olc_ccvv_max_mb', 5);
        add_option('olc_ccvv_retention_days', 60);
        add_option('olc_threshold_A', 50);
        add_option('olc_threshold_B', 30);

        add_option('olc_score_age', json_encode(['18_22'=>20,'23_35'=>30,'36_40'=>10,'41_plus'=>0]));
        add_option('olc_score_banco', json_encode(['Bueno'=>30,'Regular'=>20,'Riesgo'=>0,'Peligro'=>0]));
        add_option('olc_score_profesion', json_encode(['coincidente'=>30,'relacionada'=>20,'otra'=>0]));
        add_option('olc_score_experiencia', json_encode(['>3'=>30,'1_2'=>20,'0'=>0]));

        add_option('olc_wa_mode', 'wame');
    }
}
