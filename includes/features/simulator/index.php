<?php
/**
 * Plantilla: Simulador de Torneo (por parejas)
 * Antiguo: /saas-torneos-de-raqueta/templates/torneos/simulador-torneo.php
 */

if (!defined('ABSPATH')) { exit; }

// Solo usuarios logueados
if (!is_user_logged_in()) {
    wp_redirect( wp_login_url() );
    exit;
}

/**
 * Encolar assets del simulador
 */
wp_enqueue_style(
    'str-simulador-torneo',
    plugin_dir_url(__DIR__ . '/../../') . 'assets/css/simulador-torneo.css',
    [],
    '1.0.0'
);

wp_enqueue_script(
    'str-simulador-torneo',
    plugin_dir_url(__DIR__ . '/../../') . 'assets/js/simulador-torneo.js',
    ['jquery'],
    '1.0.0',
    true
);

// Localizar AJAX URL y nonce para el JS
wp_localize_script('str-simulador-torneo', 'str_ajax_obj', [
    'ajax_url' => admin_url('admin-ajax.php'),
    'nonce'    => wp_create_nonce('str_nonce'),
    // URL de la página de creación (fallback). Si tu slug es distinto, cámbialo en el handler.
    'crear_competicion_url' => site_url('/crear-competicion/'),
]);

get_header();
?>

<div class="str-simulador-torneo">
    <!-- Columna izquierda: Formulario -->
    <div class="str-simulador-torneo-col">
        <h1>Simulador de Torneo</h1>

        <form id="form-simulador-torneo" class="str-simulador-formulario">
            <!-- Fijo por parejas: sin selector de tipo -->

            <div class="str-simulador-form-group">
                <label for="simulador_n_jugadores">Número total de jugadores</label>
                <input type="number" id="simulador_n_jugadores" name="simulador_n_jugadores" min="2" required>
            </div>

            <!-- Sin nº de pistas -->

            <div class="str-simulador-form-group">
                <label for="simulador_n_grupos">Número de grupos (opcional)</label>
                <input type="number" id="simulador_n_grupos" name="simulador_n_grupos" min="0" placeholder="Déjalo vacío para sugerencia automática">
            </div>

            <div class="str-simulador-form-group">
                <label for="simulador_fase_final">Fase final</label>
                <select id="simulador_fase_final" name="simulador_fase_final">
                    <option value="final">Final</option>
                    <option value="semifinal">Semifinal y Final</option>
                    <option value="cuartos">Cuartos, Semifinal y Final</option>
                    <option value="octavos">Octavos, Cuartos, Semifinal y Final</option>
                </select>
            </div>

            <div class="str-simulador-form-group">
                <label>Organizar cuadro final</label>
                <div class="simulador-final-opciones">
                    <label style="display:block;margin-bottom:6px;">
                        <input type="radio" name="simulador_organizar_final" value="premios_grupo" checked>
                        Premios por grupo
                        <div class="simulador-final-help" style="font-size:.9em;color:#61708f;margin:4px 0 0 26px;">
                            Cada grupo tiene su propio cuadro final (no se mezclan entre grupos).
                        </div>
                    </label>
                    <label style="display:block;">
                        <input type="radio" name="simulador_organizar_final" value="mezclar">
                        Mezclar grupos
                        <div class="simulador-final-help" style="font-size:.9em;color:#61708f;margin:4px 0 0 26px;">
                            Los clasificados de todos los grupos compiten en un único cuadro final.
                        </div>
                    </label>
                </div>
            </div>

            <button type="submit" class="str-btn-simular">Simular torneo</button>
        </form>
    </div>

    <!-- Columna derecha: Resultados -->
    <div class="str-simulador-torneo-col str-simulador-visual">
        <!-- Mensajes educativos o de advertencia -->
        <div id="simulador-msg-sugerencias" class="simulador-msg-sugerencias"></div>

        <!-- Resultados de grupos y cuadro final -->
        <div id="simulador-resultados"></div>

        <!-- NUEVO: Barra de acciones posterior a la simulación -->
        <div id="simulador-acciones" style="margin-top:16px;display:none;">
            <button id="btn-crear-competicion" class="str-btn-simular" style="max-width:280px;">
                Crear competición
            </button>
            <div style="font-size:.92em;color:#61708f;margin-top:6px;">
                Se abrirá el formulario de creación con los datos de esta simulación.
            </div>
        </div>
    </div>
</div>

<?php
get_footer();
