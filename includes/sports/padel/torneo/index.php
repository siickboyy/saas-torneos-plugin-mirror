<?php
/**
 * Template: Ficha de Torneo (frontend) — PÁDEL (adaptada a tus ACF)
 * Ruta: wp-content/plugins/saas-torneos-de-raqueta/includes/sports/padel/torneo/index.php
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

get_header();

global $post;
$torneo_id = $post ? (int) $post->ID : 0;


// URL de edición frontend (permite filtrar desde el plugin/tema)
$edit_url = apply_filters( 'saas_tr/torneo_edit_url', '', $torneo_id );

/**
 * Helpers
 */
function saas_tr_val($v){ return ($v !== '' && $v !== null) ? $v : ''; }
function saas_tr_text($v){ $v = saas_tr_val($v); return $v !== '' ? esc_html($v) : '—'; }

/**
 * ACF “planos” de competición
 */
$acf_deporte    = function_exists('get_field') ? get_field('deporte', $torneo_id) : '';
$acf_tipo       = function_exists('get_field') ? get_field('tipo_competicion', $torneo_id) : '';
$acf_categoria  = function_exists('get_field') ? get_field('categoria', $torneo_id) : '';
$acf_formato    = function_exists('get_field') ? get_field('formato_competicion', $torneo_id) : '';
$acf_sistema    = function_exists('get_field') ? get_field('sistema_puntuacion', $torneo_id) : '';
$acf_njug       = function_exists('get_field') ? get_field('n_jugadores', $torneo_id) : '';
$acf_desc       = function_exists('get_field') ? get_field('descripcion_competicion', $torneo_id) : '';

/**
 * Grupo ACF: tipo_competicion_torneo (fecha/hora)
 */
$grp_torneo     = function_exists('get_field') ? get_field('tipo_competicion_torneo', $torneo_id) : '';
$fecha          = '';
$hora_i         = '';
$hora_f         = '';

if (is_array($grp_torneo) && !empty($grp_torneo)) {
    $fecha  = saas_tr_val( $grp_torneo['fecha_torneo'] ?? '' );
    $hora_i = saas_tr_val( $grp_torneo['hora_inicio']   ?? '' );
    $hora_f = saas_tr_val( $grp_torneo['hora_fin']      ?? '' );

    if ($fecha === '') {
        foreach (['fecha','fecha_evento','dia'] as $k) {
            if (!empty($grp_torneo[$k])) { $fecha = $grp_torneo[$k]; break; }
        }
    }
    if ($hora_i === '') {
        foreach (['hora_inicio','hora_incio','inicio','hora_inicio_torneo'] as $k) {
            if (!empty($grp_torneo[$k])) { $hora_i = $grp_torneo[$k]; break; }
        }
    }
    if ($hora_f === '') {
        foreach (['hora_fin','fin','hora_fin_torneo'] as $k) {
            if (!empty($grp_torneo[$k])) { $hora_f = $grp_torneo[$k]; break; }
        }
    }
}

// Fallback metas planas si no vino del grupo
if ($fecha === '')  { $fecha  = saas_tr_val( get_post_meta($torneo_id, 'fecha', true) ); }
if ($hora_i === '') { $hora_i = saas_tr_val( get_post_meta($torneo_id, 'hora_inicio', true) ); }
if ($hora_f === '') { $hora_f = saas_tr_val( get_post_meta($torneo_id, 'hora_fin', true) ); }

