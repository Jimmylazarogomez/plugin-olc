<?php
if (!defined('ABSPATH')) exit;

class OLC_Public {

    public static function init() {
        // Shortcodes
        add_shortcode('olc_ofertas', array(__CLASS__, 'shortcode_list'));
        add_shortcode('olc_detalle_oferta', array(__CLASS__, 'shortcode_detail'));
        add_shortcode('olc_mis_postulaciones', array(__CLASS__, 'shortcode_mis_postulaciones'));
        add_shortcode('olc_panel_postulante', array(__CLASS__, 'shortcode_panel_postulante'));

        // Scripts
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_public_scripts'));

        // AJAX handlers (frontend)
        // Action name: 'olc_guardar_postulante' (used in the form: <input name="action" value="olc_guardar_postulante">)
        add_action('wp_ajax_olc_guardar_postulante', array(__CLASS__, 'olc_guardar_postulante_ajax'));
        add_action('wp_ajax_nopriv_olc_guardar_postulante', array(__CLASS__, 'olc_guardar_postulante_ajax'));

        add_action('wp_ajax_olc_registrar_postulacion', array(__CLASS__, 'olc_registrar_postulacion_ajax'));
        add_action('wp_ajax_nopriv_olc_registrar_postulacion', array(__CLASS__, 'olc_registrar_postulacion_ajax'));

        // Backward-compatible handler for non-AJAX (form POST fallback)
        add_action('init', array(__CLASS__, 'handle_requests'));
        
        // Perfil para actualizar ccvv
        add_action('wp_ajax_olc_actualizar_cv', array(__CLASS__, 'actualizar_cv_ajax'));
        
        // Handler para bolsa de CVs
        add_action('wp_ajax_olc_enviar_cv_bolsa', array(__CLASS__, 'enviar_cv_bolsa_ajax'));
        add_action('wp_ajax_nopriv_olc_enviar_cv_bolsa', array(__CLASS__, 'enviar_cv_bolsa_ajax'));
        
        // ========================================
        // ✅ NUEVOS HOOKS PARA POSTULANTES
        // ========================================
        
        // 1. Redirigir al login
        add_filter('login_redirect', array(__CLASS__, 'redirect_postulante_after_login'), 10, 3);
        
        // 2. Bloquear acceso al admin
        add_action('admin_init', array(__CLASS__, 'bloquear_admin_para_postulantes'));
        
        // 3. Cambiar destino del registro por defecto
        add_filter('registration_redirect', array(__CLASS__, 'redirect_postulante_after_register'));
        
        // Backward-compatible handler
        add_action('init', array(__CLASS__, 'handle_requests'));
    }

