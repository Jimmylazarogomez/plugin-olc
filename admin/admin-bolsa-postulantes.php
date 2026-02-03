    <?php
    if (!defined('ABSPATH')) exit;
    ?>
    
    <div class="wrap">
        <h1>📋 Bolsa de Postulantes</h1>

        <?php if (isset($_GET['mensaje']) && $_GET['mensaje'] === 'asociado'): ?>
            <div class="notice notice-success is-dismissible">
                <p><strong>Postulante asociado correctamente a la oferta.</strong></p>
            </div>
        <?php endif; ?>
        
        <p>Postulantes que enviaron su CV sin asociarse a una oferta específica.</p>
        
        <?php if (empty($postulantes)): ?>
            <p><em>No hay postulantes en la bolsa aún.</em></p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>DNI</th>
                        <th>Profesión</th>
                        <th>Ciudad</th>
                        <th>Teléfono</th>
                        <th>Email</th>
                        <th>Fecha</th>
                        <th>CV</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($postulantes as $p): ?>
                        <tr>
                            <td><?php echo intval($p->id); ?></td>
                            <td><strong><?php echo esc_html($p->nombre); ?></strong></td>
                            <td><?php echo esc_html($p->dni); ?></td>
                            <td><?php echo esc_html($p->profesion); ?></td>
                            <td><?php echo esc_html($p->ciudad); ?></td>
                            <td><?php echo esc_html($p->telefono); ?></td>
                            <td><?php echo esc_html($p->email); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($p->fecha_postulacion)); ?></td>
                            <td>
                                <?php if (!empty($p->ccvv_path)): ?>
                                    <a href="<?php echo esc_url($p->ccvv_path); ?>" target="_blank" class="button">
                                        Ver CV
                                    </a>
                                <?php else: ?>
                                    <span style="color:#999;">Sin CV</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="button button-primary btnAsociarOferta" 
                                        data-postulante-id="<?php echo intval($p->id); ?>"
                                        data-postulante-nombre="<?php echo esc_attr($p->nombre); ?>">
                                    Asociar a Oferta
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <!-- Modal para asociar a oferta -->
    <div id="modalAsociarOferta" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); z-index:9999;">
        <div style="background:white; max-width:500px; margin:100px auto; padding:30px; border-radius:12px;">
            <h2>ÃƒÂ¢Ã…Â¾Ã¢â‚¬Â¢ Asociar a Oferta Laboral</h2>
            <p>Postulante: <strong id="nombrePostulante"></strong></p>
            
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="olc_asociar_bolsa_oferta">
                <input type="hidden" name="postulante_id" id="postulanteId">
                <?php wp_nonce_field('olc_asociar_bolsa'); ?>
                
                <label><strong>Selecciona la oferta:</strong></label>
                <select name="oferta_id" required style="width:100%; padding:10px; margin:10px 0;">
                    <option value="">-- Selecciona una oferta --</option>
                    <?php
                    global $wpdb;
                    $ofertas = $wpdb->get_results("
                        SELECT id, titulo, ciudad 
                        FROM {$wpdb->prefix}olc_ofertas 
                        WHERE estado = 'publicada' 
                        ORDER BY titulo ASC
                    ");
                    foreach ($ofertas as $oferta):
                    ?>
                        <option value="<?php echo intval($oferta->id); ?>">
                            <?php echo esc_html($oferta->titulo . ' - ' . $oferta->ciudad); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <div style="margin-top:20px; text-align:right;">
                    <button type="button" id="btnCerrarModal" class="button">Cancelar</button>
                    <button type="submit" class="button button-primary">Asociar</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($){
        $('.btnAsociarOferta').on('click', function(){
            var id = $(this).data('postulante-id');
            var nombre = $(this).data('postulante-nombre');
            
            $('#postulanteId').val(id);
            $('#nombrePostulante').text(nombre);
            $('#modalAsociarOferta').fadeIn();
        });
        
        $('#btnCerrarModal').on('click', function(){
            $('#modalAsociarOferta').fadeOut();
        });
    });
    </script>
    
    
    
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