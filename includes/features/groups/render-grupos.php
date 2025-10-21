<?php
/**
 * Render de gestión de grupos en frontend (server-side)
 * - Lista parejas libres
 * - Lista grupos con participantes
 * - Acciones: crear grupo, asignar pareja, quitar pareja (con modal)
 * - Renombrar grupo + Eliminar grupo (modal Editar)
 * - Distribuir parejas (modal): vista previa y aplicación
 *
 * Acciones AJAX esperadas (con alias):
 *  - str_grupo_crear           (core: saas_grupo_crear)
 *  - str_grupo_asignar_pareja  (core: saas_grupo_asignar)
 *  - str_grupo_quitar_pareja   (core: saas_grupo_quitar)
 *  - str_grupo_renombrar       (core: saas_grupo_renombrar)
 *  - str_grupo_eliminar        (core: saas_grupo_eliminar)
 */

if ( ! defined('ABSPATH') ) { exit; }

if (!function_exists('str_competicion_grupos_render')):

function str_competicion_grupos_render($args = []) {
    $args = wp_parse_args($args, [
        'competicion_id' => 0,
        'print_css'      => true,
    ]);

    $comp_id = absint($args['competicion_id']);
    if ( ! $comp_id ) return '<!-- STR grupos: competicion_id vacío -->';

    if (!function_exists('str_escribir_log')) {
        function str_escribir_log($m, $o='STR'){ @file_put_contents(WP_PLUGIN_DIR.'/saas-torneos-de-raqueta/debug-saas-torneos.log', "[".date('Y-m-d H:i:s')."] [$o] $m\n", FILE_APPEND); }
    }

    $nonce = wp_create_nonce('str_nonce');

    // === PUNTOS: función mínima, con filtro y meta opcional ===
    $__str_points_cache = [];
    $str_get_points = function(int $pair_id) use (&$__str_points_cache, $comp_id) : int {
        if (isset($__str_points_cache[$pair_id])) return $__str_points_cache[$pair_id];

        // 1) filtro (puedes enganchar tus cálculos reales desde partidos)
        $pts = apply_filters('str_pair_points', null, $pair_id, (int)$comp_id);

        // 2) metas opcionales (si algún día los guardas en meta/ACF)
        if ($pts === null) {
            foreach (['str_puntos_comp_'.(int)$comp_id, 'str_puntos', 'puntos'] as $mk) {
                $v = get_post_meta($pair_id, $mk, true);
                if ($v !== '' && $v !== null) { $pts = (int)$v; break; }
            }
        }

        if ($pts === null) $pts = 0; // 3) por defecto
        return $__str_points_cache[$pair_id] = (int)$pts;
    };

    // =============================
    // 1) Obtener GRUPOS del torneo
    // =============================
    $grupos = get_posts([
        'post_type'      => 'grupo',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_query'     => [[
            'key'     => 'torneo_asociado',
            'value'   => '"' . $comp_id . '"',
            'compare' => 'LIKE',
        ]],
        'orderby' => ['date' => 'ASC', 'ID' => 'ASC'],
    ]);

    // Mapa de nombres de grupos (para el front)
    $map_grupos = []; // gid => "Grupo A"
    foreach ($grupos as $g) {
        $nombre_g = function_exists('get_field') ? get_field('nombre_grupo', $g->ID) : '';
        if (!$nombre_g) $nombre_g = $g->post_title ?: ('Grupo '.$g->ID);
        // Siempre mostramos con prefijo "Grupo "
        $map_grupos[$g->ID] = 'Grupo ' . trim($nombre_g);
    }

    // =============================
    // 2) Obtener PAREJAS del torneo
    // =============================
    $parejas_eq = get_posts([
        'post_type'      => 'pareja',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_query'     => [[
            'key'     => 'torneo_asociado',
            'value'   => $comp_id,
            'compare' => '=',
        ]],
        'fields'  => 'ids',
    ]);
    $parejas_like = get_posts([
        'post_type'      => 'pareja',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_query'     => [[
            'key'     => 'torneo_asociado',
            'value'   => '"' . $comp_id . '"',
            'compare' => 'LIKE',
        ]],
        'fields'  => 'ids',
    ]);
    $parejas_torneo = array_values(array_unique(array_merge($parejas_eq, $parejas_like)));

    // Mapa de nombres de pareja (para el front)
    $map_parejas = []; // pid => "Nombre pareja"
    foreach ($parejas_torneo as $pid) {
        $t = get_the_title($pid);
        $map_parejas[$pid] = $t ? $t : ('Pareja #'.$pid);
    }

    // 3) Parejas asignadas a algún grupo
    $parejas_asignadas = [];
    $grupos_participantes = []; // gid => [pids]
    foreach ($grupos as $g) {
        $participantes = function_exists('get_field')
            ? get_field('participantes_grupo', $g->ID, false)
            : get_post_meta($g->ID, 'participantes_grupo', true);
        if (empty($participantes)) $participantes = [];
        if (!is_array($participantes)) $participantes = (array)$participantes;
        $participantes = array_map('absint', $participantes);
        $grupos_participantes[$g->ID] = $participantes;
        $parejas_asignadas = array_merge($parejas_asignadas, $participantes);
    }
    $parejas_asignadas = array_unique(array_filter($parejas_asignadas));

    // 4) Parejas libres = todas del torneo – asignadas
    $parejas_libres = array_values(array_diff($parejas_torneo, $parejas_asignadas));

    $nombre_pareja = function($pareja_id) use ($map_parejas) {
        return $map_parejas[$pareja_id] ?? ('Pareja #'.$pareja_id);
    };

    ob_start();
    ?>

    <?php if (!empty($args['print_css'])): ?>
    <style>
      /* Contenedor y elementos */
      .str-grupos-wrapper{margin:1.5rem 0;border:1px solid #e5e7eb;border-radius:12px;padding:16px;background:#fff}
      .str-grupos-header{display:flex;gap:12px;align-items:center;justify-content:space-between;margin-bottom:12px;flex-wrap:wrap}
      .str-grupos-actions{display:flex;gap:8px;flex-wrap:wrap}
      .str-badge{display:inline-flex;align-items:center;gap:.4rem;font-size:.85rem;border:1px solid #e5e7eb;border-radius:999px;padding:.25rem .6rem;background:#f9fafb}
      .str-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px}
      .str-card{border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;background:#fff}
      .str-card-hd{display:flex;align-items:center;justify-content:space-between;padding:10px 12px;background:#f8fafc;border-bottom:1px solid #e5e7eb}
      .str-card-ttl{font-weight:600}
      .str-card-actions{display:flex;gap:6px}
      .str-btn{appearance:none;border:1px solid #dbe6ff;background:#f8fbff;border-radius:10px;padding:10px 14px;cursor:pointer;color:#1a2156;font-weight:600;display:inline-flex;align-items:center;gap:8px;transition:box-shadow .15s, transform .06s, background .15s}
      .str-btn:hover{box-shadow:0 3px 10px rgba(40,65,100,0.12)}
      .str-btn:active{transform:translateY(1px)}
      .str-btn--primary{border-color:#2152ff;background:linear-gradient(92deg,#2152ff 0%,#3273f8 100%);color:#fff}
      .str-btn--primary:hover{box-shadow:0 3px 12px rgba(33,82,255,0.25)}
      .str-btn-danger{border-color:#fecaca;background:#fff5f5;color:#7f1d1d}
      .str-btn-danger:hover{background:#ef4444;color:#fff;border-color:#ef4444}
      .str-list{list-style:none;margin:0;padding:8px 12px;display:flex;flex-direction:column;gap:6px}
      .str-list li{border:1px solid #eef2f7;border-radius:8px;padding:6px 8px}
      /* NUEVO: maquetación de la fila con columna de puntos */
      .str-li{display:flex;align-items:center;justify-content:space-between;gap:8px}
      .str-li .str-name{flex:1 1 auto;min-width:0}
      .str-points{min-width:58px;text-align:center;border:1px solid #e5e7eb;border-radius:999px;padding:2px 10px;background:#f8fafc;font-variant-numeric:tabular-nums}
      .str-row-add{display:flex;gap:6px;padding:10px 12px;border-top:1px dashed #e5e7eb;background:#fcfcfd}
      .str-select{width:100%;padding:8px 10px;border:1.5px solid #d8e4ff;border-radius:8px;background:#fff;color:#1a2156}
      .str-select:focus{border-color:#2152ff;background:#f5f9ff;outline:none}
      .str-muted{color:#6b7280;font-size:.9rem}

      /* Modal base */
      .str-modal{position:fixed;inset:0;display:none;align-items:center;justify-content:center;background:rgba(0,0,0,.45);z-index:9999}
      .str-modal.is-open{display:flex}
      .str-dialog{background:#fff;border-radius:12px;max-width:560px;width:92%;box-shadow:0 15px 40px rgba(0,0,0,.2);overflow:hidden}
      .str-dialog-hd{display:flex;align-items:center;justify-content:space-between;padding:12px 14px;border-bottom:1px solid #e5e7eb}
      .str-dialog-bd{padding:14px}
      .str-dialog-ft{padding:12px 14px;display:flex;justify-content:flex-end;gap:8px;border-top:1px solid #e5e7eb}
      .str-input{width:100%;padding:8px 10px;border:1px solid #e5e7eb;border-radius:8px}

      /* Vista previa distribución */
      .str-prev-wrap{max-height:320px;overflow:auto;border:1px solid #eef2f7;border-radius:10px;padding:8px;background:#fafafa}
      .str-prev-card{border:1px solid #e5e7eb;border-radius:10px;margin-bottom:10px;background:#fff}
      .str-prev-head{padding:8px 10px;border-bottom:1px solid #eef2f7;font-weight:600}
      .str-prev-body{display:grid;grid-template-columns:1fr 1fr;gap:10px;padding:10px}
      .str-chip{display:inline-block;border:1px solid #e5e7eb;border-radius:999px;padding:2px 8px;margin:2px;background:#f8fafc;font-size:.9rem}
      .str-colcap{font-size:.85rem;color:#64748b;margin-bottom:6px}
    </style>
    <?php endif; ?>

    <div id="str-grupos-wrapper"
         class="str-grupos-wrapper"
         data-ajax="<?php echo esc_attr(admin_url('admin-ajax.php')); ?>"
         data-nonce="<?php echo esc_attr($nonce); ?>"
         data-comp="<?php echo esc_attr($comp_id); ?>">

        <div class="str-grupos-header">
            <div class="str-badges">
                <span class="str-badge">Grupos: <strong><?php echo count($grupos); ?></strong></span>
                <span class="str-badge">Parejas totales: <strong><?php echo count($parejas_torneo); ?></strong></span>
                <span class="str-badge">Parejas libres: <strong id="str-count-libres"><?php echo count($parejas_libres); ?></strong></span>
            </div>
            <div class="str-grupos-actions">
                <button class="str-btn" id="str-btn-distribuir">Distribuir parejas</button>
                <button class="str-btn str-btn--primary" id="str-btn-crear-grupo">Crear grupo</button>
            </div>
        </div>

        <!-- Selector maestro de parejas libres -->
        <div style="margin:0 0 12px 0;">
            <label class="str-muted" for="str-select-libres">Parejas libres:</label>
            <select id="str-select-libres" class="str-select">
                <option value="">— Selecciona una pareja libre —</option>
                <?php foreach ($parejas_libres as $pid): ?>
                    <option value="<?php echo esc_attr($pid); ?>"><?php echo esc_html($nombre_pareja($pid)); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- GRID DE GRUPOS -->
        <div class="str-grid">
            <?php if (empty($grupos)): ?>
                <div class="str-card">
                    <div class="str-card-hd">
                        <div class="str-card-ttl">Aún no hay grupos</div>
                    </div>
                    <div class="str-list">
                        <li class="str-muted">Usa “Crear grupo” para empezar.</li>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($grupos as $grupo): ?>
                    <?php
                        $gid = $grupo->ID;
                        $nombre_g = $map_grupos[$gid] ?? ($grupo->post_title ?: 'Grupo '.$gid); // ya viene “Grupo X”
                        $participantes = $grupos_participantes[$gid] ?? [];
                    ?>
                    <div class="str-card" data-grupo-id="<?php echo esc_attr($gid); ?>">
                        <div class="str-card-hd">
                            <div class="str-card-ttl"><?php echo esc_html($nombre_g); ?></div>
                            <div class="str-card-actions">
                                <button class="str-btn js-editar-grupo"
                                        data-grupo-id="<?php echo esc_attr($gid); ?>"
                                        data-grupo-nombre="<?php echo esc_attr(preg_replace('~^\s*Grupo\s+~i','',$nombre_g)); ?>">
                                    Editar
                                </button>
                            </div>
                        </div>
                        <ul class="str-list">
                            <?php if (empty($participantes)): ?>
                                <li class="str-li">
                                    <span class="str-name str-muted">Sin participantes</span>
                                    <span class="str-points">—</span>
                                    <span><!-- vacío --></span>
                                </li>
                            <?php else: ?>
                                <?php foreach ($participantes as $pid): ?>
                                    <li class="str-li">
                                        <span class="str-name"><?php echo esc_html($nombre_pareja($pid)); ?></span>

                                        <!-- NUEVO: puntos visibles (y listos para tiempo real) -->
                                        <span class="str-points str-points-val" data-pair-id="<?php echo esc_attr($pid); ?>">
                                            <?php echo (int) $str_get_points((int)$pid); ?>
                                        </span>

                                        <button class="str-btn str-btn-danger js-quitar-pareja"
                                                data-grupo-id="<?php echo esc_attr($gid); ?>"
                                                data-pareja-id="<?php echo esc_attr($pid); ?>">
                                            Quitar
                                        </button>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                        <div class="str-row-add">
                            <select class="str-select js-select-pareja" data-grupo-id="<?php echo esc_attr($gid); ?>">
                                <option value="">— Añadir pareja libre a este grupo —</option>
                                <?php foreach ($parejas_libres as $pid): ?>
                                    <option value="<?php echo esc_attr($pid); ?>"><?php echo esc_html($nombre_pareja($pid)); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="str-btn js-add-pareja" data-grupo-id="<?php echo esc_attr($gid); ?>">Añadir</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Datos para JS: nombres de grupos y parejas -->
    <script id="str-json-data" type="application/json">
    <?php
      echo wp_json_encode([
        'groups'       => $map_grupos,             // gid => "Grupo A"
        'pairs'        => $map_parejas,            // pid => "Nombre pareja"
        'groupsPairs'  => $grupos_participantes,   // gid => [pids]
        'freePairs'    => $parejas_libres,         // [pids]
      ]);
    ?>
    </script>

    <!-- MODALES + JS existentes (sin cambios) -->
    <div class="str-modal" id="str-modal-crear">
        <div class="str-dialog" role="dialog" aria-modal="true" aria-labelledby="str-modal-crear-ttl">
            <div class="str-dialog-hd">
                <strong id="str-modal-crear-ttl">Crear grupo</strong>
                <button class="str-btn" data-close="#str-modal-crear">✕</button>
            </div>
            <div class="str-dialog-bd">
                <label class="str-muted" for="str-inp-nombre-grupo">Nombre del grupo (opcional)</label>
                <input id="str-inp-nombre-grupo" class="str-input" type="text" placeholder="Ej. A, B, C...">
                <p class="str-muted" style="margin-top:8px">Si lo dejas en blanco se autogenerará.</p>
            </div>
            <div class="str-dialog-ft">
                <button class="str-btn" data-close="#str-modal-crear">Cancelar</button>
                <button class="str-btn str-btn--primary" id="str-btn-crear-confirm">Crear</button>
            </div>
        </div>
    </div>

    <div class="str-modal" id="str-modal-rename">
        <div class="str-dialog" role="dialog" aria-modal="true" aria-labelledby="str-modal-rename-ttl">
            <div class="str-dialog-hd">
                <strong id="str-modal-rename-ttl">Renombrar grupo</strong>
                <button class="str-btn" data-close="#str-modal-rename">✕</button>
            </div>
            <div class="str-dialog-bd">
                <input id="str-inp-rename" class="str-input" type="text" placeholder="Nuevo nombre del grupo">
                <input id="str-inp-rename-grupo-id" type="hidden" value="">
                <p class="str-muted" style="margin-top:8px">El nombre debe ser único dentro de esta competición.</p>
            </div>
            <div class="str-dialog-ft" style="justify-content:space-between">
                <button class="str-btn str-btn-danger" id="str-btn-eliminar-grupo">Eliminar grupo</button>
                <div>
                    <button class="str-btn" data-close="#str-modal-rename">Cancelar</button>
                    <button class="str-btn str-btn--primary" id="str-btn-rename-confirm">Guardar</button>
                </div>
            </div>
        </div>
    </div>

    <div class="str-modal" id="str-modal-distribuir">
        <div class="str-dialog" role="dialog" aria-modal="true" aria-labelledby="str-modal-dist-ttl">
            <div class="str-dialog-hd">
                <strong id="str-modal-dist-ttl">Distribuir parejas en grupos</strong>
                <button class="str-btn" data-close="#str-modal-distribuir">✕</button>
            </div>
            <div class="str-dialog-bd">
                <div class="str-field" style="margin-bottom:10px">
                    <label class="str-muted">Política</label>
                    <select id="str-pol" class="str-input">
                        <option value="random">Aleatorio (estable con semilla)</option>
                        <option value="roundrobin">Round-robin (equilibrado)</option>
                    </select>
                </div>

                <div class="str-field" style="margin-bottom:10px">
                    <label class="str-muted">Tamaño objetivo por grupo</label>
                    <input id="str-target" class="str-input" type="number" min="2" step="1" value="4">
                </div>

                <div class="str-field" style="margin-bottom:10px">
                    <label class="str-muted">Semilla <span class="str-muted">(opcional, para repetir el reparto aleatorio)</span></label>
                    <input id="str-seed" class="str-input" type="text" placeholder="Escribe un texto o número; si repites la misma semilla, obtendrás el mismo reparto">
                </div>

                <div class="str-field" style="margin-bottom:10px">
                    <label class="str-muted"><input type="checkbox" id="str-relocate"> Recolocar parejas ya asignadas</label>
                    <p class="str-muted" style="margin:6px 0 0">
                        Desmarcado: solo reparte parejas libres. Marcado: puede mover parejas entre grupos para equilibrar.
                    </p>
                </div>

                <div id="str-prev-meta" class="str-muted" style="margin:10px 0; display:none;"></div>
                <div class="str-prev-wrap" id="str-prev-wrap" style="display:none"></div>
            </div>
            <div class="str-dialog-ft">
                <button class="str-btn" data-close="#str-modal-distribuir">Cerrar</button>
                <button class="str-btn" id="str-btn-prev">Ver vista previa</button>
                <button class="str-btn str-btn--primary" id="str-btn-apply">Aplicar</button>
            </div>
        </div>
    </div>

    <div class="str-modal" id="str-modal-confirm">
        <div class="str-dialog" role="dialog" aria-modal="true" aria-labelledby="str-modal-confirm-ttl">
            <div class="str-dialog-hd">
                <strong id="str-modal-confirm-ttl">Quitar pareja del grupo</strong>
                <button class="str-btn" data-close="#str-modal-confirm">✕</button>
            </div>
            <div class="str-dialog-bd">
                <p style="margin:0 0 8px">
                    ¿Seguro que quieres quitar <b id="str-conf-pair"></b> de <b id="str-conf-group"></b>?
                </p>
                <p class="str-muted" style="margin:6px 0 0">
                    Esta acción no elimina la pareja, solo la saca del grupo.
                </p>
                <input type="hidden" id="str-conf-gid" value="">
                <input type="hidden" id="str-conf-pid" value="">
            </div>
            <div class="str-dialog-ft">
                <button class="str-btn" data-close="#str-modal-confirm">Cancelar</button>
                <button class="str-btn str-btn-danger" id="str-conf-accept">Quitar</button>
            </div>
        </div>
    </div>

    <script>
    (function(){
        const $wrap  = document.getElementById('str-grupos-wrapper');
        if (!$wrap) return;
        const AJAX  = $wrap.dataset.ajax;
        const NONCE = $wrap.dataset.nonce;
        const COMP  = $wrap.dataset.comp;

        // Datos de nombres y estado
        const DATA = JSON.parse(document.getElementById('str-json-data').textContent || '{}');
        const GROUP_NAME = (gid) => DATA.groups?.[gid] || ('Grupo ' + gid);
        const PAIR_NAME  = (pid) => DATA.pairs?.[pid]  || ('Pareja #' + pid);

        const open  = sel => document.querySelector(sel)?.classList.add('is-open');
        const close = sel => document.querySelector(sel)?.classList.remove('is-open');

        // ===== Crear grupo =====
        document.getElementById('str-btn-crear-grupo')?.addEventListener('click', () => open('#str-modal-crear'));
        document.querySelectorAll('[data-close="#str-modal-crear"]').forEach(btn => btn.addEventListener('click', () => close('#str-modal-crear')));

        document.getElementById('str-btn-crear-confirm')?.addEventListener('click', async () => {
            const nombre = document.getElementById('str-inp-nombre-grupo').value.trim();
            const fd = new FormData();
            fd.append('action','str_grupo_crear');
            fd.append('_ajax_nonce', NONCE);
            fd.append('competicion_id', COMP);
            fd.append('nombre', nombre);
            const r = await fetch(AJAX, {method:'POST', body:fd});
            let j; try{ j = await r.json(); }catch(e){}
            if (j && j.success){
                location.reload();
            } else {
                alert((j && j.data && j.data.message) ? j.data.message : 'No se pudo crear el grupo.');
            }
        });

        // ===== Asignar pareja =====
        document.querySelectorAll('.js-add-pareja').forEach(btn => {
            btn.addEventListener('click', async () => {
                const gid = btn.dataset.grupoId;
                const select = btn.parentElement.querySelector('.js-select-pareja');
                const pid = select?.value;
                if (!pid){ alert('Selecciona una pareja libre.'); return; }

                const fd = new FormData();
                fd.append('action','str_grupo_asignar_pareja');
                fd.append('_ajax_nonce', NONCE);
                fd.append('competicion_id', COMP);
                fd.append('grupo_id', gid);
                fd.append('pareja_id', pid);

                const r = await fetch(AJAX,{method:'POST', body:fd});
                let j; try{ j = await r.json(); }catch(e){}
                if (j && j.success){ location.reload(); }
                else { alert((j && j.data && j.data.message) ? j.data.message : 'No se pudo asignar la pareja al grupo.'); }
            });
        });

        // Atajo maestro → precarga selects
        const master = document.getElementById('str-select-libres');
        if (master){
            master.addEventListener('change', () => {
                document.querySelectorAll('.js-select-pareja').forEach(sel => { sel.value = master.value || ''; });
            });
        }

        // ===== Quitar pareja (con modal propio) =====
        const modalConfirm = '#str-modal-confirm';
        const confPair  = document.getElementById('str-conf-pair');
        const confGroup = document.getElementById('str-conf-group');
        const confGid   = document.getElementById('str-conf-gid');
        const confPid   = document.getElementById('str-conf-pid');

        // abrir modal con datos
        document.querySelectorAll('.js-quitar-pareja').forEach(btn => {
            btn.addEventListener('click', () => {
                const gid = btn.dataset.grupoId;
                const pid = btn.dataset.parejaId;
                confGid.value = gid;
                confPid.value = pid;
                confPair.textContent  = PAIR_NAME(parseInt(pid,10));
                confGroup.textContent = GROUP_NAME(parseInt(gid,10));
                open(modalConfirm);
            });
        });

        // confirmar eliminación
        document.getElementById('str-conf-accept')?.addEventListener('click', async () => {
            const gid = confGid.value;
            const pid = confPid.value;
            if (!gid || !pid) return;

            const fd = new FormData();
            fd.append('action','str_grupo_quitar_pareja');
            fd.append('_ajax_nonce', NONCE);
            fd.append('grupo_id', gid);
            fd.append('pareja_id', pid);

            const r = await fetch(AJAX,{method:'POST', body:fd});
            let j; try{ j = await r.json(); }catch(e){}
            if (j && j.success){ location.reload(); }
            else { alert((j && j.data && j.data.message) ? j.data.message : 'No se pudo quitar la pareja.'); }
        });

        // ===== Editar (Renombrar / Eliminar grupo) =====
        const modalRename   = '#str-modal-rename';
        const inpRename     = document.getElementById('str-inp-rename');
        const inpRenameId   = document.getElementById('str-inp-rename-grupo-id');

        document.querySelectorAll('[data-close="#str-modal-rename"]').forEach(btn => btn.addEventListener('click', () => close(modalRename)));

        document.querySelectorAll('.js-editar-grupo').forEach(btn => {
            btn.addEventListener('click', () => {
                const gid = btn.dataset.grupoId;
                const name = btn.dataset.grupoNombre || '';
                inpRename.value = name;
                inpRenameId.value = gid;
                open(modalRename);
                setTimeout(()=>{ inpRename.focus(); }, 50);
            });
        });

        // Guardar nombre
        document.getElementById('str-btn-rename-confirm')?.addEventListener('click', async () => {
            const newName = (inpRename.value || '').trim();
            const gid     = (inpRenameId.value || '').trim();
            if (!gid) { alert('Grupo no válido'); return; }
            if (!newName) { alert('Escribe un nombre para el grupo.'); return; }

            const fd = new FormData();
            fd.append('action','str_grupo_renombrar');
            fd.append('_ajax_nonce', NONCE);
            fd.append('competicion_id', COMP);
            fd.append('grupo_id', gid);
            fd.append('nombre', newName);

            const r = await fetch(AJAX, {method:'POST', body: fd});
            let j; try{ j = await r.json(); }catch(e){}
            if (j && j.success){ location.reload(); }
            else { alert((j && j.data && j.data.msg) ? j.data.msg : (j && j.data && j.data.message) ? j.data.message : 'No se pudo renombrar el grupo.'); }
        });

        // Eliminar grupo
        document.getElementById('str-btn-eliminar-grupo')?.addEventListener('click', async () => {
            const gid = (inpRenameId.value || '').trim();
            if (!gid) { alert('Grupo no válido'); return; }
            const seguro = confirm('Esta acción enviará el grupo a la papelera y desaparecerá de la lista. ¿Continuar?');
            if (!seguro) return;

            const fd = new FormData();
            fd.append('action','str_grupo_eliminar');
            fd.append('_ajax_nonce', NONCE);
            fd.append('competicion_id', COMP);
            fd.append('grupo_id', gid);

            const r = await fetch(AJAX, {method:'POST', body: fd});
            let j; try{ j = await r.json(); }catch(e){}
            if (j && j.success){ location.reload(); }
            else { alert((j && j.data && j.data.message) ? j.data.message : 'No se pudo eliminar el grupo.'); }
        });

        // ===== Distribuir parejas (igual que antes) =====
        const modalDist = '#str-modal-distribuir';
        const polSel    = document.getElementById('str-pol');
        const targetInp = document.getElementById('str-target');
        const seedInp   = document.getElementById('str-seed');
        const relocate  = document.getElementById('str-relocate');
        const prevWrap  = document.getElementById('str-prev-wrap');
        const prevMeta  = document.getElementById('str-prev-meta');

        document.getElementById('str-btn-distribuir')?.addEventListener('click', () => {
            open(modalDist);
            prevWrap.style.display = 'none';
            prevMeta.style.display = 'none';
            prevWrap.innerHTML = '';
        });
        document.querySelectorAll('[data-close="#str-modal-distribuir"]').forEach(btn =>
            btn.addEventListener('click', () => close(modalDist))
        );

        function seededRandom(seed){
            let x = 0;
            for (let i=0;i<seed.length;i++) x = (x ^ seed.charCodeAt(i)) >>> 0;
            if (x===0) x = 0x9e3779b9;
            return function(){
                x ^= x << 13; x ^= x >>> 17; x ^= x << 5;
                return ((x>>>0) / 4294967296);
            };
        }
        function shuffle(arr, rnd){
            const a = arr.slice();
            for(let i=a.length-1;i>0;i--){
                const j = Math.floor(rnd()* (i+1));
                [a[i],a[j]] = [a[j],a[i]];
            }
            return a;
        }

        function buildPreview(){
            const target = Math.max(2, parseInt(targetInp.value||'4',10));
            const policy = polSel.value;
            const seed   = seedInp.value.trim() || (new Date().toISOString().slice(0,10));
            const rnd    = seededRandom(seed);

            const groups = Object.keys(DATA.groupsPairs || {}).map(gid => parseInt(gid,10));
            const current = {};
            groups.forEach(gid => current[gid] = (DATA.groupsPairs[gid]||[]).slice());

            const free = (DATA.freePairs||[]).slice();

            const plan = {};
            groups.forEach(gid => plan[gid] = { before: current[gid].slice(), after: current[gid].slice() });

            let candidates = [];
            if (relocate.checked){
                const all = new Set();
                free.forEach(p=>all.add(p));
                groups.forEach(gid => (current[gid]||[]).forEach(p=>all.add(p)));
                candidates = Array.from(all);
            } else {
                candidates = free.slice();
            }
            candidates = shuffle(candidates, rnd);

            if (policy === 'roundrobin'){
                let idx = 0;
                while (candidates.length){
                    const gid = groups[idx % groups.length];
                    if (plan[gid].after.length < target){
                        const p = candidates.shift();
                        if (!plan[gid].after.includes(p)){
                            if (relocate.checked){
                                groups.forEach(g => {
                                    if (g!==gid){
                                        const i = plan[g].after.indexOf(p);
                                        if (i>=0) plan[g].after.splice(i,1);
                                    }
                                });
                            }
                            plan[gid].after.push(p);
                        }
                    }
                    idx++;
                    if (groups.every(g => plan[g].after.length >= target)) break;
                }
            } else {
                groups.forEach(gid => {
                    while (plan[gid].after.length < target && candidates.length){
                        const p = candidates.shift();
                        if (relocate.checked){
                            groups.forEach(g => {
                                if (g!==gid){
                                    const i = plan[g].after.indexOf(p);
                                    if (i>=0) plan[g].after.splice(i,1);
                                }
                            });
                        }
                        if (!plan[gid].after.includes(p)){
                            plan[gid].after.push(p);
                        }
                    }
                });
            }

            prevMeta.textContent = `Grupos: ${groups.length} · Política: ${policy} · Tamaño objetivo: ${target}`;
            prevMeta.style.display = 'block';

            prevWrap.innerHTML = '';
            groups.forEach(gid => {
                const card = document.createElement('div');
                card.className = 'str-prev-card';
                card.innerHTML = `
                    <div class="str-prev-head">${GROUP_NAME(gid)}</div>
                    <div class="str-prev-body">
                        <div>
                            <div class="str-colcap">Antes</div>
                            <div class="str-chips"></div>
                        </div>
                        <div>
                            <div class="str-colcap">Después</div>
                            <div class="str-chips"></div>
                        </div>
                    </div>
                `;
                const [colBefore, colAfter] = card.querySelectorAll('.str-chips');
                (plan[gid].before || []).forEach(pid => {
                    const c = document.createElement('span');
                    c.className = 'str-chip';
                    c.textContent = PAIR_NAME(pid);
                    colBefore.appendChild(c);
                });
                (plan[gid].after || []).forEach(pid => {
                    const c = document.createElement('span');
                    c.className = 'str-chip';
                    c.textContent = PAIR_NAME(pid);
                    colAfter.appendChild(c);
                });
                prevWrap.appendChild(card);
            });
            prevWrap.style.display = 'block';

            return plan;
        }

        let lastPlan = null;
        document.getElementById('str-btn-prev')?.addEventListener('click', () => {
            lastPlan = buildPreview();
        });

        document.getElementById('str-btn-apply')?.addEventListener('click', async () => {
            if (!lastPlan) lastPlan = buildPreview();
            const moves = [];
            Object.keys(lastPlan).forEach(gid => {
                const before = lastPlan[gid].before || [];
                const after  = lastPlan[gid].after  || [];
                const add    = after.filter(p => !before.includes(p));
                const remove = before.filter(p => !after.includes(p));
                moves.push({gid: parseInt(gid,10), add, remove});
            });

            for (const m of moves){
                for (const pid of m.remove){
                    const fd = new FormData();
                    fd.append('action','str_grupo_quitar_pareja');
                    fd.append('_ajax_nonce', NONCE);
                    fd.append('grupo_id', m.gid);
                    fd.append('pareja_id', pid);
                    await fetch(AJAX,{method:'POST', body:fd});
                }
                for (const pid of m.add){
                    const fd = new FormData();
                    fd.append('action','str_grupo_asignar_pareja');
                    fd.append('_ajax_nonce', NONCE);
                    fd.append('competicion_id', COMP);
                    fd.append('grupo_id', m.gid);
                    fd.append('pareja_id', pid);
                    await fetch(AJAX,{method:'POST', body:fd});
                }
            }
            location.reload();
        });

        // Cerrar modales por overlay y ESC
        document.querySelectorAll('.str-modal').forEach(m => {
            m.addEventListener('click', (e) => { if (e.target === m) m.classList.remove('is-open'); });
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape'){
                document.querySelectorAll('.str-modal.is-open').forEach(m => m.classList.remove('is-open'));
            }
        });
    })();
    </script>

    <?php
    return ob_get_clean();
}

endif; // function_exists