?>
<div class="saas-tr-contenedor saas-tr-torneo" id="saas-tr-torneo"
     data-torneo-id="<?php echo esc_attr( $torneo_id ); ?>">

    <h1 class="saas-tr-titulo"><?php echo esc_html( get_the_title() ); ?></h1>

    <ul class="saas-tr-meta">
        <li><strong><?php esc_html_e( 'Deporte', 'saas-torneos' ); ?>:</strong> <?php echo saas_tr_text($acf_deporte); ?></li>
        <li><strong><?php esc_html_e( 'Tipo de competición', 'saas-torneos' ); ?>:</strong> <?php echo saas_tr_text($acf_tipo); ?></li>
        <li><strong><?php esc_html_e( 'Categoría', 'saas-torneos' ); ?>:</strong> <?php echo saas_tr_text($acf_categoria); ?></li>
        <li><strong><?php esc_html_e( 'Formato', 'saas-torneos' ); ?>:</strong> <?php echo saas_tr_text($acf_formato); ?></li>
        <li><strong><?php esc_html_e( 'Sistema de puntuación', 'saas-torneos' ); ?>:</strong> <?php echo saas_tr_text($acf_sistema); ?></li>
        <li><strong><?php esc_html_e( 'Nº jugadores', 'saas-torneos' ); ?>:</strong> <?php echo saas_tr_text($acf_njug); ?></li>
        <li><strong><?php esc_html_e( 'Fecha', 'saas-torneos' ); ?>:</strong> <?php echo saas_tr_text($fecha); ?></li>
        <li><strong><?php esc_html_e( 'Horario', 'saas-torneos' ); ?>:</strong>
            <?php
            $horario = [];
            if ( $hora_i ) { $horario[] = $hora_i; }
            if ( $hora_f ) { $horario[] = $hora_f; }
            echo $horario ? esc_html( implode( ' — ', $horario ) ) : '—';
            ?>
        </li>
        <li><strong><?php esc_html_e( 'Código del torneo', 'saas-torneos' ); ?>:</strong> <?php echo (int) $torneo_id; ?></li>
    </ul>

    <?php if ($acf_desc): ?>
        <div class="saas-tr-descripcion">
            <?php echo wp_kses_post( wpautop( $acf_desc ) ); ?>
        </div>
    <?php endif; ?>

    <p>
        <button type="button"
                id="btn-editar-torneo"
                class="saas-tr-btn saas-tr-btn--light"
                data-torneo-id="<?php echo esc_attr( $torneo_id ); ?>">
            <?php esc_html_e( 'Editar torneo', 'saas-torneos' ); ?>
        </button>
    </p>


    <!-- === PAREJAS === -->
    <section class="saas-tr-bloque saas-tr-bloque--parejas" id="saas-tr-parejas">
        <h2><?php esc_html_e( 'Parejas', 'saas-torneos' ); ?></h2>
        <div class="saas-tr-acciones">
            <button type="button" class="saas-tr-btn js-add-pareja"><?php esc_html_e( '+ Añadir pareja', 'saas-torneos' ); ?></button>
            <button type="button" class="saas-tr-btn js-add-jugador"><?php esc_html_e( '+ Añadir jugador', 'saas-torneos' ); ?></button>
        </div>

        <div class="saas-tr-tabla-wrap">
            <table class="saas-tr-tabla" id="tabla-parejas">
                <thead>
                <tr>
                    <th><?php esc_html_e( 'Nº', 'saas-torneos' ); ?></th>
                    <th><?php esc_html_e( 'Jugador 1', 'saas-torneos' ); ?></th>
                    <th><?php esc_html_e( 'Jugador 2', 'saas-torneos' ); ?></th>
                    <th><?php esc_html_e( 'Acciones', 'saas-torneos' ); ?></th>
                </tr>
                </thead>
                <tbody id="saas-tr-parejas-body"><!-- Relleno por JS (pairs/index.js) --></tbody>
            </table>
        </div>
    </section>

    <!-- === GESTIÓN DE GRUPOS === -->
    <?php
// 1) incluir el renderer (solo una vez)
require_once WP_PLUGIN_DIR . '/saas-torneos-de-raqueta/includes/features/groups/render-grupos.php';

// 2) resolver el ID de la competición que estás mostrando en este template
//    adapta esto a tu variable real; a modo de ejemplo:
$comp_id = isset($post) && $post->post_type === 'competicion' ? (int)$post->ID : 0;
// o si ya tienes la variable $torneo_id / $competicion_id úsala directamente.

