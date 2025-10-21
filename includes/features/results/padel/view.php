<?php
/** Antes:  includes/forms/form-resultado-padel.php
 * Formulario visual de introducción y validación de resultados de partido - Pádel
 * SaaS Torneos de Raqueta
 * Ruta: /includes/forms/form-resultado-padel.php
 * 
 * Recibe: $partido_id (ID del partido), $modo (opcional: 'ver' o 'editar')
 */

// Función para logs profesionales en debug-saas-torneos.log
if (!function_exists('str_escribir_log')) {
    function str_escribir_log($mensaje, $origen = '') {
        $ruta_log = plugin_dir_path(dirname(__FILE__, 2)) . 'debug-saas-torneos.log';
        $timestamp = date('[Y-m-d H:i:s]');
        $origen = $origen ? "[$origen]" : '';
        if (is_array($mensaje) || is_object($mensaje)) {
            $mensaje = print_r($mensaje, true);
        }
        $linea = "$timestamp $origen $mensaje" . PHP_EOL;
        file_put_contents($ruta_log, $linea, FILE_APPEND | LOCK_EX);
    }
}

// --- LOG DE INICIO (Para saber si se incluye el archivo) ---
str_escribir_log([
    'INICIO_FORM' => true,
    'partido_id' => isset($partido_id) ? $partido_id : 'NO DEFINIDO'
], 'FORM_PADEL');

// Comprobar seguridad básica
if (!isset($partido_id) || !get_post($partido_id)) {
    str_escribir_log('Partido no válido. $partido_id=' . (isset($partido_id) ? $partido_id : 'NO DEFINIDO'), 'FORM_PADEL');
    echo '<div class="str-error">Partido no válido</div>';
    return;
}

$partido = get_post($partido_id);
$tipo_partido = get_field('tipo_partido', $partido_id); // 'parejas' o 'individual'

// *** CAMPOS DEL GRUPO ACF DE PÁDEL ***
$estado         = get_field('estado', $partido_id); // Select: pendiente, resultado_propuesto, etc.
$ronda          = get_field('ronda', $partido_id); // Select: grupos, cuartos, etc.
$ganador_id     = get_field('ganador', $partido_id); // Relationship, ID
$propuesto_por  = get_field('propuesto_por', $partido_id); // User, ID
$observaciones  = get_field('oberservaciones', $partido_id); // Textarea
$resultado      = get_field('resultado_padel', $partido_id); // repeater array

// Nueva relación para competición de pádel
$competicion_padel = get_field('competicion_padel', $partido_id);

// LOG después de cargar campos
str_escribir_log([
    'DESPUES_CAMPOS_ACF' => true,
    'partido_id' => $partido_id,
    'estado' => $estado,
    'ronda' => $ronda,
    'ganador_id' => $ganador_id,
    'pareja_1' => get_field('pareja_1', $partido_id),
    'pareja_2' => get_field('pareja_2', $partido_id),
    'competicion_padel' => $competicion_padel,
], 'FORM_PADEL');

// Opciones de ronda predefinidas
$rondas_disponibles = [
    'Grupos'  => 'Grupos',
    'Cuartos' => 'Cuartos',
    'Semis'   => 'Semis',
    'Final'   => 'Final',
];

// Obtener nombres de equipos/jugadores y sus IDs reales
if (strtolower($tipo_partido) === 'parejas') {
    $equipo1_id_arr = get_field('pareja_1', $partido_id);
    $equipo1_id = is_array($equipo1_id_arr) ? $equipo1_id_arr[0] : $equipo1_id_arr;

    $equipo2_id_arr = get_field('pareja_2', $partido_id);
    $equipo2_id = is_array($equipo2_id_arr) ? $equipo2_id_arr[0] : $equipo2_id_arr;

    $equipo1_nombre = $equipo1_id ? get_the_title($equipo1_id) : 'N/A';
    $equipo2_nombre = $equipo2_id ? get_the_title($equipo2_id) : 'N/A';
} else {
    $equipo1_id = get_field('jugador_1', $partido_id);
    $equipo2_id = get_field('jugador_2', $partido_id);
    $equipo1_nombre = $equipo1_id ? get_the_title($equipo1_id) : 'N/A';
    $equipo2_nombre = $equipo2_id ? get_the_title($equipo2_id) : 'N/A';
}

