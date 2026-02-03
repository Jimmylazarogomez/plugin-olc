<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$tabla_ofertas = $wpdb->prefix . 'olc_ofertas';

$ofertas = $wpdb->get_results("
    SELECT id, titulo, ciudad, fecha_inicio, fecha_fin, estado
    FROM $tabla_ofertas
    ORDER BY id DESC
");
?>

<div class="wrap">
    <h1 class="wp-heading-inline">Listado de Ofertas Laborales</h1>
    <a href="<?php echo admin_url('admin.php?page=olc_ofertas_new'); ?>" class="page-title-action">Añadir Nueva</a>
    <hr class="wp-header-end">
    
    <?php if (isset($_GET['mensaje']) && $_GET['mensaje'] === 'eliminada'): ?>
        <div class="notice notice-success is-dismissible">
            <p><strong>✅ Oferta eliminada correctamente.</strong></p>
        </div>
    <?php endif; ?>
    
    <?php if ($ofertas): ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th width="50">ID</th>
                    <th>Título</th>
                    <th>Ciudad</th>
                    <th>Fecha Inicio</th>
                    <th>Fecha Fin</th>
                    <th>Estado</th>
                    <th width="180">Acciones</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($ofertas as $o): ?>
                <tr>
                    <td><?php echo intval($o->id); ?></td>
                    <td><strong><?php echo esc_html($o->titulo); ?></strong></td>
                    <td><?php echo esc_html($o->ciudad ?: '—'); ?></td>
                    <td><?php echo esc_html($o->fecha_inicio ?: '—'); ?></td>
                    <td><?php echo esc_html($o->fecha_fin ?: '—'); ?></td>
                    
                    <td>
                        <?php
                            // Fechas
                            $hoy = current_time('Y-m-d');
                            $inicio = $o->fecha_inicio;
                            $fin = $o->fecha_fin;
                    
                            // Consultar si existe GANADOR para esta oferta
                            $hay_ganador = $wpdb->get_var($wpdb->prepare("
                                SELECT COUNT(*) 
                                FROM {$wpdb->prefix}olc_postulaciones 
                                WHERE oferta_id = %d 
                                AND estado_postulacion = 'Ganador'
                            ", $o->id));
                    
                            if ($hoy >= $inicio && $hoy <= $fin) {
                                echo '<span style="color:#0a7b1f; font-weight:bold;">Activo</span>';
                    
                            } elseif ($hoy > $fin && !$hay_ganador) {
                                echo '<span style="color:#f1c40f; font-weight:bold;">En evaluación</span>';
                    
                            } elseif ($hoy > $fin && $hay_ganador) {
                                echo '<span style="color:#a00; font-weight:bold;">Finalizado</span>';
                    
                            } else {
                                echo '<span style="color:#555; font-weight:bold;">Indefinido</span>';
                            }
                        ?>
                    </td>


                    <td>
                        <a href="<?php echo admin_url('admin.php?page=olc_ofertas&action=edit&id=' . intval($o->id)); ?>" class="button button-primary button-small">Editar</a>

                        <a href="<?php echo admin_url('admin.php?page=olc_postulaciones&oferta_id=' . intval($o->id)); ?>" class="button button-secondary button-small">Postulaciones</a>

                        <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=olc_delete_oferta&id=' . intval($o->id)), 'olc_delete_oferta'); ?>"
                           class="button button-small"
                           onclick="return confirm('¿Estás seguro de eliminar esta oferta?');">
                           Eliminar
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>

        </table>
    <?php else: ?>
        <p>No hay ofertas creadas aún.</p>
    <?php endif; ?>
</div>

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
</style>
