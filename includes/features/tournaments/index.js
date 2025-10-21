/**
 * includes/features/tournaments/index.js
 * Guardado "Editar torneo" con cookies + anti-caché + retry si el nonce está caducado.
 */
(function () {
  'use strict';

  // --------- Utilidades ----------
  const DBG = (...a) => console.log('[TOURNAMENTS]', ...a);

  function getAjaxConfig() {
    const cfg = window.STR_TOURNAMENTS_AJAX || {};
    if (!cfg.ajax_url || !cfg.nonce) {
      DBG('❌ Config AJAX ausente/incompleta', cfg);
    } else {
      DBG('✅ Config AJAX', cfg);
    }
    return cfg;
  }

  // Crea (si no existe) un input hidden con el nonce dentro del modal.
  function ensureNonceInput(defaultNonce) {
    const modal = document.getElementById('modal-editar-torneo');
    if (!modal) return null;

    let input = modal.querySelector('#str-nonce');
    if (!input) {
      input = document.createElement('input');
      input.type = 'hidden';
      input.id = 'str-nonce';
      input.name = 'str_nonce';
      modal.appendChild(input);
    }
    if (!input.value && defaultNonce) input.value = defaultNonce;
    return input;
  }

  // Normaliza HH:mm (admite "H:m")
  function fixTime(t) {
    if (!t) return '';
    const p = (t + '').split(':');
    if (p.length >= 2) return `${p[0].padStart(2,'0')}:${p[1].padStart(2,'0')}`;
    return '';
  }

  // --------- UI ----------
  function openModal() {
    const m = document.getElementById('modal-editar-torneo');
    if (m) m.style.display = 'flex';
  }
  function closeModal() {
    const m = document.getElementById('modal-editar-torneo');
    if (m) m.style.display = 'none';
  }

  // --------- AJAX helper ----------
  async function postAjax(url, fd) {
    // Anti-caché SW + envío cookies forzado
    const cacheBuster = (url.includes('?') ? '&' : '?') + `_=${Date.now()}`;
    const res = await fetch(url + cacheBuster, {
      method: 'POST',
      body: fd,
      credentials: 'include', // <- cookies SIEMPRE
      cache: 'no-store',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Cache-Control': 'no-store'
      }
    });
    const raw = await res.text();
    let data = null;
    try { data = JSON.parse(raw); } catch (_) {}
    DBG('← RESP', res.status, raw);
    return { res, data, raw };
  }

  async function handleSave(clickBtn) {
    const cfg = getAjaxConfig();
    if (!cfg.ajax_url) {
      alert('No se pudo guardar (config AJAX ausente).');
      return;
    }

    // Aseguramos nonce visible en el modal
    const nonceInput = ensureNonceInput(cfg.nonce);

    // Datos del modal
    const torneoId    = clickBtn.getAttribute('data-torneo-id') || '';
    const titulo      = document.querySelector('#modal-editar-torneo input[name="titulo"]')?.value || '';
    const descripcion = document.querySelector('#modal-editar-torneo textarea[name="descripcion"]')?.value || '';
    const fecha       = document.querySelector('#modal-editar-torneo input[name="fecha_torneo"]')?.value || '';
    const hora_inicio = fixTime(document.querySelector('#modal-editar-torneo input[name="hora_inicio"]')?.value || '');
    const hora_fin    = fixTime(document.querySelector('#modal-editar-torneo input[name="hora_fin"]')?.value || '');

    // Construye payload
    const buildFD = (nonce) => {
      const fd = new FormData();
      fd.append('action',       'str_guardar_torneo_editado');
      fd.append('nonce',        nonce || '');
      fd.append('torneo_id',    torneoId);
      fd.append('titulo',       titulo);
      fd.append('descripcion',  descripcion);
      fd.append('fecha_torneo', fecha);
      fd.append('hora_inicio',  hora_inicio);
      fd.append('hora_fin',     hora_fin);
      return fd;
    };

    let currentNonce = nonceInput?.value || cfg.nonce || '';
    DBG('→ POST (primer intento)', { torneoId, titulo, fecha, hora_inicio, hora_fin, nonce: currentNonce });

    // 1º intento
    let { res, data } = await postAjax(cfg.ajax_url, buildFD(currentNonce));

    // ¿Nonce inválido? reintenta UNA vez con el que devuelva el servidor
    if (res.status === 403 && data && data.success === false && data.data && data.data.code === 'nonce_invalid' && data.data.new_nonce) {
      currentNonce = data.data.new_nonce;
      if (nonceInput) nonceInput.value = currentNonce;
      DBG('↻ Reintentando con nonce fresco…', currentNonce);
      ({ res, data } = await postAjax(cfg.ajax_url, buildFD(currentNonce)));
    }

    // Resultado final
    if (!res.ok) {
      // Mensajes más claros si vienen de nuestro callback
      if (data && data.data && data.data.code === 'auth_required') {
        alert('Tu sesión no está activa. Inicia sesión de nuevo y prueba de nuevo.');
        return;
      }
      if (data && data.data && data.data.message) {
        alert('No se pudo guardar: ' + data.data.message);
        return;
      }
      alert('No se pudo guardar. Status: ' + res.status);
      return;
    }

    if (data && data.success) {
      alert('Torneo actualizado correctamente.');
      closeModal();
      location.reload();
    } else {
      const msg = (data && (data.data?.message || data.message)) || 'Error desconocido';
      alert('No se pudo guardar: ' + msg);
    }
  }

  function bindUI() {
    const btnOpen   = document.getElementById('btn-editar-torneo');
    const btnSave   = document.getElementById('guardar-torneo');
    const btnCancel = document.getElementById('cancelar-editar-torneo');
    const btnX      = document.getElementById('cerrar-modal-editar');

    if (btnOpen)   btnOpen.addEventListener('click', openModal);
    if (btnCancel) btnCancel.addEventListener('click', closeModal);
    if (btnX)      btnX.addEventListener('click', closeModal);
    if (btnSave)   btnSave.addEventListener('click', () => handleSave(btnSave));
  }

  document.addEventListener('DOMContentLoaded', bindUI);
})();
