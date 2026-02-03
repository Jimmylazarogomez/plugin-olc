<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap">
  <h1>Ajustes OLC</h1>
  <form method="post" action="options.php">
    <?php settings_fields('olc_settings_group'); do_settings_sections('olc_settings_group'); ?>
    <table class="form-table">
      <tr><th>Tamaño máximo CCVV (MB)</th><td><input type="number" name="olc_ccvv_max_mb" value="<?php echo esc_attr(get_option('olc_ccvv_max_mb',5)); ?>"></td></tr>
      <tr><th>Retención CCVV (días)</th><td><input type="number" name="olc_ccvv_retention_days" value="<?php echo esc_attr(get_option('olc_ccvv_retention_days',60)); ?>"></td></tr>
      <tr><th>Modo WhatsApp</th><td><select name="olc_wa_mode"><option value="wame" <?php selected(get_option('olc_wa_mode'),'wame'); ?>>wa.me</option><option value="cloud_api" <?php selected(get_option('olc_wa_mode'),'cloud_api'); ?>>Cloud API</option></select></td></tr>
    </table>
    <?php submit_button(); ?>
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
