<?php
if ( ! defined('ABSPATH') ) { exit; }

class STR_Groups_V2_REST {

    public function register_routes() {
        register_rest_route('saas/v1', '/groups', [
            [
                'methods'             => WP_REST_Server::READABLE, // GET
                'callback'            => [$this, 'get_groups'],
                'permission_callback' => '__return_true',          // lectura pública
                'args'                => [
                    'competicion_id' => ['type'=>'integer','required'=>true],
                ],
            ],
        ]);

        register_rest_route('saas/v1', '/groups/standings', [
            [
                'methods'             => WP_REST_Server::READABLE, // GET
                'callback'            => [$this, 'get_standings'],
                'permission_callback' => '__return_true',
                'args'                => [
                    'competicion_id'     => ['type'=>'integer','required'=>true],
                    'incluir_propuestos' => ['type'=>'boolean','required'=>false],
                    'ppv'                => ['type'=>'integer','required'=>false],
                ],
            ],
        ]);

        register_rest_route('saas/v1', '/groups/auto-assign', [
            [
                'methods'             => WP_REST_Server::CREATABLE, // POST
                'callback'            => [$this, 'post_auto_assign'],
                'permission_callback' => [$this, 'can_manage'],
                'args'                => [
                    'competicion_id' => ['type'=>'integer','required'=>true],
                ],
            ],
        ]);

        register_rest_route('saas/v1', '/group/(?P<grupo_id>\d+)/assign', [
            [
                'methods'             => WP_REST_Server::CREATABLE, // POST
                'callback'            => [$this, 'post_assign_pair'],
                'permission_callback' => [$this, 'can_manage'],
                'args'                => [
                    'grupo_id'      => ['type'=>'integer','required'=>true],
                    'competicion_id'=> ['type'=>'integer','required'=>true],
                    'pareja_id'     => ['type'=>'integer','required'=>true],
                    'placeholder_id'=> ['type'=>'integer','required'=>false],
                ],
            ],
        ]);

        register_rest_route('saas/v1', '/bracket/volcar', [
            [
                'methods'             => WP_REST_Server::CREATABLE, // POST
                'callback'            => [$this, 'post_bracket_volcar'],
                'permission_callback' => [$this, 'can_manage'],
                'args'                => [
                    'competicion_id'     => ['type'=>'integer','required'=>true],
                    'incluir_propuestos' => ['type'=>'boolean','required'=>false],
                ],
            ],
        ]);
    }

    /* ===========================
     *  Permissions
     * =========================== */
    public function can_manage( WP_REST_Request $req ) {
        // Requerimos login + rol cliente o admin
        if ( ! is_user_logged_in() ) return false;
        if ( current_user_can('administrator') || current_user_can('cliente') ) return true;
        return false;
    }

    /* ===========================
     *  GET /groups
     * =========================== */
    public function get_groups( WP_REST_Request $req ) {
        $cid = (int) $req->get_param('competicion_id');
        if ( $cid <= 0 ) { return new WP_Error('bad_request', 'competicion_id inválido', ['status'=>400]); }

        $meta = [
            'n_grupos'   => (int) get_post_meta($cid, 'str_n_grupos', true),
            'n_parejas'  => (int) get_post_meta($cid, 'str_n_parejas', true),
            'fase_final' => (string) get_post_meta($cid, 'str_fase_final', true),
            'modo_final' => (string) get_post_meta($cid, 'str_modo_final', true),
        ];

        $grupos = $this->listar_grupos_competicion($cid);
        $todas  = $this->listar_parejas_competicion($cid);
        $libres = $this->calcular_parejas_libres($todas, $grupos);

        if ( function_exists('str_log') ) {
            str_log('GROUPS_V2/GET_GROUPS', 'OK', [
                'comp'=>$cid,'grupos'=>count($grupos),'parejas_total'=>count($todas),'libres'=>count($libres)
            ]);
        }

        return new WP_REST_Response([
            'competicion_id' => $cid,
            'meta'           => $meta,
            'grupos'         => $grupos,
            'parejas_libres' => $libres,
        ], 200);
    }

