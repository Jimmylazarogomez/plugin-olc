jQuery(document).ready(function($){
    // Abrir modal (ya hay botón en el HTML)
    $(document).on('click', '#btnAbrirModalPostulante', function(e){
        e.preventDefault();
        $('#modalPostulante').show();
    });

    $(document).on('click', '#cerrarModalPostulante', function(e){
        e.preventDefault();
        $('#modalPostulante').hide();
    });

    // Envío del formulario del postulante (perfil)
    $(document).on('submit', '#formPostulante', function(e){
        e.preventDefault();
        var fd = new FormData(this);
        fd.append('action','olc_guardar_postulante');
        fd.append('security', olc_ajax.nonce);

        $.ajax({
            url: olc_ajax.ajax_url,
            method: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            dataType: 'json'
        }).done(function(resp){
            if (!resp.success) {
                alert(resp.data?.message || 'Error al guardar.');
                return;
            }
            alert(olc_ajax.msg_saved);
            location.reload(); // recarga para actualizar estado
        }).fail(function(){
            alert('Error de comunicación.');
        });
    });

    // Click confirmar postulación
    $(document).on('click', '#btnConfirmarPostulacion', function(e){
        e.preventDefault();

        // oferta_id se puede obtener desde la URL o data attribute; vamos a obtener de query string
        var oferta_id = (new URLSearchParams(window.location.search)).get('oferta_id');
        if (!oferta_id) {
            alert('Oferta inválida.');
            return;
        }

        if (!confirm('¿Confirmas tu postulación a esta oferta?')) return;

        $.post(olc_ajax.ajax_url, {
            action: 'olc_registrar_postulacion',
            oferta_id: oferta_id,
            security: olc_ajax.nonce
        }, function(resp){
            if (!resp.success) {
                alert(resp.data?.message || 'Error al postular.');
                return;
            }
            alert(olc_ajax.msg_postulado);
            // recargar para mostrar estado o redirigir si quieres
            location.reload();
        }, 'json').fail(function(){
            alert('Error de comunicación.');
        });
    });
});
