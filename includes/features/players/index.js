// Players (invitación) – compatible con legacy
// (A1) Eliminado el disparador alternativo para evitar colisiones con otros botones.
// Ahora SOLO abre el modal el botón #btn-abrir-modal-invitacion-jugador

(function (w, d) {
  'use strict';

  function qs(sel, ctx) { return (ctx || d).querySelector(sel); }
  function qsa(sel, ctx) { return Array.prototype.slice.call((ctx || d).querySelectorAll(sel)); }

  function boot() {
    var cfg     = w.str_players_ajax_obj || {};
    var AJAX    = (cfg.ajax_url) || (w.str_ajax_obj && w.str_ajax_obj.ajax_url) || '';
    var NONCE   = (cfg.nonce)    || (w.str_ajax_obj && w.str_ajax_obj.nonce)    || '';
    var TORNEO_ID = parseInt(cfg.post_id || cfg.torneo_id || 0, 10) || 0;

    var ACT = (cfg.actions || {});
    var ACTIONS = {
      CARGAR_MODAL: ACT.cargar_modal || 'saas_cargar_modal_invitacion',
      FORM_AUTO:    ACT.form_auto    || 'saas_invitacion_automatica',
      FORM_MANUAL:  ACT.form_manual  || 'saas_invitacion_manual',
      ENVIAR_AUTO:  ACT.enviar_auto  || 'saas_invitacion_enviar',
      ENVIAR_MAN:   ACT.enviar_manual|| 'saas_invitacion_enviar_manual',
      REGISTRO:     ACT.registro_token || 'saas_registro_jugador'
    };

    console.log('[PLAYERS][BOOT]', { AJAX, NONCE, TORNEO_ID, ACTIONS, cfg });

    // ─────────────────────────────────────────────────────────
    // (A1) Solo este disparador: botón legacy del modal
    // ─────────────────────────────────────────────────────────
    var btnLegacy = d.getElementById('btn-abrir-modal-invitacion-jugador'); // único disparador

    function abrirModalInvitacion(e) {
      if (e) e.preventDefault();

      var overlay = d.getElementById('modal-invitacion-jugador-overlay');
      if (overlay) {
        overlay.style.display = 'flex';
        d.body.classList.add('modal-abierto-invitacion-jugador');
        return;
      }

      // Cargar modal por AJAX
      fetch(AJAX, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          action: ACTIONS.CARGAR_MODAL,
          torneo_id: String(TORNEO_ID || ''),
          nonce: NONCE
        })
      })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (data && data.success && data.data && data.data.html) {
          d.body.insertAdjacentHTML('beforeend', data.data.html);
          iniciarEventosModal();
        } else {
          alert('No se pudo cargar el formulario de invitación. Intenta de nuevo.');
          console.warn('[PLAYERS] Respuesta inválida al cargar modal:', data);
        }
      })
      .catch(function (err) {
        alert('Error de red al cargar el modal.');
        console.error('[PLAYERS] Error AJAX cargar modal:', err);
      });
    }

    function iniciarEventosModal() {
      var overlay = d.getElementById('modal-invitacion-jugador-overlay');
      var btnCerrar = d.getElementById('cerrar-modal-invitacion-jugador');
      var btnAuto = d.getElementById('btn-seleccionar-automatica');
      var btnManual = d.getElementById('btn-seleccionar-manual');
      var contSel = qs('.modal-invitacion-seleccion');
      var contDyn = d.getElementById('modal-invitacion-jugador-dinamico');

      if (overlay) {
        overlay.style.display = 'flex';
        d.body.classList.add('modal-abierto-invitacion-jugador');
      }

      if (btnCerrar && overlay) {
        btnCerrar.addEventListener('click', function () {
          overlay.style.display = 'none';
          d.body.classList.remove('modal-abierto-invitacion-jugador');
          if (contSel) contSel.style.display = 'flex';
          if (contDyn) contDyn.innerHTML = '';
        });
      }

      if (overlay) {
        overlay.addEventListener('click', function (e) {
          if (e.target === overlay) {
            overlay.style.display = 'none';
            d.body.classList.remove('modal-abierto-invitacion-jugador');
            if (contSel) contSel.style.display = 'flex';
            if (contDyn) contDyn.innerHTML = '';
          }
        });
      }

      if (btnAuto && contSel && contDyn) {
        btnAuto.addEventListener('click', function () {
          contSel.style.display = 'none';
          fetch(AJAX, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
              action: ACTIONS.FORM_AUTO,
              torneo_id: String(TORNEO_ID || '')
            })
          })
          .then(function (res) { return res.json(); })
          .then(function (data) {
            if (data && data.success && data.data && data.data.html) {
              contDyn.innerHTML = data.data.html;
              var inp = d.getElementById('inv-torneo-id');
              if (inp && TORNEO_ID) inp.value = String(TORNEO_ID);
              if (typeof w.strIniciarFormularioInvitacion === 'function') {
                w.strIniciarFormularioInvitacion(ACTIONS, AJAX, NONCE);
              }
            } else {
              contDyn.innerHTML = '<div style="color:red;text-align:center;">Error al cargar el formulario.</div>';
            }
          })
          .catch(function () {
            contDyn.innerHTML = '<div style="color:red;text-align:center;">Error de red al cargar el formulario.</div>';
          });
        });
      }

      if (btnManual && contSel && contDyn) {
        btnManual.addEventListener('click', function () {
          contSel.style.display = 'none';
          fetch(AJAX, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
              action: ACTIONS.FORM_MANUAL,
              torneo_id: String(TORNEO_ID || '')
            })
          })
          .then(function (res) { return res.json(); })
          .then(function (data) {
            if (data && data.success && data.data && data.data.html) {
              contDyn.innerHTML = data.data.html;
              var inp = d.getElementById('inv-torneo-id-manual');
              if (inp && TORNEO_ID) inp.value = String(TORNEO_ID);
              if (typeof w.strIniciarFormularioInvitacionManual === 'function') {
                w.strIniciarFormularioInvitacionManual(ACTIONS, AJAX, NONCE);
              }
            } else {
              contDyn.innerHTML = '<div style="color:red;text-align:center;">Error al cargar el formulario manual.</div>';
            }
          })
          .catch(function () {
            contDyn.innerHTML = '<div style="color:red;text-align:center;">Error de red al cargar el formulario manual.</div>';
          });
        });
      }
    }

    // Exponer helpers de envío para que usen las acciones correctas
    w.strIniciarFormularioInvitacion = function (ACTIONS_FROM_PARENT, AJAX_URL, NONCE_TOKEN) {
      var ACTIONS2 = ACTIONS_FROM_PARENT || ACTIONS;
      var AJAX2 = AJAX_URL || AJAX;
      var NONCE2 = NONCE_TOKEN || NONCE;

      var form = d.getElementById('form-invitacion-jugador');
      var mensaje = d.getElementById('mensaje-invitacion-jugador');
      var btnEnviar = d.getElementById('btn-enviar-invitacion-jugador');

      if (!form) { console.log('[PLAYERS][FORM AUTO] No encontrado'); return; }

      form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (mensaje) mensaje.textContent = '';

        var nombre = form.querySelector('[name="nombre"]').value.trim();
        var apellidos = form.querySelector('[name="apellidos"]').value.trim();
        var email = form.querySelector('[name="email"]').value.trim();
        var telefono = form.querySelector('[name="telefono"]').value.trim();
        var mensajeTxt = form.querySelector('[name="mensaje"]').value.trim();
        var asunto = form.querySelector('[name="asunto"]') ? form.querySelector('[name="asunto"]').value.trim() : '';
        var torneo_id = form.querySelector('[name="torneo_id"]').value;

        if (!nombre || !apellidos || !email || !asunto) {
          if (mensaje) mensaje.textContent = 'Nombre, apellidos, email y asunto son obligatorios.';
          return;
        }

        btnEnviar.disabled = true;
        btnEnviar.textContent = 'Enviando...';

        var fd = new FormData();
        fd.append('action', ACTIONS2.ENVIAR_AUTO);
        fd.append('nombre', nombre);
        fd.append('apellidos', apellidos);
        fd.append('email', email);
        fd.append('telefono', telefono);
        fd.append('asunto', asunto);
        fd.append('mensaje', mensajeTxt);
        fd.append('torneo_id', torneo_id);
        fd.append('nonce', NONCE2);

        fetch(AJAX2, { method: 'POST', body: fd })
        .then(function (res) { return res.json(); })
        .then(function (data) {
          btnEnviar.disabled = false;
          btnEnviar.textContent = 'Enviar invitación';
          if (data && data.success) {
            if (mensaje) mensaje.textContent = 'Invitación enviada correctamente.';
            form.reset();
          } else {
            if (mensaje) mensaje.textContent = (data && data.data && data.data.mensaje) ? data.data.mensaje : 'Error al enviar la invitación.';
          }
        })
        .catch(function (err) {
          btnEnviar.disabled = false;
          btnEnviar.textContent = 'Enviar invitación';
          if (mensaje) mensaje.textContent = 'Error de red. Intenta de nuevo.';
          console.error('[PLAYERS][FORM AUTO] Error:', err);
        });
      });
    };

    w.strIniciarFormularioInvitacionManual = function (ACTIONS_FROM_PARENT, AJAX_URL, NONCE_TOKEN) {
      var ACTIONS2 = ACTIONS_FROM_PARENT || ACTIONS;
      var AJAX2 = AJAX_URL || AJAX;
      var NONCE2 = NONCE_TOKEN || NONCE;

      var form = d.getElementById('form-invitacion-jugador-manual');
      var mensaje = d.getElementById('mensaje-invitacion-jugador-manual');
      var btnEnviar = d.getElementById('btn-enviar-invitacion-jugador-manual');

      if (!form) { console.log('[PLAYERS][FORM MANUAL] No encontrado'); return; }

      form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (mensaje) mensaje.textContent = '';

        var nombre = form.querySelector('[name="nombre"]').value.trim();
        var apellidos = form.querySelector('[name="apellidos"]').value.trim();
        var email = form.querySelector('[name="email"]').value.trim();
        var telefono = form.querySelector('[name="telefono"]').value.trim();
        var torneo_id = form.querySelector('[name="torneo_id"]').value;

        if (!nombre || !apellidos || !email) {
          if (mensaje) mensaje.textContent = 'Nombre, apellidos y email son obligatorios.';
          return;
        }

        btnEnviar.disabled = true;
        btnEnviar.textContent = 'Guardando...';

        var fd = new FormData();
        fd.append('action', ACTIONS2.ENVIAR_MAN);
        fd.append('nombre', nombre);
        fd.append('apellidos', apellidos);
        fd.append('email', email);
        fd.append('telefono', telefono);
        fd.append('torneo_id', torneo_id);
        fd.append('nonce', NONCE2);

        fetch(AJAX2, { method: 'POST', body: fd })
        .then(function (res) { return res.json(); })
        .then(function (data) {
          btnEnviar.disabled = false;
          btnEnviar.textContent = 'Guardar jugador';
          if (data && data.success) {
            if (mensaje) mensaje.textContent = 'Jugador guardado correctamente.';
            form.reset();
          } else {
            if (mensaje) mensaje.textContent = (data && data.data && data.data.mensaje) ? data.data.mensaje : 'Error al guardar el jugador.';
          }
        })
        .catch(function (err) {
          btnEnviar.disabled = false;
          btnEnviar.textContent = 'Guardar jugador';
          if (mensaje) mensaje.textContent = 'Error de red. Intenta de nuevo.';
          console.error('[PLAYERS][FORM MANUAL] Error:', err);
        });
      });
    };

    // ─────────────────────────────────────────────────────────
    // Listeners (solo el botón legítimo)
    // ─────────────────────────────────────────────────────────
    if (btnLegacy) btnLegacy.addEventListener('click', abrirModalInvitacion);

    console.log('[PLAYERS][READY] Listener activo (legacy only)');
  }

  if (d.readyState === 'loading') {
    d.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})(window, document);
