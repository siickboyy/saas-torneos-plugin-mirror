/** Grupos – Frontend (gestión simple)
 * Requiere que el PHP haya localizado window.str_groups_ajax_obj (ver enqueue).
 * Este fichero:
 *  - Carga grupos + parejas libres
 *  - Crea grupos (modal; con fallback a prompt)
 *  - Asigna/Quita parejas en grupos
 *  - Recalcula standings
 */

/* ────────────────────────────────────────────────────────────────
   SHIM: crea window.strTorneo a partir de str_groups_ajax_obj
   ──────────────────────────────────────────────────────────────── */
(function (w) {
  if (w.strTorneo) return;
  var S = w.str_groups_ajax_obj || {};
  if (!S || !S.ajax_url || !S.post_id) return;
  var A = (S.actions || {});
  w.strTorneo = {
    ajaxUrl: S.ajax_url,
    nonce:  S.nonce || '',
    competicionId: parseInt(S.post_id, 10) || 0,
    actions: {
      cargar:    A.cargar    || 'saas_grupos_cargar',
      aleatorio: A.aleatorio || 'saas_grupos_aleatorio',
      asignar:   A.asignar   || 'saas_grupo_asignar',
      standings: A.standings || 'saas_grupos_standings',
      bracket:   A.bracket   || 'saas_bracket_volcar',
      crear:     A.crear     || 'saas_grupo_crear',
      quitar:    A.quitar    || 'saas_grupo_quitar'
    }
  };
})(window);

