<!-- Contenedor del fondo gris -->
<div id="modal-nueva-competicion" class="str-modal-overlay" style="display: none;" data-modal-auto-open="true">
    <div class="str-modal-contenido">
        <button class="str-modal-cerrar" id="cerrar-modal-competicion">✖</button>

        <h2>Crear nueva competición</h2>
        <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="POST">
            <input type="hidden" name="action" value="str_guardar_competicion">
            <input type="hidden" name="str_crear_competicion" value="1">

            <label>Título del torneo</label>
            <input type="text" name="titulo" required>

            <label>Deporte</label>
            <select name="deporte" required>
                <option value="">Selecciona...</option>
                <option value="Padel">Pádel</option>
                <option value="Tenis">Tenis</option>
                <option value="Pickleball">Pickleball</option>
                <option value="Tenis Playa">Tenis Playa</option>
            </select>

            <label>Tipo de competición</label>
            <select name="tipo_competicion" required>
                <option value="">Selecciona...</option>
                <option value="Torneo">Torneo</option>
                <option value="Ranking">Ranking</option>
            </select>

            <label>Categoría</label>
            <select name="categoria" required>
                <option value="">Selecciona...</option>
                <option value="Masculina">Masculina</option>
                <option value="Femenina">Femenina</option>
                <option value="Mixta">Mixta</option>
            </select>

            <label>Formato</label>
            <select name="formato_competicion" required>
                <option value="">Selecciona...</option>
                <option value="Grupos">Grupos</option>
                <option value="Grupos + Bracket Final">Grupos + Bracket Final</option>
            </select>

            <label>Sistema de puntuación</label>
            <select name="sistema_puntuacion" required>
                <option value="">Selecciona...</option>
                <option value="3v-1d">Victoria 3 pts / Derrota 1 pt</option>
                <option value="3v-0d">Victoria 3 pts / Derrota 0 pts</option>
            </select>

            <label>Nº de jugadores/parejas</label>
            <input type="number" name="n_jugadores" min="2" required>

            <label>Descripción</label>
            <textarea name="descripcion_competicion" rows="3"></textarea>

            <!-- Condicionales -->
            <div class="tipo_torneo">
                <label>Fecha del torneo</label>
                <input type="date" name="fecha_torneo">

                <label>Hora inicio</label>
                <input type="time" name="hora_inicio">

                <label>Hora fin</label>
                <input type="time" name="hora_fin">
            </div>

            <div class="tipo_ranking">
                <label>Fecha de inicio</label>
                <input type="date" name="fecha_inicio">

                <label>Fecha de fin</label>
                <input type="date" name="fecha_fin">
            </div>

            <button type="submit" class="str-btn">Crear competición</button>
        </form>
    </div>
</div>