    /* ===========================
     *  GET /groups/standings
     * =========================== */
    public function get_standings( WP_REST_Request $req ) {
        $cid  = (int) $req->get_param('competicion_id');
        $prop = (bool) $req->get_param('incluir_propuestos');
        $ppv  = (int) ($req->get_param('ppv') ?: 3);
        if ( $cid <= 0 ) { return new WP_Error('bad_request', 'competicion_id inválido', ['status'=>400]); }

        $out_groups = [];
        $count_matches = 0;

        $grupos_ids = $this->listar_grupos_ids($cid);
        foreach ($grupos_ids as $gid) {
            // seguridad de pertenencia
            $comp_g = (int) get_post_meta($gid, 'competicion_id', true);
            if ( $comp_g !== $cid ) continue;

            $ginfo = $this->grupo_info($gid);

            // Inicial stats
            $stats = [];
            foreach ($ginfo['participantes'] as $p) {
                $stats[ (int)$p['id'] ] = ['pj'=>0,'pg'=>0,'pp'=>0,'pts'=>0,'sets_f'=>0,'sets_c'=>0,'juegos_f'=>0,'juegos_c'=>0];
            }

            $partidos = $this->listar_partidos_de_grupo($gid);
            foreach ($partidos as $pid) {
                if ( ! $this->contar_partido($pid, $prop) ) continue;

                $p1 = (int) get_post_meta($pid, 'pareja_1', true);
                $p2 = (int) get_post_meta($pid, 'pareja_2', true);
                if ( $p1 <= 0 || $p2 <= 0 ) continue;

                // ambos del grupo
                if ( ! $this->pareja_en_grupo($p1, $ginfo) || ! $this->pareja_en_grupo($p2, $ginfo) ) continue;

                $rows = $this->leer_resultado($pid);
                if ( $this->aplicar_resultado($stats, $p1, $p2, $rows, $ppv) ) { $count_matches++; }
            }

            // map + orden
            $items = [];
            foreach ($ginfo['participantes'] as $p) {
                $pid = (int) $p['id'];
                $st = $stats[$pid] ?? ['pj'=>0,'pg'=>0,'pp'=>0,'pts'=>0,'sets_f'=>0,'sets_c'=>0,'juegos_f'=>0,'juegos_c'=>0];
                $items[] = [
                    'pareja_id'   => $pid,
                    'title'       => $p['title'],
                    'placeholder' => (bool) $p['placeholder'],
                    'pj'          => $st['pj'],
                    'pg'          => $st['pg'],
                    'pp'          => $st['pp'],
                    'pts'         => $st['pts'],
                    'sets_f'      => $st['sets_f'],
                    'sets_c'      => $st['sets_c'],
                    'dif_sets'    => $st['sets_f'] - $st['sets_c'],
                    'juegos_f'    => $st['juegos_f'],
                    'juegos_c'    => $st['juegos_c'],
                    'dif_juegos'  => $st['juegos_f'] - $st['juegos_c'],
                ];
            }
            usort($items, [$this,'ordenar_items']);

            $out_groups[] = [
                'grupo_id' => $ginfo['id'],
                'letra'    => $ginfo['letra'],
                'items'    => $items,
            ];
        }

        if ( function_exists('str_log') ) {
            str_log('GROUPS_V2/STANDINGS', 'OK', ['comp'=>$cid,'grupos'=>count($out_groups),'partidos'=>$count_matches,'ppv'=>$ppv,'prop'=>$prop]);
        }

        return new WP_REST_Response([
            'competicion_id' => $cid,
            'grupos'         => $out_groups,
            'resumen'        => [
                'grupos_calculados' => count($out_groups),
                'partidos_contados' => $count_matches,
                'criterio_puntos'   => "victoria={$ppv}, derrota=0",
                'incluye_propuestos'=> $prop ? true : false
            ]
        ], 200);
    }

