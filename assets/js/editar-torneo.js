document.addEventListener('DOMContentLoaded', function () {
  const btnEditar = document.getElementById('btn-editar-torneo');
  const modal = document.getElementById('modal-editar-torneo');
  const cerrarModal = document.getElementById('cerrar-modal-editar');
  const btnGuardar = document.getElementById('guardar-torneo');

  // Mostrar el modal
  if (btnEditar && modal) {
    btnEditar.addEventListener('click', function (e) {
      e.preventDefault();
      modal.style.display = 'flex';
    });
  }

  // Cerrar el modal con la X
  if (cerrarModal && modal) {
    cerrarModal.addEventListener('click', function () {
      modal.style.display = 'none';
    });
  }

  // Cerrar el modal al hacer clic fuera del contenido
  window.addEventListener('click', function (e) {
    if (e.target === modal) {
      modal.style.display = 'none';
    }
  });

  // Guardar los datos del torneo por AJAX
  if (btnGuardar) {
    btnGuardar.addEventListener('click', function () {
      const torneoID = btnGuardar.dataset.torneoId;
      const titulo = document.querySelector('input[name="titulo"]').value;
      const descripcion = document.querySelector('textarea[name="descripcion"]').value;
      const fecha = document.querySelector('input[name="fecha_torneo"]').value;
      const horaInicio = document.querySelector('input[name="hora_inicio"]').value;
      const horaFin = document.querySelector('input[name="hora_fin"]').value;

      const datos = new FormData();
      datos.append('action', 'str_guardar_torneo_editado');
      datos.append('torneo_id', torneoID);
      datos.append('titulo', titulo);
      datos.append('descripcion', descripcion);
      datos.append('fecha_torneo', fecha);
      datos.append('hora_inicio', horaInicio);
      datos.append('hora_fin', horaFin);

      fetch(str_ajax_obj.ajax_url, {
        method: 'POST',
        body: datos
      })
        .then(res => res.json())
        .then(respuesta => {
          if (respuesta.success) {
            alert('✅ Torneo actualizado correctamente');
            location.reload();
          } else {
            alert('❌ Error: ' + (respuesta.data || 'Intenta de nuevo.'));
            console.error(respuesta);
          }
        })
        .catch(err => {
          alert('❌ Error de red o servidor');
          console.error(err);
        });
    });
  }
});
