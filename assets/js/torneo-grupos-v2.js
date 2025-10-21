/* globals strTorneoV2 */
(function(){
  "use strict";

  if (!window.strTorneoV2) { console.warn('[GROUPS v2] strTorneoV2 no definido'); return; }
  const cfg = window.strTorneoV2;
  const REST = cfg.restBase.replace(/\/+$/,''); // .../wp-json/saas/v1
  const CID  = parseInt(cfg.competicionId||0,10);
  const RID  = cfg.reqId || ('r'+Math.random().toString(36).slice(2,8));

  const H = (html) => html; // sugar
  const $ = (sel) => document.querySelector(sel);
  const $$ = (sel) => Array.from(document.querySelectorAll(sel));

  async function restGet(path){
    const res = await fetch(REST + path, { credentials:'same-origin' });
    const ct  = res.headers.get('content-type')||'';
    if (!ct.includes('application/json')) {
      const raw = await res.text();
      console.error('[GROUPS v2] RAW (no JSON):', raw);
      throw new Error('Respuesta no JSON (GET '+path+')');
    }
    return await res.json();
  }
  async function restPost(path, data){
    const res = await fetch(REST + path, {
      method: 'POST',
      headers: {
        'Content-Type':'application/json',
        'X-WP-Nonce': cfg.restNonce || '',
      },
      credentials:'same-origin',
      body: JSON.stringify(data||{})
    });
    const ct  = res.headers.get('content-type')||'';
    if (!ct.includes('application/json')) {
      const raw = await res.text();
      console.error('[GROUPS v2] RAW (no JSON):', raw);
      throw new Error('Respuesta no JSON (POST '+path+')');
    }
    const json = await res.json();
    if (!res.ok) {
      const msg = (json && json.message) ? json.message : 'Error AJAX (success=false)';
      throw new Error(msg);
    }
    return json;
  }

  const state = { meta:{}, grupos:[], libres:[] };

  function renderGrupos(){
    const wrap = $('#str-gestion-grupos');
    if (!wrap) return;
    const libresOpt = state.libres.map(p=>`<option value="${p.id}">${escapeHTML(p.title||'Pareja')}</option>`).join('');

    const cards = state.grupos.map(g=>{
      const rows = (g.participantes||[]).map(p=>{
        if (p.placeholder) {
          return `
          <li class="str-row placeholder">
            <div class="str-pair-name"><span class="badge">Placeholder</span></div>
            <div class="str-actions">
              <select class="str-pair-select" data-grupo="${g.id}">
                <option value="">— Seleccionar pareja —</option>
                ${libresOpt}
              </select>
              <button class="str-btn str-btn-small str-btn-assign" data-grupo="${g.id}" data-ph="${p.id}" type="button">Asignar</button>
            </div>
          </li>`;
        }
        return `
        <li class="str-row">
          <div class="str-pair-name">${escapeHTML(p.title||'Pareja')}</div>
          <div class="str-actions"><span class="pill pill-ok">Asignada</span></div>
        </li>`;
      }).join('');

      return `
      <div class="str-card grupo">
        <div class="str-card-head">
          <div class="title">Grupo <b>${escapeHTML(g.letra||'?')}</b></div>
          <div class="sub">Capacidad: ${g.tam} · Ocupadas: ${(g.participantes||[]).filter(x=>!x.placeholder).length}</div>
        </div>
        <ul class="str-list">${rows || `<li class="str-row"><em>Sin participantes</em></li>`}</ul>
      </div>`;
    }).join('');

    wrap.innerHTML = `
      <div class="str-groups-header">
        <div class="str-groups-meta">
          <span><b>Grupos:</b> ${state.grupos.length}</span>
          <span><b>Parejas totales (aprox):</b> ${state.meta.n_parejas || '-'}</span>
          <span><b>Fase final:</b> ${escapeHTML(state.meta.fase_final||'-')}</span>
          <span><b>Modo final:</b> ${escapeHTML(state.meta.modo_final||'-')}</span>
        </div>
        <div class="str-groups-actions">
          <button class="str-btn" id="btn-v2-recalc" type="button">Recalcular clasificación</button>
          <button class="str-btn str-btn-primary" id="btn-v2-auto" type="button">Rellenar aleatoriamente</button>
        </div>
      </div>
      <div class="str-grid-groups">${cards}</div>`;

    const btnAuto  = $('#btn-v2-auto');
    const btnRecal = $('#btn-v2-recalc');
    if (btnAuto)  btnAuto.addEventListener('click', onAutoAssign);
    if (btnRecal) btnRecal.addEventListener('click', cargarStandings);

    $$('.str-btn-assign').forEach(btn=>{
      btn.addEventListener('click', async ()=>{
        try {
          const gid = parseInt(btn.getAttribute('data-grupo'),10);
          const ph  = parseInt(btn.getAttribute('data-ph'),10);
          const sel = btn.parentElement.querySelector('.str-pair-select');
          const pid = sel ? parseInt(sel.value,10) : 0;
          if (!pid) { alert('Selecciona una pareja'); return; }
          btn.disabled = true; btn.textContent = 'Asignando...';
          await restPost(`/group/${gid}/assign`, {competicion_id:CID, pareja_id:pid, placeholder_id:ph});
          await Promise.all([cargarGrupos(), cargarStandings()]);
        } catch (e) {
          console.error(e); alert(e.message||'Error al asignar');
        } finally {
          btn.disabled=false; btn.textContent='Asignar';
        }
      });
    });
  }

  function renderStandings(data){
    const wrap = $('#str-standings');
    if (!wrap) return;
    if (!data || !Array.isArray(data.grupos) || data.grupos.length===0) {
      wrap.innerHTML = `<div class="str-card"><div class="str-card-body"><em>No hay standings todavía.</em></div></div>`;
      return;
    }
    const tables = data.grupos.map(g=>{
      const rows = (g.items||[]).map((it,i)=>`
        <tr>
          <td>${i+1}</td>
          <td>${escapeHTML(it.title||'Pareja')}${it.placeholder? ' <span class="badge">PH</span>':''}</td>
          <td>${it.pj}</td><td>${it.pg}</td><td>${it.pp}</td>
          <td><b>${it.pts}</b></td>
          <td>${it.sets_f}-${it.sets_c}</td>
          <td>${it.juegos_f}-${it.juegos_c}</td>
        </tr>
      `).join('');
      return `
        <div class="str-card standings">
          <div class="str-card-head"><div class="title">Clasificación · Grupo <b>${escapeHTML(g.letra||'?')}</b></div></div>
          <div class="str-table-wrap">
            <table class="str-table">
              <thead><tr><th>#</th><th>Pareja</th><th>PJ</th><th>PG</th><th>PP</th><th>Pts</th><th>Sets</th><th>Juegos</th></tr></thead>
              <tbody>${rows || `<tr><td colspan="8"><em>Sin datos</em></td></tr>`}</tbody>
            </table>
          </div>
        </div>`;
    }).join('');
    wrap.innerHTML = tables;
  }

  async function cargarGrupos(){
    const data = await restGet(`/groups?competicion_id=${CID}&rid=${encodeURIComponent(RID)}`);
    state.meta   = data.meta || {};
    state.grupos = Array.isArray(data.grupos) ? data.grupos : [];
    state.libres = Array.isArray(data.parejas_libres) ? data.parejas_libres : [];
    renderGrupos();
  }

  async function cargarStandings(){
    const data = await restGet(`/groups/standings?competicion_id=${CID}&rid=${encodeURIComponent(RID)}`);
    renderStandings(data);
  }

  async function onAutoAssign(){
    const btn = $('#btn-v2-auto');
    if (btn) { btn.disabled = true; btn.textContent = 'Rellenando...'; }
    try {
      await restPost('/groups/auto-assign', {competicion_id: CID});
      await Promise.all([cargarGrupos(), cargarStandings()]);
    } catch (e) {
      console.error(e); alert(e.message||'No se pudo completar automáticamente');
    } finally {
      if (btn) { btn.disabled=false; btn.textContent='Rellenar aleatoriamente'; }
    }
  }

  function escapeHTML(str){
    if (str==null) return '';
    return String(str).replace(/[&<>"']/g, function(m){
      return ({
        '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'
      })[m] || m;
    });
  }

  document.addEventListener('DOMContentLoaded', async ()=>{
    if (!CID) return;
    try {
      await cargarGrupos();
      await cargarStandings();
    } catch (e) {
      console.error('[GROUPS v2] init error', e);
      alert(e.message || 'Error inicial (v2)');
    }
  });

})();
