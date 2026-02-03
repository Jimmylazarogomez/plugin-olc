<?php
/**
 * Vista: Panel de Control - Dashboard del Plugin
 * Archivo: admin/views/admin-dashboard.php
 */

if (!defined('ABSPATH')) exit;

global $wpdb;

// ========================================
// CONSULTAS PARA OBTENER DATOS
// ========================================

// Tablas
$tbl_ofertas = $wpdb->prefix . 'olc_ofertas';
$tbl_post = $wpdb->prefix . 'olc_postulaciones';
$tbl_punt = $wpdb->prefix . 'olc_puntuaciones';

// === OFERTAS ===
$total_ofertas = $wpdb->get_var("SELECT COUNT(*) FROM {$tbl_ofertas}");

// Ofertas Activas (dentro del rango de fechas)
$hoy = current_time('mysql');
$ofertas_activas = $wpdb->get_var($wpdb->prepare("
    SELECT COUNT(*) FROM {$tbl_ofertas} 
    WHERE fecha_inicio <= %s AND fecha_fin >= %s
", $hoy, $hoy));

// Ofertas Finalizadas (tienen ganador)
$ofertas_finalizadas = $wpdb->get_var("
    SELECT COUNT(DISTINCT o.id) 
    FROM {$tbl_ofertas} o
    WHERE EXISTS (
        SELECT 1 FROM {$tbl_post} p 
        WHERE p.oferta_id = o.id 
        AND p.resultado_final IN ('Ganador','ganador','GANADOR')
    )
");

// Ofertas en Evaluación (fecha pasada pero sin ganador)
$ofertas_evaluacion = $wpdb->get_var($wpdb->prepare("
    SELECT COUNT(*) FROM {$tbl_ofertas} o
    WHERE fecha_fin < %s
    AND NOT EXISTS (
        SELECT 1 FROM {$tbl_post} p 
        WHERE p.oferta_id = o.id 
        AND p.resultado_final IN ('Ganador','ganador','GANADOR')
    )
", $hoy));

// === POSTULACIONES ===
$total_postulaciones = $wpdb->get_var("SELECT COUNT(*) FROM {$tbl_post} WHERE oferta_id > 0");

// Postulaciones por etapa
$etapa_1 = $wpdb->get_var("SELECT COUNT(*) FROM {$tbl_post} WHERE etapa = 1 AND oferta_id > 0");
$etapa_2 = $wpdb->get_var("SELECT COUNT(*) FROM {$tbl_post} WHERE etapa = 2 AND oferta_id > 0");
$etapa_3 = $wpdb->get_var("SELECT COUNT(*) FROM {$tbl_post} WHERE etapa = 3 AND oferta_id > 0");

// Ganadores
$total_ganadores = $wpdb->get_var("
    SELECT COUNT(*) FROM {$tbl_post} 
    WHERE resultado_final IN ('Ganador','ganador','GANADOR')
");

// Bolsa de postulantes
$bolsa_postulantes = $wpdb->get_var("SELECT COUNT(*) FROM {$tbl_post} WHERE oferta_id = 0");

// === ÚLTIMAS ACTIVIDADES ===
$ultimas_postulaciones = $wpdb->get_results("
    SELECT p.*, o.titulo as oferta_titulo
    FROM {$tbl_post} p
    LEFT JOIN {$tbl_ofertas} o ON p.oferta_id = o.id
    WHERE p.oferta_id > 0
    ORDER BY p.fecha_postulacion DESC
    LIMIT 5
");

// Ofertas más recientes
$ofertas_recientes = $wpdb->get_results("
    SELECT id, titulo, estado, fecha_inicio, fecha_fin, 
           (SELECT COUNT(*) FROM {$tbl_post} WHERE oferta_id = o.id) as total_postulaciones
    FROM {$tbl_ofertas} o
    ORDER BY created_at DESC
    LIMIT 5
");

// === ESTADÍSTICAS AVANZADAS ===
// Promedio de postulaciones por oferta
$promedio_postulaciones = $wpdb->get_var("
    SELECT AVG(total) FROM (
        SELECT COUNT(*) as total 
        FROM {$tbl_post} 
        WHERE oferta_id > 0 
        GROUP BY oferta_id
    ) as subconsulta
");
$promedio_postulaciones = $promedio_postulaciones ? round($promedio_postulaciones, 1) : 0;

// Tasa de conversión (ganadores / total postulaciones)
$tasa_conversion = $total_postulaciones > 0 ? round(($total_ganadores / $total_postulaciones) * 100, 1) : 0;

?>

<div class="wrap olc-dashboard">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-chart-area" style="font-size: 28px; vertical-align: middle;"></span>
        Panel de Control - Ofertas Laborales
    </h1>
    
    <hr class="wp-header-end">

    <!-- ========================================
         TARJETAS DE RESUMEN (KPIs)
    ======================================== -->
    <div class="olc-cards-grid">
        <!-- Ofertas Activas -->
        <div class="olc-card olc-card-green">
            <div class="olc-card-icon">
                <span class="dashicons dashicons-megaphone"></span>
            </div>
            <div class="olc-card-content">
                <h3><?php echo esc_html($ofertas_activas); ?></h3>
                <p>Ofertas Activas</p>
            </div>
        </div>

        <!-- Total Ofertas -->
        <div class="olc-card olc-card-blue">
            <div class="olc-card-icon">
                <span class="dashicons dashicons-portfolio"></span>
            </div>
            <div class="olc-card-content">
                <h3><?php echo esc_html($total_ofertas); ?></h3>
                <p>Total Ofertas</p>
            </div>
        </div>

        <!-- Total Postulaciones -->
        <div class="olc-card olc-card-purple">
            <div class="olc-card-icon">
                <span class="dashicons dashicons-groups"></span>
            </div>
            <div class="olc-card-content">
                <h3><?php echo esc_html($total_postulaciones); ?></h3>
                <p>Total Postulaciones</p>
            </div>
        </div>

        <!-- Ganadores -->
        <div class="olc-card olc-card-gold">
            <div class="olc-card-icon">
                <span class="dashicons dashicons-awards"></span>
            </div>
            <div class="olc-card-content">
                <h3><?php echo esc_html($total_ganadores); ?></h3>
                <p>Ganadores</p>
            </div>
        </div>
    </div>

    <!-- ========================================
         ESTADÍSTICAS DETALLADAS
    ======================================== -->
    <div class="olc-stats-section">
        <div class="olc-stats-left">
            <!-- Estado de Ofertas -->
            <div class="olc-panel">
                <h2>📊 Estado de Ofertas</h2>
                <table class="widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Estado</th>
                            <th style="text-align: center;">Cantidad</th>
                            <th style="text-align: center;">Porcentaje</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><span class="olc-badge olc-badge-green">Activas</span></td>
                            <td style="text-align: center;"><strong><?php echo esc_html($ofertas_activas); ?></strong></td>
                            <td style="text-align: center;">
                                <?php echo $total_ofertas > 0 ? round(($ofertas_activas / $total_ofertas) * 100, 1) : 0; ?>%
                            </td>
                        </tr>
                        <tr>
                            <td><span class="olc-badge olc-badge-orange">En Evaluación</span></td>
                            <td style="text-align: center;"><strong><?php echo esc_html($ofertas_evaluacion); ?></strong></td>
                            <td style="text-align: center;">
                                <?php echo $total_ofertas > 0 ? round(($ofertas_evaluacion / $total_ofertas) * 100, 1) : 0; ?>%
                            </td>
                        </tr>
                        <tr>
                            <td><span class="olc-badge olc-badge-gray">Finalizadas</span></td>
                            <td style="text-align: center;"><strong><?php echo esc_html($ofertas_finalizadas); ?></strong></td>
                            <td style="text-align: center;">
                                <?php echo $total_ofertas > 0 ? round(($ofertas_finalizadas / $total_ofertas) * 100, 1) : 0; ?>%
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Postulaciones por Etapa -->
            <div class="olc-panel" style="margin-top: 20px;">
                <h2>🎯 Postulaciones por Etapa</h2>
                <table class="widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Etapa</th>
                            <th style="text-align: center;">Cantidad</th>
                            <th style="text-align: center;">Porcentaje</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><span class="olc-badge olc-badge-blue">Etapa 1: Evaluación</span></td>
                            <td style="text-align: center;"><strong><?php echo esc_html($etapa_1); ?></strong></td>
                            <td style="text-align: center;">
                                <?php echo $total_postulaciones > 0 ? round(($etapa_1 / $total_postulaciones) * 100, 1) : 0; ?>%
                            </td>
                        </tr>
                        <tr>
                            <td><span class="olc-badge olc-badge-purple">Etapa 2: Entrevista</span></td>
                            <td style="text-align: center;"><strong><?php echo esc_html($etapa_2); ?></strong></td>
                            <td style="text-align: center;">
                                <?php echo $total_postulaciones > 0 ? round(($etapa_2 / $total_postulaciones) * 100, 1) : 0; ?>%
                            </td>
                        </tr>
                        <tr>
                            <td><span class="olc-badge olc-badge-gold">Etapa 3: Selección Final</span></td>
                            <td style="text-align: center;"><strong><?php echo esc_html($etapa_3); ?></strong></td>
                            <td style="text-align: center;">
                                <?php echo $total_postulaciones > 0 ? round(($etapa_3 / $total_postulaciones) * 100, 1) : 0; ?>%
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- KPIs Avanzados -->
        <div class="olc-stats-right">
            <div class="olc-panel">
                <h2>📈 KPIs Clave</h2>
                <div class="olc-kpi-list">
                    <div class="olc-kpi-item">
                        <span class="olc-kpi-label">Promedio Postulaciones/Oferta</span>
                        <span class="olc-kpi-value"><?php echo esc_html($promedio_postulaciones); ?></span>
                    </div>
                    <div class="olc-kpi-item">
                        <span class="olc-kpi-label">Tasa de Conversión</span>
                        <span class="olc-kpi-value"><?php echo esc_html($tasa_conversion); ?>%</span>
                    </div>
                    <div class="olc-kpi-item">
                        <span class="olc-kpi-label">Bolsa de Postulantes</span>
                        <span class="olc-kpi-value"><?php echo esc_html($bolsa_postulantes); ?></span>
                    </div>
                </div>
            </div>

            <!-- Acciones Rápidas -->
            <div class="olc-panel" style="margin-top: 20px;">
                <h2>⚡ Acciones Rápidas</h2>
                <div class="olc-quick-actions">
                    <a href="<?php echo admin_url('admin.php?page=olc_ofertas_new'); ?>" class="button button-primary button-large">
                        <span class="dashicons dashicons-plus-alt" style="vertical-align: middle;"></span>
                        Nueva Oferta
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=olc_ofertas'); ?>" class="button button-large">
                        <span class="dashicons dashicons-list-view" style="vertical-align: middle;"></span>
                        Ver Ofertas
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=olc_postulaciones'); ?>" class="button button-large">
                        <span class="dashicons dashicons-groups" style="vertical-align: middle;"></span>
                        Ver Postulaciones
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=olc_etapas'); ?>" class="button button-large">
                        <span class="dashicons dashicons-networking" style="vertical-align: middle;"></span>
                        Gestión de Etapas
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================
         ACTIVIDAD RECIENTE
    ======================================== -->
    <div class="olc-activity-section">
        <!-- Últimas Postulaciones -->
        <div class="olc-panel olc-panel-half">
            <h2>🕒 Últimas Postulaciones</h2>
            <?php if (!empty($ultimas_postulaciones)) : ?>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Oferta</th>
                            <th>Etapa</th>
                            <th>Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ultimas_postulaciones as $post) : ?>
                            <tr>
                                <td><strong><?php echo esc_html($post->nombre); ?></strong></td>
                                <td><?php echo esc_html($post->oferta_titulo ?: 'N/A'); ?></td>
                                <td>
                                    <?php 
                                    $etiquetas = array(
                                        1 => '<span class="olc-badge olc-badge-blue">Etapa 1</span>',
                                        2 => '<span class="olc-badge olc-badge-purple">Etapa 2</span>',
                                        3 => '<span class="olc-badge olc-badge-gold">Etapa 3</span>'
                                    );
                                    echo $etiquetas[$post->etapa] ?? '<span class="olc-badge">-</span>';
                                    ?>
                                </td>
                                <td><?php echo esc_html(date('d/m/Y H:i', strtotime($post->fecha_postulacion))); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p style="color: #666; padding: 20px; text-align: center;">No hay postulaciones recientes</p>
            <?php endif; ?>
        </div>

        <!-- Ofertas Recientes -->
        <div class="olc-panel olc-panel-half">
            <h2>📋 Ofertas Recientes</h2>
            <?php if (!empty($ofertas_recientes)) : ?>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th>Título</th>
                            <th style="text-align: center;">Postulaciones</th>
                            <th>Estado</th>
                            <th style="text-align: center;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ofertas_recientes as $oferta) : ?>
                            <tr>
                                <td><strong><?php echo esc_html($oferta->titulo); ?></strong></td>
                                <td style="text-align: center;">
                                    <span class="olc-badge olc-badge-blue"><?php echo esc_html($oferta->total_postulaciones); ?></span>
                                </td>
                                <td>
                                    <?php echo OLC_Admin::calcular_estado_oferta($oferta); ?>
                                </td>
                                <td style="text-align: center;">
                                    <a href="<?php echo admin_url('admin.php?page=olc_ofertas&action=edit&id=' . $oferta->id); ?>" 
                                       class="button button-small">Ver</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p style="color: #666; padding: 20px; text-align: center;">No hay ofertas creadas</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ========================================
     ESTILOS CSS
======================================== -->
<style>

    /* Títulos */

h1 {
  font-family: 'Poppins', sans-serif !important; /* Tipografía elegante */
  font-size: 30px !important; /* Tamaño grande */
  font-weight: bold !important; /* Negrita */
  text-align: center; /* Centrado */
  color: #ffffff; /* Color blanco */
  background: linear-gradient(45deg, #00c6ff, #0072ff); /* Gradiente azul y celeste */
  padding: 30px 70px !important; /* Espaciado alrededor del texto */
  border-radius: 10px; /* Bordes redondeados */
  box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2); /* Sombra sutil */
  text-shadow: 2px 2px 5px rgba(0, 0, 0, 0.3); /* Sombra de texto */
  transition: all 0.3s ease; /* Transición suave para los efectos */
}

h1:hover {
  background: linear-gradient(45deg, #0072ff, #00c6ff); /* Cambio de gradiente en hover */
}

h2 {
  font-family: 'Poppins', sans-serif; /* Aplicamos la fuente Poppins */
  font-weight: 600; /* Semi-bold, para que sea grueso pero elegante */
  font-size: 25px; /* Un tamaño adecuado para un h2 (ajústalo según lo necesites) */
  color: #333; /* Color oscuro para el texto */
}

.olc-dashboard {
    margin: 20px 20px 20px 0;
}

/* Tarjetas de resumen */
.olc-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 20px;
    margin: 25px 0;
}

.olc-card {
    background: #fff;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    gap: 15px;
    transition: transform 0.2s, box-shadow 0.2s;
}

.olc-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.olc-card-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
}

.olc-card-green .olc-card-icon { background: #d4edda; color: #155724; }
.olc-card-blue .olc-card-icon { background: #d1ecf1; color: #0c5460; }
.olc-card-purple .olc-card-icon { background: #e2d9f3; color: #5a3a7c; }
.olc-card-gold .olc-card-icon { background: #fff3cd; color: #856404; }

.olc-card-content h3 {
    margin: 0;
    font-size: 32px;
    font-weight: 700;
    color: #23282d;
}

.olc-card-content p {
    margin: 5px 0 0;
    font-size: 14px;
    color: #666;
}

/* Sección de estadísticas */
.olc-stats-section {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
    margin: 25px 0;
}

/* Paneles */
.olc-panel {
    background: #fff;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.olc-panel h2 {
    margin: 0 0 15px 0;
    font-size: 18px;
    color: #23282d;
    border-bottom: 2px solid #f0f0f0;
    padding-bottom: 10px;
}

/* Badges */
.olc-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
}

.olc-badge-green { background: #d4edda; color: #155724; }
.olc-badge-orange { background: #fff3cd; color: #856404; }
.olc-badge-gray { background: #e2e3e5; color: #383d41; }
.olc-badge-blue { background: #d1ecf1; color: #0c5460; }
.olc-badge-purple { background: #e2d9f3; color: #5a3a7c; }
.olc-badge-gold { background: #fff3cd; color: #856404; }

/* KPIs */
.olc-kpi-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.olc-kpi-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    background: #f9f9f9;
    border-radius: 6px;
    border-left: 4px solid #2271b1;
}

.olc-kpi-label {
    font-size: 14px;
    color: #666;
}

.olc-kpi-value {
    font-size: 24px;
    font-weight: 700;
    color: #2271b1;
}

/* Acciones rápidas */
.olc-quick-actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.olc-quick-actions .button {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

/* Actividad reciente */
.olc-activity-section {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin: 25px 0;
}

.olc-panel-half table {
    margin-top: 10px;
}

/* Responsive */
@media (max-width: 1200px) {
    .olc-stats-section {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .olc-activity-section {
        grid-template-columns: 1fr;
    }
    
    .olc-cards-grid {
        grid-template-columns: 1fr;
    }
}
</style>