// Mostrar SIEMPRE 3 sets para pádel
$max_sets = 3;
// Inicializar sets vacíos
$sets = [
    0 => ['juegos_equipo_1'=>'', 'juegos_equipo_2'=>'', 'tipo_set'=>'Normal'],
    1 => ['juegos_equipo_1'=>'', 'juegos_equipo_2'=>'', 'tipo_set'=>'Normal'],
    2 => ['juegos_equipo_1'=>'', 'juegos_equipo_2'=>'', 'tipo_set'=>'Normal']
];

if (is_array($resultado)) {
    foreach ($resultado as $idx => $set) {
        if ($idx < $max_sets) {
            $sets[$idx]['juegos_equipo_1'] = isset($set['juegos_equipo_1']) ? $set['juegos_equipo_1'] : '';
            $sets[$idx]['juegos_equipo_2'] = isset($set['juegos_equipo_2']) ? $set['juegos_equipo_2'] : '';
            $sets[$idx]['tipo_set']        = isset($set['tipo_set']) ? $set['tipo_set'] : 'Normal';
        }
    }
}

// Permisos y modo
$current_user_id = get_current_user_id();
$current_user_roles = function_exists('wp_get_current_user') ? wp_get_current_user()->roles : [];
$puede_editar = true; // Temporalmente a true para pruebas

// --- LOG PRE-RENDER: Confirmar datos principales antes de pintar la tabla ---
str_escribir_log([
    'RENDER_FORM' => true,
    'partido_id'   => $partido_id,
    'estado'       => $estado,
    'puede_editar' => $puede_editar,
    'equipo1_id'   => $equipo1_id,
    'equipo1_nombre' => $equipo1_nombre,
    'equipo2_id'   => $equipo2_id,
    'equipo2_nombre' => $equipo2_nombre,
    'sets'         => $sets
], 'FORM_PADEL');

// --- Mostrar el selector o badge de ronda --- //
?>
<div class="str-partido-ronda-select" style="margin-bottom: 16px;">
    <strong>Ronda:</strong>
    <?php if ($puede_editar && (in_array('administrator', $current_user_roles) || in_array('cliente', $current_user_roles))) : ?>
        <form method="post" class="form-editar-ronda" action="">
            <input type="hidden" name="action" value="str_actualizar_ronda">
            <input type="hidden" name="partido_id" value="<?php echo esc_attr($partido_id); ?>">
            <select name="ronda" class="select-ronda" style="padding:3px 6px;">
                <?php foreach ($rondas_disponibles as $val => $label) : ?>
                    <option value="<?php echo esc_attr($val); ?>" <?php selected($ronda, $val); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" style="margin-left:6px;padding:3px 10px;">Guardar</button>
        </form>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.form-editar-ronda').forEach(form => {
                form.addEventListener('submit', function(e){
                    e.preventDefault();
                    const fd = new FormData(form);
                    fetch(str_ajax_obj.ajax_url, {
                        method: 'POST',
                        credentials: 'same-origin',
                        body: fd
                    }).then(res => res.json()).then(resp => {
                        if (resp.success) location.reload();
                        else alert('Error al actualizar la ronda');
                    });
                });
            });
        });
        </script>
    <?php else: ?>
        <span style="margin-left:10px;"><?php echo esc_html($ronda ?: 'Sin definir'); ?></span>
    <?php endif; ?>
</div>

