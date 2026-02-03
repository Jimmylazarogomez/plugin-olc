<?php if (!defined('ABSPATH')) exit;
global $wpdb;
$table = $wpdb->prefix . 'olc_ofertas';
$editing = false;
$data = null;
if (!empty($_GET['id'])) {
    $id = intval($_GET['id']);
    $data = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d LIMIT 1", $id));
    if ($data) $editing = true;
}
?>

<div class="wrap">
  <h1><?php echo $editing ? 'Editar Oferta' : 'Nueva Oferta'; ?></h1>

  <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
    <?php wp_nonce_field('olc_save_oferta'); ?>
    <input type="hidden" name="action" value="olc_save_oferta" />
    <?php if ($editing): ?>
      <input type="hidden" name="id" value="<?php echo intval($data->id); ?>" />
    <?php endif; ?>

    <table class="form-table">

      <tr>
        <th><label for="titulo">Nombre del puesto</label></th>
        <td>
            <select name="titulo" id="titulo" class="regular-text" required style="width: 25em;">
                <option value="">-- Selecciona el tipo de puesto --</option>
                <option value="Asesor de crédito" <?php echo ($editing && $data->titulo === 'Asesor de crédito') ? 'selected' : ''; ?>>
                    Asesor de crédito
                </option>
                <option value="Operador de oficina" <?php echo ($editing && $data->titulo === 'Operador de oficina') ? 'selected' : ''; ?>>
                    Operador de oficina
                </option>
                <option value="Personal administrativo" <?php echo ($editing && $data->titulo === 'Personal administrativo') ? 'selected' : ''; ?>>
                    Personal administrativo
                </option>
            </select>
            <p class="description">Selecciona el tipo de puesto para esta oferta laboral.</p>
        </td>
      </tr>

      <tr>
        <th><label for="descripcion">Descripción general</label></th>
        <td><textarea name="descripcion" id="descripcion" rows="4" cols="60" placeholder="Describe brevemente el puesto..."><?php echo $editing ? esc_textarea($data->descripcion) : ''; ?></textarea></td>
      </tr>

      <tr>
        <th><label for="ciudad">Lugar / Agencia</label></th>
        <td>
          <select name="ciudad" id="ciudad" required>
            <?php
              $agencias = ['Aguaytía', 'Atalaya', 'Aucayacu', 'Bagua', 'Bagua Grande', 'Bellavista', 'Campo Verde', 'Chachapollas', 'Chota', 'Cutervo', 'Huancayo', 'Huánuco', 'Jaén', 'Juanjui', 'Moyobamba', 'Nueva Cajamarca', 'Pillcomarca', 'Pucallpa Nueva', 'San Alejandro', 'Tambopata', 'Tocache', 'Yurimaguas'];
              $selected_agencia = $editing ? $data->ciudad : '';
              foreach ($agencias as $agencia) {
                  $sel = selected($selected_agencia, $agencia, false);
                  echo "<option value='{$agencia}' {$sel}>{$agencia}</option>";
              }
            ?>
          </select>
        </td>
      </tr>

      <tr>
        <th><label for="tipo_contrato">Tipo de contrato</label></th>
        <td>
          <select name="tipo_contrato" id="tipo_contrato">
            <?php
              $tipos = ['Tiempo completo', 'Medio tiempo', 'Freelance', 'Practicante', 'Otros'];
              $selected_tipo = $editing ? $data->tipo_contrato : '';
              foreach ($tipos as $tipo) {
                  $sel = selected($selected_tipo, $tipo, false);
                  echo "<option value='{$tipo}' {$sel}>{$tipo}</option>";
              }
            ?>
          </select>
        </td>
      </tr>

      <tr>
        <th><label for="horario">Horario</label></th>
        <td><input class="regular-text" name="horario" id="horario" value="<?php echo $editing ? esc_attr($data->horario) : ''; ?>" placeholder="Ej. Lunes a Viernes 8am - 5pm" /></td>
      </tr>

      <tr>
        <th><label for="perfil">Perfil buscado</label></th>
        <td><textarea name="perfil" id="perfil" rows="3" cols="60"><?php echo $editing ? esc_textarea($data->perfil) : ''; ?></textarea></td>
      </tr>

      <tr>
        <th><label for="profesion_requerida">Profesión requerida</label></th>
        <td>
          <select name="profesion_requerida" id="profesion_requerida" required>
            <optgroup label="APTAS">
              <option value="Administración de Empresas" <?php selected($data->profesion_requerida, 'Administración de Empresas'); ?>>Administración de Empresas</option>
              <option value="Contabilidad y Finanzas" <?php selected($data->profesion_requerida, 'Contabilidad y Finanzas'); ?>>Contabilidad y Finanzas</option>
             <option value="Economía" <?php selected($data->profesion_requerida, 'Economía'); ?>>Economía</option>
             <option value="Administración Bancaria" <?php selected($data->profesion_requerida, 'Administración Bancaria'); ?>>Administración Bancaria</option>
             <option value="Banca y Finanzas" <?php selected($data->profesion_requerida, 'Banca y Finanzas'); ?>>Banca y Finanzas</option>
             <option value="Administración de Negocios Bancarios y Financieros" <?php selected($data->profesion_requerida, 'Administración de Negocios Bancarios y Financieros'); ?>>Administración de Negocios Bancarios y Financieros</option>
             <option value="Computación e Informática" <?php selected($data->profesion_requerida, 'Computación e Informática'); ?>>Computación e Informática</option>
             <option value="Informática Administrativa" <?php selected($data->profesion_requerida, 'Informática Administrativa'); ?>>Informática Administrativa</option>
             <option value="Gestor de Recuperaciones y cobranzas" <?php selected($data->profesion_requerida, 'Gestor de Recuperaciones y cobranzas'); ?>>Gestor de Recuperaciones y cobranzas</option>
             <option value="Analista de Créditos" <?php selected($data->profesion_requerida, 'Analista de Créditos'); ?>>Analista de Créditos</option>
             <option value="Cajero Promotor" <?php selected($data->profesion_requerida, 'Cajero Promotor'); ?>>Cajero Promotor</option>
             <option value="Secretariado Ejecutivo" <?php selected($data->profesion_requerida, 'Secretariado Ejecutivo'); ?>>Secretariado Ejecutivo</option>
           </optgroup>
    
           <optgroup label="INTERMEDIAS">
             <option value="Negocios Internacionales" <?php selected($data->profesion_requerida, 'Negocios Internacionales'); ?>>Negocios Internacionales</option>
             <option value="Marketing" <?php selected($data->profesion_requerida, 'Marketing'); ?>>Marketing</option>
             <option value="Ingeniería Industrial" <?php selected($data->profesion_requerida, 'Ingeniería Industrial'); ?>>Ingeniería Industrial</option>
             <option value="Gestión Comercial" <?php selected($data->profesion_requerida, 'Gestión Comercial'); ?>>Gestión Comercial</option>
             <option value="Ingeniería Empresarial" <?php selected($data->profesion_requerida, 'Ingeniería Empresarial'); ?>>Ingeniería Empresarial</option>
             <option value="Ingeniería Comercial" <?php selected($data->profesion_requerida, 'Ingeniería Comercial'); ?>>Ingeniería Comercial</option>
             <option value="Turismo y Hotelería" <?php selected($data->profesion_requerida, 'Turismo y Hotelería'); ?>>Turismo y Hotelería</option>
             <option value="Administración Hotelera" <?php selected($data->profesion_requerida, 'Administración Hotelera'); ?>>Administración Hotelera</option>
           </optgroup>
    
           <optgroup label="NO APTAS">
             <option value="Medicina" <?php selected($data->profesion_requerida, 'Medicina'); ?>>Medicina</option>
             <option value="Enfermería" <?php selected($data->profesion_requerida, 'Enfermería'); ?>>Enfermería</option>
             <option value="Odontología" <?php selected($data->profesion_requerida, 'Odontología'); ?>>Odontología</option>
             <option value="Arquitectura" <?php selected($data->profesion_requerida, 'Arquitectura'); ?>>Arquitectura</option>
             <option value="Ingeniería Civil" <?php selected($data->profesion_requerida, 'Ingeniería Civil'); ?>>Ingeniería Civil</option>
             <option value="Psicología" <?php selected($data->profesion_requerida, 'Psicología'); ?>>Psicología</option>
             <option value="Mecánica" <?php selected($data->profesion_requerida, 'Mecánica'); ?>>Mecánica</option>
             <option value="Eléctrica" <?php selected($data->profesion_requerida, 'Eléctrica'); ?>>Eléctrica</option>
             <option value="Veterinaria" <?php selected($data->profesion_requerida, 'Veterinaria'); ?>>Veterinaria</option>
             <option value="Gastronomía" <?php selected($data->profesion_requerida, 'Gastronomía'); ?>>Gastronomía</option>
             <option value="Diseño Gráfico" <?php selected($data->profesion_requerida, 'Diseño Gráfico'); ?>>Diseño Gráfico</option>
             <option value="Arte / Música / Danza" <?php selected($data->profesion_requerida, 'Arte / Música / Danza'); ?>>Arte / Música / Danza</option>
             <option value="Derecho" <?php selected($data->profesion_requerida, 'Derecho'); ?>>Derecho</option>
             <option value="Trabajo Social" <?php selected($data->profesion_requerida, 'Trabajo Social'); ?>>Trabajo Social</option>
             <option value="Estadística" <?php selected($data->profesion_requerida, 'Estadística'); ?>>Estadística</option>
             <option value="Educación" <?php selected($data->profesion_requerida, 'Educación'); ?>>Educación</option>
             <option value="Comunicaciones" <?php selected($data->profesion_requerida, 'Comunicaciones'); ?>>Comunicaciones</option>
             <option value="Gestión Pública" <?php selected($data->profesion_requerida, 'Gestión Pública'); ?>>Gestión Pública</option>
           </optgroup>
          </select>
        </td>
      </tr>


      <tr>
        <th><label for="sueldo">Sueldo</label></th>
        <td><input class="regular-text" name="sueldo" id="sueldo" value="<?php echo $editing ? esc_attr($data->sueldo) : ''; ?>" placeholder="Ej. S/ 1800 - 2500" /></td>
      </tr>

      <tr>
        <th><label for="funciones">Funciones y responsabilidades</label></th>
        <td><textarea name="funciones" id="funciones" rows="5" cols="60"><?php echo $editing ? esc_textarea($data->funciones) : ''; ?></textarea></td>
      </tr>

      <tr>
        <th><label for="beneficios">Beneficios</label></th>
        <td><textarea name="beneficios" id="beneficios" rows="3" cols="60"><?php echo $editing ? esc_textarea($data->beneficios) : ''; ?></textarea></td>
      </tr>

      <tr>
        <th><label for="fecha_inicio">Fecha de inicio</label></th>
        <td><input type="date" name="fecha_inicio" id="fecha_inicio" value="<?php echo $editing && !empty($data->fecha_inicio) ? date('Y-m-d', strtotime($data->fecha_inicio)) : ''; ?>" /></td>
      </tr>

      <tr>
        <th><label for="fecha_fin">Fecha fin</label></th>
        <td><input type="date" name="fecha_fin" id="fecha_fin" value="<?php echo $editing && !empty($data->fecha_fin) ? date('Y-m-d', strtotime($data->fecha_fin)) : ''; ?>" /></td>
      </tr>

      <tr>
        <th><label for="estado">Estado</label></th>
        <td>
          <select name="estado" id="estado">
            <option value="borrador" <?php selected($editing ? $data->estado : '', 'borrador'); ?>>Borrador</option>
            <option value="publicada" <?php selected($editing ? $data->estado : '', 'publicada'); ?>>Publicada</option>
            <option value="finalizada" <?php selected($editing ? $data->estado : '', 'finalizada'); ?>>Finalizada</option>
          </select>
        </td>
      </tr>

    </table>

    <?php submit_button($editing ? 'Actualizar Oferta' : 'Crear Oferta'); ?>
  </form>
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