// 3) renderizar
echo str_competicion_grupos_render([
  'competicion_id' => $comp_id, // <-- pon aquí tu ID real
  'print_css'      => true       // o false si prefieres usar tus estilos
]);
?>
    <section class="saas-tr-bloque saas-tr-bloque--grupos" id="saas-tr-grupos"
             data-action-cargar="saas_grupos_cargar"
             data-action-recalcular="saas_grupos_standings"
             data-action-asignar-pareja="saas_grupo_asignar_pareja">
        <h2><?php esc_html_e( 'Gestión de grupos', 'saas-torneos' ); ?></h2>

        <button type="button" class="saas-tr-btn saas-tr-btn--primary js-recalcular-standings">
            <?php esc_html_e( 'Recalcular clasificación', 'saas-torneos' ); ?>
        </button>

        <div id="saas-tr-grupos-libres" class="saas-tr-seccion">
            <h3><?php esc_html_e( 'Grupos y Parejas libres', 'saas-torneos' ); ?></h3>
            <div class="saas-tr-hint"><?php esc_html_e( 'Cargando grupos y parejas libres…', 'saas-torneos' ); ?></div>
            <div class="saas-tr-grid" data-slot="grupos"><!-- JS (módulo groups) --></div>
            <div class="saas-tr-grid" data-slot="parejas-libres"><!-- JS (módulo groups) --></div>
        </div>

        <div id="saas-tr-standings" class="saas-tr-seccion">
            <h3><?php esc_html_e( 'Clasificación', 'saas-torneos' ); ?></h3>
            <div class="saas-tr-hint"><?php esc_html_e( 'Calculando standings…', 'saas-torneos' ); ?></div>
            <div class="saas-tr-grid" data-slot="standings"><!-- JS (módulo groups) --></div>
        </div>

        <div id="saas-tr-bracket" class="saas-tr-seccion">
            <h3><?php esc_html_e( 'Fase final (Bracket)', 'saas-torneos' ); ?></h3>
            <div class="saas-tr-hint"><?php esc_html_e( 'Acciones para volcar la fase final con los clasificados actuales.', 'saas-torneos' ); ?></div>
            <div class="saas-tr-grid" data-slot="bracket"><!-- JS (módulo groups) --></div>
        </div>
    </section>

    <!-- === PARTIDOS & RESULTADOS (vista co-localizada) === -->
    <section class="saas-tr-bloque saas-tr-bloque--partidos" id="saas-tr-partidos">
        <h2><?php esc_html_e( 'Partidos del torneo', 'saas-torneos' ); ?></h2>

        <?php
        // Consulta de partidos asociados a este torneo
        // ACF del CPT partido: meta_key 'competicion_padel' con ID del torneo
        $args = [
            'post_type'      => 'partido',
            'posts_per_page' => -1,
            'post_status'    => ['publish','pending','draft'],
            'meta_query'     => [
                [
                    'key'     => 'competicion_padel',
                    'value'   => $torneo_id,
                    'compare' => '='
                ]
            ],
            'orderby' => 'date',
            'order'   => 'DESC',
        ];
        $q_partidos = new WP_Query($args);

        if ($q_partidos->have_posts()):
            ?>
            <div class="saas-tr-lista-partidos">
                <?php
                while ($q_partidos->have_posts()): $q_partidos->the_post();
                    $partido_id = (int) get_the_ID();

                    // Incluimos la vista co-localizada del módulo de resultados (Pádel).
                    $view_resultado = STR_PLUGIN_PATH . 'includes/features/results/padel/view.php';
                    if (file_exists($view_resultado)) {
                        $GLOBALS['saas_tr_partido_id'] = $partido_id;
                        include $view_resultado;
                        unset($GLOBALS['saas_tr_partido_id']);
                    } else {
                        echo '<div class="saas-tr-hint">'.esc_html__('Vista de resultado no encontrada.', 'saas-torneos').'</div>';
                    }
                endwhile;
                wp_reset_postdata();
                ?>
            </div>
        <?php else: ?>
            <div class="saas-tr-hint">
                <?php esc_html_e( 'No hay partidos creados para este torneo.', 'saas-torneos' ); ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- ====== MODALES (WRAPPERS) ====== -->

    <!-- Modal: PAREJAS MULTISELECCIÓN (contenido incrustado) -->
    <div id="modal-parejas-multiseleccion" class="saas-tr-modal" aria-hidden="true" style="display:none;">
        <div class="saas-tr-modal__backdrop js-close-modal" data-close="overlay"></div>

        <div class="saas-tr-modal__dialog" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e('Crear parejas para la competición', 'saas-torneos'); ?>">
            <button type="button" class="saas-tr-modal__close js-close-modal" aria-label="<?php esc_attr_e('Cerrar', 'saas-torneos'); ?>">×</button>

            <div class="saas-tr-modal__content" id="modal-parejas-multiseleccion-content">
                <?php
                // El JS de pairs NO descarga por AJAX: necesita el HTML aquí dentro.
                $form_parejas = STR_PLUGIN_PATH . 'includes/forms/form-parejas-multiseleccion.php';
                if ( file_exists($form_parejas) ) {
                    include $form_parejas;
                } else {
                    echo '<div class="saas-tr-hint">'.esc_html__('No se encontró el formulario de parejas.', 'saas-torneos').'</div>';
                }
                ?>
            </div>
        </div>
    </div>

    <!-- Modal: INVITACIÓN JUGADOR (el contenido lo inyecta players por AJAX) -->
    <div id="modal-invitacion-jugador" class="saas-tr-modal" aria-hidden="true" style="display:none;">
        <div class="saas-tr-modal__backdrop js-close-modal" data-close="overlay"></div>

        <div class="saas-tr-modal__dialog" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e('Añadir jugadores', 'saas-torneos'); ?>">
            <button type="button" class="saas-tr-modal__close js-close-modal" aria-label="<?php esc_attr_e('Cerrar', 'saas-torneos'); ?>">×</button>

            <div class="saas-tr-modal__content" id="modal-invitacion-jugador-content"><!-- AJAX (players) --></div>
        </div>
    </div>