    /* ===========================
     *  POST /groups/auto-assign
     * =========================== */
    public function post_auto_assign( WP_REST_Request $req ) {
        $cid = (int) $req->get_param('competicion_id');
        if ( $cid <= 0 ) { return new WP_Error('bad_request', 'competicion_id inválido', ['status'=>400]); }

        $all_pairs = $this->listar_parejas_competicion($cid);
        $free      = $this->calcular_parejas_libres($all_pairs, $this->listar_grupos_competicion($cid));
        if ( ! empty($free) ) shuffle($free);

        $reemplazos = 0; $asignadas = 0;
        $grupos_ids = $this->listar_grupos_ids($cid);

        foreach ($grupos_ids as $gid) {
            if ( empty($free) ) break;

            $ginfo = $this->grupo_info($gid);

            $placeholders = array_values( array_map(
                fn($p) => (int)$p['id'],
                array_filter($ginfo['participantes'], fn($p) => !empty($p['placeholder']))
            ));
            if ( empty($placeholders) ) continue;

            foreach ($placeholders as $phid) {
                if ( empty($free) ) break;
                $par    = array_pop($free);
                $pidNew = (int) $par['id'];

                // Garantiza vínculo
                $meta_comp = (int) get_post_meta($pidNew, 'competicion_id', true);
                if ( $meta_comp !== $cid ) update_post_meta($pidNew, 'competicion_id', $cid);

                if ( $this->grupo_reemplazar_participante($gid, $phid, $pidNew) ) {
                    $this->partidos_reemplazar_en_grupo($gid, $phid, $pidNew);
                    $reemplazos++; $asignadas++;
                }
            }
        }

        if ( function_exists('str_log') ) {
            str_log('GROUPS_V2/AUTOASSIGN', 'OK', ['comp'=>$cid,'reemplazos'=>$reemplazos,'asignadas'=>$asignadas]);
        }

        return new WP_REST_Response([
            'resumen' => [
                'grupos_procesados'         => count($grupos_ids),
                'placeholders_reemplazados' => $reemplazos,
                'parejas_asignadas'         => $asignadas,
            ]
        ], 200);
    }

    /* ===========================
     *  POST /group/{id}/assign
     * =========================== */
    public function post_assign_pair( WP_REST_Request $req ) {
        $cid  = (int) $req->get_param('competicion_id');
        $gid  = (int) $req->get_param('grupo_id');
        $pid  = (int) $req->get_param('pareja_id');
        $ph   = (int) $req->get_param('placeholder_id');

        if ( $cid<=0 || $gid<=0 || $pid<=0 ) { return new WP_Error('bad_request', 'Parámetros inválidos', ['status'=>400]); }

        $grupo_comp = (int) get_post_meta($gid, 'competicion_id', true);
        if ( $grupo_comp !== $cid ) { return new WP_Error('bad_request','El grupo no pertenece a la competición',['status'=>400]); }

        // Evitar duplicados: si ya está en otro grupo…
        $gid_actual = $this->pareja_grupo_actual($cid, $pid);
        if ( $gid_actual > 0 && $gid_actual !== $gid ) {
            return new WP_Error('already_assigned', 'Pareja ya asignada a otro grupo', ['status'=>409, 'grupo_id'=>$gid_actual] );
        }

        $ginfo = $this->grupo_info($gid);

        if ( $ph <= 0 ) {
            foreach ($ginfo['participantes'] as $p) {
                if ( ! empty($p['placeholder']) ) { $ph = (int) $p['id']; break; }
            }
        }
        if ( $ph <= 0 ) { return new WP_Error('group_full','No hay placeholders en el grupo',['status'=>409]); }

        $es_ph = (bool) get_post_meta($ph, 'placeholder', true);
        $esta  = false;
        foreach ($ginfo['participantes'] as $p) { if ( (int)$p['id'] === $ph ) { $esta = true; break; } }
        if ( ! $es_ph || ! $esta ) { return new WP_Error('bad_request','placeholder_id no válido',['status'=>400]); }

        // Vincular pareja a comp
        if ( (int)get_post_meta($pid,'competicion_id',true) !== $cid ) update_post_meta($pid,'competicion_id',$cid);

        if ( ! $this->grupo_reemplazar_participante($gid, $ph, $pid) ) {
            return new WP_Error('server_error','No se pudo reemplazar',['status'=>500]);
        }
        $mod = $this->partidos_reemplazar_en_grupo($gid, $ph, $pid);

        if ( function_exists('str_log') ) {
            str_log('GROUPS_V2/ASSIGN', 'OK', ['comp'=>$cid,'gid'=>$gid,'pid'=>$pid,'ph'=>$ph,'partidos_mod'=>$mod]);
        }

        return new WP_REST_Response([
            'grupo' => $this->grupo_info($gid),
            'reemplazos' => ['placeholder_id'=>$ph,'pareja_id'=>$pid],
            'partidos_actualizados' => $mod,
        ], 200);
    }

