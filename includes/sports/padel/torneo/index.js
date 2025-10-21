/**
 * TORNEOS · Editar (abrir/cerrar y guardar)
 * Depende de que el PHP localice `saas_tr_ajax` con { ajax_url, nonce }
 */
(function () {
  function ready(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn, { once: true });
    } else {
      fn();
    }
  }

  ready(function () {
    var btnEditar   = document.getElementById('btn-editar-torneo');
    var modal       = document.getElementById('modal-editar-torneo');
    var btnCerrar   = document.getElementById('cerrar-modal-editar');
    var btnGuardar  = document.getElementById('guardar-torneo');

    if (!modal || !btnEditar || !btnGuardar) return;

    var ajax      = (window.saas_tr_ajax || {});                 // <— objeto global LOCALIZADO
    var ajaxUrl   = ajax.ajax_url || window.ajaxurl || '/wp-admin/admin-ajax.php';
    var ajaxNonce = ajax.nonce || '';                            // <— nonce que valida PHP

    // Abrir
    btnEditar.addEventListener('click', function (e) {
      e.preventDefault();
      modal.style.display = 'flex';
      document.documentElement.classList.add('saas-tr-modal-open');
      document.body.classList.add('saas-tr-modal-open');
    });

    // Cerrar (X)
    if (btnCerrar) {
      btnCerrar.addEventListener('click', function () {
        modal.style.display = 'none';
        document.documentElement.classList.remove('saas-tr-modal-open');
        document.body.classList.remove('saas-tr-modal-open');
      });
    }

    // Cerrar al fondo
    modal.addEventListener('click', function (e) {
      if (e.target === modal) {
        modal.style.display = 'none';
        document.documentElement.classList.remove('saas-tr-modal-open');
        document.body.classList.remove('saas-tr-modal-open');
      }
    });

    // Guardar
    btnGuardar.addEventListener('click', function () {
      var torneoId   = btnGuardar.getAttribute('data-torneo-id');
      if (!torneoId) { alert('ID de torneo no encontrado.'); return; }

      var titulo      = modal.querySelector('input[name="titulo"]')?.value || '';
      var descripcion = modal.querySelector('textarea[name="descripcion"]')?.value || '';
      var fecha       = modal.querySelector('input[name="fecha_torneo"]')?.value || '';
      var horaInicio  = modal.querySelector('input[name="hora_inicio"]')?.value || '';
      var horaFin     = modal.querySelector('input[name="hora_fin"]')?.value || '';

      var fd = new FormData();
      fd.append('action', 'saas_tr_torneo_guardar'); // <— DEBE coincidir con el add_action wp_ajax_...
      fd.append('_ajax_nonce', ajaxNonce);           // <— DEBE coincidir con el check_ajax_referer del PHP
      fd.append('torneo_id', torneoId);
      fd.append('titulo', titulo);
      fd.append('descripcion', descripcion);
      fd.append('fecha_torneo', fecha);
      fd.append('hora_inicio', horaInicio);
      fd.append('hora_fin', horaFin);

      btnGuardar.disabled = true;

      fetch(ajaxUrl, { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          if (res && res.success) {
            window.location.reload();
          } else {
            var msg = (res && res.data) ? res.data : 'No se pudo guardar.';
            alert('❌ ' + msg);
            console.error('[TORNEO][GUARDAR] Error:', res);
          }
        })
        .catch(function (err) {
          alert('❌ Error de red/servidor.');
          console.error('[TORNEO][GUARDAR] Fetch error:', err);
        })
        .finally(function () { btnGuardar.disabled = false; });
    });
  });
})();
