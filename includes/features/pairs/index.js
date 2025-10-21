// Pairs (multi-selección) – compatible con legacy y nueva plantilla
// - Engancha tanto #btn-abrir-modal-pareja como .js-add-pareja
// - Usa acciones desde str_parejas_ajax_obj.actions con fallback a saas_*
// - Soporta str_parejas_ajax_obj.torneo_id o .post_id
// - Pinta parejas en: 
//     • Ficha de torneo → #tabla-parejas tbody
//     • Modal (si existe) → #tabla-parejas-confirmadas
// - Logs detallados para depuración

(function ($, w) {
  'use strict';

  if (typeof $ === 'undefined') {
    console.error('[PAIRS] jQuery no disponible');
    return;
  }

  // Helpers de supresión (cooperan con /groups/)
  function __isPairsSuppressed() {
    try { return window.__STR_SUPPRESS_PAIRS_MODAL__ && Date.now() < window.__STR_SUPPRESS_PAIRS_MODAL__; } catch(_) { return false; }
  }
  function __insideGroups(el) {
    try { return !!(el && el.closest && el.closest('#str-gestion-grupos')); } catch(_) { return false; }
  }

  $(function () {
    // --- CONFIG -------------------------------------------------------------
    var cfg   = w.str_parejas_ajax_obj || {};
    var AJAX  = cfg.ajax_url || (w.str_ajax_obj && w.str_ajax_obj.ajax_url) || '';
    var NONCE = cfg.nonce    || (w.str_ajax_obj && w.str_ajax_obj.nonce)    || '';
    var TORNEO_ID = parseInt(cfg.torneo_id || cfg.post_id || 0, 10) || 0;

    // Acciones (usar las que vinieron localizadas; si no, fallback a saas_*)
    var ACT = (cfg.actions || {});
    var ACTIONS = {
      BUSCAR:  ACT.buscar  || 'saas_buscar_jugadores',
      LISTAR:  ACT.listar  || 'saas_listar_parejas_multiseleccion',
      GUARDAR: ACT.guardar || 'saas_guardar_pareja_multiseleccion'
    };

    console.log('[PAIRS][BOOT]', { AJAX, NONCE, TORNEO_ID, ACTIONS, cfg });

    // --- SELECTORES (legacy + nuevos) --------------------------------------
    var $btnAbrir     = $('#btn-abrir-modal-pareja');        // legacy
    var $btnAbrirAlt  = $('.js-add-pareja');                 // nuevo
    var $modal        = $('#modal-parejas-multiseleccion');  // modal (si existe)
    var $cerrar       = $('.cerrar-modal-parejas, .saas-tr-modal__close, .saas-tr-modal_close, .js-close-modal');

    // Slots usados en el MODAL (si existe):
    var $tablaJugadores = $('#tabla-jugadores-disponibles');
    var $tablaParejasModal = $('#tabla-parejas-confirmadas');

    // Slot de la FICHA (siempre):
    var $tablaParejasMainBody = $('#tabla-parejas tbody');

    var $selWrap      = $('#jugadores-seleccionados');
    var $btnConfirmar = $('#btn-confirmar-pareja');
    var $formBuscar   = $('#form-buscar-jugadores');
    var $msgParejas   = $('#mensaje-parejas');

    var seleccionados = [];
    var jugadoresCache = {};

    // --- UTIL ---------------------------------------------------------------
    function showModal() {
      // No abrir si suprimido por flujo de grupos
      if (__isPairsSuppressed()) {
        console.warn('[PAIRS] Apertura del modal SUPRIMIDA por flujo de grupos.');
        return;
      }

      if (!$modal.length) {
        console.warn('[PAIRS] Modal #modal-parejas-multiseleccion no está en el DOM. (Solo ficha)');
        return;
      }
      // Normaliza estado abierto + aria
      $modal.css('display', 'flex').attr('aria-modal', 'true').attr('aria-hidden', 'false');
      $('body').addClass('saas-tr-modal-open');
    }
    function hideModal() {
      if ($modal.length) {
        $modal.attr('aria-hidden', 'true').css('display', 'none');
        $('body').removeClass('saas-tr-modal-open');
      }
      limpiarSeleccion();
    }
    function setMsg(msg, type) {
      if (!$msgParejas.length) return;
      $msgParejas.removeClass().addClass(type || '').html(msg).fadeIn();
      setTimeout(function () { $msgParejas.fadeOut(); }, 2200);
    }
    function getNombreJugador(id) {
      return jugadoresCache[id] ? (jugadoresCache[id].nombre + ' ' + jugadoresCache[id].apellido) : '';
    }
    function actualizarCheckboxes() {
      $('.chk-jugador').each(function () {
        var id = parseInt($(this).data('id'), 10);
        if (seleccionados.includes(id)) {
          $(this).prop('checked', true).prop('disabled', false);
        } else if (seleccionados.length >= 2) {
          $(this).prop('disabled', true);
        } else {
          $(this).prop('disabled', false);
        }
      });
    }
    function actualizarParejaEnPreparacion() {
      if (!$selWrap.length) return;
      var html = '';
      if (seleccionados.length === 0) {
        html = '<p>Selecciona dos jugadores para formar una pareja.</p>';
      } else {
        seleccionados.forEach(function (id, idx) {
          html += '<span class="jugador-seleccionado">Jugador ' + (idx + 1) + ': <strong>' + getNombreJugador(id) + '</strong> <button class="btn-quitar-jugador" data-id="' + id + '" aria-label="Quitar jugador">&times;</button></span>';
        });
      }
      $selWrap.html(html);
      if ($btnConfirmar.length) $btnConfirmar.prop('disabled', seleccionados.length !== 2);
    }
    function limpiarSeleccion() {
      seleccionados = [];
      actualizarParejaEnPreparacion();
      actualizarCheckboxes();
    }

    // --- RENDER: Modal ------------------------------------------------------
    function renderParejasModal(parejas) {
      if (!$tablaParejasModal.length) return;
      var html = '<table class="tabla-parejas-confirmadas"><thead><tr><th>#</th><th>Jugador 1</th><th>Jugador 2</th></tr></thead><tbody>';
      if (!parejas || !parejas.length) {
        html += '<tr><td colspan="3" style="color:#888;text-align:center;">Aún no hay parejas confirmadas.</td></tr>';
      } else {
        parejas.forEach(function (p, idx) {
          // Tolerante a claves distintas
          var j1 = p.jugador_1_nombre || p.jugador1 || p.jugador_1 || '';
          var j2 = p.jugador_2_nombre || p.jugador2 || p.jugador_2 || '';
          html += '<tr><td>' + (idx + 1) + '</td><td>' + j1 + '</td><td>' + j2 + '</td></tr>';
        });
      }
      html += '</tbody></table>';
      $tablaParejasModal.html(html);
    }

    // --- RENDER: Ficha Torneo ----------------------------------------------
    function renderParejasMain(parejas) {
      if (!$tablaParejasMainBody.length) return;
      var html = '';

      if (!parejas || !parejas.length) {
        html += '<tr><td colspan="4" style="color:#8aa0c4;text-align:center;padding:14px 8px;">Aún no hay parejas confirmadas.</td></tr>';
      } else {
        parejas.forEach(function (p, idx) {
          var j1 = p.jugador_1_nombre || p.jugador1 || p.jugador_1 || '';
          var j2 = p.jugador_2_nombre || p.jugador2 || p.jugador_2 || '';
          html += '<tr>' +
                    '<td>' + (idx + 1) + '</td>' +
                    '<td>' + j1 + '</td>' +
                    '<td>' + j2 + '</td>' +
                    '<td>—</td>' +
                  '</tr>';
        });
      }

      $tablaParejasMainBody.html(html);
    }

    // --- RENDER: Jugadores disponibles (Modal) ------------------------------
    function renderJugadores(lista) {
      if (!$tablaJugadores.length) return;

      jugadoresCache = {};
      var html = '<table class="tabla-jugadores-disponibles"><thead>' +
        '<tr><th></th><th>Nombre</th><th>Apellido</th><th>Email</th></tr></thead><tbody>';

      if (!lista || !lista.length) {
        html += '<tr><td colspan="4" style="color:#888;text-align:center;">No hay jugadores disponibles.</td></tr>';
      } else {
        lista.forEach(function (jug) {
          jugadoresCache[jug.ID] = jug;
          var checked  = seleccionados.includes(jug.ID) ? 'checked disabled' : '';
          var disabled = (seleccionados.length >= 2 && !seleccionados.includes(jug.ID)) ? 'disabled' : '';
          html += '<tr>' +
                    '<td><input type="checkbox" class="chk-jugador" data-id="' + jug.ID + '" ' + checked + ' ' + disabled + '></td>' +
                    '<td>' + jug.nombre + '</td>' +
                    '<td>' + jug.apellido + '</td>' +
                    '<td>' + jug.email + '</td>' +
                  '</tr>';
        });
      }

      html += '</tbody></table>';
      $tablaJugadores.html(html);
    }

    // --- AJAX ---------------------------------------------------------------
    function cargarParejasConfirmadas() {
      // Si no tenemos ningún destino, no disparamos petición
      if (!$tablaParejasMainBody.length && !$tablaParejasModal.length) return;

      $.ajax({
        url: AJAX,
        method: 'POST',
        data: {
          action: ACTIONS.LISTAR,
          nonce: NONCE,
          torneo_id: TORNEO_ID
        }
      }).done(function (res) {
        var parejas = (res && res.success && res.data && res.data.parejas) ? res.data.parejas : [];
        renderParejasMain(parejas);
        renderParejasModal(parejas);
      }).fail(function () {
        // Mensaje amable en la ficha (si existe)
        if ($tablaParejasMainBody.length) {
          $tablaParejasMainBody.html('<tr><td colspan="4" style="color:#b00;text-align:center;padding:14px 8px;">Error al cargar parejas.</td></tr>');
        }
        if ($tablaParejasModal.length) {
          $tablaParejasModal.html('<p style="color:#b00;">Error al cargar parejas.</p>');
        }
      });
    }

    function cargarJugadoresDisponibles(nombre, apellido, email) {
      if (!$tablaJugadores.length) return;
      $tablaJugadores.html('<p>Cargando jugadores...</p>');

      $.ajax({
        url: AJAX,
        method: 'POST',
        data: {
          action: ACTIONS.BUSCAR,
          nonce: NONCE,
          torneo_id: TORNEO_ID,
          nombre: nombre || '',
          apellido: apellido || '',
          email: email || ''
        }
      }).done(function (res) {
        if (res && res.success) {
          renderJugadores(res.data && res.data.jugadores ? res.data.jugadores : []);
        } else {
          $tablaJugadores.html('<p style="color:#b00;">' + (res && res.data && res.data.mensaje ? res.data.mensaje : 'No se pudo cargar jugadores') + '</p>');
        }
      }).fail(function () {
        $tablaJugadores.html('<p style="color:#b00;">Error al cargar jugadores.</p>');
      });
    }

    function guardarPareja() {
      if (seleccionados.length !== 2) return;
      if (!$btnConfirmar.length) return;

      $btnConfirmar.prop('disabled', true).text('Guardando...');
      $.post(AJAX, {
        action: ACTIONS.GUARDAR,
        nonce: NONCE,
        torneo_id: TORNEO_ID,
        jugador_1: seleccionados[0],
        jugador_2: seleccionados[1]
      }).done(function (res) {
        $btnConfirmar.prop('disabled', false).text('Confirmar pareja');
        if (res && res.success) {
          setMsg('¡Pareja guardada con éxito!', 'success');
          cargarParejasConfirmadas();      // ← refresca ficha + modal
          cargarJugadoresDisponibles();    // ← refresca lista en modal
          limpiarSeleccion();
        } else {
          setMsg((res && res.data && res.data.mensaje) ? res.data.mensaje : 'Error al guardar pareja', 'error');
        }
      }).fail(function () {
        $btnConfirmar.prop('disabled', false).text('Confirmar pareja');
        setMsg('Error de red al guardar pareja', 'error');
      });
    }

    // --- LISTENERS ----------------------------------------------------------
    // Abrir modal
    $btnAbrir.on('click', function (e) {
      if (__isPairsSuppressed() || __insideGroups(e.target)) {
        e.preventDefault();
        console.warn('[PAIRS] Click ignorado (supresión activa o click dentro de grupos).');
        return;
      }
      e.preventDefault();
      showModal();
      cargarParejasConfirmadas();
      cargarJugadoresDisponibles();
    });

    $btnAbrirAlt.on('click', function (e) {
      if (__isPairsSuppressed() || __insideGroups(e.target)) {
        e.preventDefault();
        console.warn('[PAIRS] Click ignorado (supresión activa o click dentro de grupos).');
        return;
      }
      e.preventDefault();
      showModal();
      cargarParejasConfirmadas();
      cargarJugadoresDisponibles();
    });

    // Cerrar modal
    $cerrar.on('click', function (e) { e.preventDefault(); hideModal(); });
    $(document).on('keydown', function (e) {
      if (e.key === 'Escape' && $modal.length && $modal.is(':visible')) hideModal();
    });
    if ($modal.length) {
      $modal.on('click', function (e) {
        if (e.target === this || $(e.target).hasClass('saas-tr-modal_backdrop') || $(e.target).hasClass('saas-tr-modal__backdrop')) {
          hideModal();
        }
      });
    }

    // Buscar jugadores (modal)
    $formBuscar.on('submit', function (e) {
      e.preventDefault();
      var nombre   = $('#busqueda-nombre').val();
      var apellido = $('#busqueda-apellido').val();
      var email    = $('#busqueda-email').val();
      cargarJugadoresDisponibles(nombre, apellido, email);
    });

    // Selección de jugadores (modal)
    $(document).on('change', '.chk-jugador', function () {
      var id = parseInt($(this).data('id'), 10);
      if ($(this).is(':checked')) {
        if (seleccionados.length < 2) { seleccionados.push(id); } else { this.checked = false; }
      } else {
        seleccionados = seleccionados.filter(function (x) { return x !== id; });
      }
      actualizarParejaEnPreparacion();
      actualizarCheckboxes();
    });

    $(document).on('click', '.btn-quitar-jugador', function () {
      var id = parseInt($(this).data('id'), 10);
      seleccionados = seleccionados.filter(function (x) { return x !== id; });
      actualizarParejaEnPreparacion();
      actualizarCheckboxes();
    });

    $btnConfirmar.on('click', function () { guardarPareja(); });

    // --- INIT ---------------------------------------------------------------
    actualizarParejaEnPreparacion();
    cargarParejasConfirmadas(); // ← pinta en la ficha al entrar
    console.log('[PAIRS][READY] Listeners activos');
  });

})(jQuery, window);