    /* -----------------------------
       Enqueue scripts & localize
       ----------------------------- */
    public static function enqueue_public_scripts() {
        $script_path = plugin_dir_path(__FILE__) . '../assets/js/olc-public.js';
        $script_url  = plugin_dir_url(__FILE__) . '../assets/js/olc-public.js';

        // Avoid filemtime warning if file missing
        $ver = file_exists($script_path) ? filemtime($script_path) : false;

        wp_enqueue_script('jquery'); // ensure jQuery present
        if ($ver) {
            wp_enqueue_script('olc-public', $script_url, array('jquery'), $ver, true);
        } else {
            // register a placeholder so wp_localize_script works
            wp_register_script('olc-public', '', array('jquery'), false, true);
            wp_enqueue_script('olc-public');
        }

        // Note: our form uses a server-side nonce field named 'olc_nonce' (see wp_nonce_field below).
        // We'll also create a separate nonce for JS if needed (named 'olc_nonce_action').
        wp_localize_script('olc-public', 'olc_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce_action'    => wp_create_nonce('olc_nonce_action'),
            'msg_login' => __('Debes iniciar sesión para postular.', 'ofertas-laborales-crecer'),
            'msg_saved' => __('Perfil guardado correctamente.', 'ofertas-laborales-crecer'),
            'msg_postulado' => __('Postulación registrada correctamente.', 'ofertas-laborales-crecer'),
            'msg_already' => __('Ya postulaste a esta oferta.', 'ofertas-laborales-crecer')
        ));
    }

    /* -----------------------------
    Shortcode: listado de ofertas CON FILTROS (CORREGIDO)
    ----------------------------- */

    public static function shortcode_list($atts) {
        global $wpdb;
        $table = $wpdb->prefix . 'olc_ofertas';
        
        // ========================================
        // OBTENER VALORES DE FILTROS (GET params)
        // ========================================
        $filtro_tipo = isset($_GET['tipo_oferta']) ? sanitize_text_field($_GET['tipo_oferta']) : '';
        $filtro_sede = isset($_GET['sede']) ? sanitize_text_field($_GET['sede']) : '';
        $filtro_estado = isset($_GET['estado_oferta']) ? sanitize_text_field($_GET['estado_oferta']) : '';
        
        // ========================================
        // ✅ CONSTRUIR CONSULTA CON FILTROS (CORREGIDO)
        // ========================================
        
        // ✅ Calcular fecha límite: 1 mes después de expiración
        $fecha_limite = date('Y-m-d H:i:s', strtotime('-1 month'));
        
        // Mostrar ofertas publicadas/activas que NO hayan expirado hace más de 1 mes
        $where = "WHERE (estado = 'publicada' OR estado = 'activa' OR estado IS NULL OR estado = '')";
        $where .= " AND (fecha_fin >= %s OR fecha_fin IS NULL)";
        $params = array($fecha_limite);
        
        if (!empty($filtro_tipo)) {
            $where .= " AND titulo = %s";
            $params[] = $filtro_tipo;
        }
        
        if (!empty($filtro_sede)) {
            $where .= " AND ciudad = %s";
            $params[] = $filtro_sede;
        }
        
        // ✅ Ejecutar query (siempre hay al menos 1 parámetro: fecha_limite)
        $query = "SELECT * FROM {$table} {$where} ORDER BY created_at DESC";
        $rows = $wpdb->get_results($wpdb->prepare($query, $params));
        
        // ✅ OBTENER OPCIONES PARA LOS FILTROS (CORREGIDO)
        // ========================================
        $fecha_limite_filtros = date('Y-m-d H:i:s', strtotime('-1 month'));
        
        // Tipos de oferta (desde titulo) - solo ofertas publicadas/activas válidas
        $tipos_disponibles = $wpdb->get_col($wpdb->prepare("
            SELECT DISTINCT titulo 
            FROM {$table} 
            WHERE (estado = 'publicada' OR estado = 'activa' OR estado IS NULL OR estado = '') 
            AND (fecha_fin >= %s OR fecha_fin IS NULL)
            AND titulo IS NOT NULL 
            AND titulo != '' 
            ORDER BY titulo ASC
        ", $fecha_limite_filtros));
        
        // Sedes disponibles (desde ciudad) - solo ofertas publicadas/activas válidas
        $sedes_disponibles = $wpdb->get_col($wpdb->prepare("
            SELECT DISTINCT ciudad 
            FROM {$table} 
            WHERE (estado = 'publicada' OR estado = 'activa' OR estado IS NULL OR estado = '') 
            AND (fecha_fin >= %s OR fecha_fin IS NULL)
            AND ciudad IS NOT NULL 
            AND ciudad != '' 
            ORDER BY ciudad ASC
        ", $fecha_limite_filtros));
        
        ob_start();
        
        
        // ========================================
        // HTML: FORMULARIO DE FILTROS
        // ========================================
        ?>
        <div class="olc-filtros-container">
            <form method="get" class="olc-filtros-form" id="olcFiltrosForm">
                <?php 
                // Preservar parámetros existentes (como page_id si usas permalinks)
                foreach ($_GET as $key => $value) {
                    if ($key !== 'tipo_oferta' && $key !== 'sede' && $key !== 'estado_oferta') {
                        echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '">';
                    }
                }
                ?>
                
                <div class="olc-filtro-item">
                    <label for="tipo_oferta">Tipo de Oferta:</label>
                    <select name="tipo_oferta" id="tipo_oferta">
                        <option value="">Todos los tipos</option>
                        <?php foreach ($tipos_disponibles as $tipo): ?>
                            <option value="<?php echo esc_attr($tipo); ?>" <?php selected($filtro_tipo, $tipo); ?>>
                                <?php echo esc_html($tipo); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="olc-filtro-item">
                    <label for="sede">Sede/Agencia:</label>
                    <select name="sede" id="sede">
                        <option value="">Todas las sedes</option>
                        <?php foreach ($sedes_disponibles as $sede): ?>
                            <option value="<?php echo esc_attr($sede); ?>" <?php selected($filtro_sede, $sede); ?>>
                                <?php echo esc_html($sede); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- ✅ NUEVO: Filtro por Estado -->
                <div class="olc-filtro-item">
                    <label for="estado_oferta">Estado:</label>
                    <select name="estado_oferta" id="estado_oferta">
                        <option value="">Todos los estados</option>
                        <option value="activa" <?php selected($filtro_estado, 'activa'); ?>>Activa</option>
                        <option value="evaluacion" <?php selected($filtro_estado, 'evaluacion'); ?>>En Evaluación</option>
                        <option value="finalizada" <?php selected($filtro_estado, 'finalizada'); ?>>Finalizada</option>
                    </select>
                </div>
                
                <div class="olc-filtro-item">
                    <button type="submit" class="olc-btn-filtrar">🔍 Filtrar</button>
                    <?php if (!empty($filtro_tipo) || !empty($filtro_sede) || !empty($filtro_estado)): ?>
                        <a href="<?php echo esc_url(strtok($_SERVER['REQUEST_URI'], '?')); ?>" class="olc-btn-limpiar">
                            ✖ Limpiar filtros
                        </a>
                    <?php endif; ?>
                </div>
                
                <!-- ✅ NUEVO: Botón Bolsa de Postulantes -->
                <div class="olc-filtro-item" style="margin-left: auto;">
                    <button type="button" id="btnAbrirBolsaCV" class="olc-btn-bolsa-cv">
                        📤 Déjanos tu CVV
                    </button>
                    <span style="display:block; font-size:11px; font-weight:normal; margin-top:3px;text-align: center;">
                        (Si no encontraste una oferta que se adecúe a ti)
                    </span>
                </div>
            </form>
        </div>
        
        <!-- ========================================
            ✅ MODAL MOVIDO AQUÍ (ANTES DEL CHECK DE RESULTADOS)
            MODAL: ENVIAR CV A BOLSA DE POSTULANTES
            ======================================== -->
        <?php 
        $user_id = get_current_user_id();
        $datos_postulante = null;
        
        if ($user_id) {
            $datos_postulante = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}olc_postulantes WHERE user_id=%d LIMIT 1", 
                $user_id
            ));
        }
        ?>
        
        <div id="modalBolsaCV" class="olc-modal" style="display:none;">
            <div class="olc-modal-content olc-modal-grande">
                <span class="olc-modal-close" id="cerrarModalBolsa">&times;</span>
                
                <?php if (!is_user_logged_in()): ?>
                    <!-- Si no está logueado -->
                    <h2>🔒 Inicia sesión para continuar</h2>
                    <p>Para dejar tu CV en nuestra bolsa de postulantes, necesitas tener una cuenta.</p>
                    <div style="margin-top:20px; text-align:center;">
                        <a href="<?php echo wp_login_url(get_permalink()); ?>" class="olc-btn-primary">
                            Iniciar Sesión
                        </a>
                        <p style="margin-top:15px;">
                            ¿No tienes cuenta? 
                            <a href="<?php echo wp_registration_url(); ?>" style="color:#667eea; font-weight:600;">
                                Regístrate aquí
                            </a>
                        </p>
                    </div>
                <?php else: ?>
                    <!-- Si está logueado -->
                    <h2>📤 Déjanos tu CV</h2>
                    <p style="color:#666; margin-bottom:25px;">
                        Aunque no hayas encontrado una oferta específica, queremos conocerte. 
                        Completa tus datos y te contactaremos cuando surja una oportunidad que se ajuste a tu perfil.
                    </p>
                    
                    <form id="formEnviarBolsaCV" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="olc_enviar_cv_bolsa">
                        <?php wp_nonce_field('olc_bolsa_cv', 'bolsa_nonce'); ?>
                        
                        <!-- Reutilizar los mismos campos del formulario existente -->
                        <div class="olc-form-row">
                            <div class="olc-form-col">
                                <label>Nombre completo *</label>
                                <input type="text" name="nombre" value="<?php echo esc_attr($datos_postulante->nombre ?? ''); ?>" required>
                            </div>
                            <div class="olc-form-col">
                                <label>DNI *</label>
                                <input type="text" name="dni" value="<?php echo esc_attr($datos_postulante->dni ?? ''); ?>" required>
                            </div>
                        </div>
        
                        <div class="olc-form-row">
                            <div class="olc-form-col">
                                <label>Teléfono *</label>
                                <input type="text" name="telefono" value="<?php echo esc_attr($datos_postulante->telefono ?? ''); ?>" required>
                            </div>
                            <div class="olc-form-col">
                                <label>Email *</label>
                                <input type="email" name="email" value="<?php echo esc_attr($datos_postulante->email ?? ''); ?>" required>
                            </div>
                        </div>
        
                        <div class="olc-form-row">
                            <div class="olc-form-col">
                                <label>Fecha de nacimiento</label>
                                <input type="date" name="fecha_nacimiento" value="<?php echo esc_attr($datos_postulante->fecha_nacimiento ?? ''); ?>">
                            </div>
                            <div class="olc-form-col">
                                <label>Ciudad *</label>
                                <input type="text" name="ciudad" value="<?php echo esc_attr($datos_postulante->ciudad ?? ''); ?>" required>
                            </div>
                        </div>
        
                        <div style="margin-bottom:15px;">
                            <label>Profesión *</label>
                            <select name="profesion" required style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px;">
                                <option value="">-- Selecciona tu profesión --</option>
                                
                                <optgroup label="Profesiones disponibles">
                                    <option value="Administración de Empresas" <?php selected($datos_postulante->profesion ?? '', 'Administración de Empresas');?>>Administración de Empresas</option>
                                    <option value="Contabilidad y Finanzas" <?php selected($datos_postulante->profesion ?? '', 'Contabilidad y Finanzas');?>>Contabilidad y Finanzas</option>
                                    <option value="Economía" <?php selected($datos_postulante->profesion ?? '', 'Economía');?>>Economía</option>
                                    <option value="Administración Bancaria" <?php selected($datos_postulante->profesion ?? '', 'Administración Bancaria');?>>Administración Bancaria</option>
                                    <option value="Banca y Finanzas" <?php selected($datos_postulante->profesion ?? '', 'Banca y Finanzas');?>>Banca y Finanzas</option>
                                    <option value="Administración de Negocios Bancarios y Financieros" <?php selected($datos_postulante->profesion ?? '', 'Administración de Negocios Bancarios y Financieros');?>>Administración de Negocios Bancarios y Financieros</option>
                                    <option value="Computación e Informática" <?php selected($datos_postulante->profesion ?? '', 'Computación e Informática');?>>Computación e Informática</option>
                                    <option value="Informática Administrativa" <?php selected($datos_postulante->profesion ?? '', 'Informática Administrativa');?>>Informática Administrativa</option>
                                    <option value="Gestor de Recuperaciones y cobranzas" <?php selected($datos_postulante->profesion ?? '', 'Gestor de Recuperaciones y cobranzas');?>>Gestor de Recuperaciones y cobranzas</option>
                                    <option value="Analista de Créditos" <?php selected($datos_postulante->profesion ?? '', 'Analista de Créditos');?>>Analista de Créditos</option>
                                    <option value="Cajero Promotor" <?php selected($datos_postulante->profesion ?? '', 'Cajero Promotor');?>>Cajero Promotor</option>
                                    <option value="Secretariado Ejecutivo" <?php selected($datos_postulante->profesion ?? '', 'Secretariado Ejecutivo');?>>Secretariado Ejecutivo</option>
                                </optgroup>
                                
                                <optgroup label="">
                                    <option value="Negocios Internacionales" <?php selected($datos_postulante->profesion ?? '', 'Negocios Internacionales');?>>Negocios Internacionales</option>
                                    <option value="Marketing" <?php selected($datos_postulante->profesion ?? '', 'Marketing');?>>Marketing</option>
                                    <option value="Ingeniería Industrial" <?php selected($datos_postulante->profesion ?? '', 'Ingeniería Industrial');?>>Ingeniería Industrial</option>
                                    <option value="Gestión Comercial" <?php selected($datos_postulante->profesion ?? '', 'Gestión Comercial');?>>Gestión Comercial</option>
                                    <option value="Ingeniería Empresarial" <?php selected($datos_postulante->profesion ?? '', 'Ingeniería Empresarial');?>>Ingeniería Empresarial</option>
                                    <option value="Ingeniería Comercial" <?php selected($datos_postulante->profesion ?? '', 'Ingeniería Comercial');?>>Ingeniería Comercial</option>
                                    <option value="Turismo y Hotelería" <?php selected($datos_postulante->profesion ?? '', 'Turismo y Hotelería');?>>Turismo y Hotelería</option>
                                    <option value="Administración Hotelera" <?php selected($datos_postulante->profesion ?? '', 'Administración Hotelera');?>>Administración Hotelera</option>
                                </optgroup>
                                
                                <optgroup label="">
                                    <option value="Medicina" <?php selected($datos_postulante->profesion ?? '', 'Medicina');?>>Medicina</option>
                                    <option value="Enfermería" <?php selected($datos_postulante->profesion ?? '', 'Enfermería');?>>Enfermería</option>
                                    <option value="Odontología" <?php selected($datos_postulante->profesion ?? '', 'Odontología');?>>Odontología</option>
                                    <option value="Arquitectura" <?php selected($datos_postulante->profesion ?? '', 'Arquitectura');?>>Arquitectura</option>
                                    <option value="Ingeniería Civil" <?php selected($datos_postulante->profesion ?? '', 'Ingeniería Civil');?>>Ingeniería Civil</option>
                                    <option value="Psicología" <?php selected($datos_postulante->profesion ?? '', 'Psicología');?>>Psicología</option>
                                    <option value="Mecánica" <?php selected($datos_postulante->profesion ?? '', 'Mecánica');?>>Mecánica</option>
                                    <option value="Eléctrica" <?php selected($datos_postulante->profesion ?? '', 'Eléctrica');?>>Eléctrica</option>
                                    <option value="Veterinaria" <?php selected($datos_postulante->profesion ?? '', 'Veterinaria');?>>Veterinaria</option>
                                    <option value="Gastronomía" <?php selected($datos_postulante->profesion ?? '', 'Gastronomía');?>>Gastronomía</option>
                                    <option value="Diseño Gráfico" <?php selected($datos_postulante->profesion ?? '', 'Diseño Gráfico');?>>Diseño Gráfico</option>
                                    <option value="Arte / Música / Danza" <?php selected($datos_postulante->profesion ?? '', 'Arte / Música / Danza');?>>Arte / Música / Danza</option>
                                    <option value="Derecho" <?php selected($datos_postulante->profesion ?? '', 'Derecho');?>>Derecho</option>
                                    <option value="Trabajo Social" <?php selected($datos_postulante->profesion ?? '', 'Trabajo Social');?>>Trabajo Social</option>
                                    <option value="Estadística" <?php selected($datos_postulante->profesion ?? '', 'Estadística');?>>Estadística</option>
                                    <option value="Educación" <?php selected($datos_postulante->profesion ?? '', 'Educación');?>>Educación</option>
                                    <option value="Comunicaciones" <?php selected($datos_postulante->profesion ?? '', 'Comunicaciones');?>>Comunicaciones</option>
                                    <option value="Gestión Pública" <?php selected($datos_postulante->profesion ?? '', 'Gestión Pública');?>>Gestión Pública</option>
                                </optgroup>
                            </select>
                        </div>
        
                        <div class="olc-form-row">
                            <div class="olc-form-col">
                                <label>Experiencia *</label>
                                <select name="experiencia_anios" required>
                                    <option value="0" <?php selected($datos_postulante->experiencia_anios ?? '', '0'); ?>>Sin experiencia</option>
                                    <option value="1" <?php selected($datos_postulante->experiencia_anios ?? '', '1'); ?>>Menos de 1 año</option>
                                    <option value="2" <?php selected($datos_postulante->experiencia_anios ?? '', '2'); ?>>Entre 1 y 2 años</option>
                                    <option value="3" <?php selected($datos_postulante->experiencia_anios ?? '', '3'); ?>>De 2 a más años</option>
                                </select>
                            </div>
                            <div class="olc-form-col">
                                <label>¿Maneja moto? *</label>
                                <select name="sabe_moto" required>
                                    <option value="">-- Selecciona --</option>
                                    <option value="tiene_licencia" <?php selected($datos_postulante->sabe_moto ?? '', 'tiene_licencia'); ?>>Sí, tengo licencia</option>
                                    <option value="disponibilidad" <?php selected($datos_postulante->sabe_moto ?? '', 'disponibilidad'); ?>>Disponible para sacar</option>
                                    <option value="no_sabe" <?php selected($datos_postulante->sabe_moto ?? '', 'no_sabe'); ?>>No</option>
                                </select>
                            </div>
                        </div>
        
                        <div style="margin-bottom:15px;">
                            <label>Currículum Vitae (PDF) *</label>
                            <input type="file" name="ccvv" accept="application/pdf" required style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px;">
                            <small style="color:#666;">Archivo PDF, máximo 5MB</small>
                        </div>
        
                        <div style="margin-top:25px; text-align:center;">
                            <button type="submit" class="olc-btn-primary" style="min-width:200px;">
                                📤 Enviar mi CV
                            </button>
                            <button type="button" id="btnCancelarBolsa" class="olc-btn-secondary" style="min-width:150px; margin-left:10px;">
                                Cancelar
                            </button>
                        </div>
                    </form>
                    
                    <div id="bolsa-form-response" style="margin-top:15px;"></div>
                <?php endif; ?>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($){
            // Abrir modal
            $('#btnAbrirBolsaCV').on('click', function(){
                console.log('Botón clickeado - Abriendo modal'); // Debug
                $('#modalBolsaCV').fadeIn();
            });
            
            // Cerrar modal
            $('#cerrarModalBolsa, #btnCancelarBolsa').on('click', function(){
                console.log('Cerrando modal'); // Debug
                $('#modalBolsaCV').fadeOut();
            });
            
            // Submit formulario
            $('#formEnviarBolsaCV').on('submit', function(e){
                e.preventDefault();
                
                console.log('Formulario enviado'); // Debug
                
                var formData = new FormData(this);
                var resp = $('#bolsa-form-response');
                
                resp.html('<p class="olc-loading">⏳ Enviando CV...</p>');
                
                $.ajax({
                    url: olc_ajax.ajax_url,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response){
                        console.log('Respuesta del servidor:', response); // Debug
                        if (response.success) {
                            resp.html('<p class="olc-success">✅ ¡CV enviado correctamente! Te contactaremos pronto.</p>');
                            setTimeout(function(){
                                $('#modalBolsaCV').fadeOut();
                                $('#formEnviarBolsaCV')[0].reset();
                            }, 2000);
                        } else {
                            resp.html('<p class="olc-error">❌ ' + (response.data?.message || 'Error al enviar') + '</p>');
                        }
                    },
                    error: function(xhr, status, error){
                        console.error('Error AJAX:', status, error); // Debug
                        resp.html('<p class="olc-error">❌ Error de conexión</p>');
                    }
                });
            });
        });
        </script>
        
        <?php
        // ========================================
        // AHORA SÍ VERIFICAR RESULTADOS
        // ========================================
        if (empty($rows)) {
            echo '<p class="olc-sin-resultados">No hay ofertas laborales disponibles con los filtros seleccionados.</p>';
            return ob_get_clean();
        }
        
        echo '<div class="olc-resultados-info">';
        echo '<p>Mostrando <strong>' . count($rows) . '</strong> oferta(s)</p>';
        echo '</div>';
        
        echo '<div class="olc-grid-container">';
        foreach ($rows as $r) {
            $detail_page = get_page_by_title('Detalle Oferta');
            $url_detalle = $detail_page ? add_query_arg(array('oferta_id' => $r->id), get_permalink($detail_page)) : '#';

            // ========================================
            // DETERMINAR ESTADO DINÁMICO DE LA OFERTA
            // ========================================
            $hoy = new DateTime();
            $estado_texto = 'Publicada';
            $estado_class = 'olc-status-otro';
            $oferta_vencida = false;
            
            // 1. Verificar si ya tiene un ganador (Finalizada)
            if (!empty($r->ganador_id)) {
                $estado_texto = 'Finalizada';
                $estado_class = 'olc-status-finalizada';
                $oferta_vencida = true;
            } else {
                // 2. Verificar fecha_fin
                if (!empty($r->fecha_fin)) {
                    $fecha_fin = new DateTime($r->fecha_fin);
                    
                    if ($fecha_fin < $hoy) {
                        // Ya pasó la fecha → En evaluación
                        $estado_texto = 'En evaluación';
                        $estado_class = 'olc-status-evaluacion';
                        $oferta_vencida = true;
                    } else {
                        // Dentro de fecha → Activa
                        $estado_texto = 'Activa';
                        $estado_class = 'olc-status-activa';
                        $oferta_vencida = false;
                    }
                } else {
                    // Sin fecha definida, considerar activa si está publicada
                    if ($r->estado === 'publicada') {
                        $estado_texto = 'Activa';
                        $estado_class = 'olc-status-activa';
                        $oferta_vencida = false;
                    } else {
                        $estado_texto = ucfirst($r->estado);
                        $estado_class = 'olc-status-otro';
                    }
                }
            }
            
            // ✅ APLICAR FILTRO POR ESTADO (si está activo)
            if (!empty($filtro_estado)) {
                $mostrar = false;
                
                if ($filtro_estado === 'activa' && $estado_texto === 'Activa') {
                    $mostrar = true;
                } elseif ($filtro_estado === 'evaluacion' && $estado_texto === 'En evaluación') {
                    $mostrar = true;
                } elseif ($filtro_estado === 'finalizada' && $estado_texto === 'Finalizada') {
                    $mostrar = true;
                }
                
                // Si no coincide con el filtro, saltar esta oferta
                if (!$mostrar) {
                    continue;
                }
            }
            
            // Calcular días restantes
            $dias_restantes = '';
            if (!empty($r->fecha_fin) && !$oferta_vencida) {
                $vence = new DateTime($r->fecha_fin);
                $diff = $hoy->diff($vence)->days;
                $dias_restantes = "⏳ {$diff} días restantes";
            } elseif ($oferta_vencida) {
                $dias_restantes = "❌ Oferta cerrada";
            }
            
            $estado_label = '<span class="olc-status-badge ' . $estado_class . '">' . esc_html($estado_texto) . '</span>';

            echo '<div class="olc-card">';
            echo $estado_label;
            echo '<div class="olc-card-body">';
            echo '<h3>' . esc_html($r->titulo) . '</h3>';
            echo '<p class="olc-descripcion">' . wp_trim_words(strip_tags($r->descripcion), 25, '...') . '</p>';
            echo '<p class="olc-info"><strong>📍 ' . esc_html($r->ciudad) . '</strong></p>';
            echo '<p class="olc-sueldo">💰 ' . esc_html($r->sueldo ?: 'A convenir') . '</p>';
            if ($dias_restantes) echo '<p class="olc-tiempo">' . esc_html($dias_restantes) . '</p>';
            echo '</div>';
            echo '<div class="olc-card-footer">';
            echo '<a href="' . esc_url($url_detalle) . '" class="olc-btn-detalle">Ver detalles</a>';
            
            // ✅ Botón "Postular" solo si NO está vencida
            if (!$oferta_vencida) {
                echo '<a href="' . esc_url($url_detalle) . '" class="olc-btn-postular">Postular</a>';
            } else {
                echo '<button class="olc-btn-postular-disabled" disabled>Cerrada</button>';
            }
            
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';

        return ob_get_clean();
    }

    /* -----------------------------
       Shortcode: detalle de oferta + modal postulante
       ----------------------------- */
    public static function shortcode_detail($atts) {
        if (empty($_GET['oferta_id'])) return 'Oferta no definida.';
        $id = intval($_GET['oferta_id']);

        global $wpdb;
        $table = $wpdb->prefix . 'olc_ofertas';
        $r = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d LIMIT 1", $id));
        if (!$r) return 'Oferta no encontrada.';

        // configurable
        $ccvv_valid_days = intval(get_option('olc_ccvv_valid_days', 60));

        ob_start();

        // --- HTML de la oferta (similar a tu versión original) ---
        echo '<div class="olc-oferta-container">';
        echo '<div class="olc-oferta-header">';
        echo '<h1 class="olc-oferta-title">' . esc_html($r->titulo) . '</h1>';
        echo '<div class="olc-oferta-meta">';
        echo '<span class="olc-meta-item">📍 ' . esc_html($r->ciudad) . '</span>';
        echo '<span class="olc-meta-item">💰 ' . esc_html($r->sueldo ?: 'A convenir') . '</span>';
        echo '<span class="olc-meta-item">⏱ ' . esc_html($r->tipo_contrato ?: $r->horario ?: 'Tiempo completo') . '</span>';
        echo '</div>';
        
        
        // ========================================
        // DETERMINAR ESTADO DINÁMICO (IGUAL QUE EN LISTADO)
        // ========================================
        $estado_text = 'Publicada';
        
        if (!empty($r->ganador_id)) {
            $estado_text = 'Finalizada';
        } else {
            if (!empty($r->fecha_fin)) {
                $hoy = new DateTime();
                $fecha_fin = new DateTime($r->fecha_fin);
                
                if ($fecha_fin < $hoy) {
                    $estado_text = 'En evaluación';
                } else {
                    $estado_text = 'Activa';
                }
            } else {
                if ($r->estado === 'publicada') {
                    $estado_text = 'Activa';
                } else {
                    $estado_text = ucfirst($r->estado ?: 'Borrador');
                }
            }
        }
        
        echo '<div class="olc-oferta-status">' . esc_html($estado_text) . '</div>';
        
        
        echo '</div>'; // header

        echo '<div class="olc-oferta-detalle">';
        // main
        echo '<div class="olc-oferta-main">';
        echo '<div class="olc-box olc-box-descripcion"><h3>Descripción</h3><div class="olc-box-content">' . wp_kses_post($r->descripcion) . '</div></div>';
        if (!empty($r->funciones)) echo '<div class="olc-box"><h3>Funciones y Responsabilidades</h3><div class="olc-box-content">' . wp_kses_post($r->funciones) . '</div></div>';
        echo '<div class="olc-box"><h3>Perfil Buscado</h3><div class="olc-box-content">' . wp_kses_post($r->perfil) . '</div></div>';
        if (!empty($r->beneficios)) echo '<div class="olc-box"><h3>Condiciones y Beneficios</h3><div class="olc-box-content">' . wp_kses_post($r->beneficios) . '</div></div>';

        // simplified instruction
        echo '<div id="postular-form" class="olc-box olc-box-postular">';
        echo '<h3>Postular a esta Oferta</h3>';
        echo '<p>Para postular a esta oferta, utiliza el botón <strong>“Confirmar Postulación”</strong> ubicado en el panel derecho.</p>';
        if (!is_user_logged_in()) {
            $login_url = wp_login_url(get_permalink() . '?oferta_id=' . $id);
            echo '<p><a href="' . esc_url($login_url) . '" class="olc-btn-primary">Iniciar sesión para postular</a></p>';
        } else {
            echo '<p class="olc-info-ok">✅ Estás logueado. Puedes continuar con tu postulación desde el panel lateral.</p>';
        }
        echo '</div>'; // postular-form

        echo '</div>'; // .olc-oferta-main

        // sidebar
        echo '<aside class="olc-oferta-sidebar">';
        echo '<div class="olc-sidebar-section"><h4>Estado de la oferta</h4><p><strong>' . esc_html($estado_text) . '</strong></p></div>';

        echo '<div class="olc-sidebar-section">';
        echo '<h4>Tu perfil de postulante</h4>';
        if (!is_user_logged_in()) {
            $login_url = wp_login_url(get_permalink() . '?oferta_id=' . $id);
            echo '<div class="olc-alert olc-alert-warning"><p>🔒 Debes iniciar sesión para postular.</p></div>';
            echo '<p><a href="' . esc_url($login_url) . '" class="olc-btn-primary w-100">Iniciar sesión</a></p>';
        } else {
            $user_id = get_current_user_id();
            $verificacion = self::verificar_datos_postulante($user_id);

            if (!$verificacion['completo']) {
                echo '<div class="olc-alert olc-alert-warning">';
                if ($verificacion['razon'] === 'no_registrado') echo '<p>📝 Aún no has registrado tu perfil de postulante.</p>';
                elseif ($verificacion['razon'] === 'incompleto') echo '<p>⚠️ Tu perfil está incompleto. Completa todos los campos antes de postular.</p>';
                elseif ($verificacion['razon'] === 'cv_vencido') echo '<p>📄 Tu CV ha vencido. Sube una versión actualizada antes de continuar.</p>';
                echo '</div>';
                echo '<p><button id="btnAbrirModalPostulante" class="olc-btn-primary w-100" type="button">Completar o actualizar datos</button></p>';
            } else {
                echo '<div class="olc-alert olc-alert-success"><p>✅ Tu perfil está completo y verificado.</p></div>';

                global $wpdb;
                $tabla_post = $wpdb->prefix . 'olc_postulaciones';
                $user_id = get_current_user_id();
                
                // Verificar si el usuario ya postuló a esta oferta
                $ya_postulo = (bool) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$tabla_post} WHERE user_id=%d AND oferta_id=%d",
                    $user_id,
                    $id
                ));
                
                if ($ya_postulo) {
                    // Botón deshabilitado
                    echo '<p><button class="olc-btn-ya-postulaste w-100" type="button" disabled>
                           Ya postulaste
                         </button></p>';

                } else {
                    // Botón activo para postular
                    echo '<p><button id="btnConfirmarPostulacion" data-oferta-id="' . esc_attr($id) . '" class="olc-btn-primary w-100" type="button">
                            Confirmar Postulación
                          </button></p>';
                }

             // ✅ NUEVO: Botón para editar datos
                $panel_url = get_permalink(get_page_by_path('panel-del-postulante'));
                if ($panel_url) {
                    echo '<p style="margin-top:15px;">
                            <a href="' . esc_url($panel_url) . '" class="olc-btn-editar-datos w-100">
                                Editar mis Datos
                            </a>
                          </p>';
                }

            }
        }
        echo '</div>'; // sidebar-section

        echo '</aside>'; // .olc-oferta-sidebar

        // --- Modal HTML: form sincronizado con tabla olc_postulantes ---
        $user_id = get_current_user_id();
        $datos_postulante = $user_id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}olc_postulantes WHERE user_id=%d LIMIT 1", $user_id)) : null;
        ?>
        <div id="modalPostulante" class="olc-modal" style="display:none;">
          <div class="olc-modal-content">
            <span class="olc-modal-close" id="cerrarModalPostulante">&times;</span>
            <h3>Completa tus datos de postulante</h3>

            <form id="formPostulante" method="post" enctype="multipart/form-data">
              <!-- AJAX action and nonce -->
              <input type="hidden" name="action" value="olc_guardar_postulante">
              <input type="hidden" name="oferta_id" value="<?php echo esc_attr($id); ?>">
              <?php wp_nonce_field('olc_nonce', 'security'); ?>

              <label>Nombre completo</label>
              <input type="text" name="nombre" value="<?php echo esc_attr($datos_postulante->nombre ?? ''); ?>" required>

              <label>DNI</label>
              <input type="text" name="dni" value="<?php echo esc_attr($datos_postulante->dni ?? ''); ?>" required>

              <label>Teléfono</label>
              <input type="text" name="telefono" value="<?php echo esc_attr($datos_postulante->telefono ?? ''); ?>">

              <label>Correo electrónico</label>
              <input type="email" name="email" value="<?php echo esc_attr($datos_postulante->email ?? ''); ?>">

              <label>Fecha de nacimiento</label>
              <input type="date" name="fecha_nacimiento" value="<?php echo esc_attr($datos_postulante->fecha_nacimiento ?? ''); ?>">

              <label>Ciudad</label>
              <input type="text" name="ciudad" value="<?php echo esc_attr($datos_postulante->ciudad ?? $datos_postulante->Ciudad ?? ''); ?>">

              <label>Profesión</label>
                <select name="profesion" required>
                  <option value="">-- Selecciona tu profesión --</option>
                
                  <optgroup label="Profesiones disponibles">
                    <option<?php selected($datos_postulante->profesion ?? '', 'Administración de Empresas');?>>Administración de Empresas</option>
                    <option<?php selected($datos_postulante->profesion ?? '', 'Contabilidad y Finanzas');?>>Contabilidad y Finanzas</option>
                    <option<?php selected($datos_postulante->profesion ?? '', 'Economía');?>>Economía</option>
                    <option<?php selected($datos_postulante->profesion ?? '', 'Administración Bancaria');?>>Administración Bancaria</option>
                    <option<?php selected($datos_postulante->profesion ?? '', 'Banca y Finanzas');?>>Banca y Finanzas</option>
                    <option<?php selected($datos_postulante->profesion ?? '', 'Administración de Negocios Bancarios y Financieros');?>>Administración de Negocios Bancarios y Financieros</option>
                    <option<?php selected($datos_postulante->profesion ?? '', 'Computación e Informática');?>>Computación e Informática</option>
                    <option<?php selected($datos_postulante->profesion ?? '', 'Informática Administrativa');?>>Informática Administrativa</option>
                    <option<?php selected($datos_postulante->profesion ?? '', 'Gestor de Recuperaciones y cobranzas');?>>Gestor de Recuperaciones y cobranzas</option>
                    <option<?php selected($datos_postulante->profesion ?? '', 'Analista de Créditos');?>>Analista de Créditos</option>
                    <option<?php selected($datos_postulante->profesion ?? '', 'Cajero Promotor');?>>Cajero Promotor</option>
                    <option<?php selected($datos_postulante->profesion ?? '', 'Secretariado Ejecutivo');?>>Secretariado Ejecutivo</option>
                  </optgroup>
                
                  <optgroup label="">
                    <option<?php selected($datos_postulante->profesion ?? '', 'Negocios Internacionales');?>>Negocios Internacionales</option>
                    <option<?php selected($datos_postulante->profesion ?? '', 'Marketing');?>>Marketing</option>
                    <option<?php selected($datos_postulante->profesion ?? '', 'Ingeniería Industrial');?>>Ingeniería Industrial</option>
                    <option<?php selected($datos_postulante->profesion ?? '', 'Gestión Comercial');?>>Gestión Comercial</option>
                    <option<?php selected($datos_postulante->profesion ?? '', 'Ingeniería Empresarial');?>>Ingeniería Empresarial</option>
                    <option<?php selected($datos_postulante->profesion ?? '', 'Ingeniería Comercial');?>>Ingeniería Comercial</option>
                    <option<?php selected($datos_postulante->profesion ?? '', 'Turismo y Hotelería');?>>Turismo y Hotelería</option>
                    <option<?php selected($datos_postulante->profesion ?? '', 'Administración Hotelera');?>>Administración Hotelera</option>
                  </optgroup>
                
                  <optgroup label="">
                    <option<?php selected($datos_postulante->profesion ?? '', 'Medicina');?>>Medicina</option>
                    <option<?php selected($datos_postulante->profesion ?? '', 'Enfermería');?>>Enfermería</option>
                    <option<?php selected($datos_postulante->profesion ?? '', 'Odontología');?>>Odontología</option>
                    <option<?php selected($datos_postulante->profesion ?? '', 'Arquitectura');?>>Arquitectura</option>
                    <option<?php selected($datos_postulante->profesion ?? '', 'Ingeniería Civil');?>>Ingeniería Civil</option>
                    <option<?php selected($datos_postulante->profesion ?? '', 'Psicología');?>>Psicología</option>
                    <option<?php selected($datos_postulante->profesion ?? '', 'Mecánica');?>>Mecánica</option>
                    <option<?php selected($datos_postulante->profesion ?? '', 'Eléctrica');?>>Eléctrica</option>
                    <option<?php selected($datos_postulante->profesion ?? '', 'Veterinaria');?>>Veterinaria</option>
                    <option<?php selected($datos_postulante->profesion ?? '', 'Gastronomía');?>>Gastronomía</option>
                    <option<?php selected($datos_postulante->profesion ?? '', 'Diseño Gráfico');?>>Diseño Gráfico</option>
                    <option<?php selected($datos_postulante->profesion ?? '', 'Arte / Música / Danza');?>>Arte / Música / Danza</option>
                    <option<?php selected($datos_postulante->profesion ?? '', 'Derecho');?>>Derecho</option>
                    <option<?php selected($datos_postulante->profesion ?? '', 'Trabajo Social');?>>Trabajo Social</option>
                    <option<?php selected($datos_postulante->profesion ?? '', 'Estadística');?>>Estadística</option>
                    <option<?php selected($datos_postulante->profesion ?? '', 'Educación');?>>Educación</option>
                    <option<?php selected($datos_postulante->profesion ?? '', 'Comunicaciones');?>>Comunicaciones</option>
                    <option<?php selected($datos_postulante->profesion ?? '', 'Gestión Pública');?>>Gestión Pública</option>
                  </optgroup>
                </select>



              <label>Experiencia</label> 
                <select name="experiencia_anios" required> 
                    <option value="0" <?php selected( $datos_postulante->experiencia_anios ?? '', '0'); ?>>Sin experiencia</option> 
                    <option value="1" <?php selected( $datos_postulante->experiencia_anios ?? '', '1'); ?>>Menos de 1 año</option> 
                    <option value="2" <?php selected( $datos_postulante->experiencia_anios ?? '', '2'); ?>>Entre 1 y 2 años</option> 
                    <option value="3" <?php selected( $datos_postulante->experiencia_anios ?? '', '3'); ?>>De 2 a más años</option> 
                </select>


              <label>Disponibilidad</label>
              <input type="text" name="disponibilidad" value="<?php echo esc_attr($datos_postulante->disponibilidad ?? ''); ?>">
              
              <label for="sabe_moto">¿Maneja moto?</label>
                <select name="sabe_moto" id="sabe_moto" required>
                    <option value="">-- Selecciona una opción --</option>
                    <option value="tiene_licencia" <?php selected($datos_postulante->sabe_moto ?? '', 'tiene_licencia'); ?>>Sí, tengo licencia</option>
                    <option value="disponibilidad" <?php selected($datos_postulante->sabe_moto ?? '', 'disponibilidad'); ?>>Tengo disponibilidad para sacar licencia</option>
                    <option value="no_sabe" <?php selected($datos_postulante->sabe_moto ?? '', 'no_sabe'); ?>>No sé manejar moto</option>
                </select>

              <label for="pretension_salarial">Pretensión salarial (S/.)</label>
                <input type="number" name="pretension_salarial" id="pretension_salarial" step="0.01" min="0" placeholder="Si eliges 0 = A convenir" value="<?php echo esc_attr($datos_postulante->pretension_salarial ?? ''); ?>">

              
              <label>Currículum Vitae (PDF)</label>
                  <input type="file" name="ccvv" accept="application/pdf">
                  
                    <?php if (!empty($datos_postulante->ccvv)): ?>
                    <p>📎 CV actual: 
                       <a href="<?php echo esc_url($datos_postulante->ccvv); ?>" target="_blank">Ver archivo</a>
                    </p>
                    <?php endif; ?>



              <div style="margin-top:12px;">
                <button type="submit" class="olc-btn-primary">Guardar datos</button>
                <button id="btnCerrarModal" type="button" class="olc-btn-secondary">Cancelar</button>
              </div>
            </form>

            <div id="olc-form-response" style="margin-top:10px;"></div>
          </div>
        </div>

        <script>
        (function(){
            // modal open/close and AJAX submit
            document.addEventListener('DOMContentLoaded', function(){
                const modal = document.getElementById('modalPostulante');
                const btnOpen = document.getElementById('btnAbrirModalPostulante');
                const btnClose = document.getElementById('cerrarModalPostulante');
                const btnCancel = document.getElementById('btnCerrarModal');

                if (btnOpen) btnOpen.addEventListener('click', function(){ modal.style.display='block'; });
                if (btnClose) btnClose.addEventListener('click', function(){ modal.style.display='none'; });
                if (btnCancel) btnCancel.addEventListener('click', function(){ modal.style.display='none'; });

                window.onclick = function(e){ if (e.target == modal) modal.style.display='none'; };

                // AJAX submission of formPostulante
                var form = document.getElementById('formPostulante');
                if (form) {
                    form.addEventListener('submit', function(e){
                        e.preventDefault();
                        var fd = new FormData(form);
                        var resp = document.getElementById('olc-form-response');
                        resp.innerText = 'Guardando...';

                        fetch(olc_ajax.ajax_url, {
                            method: 'POST',
                            credentials: 'same-origin',
                            body: fd
                        }).then(function(r){ return r.json(); })
                          .then(function(json){
                            if (json.success) {
                                resp.innerText = olc_ajax.msg_saved;
                                setTimeout(function(){
                                    modal.style.display='none';
                                    location.reload();
                                }, 700);
                            } else {
                                resp.innerText = json.data && json.data.message ? json.data.message : 'Error al guardar';
                            }
                          }).catch(function(err){
                            resp.innerText = 'Error de red';
                            console.error(err);
                          });
                    });
                }
            });
        })();
        </script>
        
        <!-- ========================================
        SCRIPT: CONFIRMAR POSTULACIÓN CON MODAL
        ======================================== -->
        
        <script>
        document.addEventListener('DOMContentLoaded', function(){
            const btnPostular = document.getElementById('btnConfirmarPostulacion');
            
            if (btnPostular) {
                btnPostular.addEventListener('click', function(){
                    const ofertaId = this.getAttribute('data-oferta-id');
                    const boton = this;
                    
                    // Deshabilitar botón
                    boton.disabled = true;
                    boton.textContent = 'Procesando...';
                    
                    fetch(olc_ajax.ajax_url, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams({
                            action: 'olc_registrar_postulacion',
                            oferta_id: ofertaId,
                            security: olc_ajax.nonce_action
                        })
                    })
                    .then(r => r.json())
                    .then(json => {
                        if (json.success) {
                            mostrarModalExito();
                        } else {
                            alert('Error: ' + (json.data?.message || 'No se pudo completar la postulación'));
                            boton.disabled = false;
                            boton.textContent = 'Confirmar Postulación';
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        alert('Error de conexión');
                        boton.disabled = false;
                        boton.textContent = 'Confirmar Postulación';
                    });
                });
            }
        });
        
        // ========================================
        // FUNCIÓN: MOSTRAR MODAL DE ÉXITO
        // ========================================
        function mostrarModalExito() {
            const overlay = document.createElement('div');
            overlay.id = 'olc-modal-exito-overlay';
            overlay.innerHTML = `
                <div class="olc-modal-exito-contenido">
                    <div class="olc-modal-exito-icono">✅</div>
                    <h2>¡Gracias por postular!</h2>
                    <p>Hemos recibido tu CV correctamente.</p>
                    <p><strong>En breve nos comunicaremos contigo</strong> para continuar con el proceso de selección.</p>
                    <p class="olc-modal-exito-nota">Mantén tu teléfono disponible y revisa tu correo.</p>
                    <button onclick="location.reload()" class="olc-btn-modal-cerrar">Entendido</button>
                </div>
            `;
            
            document.body.appendChild(overlay);
            
            overlay.addEventListener('click', function(e){
                if (e.target === overlay) {
                    location.reload();
                }
            });
        }
        </script>
        
        <?php

        echo '</div>'; // .olc-oferta-detalle
        echo '</div>'; // .olc-oferta-container

        return ob_get_clean();
    }

    /* -----------------------------
       Shortcode: mis postulaciones
       ----------------------------- */
    public static function shortcode_mis_postulaciones($atts) {
        if (!is_user_logged_in()) return 'Debes iniciar sesión para ver tus postulaciones.';
        global $wpdb;
        $user_id = get_current_user_id();
        $table = $wpdb->prefix . 'olc_postulaciones';
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE user_id=%d ORDER BY fecha_postulacion DESC", $user_id));

        if (empty($rows)) return '<p>No tienes postulaciones.</p>';

        ob_start();
        echo '<table class="widefat"><thead><tr><th>ID</th><th>Oferta</th><th>Estado</th><th>Puntaje</th><th>CV</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            $link = site_url('/?olc_action=view_cv&postulacion_id=' . intval($r->id));
            echo '<tr>';
            echo '<td>' . intval($r->id) . '</td>';
            echo '<td>' . intval($r->oferta_id) . '</td>';
            echo '<td>' . esc_html($r->estado_postulacion) . '</td>';
            echo '<td>' . esc_html($r->puntaje_total) . '</td>';
            echo '<td>' . (!empty($r->ccvv_path) ? '<a href="' . esc_url($link) . '" target="_blank">Ver CV</a>' : 'No') . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        return ob_get_clean();
    }

    /* -----------------------------
       AJAX: guardar postulante (frontend)
       Receives FormData, files allowed
       ----------------------------- */
    public static function olc_guardar_postulante_ajax() {
        global $wpdb;
        $tabla = $wpdb->prefix . 'olc_postulantes';
    
        // Validar usuario logueado
        if (!is_user_logged_in()) {
            wp_send_json_error("Debes iniciar sesión.");
        }
    
        $user_id = get_current_user_id();
        
        
        // ========================================
        // Procesar archivo CCVV (PDF) - Carpeta personalizada
        // ========================================
        $ccvv_url_nuevo = '';
        
        if (!empty($_FILES['ccvv']) && $_FILES['ccvv']['error'] === UPLOAD_ERR_OK) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        
            $file = $_FILES['ccvv'];
        
            // Validar que sea PDF
            $allowed_types = array('application/pdf');
            $file_type = wp_check_filetype($file['name']);
            
            if (!in_array($file['type'], $allowed_types) && $file_type['type'] !== 'application/pdf') {
                wp_send_json_error("Solo se permiten archivos PDF.");
            }
        
            // Validar tamaño (máximo 5MB)
            $max_size = 5 * 1024 * 1024; // 5MB en bytes
            if ($file['size'] > $max_size) {
                wp_send_json_error("El archivo es muy grande. Máximo 5MB.");
            }
        
            // ========================================
            // ✅ Crear carpeta personalizada: /wp-content/uploads/cvs-postulantes/
            // ========================================
            $upload_dir = wp_upload_dir();
            $custom_dir = $upload_dir['basedir'] . '/cvs-postulantes';
            $custom_url = $upload_dir['baseurl'] . '/cvs-postulantes';
        
            // Crear carpeta si no existe
            if (!file_exists($custom_dir)) {
                wp_mkdir_p($custom_dir);
                
                // ✅ Crear archivo .htaccess para proteger la carpeta (opcional)
                $htaccess_content = "Options -Indexes\n";
                $htaccess_content .= "<FilesMatch '\\.pdf$'>\n";
                $htaccess_content .= "  Order Allow,Deny\n";
                $htaccess_content .= "  Allow from all\n";
                $htaccess_content .= "</FilesMatch>";
                
                file_put_contents($custom_dir . '/.htaccess', $htaccess_content);
            }
        
            // ========================================
            // ✅ Generar nombre único para el archivo
            // ========================================
            // Formato: cv_{user_id}_{timestamp}.pdf
            $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $unique_filename = 'cv_' . $user_id . '_' . time() . '.' . $file_extension;
            $destination = $custom_dir . '/' . $unique_filename;
        
            // ========================================
            // Mover archivo a la carpeta personalizada
            // ========================================
            if (move_uploaded_file($file['tmp_name'], $destination)) {
                $ccvv_url_nuevo = $custom_url . '/' . $unique_filename;
                
                // ✅ Eliminar CV anterior si existe (para no acumular archivos)
                if (!empty($ccvv_actual) && strpos($ccvv_actual, '/cvs-postulantes/') !== false) {
                    $old_file_path = str_replace($custom_url, $custom_dir, $ccvv_actual);
                    if (file_exists($old_file_path)) {
                        @unlink($old_file_path); // Eliminar archivo anterior
                    }
                }
            } else {
                wp_send_json_error("Error al guardar el archivo. Intenta de nuevo.");
            }
        }
        
        // ========================================
        // ✅ Obtener CV actual si existe
        // ========================================
        $ccvv_actual = '';
        $postulante_existente = $wpdb->get_row($wpdb->prepare(
            "SELECT ccvv FROM {$tabla} WHERE user_id=%d LIMIT 1", 
            $user_id
        ));
        
        if ($postulante_existente && !empty($postulante_existente->ccvv)) {
            $ccvv_actual = $postulante_existente->ccvv;
        }
        
        // ========================================
        // Determinar qué CV usar
        // ========================================
        // Si se subió uno nuevo → usar el nuevo
        // Si NO se subió uno nuevo → mantener el actual
        $ccvv_final = !empty($ccvv_url_nuevo) ? $ccvv_url_nuevo : $ccvv_actual;
        
        // Sanitizar datos recibidos
        $experiencia_anios = intval($_POST['experiencia_anios'] ?? 0);
        $sabe_moto = isset($_POST['sabe_moto']) ? sanitize_text_field(wp_unslash($_POST['sabe_moto'])) : 'no_sabe';
        
        // Lista de opciones válidas
        $opciones_validas = array('tiene_licencia', 'disponibilidad', 'no_sabe');
        
        if (!in_array($sabe_moto, $opciones_validas, true)) {
            $sabe_moto = 'no_sabe';
        }
        
        $pretension_salarial_raw = $_POST['pretension_salarial'] ?? null;
        
        // Si está vacío o no enviado → guardar NULL
        if ($pretension_salarial_raw === null || $pretension_salarial_raw === '') {
            $pretension_salarial = null;
        } else {
            $pretension_salarial = floatval($pretension_salarial_raw);
        
            // Seguridad: evitar negativos o valores absurdos
            if ($pretension_salarial < 0 || $pretension_salarial > 50000) {
                $pretension_salarial = null;
            }
        }
        
        // ========================================
        // Construir array de datos
        // ========================================
        $data = array(
            'user_id'          => $user_id,
            'nombre'           => sanitize_text_field($_POST['nombre'] ?? ''),
            'dni'              => sanitize_text_field($_POST['dni'] ?? ''),
            'telefono'         => sanitize_text_field($_POST['telefono'] ?? ''),
            'email'            => sanitize_email($_POST['email'] ?? ''),
            'fecha_nacimiento' => sanitize_text_field($_POST['fecha_nacimiento'] ?? ''),
            'ciudad'           => sanitize_text_field($_POST['ciudad'] ?? ''),
            'profesion'        => sanitize_text_field($_POST['profesion'] ?? ''),
            'pretension_salarial' => $pretension_salarial,
            'disponibilidad'   => sanitize_text_field($_POST['disponibilidad'] ?? ''),
            'ccvv'             => $ccvv_final, // ✅ Usar el CV final (nuevo o actual)
            'fecha_registro'   => current_time('mysql'),
            'sabe_moto'        => $sabe_moto,
            'experiencia_anios'=> $experiencia_anios
        );
    
        // Verificar si ya existe registro para este usuario
        $existe = $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM $tabla WHERE user_id = %d", $user_id)
        );
    
        // Tipos para prepare
      
        // Construir formatos automáticamente
            $formatos = [];
            foreach ($data as $key => $value) {
                if (is_int($value)) {
                    $formatos[] = '%d';
                } elseif (is_float($value)) {
                    $formatos[] = '%f';
                } else {
                    $formatos[] = '%s';
                }
            }

    
        if ($existe) {
            // ACTUALIZAR
            $resultado = $wpdb->update(
                $tabla,
                $data,
                ['id' => $existe],
                $formatos,
                ['%d']
            );
        } else {
            // INSERTAR
            $resultado = $wpdb->insert(
                $tabla,
                $data,
                $formatos
            );
        }
        
        // 🔥 INSERTAR AQUÍ LA LLAMADA AL CÁLCULO DE PUNTAJES 🔥
        //self::calcular_y_guardar_puntajes(
        //    $existe ? $existe : $wpdb->insert_id,  // ID del postulante recién insertado
        //    $user_id,                              // user_id del postulante
        //    intval($_POST['oferta_id'] ?? 0)       // oferta_id del formulario
        //);
        
        if ($resultado === false) {
            wp_send_json_error("Error al guardar: " . $wpdb->last_error);
        }
        
        wp_send_json_success("Datos guardados correctamente.");



    }


    /* -----------------------------
       AJAX: registrar postulación (frontend)
       ----------------------------- */
    public static function olc_registrar_postulacion_ajax() {
        error_log('PUBLIC ejecutado');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            wp_send_json_error(['message' => 'Método inválido.']);
        }

        // check nonce if JS uses olc_nonce_action (optional). Here we check security param if present
        if ( isset($_POST['security']) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['security'] ) ), 'olc_nonce_action' ) ) {
            wp_send_json_error( ['message' => 'Nonce inválido.'] );
        }

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'No estás logueado.']);
        }

        global $wpdb;
        $user_id = get_current_user_id();
        $oferta_id = intval($_POST['oferta_id'] ?? 0);
        if (!$oferta_id) wp_send_json_error(['message' => 'Oferta inválida.']);

        $tabla_post = $wpdb->prefix . 'olc_postulaciones';

        // prevent duplicate
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$tabla_post} WHERE user_id=%d AND oferta_id=%d LIMIT 1", $user_id, $oferta_id));
        if ($exists) wp_send_json_error(['message' => __('Ya postulaste a esta oferta.', 'ofertas-laborales-crecer')]);

        // prefer postulante data if exists
        $tabla_postulantes = $wpdb->prefix . 'olc_postulantes';
        $postulante = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tabla_postulantes} WHERE user_id=%d LIMIT 1", $user_id));

        $nombre = $postulante->nombre ?? sanitize_text_field($_POST['nombre'] ?? '');
        $dni = $postulante->dni ?? sanitize_text_field($_POST['dni'] ?? '');
        $telefono = $postulante->telefono ?? sanitize_text_field($_POST['telefono'] ?? '');
        $email = $postulante->email ?? sanitize_email($_POST['email'] ?? '');
        $fecha_nac = !empty($_POST['fecha_nacimiento']) ? sanitize_text_field($_POST['fecha_nacimiento']) : null;
        $ciudad = sanitize_text_field($_POST['ciudad'] ?? '');
        $profesion = sanitize_text_field($_POST['profesion'] ?? '');
        $experiencia = isset($_POST['experiencia_anios']) ? intval($_POST['experiencia_anios']) : 0;
        $disponibilidad = sanitize_text_field($_POST['disponibilidad'] ?? '');
        //$licencia_moto = sanitize_text_field($_POST['licencia_moto'] ?? '');


        // if CV not present in postulante, allow upload (optional)
        $ccvv_path = $postulante->ccvv_path ?? '';
        if (empty($ccvv_path) && !empty($_FILES['olc_ccvv']['name']) && $_FILES['olc_ccvv']['error'] === 0) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            $movefile = wp_handle_upload($_FILES['olc_ccvv'], array('test_form' => false, 'mimes' => array('pdf' => 'application/pdf')));
            if (isset($movefile['error'])) wp_send_json_error(['message' => $movefile['error']]);
            if (!empty($movefile['file'])) {
                require_once(ABSPATH . 'wp-admin/includes/image.php');
                $attachment = array(
                    'post_mime_type' => wp_check_filetype($movefile['file'])['type'] ?? 'application/pdf',
                    'post_title' => sanitize_file_name(basename($movefile['file'])),
                    'post_content' => '',
                    'post_status' => 'inherit'
                );
                $attach_id = wp_insert_attachment($attachment, $movefile['file']);
                if (!is_wp_error($attach_id)) {
                    $attach_data = wp_generate_attachment_metadata($attach_id, $movefile['file']);
                    wp_update_attachment_metadata($attach_id, $attach_data);
                    $ccvv_path = esc_url_raw(wp_get_attachment_url($attach_id));
                }
            }
        }
        
        // Obtener años de experiencia del formulario
        $postulante = $wpdb->get_row(
            $wpdb->prepare("SELECT experiencia_anios FROM {$wpdb->prefix}olc_postulantes WHERE user_id=%d LIMIT 1", $user_id)
        );
        
        $experiencia_anios = intval($postulante->experiencia_anios ?? 0);


        $insert = array(
            'oferta_id' => $oferta_id,
            'user_id' => $user_id,
            'nombre' => $nombre,
            'dni' => $dni,
            'telefono' => $telefono,
            'email' => $email,
            'fecha_nacimiento' => $fecha_nac,
            'ciudad' => $ciudad,
            'profesion' => $profesion,
            'experiencia_anios' => $experiencia_anios,
            'disponibilidad' => $disponibilidad,
            //'licencia_moto' => $licencia_moto,
            'ccvv_path' => $ccvv_path,
            'fecha_ccvv_subida' => current_time('mysql'),
            'fecha_postulacion' => current_time('mysql'),
            'estado_postulacion' => 'Enviado'
        );

        $res = $wpdb->insert($tabla_post, $insert);
        if ($res === false) wp_send_json_error(['message' => 'Error al registrar la postulación.']);

        $postulacion_id = $wpdb->insert_id;

        self::calcular_y_guardar_puntajes(
            $postulacion_id,
            $user_id, 
            $oferta_id
        );
        
        
        wp_send_json_success(['message' => __('Postulación registrada correctamente.', 'ofertas-laborales-crecer')]);
    }

    /* -----------------------------
       Backward-compatible POST handler: handle_requests()
       Allows form POST when JS disabled (keeps original behavior)
       ----------------------------- */
    public static function handle_requests() {
        // handle old non-AJAX POST save (form field olc_action=guardar_postulante)
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['olc_action']) && $_POST['olc_action'] === 'guardar_postulante') {
            if (!is_user_logged_in()) wp_die('Debes iniciar sesión para registrar tus datos.');
            if (!isset($_POST['olc_nonce']) || !wp_verify_nonce($_POST['olc_nonce'],'olc_guardar_postulante')) wp_die('Nonce inválido.');

            global $wpdb;
            $user_id = get_current_user_id();
            $tabla = $wpdb->prefix . 'olc_postulantes';

            $data = array(
                'user_id' => $user_id,
                'nombre' => sanitize_text_field($_POST['nombre']),
                'dni' => sanitize_text_field($_POST['dni']),
                'telefono' => sanitize_text_field($_POST['telefono']),
                'email' => sanitize_email($_POST['email']),
                'profesion' => sanitize_text_field($_POST['profesion']),
                'fecha_ccvv_subida' => current_time('mysql'),
            );

            // Subida del CV (simple fallback)
            if (!empty($_FILES['ccvv']['name']) && $_FILES['ccvv']['error'] == 0) {
                $file = $_FILES['ccvv'];
                if ($file['type'] !== 'application/pdf') wp_die('Solo se permiten archivos PDF.');
                $upload = wp_upload_dir();
                $dir = trailingslashit($upload['basedir']) . 'cv/';
                if (!file_exists($dir)) wp_mkdir_p($dir);
                $name = sanitize_file_name(time() . '_' . $file['name']);
                $dest = $dir . $name;
                if (!move_uploaded_file($file['tmp_name'], $dest)) wp_die('No se pudo guardar el CV.');
                $data['ccvv_path'] = str_replace(ABSPATH, '/', $dest);
            }

            $existe = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$tabla} WHERE user_id=%d LIMIT 1", $user_id));
            if ($existe) {
                $wpdb->update($tabla, $data, array('user_id'=>$user_id));
            } else {
                $wpdb->insert($tabla, $data);
            }

            wp_safe_redirect(add_query_arg(array('oferta_id'=>intval($_GET['oferta_id'] ?? 0),'olc_msg'=>'perfil_guardado'), get_permalink()));
            exit;
        }

        // view CV (already-existing behavior)
        if (isset($_GET['olc_action']) && $_GET['olc_action'] === 'view_cv' && !empty($_GET['postulacion_id'])) {
            $pid = intval($_GET['postulacion_id']);
            global $wpdb;
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}olc_postulaciones WHERE id=%d LIMIT 1", $pid));
            if (!$row) wp_die('No encontrada.');
            $path = ABSPATH . ltrim($row->ccvv_path, '/');
            if (!current_user_can('manage_options')) {
                $user_id = get_current_user_id();
                if ($user_id !== intval($row->user_id)) wp_die('No permitido.');
            }
            if (!file_exists($path)) wp_die('Archivo no existe.');
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . basename($path) . '"');
            header('Content-Length: ' . filesize($path));
            readfile($path);
            exit;
        }
    }

    /* -----------------------------
       Verificar datos del postulante (auxiliar)
       ----------------------------- */
    public static function verificar_datos_postulante($user_id) {
        
        
        global $wpdb;
        $tabla_postulantes = $wpdb->prefix . 'olc_postulantes';
        $datos = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tabla_postulantes} WHERE user_id=%d LIMIT 1", $user_id));
        error_log(print_r($datos, true));
    
        if (!$datos) return array('completo' => false, 'razon' => 'no_registrado');
    
        // Campos requeridos mínimos (ajustado según tabla real)
        $requeridos = ['nombre', 'dni', 'telefono', 'email', 'profesion', 'ccvv'];
    
        foreach ($requeridos as $campo) {
            if (empty($datos->$campo)) {
                return array('completo' => false, 'razon' => 'incompleto');
            }
        }
    
        // Validar vencimiento del CV si existe fecha_ccvv_subida
        if (!empty($datos->fecha_ccvv_subida)) {
            $fecha_subida = strtotime($datos->fecha_ccvv_subida);
            $limite = strtotime('-' . intval(get_option('olc_ccvv_valid_days', 60)) . ' days');
            if ($fecha_subida < $limite) {
                return array('completo' => false, 'razon' => 'cv_vencido');
            }
        }
    
        return array('completo' => true, 'razon' => 'ok');
    }
    
    
    
    /* Helper: clasificar profesión en grupo */
    
    private static function _profesion_grupo($profesion) {
        $profesion = trim($profesion);
        $aptas = array_map('trim', [
            'Administración de Empresas','Contabilidad y Finanzas','Economía','Administración Bancaria','Banca y Finanzas',
            'Administración de Negocios Bancarios y Financieros','Computación e Informática','Informática Administrativa',
            'Gestor de Recuperaciones y cobranzas','Analista de Créditos','Cajero Promotor','Secretariado Ejecutivo'
        ]);
        $inter = array_map('trim', [
            'Negocios Internacionales','Marketing','Ingeniería Industrial','Gestión Comercial','Ingeniería Empresarial',
            'Ingeniería Comercial','Turismo y Hotelería','Administración Hotelera'
        ]);
        if (in_array($profesion, $aptas, true)) return 'apta';
        if (in_array($profesion, $inter, true)) return 'intermedia';
        return 'no_apta';
    }
    
    /* Main: calcular y guardar puntajes (postulacion_id puede ser null si aún no hay; usaremos postulacion_id si existe) */
    public static function calcular_y_guardar_puntajes($postulacion_id = 0, $user_id = 0, $oferta_id = 0) {
        global $wpdb;
        // obtener datos postulante
        if ($postulacion_id) {

            error_log("OLC_DEBUG - post ID recibido: " . $post_id);

            $post = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}olc_postulaciones WHERE id = %d LIMIT 1", $postulacion_id));
            
            error_log("OLC_DEBUG - fila postulación completa: " . print_r($post, true));


            if (!$post) return false;
            $user_id = $post->user_id;
            // intento leer datos desde la propia postulación (si allí están)
            $postulante_row = $post;
        }
        if (!$user_id) return false;
    
        $tbl_postulantes = $wpdb->prefix . 'olc_postulantes';
        $postulante = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tbl_postulantes} WHERE user_id=%d LIMIT 1", $user_id));
        if (!$postulante) return false;
    
        // obtener oferta si tenemos id
        $oferta = null;
        if ($oferta_id) {
            $oferta = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}olc_ofertas WHERE id=%d LIMIT 1", $oferta_id));
        } elseif (!empty($postulacion_row->oferta_id)) {
            $oferta = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}olc_ofertas WHERE id=%d LIMIT 1", intval($postulacion_row->oferta_id)));
        }
    
        // ----------------- CÁLCULO -----------------
        $scores = [];
        // 1) Edad
        $age_score = 0;
        if (!empty($postulante->fecha_nacimiento) && $ts = strtotime($postulante->fecha_nacimiento)) {
            $age = floor((time() - $ts) / (365*24*60*60));
            if ($age >= 18 && $age <= 27) $age_score = 20;
            elseif ($age >= 28 && $age <= 32) $age_score = 10;
            elseif ($age >= 33 && $age <= 80) $age_score = 0;
            else $age_score = 0;
        }
        $scores['edad'] = $age_score;
    
        // 2) Estado bancario -> valor por defecto 0 (se agregará manualmente en etapa 1 por admin)
        $estado_bancario_score = 0; // si admin lo asigna, se insertará desde admin
        $scores['estado_bancario'] = $estado_bancario_score;
    
        // 3) Profesion match
        $prof_post = trim($postulante->profesion ?? '');
        $prof_oferta = trim($oferta->profesion_requerida ?? '');
        $prof_score = 0;
        if (!empty($prof_post) && !empty($prof_oferta)) {
            if (strcasecmp($prof_post, $prof_oferta) === 0) {
                $prof_score = 20;
            } else {
                // comparar por grupo
                $g_post = self::_profesion_grupo($prof_post);
                $g_of = self::_profesion_grupo($prof_oferta);
                if ($g_post === $g_of && $g_post === 'apta') $prof_score = 20;
                elseif ($g_post === $g_of && $g_post === 'intermedia') $prof_score = 10;
                else $prof_score = 0;
            }
        }
        $scores['profesion_match'] = $prof_score;
    
        
        // ======================================================
        // 4) EXPERIENCIA (LEER DESDE wp_olc_postulaciones)
        // ======================================================

        
        $exp_val = null;
        
        // Asegurar lectura de experiencia_anios desde la postulación
        if (isset($post->experiencia_anios)) {
            $exp_raw = trim((string)$post->experiencia_anios);
        
            if ($exp_raw === 'other' || strtolower($exp_raw) === 'otra') {
                $exp_val = 4;
            } elseif (is_numeric($exp_raw)) {
                $exp_val = intval($exp_raw);
            } else {
                $exp_val = 0;
            }
        } else {
            $exp_val = 0;
        }
        
        // Asignar puntajes correctos
        switch ($exp_val) {
            case 0:
                $exp_score = 0;
                break;
            case 1:
                $exp_score = 10;
                break;
            case 2:
            case 3:
            case 4:
            default:
                $exp_score = 20;
                break;
        }
        
        $scores['experiencia'] = $exp_score;


        
    
        // ----------------- GUARDAR PUNTUACIONES EN wp_olc_puntuaciones -----------------
        $puntuaciones_table = $wpdb->prefix . 'olc_puntuaciones';
        // eliminar puntuaciones previas de esta postulacion (para recalculo, si existe postulacion_id)
        if ($postulacion_id) {
            $wpdb->delete($puntuaciones_table, array('postulacion_id' => $postulacion_id));
        }
        // Si no hay postulacion_id, intentaremos obtener una si ya hay en tabla postulaciones (oferta+user)
        if (!$postulacion_id && $oferta_id) {
            $postulacion_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}olc_postulaciones WHERE oferta_id=%d AND user_id=%d LIMIT 1", $oferta_id, $user_id));
        }
    
        foreach ($scores as $criterio => $valor) {
            $wpdb->insert($puntuaciones_table, array(
                'postulacion_id' => $postulacion_id ?: 0,
                'criterio' => $criterio,
                'valor' => intval($valor),
                'comentario' => 'cálculo automático: ' . $criterio,
                'creado_por' => get_current_user_id(),
                'fecha' => current_time('mysql')
            ));
        }
    
        // actualizar puntaje_total en la tabla olc_postulaciones si existe
        $total = array_sum($scores);
        if ($postulacion_id) {
            $wpdb->update($wpdb->prefix . 'olc_postulaciones', array('puntaje_total' => $total), array('id' => $postulacion_id));
        }
    
        return $scores;
    }


    // HELPER PARA CALCULAR MATCH DE PROFESIÓN

    public static function calcular_puntaje_profesion_match($prof_postulante, $prof_requerida) {
        $p = trim(mb_strtolower((string)$prof_postulante));
        $r = trim(mb_strtolower((string)$prof_requerida));
    
        // normalización simple (quita acentos básicos)
        $normalize = function($s){
            $map = ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n'];
            return strtr($s, $map);
        };
        $p = $normalize($p);
        $r = $normalize($r);
    
        // listas (normalizadas)
        $aptas = array_map($normalize, [
            "Administración de Empresas","Contabilidad y Finanzas","Economía","Administración Bancaria",
            "Banca y Finanzas","Administración de Negocios Bancarios y Financieros","Computación e Informática",
            "Informática Administrativa","Gestor de Recuperaciones y cobranzas","Analista de Créditos",
            "Cajero Promotor","Secretariado Ejecutivo"
        ]);
        $intermedias = array_map($normalize, [
            "Negocios Internacionales","Marketing","Ingeniería Industrial","Gestión Comercial",
            "Ingeniería Empresarial","Ingeniería Comercial","Turismo y Hotelería","Administración Hotelera"
        ]);
        $no_aptas = array_map($normalize, [
            "Medicina","Enfermería","Odontología","Arquitectura","Ingeniería Civil","Psicología",
            "Mecánica","Eléctrica","Veterinaria","Gastronomía","Diseño Gráfico","Arte / Música / Danza",
            "Derecho","Trabajo Social","Estadística","Educación","Comunicaciones","Gestión Pública"
        ]);
    
        // match exacto
        if ($p === $r && $p !== '') return 20;
    
        // ambos en misma categoría apta -> 20
        if (in_array($p, $aptas) && in_array($r, $aptas)) return 20;
        if (in_array($p, $intermedias) && in_array($r, $intermedias)) return 10;
        if (in_array($p, $no_aptas) && in_array($r, $no_aptas)) return 0;
    
        // si postulante en aptas y requerida en intermedias -> 20 (relacionado)
        if (in_array($p, $aptas) && in_array($r, $intermedias)) return 20;
        if (in_array($p, $intermedias) && in_array($r, $aptas)) return 10;
    
        // si no coincide, 0
        return 0;
    }

    /* -----------------------------
       Redirigir postulantes después del login
       ----------------------------- */
    public static function redirect_postulante_after_login($redirect_to, $request, $user) {
        // Si el usuario tiene el rol "postulante"
        if (isset($user->roles) && is_array($user->roles)) {
            if (in_array('postulante', $user->roles)) {
                // Obtener la página de ofertas laborales
                $ofertas_page = get_page_by_path('ofertas-laborales'); // Ajusta el slug de tu página
                
                if ($ofertas_page) {
                    return get_permalink($ofertas_page->ID);
                } else {
                    // Fallback: buscar por título
                    $ofertas_page = get_page_by_title('Ofertas Laborales');
                    if ($ofertas_page) {
                        return get_permalink($ofertas_page->ID);
                    }
                }
                
                // Si no encuentra la página, ir al home
                return home_url('/');
            }
        }
        
        // Para otros roles, comportamiento normal
        return $redirect_to;
    }
    
    /* -----------------------------
       Bloquear acceso al admin para postulantes
       ----------------------------- */
    public static function bloquear_admin_para_postulantes() {
        // Solo aplicar en el admin (no en AJAX)
        if (!defined('DOING_AJAX') || !DOING_AJAX) {
            $user = wp_get_current_user();
            
            // Si es postulante y está intentando acceder al admin
            if (in_array('postulante', (array) $user->roles)) {
                // Redirigir a ofertas laborales
                $ofertas_page = get_page_by_path('ofertas-laborales');
                
                if ($ofertas_page) {
                    wp_redirect(get_permalink($ofertas_page->ID));
                } else {
                    wp_redirect(home_url('/'));
                }
                exit;
            }
        }
    }
    
    /* -----------------------------
       Redirigir después del registro
       ----------------------------- */
    public static function redirect_postulante_after_register($redirect_to) {
        // Después de registrarse, ir a ofertas laborales
        $ofertas_page = get_page_by_path('ofertas-laborales');
        
        if ($ofertas_page) {
            return get_permalink($ofertas_page->ID);
        }
        
        return home_url('/');
    }

    /* -----------------------------
       Shortcode: Panel del postulante (VERSIÓN MEJORADA)
       ----------------------------- */
    public static function shortcode_panel_postulante($atts) {
        if (!is_user_logged_in()) {
            return '<div class="olc-panel-login-required">
                        <h2>🔒 Acceso Restringido</h2>
                        <p>Debes iniciar sesión para ver tu perfil.</p>
                        <a href="' . wp_login_url(get_permalink()) . '" class="olc-btn-primary">Iniciar Sesión</a>
                    </div>';
        }
        
        $user_id = get_current_user_id();
        $user = wp_get_current_user();
        
        // Verificar que sea postulante
        if (!in_array('postulante', (array) $user->roles)) {
            return '<p>Esta sección es solo para postulantes.</p>';
        }
        
        global $wpdb;
        $tabla_postulantes = $wpdb->prefix . 'olc_postulantes';
        $datos = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$tabla_postulantes} WHERE user_id=%d LIMIT 1", 
            $user_id
        ));
        
        ob_start();
        ?>
        
        <div class="olc-panel-postulante">
            
            <!-- Header del panel -->
            <div class="olc-panel-header">
                <div class="olc-header-content">
                    <div class="olc-avatar">
                        <?php echo get_avatar($user_id, 80); ?>
                    </div>
                    <div class="olc-header-text">
                        <h1>Hola, <?php echo esc_html($user->display_name); ?> 👋</h1>
                        <p class="olc-subtitle">Gestiona tu perfil y postulaciones</p>
                    </div>
                </div>
                <a href="<?php echo wp_logout_url(home_url()); ?>" class="olc-btn-logout-header">
                    🚪 Cerrar sesión
                </a>
            </div>
            
            <!-- Grid de contenido -->
            <div class="olc-panel-grid">
                
                <!-- Columna izquierda: Información -->
                <div class="olc-panel-col-izq">
                    
                    <!-- Card: Información Personal -->
                    <div class="olc-card">
                        <div class="olc-card-header">
                            <h3>📋 Información Personal</h3>
                            <button class="olc-btn-icon" id="btnEditarInfo" title="Editar información">
                                ✏️
                            </button>
                        </div>
                        
                        <?php if ($datos): ?>
                            <div class="olc-info-list">
                                <div class="olc-info-item">
                                    <span class="olc-info-label">Nombre completo:</span>
                                    <span class="olc-info-value"><?php echo esc_html($datos->nombre); ?></span>
                                </div>
                                <div class="olc-info-item">
                                    <span class="olc-info-label">DNI:</span>
                                    <span class="olc-info-value"><?php echo esc_html($datos->dni); ?></span>
                                </div>
                                <div class="olc-info-item">
                                    <span class="olc-info-label">Teléfono:</span>
                                    <span class="olc-info-value"><?php echo esc_html($datos->telefono); ?></span>
                                </div>
                                <div class="olc-info-item">
                                    <span class="olc-info-label">Email:</span>
                                    <span class="olc-info-value"><?php echo esc_html($datos->email); ?></span>
                                </div>
                                <div class="olc-info-item">
                                    <span class="olc-info-label">Fecha de nacimiento:</span>
                                    <span class="olc-info-value">
                                        <?php echo !empty($datos->fecha_nacimiento) ? date('d/m/Y', strtotime($datos->fecha_nacimiento)) : '-'; ?>
                                    </span>
                                </div>
                                <div class="olc-info-item">
                                    <span class="olc-info-label">Ciudad:</span>
                                    <span class="olc-info-value"><?php echo esc_html($datos->ciudad); ?></span>
                                </div>
                                <div class="olc-info-item">
                                    <span class="olc-info-label">Profesión:</span>
                                    <span class="olc-info-value"><?php echo esc_html($datos->profesion); ?></span>
                                </div>
                                <div class="olc-info-item">
                                    <span class="olc-info-label">Experiencia:</span>
                                    <span class="olc-info-value">
                                        <?php 
                                        $exp = intval($datos->experiencia_anios ?? 0);
                                        $exp_text = array(
                                            0 => 'Sin experiencia',
                                            1 => 'Menos de 1 año',
                                            2 => 'Entre 1 y 2 años',
                                            3 => 'De 2 a más años'
                                        );
                                        echo $exp_text[$exp] ?? '-';
                                        ?>
                                    </span>
                                </div>
                                <div class="olc-info-item">
                                    <span class="olc-info-label">¿Maneja moto?:</span>
                                    <span class="olc-info-value">
                                        <?php 
                                        $moto = $datos->sabe_moto ?? 'no_sabe';
                                        $moto_text = array(
                                            'tiene_licencia' => 'Sí, con licencia',
                                            'disponibilidad' => 'Disponible para sacar',
                                            'no_sabe' => 'No'
                                        );
                                        echo $moto_text[$moto] ?? '-';
                                        ?>
                                    </span>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="olc-alerta-info">
                                <p>⚠️ Aún no has completado tu perfil.</p>
                                <button class="olc-btn-primary" id="btnCompletarPerfil">Completar Perfil</button>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                </div>
                
                <!-- Columna derecha: CV y Acciones -->
                <div class="olc-panel-col-der">
                    
                    <!-- Card: CV -->
                    <div class="olc-card olc-card-cv">
                        <div class="olc-card-header">
                            <h3>📄 Currículum Vitae</h3>
                        </div>
                        
                        <?php if (!empty($datos->ccvv)): ?>
                            <div class="olc-cv-status">
                                <div class="olc-cv-icon">✅</div>
                                <div>
                                    <p class="olc-cv-estado">CV Cargado</p>
                                    <p class="olc-cv-fecha">
                                        Actualizado: <?php echo date('d/m/Y', strtotime($datos->fecha_registro)); ?>
                                    </p>
                                </div>
                            </div>
                            
                            <a href="<?php echo esc_url($datos->ccvv); ?>" target="_blank" class="olc-btn-ver-cv">
                                📥 Descargar mi CV
                            </a>
                        <?php else: ?>
                            <div class="olc-cv-status olc-cv-faltante">
                                <div class="olc-cv-icon">⚠️</div>
                                <div>
                                    <p class="olc-cv-estado">Sin CV</p>
                                    <p class="olc-cv-fecha">Necesitas subir tu CV para postular</p>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Botón para actualizar CV -->
                        <button class="olc-btn-actualizar-cv" id="btnActualizarCV">
                            🔄 <?php echo !empty($datos->ccvv) ? 'Actualizar CV' : 'Subir CV'; ?>
                        </button>
                        
                        <!-- Form oculto para subir CV -->
                        <form id="formActualizarCV" style="display:none;" method="post" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="olc_actualizar_cv">
                            <?php wp_nonce_field('olc_actualizar_cv', 'cv_nonce'); ?>
                            <input type="file" name="nuevo_cv" id="nuevo_cv" accept="application/pdf">
                        </form>
                        
                        <div id="cv-response"></div>
                    </div>
                    
                    <!-- Card: Acciones rápidas -->
                    <div class="olc-card olc-card-acciones">
                        <h3>⚡ Acciones Rápidas</h3>
                        
                        <a href="<?php echo get_page_link(get_page_by_path('ofertas-laborales')); ?>" class="olc-btn-accion">
                            🔍 Ver Ofertas Disponibles
                        </a>
                        
                        <button class="olc-btn-accion" id="btnEditarPerfilCompleto">
                            ✏️ Editar Todo mi Perfil
                        </button>
                    </div>
                    
                </div>
                
            </div>
        </div>
        
        <!-- Modal para editar perfil (reutilizando el existente) -->
        <?php echo self::render_modal_editar_perfil($datos); ?>
        
        <script>
        jQuery(document).ready(function($){
            
            // Abrir modal al hacer clic en editar
            $('#btnEditarInfo, #btnEditarPerfilCompleto, #btnCompletarPerfil').on('click', function(){
                $('#modalEditarPerfil').fadeIn();
            });
            
            // Cerrar modal
            $('#cerrarModalEditar, #btnCancelarEditar').on('click', function(){
                $('#modalEditarPerfil').fadeOut();
            });
            
            // Click en botón Actualizar CV
            $('#btnActualizarCV').on('click', function(){
                $('#nuevo_cv').click();
            });
            
            // Al seleccionar archivo, subir automáticamente
            $('#nuevo_cv').on('change', function(){
                if (this.files.length > 0) {
                    var formData = new FormData($('#formActualizarCV')[0]);
                    var responseDiv = $('#cv-response');
                    
                    responseDiv.html('<p class="olc-loading">⏳ Subiendo CV...</p>');
                    
                    $.ajax({
                        url: olc_ajax.ajax_url,
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function(response){
                            if (response.success) {
                                responseDiv.html('<p class="olc-success">✅ CV actualizado correctamente</p>');
                                setTimeout(function(){
                                    location.reload();
                                }, 1500);
                            } else {
                                responseDiv.html('<p class="olc-error">❌ ' + (response.data?.message || 'Error al subir CV') + '</p>');
                            }
                        },
                        error: function(){
                            responseDiv.html('<p class="olc-error">❌ Error de conexión</p>');
                        }
                    });
                }
            });
            
            // Submit del formulario de editar perfil
            $('#formEditarPerfil').on('submit', function(e){
                e.preventDefault();
                var formData = new FormData(this);
                var resp = $('#edit-form-response');
                
                resp.html('<p class="olc-loading">Guardando...</p>');
                
                $.ajax({
                    url: olc_ajax.ajax_url,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response){
                        if (response.success) {
                            resp.html('<p class="olc-success">✅ Datos actualizados</p>');
                            setTimeout(function(){
                                location.reload();
                            }, 1000);
                        } else {
                            resp.html('<p class="olc-error">❌ ' + (response.data || 'Error') + '</p>');
                        }
                    },
                    error: function(){
                        resp.html('<p class="olc-error">❌ Error de conexión</p>');
                    }
                });
            });
        });
        </script>
        
        <?php
        return ob_get_clean();
    }
    
    /* -----------------------------
       Render modal para editar perfil COMPLETO
       ----------------------------- */
    private static function render_modal_editar_perfil($datos) {
        ob_start();
        ?>
        <div id="modalEditarPerfil" class="olc-modal" style="display:none;">
            <div class="olc-modal-content olc-modal-grande">
                <span class="olc-modal-close" id="cerrarModalEditar">&times;</span>
                <h2>✏️ Editar mi Perfil</h2>
                
                <form id="formEditarPerfil" method="post" enctype="multipart/form-data">
                    <!-- AJAX action and nonce -->
                    <input type="hidden" name="action" value="olc_guardar_postulante">
                    <?php wp_nonce_field('olc_nonce', 'security'); ?>
    
                    <div class="olc-form-row">
                        <div class="olc-form-col">
                            <label>Nombre completo *</label>
                            <input type="text" name="nombre" value="<?php echo esc_attr($datos->nombre ?? ''); ?>" required>
                        </div>
                        <div class="olc-form-col">
                            <label>DNI *</label>
                            <input type="text" name="dni" value="<?php echo esc_attr($datos->dni ?? ''); ?>" required>
                        </div>
                    </div>
    
                    <div class="olc-form-row">
                        <div class="olc-form-col">
                            <label>Teléfono</label>
                            <input type="text" name="telefono" value="<?php echo esc_attr($datos->telefono ?? ''); ?>">
                        </div>
                        <div class="olc-form-col">
                            <label>Correo electrónico</label>
                            <input type="email" name="email" value="<?php echo esc_attr($datos->email ?? ''); ?>">
                        </div>
                    </div>
    
                    <div class="olc-form-row">
                        <div class="olc-form-col">
                            <label>Fecha de nacimiento</label>
                            <input type="date" name="fecha_nacimiento" value="<?php echo esc_attr($datos->fecha_nacimiento ?? ''); ?>">
                        </div>
                        <div class="olc-form-col">
                            <label>Ciudad</label>
                            <input type="text" name="ciudad" value="<?php echo esc_attr($datos->ciudad ?? $datos->Ciudad ?? ''); ?>">
                        </div>
                    </div>
    
                    <div style="margin-bottom:15px;">
                        <label>Profesión *</label>
                        <select name="profesion" required style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px;">
                            <option value="">-- Selecciona tu profesión --</option>
                            
                            <optgroup label="Profesiones disponibles">
                                <option value="Administración de Empresas" <?php selected($datos->profesion ?? '', 'Administración de Empresas');?>>Administración de Empresas</option>
                                <option value="Contabilidad y Finanzas" <?php selected($datos->profesion ?? '', 'Contabilidad y Finanzas');?>>Contabilidad y Finanzas</option>
                                <option value="Economía" <?php selected($datos->profesion ?? '', 'Economía');?>>Economía</option>
                                <option value="Administración Bancaria" <?php selected($datos->profesion ?? '', 'Administración Bancaria');?>>Administración Bancaria</option>
                                <option value="Banca y Finanzas" <?php selected($datos->profesion ?? '', 'Banca y Finanzas');?>>Banca y Finanzas</option>
                                <option value="Administración de Negocios Bancarios y Financieros" <?php selected($datos->profesion ?? '', 'Administración de Negocios Bancarios y Financieros');?>>Administración de Negocios Bancarios y Financieros</option>
                                <option value="Computación e Informática" <?php selected($datos->profesion ?? '', 'Computación e Informática');?>>Computación e Informática</option>
                                <option value="Informática Administrativa" <?php selected($datos->profesion ?? '', 'Informática Administrativa');?>>Informática Administrativa</option>
                                <option value="Gestor de Recuperaciones y cobranzas" <?php selected($datos->profesion ?? '', 'Gestor de Recuperaciones y cobranzas');?>>Gestor de Recuperaciones y cobranzas</option>
                                <option value="Analista de Créditos" <?php selected($datos->profesion ?? '', 'Analista de Créditos');?>>Analista de Créditos</option>
                                <option value="Cajero Promotor" <?php selected($datos->profesion ?? '', 'Cajero Promotor');?>>Cajero Promotor</option>
                                <option value="Secretariado Ejecutivo" <?php selected($datos->profesion ?? '', 'Secretariado Ejecutivo');?>>Secretariado Ejecutivo</option>
                            </optgroup>
                            
                            <optgroup label="">
                                <option value="Negocios Internacionales" <?php selected($datos->profesion ?? '', 'Negocios Internacionales');?>>Negocios Internacionales</option>
                                <option value="Marketing" <?php selected($datos->profesion ?? '', 'Marketing');?>>Marketing</option>
                                <option value="Ingeniería Industrial" <?php selected($datos->profesion ?? '', 'Ingeniería Industrial');?>>Ingeniería Industrial</option>
                                <option value="Gestión Comercial" <?php selected($datos->profesion ?? '', 'Gestión Comercial');?>>Gestión Comercial</option>
                                <option value="Ingeniería Empresarial" <?php selected($datos->profesion ?? '', 'Ingeniería Empresarial');?>>Ingeniería Empresarial</option>
                                <option value="Ingeniería Comercial" <?php selected($datos->profesion ?? '', 'Ingeniería Comercial');?>>Ingeniería Comercial</option>
                                <option value="Turismo y Hotelería" <?php selected($datos->profesion ?? '', 'Turismo y Hotelería');?>>Turismo y Hotelería</option>
                                <option value="Administración Hotelera" <?php selected($datos->profesion ?? '', 'Administración Hotelera');?>>Administración Hotelera</option>
                            </optgroup>
                            
                            <optgroup label="">
                                <option value="Medicina" <?php selected($datos->profesion ?? '', 'Medicina');?>>Medicina</option>
                                <option value="Enfermería" <?php selected($datos->profesion ?? '', 'Enfermería');?>>Enfermería</option>
                                <option value="Odontología" <?php selected($datos->profesion ?? '', 'Odontología');?>>Odontología</option>
                                <option value="Arquitectura" <?php selected($datos->profesion ?? '', 'Arquitectura');?>>Arquitectura</option>
                                <option value="Ingeniería Civil" <?php selected($datos->profesion ?? '', 'Ingeniería Civil');?>>Ingeniería Civil</option>
                                <option value="Psicología" <?php selected($datos->profesion ?? '', 'Psicología');?>>Psicología</option>
                                <option value="Mecánica" <?php selected($datos->profesion ?? '', 'Mecánica');?>>Mecánica</option>
                                <option value="Eléctrica" <?php selected($datos->profesion ?? '', 'Eléctrica');?>>Eléctrica</option>
                                <option value="Veterinaria" <?php selected($datos->profesion ?? '', 'Veterinaria');?>>Veterinaria</option>
                                <option value="Gastronomía" <?php selected($datos->profesion ?? '', 'Gastronomía');?>>Gastronomía</option>
                                <option value="Diseño Gráfico" <?php selected($datos->profesion ?? '', 'Diseño Gráfico');?>>Diseño Gráfico</option>
                                <option value="Arte / Música / Danza" <?php selected($datos->profesion ?? '', 'Arte / Música / Danza');?>>Arte / Música / Danza</option>
                                <option value="Derecho" <?php selected($datos->profesion ?? '', 'Derecho');?>>Derecho</option>
                                <option value="Trabajo Social" <?php selected($datos->profesion ?? '', 'Trabajo Social');?>>Trabajo Social</option>
                                <option value="Estadística" <?php selected($datos->profesion ?? '', 'Estadística');?>>Estadística</option>
                                <option value="Educación" <?php selected($datos->profesion ?? '', 'Educación');?>>Educación</option>
                                <option value="Comunicaciones" <?php selected($datos->profesion ?? '', 'Comunicaciones');?>>Comunicaciones</option>
                                <option value="Gestión Pública" <?php selected($datos->profesion ?? '', 'Gestión Pública');?>>Gestión Pública</option>
                            </optgroup>
                        </select>
                    </div>
    
                    <div class="olc-form-row">
                        <div class="olc-form-col">
                            <label>Experiencia *</label>
                            <select name="experiencia_anios" required>
                                <option value="0" <?php selected($datos->experiencia_anios ?? '', '0'); ?>>Sin experiencia</option>
                                <option value="1" <?php selected($datos->experiencia_anios ?? '', '1'); ?>>Menos de 1 año</option>
                                <option value="2" <?php selected($datos->experiencia_anios ?? '', '2'); ?>>Entre 1 y 2 años</option>
                                <option value="3" <?php selected($datos->experiencia_anios ?? '', '3'); ?>>De 2 a más años</option>
                            </select>
                        </div>
                        <div class="olc-form-col">
                            <label>¿Maneja moto? *</label>
                            <select name="sabe_moto" required>
                                <option value="">-- Selecciona una opción --</option>
                                <option value="tiene_licencia" <?php selected($datos->sabe_moto ?? '', 'tiene_licencia'); ?>>Sí, tengo licencia</option>
                                <option value="disponibilidad" <?php selected($datos->sabe_moto ?? '', 'disponibilidad'); ?>>Tengo disponibilidad para sacar licencia</option>
                                <option value="no_sabe" <?php selected($datos->sabe_moto ?? '', 'no_sabe'); ?>>No sé manejar moto</option>
                            </select>
                        </div>
                    </div>
    
                    <div class="olc-form-row">
                        <div class="olc-form-col">
                            <label>Disponibilidad</label>
                            <input type="text" name="disponibilidad" value="<?php echo esc_attr($datos->disponibilidad ?? ''); ?>" placeholder="Ej: Inmediata, A partir de...">
                        </div>
                        <div class="olc-form-col">
                            <label>Pretensión salarial (S/.)</label>
                            <input type="number" name="pretension_salarial" step="0.01" min="0" placeholder="0 = A convenir" value="<?php echo esc_attr($datos->pretension_salarial ?? ''); ?>">
                        </div>
                    </div>
    
                    <div style="margin-bottom:15px;">
                        <label>Currículum Vitae (PDF)</label>
                        <input type="file" name="ccvv" accept="application/pdf" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px;">
                        
                        <?php if (!empty($datos->ccvv)): ?>
                        <p style="margin:10px 0 0 0; font-size:13px; color:#666;">
                            📎 CV actual: 
                            <a href="<?php echo esc_url($datos->ccvv); ?>" target="_blank" style="color:#667eea;">Ver archivo</a>
                        </p>
                        <?php endif; ?>
                    </div>
    
                    <div style="margin-top:25px; text-align:center; display:flex; gap:10px; justify-content:center;">
                        <button type="submit" class="olc-btn-primary" style="flex:1; max-width:200px;">
                            💾 Guardar Cambios
                        </button>
                        <button type="button" id="btnCancelarEditar" class="olc-btn-secondary" style="flex:1; max-width:200px;">
                            ❌ Cancelar
                        </button>
                    </div>
                </form>
                
                <div id="edit-form-response" style="margin-top:15px;"></div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    
    
       
    /* -----------------------------
     * Actualizar CV desde el panel del postulante (Shortcode: Actualizar ccvv)
    ----------------------------- */
    
    
    public static function actualizar_cv_ajax() {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'No autorizado']);
        }
        
        check_ajax_referer('olc_actualizar_cv', 'cv_nonce');
        
        $user_id = get_current_user_id();
        
        // Procesar archivo
        if (empty($_FILES['nuevo_cv']) || $_FILES['nuevo_cv']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(['message' => 'No se recibió el archivo']);
        }
        
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        
        $file = $_FILES['nuevo_cv'];
        
        // Validar PDF
        $file_type = wp_check_filetype($file['name']);
        if ($file['type'] !== 'application/pdf' && $file_type['type'] !== 'application/pdf') {
            wp_send_json_error(['message' => 'Solo se permiten archivos PDF']);
        }
        
        // Validar tamaño (5MB)
        if ($file['size'] > 5 * 1024 * 1024) {
            wp_send_json_error(['message' => 'El archivo es muy grande (máximo 5MB)']);
        }
        
        // ========================================
        // ✅ Guardar en carpeta personalizada
        // ========================================
        $upload_dir = wp_upload_dir();
        $custom_dir = $upload_dir['basedir'] . '/cvs-postulantes';
        $custom_url = $upload_dir['baseurl'] . '/cvs-postulantes';
        
        // Crear carpeta si no existe
        if (!file_exists($custom_dir)) {
            wp_mkdir_p($custom_dir);
        }
        
        // Generar nombre único
        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $unique_filename = 'cv_' . $user_id . '_' . time() . '.' . $file_extension;
        $destination = $custom_dir . '/' . $unique_filename;
        
        // Mover archivo
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            wp_send_json_error(['message' => 'Error al guardar el archivo']);
        }
        
        $new_cv_url = $custom_url . '/' . $unique_filename;
        
        // ========================================
        // ✅ Eliminar CV anterior
        // ========================================
        global $wpdb;
        $tabla = $wpdb->prefix . 'olc_postulantes';
        
        $cv_anterior = $wpdb->get_var($wpdb->prepare(
            "SELECT ccvv FROM {$tabla} WHERE user_id=%d",
            $user_id
        ));
        
        if (!empty($cv_anterior) && strpos($cv_anterior, '/cvs-postulantes/') !== false) {
            $old_file_path = str_replace($custom_url, $custom_dir, $cv_anterior);
            if (file_exists($old_file_path)) {
                @unlink($old_file_path);
            }
        }
        
        // ========================================
        // Actualizar en BD
        // ========================================
        $wpdb->update($tabla, array(
            'ccvv' => $new_cv_url,
            'fecha_registro' => current_time('mysql')
        ), array('user_id' => $user_id));
        
        wp_send_json_success(['message' => 'CV actualizado correctamente']);
    }


        /* -----------------------------
           AJAX: Enviar CV a Bolsa de Postulantes
           ----------------------------- */
        public static function enviar_cv_bolsa_ajax() {
            if (!is_user_logged_in()) {
                wp_send_json_error(['message' => 'Debes iniciar sesión']);
            }
            
            check_ajax_referer('olc_bolsa_cv', 'bolsa_nonce');
            
            global $wpdb;
            $user_id = get_current_user_id();
            
            // ========================================
            // Validar que no haya enviado ya a la bolsa
            // ========================================
            $tabla_post = $wpdb->prefix . 'olc_postulaciones';
            $ya_en_bolsa = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$tabla_post} 
                 WHERE user_id = %d AND oferta_id = 0",
                $user_id
            ));
            
            if ($ya_en_bolsa > 0) {
                wp_send_json_error(['message' => 'Ya enviaste tu CV a nuestra bolsa de postulantes. Te contactaremos cuando surja una oportunidad.']);
            }
            
            // ========================================
            // Procesar CV (igual que antes)
            // ========================================
            $ccvv_url = '';
            
            if (empty($_FILES['ccvv']) || $_FILES['ccvv']['error'] !== UPLOAD_ERR_OK) {
                wp_send_json_error(['message' => 'El CV es obligatorio']);
            }
            
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            $file = $_FILES['ccvv'];
            
            // Validar PDF
            $file_type = wp_check_filetype($file['name']);
            if ($file['type'] !== 'application/pdf' && $file_type['type'] !== 'application/pdf') {
                wp_send_json_error(['message' => 'Solo se permiten archivos PDF']);
            }
            
            // Validar tamaño (5MB)
            if ($file['size'] > 5 * 1024 * 1024) {
                wp_send_json_error(['message' => 'El archivo es muy grande (máximo 5MB)']);
            }
            
            // Guardar en carpeta personalizada
            $upload_dir = wp_upload_dir();
            $custom_dir = $upload_dir['basedir'] . '/cvs-postulantes';
            $custom_url = $upload_dir['baseurl'] . '/cvs-postulantes';
            
            if (!file_exists($custom_dir)) {
                wp_mkdir_p($custom_dir);
            }
            
            $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $unique_filename = 'cv_bolsa_' . $user_id . '_' . time() . '.' . $file_extension;
            $destination = $custom_dir . '/' . $unique_filename;
            
            if (!move_uploaded_file($file['tmp_name'], $destination)) {
                wp_send_json_error(['message' => 'Error al guardar el archivo']);
            }
            
            $ccvv_url = $custom_url . '/' . $unique_filename;
            
            // ========================================
            // Guardar/Actualizar en tabla postulantes
            // ========================================
            $tabla_postulantes = $wpdb->prefix . 'olc_postulantes';
            
            $experiencia_anios = intval($_POST['experiencia_anios'] ?? 0);
            $sabe_moto = sanitize_text_field($_POST['sabe_moto'] ?? 'no_sabe');
            
            $data_postulante = array(
                'user_id' => $user_id,
                'nombre' => sanitize_text_field($_POST['nombre'] ?? ''),
                'dni' => sanitize_text_field($_POST['dni'] ?? ''),
                'telefono' => sanitize_text_field($_POST['telefono'] ?? ''),
                'email' => sanitize_email($_POST['email'] ?? ''),
                'fecha_nacimiento' => sanitize_text_field($_POST['fecha_nacimiento'] ?? ''),
                'ciudad' => sanitize_text_field($_POST['ciudad'] ?? ''),
                'profesion' => sanitize_text_field($_POST['profesion'] ?? ''),
                'experiencia_anios' => $experiencia_anios,
                'sabe_moto' => $sabe_moto,
                'ccvv' => $ccvv_url,
                'fecha_registro' => current_time('mysql')
            );
            
            $existe = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$tabla_postulantes} WHERE user_id=%d", 
                $user_id
            ));
            
            if ($existe) {
                $wpdb->update($tabla_postulantes, $data_postulante, array('user_id' => $user_id));
            } else {
                $wpdb->insert($tabla_postulantes, $data_postulante);
            }
            
            // ========================================
            // Crear registro en postulaciones con oferta_id = 0
            // ========================================
            $insert_bolsa = array(
                'oferta_id' => 0, // ✅ 0 = Bolsa de postulantes
                'user_id' => $user_id,
                'nombre' => sanitize_text_field($_POST['nombre'] ?? ''),
                'dni' => sanitize_text_field($_POST['dni'] ?? ''),
                'telefono' => sanitize_text_field($_POST['telefono'] ?? ''),
                'email' => sanitize_email($_POST['email'] ?? ''),
                'fecha_nacimiento' => sanitize_text_field($_POST['fecha_nacimiento'] ?? ''),
                'ciudad' => sanitize_text_field($_POST['ciudad'] ?? ''),
                'profesion' => sanitize_text_field($_POST['profesion'] ?? ''),
                'experiencia_anios' => $experiencia_anios,
                'ccvv_path' => $ccvv_url,
                'fecha_ccvv_subida' => current_time('mysql'),
                'fecha_postulacion' => current_time('mysql'),
                'estado_postulacion' => 'Bolsa', // ✅ Estado especial
                'etapa' => 0
            );
            
            $resultado = $wpdb->insert($tabla_post, $insert_bolsa);
            
            if ($resultado === false) {
                wp_send_json_error(['message' => 'Error al registrar en la bolsa']);
            }
            
            wp_send_json_success(['message' => '¡CV enviado correctamente a nuestra bolsa de postulantes!']);
        }




}

// init
OLC_Public::init();
