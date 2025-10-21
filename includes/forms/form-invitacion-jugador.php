<?php
// /includes/forms/form-invitacion-jugador.php
// Modal para añadir jugadores: selección manual o automática.
?>

<!-- Overlay para el fondo oscuro del modal -->
<div id="modal-invitacion-jugador-overlay" class="modal-invitacion-jugador-overlay" style="display:none;">
    <div class="modal-invitacion-jugador-contenedor">
        <button id="cerrar-modal-invitacion-jugador" class="cerrar-modal-invitacion-jugador" type="button">&times;</button>
        <h2 class="titulo-modal-invitacion">Añadir Jugadores</h2>
        <div class="modal-invitacion-seleccion">
            <!-- Opción manual -->
            <div class="modal-invitacion-bloque">
                <h3>Manualmente</h3>
                <div class="modal-invitacion-cuadro">
                    Añadirás los jugadores y parejas manualmente.<br>
                    No podrán subir resultados ni consultar su posición.<br>
                    Ideal para competiciones de un día sin interacción del jugador.
                </div>
                <button class="btn-seleccionar-modal-invitacion" id="btn-seleccionar-manual">
                    Seleccionar
                </button>
            </div>
            <!-- Opción automática -->
            <div class="modal-invitacion-bloque">
                <h3>Automáticamente</h3>
                <div class="modal-invitacion-cuadro">
                    Los jugadores se podrán registrar y añadir resultados,<br>
                    consultar estadísticas y posiciones.<br>
                    Ideal para competiciones donde el jugador interactúa.
                </div>
                <button class="btn-seleccionar-modal-invitacion" id="btn-seleccionar-automatica" type="button">
                    Seleccionar
                </button>
            </div>
        </div>
        <!-- Aquí debajo, JS insertará el formulario real cuando pulses "Automáticamente" -->
        <div id="modal-invitacion-jugador-dinamico"></div>
    </div>
</div>