<div class="str-partido-tabla-wrap">
    <table class="str-partido-resultado">
        <thead>
            <tr>
                <th></th>
                <?php for ($i = 1; $i <= $max_sets; $i++): ?>
                    <th>Set-<?php echo $i; ?></th>
                <?php endfor; ?>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="<?php echo ($ganador_id == $equipo1_id) ? 'str-ganador' : ''; ?>">
                    <?php echo esc_html($equipo1_nombre); ?>
                </td>
                <?php foreach ($sets as $k => $set): ?>
                    <td>
                        <?php if ($puede_editar && in_array(strtolower($estado), ['pendiente', 'resultado propuesto'])): ?>
                            <input type="number"
                                   class="str-set-input"
                                   name="sets[<?php echo $k; ?>][juegos_equipo_1]"
                                   value="<?php echo esc_attr($set['juegos_equipo_1']); ?>"
                                   min="0" max="7"
                                   <?php echo (strtolower($estado) == 'confirmado') ? 'readonly' : ''; ?>
                                   data-set="<?php echo $k; ?>" data-equipo="1">
                            <select name="sets[<?php echo $k; ?>][tipo_set]" class="str-set-tipo" style="margin-left:5px;">
                                <option value="Normal" <?php selected($set['tipo_set'], 'Normal'); ?>>Normal</option>
                                <option value="Tie-Break" <?php selected($set['tipo_set'], 'Tie-Break'); ?>>Tie-Break</option>
                                <option value="Super Tie-Break" <?php selected($set['tipo_set'], 'Super Tie-Break'); ?>>Super Tie-Break</option>
                            </select>
                        <?php else: ?>
                            <?php echo ($set['juegos_equipo_1'] !== '') ? esc_html($set['juegos_equipo_1']) : '-'; ?>
                            <?php if ($set['tipo_set']) : ?>
                                <span class="str-tipo-set-label">(<?php echo esc_html($set['tipo_set']); ?>)</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                <?php endforeach; ?>
            </tr>
            <tr>
                <td class="<?php echo ($ganador_id == $equipo2_id) ? 'str-ganador' : ''; ?>">
                    <?php echo esc_html($equipo2_nombre); ?>
                </td>
                <?php foreach ($sets as $k => $set): ?>
                    <td>
                        <?php if ($puede_editar && in_array(strtolower($estado), ['pendiente', 'resultado propuesto'])): ?>
                            <input type="number"
                                   class="str-set-input"
                                   name="sets[<?php echo $k; ?>][juegos_equipo_2]"
                                   value="<?php echo esc_attr($set['juegos_equipo_2']); ?>"
                                   min="0" max="7"
                                   <?php echo (strtolower($estado) == 'confirmado') ? 'readonly' : ''; ?>
                                   data-set="<?php echo $k; ?>" data-equipo="2">
                        <?php else: ?>
                            <?php echo ($set['juegos_equipo_2'] !== '') ? esc_html($set['juegos_equipo_2']) : '-'; ?>
                        <?php endif; ?>
                    </td>
                <?php endforeach; ?>
            </tr>
        </tbody>
    </table>
    <?php if ($puede_editar && in_array(strtolower($estado), ['pendiente', 'resultado propuesto'])): ?>
        <button class="str-btn-confirmar-resultado" data-partido="<?php echo esc_attr($partido_id); ?>">
            Confirmar resultado
        </button>
    <?php elseif (strtolower($estado) === 'pendiente de validación'): ?>
        <div class="str-msg-pendiente-validacion">
            Resultado pendiente de validación por el rival.
        </div>
    <?php elseif (strtolower($estado) === 'confirmado'): ?>
        <div class="str-badge-confirmado">Resultado confirmado</div>
    <?php endif; ?>
</div>

<!-- Puedes incluir aquí el campo observaciones si lo deseas -->
<?php if ($observaciones): ?>
    <div class="str-observaciones-partido">
        <strong>Observaciones:</strong> <?php echo esc_html($observaciones); ?>
    </div>
<?php endif; ?>

<?php
// Incluir aquí scripts JS solo si hace falta (o bien encolarlo globalmente)
?>