(function () {
  "use strict";

  // ---- GUARD: evita doble inicialización si el script se carga dos veces ----
  if (window.__STR_GROUPS_BOOTED__) { try { console.warn('[GROUPS] Ya inicializado; salto.'); } catch(e) {} return; }
  window.__STR_GROUPS_BOOTED__ = true;

  // ────────────────────────────────────────────────────────────
  // Utilidades / Config
  // ────────────────────────────────────────────────────────────
  const cfg = (window && window.strTorneo) ? window.strTorneo : {};
  const AJAX = cfg.ajaxUrl || (window.ajaxurl || "/wp-admin/admin-ajax.php");
  const NONCE = cfg.nonce || "";
  const COMP_ID = parseInt(cfg.competicionId || 0, 10);
  const ACTIONS = Object.assign({
    crear:     'saas_grupo_crear',
    cargar:    'saas_grupos_cargar',
    asignar:   'saas_grupo_asignar',
    quitar:    'saas_grupo_quitar',
    aleatorio: 'saas_grupos_aleatorio',
    standings: 'saas_grupos_standings',
    bracket:   'saas_bracket_volcar',
  }, (cfg.actions || {}));

  const qs  = (sel) => document.querySelector(sel);
  const qsa = (sel) => Array.from(document.querySelectorAll(sel));

  // --- Supresión temporal del modal de "parejas" cuando operamos en grupos ---
  function suppressPairsModal(ms = 4000) {
    try { window.__STR_SUPPRESS_PAIRS_MODAL__ = Date.now() + ms; } catch(_) {}
  }
  function isPairsSuppressed() {
    try { return window.__STR_SUPPRESS_PAIRS_MODAL__ && Date.now() < window.__STR_SUPPRESS_PAIRS_MODAL__; } catch(_) { return false; }
  }

  function encode(data) {
    return Object.keys(data)
      .map(k => encodeURIComponent(k) + "=" + encodeURIComponent(data[k] == null ? "" : data[k]))
      .join("&");
  }

  async function postAjax(action, data = {}) {
    const payload = Object.assign({}, data, {
      action,
      nonce: NONCE,
      _ajax_nonce: NONCE
    });

    const res = await fetch(AJAX, {
      method: "POST",
      credentials: "same-origin",
      headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
      body: encode(payload),
    });

    try {
      const json = await res.json();
      if (json && json.success) return json.data;
      const msg = (json && (json.data?.message || json.message)) || "Error AJAX (success=false)";
      throw new Error(msg);
    } catch (e) {
      let raw = "";
      try { raw = await res.text(); } catch (_){}
      if (!(e instanceof Error && e.message === "Unexpected end of JSON input")) {
        console.error("[GRUPOS] AJAX error:", e.message, "RAW:", raw);
        throw e;
      }
      console.error("[GRUPOS] Respuesta no JSON:", raw);
      throw new Error("Respuesta no válida del servidor (no JSON).");
    }
  }

  window.__STR_GROUPS_POST__ = postAjax;

  function htmlEscape(str){
    if(str==null) return "";
    return String(str).replace(/[&<>"']/g, m=>({ "&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#039;" }[m]));
  }

  // ⚠️ NUEVO HELPER: marcar error en el <select> sin usar alert()
  function showSelectError(sel, message = "Selecciona una pareja libre.") {
    if (!sel) return;
    const wrap = sel.closest('.str-card-foot') || sel.parentElement;

    sel.classList.add('str-field-error');
    sel.setAttribute('aria-invalid', 'true');

    let msg = wrap && wrap.querySelector('.str-inline-error');
    if (!msg && wrap) {
      msg = document.createElement('div');
      msg.className = 'str-inline-error';
      msg.textContent = message;
      wrap.appendChild(msg);
    } else if (msg) {
      msg.textContent = message;
    }

    sel.focus();

    setTimeout(() => {
      if (msg && msg.parentNode) msg.parentNode.removeChild(msg);
      sel.classList.remove('str-field-error');
      sel.removeAttribute('aria-invalid');
    }, 2200);
  }

  // ────────────────────────────────────────────────────────────
  // Estado
  // ────────────────────────────────────────────────────────────
  const state = {
    meta: { n_grupos: 0, n_parejas: 0, fase_final: "", modo_final: "" },
    grupos: [],       // [{id, letra, tam, participantes:[{id,title,puntos?}]}]
    libres: [],       // [{id,title}]
    loading: false
  };

  // ────────────────────────────────────────────────────────────
  // (OPCIÓN A) Declaración HOISTED del submit del modal
  // ────────────────────────────────────────────────────────────
  async function submitCreateModal() {
    const btn  = document.getElementById('str-modal-create-btn');
    const err  = document.getElementById('str-modal-err');
    const name = (document.getElementById('str-modal-name')?.value || '').trim();

    if (!COMP_ID) {
      if (err) err.textContent = 'Competición inválida.', err.classList.add('show');
      return;
    }

    try {
      if (btn) btn.disabled = true, btn.textContent = 'Creando…';
      await postAjax(ACTIONS.crear || 'saas_grupo_crear', { competicion_id: COMP_ID, nombre: name });
      await Promise.all([
        (typeof cargarGrupos === 'function' ? cargarGrupos() : Promise.resolve()),
        (typeof cargarStandings === 'function' ? cargarStandings() : Promise.resolve()),
      ]);
      closeCreateModal();
    } catch (e) {
      if (err) {
        err.textContent = (e && e.message) ? e.message : 'No se pudo crear el grupo.';
        err.classList.add('show');
      } else {
        alert(e && e.message ? e.message : 'No se pudo crear el grupo.');
      }
    } finally {
      if (btn) btn.disabled = false, btn.textContent = 'Crear';
    }
  }

  // ────────────────────────────────────────────────────────────
  // Modal "Crear grupo"
  // ────────────────────────────────────────────────────────────
  function ensureCreateModal() {
    if (document.getElementById('str-modal-create-group')) return;

    const overlay = document.createElement('div');
    overlay.className = 'str-modal-overlay';
    overlay.id = 'str-modal-overlay';

    const modal = document.createElement('div');
    modal.className = 'str-modal';
    modal.id = 'str-modal-create-group';
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');
    modal.setAttribute('aria-labelledby', 'str-modal-create-title');

    modal.innerHTML = `
      <div class="str-modal-dialog" role="document">
        <div class="str-modal-head">
          <h3 class="str-modal-title" id="str-modal-create-title">Crear grupo</h3>
        </div>
        <div class="str-modal-body">
          <form id="str-modal-create-form">
            <div class="str-field">
              <label for="str-modal-name">Nombre del grupo <span class="str-muted">(opcional, ej. A, B, C)</span></label>
              <input type="text" id="str-modal-name" name="nombre" placeholder="A, B, C…" autocomplete="off" />
              <div id="str-modal-err" class="str-error"></div>
            </div>
            <input type="hidden" id="str-modal-capacity" name="capacidad" value="" />
          </form>
        </div>
        <div class="str-modal-foot">
          <button type="button" class="str-btn" id="str-modal-cancel">Cancelar</button>
          <button type="button" class="str-btn str-btn-primary" id="str-modal-create-btn">Crear</button>
        </div>
      </div>
    `;

    document.body.appendChild(overlay);
    document.body.appendChild(modal);

    overlay.addEventListener('click', closeCreateModal);
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeCreateModal(); });

    // 👉 Aquí usamos la función hoisted
    document.getElementById('str-modal-cancel')?.addEventListener('click', closeCreateModal);
    document.getElementById('str-modal-create-btn')?.addEventListener('click', submitCreateModal);
    document.getElementById('str-modal-create-form')?.addEventListener('submit', (e) => {
      e.preventDefault();
      submitCreateModal();
    });
  }

  function openCreateModal() {
    ensureCreateModal();
    const overlay = document.getElementById('str-modal-overlay');
    const modal   = document.getElementById('str-modal-create-group');
    const input   = document.getElementById('str-modal-name');
    const err     = document.getElementById('str-modal-err');

    if (err) err.classList.remove('show'), (err.textContent = '');
    overlay?.classList.add('show');
    modal?.classList.add('show');
    setTimeout(() => input?.focus(), 30);
    trapFocus(modal);
  }

  function closeCreateModal() {
    const overlay = document.getElementById('str-modal-overlay');
    const modal   = document.getElementById('str-modal-create-group');
    overlay?.classList.remove('show');
    modal?.classList.remove('show');
  }

  function trapFocus(container) {
    if (!container) return;
    const focusables = container.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
    const list = Array.from(focusables);
    if (list.length === 0) return;
    const first = list[0], last = list[list.length - 1];
    container.addEventListener('keydown', function onKey(e) {
      if (e.key !== 'Tab') return;
      if (e.shiftKey) {
        if (document.activeElement === first) { last.focus(); e.preventDefault(); }
      } else {
        if (document.activeElement === last) { first.focus(); e.preventDefault(); }
      }
    });
  }

  // ────────────────────────────────────────────────────────────
  // Render UI
  // ────────────────────────────────────────────────────────────
  function ensureRoot() {
    let mount = document.getElementById('str-gestion-grupos');
    if (!mount) {
      mount = document.createElement('div');
      mount.id = 'str-gestion-grupos';
      document.body.appendChild(mount);
      try { console.info('[GROUPS] No existía #str-gestion-grupos. Se ha inyectado al final del <body>.'); } catch(_) {}
    }
    return mount;
  }

  function render() {
    const wrap = ensureRoot();

    const topBar = `
      <div class="str-topbar">
        <div class="str-topbar-left">
          <div class="muted">Competición: #${COMP_ID}</div>
          <div class="muted">Grupos: ${state.meta.n_grupos || state.grupos.length}</div>
          <div class="muted">Parejas libres: ${state.libres.length}</div>
        </div>
        <div class="str-topbar-right">
          <button id="btn-crear-grupo" class="str-btn str-btn-primary" type="button">Crear grupo</button>
        </div>
      </div>
    `;

    const libresOpt = state.libres.map(p => `<option value="${p.id}">${htmlEscape(p.title)}</option>`).join("");

    const cards = state.grupos.map(g => {
      // Cabecera de columnas dentro del card
      const headRow = `
        <li class="str-row str-row-head">
          <div class="str-pair-name"><span>Pareja</span></div>
          <div class="str-points"><span>Puntos</span></div>
          <div class="str-actions"><span>Acciones</span></div>
        </li>
      `;

      const filas = (g.participantes || []).map(p => {
        const pts = (typeof p.puntos === 'number' ? p.puntos : 0);
        return `
          <li class="str-row">
            <div class="str-pair-name">${htmlEscape(p.title)}</div>
            <div class="str-points">
              <input class="str-points-input" type="number" min="0" step="1"
                     value="${pts}" inputmode="numeric" aria-label="Puntos" disabled />
            </div>
            <div class="str-actions">
              <button class="str-btn str-btn-small str-btn-remove"
                      data-grupo="${g.id}" data-pareja="${p.id}" type="button">Quitar</button>
            </div>
          </li>
        `;
      }).join("");

      return `
        <div class="str-card grupo" data-grupo="${g.id}">
          <div class="str-card-head">
            <div class="title">Grupo <b>${htmlEscape(g.letra || g.nombre || "?")}</b></div>
            <div class="sub">Capacidad: ${g.tam ?? "-"} · Ocupadas: ${(g.participantes||[]).length}</div>
          </div>

          <ul class="str-list">
            ${headRow}
            ${filas || `<li class="str-row"><em>Sin participantes</em></li>`}
          </ul>

          <div class="str-card-foot">
            <div class="str-add">
              <select class="str-add-select">
                <option value="">— Añadir pareja libre —</option>
                ${libresOpt}
              </select>
              <button class="str-btn str-btn-small str-btn-add" type="button">Añadir</button>
            </div>
          </div>
        </div>
      `;
    }).join("");

    const html = `
      ${topBar}
      <div class="str-free-select">
        <label class="muted">Parejas libres en este torneo</label>
        <select id="str-free-select"><option value="">— Selecciona una pareja —</option>${libresOpt}</select>
        <div class="muted">Puedes elegirla en el selector de cada grupo.</div>
      </div>
      <div class="str-grid-groups">${cards}</div>
    `;
    wrap.innerHTML = html;

    // listeners
    const btnCrear = qs("#btn-crear-grupo");
    if (btnCrear) btnCrear.addEventListener("click", onCrearGrupo);

    // ⚠️ CAMBIO: sin alert(); validación inline con showSelectError()
    qsa(".str-btn-add").forEach(btn => {
      btn.addEventListener("click", async () => {
        const card = btn.closest(".str-card.grupo");
        const gid  = parseInt(card?.getAttribute("data-grupo") || "0", 10);
        const sel  = card?.querySelector(".str-add-select");
        const pid  = sel ? parseInt(sel.value || "0", 10) : 0;

        if (!gid || !pid) {
  if (sel) {
    sel.classList.add('str-field-error');
    const holder = sel.closest('.str-card-foot') || sel.parentElement;
    let tip = holder && holder.querySelector('.str-inline-error');
    if (!tip && holder) {
      tip = document.createElement('div');
      tip.className = 'str-inline-error';
      tip.textContent = 'Selecciona una pareja libre.';
      holder.appendChild(tip);
    }
    sel.focus();
    setTimeout(() => {
      if (tip && tip.parentNode) tip.parentNode.removeChild(tip);
      sel.classList.remove('str-field-error');
    }, 2000);
  }
  return;
}

        try {
          suppressPairsModal(5000);
          btn.disabled = true; btn.textContent = "Añadiendo...";
          await asignarPareja(gid, pid);
          await recargar();
        } catch (e) {
          console.error(e); alert(e.message || "No se pudo añadir.");
        } finally {
          btn.disabled = false; btn.textContent = "Añadir";
        }
      });
    });

    qsa(".str-btn-remove").forEach(btn => {
      btn.addEventListener("click", async () => {
        const gid = parseInt(btn.getAttribute("data-grupo") || "0", 10);
        const pid = parseInt(btn.getAttribute("data-pareja") || "0", 10);
        if (!gid || !pid) return;
        try {
          btn.disabled = true; btn.textContent = "Quitando...";
          await quitarPareja(gid, pid);
          await recargar();
        } catch (e) {
          console.error(e); alert(e.message || "No se pudo quitar.");
        } finally {
          btn.disabled = false; btn.textContent = "Quitar";
        }
      });
    });
  }

  // ────────────────────────────────────────────────────────────
  // Acciones
  // ────────────────────────────────────────────────────────────
  async function cargarGrupos() {
    if (!COMP_ID) return;
    const data = await postAjax(ACTIONS.cargar, { competicion_id: COMP_ID });
    state.meta   = data.meta   || state.meta;
    state.grupos = Array.isArray(data.grupos) ? data.grupos : [];
    state.libres = Array.isArray(data.parejas_libres) ? data.parejas_libres : [];
  }

  async function cargarStandings() {
    try {
      const data = await postAjax(ACTIONS.standings, { competicion_id: COMP_ID });
      const wrap = qs("#str-standings");
      if (wrap) {
        wrap.innerHTML = `<div class="str-card"><div class="str-card-body"><em>Clasificación actualizada.</em></div></div>`;
      }
      void data;
    } catch (e) {
      console.error(e);
    }
  }

  async function recargar() {
    await Promise.all([cargarGrupos(), cargarStandings()]);
    render();
  }

  // Abre modal (con fallback defensivo a prompt)
  async function onCrearGrupo() {
    try {
      openCreateModal();
    } catch (e) {
      let nombre = window.prompt("Nombre del grupo (ej. A, B, C). Deja vacío para autogenerar:");
      if (nombre == null) return; // cancel
      nombre = String(nombre).trim();
      try {
        await postAjax(ACTIONS.crear, { competicion_id: COMP_ID, nombre });
        await recargar();
      } catch (err) {
        console.error(err); alert(err.message || "No se pudo crear el grupo.");
      }
    }
  }

  async function asignarPareja(grupoId, parejaId) {
    return postAjax(ACTIONS.asignar || 'saas_grupo_asignar', {
      competicion_id: COMP_ID,
      grupo_id: grupoId,
      pareja_id: parejaId
    });
  }

  async function quitarPareja(grupoId, parejaId) {
    return postAjax(ACTIONS.quitar || 'saas_grupo_quitar', {
      competicion_id: COMP_ID,
      grupo_id: grupoId,
      pareja_id: parejaId
    });
  }

  // ────────────────────────────────────────────────────────────
  // Init
  // ────────────────────────────────────────────────────────────
  document.addEventListener("DOMContentLoaded", async () => {
    if (!COMP_ID) return;
    try {
      ensureCreateModal();  // modal montado desde el inicio
      await cargarGrupos();
      render();
      await cargarStandings();
      console.info('[GROUPS][BOOT]', { AJAX, NONCE, COMP_ID, from: {strTorneo: !!window.strTorneo, str_groups_ajax_obj: !!window.str_groups_ajax_obj}, ACTIONS });
    } catch (e) {
      console.error(e);
    }
  });

  // Interceptores en captura
  document.addEventListener('click', function (e) {
    const btn = e.target && e.target.closest && e.target.closest('#btn-crear-grupo');
    if (!btn) return;
    e.preventDefault();
    e.stopPropagation();
    e.stopImmediatePropagation();
    try {
      openCreateModal();
    } catch (err) {
      console.error('[GROUPS][MODAL] fallback por error:', err);
      const nombre = window.prompt("Nombre del grupo (ej. A, B, C). Deja vacío para autogenerar:");
      if (nombre != null) {
        postAjax(ACTIONS.crear || 'saas_grupo_crear', {
          competicion_id: COMP_ID,
          nombre: String(nombre).trim()
        }).then(() => {
          return Promise.all([
            (typeof cargarGrupos === 'function' ? cargarGrupos() : Promise.resolve()),
            (typeof cargarStandings === 'function' ? cargarStandings() : Promise.resolve())
          ]);
        }).then(render)
          .catch((e) => alert(e && e.message ? e.message : 'No se pudo crear el grupo.'));
      }
    }
  }, true);

  document.addEventListener('click', function (e) {
    const addBtn = e.target && e.target.closest && e.target.closest('#str-gestion-grupos .str-btn-add');
    if (!addBtn) return;
    e.stopPropagation();
    e.stopImmediatePropagation();
  }, true);

})();