    /* ===========================
     *  POST /bracket/volcar
     *  (mínimo viable; puedes ampliar después)
     * =========================== */
    public function post_bracket_volcar( WP_REST_Request $req ) {
        $cid  = (int) $req->get_param('competicion_id');
        $prop = (bool) $req->get_param('incluir_propuestos');

        if ( $cid <= 0 ) { return new WP_Error('bad_request', 'competicion_id inválido', ['status'=>400]); }

        // Buscar partidos de fase final
        $rondas = ['Octavos','Cuartos','Semifinal','Final','octavos','cuartos','semifinal','final'];
        $q = new WP_Query([
            'post_type'      => 'partido',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [
                'relation'=>'AND',
                ['key'=>'competicion_padel','value'=>$cid,'compare'=>'='],
                ['key'=>'ronda','value'=>$rondas,'compare'=>'IN'],
            ],
        ]);
        if ( ! $q->have_posts() ) {
            return new WP_REST_Response([
                'partidos_procesados'=>0,'slots_resueltos'=>0,'partidos_actualizados'=>0,'detalles'=>[]
            ], 200);
        }

        $procesados=0; $slots=0; $actualizados=0; $detalles=[];

        foreach ($q->posts as $match_id) {
            $procesados++;
            $slot_a = get_post_meta($match_id,'slot_a',true);
            $slot_b = get_post_meta($match_id,'slot_b',true);
            $p1     = (int) get_post_meta($match_id,'pareja_1',true);
            $p2     = (int) get_post_meta($match_id,'pareja_2',true);

            $asig1=false; $asig2=false;

            if ( $p1<=0 && $slot_a ) {
                $pid = $this->resolver_slot_posicional($slot_a, $cid, $prop);
                if ( $pid > 0 ) { update_post_meta($match_id,'pareja_1',$pid); $p1=$pid; $asig1=true; $slots++; }
            }
            if ( $p2<=0 && $slot_b ) {
                $pid = $this->resolver_slot_posicional($slot_b, $cid, $prop);
                if ( $pid > 0 ) { update_post_meta($match_id,'pareja_2',$pid); $p2=$pid; $asig2=true; $slots++; }
            }
            if ( $asig1 || $asig2 ) { $this->refrescar_titulo_partido($match_id); $actualizados++; }

            $detalles[] = [
                'partido_id'=>$match_id,
                'pareja_1_asignada'=>$asig1 ? $p1 : null,
                'pareja_2_asignada'=>$asig2 ? $p2 : null,
            ];
        }

        if ( function_exists('str_log') ) {
            str_log('GROUPS_V2/BRACKET', 'OK', ['comp'=>$cid,'procesados'=>$procesados,'slots'=>$slots,'actualizados'=>$actualizados]);
        }

        return new WP_REST_Response([
            'partidos_procesados'=>$procesados,
            'slots_resueltos'=>$slots,
            'partidos_actualizados'=>$actualizados,
            'detalles'=>$detalles,
        ], 200);
    }

