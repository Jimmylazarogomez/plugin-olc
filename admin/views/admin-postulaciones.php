<?php
if (!defined('ABSPATH')) exit;
global $wpdb;

$table_post = $wpdb->prefix . 'olc_postulaciones';
$table_ofertas = $wpdb->prefix . 'olc_ofertas';

// ==== FILTROS ====
$f_oferta = isset($_GET['oferta_id']) ? intval($_GET['oferta_id']) : 0;
$f_estado = isset($_GET['estado']) ? sanitize_text_field($_GET['estado']) : '';
$f_ciudad = isset($_GET['ciudad']) ? sanitize_text_field($_GET['ciudad']) : '';

$where = '1=1';
if ($f_oferta) $where .= $wpdb->prepare(" AND p.oferta_id = %d", $f_oferta);
if (!empty($f_estado)) $where .= $wpdb->prepare(" AND p.estado_postulacion = %s", $f_estado);
// if (!empty($f_ciudad)) $where .= $wpdb->prepare(" AND p.ciudad = %s", $f_ciudad);
if (!empty($f_ciudad)) {
    $where .= $wpdb->prepare(" AND o.ciudad = %s", $f_ciudad);
}



// ==== DETALLE DE POSTULACIÓN ====
if (isset($_GET['action']) && $_GET['action'] === 'view' && !empty($_GET['id'])) {
    $id = intval($_GET['id']);
    // $post = $wpdb->get_row($wpdb->prepare("
    //     SELECT p.*, o.titulo 
    //       FROM {$table_post} p 
    //       LEFT JOIN {$table_ofertas} o ON p.oferta_id = o.id 
    //     WHERE p.id = %d
    //     ", $id));
    $post = $wpdb->get_row($wpdb->prepare("
        SELECT 
            p.*, 
            o.titulo,
            pt.sabe_moto,
            pt.pretension_salarial,
            o.ciudad As agencia,
            pt.telefono AS telefono_real,
            pt.ccvv AS ccvv_url
        FROM {$table_post} p
        LEFT JOIN {$table_ofertas} o ON p.oferta_id = o.id
        LEFT JOIN {$wpdb->prefix}olc_postulantes pt ON pt.user_id = p.user_id
        WHERE p.id = %d
    ", $id));


    if ($post):
        $telefono_limpio = preg_replace('/[^0-9+]/','', $post->telefono);
        $mensaje = rawurlencode("Hola {$post->nombre}, te contactamos respecto a tu postulación en la oferta: {$post->titulo}.");
        ?>
        <div class="wrap">
            <h1>Detalle de Postulación</h1>
            <table class="widefat fixed striped" style="max-width:800px;">
                <tbody>
                    <tr><th>ID</th><td><?php echo intval($post->id); ?></td></tr>
                    <tr><th>Oferta</th><td><?php echo esc_html($post->titulo); ?></td></tr>
                    <tr><th>Nombre</th><td><?php echo esc_html($post->nombre); ?></td></tr>
                    <tr><th>Email</th><td><?php echo esc_html($post->email); ?></td></tr>
                    <tr><th>Teléfono</th><td><?php echo esc_html($post->telefono); ?></td></tr>
                    <tr><th>Agencia</th><td><?php echo esc_html($post->ciudad); ?></td></tr>
                    <tr><th>Profesión</th><td><?php echo esc_html($post->profesion); ?></td></tr>
                    <tr>
                        <th>Pretensión Salarial</th>
                        <td>
                            <?php
                                if ($post->pretension_salarial === null || $post->pretension_salarial == 0) {
                                    echo "A convenir";
                                } else {
                                    echo "S/ " . number_format(floatval($post->pretension_salarial), 2);
                                }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th>¿Maneja moto?</th>
                        <td>
                            <?php
                                $moto = $post->sabe_moto ?? 'no_sabe';
                    
                                switch ($moto) {
                                    case 'tiene_licencia':
                                        echo "Sí, tiene licencia";
                                        break;
                                    case 'disponibilidad':
                                        echo "Predisposición";
                                        break;
                                    default:
                                        echo "No sabe";
                                        break;
                                }
                            ?>
                        </td>
                    </tr>


                    <tr><th>Estado</th><td><?php echo esc_html($post->estado_postulacion); ?></td></tr>
                    <tr><th>Puntaje Total</th><td><?php echo intval($post->puntaje_total); ?></td></tr>
                    <tr><th>Fecha de Postulación</th><td><?php echo esc_html($post->fecha_postulacion); ?></td></tr>
                    <tr><th>Archivo CV</th><td>
                        <?php if (!empty($post->ccvv_path)): ?>
                            <a href="<?php echo esc_url(site_url('/?olc_action=view_cv&postulacion_id=' . intval($post->id))); ?>" target="_blank">Descargar CV</a>
                        <?php else: ?>
                            <em>No disponible</em>
                        <?php endif; ?>
                    </td></tr>
                </tbody>
            </table>
            <p style="margin-top:20px;">
                <a href="<?php echo esc_url(admin_url('admin.php?page=olc_postulaciones')); ?>" class="button">← Volver al listado</a>
                <a href="https://wa.me/<?php echo $telefono_limpio; ?>?text=<?php echo $mensaje; ?>" target="_blank" class="button button-primary">Enviar mensaje WhatsApp</a>
            </p>
        </div>
        <?php
    endif;
    return;
}

// ==== LISTADO GENERAL ====
$query = "
    SELECT p.*, o.titulo, o.ciudad As agencia, pt.ccvv AS ccvv_url, pt.telefono AS telefono_real, pt.pretension_salarial, pt.sabe_moto
    FROM {$table_post} p
    LEFT JOIN {$table_ofertas} o ON p.oferta_id = o.id
    LEFT JOIN {$wpdb->prefix}olc_postulantes pt ON pt.user_id = p.user_id
    WHERE $where
    ORDER BY p.fecha_postulacion DESC
    LIMIT 1000
";

$rows = $wpdb->get_results($query);
$ofertas = $wpdb->get_results("SELECT id, titulo, ciudad FROM {$table_ofertas} ORDER BY titulo ASC");
?>

<div class="wrap">
    <h1>Postulaciones</h1>

    <form method="get" style="margin-bottom:15px;">
        <input type="hidden" name="page" value="olc_postulaciones">
        <label>Oferta:
            <select name="oferta_id">
                <option value="">Todas</option>
                <?php foreach($ofertas as $o): ?>
                    <option value="<?php echo intval($o->id); ?>" <?php selected($f_oferta, $o->id); ?>>
                        <?php echo esc_html($o->titulo . ' — ' . $o->ciudad); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>Estado:
            <select name="estado">
                <option value="">Todos</option>
                <option value="Enviado" <?php selected($f_estado, 'Enviado'); ?>>Enviado</option>
                <option value="En evaluación" <?php selected($f_estado, 'En evaluación'); ?>>En evaluación</option>
                <option value="Preseleccionado" <?php selected($f_estado, 'Preseleccionado'); ?>>Preseleccionado</option>
                <option value="Entrevista" <?php selected($f_estado, 'Entrevista'); ?>>Entrevista</option>
                <option value="Contratado" <?php selected($f_estado, 'Contratado'); ?>>Contratado</option>
                <option value="Rechazado" <?php selected($f_estado, 'Rechazado'); ?>>Rechazado</option>
            </select>
        </label>

        <label>Agencia:
            <input type="text" name="ciudad" value="<?php echo esc_attr($f_ciudad); ?>" placeholder="Ej. Lima">
            
        </label>

        <button class="button">Filtrar</button>
        <a href="<?php echo admin_url('admin-post.php?action=olc_export_postulaciones'); ?>" class="button button-secondary">Exportar CSV</a>
    </form>

    <table class="widefat fixed striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Oferta</th>
                <th>Nombre</th>
                <th>DNI</th>
                <th>Email</th>
                <th>Teléfono</th>
                <th>Agencia</th>
                <th>Pretensión</th>
                <th>Maneja Moto</th>
                <th>Estado</th>
                <th>Puntaje</th>
                <th>Fecha</th>
                <th>CV</th>
                <th>Acción</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($rows): foreach ($rows as $r): ?>
            <tr>
                <td><?php echo intval($r->id); ?></td>
                <td><?php echo esc_html($r->titulo ?: 'Sin oferta'); ?></td>
                <td><?php echo esc_html($r->nombre); ?></td>
                <td><?php echo esc_html($r->dni); ?></td>
                <td><?php echo esc_html($r->email); ?></td>
                <td><?php echo esc_html($r->telefono); ?></td>
                <td><?php echo esc_html($r->agencia); ?></td>
                <td>
                    <?php 
                        echo ($r->pretension_salarial === null || intval($r->pretension_salarial) === 0)
                            ? "A convenir"
                            : "S/ " . number_format(floatval($r->pretension_salarial), 2);
                    ?>
                </td>
                <td>
                    <?php
                        $moto = $r->sabe_moto ?? 'no_sabe';
                
                        switch ($moto) {
                            case 'tiene_licencia':
                                echo "Tiene licencia";
                                break;
                            case 'disponibilidad':
                                echo "Predisposición";
                                break;
                            default:
                                echo "No sabe";
                                break;
                        }
                    ?>
                </td>
                <td><?php echo esc_html($r->estado_postulacion); ?></td>
                <td><?php echo intval($r->puntaje_total); ?></td>
                <td><?php echo esc_html($r->fecha_postulacion); ?></td>
                <td>
                    <?php if (!empty($r->ccvv_url)): ?>
                        <a href="<?php echo esc_url($r->ccvv_url); ?>" target="_blank">Ver CV</a>
                    <?php else: ?>
                        <em>No</em>
                    <?php endif; ?>

                </td>
                <td>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=olc_etapas&oferta_id=' . intval($r->oferta_id))); ?>" class="button button-primary">Ver Etapas</a>
                </td>
            </tr>
        <?php endforeach; else: ?>
            <tr><td colspan="11"><em>No hay postulaciones registradas.</em></td></tr>
        <?php endif; ?>
        </tbody>
    </table>
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
