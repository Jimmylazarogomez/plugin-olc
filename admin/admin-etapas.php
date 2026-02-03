<?php
if (!defined('ABSPATH')) exit;
global $wpdb;

$table_post = $wpdb->prefix . 'olc_postulaciones';
$table_ofertas = $wpdb->prefix . 'olc_ofertas';

$oferta_id = isset($_GET['oferta_id']) ? intval($_GET['oferta_id']) : 0;

// Obtener lista de ofertas
$ofertas = $wpdb->get_results("SELECT id, titulo, ciudad FROM $table_ofertas ORDER BY ciudad ASC, titulo ASC");

// Obtener postulantes de la oferta seleccionada
$postulantes = [];
if ($oferta_id) {

    $table_pt = $wpdb->prefix . 'olc_postulantes';
    
    $postulantes = $wpdb->get_results($wpdb->prepare("
        SELECT p.*, pt.ccvv AS ccvv_url
        FROM $table_post p
        LEFT JOIN $table_pt pt ON pt.user_id = p.user_id
        WHERE p.oferta_id = %d
        ORDER BY p.puntaje_total DESC
    ", $oferta_id));



}
?>

<div class="wrap">
    <h1>Gestión de Etapas - Postulantes</h1>

    <form method="get" style="margin-bottom:15px;">
        <input type="hidden" name="page" value="olc_etapas">
        <label>Seleccionar oferta:
            <select name="oferta_id" onchange="this.form.submit()">
                <option value="">-- Selecciona una oferta --</option>
                <?php foreach ($ofertas as $o): ?>
                    <option value="<?php echo intval($o->id); ?>" <?php selected($oferta_id, $o->id); ?>>
                        <?php echo esc_html($o->titulo . ' — ' . $o->ciudad); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
    </form>

    <?php if (!$oferta_id): ?>
        <p><em>Selecciona una oferta para gestionar las etapas de postulación.</em></p>
    <?php else: ?>
    
    <?php 
        $tab = isset($_GET['etapa_tab']) ? intval($_GET['etapa_tab']) : 1;
        ?>
        
        <div class="etapas-tabs">
            <a href="<?php echo admin_url('admin.php?page=olc_etapas&oferta_id='.$oferta_id.'&etapa_tab=1'); ?>" 
               class="etapa-tab <?php echo $tab === 1 ? 'active' : ''; ?>">
               Etapa 1
            </a>
        
            <a href="<?php echo admin_url('admin.php?page=olc_etapas&oferta_id='.$oferta_id.'&etapa_tab=2'); ?>" 
               class="etapa-tab <?php echo $tab === 2 ? 'active' : ''; ?>">
               Etapa 2
            </a>
        
            <a href="<?php echo admin_url('admin.php?page=olc_etapas&oferta_id='.$oferta_id.'&etapa_tab=3'); ?>" 
               class="etapa-tab <?php echo $tab === 3 ? 'active' : ''; ?>">
               Etapa 3
            </a>
        </div>
        
        <hr style="margin-top:5px;">


        <?php if ($tab === 1): ?>
        <h2>Etapa 1: Puntuación Automática</h2>

        <div style="display:flex; flex-wrap:wrap; gap:15px;">
            <?php
            // ✅ ETAPA 1: Ordenar por puntaje después de filtrar
            $etapa1 = array_filter($postulantes, fn($p) => $p->estado_postulacion === 'Enviado' || $p->estado_postulacion === 'Evaluado');
            usort($etapa1, fn($a, $b) => floatval($b->puntaje_total) <=> floatval($a->puntaje_total));
            
            if ($etapa1):
                foreach ($etapa1 as $p):
            ?>
            
            
            <?php
            // dentro del foreach ($etapa1 as $p):
            // obtener puntuaciones en mapa criterio => valor/row
            $tabla_p = $wpdb->prefix . 'olc_puntuaciones';
            $detalles = $wpdb->get_results(
                $wpdb->prepare("SELECT criterio, valor, comentario FROM {$tabla_p} WHERE postulacion_id = %d", $p->id)
            );
            $map = [];
            foreach ($detalles as $d) {
                $map[$d->criterio] = $d;
            }
            
            // Valores por defecto si no existen
            $edad_pts = intval($map['edad']->valor ?? 0);
            $profesion_pts = intval($map['profesion_match']->valor ?? 0);
            $experiencia_pts = intval($map['experiencia']->valor ?? 0);
            
            // Para estado bancario priorizamos puntuación en la tabla; si no existe, leemos post_meta y mapear a puntos
            if (isset($map['estado_bancario']->valor)) {
                $banco_pts = intval($map['estado_bancario']->valor);
                $banco_label = !empty($map['estado_bancario']->comentario) ? esc_html($map['estado_bancario']->comentario) : '';
            } else {
                // fallback: leer meta y mapear
                $meta_estado = get_post_meta($p->id, 'estado_bancario', true); // puede ser 'Bueno','Regular','Malo' o ''
                $meta_estado = $meta_estado ? $meta_estado : '';
                if ($meta_estado === 'Bueno') $banco_pts = 60;
                elseif ($meta_estado === 'Regular') $banco_pts = 20;
                elseif ($meta_estado === 'Malo') $banco_pts = 0;
                else $banco_pts = 0;
                $banco_label = $meta_estado;
            }
            ?>
        
        
            <div class="etapa-card 
                <?php 
                    if ($p->puntaje_total <= 60) {
                        echo 'zona-roja';
                    } elseif ($p->puntaje_total <= 90) {
                        echo 'zona-amarilla';
                    } else {
                        echo 'zona-verde';
                    }
                ?>
            ">


                <div class="etapa-card-left">
                    <!-- <input type="radio" class="etapa-radio" /> -->
                    <input type="checkbox" class="etapa-radio" name="postulantes[]" value="<?php echo intval($p->id); ?>">


                    <div>
                        <div class="etapa-name"><?php echo esc_html($p->nombre); ?></div>
                        <div class="etapa-email">📧 <?php echo esc_html($p->email); ?></div>
                        <div class="etapa-telefono">📞 <?php echo esc_html($p->telefono); ?></div>
                    </div>
                </div>
            
                <!-- Detalles de puntajes (mostrar los 3 automáticos) -->
                <div class="etapa-details" style="min-width:320px;">
                    <div><span class="etapa-detail-label">Edad:</span> <?php echo $edad_pts; ?> pts</div>
                    <div><span class="etapa-detail-label">Profesión:</span> <?php echo $profesion_pts; ?> pts</div>
                    <div><span class="etapa-detail-label">Experiencia:</span> <?php echo $experiencia_pts; ?> pts</div>
                    <div><span class="etapa-detail-label">Central de riesgo:</span> <?php echo $banco_pts; ?> pts</div>
                </div>
            
                <!-- Formulario/selector para Estado Bancario (manual) y su puntaje -->
                <div style="min-width:210px; display:flex; flex-direction:column; gap:8px; align-items:flex-end;">
                    
                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="width:100%;">
                        <input type="hidden" name="action" value="olc_guardar_estado_bancario">
                        <input type="hidden" name="postulacion_id" value="<?php echo intval($p->id); ?>">
                        <?php wp_nonce_field('olc_guardar_estado_bancario'); ?>
            
                        <label for="estado_bancario_<?php echo intval($p->id); ?>" style="display:block; font-size: 10px; font-weight:400;margin-bottom:5px;">
                           <i> Puntúación manual de: <strong>Central de riesgo</strong> </i>
                        </label>
            
                        <div style="display:flex; gap:8px; align-items:center;">
                            <select name="estado_bancario" id="estado_bancario_<?php echo intval($p->id); ?>" style="padding:6px;border-radius:6px;">
                                <option value="">Elije puntaje?</option>
                                <option value="Bueno" <?php selected( get_post_meta($p->id, 'estado_bancario', true), 'Bueno' ); ?>>Bueno (60 pts)</option>
                                <option value="Regular" <?php selected( get_post_meta($p->id, 'estado_bancario', true), 'Regular' ); ?>>Regular (20 pts)</option>
                                <option value="Malo" <?php selected( get_post_meta($p->id, 'estado_bancario', true), 'Malo' ); ?>>Malo (0 pts)</option>
                            </select>
            
                            <button class="button button-primary olc-btn-puntaje-banco" type="submit" style="white-space:nowrap;">Guardar</button>
                            
                            
                        </div>
                    </form>
            
                
                </div>
            
                <div class="puntaje-pill">
                    <?php echo intval($p->puntaje_total); ?> pts
                </div>
                
                   <!--<a class="btn-cv" href="<?php echo esc_url(site_url('/?olc_action=view_cv&postulacion_id=' . intval($p->id))); ?>" target="_blank">📄 Ver CV</a>-->
                   
                   <?php if (!empty($p->ccvv_url)): ?>
                        <a class="btn-cv" href="<?php echo esc_url($p->ccvv_url); ?>" target="_blank">📄 Ver CV</a>
                    <?php else: ?>
                        <span class="btn-cv" style="opacity:0.5; cursor:not-allowed;">No CV</span>
                    <?php endif; ?>

                   <a href="<?php echo admin_url('admin-post.php?action=olc_pasar_etapa&etapa=2&id=' . intval($p->id)); ?>" class="btn-etapa">✓ Pasar a Etapa 2</a>
            
                
            </div>
            
            <button id="btn-pasar-etapa-2" class="btn-etapa btn-masivo">✓ Pasar seleccionados a Etapa 2</button>

        </div>
        
        <?php
            endforeach;
        else:
            echo '<p><em>No hay postulantes en esta etapa.</em></p>';
        endif;
        ?>
        </div>
        
        <?php endif; ?>

        <hr>

<!-- Etapa 2-->


        <hr>

        <?php if ($tab === 2): ?>
        <h2>Etapa 2: Entrevistas</h2>
        <div style="display:flex; flex-wrap:wrap; gap:15px;"> 
                <?php 
                    // ✅ ETAPA 2: Ordenar por puntaje después de filtrar
                    $etapa2 = array_filter($postulantes, fn($p) => $p->estado_postulacion === 'Entrevista');
                    usort($etapa2, fn($a, $b) => floatval($b->puntaje_total) <=> floatval($a->puntaje_total));
                    
                    
                    if ($etapa2): 
                        foreach ($etapa2 as $p): 
                        
                        
                    // Determinar zona (roja / amarilla / verde) 
                    if ($p->puntaje_total <= 60) { 
                        $zona = 'zona-roja'; 
                    } elseif ($p->puntaje_total <= 90) { 
                        $zona = 'zona-amarilla';
                    } else { 
                        $zona = 'zona-verde'; 
                    } 
                ?> 
                
                <div class="etapa-card <?php echo $zona; ?>" style="flex-direction:column; gap:10px;"> 
                
                
                <!-- Fila superior: Datos + Estado + Pill -->
                <div class="fila-superior">
                
                    <!-- Datos personales -->
                    <div>
                        <div class="etapa-name" style="margin-bottom:4px;">
                            <?php echo esc_html($p->nombre); ?>
                        </div>
                
                        <div class="etapa-email" style="margin-bottom:8px;">
                            📧 <?php echo esc_html($p->email); ?>
                        </div>
                
                        <div class="etapa-email" style="margin-bottom:8px;">
                            📞 <?php echo esc_html($p->telefono); ?>
                        </div>
                    </div>
                
                    <!-- Estado + Puntaje entrevista -->
                    
                
                        <!-- Estado -->
                        <div>
                            <label style="font-weight:600;">Estado de entrevista</label><br>
                            <select class="olc-estado-entrevista"
                                data-id="<?php echo intval($p->id); ?>"
                                style="width:100%; margin-bottom:8px; margin-top:5px;"
                                <?php 
                                // ✅ BLOQUEAR si ya fue entrevistado Y tiene puntaje asignado
                                if (($p->estado_entrevista ?? '') === 'Entrevistado' && floatval($p->puntaje_entrevista) > 0) {
                                    echo 'disabled';
                                }
                                ?>>
                        
                                <option value="No convocado" 
                                    <?php selected($p->estado_entrevista ?? 'No convocado', 'No convocado'); ?>>
                                    No convocado
                                </option>
                            
                                <option value="Convocado"
                                    <?php selected($p->estado_entrevista ?? '', 'Convocado'); ?>>
                                    Convocado
                                </option>
                            
                                <option value="No asistió"
                                    <?php selected($p->estado_entrevista ?? '', 'No asistió'); ?>>
                                    No asistió
                                </option>
                            
                                <option value="Entrevistado"
                                    <?php selected($p->estado_entrevista ?? '', 'Entrevistado'); ?>>
                                    Entrevistado
                                </option>
                            </select>
                        </div>
                
                        <!-- Puntaje entrevista -->
                        <!-- Campo puntaje (solo visible si estado = Entrevistado) -->
                        <div class="olc-box-puntaje"
                             style="<?php echo (($p->estado_entrevista ?? '') === 'Entrevistado') ? '' : 'display:none;'; ?>"
                             data-id="<?php echo intval($p->id); ?>">
                        
                            <label><strong>Puntaje entrevista:</strong></label><br>
                        
                            <!-- ✅ Contenedor flex para alinear select y botón en la misma línea -->
                            <div style="display:flex; gap:8px; align-items:center; margin-top:5px;">
                                
                                <select class="olc-puntaje-entrevista"
                                        data-id="<?php echo intval($p->id); ?>"
                                        style="flex:1; min-width:0;">
                                    <option value="">- Puntúa -</option>
                                    <option value="100" <?php selected(floatval($p->puntaje_entrevista), 100); ?>>Bueno (100 pts)</option>
                                    <option value="50" <?php selected(floatval($p->puntaje_entrevista), 50); ?>>Regular (50 pts)</option>
                                    <option value="0" <?php selected(floatval($p->puntaje_entrevista), 0); ?>>Malo (0 pts)</option>
                                </select>
                        
                                <button class="button button-primary olc-btn-guardar-puntaje"
                                        data-id="<?php echo intval($p->id); ?>"
                                        style="white-space:nowrap;">
                                    Guardar
                                </button>
                                
                            </div>
                        </div>
                    
                
                    <!-- Pill de puntaje  -->
                    <div class="puntaje-pill"
                        id="puntaje_<?php echo intval($p->id); ?>"
                        style="width:max-content;">
                        <?php 
                        // ✅ Determinar si hay puntaje de entrevista
                        $puntaje_entrevista = floatval($p->puntaje_entrevista ?? 0);
                        $puntaje_total = floatval($p->puntaje_total ?? 0);
                        
                        if ($puntaje_entrevista > 0) {
                            echo $puntaje_total . ' pts (Etapa 1 + 2)';
                        } else {
                            echo $puntaje_total . ' pts (Etapa 1)';
                        }
                        ?>
                    </div>
                
                </div>


                    <!-- Botones alineados --> 
                    
                    <div style="display:flex; gap:10px; flex-wrap:wrap;"> 
                    
                        <!-- Ver CV --> 
                        <?php if (!empty($p->ccvv_url)): ?>
                            <a class="btn-cv" 
                                style="padding:8px 14px;" 
                                href="<?php echo esc_url($p->ccvv_url); ?>" 
                                target="_blank"> 
                                
                                📄 Ver CV 
                            </a> 
                            
                        <?php else: ?> 
                            <span class="btn-cv" style="opacity:0.5; cursor:not-allowed; padding:8px 14px;">
                                No CV 
                            </span> 
                        <?php endif; ?> 
                        
                        <!-- Convocar --> 
                        
                        <a class="btn-whatsapp-etapa" 
                            href="https://wa.me/<?php echo preg_replace('/[^0-9+]/','', $p->telefono); ?>?text=<?php echo rawurlencode("Hola {$p->nombre}, ¡Felicitaciones! Queremos informarte que has avanzado oficialmente a la siguiente etapa del proceso de selección en Financiera Crecer Más. Tu perfil ha sido evaluado y cumple con los requisitos, por lo que participarás en la etapa de entrevista."); ?>" 
                            target="_blank"> 
                            💬 Convocar Entrevista 
                        </a> 
                        
                        
                        <!-- Pasar Etapa 3 --> 
                        
                        <?php 
                        $tiene_puntaje = floatval($p->puntaje_entrevista ?? 0) > 0;
                        if ($tiene_puntaje): 
                        ?>
                            <a class="btn-etapa-pasar" 
                                data-id="<?php echo intval($p->id); ?>"
                                href="<?php echo admin_url('admin-post.php?action=olc_pasar_etapa&etapa=3&id=' . intval($p->id)); ?>"> 
                                ✓ Pasar a Etapa 3 
                            </a>
                        <?php else: ?>
                            <button class="btn-etapa-pasar disabled-etapa-3" 
                                    data-id="<?php echo intval($p->id); ?>"
                                    disabled 
                                    style="opacity:0.5; cursor:not-allowed; background:#ccc !important;"
                                    title="Debe asignar un puntaje de entrevista primero">
                                ⚠️ Sin puntaje
                            </button>
                        <?php endif; ?>
                    </div> 
                </div> 
                
                <?php 
                    endforeach; 
                else: 
                    echo '<p><em>No hay postulantes en esta etapa.</em></p>'; 
                endif; 
                ?>
            </div>


    <?php endif; ?>

<!-- Etapa 3-->

        <hr>

        <?php if ($tab === 3): ?>
        <h2>Etapa 3: Selección Final</h2>
        
        <div style="display:flex; flex-wrap:wrap; gap:15px;">
        <?php

        // ✅ Mostrar todos los que están en etapa 3
        $etapa3 = array_filter($postulantes, fn($p) => intval($p->etapa ?? 0) === 3);

        // ✅ Ordenamiento especial: Ganador primero, luego por puntaje
        usort($etapa3, function($a, $b) {
            $a_es_ganador = ($a->resultado_final ?? '') === 'Ganador';
            $b_es_ganador = ($b->resultado_final ?? '') === 'Ganador';
            
            // Si A es ganador y B no, A va primero
            if ($a_es_ganador && !$b_es_ganador) return -1;
            
            // Si B es ganador y A no, B va primero
            if ($b_es_ganador && !$a_es_ganador) return 1;
            
            // Si ambos son ganadores o ninguno, ordenar por puntaje
            return floatval($b->puntaje_total) <=> floatval($a->puntaje_total);
        });
        
        
        // ✅ VERIFICAR SI YA HAY UN GANADOR
        $ya_hay_ganador = false;
        foreach ($etapa3 as $candidato) {
            if (($candidato->resultado_final ?? '') === 'Ganador') {
                $ya_hay_ganador = true;
                break;
            }
        }
        
        if ($etapa3):
            foreach ($etapa3 as $p):
            
            // Determinar zona (roja / amarilla / verde)
            if ($p->puntaje_total <= 60) {
                $zona = 'zona-roja';
            } elseif ($p->puntaje_total <= 90) {
                $zona = 'zona-amarilla';
            } else {
                $zona = 'zona-verde';
            }
        ?>
        
        <div class="etapa-card <?php echo $zona; ?> 
             <?php 
             $resultado = $p->resultado_final ?? '';
             if ($resultado === 'Ganador') echo 'es-ganador';
             if ($resultado === 'Reserva') echo 'estado-reserva';
             if ($resultado === 'Descartado') echo 'estado-descartado';
             ?>" 
             style="flex-direction:column; gap:10px; position:relative;">
        
            <!-- Fila superior: Datos + Selección + Pill -->
            <div class="fila-superior">
            
                <!-- Datos personales -->
                <div>
                    <div class="etapa-name" style="margin-bottom:4px;">
                        <?php echo esc_html($p->nombre); ?>
                    </div><br>
            
                    <div class="puntaje-pill" style="width:max-content;">
                                    
                        <?php echo intval($p->puntaje_total); ?> pts<br>
                                    
                    </div>
    
                </div>
            
                <!-- Formulario de selección -->
                <div style="min-width:230px;">
                    <form method="post" 
                          action="<?php echo admin_url('admin-post.php'); ?>"
                          class="form-seleccion-final"
                          data-postulacion-id="<?php echo intval($p->id); ?>">
                        
                        <input type="hidden" name="action" value="olc_guardar_seleccion">
                        <input type="hidden" name="id" value="<?php echo intval($p->id); ?>">
                        <?php wp_nonce_field('olc_guardar_seleccion'); ?>
                        
                        <label style="font-weight:600; display:block; margin-bottom:5px;">
                            Resultado final:
                        </label>
                        
                        <select name="resultado_final" 
                                class="select-resultado-final"
                                style="width:100%; margin-bottom:8px;"
                                <?php 
                                // ✅ Bloquear si ya hay ganador Y este no es el ganador
                                if ($ya_hay_ganador && ($p->resultado_final ?? '') !== 'Ganador') {
                                    echo 'disabled';
                                }
                                ?>>
                            <option value="">-- Seleccionar --</option>
                            
                            <?php if (!$ya_hay_ganador || ($p->resultado_final ?? '') === 'Ganador'): ?>
                                <option value="Ganador" <?php selected($p->resultado_final ?? '', 'Ganador'); ?>>
                                    🏆 Ganador
                                </option>
                            <?php endif; ?>
                            
                            <option value="Reserva" <?php selected($p->resultado_final ?? '', 'Reserva'); ?>>
                                ⏸️ Reserva
                            </option>
                            <option value="Descartado" <?php selected($p->resultado_final ?? '', 'Descartado'); ?>>
                                ❌ Descartado
                            </option>
                        </select>
                        <button type="submit" 
                                class="button button-primary olc-btn-puntaje-final"
                                style="width:100%; white-space:nowrap;"
                                <?php 
                                // ✅ Bloquear botón si el select está deshabilitado
                                if ($ya_hay_ganador && ($p->resultado_final ?? '') !== 'Ganador') {
                                    echo 'disabled';
                                }
                                ?>>
                            Guardar selección
                        </button>
                    </form>
                </div>
                <!-- Botones -->
                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    
                <!-- Botón WhatsApp para ganador (solo si es ganador) -->
                <?php if (($p->resultado_final ?? '') === 'Ganador'): ?>
                    <a class="btn-ganador-whatsapp" 
                        href="https://wa.me/<?php echo preg_replace('/[^0-9+]/','', $p->telefono); ?>?text=<?php echo rawurlencode("🎉 ¡FELICITACIONES {$p->nombre}! Has sido seleccionado(a) como GANADOR(A) del proceso de selección en Financiera Crecer Más. Por favor, acércate a nuestras oficinas para continuar con el proceso. ¡Te esperamos!"); ?>" 
                        target="_blank"
                        style="background:#34c759; color:white !important; padding:8px 18px; border-radius:8px; text-decoration:none; font-weight:600; margin-top: 25px;">
                        🎊 Notificar al Ganador
                    </a>
                <?php endif; ?>
                    
                </div>
            </div>
        
        </div>
        
        <?php
            endforeach;
        else:
            echo '<p><em>No hay postulantes en esta etapa.</em></p>';
        endif;
        ?>
        </div>
        
    <?php endif; ?>
  
  
    <?php endif; ?>

</div>


<script>

jQuery(document).ready(function($){
    $('#btn-pasar-etapa-2').on('click', function(e){
        e.preventDefault();

        // Recoger los IDs seleccionados
        var ids = [];
        $('.etapa-radio:checked').each(function(){
            ids.push($(this).val());
        });

        if (ids.length === 0) {
            alert('Selecciona al menos un postulante.');
            return;
        }

        // Construir form dinámico y enviarlo (POST a admin-post.php)
        var form = $('<form/>', {
            method: 'post',
            action: '<?php echo esc_js( admin_url('admin-post.php') ); ?>'
        });

        // action para admin_post hook
        form.append($('<input/>', { type: 'hidden', name: 'action', value: 'olc_pasar_etapa_masivo' }));

        // etapa destino
        form.append($('<input/>', { type: 'hidden', name: 'etapa', value: '2' }));

        // incluir cada id como postulantes[]
        ids.forEach(function(id){
            form.append($('<input/>', { type: 'hidden', name: 'postulantes[]', value: id }));
        });

        // Si en el handler masivo usas nonce, añádelo aquí
        // form.append($('<input/>', { type: 'hidden', name: 'olc_nonce', value: '<?php // echo wp_create_nonce("olc_pasar_etapa_masivo"); ?>' }));

        // anexar y enviar
        $('body').append(form);
        form.submit();
    });
});



jQuery(document).ready(function($){

    // ========================================
    // 1. CAMBIO DE ESTADO
    // ========================================
    $(document).on("change", ".olc-estado-entrevista", function(){
        let id = $(this).data("id");
        let estado = $(this).val();

        // Mostrar/ocultar campo de puntaje
        let box = $('.olc-box-puntaje[data-id="'+id+'"]');
        let input = $('.olc-puntaje-entrevista[data-id="'+id+'"]');

        if (estado === "Entrevistado") {
            box.slideDown();
            input.prop("disabled", false);
        } else {
            box.slideUp();
            input.prop("disabled", true);
        }

        // Guardar en BD
        $.post(ajaxurl, {
            action: "olc_guardar_entrevista_ajax",
            id: id,
            estado_entrevista: estado
        }, function(response){
            if (response.success) {
                console.log("✅ Estado guardado:", estado);
            } else {
                alert("Error: " + (response.data || "No se pudo guardar"));
                console.error("Error:", response);
            }
        }, "json");
    });

    // ========================================
    // 2. GUARDAR PUNTAJE
    // ========================================
    $(document).on("click", ".olc-btn-guardar-puntaje", function(e){
        e.preventDefault();

        let id = $(this).data("id");
        let puntaje = $('.olc-puntaje-entrevista[data-id="'+id+'"]').val();
        let boton = $(this);
        let select_estado = $('.olc-estado-entrevista[data-id="'+id+'"]');

        // Validar que haya seleccionado un puntaje
        if (!puntaje || puntaje === "") {
            alert("⚠️ Por favor selecciona un puntaje antes de guardar");
            return;
        }

        // Deshabilitar botón mientras guarda
        boton.prop("disabled", true).text("Guardando...");

        $.post(ajaxurl, {
            action: "olc_guardar_entrevista_ajax",
            id: id,
            puntaje_entrevista: puntaje
        }, function(response){
            if (response.success && response.data.puntaje_total !== undefined) {
                // Actualizar visualización del puntaje total
                $("#puntaje_" + id).html(response.data.puntaje_total + " pts (Etapa 1 + 2)");
                
                // ✅ REGLA 3: BLOQUEAR el select de estado
                select_estado.prop("disabled", true);
                    
                    
                // ✅ HABILITAR BOTÓN "PASAR A ETAPA 3"
                let botonEtapa3 = $('.btn-etapa-pasar[data-id="'+id+'"]');
                if (botonEtapa3.length) {
                    botonEtapa3.removeClass('disabled-etapa-3');
                    botonEtapa3.prop('disabled', false);
                    botonEtapa3.css({
                        'opacity': '1',
                        'cursor': 'pointer',
                        'background': '#0d6efd'
                    });
                    botonEtapa3.text('✓ Pasar a Etapa 3');
                    
                    // Si es un <button>, convertirlo a <a>
                    if (botonEtapa3.is('button')) {
                        let nuevoEnlace = $('<a/>', {
                            'class': 'btn-etapa-pasar',
                            'href': '<?php echo admin_url("admin-post.php"); ?>?action=olc_pasar_etapa&etapa=3&id=' + id,
                            'text': '✓ Pasar a Etapa 3'
                        });
                        botonEtapa3.replaceWith(nuevoEnlace);
                    }
                }
                
                alert("✅ Puntaje guardado correctamente");
                console.log("✅ Puntaje guardado:", response.data);
            } else {
                alert("Error: " + (response.data || "No se pudo guardar"));
                console.error("Error:", response);
            }
        }, "json")
        .fail(function(){
            alert("❌ Error de conexión");
        })
        .always(function(){
            // Rehabilitar botón
            boton.prop("disabled", false).text("Guardar puntaje");
        });
    });

    // ========================================
    // 3. WHATSAPP → MARCA COMO "CONVOCADO"
    // ========================================
    $(document).on("click", ".btn-whatsapp-etapa", function(e){
        let id = $(this).data("id");
        let select = $('.olc-estado-entrevista[data-id="'+id+'"]');

        // ✅ REGLA 1: Cambiar a "Convocado" automáticamente
        select.val("Convocado");

        // Guardar en BD (background)
        $.post(ajaxurl, {
            action: "olc_guardar_entrevista_ajax",
            id: id,
            estado_entrevista: "Convocado"
        }, function(response){
            if (response.success) {
                console.log("✅ Marcado como Convocado automáticamente");
            } else {
                console.error("Error al guardar:", response);
            }
        }, "json");

        // Ocultar campo de puntaje (porque ya no está en "Entrevistado")
        $('.olc-box-puntaje[data-id="'+id+'"]').slideUp();

        // El enlace de WhatsApp se abrirá automáticamente por el href
    });

});


// ========================================
// VALIDACIONES ETAPA 3
// ========================================

// Confirmación al seleccionar ganador
$(document).on('change', '.select-resultado-final', function(){
    let valor = $(this).val();
    
    if (valor === 'Ganador') {
        let confirmar = confirm('⚠️ ¿Estás seguro de marcar a este postulante como GANADOR?\n\nEsta acción bloqueará la opción de ganador para los demás postulantes.');
        
        if (!confirmar) {
            $(this).val('');
            return false;
        }
    }
});

// Prevenir envío de formulario si no hay selección
$(document).on('submit', '.form-seleccion-final', function(e){
    let select = $(this).find('.select-resultado-final');
    
    if (!select.val() || select.val() === '') {
        e.preventDefault();
        alert('⚠️ Por favor selecciona un resultado antes de guardar.');
        return false;
    }
});


</script>



<style>
.etapas-tabs {
    display: flex;
    gap: 10px;
    margin: 20px 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #e5e7eb;
}

.etapa-tab {
    padding: 10px 22px;
    background: #f4f6f9;
    border-radius: 10px;
    color: #444;
    text-decoration: none;
    font-weight: 500;
    border: 1px solid #dfe3e8;
    transition: 0.2s;
}

.etapa-tab:hover {
    background: #e9edf2;
}

.etapa-tab.active {
    background: #0d6efd;
    color: white;
    border-color: #0d6efd;
}

/* cuerpo de etapas*/

.etapa-card {
    display: flex;
    align-items: flex-start !important;
    justify-content: space-between;
    background: #ffffff;
    text-align: left !important;
    border: 1px solid #dce3ed;
    border-radius: 14px;
    padding:16px 20px !important;
    margin-bottom: 18px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.04);
    gap: 20px;
    width: 100%; 
    box-sizing: border-box;
    
    
}

.etapa-card-left {
    display: flex;
    align-items: center;
    gap: 14px;
}

.etapa-radio {
    width: 18px;
    height: 18px;
}

.etapa-name {
    font-size: 20px;
    margin-bottom: 10px;
    font-weight: 600;
    color: #1a1a1a;
}

.etapa-email {
    color: #555;
    font-size: 13px;
}

.etapa-telefono {
    color: #555;
    font-size: 13px;
}

.etapa-details {
    display: flex;
    gap: 50px;
    font-size: 14px;
    color: #333;
}

.etapa-detail-label {
    font-weight: bold;
}

.btn-cv {
    background: #f9fafb;
    color:#333 !important;
    padding:8px 18px;
    border-radius:8px;
    text-decoration:none;
    font-weight:600;
    
}

.btn-cv:hover {
    background: #f0f4f7;
}

.btn-etapa {
    background: #1f9d4e;
    color: white !important;
    padding: 9px 18px;
    border-radius: 8px;
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
    border-style: none;
}

.puntaje-pill {
    background: #0d6efd;
    color: white;
    font-weight: bold;
    margin-top: 1px;
    padding: 10px 10px;
    border-radius: 20px;
    font-size: 15px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.puntaje-pill:before {
    content: "⭐";
}

.form-estado-banco select {
    background: #fff;
    border: 1px solid #ccc;
}

.form-estado-banco button {
    border-radius: 6px;
}


/* No pasa la balla*/


    /* ----- ZONA ROJA (0 - 60) ----- */
    .zona-roja {
        border: 2px solid #ff3b30 !important;
        background: #ffecec !important;
    }
    .zona-roja .puntaje-pill {
        background: #ff3b30 !important;
        color: white !important;
        font-weight: bold;
    }

    /* ----- ZONA AMARILLA (61 - 90) ----- */
    .zona-amarilla {
        border: 2px solid #ffd60a !important;
        background: #fff8db !important;
    }
    .zona-amarilla .puntaje-pill {
        background: #ffd60a !important;
        color: #5c4a00 !important;
        font-weight: bold;
    }

    /* ----- ZONA VERDE (91 a más) ----- */
    .zona-verde {
        border: 2px solid #34c759 !important;
        background: #e9ffe9 !important;
    }
    .zona-verde .puntaje-pill {
        background: #34c759 !important;
        color: white !important;
        font-weight: bold;
    }


/* Ajustes para etapa 2 integrados en el diseño de etapa 1 */


.btn-whatsapp-etapa {
    background:#25D366;
    color:white !important;
    padding:8px 18px;
    border-radius:8px;
    text-decoration:none;
    font-weight:600;
}

.btn-whatsapp-etapa:hover {
  background: #08d454 !important;
}


.btn-etapa-pasar {
    background:#0d6efd;
    color:white !important;
    padding:8px 18px;
    border-radius:8px;
    text-decoration:none;
    font-weight:600;
}

.btn-etapa-pasar:hover {
  background: #0b5ed7 !important; /* Fondo más oscuro cuando el usuario pasa el mouse */
}

.olc-btn-guardar-puntaje {
  background: #0d6efd !important;
  color: white !important; /* Color del texto blanco */
  border-radius: 8px !important; /* Bordes redondeados */
  font-weight: 600 !important; /* Peso de la fuente en negrita */
  border: none; /* Elimina el borde por defecto del botón */
  cursor: pointer; /* Cambia el cursor cuando pasas el mouse por encima */
  transition: background-color 0.3s ease; /* Transición suave para el fondo al pasar el mouse */
}

/* Estilo al hacer hover sobre el botón */
.olc-btn-guardar-puntaje:hover {
  background: #0b5ed7 !important; /* Fondo más oscuro cuando el usuario pasa el mouse */
}

.olc-btn-puntaje-banco {
  background: #0d6efd !important;
  color: white !important; /* Color del texto blanco */
  border-radius: 8px !important; /* Bordes redondeados */
  font-weight: 600 !important; /* Peso de la fuente en negrita */
  border: none; /* Elimina el borde por defecto del botón */
  cursor: pointer; /* Cambia el cursor cuando pasas el mouse por encima */
  transition: background-color 0.3s ease; /* Transición suave para el fondo al pasar el mouse */
}

/* Estilo al hacer hover sobre el botón */
.olc-btn-puntaje-banco:hover {
  background: #0b5ed7 !important; /* Fondo más oscuro cuando el usuario pasa el mouse */
}

.olc-btn-puntaje-final {
  background: #0d6efd !important;
  color: white !important; /* Color del texto blanco */
  border-radius: 8px !important; /* Bordes redondeados */
  font-weight: 600 !important; /* Peso de la fuente en negrita */
  border: none; /* Elimina el borde por defecto del botón */
  cursor: pointer; /* Cambia el cursor cuando pasas el mouse por encima */
  transition: background-color 0.3s ease; /* Transición suave para el fondo al pasar el mouse */
}

/* Estilo al hacer hover sobre el botón */
.olc-btn-puntaje-final:hover {
  background: #0b5ed7 !important; /* Fondo más oscuro cuando el usuario pasa el mouse */
}

.etapa-card {
    padding:16px 20px !important;
    align-items: flex-start !important;
    text-align: left !important;
    border-radius:14px;
}

.etapa-name {
    font-size:18px;
    font-weight:600;
}

.etapa-email {
    font-size:14px;
    color:#555;
}


.fila-superior {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 25px;
}

.estado-puntaje-wrapper {
    display: flex;
    flex-direction: column;
    gap: 10px;
    min-width: 230px;
}



.puntaje-pill {
    margin-left: auto;
    white-space: nowrap;
}



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

/* ========================================
   ESTILOS ESPECÍFICOS PARA ETAPA 3
======================================== */

.form-seleccion-final select {
    padding: 8px;
    border: 1px solid #dce3ed;
    border-radius: 6px;
    font-size: 14px;
}

.btn-ganador-whatsapp:hover {
    background: #2fb34d !important;
}

/* Badge de estado en la pill cuando es ganador */
.etapa-card .puntaje-pill.ganador {
    background: #ffd700 !important;
    color: #000 !important;
    border: 2px solid #ffaa00;
}

.etapa-card .puntaje-pill.ganador:before {
    content: "🏆";
}



/* ========================================
   ESTADOS VISUALES EN ETAPA 3
======================================== */


.etapa-card.es-ganador {
    border: 3px solid #00FFFF !important;
    background: linear-gradient(135deg, #fffef5 0%, #fff9e6 100%) !important;
    box-shadow: 0 8px 20px rgba(255, 215, 0, 0.3) !important;
    position: relative;
}

.etapa-card.es-ganador:before {
    content: "🏆 GANADOR";
    position: absolute;
    top: -12px;
    left: 20px;
    background: #00FFFF;
    color: #000;
    padding: 4px 12px;
    border-radius: 20px;
    font-weight: bold;
    font-size: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}

.etapa-card.es-ganador .puntaje-pill {
    background: #00FFFF !important;
    color: #000 !important;
    border: 2px solid #00FFFF;
}

/* Reserva - Borde naranja */
.etapa-card.estado-reserva {
    border: 3px solid #ff9500 !important;
    background: #fff8f0 !important;
}

.etapa-card.estado-reserva:after {
    content: "⏸️ RESERVA";
    position: absolute;
    top: 10px;
    right: 10px;
    background: #ff9500;
    color: white;
    padding: 3px 10px;
    border-radius: 12px;
    font-weight: bold;
    font-size: 11px;
}

/* Descartado - Borde rojo */
.etapa-card.estado-descartado {
    border: 3px solid #ff3b30 !important;
    background: #fff0f0 !important;
    opacity: 0.8;
}

.etapa-card.estado-descartado:after {
    content: "❌ DESCARTADO";
    position: absolute;
    top: 10px;
    right: 10px;
    background: #ff3b30;
    color: white;
    padding: 3px 10px;
    border-radius: 12px;
    font-weight: bold;
    font-size: 11px;
}

</style>



