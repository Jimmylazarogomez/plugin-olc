jQuery(document).ready(function($){
  /* ==========================================================
     1) MANEJO DEL FORMULARIO DE DATOS DEL POSTULANTE
     ========================================================== */
  $('#formPostulante').on('submit', function(e){
    e.preventDefault();
    var form = this;
    var formData = new FormData(form);
    $.ajax({
      url: olc_ajax.ajax_url,
      type: 'POST',
      data: formData,
      processData: false,
      contentType: false,
      success: function(response){
        if(response.success){
          alert(olc_ajax.msg_saved || 'Perfil guardado correctamente.');
          location.reload();
        } else {
          alert(response.data?.message || 'Error al guardar datos');
          console.log(response);
        }
      },
      error: function(xhr, status, err){
        console.log('AJAX error', status, err, xhr.responseText);
        alert('Error en la petición. Revisa la consola (Network).');
      }
    });
  });

  /* ==========================================================
     2) CONFIRMAR POSTULACIÓN 
     ========================================================== 
     ✅ CÓDIGO ANTIGUO DESHABILITADO
     Ahora se maneja directamente en shortcode_detail() con el modal grande
  */
  
  /*
  $('#btnConfirmarPostulacion').on('click', function(e){
    e.preventDefault();
    let btn = this;
    let oferta_id = $('input[name="oferta_id"]').val() || $(btn).data('oferta-id');
    if (!oferta_id) {
      alert('No se encontró el ID de la oferta.');
      return;
    }
    let originalText = $(btn).text();
    $(btn).prop('disabled', true).text('Postulando...');
    let formData = new FormData();
    formData.append('action', 'olc_registrar_postulacion');
    formData.append('oferta_id', oferta_id);
    formData.append('security', olc_ajax.nonce_action);
    $.ajax({
      url: olc_ajax.ajax_url,
      type: 'POST',
      data: formData,
      processData: false,
      contentType: false,
      success: function(response){
        if(response.success){
          alert(olc_ajax.msg_postulado || 'Postulación registrada correctamente.');
          location.reload();
        } else {
          alert(response.data?.message || 'No se pudo registrar la postulación.');
          console.log(response);
          $(btn).prop('disabled', false).text(originalText);
        }
      },
      error: function(xhr, status, err){
        console.log('AJAX error', status, err, xhr.responseText);
        alert('Error en la petición. Revisa la consola.');
        $(btn).prop('disabled', false).text(originalText);
      }
    });
  });
  */
  
});