<?php
if (!defined('ABSPATH')) exit;

class OLC_Admin {
    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'admin_menu'));
        add_action('admin_post_olc_save_oferta', array(__CLASS__, 'save_oferta'));
        add_action('admin_post_olc_export_postulaciones', array(__CLASS__, 'export_postulaciones'));
        add_action('admin_post_olc_actualizar_entrevista', array(__CLASS__, 'actualizar_entrevista'));
        add_action('admin_post_olc_actualizar_final', array(__CLASS__, 'actualizar_final'));
        
        // Eliminar oferta
        add_action('admin_post_olc_delete_oferta', array(__CLASS__, 'handle_delete_oferta'));

        // Handlers para gestión de etapas
        add_action('admin_post_olc_pasar_etapa', array(__CLASS__, 'handle_pasar_etapa'));
        add_action('admin_post_olc_guardar_entrevista', array(__CLASS__, 'handle_guardar_entrevista'));
        add_action('admin_post_olc_guardar_seleccion', array(__CLASS__, 'handle_guardar_seleccion'));    
        add_action('admin_post_olc_calcular_puntaje', array(__CLASS__, 'handle_calcular_puntaje'));
        add_action('admin_post_olc_guardar_estado_bancario', array(__CLASS__, 'handle_guardar_estado_bancario'));
        add_action('admin_post_olc_pasar_etapa_masivo', array(__CLASS__, 'handle_pasar_etapa_masivo'));

        // ==== AJAX ENTREVISTA ====
        add_action('wp_ajax_olc_guardar_entrevista_ajax', array(__CLASS__, 'handle_guardar_entrevista'));
        add_action('wp_ajax_nopriv_olc_guardar_entrevista_ajax', array(__CLASS__, 'handle_guardar_entrevista'));

        // Asociar postulante de bolsa a oferta
        add_action('admin_post_olc_asociar_bolsa_oferta', array(__CLASS__, 'handle_asociar_bolsa_oferta'));



    }

    public static function admin_menu() {
        add_menu_page('Ofertas Laborales', 'Ofertas Laborales', 'manage_options', 'olc_dashboard', array(__CLASS__, 'dashboard'), 'dashicons-portfolio', 6);
        add_submenu_page('olc_dashboard', 'Listado de Ofertas', 'Listado de Ofertas', 'manage_options', 'olc_ofertas', array(__CLASS__, 'ofertas_list'));
        add_submenu_page('olc_dashboard', 'Nueva Oferta', 'Nueva Oferta', 'manage_options', 'olc_ofertas_new', array(__CLASS__, 'oferta_new'));
        add_submenu_page('olc_dashboard', 'Postulaciones', 'Postulaciones', 'manage_options', 'olc_postulaciones', array(__CLASS__, 'postulaciones_list'));
        add_submenu_page('olc_dashboard', 'Ajustes', 'Ajustes', 'manage_options', 'olc_settings', array(__CLASS__, 'settings_page'));

        // Nueva subpágina: Gestión de Etapas
        add_submenu_page('olc_dashboard', 'Gestión de Etapas', 'Gestión de Etapas', 'manage_options', 'olc_etapas', array(__CLASS__, 'etapas_page'));
        
        // ✅ NUEVO: Bolsa de Postulantes
        add_submenu_page('olc_dashboard', 'Bolsa de Postulantes', 'Bolsa de Postulantes', 'manage_options', 'olc_bolsa_postulantes', array(__CLASS__, 'page_bolsa_postulantes'));
        
    }

    public static function dashboard() {
        include OLC_PLUGIN_DIR . 'admin/views/admin-dashboard.php';
    }

    public static function ofertas_list() {
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
        if ($action === 'edit' && !empty($_GET['id'])) {
            include OLC_PLUGIN_DIR . 'admin/views/admin-edit-oferta.php';
            return;
        }
        include OLC_PLUGIN_DIR . 'admin/views/admin-ofertas.php';
    }
    
        /**
     * Calcula el estado real de la oferta según fechas y resultados de postulaciones.
     * Devuelve HTML (span) listo para mostrar en la tabla admin.
     */
    public static function calcular_estado_oferta($oferta_row) {
        global $wpdb;
        $tbl_post = $wpdb->prefix . 'olc_postulaciones';
    
        // Aceptamos tanto objeto (fila) como id
        if (is_numeric($oferta_row)) {
            $oferta_id = intval($oferta_row);
            $tbl_ofertas = $wpdb->prefix . 'olc_ofertas';
            $oferta = $wpdb->get_row( $wpdb->prepare("SELECT * FROM {$tbl_ofertas} WHERE id=%d", $oferta_id) );
        } else {
            $oferta = $oferta_row;
        }
    
        if (!$oferta) return '<span style="color:#777">Desconocido</span>';
    
        $hoy = new DateTime(current_time('mysql'));
        $fecha_inicio = !empty($oferta->fecha_inicio) ? new DateTime($oferta->fecha_inicio) : null;
        $fecha_fin = !empty($oferta->fecha_fin) ? new DateTime($oferta->fecha_fin) : null;
    
        // 1) Si estamos dentro de la ventana inicio-fin -> Activa
        if ($fecha_inicio && $fecha_fin && $hoy >= $fecha_inicio && $hoy <= $fecha_fin) {
            return '<span style="color:#0a7b1f; font-weight:bold;">Activa</span>';
        }
    
        // 2) Si ya pasó la fecha_fin -> ver si hay ganador (finalizada) o sigue en evaluación
        if ($fecha_fin && $hoy > $fecha_fin) {
            // ¿Hay algún ganador en esta oferta?
            $ganador = $wpdb->get_var( $wpdb->prepare("SELECT COUNT(1) FROM {$tbl_post} WHERE oferta_id=%d AND resultado_final IN ('Ganador','ganador','GANADOR')", $oferta->id) );
            if (intval($ganador) > 0) {
                return '<span style="color:#000; background:#e6f4ea; padding:4px 6px; border-radius:4px; font-weight:bold;">Finalizada</span>';
            }
    
            // Si no hay ganador, pero existen postulaciones para esta oferta aún -> En evaluación
            $count_post = $wpdb->get_var( $wpdb->prepare("SELECT COUNT(1) FROM {$tbl_post} WHERE oferta_id=%d", $oferta->id) );
            if (intval($count_post) > 0) {
                return '<span style="color:#b85f00; font-weight:bold;">En evaluación</span>';
            }
    
            // Default si no hay postulaciones
            return '<span style="color:#777;">Finalizada</span>';
        }
    
        // 3) Si no hay fechas bien definidas, fallback a campo estado (si existe)
        if (!empty($oferta->estado)) {
            $e = strtolower($oferta->estado);
            if ($e === 'finalizada' || $e === 'final') return '<span style="color:#000; font-weight:bold;">Finalizada</span>';
            if ($e === 'activa' || $e === 'publicada') return '<span style="color:#0a7b1f; font-weight:bold;">Activa</span>';
            if ($e === 'en_evaluacion' || $e === 'en evaluacion' || $e === 'en evaluación') return '<span style="color:#b85f00; font-weight:bold;">En evaluación</span>';
        }
    
        return '<span style="color:#777;">Inactivo</span>';
    }


    public static function oferta_new() {
        include OLC_PLUGIN_DIR . 'admin/views/admin-edit-oferta.php';
    }

    public static function postulaciones_list() {
        include OLC_PLUGIN_DIR . 'admin/views/admin-postulaciones.php';
    }

    public static function settings_page() {
        include OLC_PLUGIN_DIR . 'admin/views/admin-settings.php';
    }

    /**
     * Página: Gestión de Etapas
     */
    public static function etapas_page() {
        // incluye la vista que mostrará las 3 etapas
        // crea el archivo admin/admin-etapas.php y pega la vista que ya te pasé anteriormente
        $file = OLC_PLUGIN_DIR . 'admin/admin-etapas.php';
        if (file_exists($file)) {
            include $file;
        } else {
            echo '<div class="wrap"><h1>Gestión de Etapas</h1><p style="color:crimson;">Archivo admin-etapas.php no encontrado en /admin. Crea el archivo con el contenido proporcionado.</p></div>';
        }
    }

    public static function save_oferta() {
        if (!current_user_can('manage_options')) wp_die('No autorizado');
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'olc_save_oferta')) wp_die('Nonce inválido');
        
        global $wpdb;
        $table = $wpdb->prefix . 'olc_ofertas';

        $data = array(
            'titulo'             => sanitize_text_field($_POST['titulo']),
            'slug'               => sanitize_title($_POST['titulo']),
            'descripcion'        => wp_kses_post($_POST['descripcion']),
            'ciudad'             => sanitize_text_field($_POST['ciudad']),
            'tipo_contrato'      => sanitize_text_field($_POST['tipo_contrato']),
            'horario'            => sanitize_text_field($_POST['horario']),
            'perfil'             => wp_kses_post($_POST['perfil']),
            'profesion_requerida'=> sanitize_text_field($_POST['profesion_requerida']),
            'sueldo'             => sanitize_text_field($_POST['sueldo']),
            'funciones'          => wp_kses_post($_POST['funciones']),
            'beneficios'         => wp_kses_post($_POST['beneficios']),
            'fecha_inicio'       => sanitize_text_field($_POST['fecha_inicio']),
            'fecha_fin'          => sanitize_text_field($_POST['fecha_fin']),
            'estado'             => sanitize_text_field($_POST['estado']),
            'creado_por'         => get_current_user_id(),
        );

        if (!empty($_POST['id'])) {
            $wpdb->update($table, $data, array('id' => intval($_POST['id'])));
        } else {
            $wpdb->insert($table, $data);
        }

        wp_safe_redirect(admin_url('admin.php?page=olc_ofertas'));
        exit;
    }


    public static function export_postulaciones() {
        if (!current_user_can('manage_options')) wp_die('No autorizado');
        global $wpdb;
        $table = $wpdb->prefix . 'olc_postulaciones';
        $rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY fecha_postulacion DESC");
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=postulaciones.csv');
        $out = fopen('php://output','w');
        fputcsv($out, array('ID','Oferta ID','Nombre','Email','Telefono','Fecha','Estado','Puntaje'));
        foreach($rows as $r) {
            fputcsv($out, array($r->id,$r->oferta_id,$r->nombre,$r->email,$r->telefono,$r->fecha_postulacion,$r->estado_postulacion,$r->puntaje_total));
        }
        fclose($out);
        exit;
    }

    /**
     * Handler: pasar un postulante a otra etapa (GET)
     * usage: admin-post.php?action=olc_pasar_etapa&id=123&etapa=2
     */
    public static function handle_pasar_etapa() {
        if (!current_user_can('manage_options')) wp_die('No autorizado');
        
        global $wpdb;
        $table = $wpdb->prefix . 'olc_postulaciones';
        $table_ofertas = $wpdb->prefix . 'olc_ofertas';
        
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $etapa = isset($_GET['etapa']) ? intval($_GET['etapa']) : 0;
        
        if (!$id || !$etapa) {
            wp_safe_redirect(wp_get_referer());
            exit;
        }
    
        // Estados según etapa
        $estados = array(
            1 => 'Evaluado',
            2 => 'Entrevista',
            3 => 'Finalista'
        );
        
        $nuevo_estado = isset($estados[$etapa]) ? $estados[$etapa] : 'Enviado';
        
        // Actualizar postulación
        $wpdb->update($table, array(
            'estado_postulacion' => $nuevo_estado,
            'etapa' => $etapa
        ), array('id' => $id));
        
        // ========================================
        // ✅ NUEVO: Actualizar etapa_actual en la oferta
        // ========================================
        $oferta_id = $wpdb->get_var($wpdb->prepare(
            "SELECT oferta_id FROM {$table} WHERE id=%d", 
            $id
        ));
        
        if ($oferta_id) {
            // Obtener la etapa máxima actual de todas las postulaciones de esta oferta
            $max_etapa = $wpdb->get_var($wpdb->prepare(
                "SELECT MAX(etapa) FROM {$table} WHERE oferta_id=%d", 
                $oferta_id
            ));
            
            // Actualizar etapa_actual de la oferta
            $wpdb->update($table_ofertas, array(
                'etapa_actual' => intval($max_etapa)
            ), array('id' => $oferta_id));
        }
        
        wp_safe_redirect(wp_get_referer());
        exit;
    }


    /**
     * Handler: pasar múltiples postulantes a una etapa (masivo)
     */
    public static function handle_pasar_etapa_masivo() {
        if (!current_user_can('manage_options')) wp_die('No autorizado');
        
        global $wpdb;
        $table_post = $wpdb->prefix . 'olc_postulaciones';
        $table_ofertas = $wpdb->prefix . 'olc_ofertas';
    
        // IDs seleccionados (array)
        $ids = isset($_POST['postulantes']) ? $_POST['postulantes'] : [];
    
        // Etapa a la que se enviará
        $etapa = isset($_POST['etapa']) ? intval($_POST['etapa']) : 0;
    
        if (empty($ids) || !$etapa) {
            wp_safe_redirect(
                wp_get_referer() ? wp_get_referer() : admin_url('admin.php?page=olc_postulaciones')
            );
            exit;
        }
    
        // ========================================
        // Determinar estado según etapa
        // ========================================
        $estados = array(
            1 => 'Evaluado',
            2 => 'Entrevista',
            3 => 'Finalista'
        );
        
        $nuevo_estado = isset($estados[$etapa]) ? $estados[$etapa] : 'Enviado';
    
        // ========================================
        // Actualizar postulaciones en lote
        // ========================================
        $ofertas_afectadas = array(); // Para trackear qué ofertas actualizar
        
        foreach ($ids as $id) {
            $id = intval($id);
            
            // Actualizar postulación
            $wpdb->update($table_post, array(
                'estado_postulacion' => $nuevo_estado,
                'etapa' => $etapa
            ), array('id' => $id));
            
            // ✅ Obtener oferta_id de esta postulación
            $oferta_id = $wpdb->get_var($wpdb->prepare(
                "SELECT oferta_id FROM {$table_post} WHERE id=%d", 
                $id
            ));
            
            if ($oferta_id && !in_array($oferta_id, $ofertas_afectadas)) {
                $ofertas_afectadas[] = intval($oferta_id);
            }
        }
    
        // ========================================
        // ✅ Actualizar etapa_actual en las ofertas afectadas
        // ========================================
        foreach ($ofertas_afectadas as $oferta_id) {
            // Obtener la etapa máxima actual de todas las postulaciones de esta oferta
            $max_etapa = $wpdb->get_var($wpdb->prepare(
                "SELECT MAX(etapa) FROM {$table_post} WHERE oferta_id=%d", 
                $oferta_id
            ));
            
            // Actualizar etapa_actual de la oferta
            $wpdb->update($table_ofertas, array(
                'etapa_actual' => intval($max_etapa)
            ), array('id' => $oferta_id));
        }
    
        wp_safe_redirect(
            wp_get_referer() ? wp_get_referer() : admin_url('admin.php?page=olc_postulaciones')
        );
        exit;
    }



    /**
     * Handler: calcular puntaje automático + estado bancario (historial)
     * Guarda cada criterio en wp_olc_puntuaciones y actualiza puntaje_total en wp_olc_postulaciones.
     * Uso: admin-post.php?action=olc_calcular_puntaje (POST)
     * Campos esperados: postulacion_id, estado_bancario (opcional)
     */
    public static function handle_calcular_puntaje() {
        if (!current_user_can('manage_options')) wp_die('No autorizado');
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'olc_calcular_puntaje')) {
            wp_safe_redirect(wp_get_referer() ?: admin_url('admin.php?page=olc_etapas'));
            exit;
        }
    
        global $wpdb;
        $postulacion_id = isset($_POST['postulacion_id']) ? intval($_POST['postulacion_id']) : 0;
        if (!$postulacion_id) {
            wp_safe_redirect(wp_get_referer() ?: admin_url('admin.php?page=olc_etapas'));
            exit;
        }
    
        // Obtener postulacion y datos relacionados
        $tbl_post = $wpdb->prefix . 'olc_postulaciones';
        $tbl_postulantes = $wpdb->prefix . 'olc_postulantes';
        $tbl_ofertas = $wpdb->prefix . 'olc_ofertas';
        $tbl_puntuaciones = $wpdb->prefix . 'olc_puntuaciones';
    
        $post = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tbl_post} WHERE id = %d LIMIT 1", $postulacion_id));
        if (!$post) {
            wp_safe_redirect(wp_get_referer() ?: admin_url('admin.php?page=olc_etapas'));
            exit;
        }
    
        $postulante = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tbl_postulantes} WHERE id = %d LIMIT 1", $post->postulante_id ?? $post->user_id /* fallback */));
        // Nota: si tu tabla relaciona postulacion->user_id en vez de postulante_id, ajústalo.
        if (!$postulante && !empty($post->user_id)) {
            $postulante = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tbl_postulantes} WHERE user_id = %d LIMIT 1", $post->user_id));
        }
    
        $oferta = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tbl_ofertas} WHERE id = %d LIMIT 1", $post->oferta_id));
    
        // -----------------------
        // 1) Puntaje Edad
        // -----------------------
        $edad_score = 0;
        $comentario_edad = '';
        if (!empty($postulante->fecha_nacimiento) && $fecha = strtotime($postulante->fecha_nacimiento)) {
            $age = floor((time() - $fecha) / (365*24*60*60));
            if ($age >= 18 && $age <= 27) $edad_score = 20;
            elseif ($age >= 28 && $age <= 32) $edad_score = 10;
            elseif ($age >= 36 && $age <= 80) $edad_score = 0;
            else $edad_score = 0;
            $comentario_edad = "Edad {$age} años";
        } else {
            $edad_score = 0;
            $comentario_edad = "Fecha de nacimiento no disponible";
        }
    
        // Inserta registro (historial)
        $wpdb->insert($tbl_puntuaciones, array(
            'postulacion_id' => $postulacion_id,
            'criterio' => 'edad',
            'valor' => $edad_score,
            'comentario' => $comentario_edad,
            'creado_por' => get_current_user_id(),
            'fecha' => current_time('mysql')
        ));
    
        // -----------------------
        // 2) Estado Bancario (manual)
        // -----------------------
        $estado_bancario = sanitize_text_field($_POST['estado_bancario'] ?? '');
        $banco_score = 0;
        $comentario_banco = $estado_bancario ?: 'No definido';
        if (!empty($estado_bancario)) {
            if (strtolower($estado_bancario) === 'bueno') $banco_score = 60;
            elseif (strtolower($estado_bancario) === 'regular') $banco_score = 20;
            else $banco_score = 0;
        } else {
            // Si no lo envían, no insertar (pero aquí insertamos con 0 para dejar rastro)
            $banco_score = 0;
        }
    
        $wpdb->insert($tbl_puntuaciones, array(
            'postulacion_id' => $postulacion_id,
            'criterio' => 'estado_bancario',
            'valor' => $banco_score,
            'comentario' => $comentario_banco,
            'creado_por' => get_current_user_id(),
            'fecha' => current_time('mysql')
        ));
    
        // -----------------------
        // 3) Profesión (match con oferta)
        // -----------------------
        $prof_score = 10;
        $comentario_prof = '';
        $prof_post = strtolower(trim($postulante->profesion ?? ''));
        $prof_oferta = strtolower(trim($oferta->profesion_requerida ?? $oferta->perfil ?? ''));
    
        if (!empty($prof_post) && !empty($prof_oferta)) {
            if ($prof_post === $prof_oferta) {
                $prof_score = 20;
                $comentario_prof = "Coincide exactamente ({$postulante->profesion})";
            } elseif (stripos($prof_oferta, $prof_post) !== false || stripos($prof_post, $prof_oferta) !== false) {
                $prof_score = 20; // muy similar
                $comentario_prof = "Coincidencia parcial ({$postulante->profesion} vs {$oferta->profesion_requerida})";
            } elseif (!empty($prof_post) && !empty($prof_oferta)) {
                // palabras compartidas
                $words_post = preg_split('/\s+/', $prof_post);
                $matched = false;
                foreach ($words_post as $w) {
                    if (strlen($w) > 3 && stripos($prof_oferta, $w) !== false) { $matched = true; break; }
                }
                if ($matched) {
                    $prof_score = 10;
                    $comentario_prof = "Relación por palabras ({$postulante->profesion})";
                } else {
                    $prof_score = 0;
                    $comentario_prof = "Profesión distinta";
                }
            }
        } else {
            $prof_score = 0;
            $comentario_prof = "Datos de profesión incompletos";
        }
    
        $wpdb->insert($tbl_puntuaciones, array(
            'postulacion_id' => $postulacion_id,
            'criterio' => 'profesion_match',
            'valor' => $prof_score,
            'comentario' => $comentario_prof,
            'creado_por' => get_current_user_id(),
            'fecha' => current_time('mysql')
        ));
    
        
        // -----------------------
        // 4) Experiencia (usar valor desde wp_olc_postulaciones -> $post)
        // -----------------------
        // DEBUG (opcional, borra si no quieres logs)
        error_log("OLC DEBUG - POST completo: " . print_r($_POST, true));

        
        $db_exp_raw = $post->experiencia_anios ?? null; // <-- <--- aquí usamos $post, NO $postulante
        $db_exp_otro_text = $post->experiencia_otro_text ?? ''; // si no existe, quedará vacío
        
        // Si por alguna razón quieres respetar POST si el cálculo es inmediato tras enviar:
        $posted_exp = isset($_POST['experiencia_anios']) ? sanitize_text_field(wp_unslash($_POST['experiencia_anios'])) : null;
        if ($posted_exp === 'other' || strtolower(trim($posted_exp)) === 'otra') {
            $exp = 4;
        } elseif (is_numeric($posted_exp)) {
            $exp = intval($posted_exp);
        } else {
            // usar valor desde la tabla postulaciones
            if ($db_exp_raw === null || $db_exp_raw === '') {
                $exp = 0;
            } else {
                $dbv = (string) $db_exp_raw;
                $dbv_trim = strtolower(trim($dbv));
                if ($dbv_trim === 'other' || $dbv_trim === 'otra') {
                    $exp = 4;
                } elseif (is_numeric($dbv_trim)) {
                    $exp = intval($dbv_trim);
                } else {
                    $exp = 0;
                }
            }
        }
        
        // fallback si hay texto libre (si existe)
        if ($exp === 0 && !empty(trim($db_exp_otro_text))) {
            $exp = 4;
        }
        
        // validar rango
        if (!in_array($exp, array(0,1,2,3,4), true)) {
            $exp = 0;
        }
        
        // aplicar puntajes solicitados
        $exp_score = 0;
        $comentario_exp = "";
        switch ($exp) {
            case 0:
                $exp_score = 0;
                $comentario_exp = "Ninguna experiencia";
                break;
            case 1:
            case 2:
                $exp_score = 10;
                $comentario_exp = "Menos de 1 año";
                break;
            case 3:
                $exp_score = 20;
                $comentario_exp = "Entre 1 y 2 años";
                break;
            case 4:
                $exp_score = 0;
                $comentario_exp = "Otra experiencia";
                if (!empty($db_exp_otro_text)) $comentario_exp .= ": " . $db_exp_otro_text;
                break;
            default:
                $exp_score = 0;
                $comentario_exp = "Experiencia no declarada";
                break;
        }
        
        // Guardar (igual que tu código actual)
        $last = $wpdb->get_row( $wpdb->prepare("
            SELECT id FROM {$tbl_puntuaciones}
            WHERE postulacion_id = %d AND criterio = %s
            ORDER BY id DESC LIMIT 1
        ", $postulacion_id, 'experiencia') );
        
        if ( $last && !empty($last->id) ) {
            $wpdb->update(
                $tbl_puntuaciones,
                array(
                    'valor'      => $exp_score,
                    'comentario' => $comentario_exp,
                    'creado_por' => get_current_user_id(),
                    'fecha'      => current_time('mysql')
                ),
                array( 'id' => intval($last->id) ),
                array( '%d', '%s', '%d', '%s' ),
                array( '%d' )
            );
        } else {
            $wpdb->insert(
                $tbl_puntuaciones,
                array(
                    'postulacion_id' => $postulacion_id,
                    'criterio'       => 'experiencia',
                    'valor'          => $exp_score,
                    'comentario'     => $comentario_exp,
                    'creado_por'     => get_current_user_id(),
                    'fecha'          => current_time('mysql')
                ),
                array('%d','%s','%d','%s','%d','%s')
            );
        }


    
        // -----------------------
        // Recalcular puntaje_total usando la ÚLTIMA entrada por criterio
        // -----------------------
        // Query: sumar los últimos valores por criterio
        $sql = $wpdb->prepare("
            SELECT SUM(t.valor) as total FROM {$tbl_puntuaciones} t
            JOIN (
                SELECT criterio, MAX(id) AS mid
                FROM {$tbl_puntuaciones}
                WHERE postulacion_id = %d
                GROUP BY criterio
            ) m ON t.criterio = m.criterio AND t.id = m.mid
            WHERE t.postulacion_id = %d
        ", $postulacion_id, $postulacion_id);
    
        $total = floatval($wpdb->get_var($sql));
        if ($total === null) $total = 0;
    
        // Actualizar tabla postulaciones
        $wpdb->update($tbl_post, array('puntaje_total' => $total), array('id' => $postulacion_id));
    
        // Redirigir de vuelta con mensaje
        $redirect = wp_get_referer() ?: admin_url('admin.php?page=olc_etapas&oferta_id=' . intval($post->oferta_id));
        $redirect = add_query_arg('olc_msg', 'puntaje_actualizado', $redirect);
        wp_safe_redirect($redirect);
        exit;
    }

    /**
     * Pase a la etapa 2
     * Guardar entrevista (AJAX + POST normal)
     */
    
    /**
     * Guardar estado y/o puntaje de entrevista (AJAX)
     */
    public static function handle_guardar_entrevista() {
        // Verificar permisos
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('No autorizado');
        }
    
        global $wpdb;
        $table = $wpdb->prefix . 'olc_postulaciones';
    
        // Obtener ID
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$id) {
            wp_send_json_error('ID inválido');
        }
    
        // Verificar que existe la postulación
        $postulante = $wpdb->get_row($wpdb->prepare("
            SELECT id, puntaje_total, puntaje_entrevista 
            FROM {$table} 
            WHERE id = %d
        ", $id));
    
        if (!$postulante) {
            wp_send_json_error('Postulación no encontrada (ID: ' . $id . ')');
        }
    
        // Preparar datos a actualizar
        $datos_actualizar = array();
        $nuevo_puntaje_total = null;
    
        // ========================================
        // 1. ACTUALIZAR ESTADO (si viene)
        // ========================================
        if (isset($_POST['estado_entrevista'])) {
            $estado = sanitize_text_field($_POST['estado_entrevista']);
            
            // Validar que sea un valor permitido
            $estados_validos = array('No convocado', 'Convocado', 'No asistió', 'Entrevistado');
            if (in_array($estado, $estados_validos)) {
                $datos_actualizar['estado_entrevista'] = $estado;
            }
        }
    
        // ========================================
        // 2. ACTUALIZAR PUNTAJE (si viene)
        // ========================================
        if (isset($_POST['puntaje_entrevista'])) {
            $nuevo_puntaje = floatval($_POST['puntaje_entrevista']);
            $puntaje_anterior = floatval($postulante->puntaje_entrevista);
            
            // Actualizar puntaje individual
            $datos_actualizar['puntaje_entrevista'] = $nuevo_puntaje;
            
            // Recalcular puntaje total
            $nuevo_puntaje_total = floatval($postulante->puntaje_total) - $puntaje_anterior + $nuevo_puntaje;
            $datos_actualizar['puntaje_total'] = $nuevo_puntaje_total;
    
            // ✅ NUEVO: UPDATE o INSERT en tabla de puntuaciones
            if ($nuevo_puntaje != $puntaje_anterior) {
                $puntuaciones_table = $wpdb->prefix . 'olc_puntuaciones';
                
                // Verificar si ya existe un registro para este criterio
                $existe = $wpdb->get_var($wpdb->prepare("
                    SELECT id FROM {$puntuaciones_table} 
                    WHERE postulacion_id = %d AND criterio = 'entrevista'
                ", $id));
    
                if ($existe) {
                    // ✅ UPDATE: Actualizar el registro existente
                    $wpdb->update(
                        $puntuaciones_table,
                        array(
                            'valor' => $nuevo_puntaje,
                            'comentario' => 'Puntaje de entrevista (actualizado)',
                            'creado_por' => get_current_user_id()
                        ),
                        array(
                            'postulacion_id' => $id,
                            'criterio' => 'entrevista'
                        ),
                        array('%f', '%s', '%d'),
                        array('%d', '%s')
                    );
                } else {
                    // ✅ INSERT: Crear nuevo registro
                    $wpdb->insert($puntuaciones_table, array(
                        'postulacion_id' => $id,
                        'criterio' => 'entrevista',
                        'valor' => $nuevo_puntaje,
                        'comentario' => 'Puntaje de entrevista',
                        'creado_por' => get_current_user_id()
                    ));
                }
            }
        }
    
        // ========================================
        // 3. GUARDAR EN BD
        // ========================================
        if (!empty($datos_actualizar)) {
            $resultado = $wpdb->update(
                $table,
                $datos_actualizar,
                array('id' => $id),
                array('%s', '%f', '%f'),
                array('%d')
            );
    
            if ($resultado === false) {
                wp_send_json_error('Error al actualizar en BD: ' . $wpdb->last_error);
            }
        }
    
        // ========================================
        // 4. RESPUESTA EXITOSA
        // ========================================
        wp_send_json_success(array(
            'message' => 'Guardado correctamente',
            'puntaje_total' => $nuevo_puntaje_total ?? floatval($postulante->puntaje_total),
            'estado_entrevista' => $datos_actualizar['estado_entrevista'] ?? null
        ));
    }

    
    /**
     * Redirección segura reutilizable
     */
    private static function redirect_back() {
        $ref = wp_get_referer();
        wp_safe_redirect($ref ? $ref : admin_url('admin.php?page=olc_postulaciones'));
        exit;
    }


    /**
     * Handler: guardar selección final (POST)
     * expected POST fields: id, resultado_final
     */
    public static function handle_guardar_seleccion() {
        if (!current_user_can('manage_options')) wp_die('No autorizado');
        global $wpdb;
    
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $resultado = isset($_POST['resultado_final']) ? sanitize_text_field($_POST['resultado_final']) : '';
    
        if (!$id || !$resultado) {
            wp_safe_redirect(wp_get_referer() ? wp_get_referer() : admin_url('admin.php?page=olc_postulaciones'));
            exit;
        }
    
        $table_post = $wpdb->prefix . 'olc_postulaciones';
        $table_ofertas = $wpdb->prefix . 'olc_ofertas';
        
        // Actualizar resultado_final en la postulación
        $wpdb->update($table_post, array(
            'resultado_final' => $resultado,
            'etapa' => 3
        ), array('id' => $id));
    
        // Obtener oferta_id y postulante_id
        $postulacion = $wpdb->get_row($wpdb->prepare(
            "SELECT oferta_id, user_id FROM {$table_post} WHERE id=%d", 
            $id
        ));
        
        if (!$postulacion) {
            wp_safe_redirect(wp_get_referer() ? wp_get_referer() : admin_url('admin.php?page=olc_postulaciones'));
            exit;
        }
        
        $oferta_id = intval($postulacion->oferta_id);
        
        // ========================================
        // ✅ NUEVO: Si es GANADOR, actualizar la oferta
        // ========================================
        if ($resultado === 'Ganador' || strtolower($resultado) === 'ganador') {
            $wpdb->update($table_ofertas, array(
                'estado' => 'finalizada',
                'ganador_id' => $id,  // ✅ Guardar ID de la postulación ganadora
                'etapa_actual' => 3
            ), array('id' => $oferta_id));
        }
    
        wp_safe_redirect(wp_get_referer() ? wp_get_referer() : admin_url('admin.php?page=olc_postulaciones'));
        exit;
    }
    
    

        public static function handle_guardar_estado_bancario() {
            if (!current_user_can('manage_options')) wp_die('No autorizado');
            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'olc_guardar_estado_bancario')) wp_die('Nonce inválido');
        
            global $wpdb;
            $postulacion_id = isset($_POST['postulacion_id']) ? intval($_POST['postulacion_id']) : 0;
            $estado = isset($_POST['estado_bancario']) ? sanitize_text_field($_POST['estado_bancario']) : '';
        
            if (!$postulacion_id || $estado === '') {
                wp_safe_redirect(wp_get_referer() ? wp_get_referer() : admin_url('admin.php?page=olc_etapas'));
                exit;
            }
        
            // map estado => valor
            $map = [
                'Bueno'   => 60,
                'Regular' => 20,
                'Malo'    => 0
            ];
            $valor = isset($map[$estado]) ? intval($map[$estado]) : 0;
        
            // insertar o actualizar en wp_olc_puntuaciones (criterio = 'estado_bancario')
            $puntuaciones_table = $wpdb->prefix . 'olc_puntuaciones';
        
            // verificar si ya existe
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$puntuaciones_table} WHERE postulacion_id = %d AND criterio = %s LIMIT 1",
                $postulacion_id, 'estado_bancario'
            ));
        
            $data = array(
                'postulacion_id' => $postulacion_id,
                'criterio'       => 'estado_bancario',
                'valor'          => $valor,
                'comentario'     => "Estado bancario: {$estado}",
                'creado_por'     => get_current_user_id(),
                'fecha'          => current_time('mysql')
            );
        
            if ($exists) {
                // actualizar (no tocar fecha original si no quieres)
                $wpdb->update($puntuaciones_table, $data, array('id' => intval($exists)));
            } else {
                $wpdb->insert($puntuaciones_table, $data);
            }
        
            // recalcular total de la postulacion sumando valores en olc_puntuaciones
            self::recalcular_total_postulacion($postulacion_id);
        
            wp_safe_redirect(wp_get_referer() ? wp_get_referer() : admin_url('admin.php?page=olc_etapas'));
            exit;
        }


        // === ETAPA 2: Actualizar Entrevista ===
    public static function actualizar_entrevista() {
        if (!current_user_can('manage_options')) wp_die('No autorizado');
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'olc_actualizar_entrevista')) wp_die('Nonce inválido');

        global $wpdb;
        $table = $wpdb->prefix . 'olc_postulaciones';

        $id = intval($_POST['postulacion_id']);
        $estado = sanitize_text_field($_POST['estado_entrevista']);
        $puntaje = intval($_POST['puntaje_entrevista']);

        $wpdb->update($table, [
            'estado_entrevista' => $estado,
            'puntaje_entrevista' => $puntaje
        ], ['id' => $id]);

        $oferta_id = $wpdb->get_var($wpdb->prepare("SELECT oferta_id FROM {$table} WHERE id = %d", $id));
        wp_safe_redirect(admin_url("admin.php?page=olc_etapas&oferta_id={$oferta_id}&etapa=2"));
        exit;
    }

    // === ETAPA 3: Actualizar Resultado Final ===
    public static function actualizar_final() {
        if (!current_user_can('manage_options')) wp_die('No autorizado');
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'olc_actualizar_final')) wp_die('Nonce inválido');

        global $wpdb;
        $table = $wpdb->prefix . 'olc_postulaciones';

        $id = intval($_POST['postulacion_id']);
        $resultado = sanitize_text_field($_POST['resultado_final']);

        $wpdb->update($table, [
            'resultado_final' => $resultado
        ], ['id' => $id]);

        $oferta_id = $wpdb->get_var($wpdb->prepare("SELECT oferta_id FROM {$table} WHERE id = %d", $id));
        wp_safe_redirect(admin_url("admin.php?page=olc_etapas&oferta_id={$oferta_id}&etapa=3"));
        exit;
    }
    
    
    public static function recalcular_total_postulacion($postulacion_id) {
        global $wpdb;
        $puntuaciones_table = $wpdb->prefix . 'olc_puntuaciones';
        $postulaciones_table = $wpdb->prefix . 'olc_postulaciones';
    
        // Sumar todos los valores para esta postulacion
        $sum = $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(valor),0) FROM {$puntuaciones_table} WHERE postulacion_id = %d",
            $postulacion_id
        ));
    
        $sum = floatval($sum);
    
        // Actualizar puntaje_total en la tabla de postulaciones
        $wpdb->update($postulaciones_table, array('puntaje_total' => $sum), array('id' => $postulacion_id));
    }


    /* -----------------------------
       Página: Bolsa de Postulantes
       ----------------------------- */
    public static function page_bolsa_postulantes() {
        global $wpdb;
        $tabla = $wpdb->prefix . 'olc_postulaciones';
        
        // Obtener postulantes de la bolsa (oferta_id = 0)
        $postulantes = $wpdb->get_results("
            SELECT * FROM {$tabla} 
            WHERE oferta_id = 0 
            ORDER BY fecha_postulacion DESC
        ");
        
        require_once plugin_dir_path(__FILE__) . 'admin-bolsa-postulantes.php';
    }


    /* -----------------------------
       Handler: Asociar postulante de bolsa a oferta
       ----------------------------- */
    public static function handle_asociar_bolsa_oferta() {
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado');
        }
        
        check_admin_referer('olc_asociar_bolsa');
        
        global $wpdb;
        $tabla = $wpdb->prefix . 'olc_postulaciones';
        
        $postulante_id = isset($_POST['postulante_id']) ? intval($_POST['postulante_id']) : 0;
        $oferta_id = isset($_POST['oferta_id']) ? intval($_POST['oferta_id']) : 0;
        
        if (!$postulante_id || !$oferta_id) {
            wp_die('Datos incompletos');
        }
        
        // ========================================
        // Verificar que el postulante existe y está en la bolsa
        // ========================================
        $postulante = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$tabla} WHERE id = %d AND oferta_id = 0",
            $postulante_id
        ));
        
        if (!$postulante) {
            wp_die('Postulante no encontrado en la bolsa');
        }
        
        // ========================================
        // Verificar que la oferta existe y está activa
        // ========================================
        $tabla_ofertas = $wpdb->prefix . 'olc_ofertas';
        $oferta = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$tabla_ofertas} WHERE id = %d",
            $oferta_id
        ));
        
        if (!$oferta) {
            wp_die('Oferta no encontrada');
        }
        
        // ========================================
        // Verificar que no esté ya postulado a esa oferta
        // ========================================
        $ya_postulado = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$tabla} 
             WHERE user_id = %d AND oferta_id = %d",
            $postulante->user_id,
            $oferta_id
        ));
        
        if ($ya_postulado > 0) {
            wp_die('Este postulante ya está asociado a esta oferta');
        }
        
        // ========================================
        // ✅ CREAR NUEVA POSTULACIÓN asociada a la oferta
        // ========================================
        $nueva_postulacion = array(
            'oferta_id' => $oferta_id,
            'user_id' => $postulante->user_id,
            'nombre' => $postulante->nombre,
            'dni' => $postulante->dni,
            'telefono' => $postulante->telefono,
            'email' => $postulante->email,
            'fecha_nacimiento' => $postulante->fecha_nacimiento,
            'ciudad' => $postulante->ciudad,
            'profesion' => $postulante->profesion,
            'experiencia_anios' => $postulante->experiencia_anios,
            'ccvv_path' => $postulante->ccvv_path,
            'fecha_ccvv_subida' => $postulante->fecha_ccvv_subida,
            'fecha_postulacion' => current_time('mysql'),
            'estado_postulacion' => 'Enviado', // ✅ Inicia en Etapa 1
            'etapa' => 1,
            'puntaje_total' => 0
        );
        
        $resultado = $wpdb->insert($tabla, $nueva_postulacion);
        
        if ($resultado === false) {
            wp_die('Error al crear la postulación: ' . $wpdb->last_error);
        }
        
        $nueva_postulacion_id = $wpdb->insert_id;
        
        // ========================================
        // ✅ Calcular puntajes automáticos
        // ========================================
        // Reutilizar la función que ya existe en OLC_Public
        OLC_Public::calcular_y_guardar_puntajes(
            $nueva_postulacion_id,
            $postulante->user_id,
            $oferta_id
        );
        
        // ========================================
        // ✅ OPCIONAL: Eliminar de la bolsa o marcar como asociado
        // ========================================
        // Opción A: Eliminar el registro de la bolsa
        // $wpdb->delete($tabla, array('id' => $postulante_id));
        
        // Opción B: Marcar como asociado (mantener historial)
        $wpdb->update($tabla, array(
            'estado_postulacion' => 'Asociado a oferta',
            'etapa' => -1 // Etapa especial para indicar que fue movido
        ), array('id' => $postulante_id));
        
        // ========================================
        // Redirigir con mensaje de éxito
        // ========================================
        wp_safe_redirect(
            add_query_arg(
                array(
                    'page' => 'olc_bolsa_postulantes',
                    'mensaje' => 'asociado'
                ),
                admin_url('admin.php')
            )
        );
        exit;
    }

    /* -----------------------------
       Handler: Eliminar oferta
       ----------------------------- */
    public static function handle_delete_oferta() {
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado');
        }
        
        // Verificar nonce
        check_admin_referer('olc_delete_oferta');
        
        global $wpdb;
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        if (!$id) {
            wp_die('ID de oferta inválido');
        }
        
        // ========================================
        // Verificar si la oferta existe
        // ========================================
        $tabla_ofertas = $wpdb->prefix . 'olc_ofertas';
        $oferta = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$tabla_ofertas} WHERE id = %d",
            $id
        ));
        
        if (!$oferta) {
            wp_die('Oferta no encontrada');
        }
        
        // ========================================
        // Verificar si tiene postulaciones
        // ========================================
        $tabla_post = $wpdb->prefix . 'olc_postulaciones';
        $tiene_postulaciones = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$tabla_post} WHERE oferta_id = %d",
            $id
        ));
        
        // ========================================
        // OPCIÓN 1: Impedir eliminar si tiene postulaciones
        // ========================================
        if ($tiene_postulaciones > 0) {
            wp_die('
                <h1>No se puede eliminar</h1>
                <p>Esta oferta tiene <strong>' . intval($tiene_postulaciones) . ' postulación(es)</strong> asociadas.</p>
                <p>Por seguridad, no se permite eliminar ofertas con postulaciones.</p>
                <p><a href="' . admin_url('admin.php?page=olc_ofertas') . '" class="button button-primary">Volver al listado</a></p>
            ');
        }
        
        // ========================================
        // OPCIÓN 2: Eliminar en cascada (comentada por seguridad)
        // ========================================
        /*
        if ($tiene_postulaciones > 0) {
            // Eliminar postulaciones asociadas
            $wpdb->delete($tabla_post, array('oferta_id' => $id));
            
            // Eliminar puntuaciones de esas postulaciones
            $tabla_punt = $wpdb->prefix . 'olc_puntuaciones';
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$tabla_punt} 
                 WHERE postulacion_id IN (
                    SELECT id FROM {$tabla_post} WHERE oferta_id = %d
                 )",
                $id
            ));
        }
        */
        
        // ========================================
        // Eliminar la oferta
        // ========================================
        $resultado = $wpdb->delete($tabla_ofertas, array('id' => $id));
        
        if ($resultado === false) {
            wp_die('Error al eliminar la oferta: ' . $wpdb->last_error);
        }
        
        // ========================================
        // Redirigir con mensaje de éxito
        // ========================================
        wp_safe_redirect(
            add_query_arg(
                array(
                    'page' => 'olc_ofertas',
                    'mensaje' => 'eliminada'
                ),
                admin_url('admin.php')
            )
        );
        exit;
    }



}
