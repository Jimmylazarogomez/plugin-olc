    <?php
    if (!defined('ABSPATH')) exit;
    global $wpdb;
    
    $oferta_id = isset($_GET['oferta_id']) ? intval($_GET['oferta_id']) : 0;
    if (!$oferta_id) {
        echo '<div class="notice notice-error"><p>Oferta no especificada.</p></div>';
        return;
    }
    
    $tbl_ofertas = $wpdb->prefix . 'olc_ofertas';
    $tbl_postulaciones = $wpdb->prefix . 'olc_postulaciones';
    $tbl_postulantes = $wpdb->prefix . 'olc_postulantes';
    $tbl_puntuaciones = $wpdb->prefix . 'olc_puntuaciones';
    
    $oferta = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tbl_ofertas} WHERE id = %d", $oferta_id));
    $postulantes = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$tbl_postulaciones} WHERE oferta_id = %d ORDER BY puntaje_total DESC, fecha_postulacion DESC", $oferta_id));
    
    $etapa = isset($_GET['etapa']) ? intval($_GET['etapa']) : 1;
    
    // helper: obtener última puntuación por criterio para una postulacion
    function olc_get_latest_scores($wpdb, $tbl_puntuaciones, $postulacion_id) {
        $sql = $wpdb->prepare("
            SELECT p.* FROM {$tbl_puntuaciones} p
            JOIN (
                SELECT criterio, MAX(id) as mid
                FROM {$tbl_puntuaciones}
                WHERE postulacion_id = %d
                GROUP BY criterio
            ) m ON p.criterio = m.criterio AND p.id = m.mid
            WHERE p.postulacion_id = %d
        ", $postulacion_id, $postulacion_id);
        return $wpdb->get_results($sql);
    }
    
    ?>
    
    <div class="wrap">
      <h1>Gestión de Etapas 1 - <?php echo esc_html($oferta->titulo); ?></h1>
    
      <nav style="margin-bottom:15px;">
        <a class="button <?php echo $etapa==1?'button-primary':''; ?>" href="?page=olc_etapas&oferta_id=<?php echo $oferta_id; ?>&etapa=1">Etapa 1: Postulantes</a>
        <a class="button <?php echo $etapa==2?'button-primary':''; ?>" href="?page=olc_etapas&oferta_id=<?php echo $oferta_id; ?>&etapa=2">Etapa 2: Entrevistas</a>
        <a class="button <?php echo $etapa==3?'button-primary':''; ?>" href="?page=olc_etapas&oferta_id=<?php echo $oferta_id; ?>&etapa=3">Etapa 3: Final</a>
      </nav>
    
      <?php if ($etapa == 1): ?>
        <h2>Etapa 1: Evaluación Automática</h2>
        <div style="display:flex; flex-wrap:wrap; gap:15px;">
          <?php
          $found = false;
          foreach ($postulantes as $p):
              if ($p->etapa && intval($p->etapa) !== 1) continue; // si tu campo 'etapa' existe y filtra
              // consideramos estado_postulacion Enviado/Evaluado para etapa1
              if (!in_array($p->estado_postulacion, array('Enviado','Evaluado','enviado','evaluado'), true)) continue;
    
              $found = true;
              $scores = olc_get_latest_scores($wpdb, $tbl_puntuaciones, $p->id);
              // convertir a array criterio=>valor/row
              $map = [];
              foreach($scores as $s) $map[$s->criterio] = $s;
          ?>
          <div style="border:1px solid #dcdcdc; padding:14px; width:340px; background:#fff; box-shadow:0 1px 2px rgba(0,0,0,0.03);">
            <strong><?php echo esc_html($p->nombre); ?></strong><br>
            <small><?php echo esc_html($p->email); ?> — <?php echo esc_html($p->telefono); ?></small>
            <hr>
            <div style="font-size:14px; margin-bottom:8px;">
                <strong>Desglose de puntuación</strong>
                <ul style="margin:6px 0 0 16px; padding:0;">
                    <li>Edad: <?php echo intval($map['edad']->valor ?? 0); ?> pts <?php echo !empty($map['edad']->comentario) ? ' — '.esc_html($map['edad']->comentario) : ''; ?></li>
                    <li>Estado bancario: <?php echo intval($map['estado_bancario']->valor ?? 0); ?> pts <?php echo !empty($map['estado_bancario']->comentario) ? ' — '.esc_html($map['estado_bancario']->comentario) : ''; ?></li>
                    <li>Profesión (match): <?php echo intval($map['profesion_match']->valor ?? 0); ?> pts <?php echo !empty($map['profesion_match']->comentario) ? ' — '.esc_html($map['profesion_match']->comentario) : ''; ?></li>
                    <li>Experiencia: <?php echo intval($map['experiencia']->valor ?? 0); ?> pts <?php echo !empty($map['experiencia']->comentario) ? ' — '.esc_html($map['experiencia']->comentario) : ''; ?></li>
                </ul>
            </div>
    
            <div style="margin-top:8px;">
                <strong>Total actual:</strong> <?php echo intval($p->puntaje_total); ?> pts
            </div>
    
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="margin-top:10px;">
                <input type="hidden" name="action" value="olc_calcular_puntaje">
                <input type="hidden" name="postulacion_id" value="<?php echo intval($p->id); ?>">
                <?php wp_nonce_field('olc_calcular_puntaje'); ?>
    
                <label style="display:block; margin-top:8px;">Estado Bancario (manual)</label>
                <select name="estado_bancario" style="width:100%; margin-bottom:8px;">
                    <option value="">No asignar</option>
                    <option value="Bueno">Bueno (30)</option>
                    <option value="Regular">Regular (15)</option>
                    <option value="Malo">Malo (0)</option>
                </select>
    
                <div style="display:flex; gap:8px;">
                    <button class="button button-primary" type="submit">Calcular puntaje</button>
                    <a class="button" href="<?php echo admin_url('admin-post.php?action=olc_pasar_etapa&etapa=2&id=' . intval($p->id)); ?>">Pasar a Etapa 2</a>
                    <a class="button" target="_blank" href="<?php echo esc_url(site_url('/?olc_action=view_cv&postulacion_id=' . intval($p->id))); ?>">Ver CVvvv</a>
                </div>
            </form>
    
            <?php if (!empty($map)): ?>
            <div style="margin-top:10px; font-size:12px; color:#666;">
                <strong>Historial (últimos criterios):</strong>
                <ul style="margin:6px 0 0 16px;">
                <?php
                // Mostrar fecha/comentario breve por criterio
                foreach (array('edad','estado_bancario','profesion_match','experiencia') as $c) {
                    if (isset($map[$c])) {
                        echo '<li>' . esc_html($c) . ': ' . intval($map[$c]->valor) . ' pts — ' . esc_html(substr($map[$c]->comentario,0,100)) . ' <em>(' . esc_html($map[$c]->fecha) . ')</em></li>';
                    }
                }
                ?>
                </ul>
            </div>
            <?php endif; ?>
    
          </div>
          <?php endforeach; ?>
    
          <?php if (!$found): ?>
            <p><em>No hay postulantes en Etapa 1 (Enviado/Evaluado).</em></p>
          <?php endif; ?>
        </div>
    
      <?php elseif ($etapa == 2): ?>
    
        <h2>Etapa 2: Entrevistas</h2>
        <!-- Mantener tu implementación actual para Etapa 2 -->
        <table class="widefat">
          <thead><tr><th>Nombre</th><th>Estado Entrevista</th><th>Puntaje</th><th>Acciones</th></tr></thead>
          <tbody>
          <?php foreach($postulantes as $p): if ($p->etapa != 2) continue; ?>
            <tr>
              <td><?php echo esc_html($p->nombre); ?></td>
              <td>
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                  <input type="hidden" name="action" value="olc_actualizar_entrevista">
                  <input type="hidden" name="postulacion_id" value="<?php echo $p->id; ?>">
                  <select name="estado_entrevista">
                    <option value="no_convocado" <?php selected($p->estado_entrevista,'no_convocado'); ?>>No convocado</option>
                    <option value="convocado" <?php selected($p->estado_entrevista,'convocado'); ?>>Convocado</option>
                    <option value="entrevistado" <?php selected($p->estado_entrevista,'entrevistado'); ?>>Entrevistado</option>
                  </select>
                  <input type="number" name="puntaje_entrevista" value="<?php echo intval($p->puntaje_entrevista); ?>" min="0" max="100">
                  <?php wp_nonce_field('olc_actualizar_entrevista'); ?>
                  <button class="button">Guardar</button>
                </form>
              </td>
              <td><?php echo intval($p->puntaje_entrevista); ?>/100</td>
              <td>
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                  <input type="hidden" name="action" value="olc_pasar_etapa">
                  <input type="hidden" name="id" value="<?php echo $p->id; ?>">
                  <input type="hidden" name="etapa" value="3">
                  <?php wp_nonce_field('olc_pasar_etapa'); ?>
                  <button class="button">Pasar a Final</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
    
      <?php elseif ($etapa == 3): ?>
    
        <h2>Etapa 3: Selección Final</h2>
        <!-- Mantener tu implementación actual para Etapa 3 -->
        <table class="widefat">
          <thead><tr><th>Nombre</th><th>Total</th><th>Selección</th></tr></thead>
          <tbody>
          <?php foreach($postulantes as $p): if ($p->etapa != 3) continue; ?>
            <tr>
              <td><?php echo esc_html($p->nombre); ?></td>
              <td><?php echo intval($p->puntaje_total + intval($p->puntaje_entrevista)); ?> pts</td>
              <td>
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                  <input type="hidden" name="action" value="olc_actualizar_final">
                  <input type="hidden" name="postulacion_id" value="<?php echo $p->id; ?>">
                  <select name="resultado_final">
                    <option value="ganador" <?php selected($p->resultado_final,'ganador'); ?>>Ganador</option>
                    <option value="reserva" <?php selected($p->resultado_final,'reserva'); ?>>Reserva</option>
                    <option value="descartado" <?php selected($p->resultado_final,'descartado'); ?>>Descartado</option>
                  </select>
                  <?php wp_nonce_field('olc_actualizar_final'); ?>
                  <button class="button">Guardar</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
    
      <?php endif; ?>
    </div>