</div>

<!-- Modal Editar Torneo (sin estilos inline; lo estiliza tournaments/index.css) -->
<div id="modal-editar-torneo" class="saas-tr-modal saas-tr-modal--editar" style="display:none;">
  <div class="saas-tr-modal__backdrop js-close-modal" data-close="overlay"></div>

  <div class="saas-tr-modal__dialog" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e('Editar torneo', 'saas-torneos'); ?>">
    <button type="button" id="cerrar-modal-editar" class="saas-tr-modal__close js-close-modal" aria-label="<?php esc_attr_e('Cerrar', 'saas-torneos'); ?>">×</button>

    <h3 class="saas-tr-modal__title"><?php esc_html_e('Editar torneo', 'saas-torneos'); ?></h3>

    <div class="saas-tr-form-grid">
      <div class="saas-tr-form-row saas-tr-form-row--full">
        <label class="saas-tr-label"><?php esc_html_e('Título', 'saas-torneos'); ?></label>
        <input type="text" name="titulo" value="<?php echo esc_attr(get_the_title($torneo_id)); ?>" class="saas-tr-input">
      </div>

      <div class="saas-tr-form-row saas-tr-form-row--full">
        <label class="saas-tr-label"><?php esc_html_e('Descripción', 'saas-torneos'); ?></label>
        <textarea name="descripcion" rows="4" class="saas-tr-textarea"><?php
            echo esc_textarea( $acf_desc ?: '' );
        ?></textarea>
      </div>

      <div class="saas-tr-form-row">
        <label class="saas-tr-label"><?php esc_html_e('Fecha', 'saas-torneos'); ?></label>
        <input type="date" name="fecha_torneo" value="<?php echo esc_attr($fecha); ?>" class="saas-tr-input">
      </div>

      <div class="saas-tr-form-row">
        <label class="saas-tr-label"><?php esc_html_e('Hora inicio', 'saas-torneos'); ?></label>
        <input type="time" name="hora_inicio" value="<?php echo esc_attr($hora_i); ?>" class="saas-tr-input">
      </div>

      <div class="saas-tr-form-row">
        <label class="saas-tr-label"><?php esc_html_e('Hora fin', 'saas-torneos'); ?></label>
        <input type="time" name="hora_fin" value="<?php echo esc_attr($hora_f); ?>" class="saas-tr-input">
      </div>
    </div>

    <div class="saas-tr-modal__actions">
      <button type="button" id="guardar-torneo"
              data-torneo-id="<?php echo esc_attr( $torneo_id ); ?>"
              class="saas-tr-btn saas-tr-btn--primary">
        <?php esc_html_e('Guardar cambios', 'saas-torneos'); ?>
      </button>
      <button type="button" id="cancelar-editar-torneo" class="saas-tr-btn saas-tr-btn--light js-close-modal">
        <?php esc_html_e('Cancelar', 'saas-torneos'); ?>
      </button>
    </div>
  </div>
</div>

<?php
get_footer();