    /* ===========================
     *  Helpers (consultas / cálculos)
     * =========================== */

    private function listar_grupos_ids($cid) {
        $q = new WP_Query([
            'post_type'      => 'grupo',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_key'       => 'orden',
            'orderby'        => 'meta_value_num',
            'order'          => 'ASC',
            'meta_query'     => [
                ['key'=>'competicion_id','value'=>$cid,'compare'=>'=']
            ]
        ]);
        return $q->have_posts() ? array_map('intval', $q->posts) : [];
    }

    private function listar_grupos_competicion($cid) {
        $out = [];
        foreach ( $this->listar_grupos_ids($cid) as $gid ) {
            $out[] = $this->grupo_info($gid);
        }
        return $out;
    }

    private function grupo_info($gid) {
        $letra = get_post_meta($gid,'letra',true);
        $tam   = (int) get_post_meta($gid,'tam',true);
        $part_ids = get_post_meta($gid,'participantes',true);
        if ( ! is_array($part_ids) ) $part_ids = [];
        $participantes = [];
        foreach ($part_ids as $pid) {
            $pid = (int) $pid; if ( ! $pid ) continue;
            $participantes[] = [
                'id'          => $pid,
                'title'       => get_the_title($pid),
                'placeholder' => (bool) get_post_meta($pid,'placeholder',true),
            ];
        }
        return [
            'id'            => (int) $gid,
            'letra'         => $letra ?: '',
            'tam'           => $tam ?: count($participantes),
            'participantes' => $participantes,
        ];
    }

    private function listar_parejas_competicion($cid) {
        $ids = [];

        // Meta directa
        $q1 = new WP_Query([
            'post_type'=>'pareja','post_status'=>'publish','posts_per_page'=>-1,'fields'=>'ids',
            'meta_query'=>[
                ['key'=>'competicion_id','value'=>$cid,'compare'=>'=']
            ]
        ]);
        if ( $q1->have_posts() ) $ids = array_merge($ids, $q1->posts);

        // Compatibilidad ACF relación serializada
        $q2 = new WP_Query([
            'post_type'=>'pareja','post_status'=>'publish','posts_per_page'=>-1,'fields'=>'ids',
            'meta_query'=>[
                'relation'=>'OR',
                ['key'=>'torneo_asociado','value'=>'"'.$cid.'"','compare'=>'LIKE'],
                ['key'=>'competicion_padel','value'=>$cid,'compare'=>'='],
            ]
        ]);
        if ( $q2->have_posts() ) $ids = array_merge($ids, $q2->posts);

        $ids = array_values( array_unique( array_map('intval', $ids) ) );
        $out = [];
        foreach ($ids as $pid) {
            $out[] = [
                'id'          => $pid,
                'title'       => get_the_title($pid),
                'placeholder' => (bool) get_post_meta($pid,'placeholder',true),
            ];
        }
        return $out;
    }

    private function calcular_parejas_libres($todas, $grupos) {
        $asignadas = [];
        foreach ($grupos as $g) {
            foreach ( $g['participantes'] as $p ) {
                $asignadas[ (int)$p['id'] ] = true;
            }
        }
        $libres = [];
        foreach ($todas as $p) {
            if ( empty( $asignadas[ (int)$p['id'] ] ) ) { $libres[] = $p; }
        }
        return $libres;
    }