/* ============================================================
   DEBUG TRACE – clicks + llamadas AJAX (admin-ajax.php)
   (Elimina este bloque cuando acabes de depurar)
   ============================================================ */
(function () {
  try {
    var SELECTORES_INTERES = [
      '.str-btn-add',
      '.js-add-pareja',
      '.js-add-pareja-grupo',
      '#btn-abrir-modal-pareja',
      '.js-add-jugador',
      '#btn-abrir-modal-invitacion-jugador'
    ];

    function matchedSelector(el) {
      for (var i=0;i<SELECTORES_INTERES.length;i++){
        try { if (el.matches(SELECTORES_INTERES[i])) return SELECTORES_INTERES[i]; } catch(_) {}
      }
      return null;
    }

    document.addEventListener('click', function (e) {
      var el = e.target, depth = 0, hit = null;
      while (el && depth < 6 && !hit) {
        var m = matchedSelector(el);
        if (m) hit = { node: el, selector: m };
        el = el.parentElement; depth++;
      }
      if (!hit) return;

      var text = (hit.node.textContent || '').trim().slice(0,120);
      console.groupCollapsed(
        '%c[TRACE CLICK]%c ' + hit.selector + '  %c' + (text || '(sin texto)'),
        'color:#0ea5e9;font-weight:700', 'color:inherit', 'color:#64748b'
      );
      console.log('Elemento que hizo match:', hit.node);
      if (hit.node.dataset) console.log('dataset:', JSON.parse(JSON.stringify(hit.node.dataset)));
      console.trace('Stack');
      console.groupEnd();
    }, true);

    if (typeof window.fetch === 'function') {
      var _fetch = window.fetch;
      window.fetch = function (input, init) {
        try {
          var url = (typeof input === 'string') ? input : (input && input.url) || '';
          if (url.indexOf('/wp-admin/admin-ajax.php') !== -1) {
            var method = (init && init.method) ? String(init.method).toUpperCase() : 'GET';
            var bodyStr = '';
            if (init && init.body) {
              if (typeof init.body === 'string') bodyStr = init.body;
              else if (init.body instanceof URLSearchParams) bodyStr = init.body.toString();
              else if (typeof FormData !== 'undefined' && init.body instanceof FormData) {
                var tmp=[]; init.body.forEach(function(v,k){ tmp.push(encodeURIComponent(k)+'='+encodeURIComponent(v)); });
                bodyStr = tmp.join('&');
              }
            }
            var m = bodyStr.match(/(?:^|&|;)action=([^&;]+)/i);
            var actionName = m ? decodeURIComponent(m[1]) : '(sin action)';
            console.groupCollapsed(
              '%c[TRACE AJAX]%c ' + method + ' admin-ajax.php  %caction=' + actionName,
              'color:#22c55e;font-weight:700', 'color:inherit', 'color:#64748b'
            );
            if (bodyStr) console.log('Body:', bodyStr.length>500 ? bodyStr.slice(0,500)+'…' : bodyStr);
            console.trace('Stack');
            console.groupEnd();
          }
        } catch (_) {}
        return _fetch.apply(this, arguments);
      };
    }

    if (window.jQuery && window.jQuery.ajax) {
      (function ($) {
        var _ajax = $.ajax;
        $.ajax = function (opts) {
          try {
            var url = (typeof opts === 'string') ? opts : (opts && opts.url) || '';
            if (url.indexOf('/wp-admin/admin-ajax.php') !== -1) {
              var dataStr = '';
              if (opts && opts.data) {
                if (typeof opts.data === 'string') dataStr = opts.data;
                else if (opts.data instanceof URLSearchParams) dataStr = opts.data.toString();
                else if (typeof opts.data === 'object') {
                  var parts=[]; for (var k in opts.data){ if(Object.prototype.hasOwnProperty.call(opts.data,k)){ parts.push(encodeURIComponent(k)+'='+encodeURIComponent(opts.data[k])); } }
                  dataStr = parts.join('&');
                }
              }
              var m = dataStr.match(/(?:^|&|;)action=([^&;]+)/i);
              var actionName = m ? decodeURIComponent(m[1]) : '(sin action)';
              console.groupCollapsed(
                '%c[TRACE jQ.AJAX]%c admin-ajax.php  %caction=' + actionName,
                'color:#84cc16;font-weight:700', 'color:inherit', 'color:#64748b'
              );
              if (dataStr) console.log('Data:', dataStr.length>500 ? dataStr.slice(0,500)+'…' : dataStr);
              console.trace('Stack');
              console.groupEnd();
            }
          } catch(_) {}
          return _ajax.apply(this, arguments);
        };
      })(window.jQuery);
    }

    console.info('%c[TRACE ACTIVADO]%c Mira la consola: clicks relevantes y AJAX a admin-ajax.php.',
      'color:#0ea5e9;font-weight:700','color:inherit');
  } catch (e) {
    console.error('[TRACE] fallo al inicializar:', e);
  }
})();
