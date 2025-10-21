<?php
// Plantilla Perfil de Jugador - SaaS Torneos de Raqueta
// Antiguo: /templates/jugador/perfil-jugador.php
// NOTA: Maquetado estático inicial. Aquí solo HTML y comentarios. La lógica dinámica vendrá después.

?>
<!-- CSS exclusivo del perfil de jugador -->
<link rel="stylesheet" href="<?php echo STR_PLUGIN_URL . 'assets/css/perfil-jugador.css'; ?>">

<div class="perfil-jugador-wrapper">
    <!-- Cabecera con nombre del jugador -->
    <div class="perfil-jugador-header">
        <h2 id="nombre-jugador"><?php the_title(); ?></h2>
    </div>

    <!-- Tabla de estadísticas generales con selector de deporte -->
    <div class="perfil-jugador-estadisticas">
        <div class="perfil-jugador-filtros">
            <label for="selector-deporte">Deporte</label>
            <select id="selector-deporte" name="deporte">
                <option value="padel">Pádel</option>
                <option value="tenis">Tenis</option>
                <option value="pickleball">Pickleball</option>
                <option value="tenis_playa">Tenis Playa</option>
            </select>
        </div>
        <table class="tabla-estadisticas">
            <tr>
                <td>Partidos jugados</td>
                <td id="estadistica-jugados">x</td>
            </tr>
            <tr>
                <td>Partidos ganados</td>
                <td id="estadistica-ganados">x</td>
                <td id="estadistica-ganados-porcentaje">%</td>
            </tr>
            <tr>
                <td>Partidos perdidos</td>
                <td id="estadistica-perdidos">x</td>
                <td id="estadistica-perdidos-porcentaje">%</td>
            </tr>
            <tr>
                <td>Racha partidos ganados</td>
                <td id="racha-ganados">x</td>
                <td id="racha-ganados-porcentaje">%</td>
            </tr>
            <tr>
                <td>Racha partidos perdidos</td>
                <td id="racha-perdidos">x</td>
                <td id="racha-perdidos-porcentaje">%</td>
            </tr>
        </table>
    </div>

    <!-- Tabla/listado de competiciones jugadas -->
    <div class="perfil-jugador-competiciones">
        <div class="perfil-jugador-filtros">
            <label for="filtro-deporte-comp">Deporte</label>
            <select id="filtro-deporte-comp" name="deporte_comp">
                <option value="todos">Todos</option>
                <option value="padel">Pádel</option>
                <option value="tenis">Tenis</option>
                <option value="pickleball">Pickleball</option>
                <option value="tenis_playa">Tenis Playa</option>
            </select>
            <label for="filtro-ano-comp">Año</label>
            <select id="filtro-ano-comp" name="ano_comp">
                <option value="todos">Todos</option>
                <option value="2025">2025</option>
                <option value="2024">2024</option>
                <option value="2023">2023</option>
                <!-- Añadir más años dinámicamente en el futuro -->
            </select>
        </div>
        <table class="tabla-competiciones">
            <thead>
                <tr>
                    <th>Competición</th>
                    <th>Posición</th>
                    <th>Fecha</th>
                </tr>
            </thead>
            <tbody>
                <!-- Ejemplo de fila -->
                <tr>
                    <td>Torneo Madrid</td>
                    <td>3º grupo</td>
                    <td>27/09/25</td>
                </tr>
                <tr>
                    <td>Torneo Barcelona</td>
                    <td>Ganador</td>
                    <td>13/10/25</td>
                </tr>
                <!-- Aquí se cargarán dinámicamente las competiciones -->
            </tbody>
        </table>
    </div>

    <!-- Botón y pop-up de conectar jugador SOLO para administrador/cliente -->
    <?php if ( current_user_can('manage_options') || current_user_can('cliente') ) : ?>
        <button id="btn-conectar-jugador" type="button">Conectar jugador</button>

        <!-- Modal de edición de jugador (oculto por defecto) -->
        <div id="modal-editar-jugador" class="modal-editar-jugador" style="display:none;">
            <div class="modal-contenido">
                <button id="cerrar-modal-editar-jugador" class="modal-cerrar">&times;</button>
                <h3>Editar datos del jugador</h3>
                <form id="form-editar-jugador" autocomplete="off">
                    <div class="campo-form">
                        <label for="editar-nombre">Nombre</label>
                        <input type="text" id="editar-nombre" name="nombre" required>
                    </div>
                    <div class="campo-form">
                        <label for="editar-apellido">Apellido</label>
                        <input type="text" id="editar-apellido" name="apellido" required>
                    </div>
                    <div class="campo-form">
                        <label for="editar-email">Email</label>
                        <input type="email" id="editar-email" name="email" required>
                    </div>
                    <div class="campo-form">
                        <label for="editar-telefono">Teléfono</label>
                        <input type="text" id="editar-telefono" name="telefono" required>
                    </div>
                    <button type="submit" id="guardar-edicion-jugador">Guardar cambios</button>
                    <div id="mensaje-editar-jugador" style="margin-top:10px;"></div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- NOTA: La lógica dinámica, AJAX y JS se integrará en siguientes fases -->
</div>
<!-- Bloque JS y variables globales -->
<script>
window.str_ajax_obj = {
    ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>',
    nonce: '<?php echo wp_create_nonce('str_nonce'); ?>'
};
window.strJugadorId = <?php echo get_the_ID(); ?>;
</script>
<script src="<?php echo STR_PLUGIN_URL . 'assets/js/perfil-jugador.js'; ?>"></script>