    private function grupo_reemplazar_participante($gid, $ph, $nuevo) {
        $part_ids = get_post_meta($gid, 'participantes', true);
        if ( ! is_array($part_ids) ) $part_ids = [];
        $ok = false;
        foreach ($part_ids as $i => $val) {
            if ( (int)$val === (int)$ph ) { $part_ids[$i] = (int)$nuevo; $ok = true; break; }
        }
        if ( $ok ) update_post_meta($gid, 'participantes', array_map('intval', $part_ids) );
        return $ok;
    }

    private function listar_partidos_de_grupo($gid) {
        $q = new WP_Query([
            'post_type'=>'partido','post_status'=>'publish','posts_per_page'=>-1,'fields'=>'ids',
            'meta_query'=>[
                ['key'=>'grupo_id','value'=>$gid,'compare'=>'=']
            ],
        ]);
        return $q->have_posts() ? array_map('intval', $q->posts) : [];
    }

    private function contar_partido($pid, $incluir_prop=false) {
        $estado = strtolower( (string) get_post_meta($pid,'estado',true) );
        if ( $estado === 'confirmado' ) return true;
        if ( $incluir_prop && in_array($estado, ['resultado propuesto','pendiente de validación','pendiente de validacion'], true) ) return true;
        return false;
    }

    private function leer_resultado($pid) {
        if ( function_exists('get_field') ) {
            $rows = get_field('resultado_padel', $pid);
            if ( is_array($rows) ) return $rows;
        }
        $meta = get_post_meta($pid,'resultado_padel',true);
        if ( is_array($meta) ) return $meta;
        if ( is_string($meta) && $meta!=='' ) {
            $try = json_decode($meta, true);
            if ( json_last_error()===JSON_ERROR_NONE && is_array($try) ) return $try;
            $maybe = @unserialize($meta);
            if ( $maybe !== false && is_array($maybe) ) return $maybe;
        }
        return [];
    }

    private function aplicar_resultado( array &$stats, $p1, $p2, $rows, $ppv=3 ) {
        foreach ( [$p1,$p2] as $pid ) {
            if ( ! isset($stats[$pid]) ) $stats[$pid] = ['pj'=>0,'pg'=>0,'pp'=>0,'pts'=>0,'sets_f'=>0,'sets_c'=>0,'juegos_f'=>0,'juegos_c'=>0];
        }
        $s1=0;$s2=0;$g1=0;$g2=0;
        if ( is_array($rows) ) {
            foreach ($rows as $r) {
                $j1 = isset($r['juegos_equipo_1']) ? (int)$r['juegos_equipo_1'] : 0;
                $j2 = isset($r['juegos_equipo_2']) ? (int)$r['juegos_equipo_2'] : 0;
                if ( $j1===0 && $j2===0 ) continue;
                $g1 += $j1; $g2 += $j2;
                if ( $j1>$j2 ) $s1++; elseif ( $j2>$j1 ) $s2++;
            }
        }
        if ( $s1===0 && $s2===0 ) return false;

        $stats[$p1]['pj']++; $stats[$p2]['pj']++;
        $stats[$p1]['sets_f'] += $s1; $stats[$p1]['sets_c'] += $s2;
        $stats[$p2]['sets_f'] += $s2; $stats[$p2]['sets_c'] += $s1;
        $stats[$p1]['juegos_f']+= $g1; $stats[$p1]['juegos_c']+= $g2;
        $stats[$p2]['juegos_f']+= $g2; $stats[$p2]['juegos_c']+= $g1;

        if ( $s1>$s2 ) { $stats[$p1]['pg']++; $stats[$p2]['pp']++; $stats[$p1]['pts'] += $ppv; }
        elseif ( $s2>$s1 ) { $stats[$p2]['pg']++; $stats[$p1]['pp']++; $stats[$p2]['pts'] += $ppv; }

        return true;
    }

    public function ordenar_items($a,$b) {
        if ( $a['pts'] !== $b['pts'] ) return ($a['pts'] > $b['pts']) ? -1 : 1;
        $ad = $a['dif_sets']; $bd = $b['dif_sets']; if ( $ad !== $bd ) return ($ad>$bd)?-1:1;
        $aj = $a['dif_juegos']; $bj = $b['dif_juegos']; if ( $aj !== $bj ) return ($aj>$bj)?-1:1;
        if ( $a['pg'] !== $b['pg'] ) return ($a['pg'] > $b['pg']) ? -1 : 1;
        return 0;
    }

    private function pareja_en_grupo($pid, $ginfo) {
        foreach ($ginfo['participantes'] as $p) { if ( (int)$p['id'] === (int)$pid ) return true; }
        return false;
    }

    private function partidos_reemplazar_en_grupo($gid, $ph, $nuevo) {
        $q = new WP_Query([
            'post_type'=>'partido','post_status'=>'publish','posts_per_page'=>-1,'fields'=>'ids',
            'meta_query'=>[
                ['key'=>'grupo_id','value'=>$gid,'compare'=>'=']
            ],
        ]);
        if ( ! $q->have_posts() ) return 0;
        $count=0;
        foreach ($q->posts as $match_id) {
            $p1 = (int) get_post_meta($match_id,'pareja_1',true);
            $p2 = (int) get_post_meta($match_id,'pareja_2',true);
            $upd=false;
            if ( $p1 === (int)$ph ) { update_post_meta($match_id,'pareja_1',(int)$nuevo); $upd=true; }
            if ( $p2 === (int)$ph ) { update_post_meta($match_id,'pareja_2',(int)$nuevo); $upd=true; }
            if ( $upd ) { $this->refrescar_titulo_partido($match_id); $count++; }
        }
        return $count;
    }

    private function pareja_grupo_actual($cid, $pid) {
        foreach ( $this->listar_grupos_ids($cid) as $gid ) {
            $ids = get_post_meta($gid,'participantes',true);
            if ( ! is_array($ids) ) continue;
            foreach ($ids as $x) { if ( (int)$x === (int)$pid ) return (int)$gid; }
        }
        return 0;
    }

    private function resolver_slot_posicional($slot, $cid, $prop=false) {
        $slot = trim((string)$slot);
        if ( $slot === '' ) return 0;
        if ( ! preg_match('/^\s*(\d+)\s*º\s*(?:Grupo\s*)?([A-Z])\s*$/u', $slot, $m) ) return 0;
        $pos = (int) $m[1]; $letra = strtoupper($m[2]);

        $gid_obj = 0;
        foreach ( $this->listar_grupos_ids($cid) as $gid ) {
            $l = strtoupper( (string) get_post_meta($gid,'letra',true) );
            if ( $l === $letra ) { $gid_obj = $gid; break; }
        }
        if ( $gid_obj <= 0 ) return 0;

        // Standings de ese grupo
        $std = $this->get_standings( new WP_REST_Request('GET','/saas/v1/groups/standings?competicion_id='.$cid) );
        if ( $std instanceof WP_REST_Response ) {
            $data = $std->get_data();
            foreach ($data['grupos'] as $g) {
                if ( (int)$g['grupo_id'] === (int)$gid_obj ) {
                    if ( $pos >=1 && $pos <= count($g['items']) ) {
                        $item = $g['items'][$pos-1];
                        return !empty($item['placeholder']) ? 0 : (int)$item['pareja_id'];
                    }
                }
            }
        }
        return 0;
    }

    private function refrescar_titulo_partido($match_id) {
        $p1 = (int) get_post_meta($match_id,'pareja_1',true);
        $p2 = (int) get_post_meta($match_id,'pareja_2',true);
        $r  = (string) get_post_meta($match_id,'ronda',true);

        $t1 = $p1 ? get_the_title($p1) : '—';
        $t2 = $p2 ? get_the_title($p2) : '—';
        $title = ( $r ? $r . ': ' : '' ) . $t1 . ' vs ' . $t2;

        wp_update_post(['ID'=>$match_id,'post_title'=>$title]);
        return true;
    }

}
