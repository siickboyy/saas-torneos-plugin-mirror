<?php
// Este archivo debe cargarse con require/include en la plantilla de torneo
// SOLO debe incluir el contenido INTERNO del modal (sin el div principal ni display:none)
?>
<!-- Bloque de gestión de parejas (contenido interno del modal) -->

<h2>Crear parejas para la competición</h2>
<!-- El botón de cerrar ya está en torneo.php, no hace falta repetirlo -->

<!-- Búsqueda de jugadores -->
<div class="bloque-busqueda-jugadores">
    <h3>Buscar jugadores disponibles</h3>
    <form id="form-buscar-jugadores">
        <input type="text" id="busqueda-nombre" name="busqueda_nombre" placeholder="Nombre">
        <input type="text" id="busqueda-apellido" name="busqueda_apellido" placeholder="Apellido">
        <input type="text" id="busqueda-email" name="busqueda_email" placeholder="Email">
        <button type="submit" id="btn-buscar-jugadores">Buscar</button>
    </form>

    <!-- Resultados (AJAX) -->
    <div id="tabla-jugadores-disponibles">
        <!-- Aquí se cargará la tabla de jugadores con checkboxes (por AJAX) -->
    </div>
</div>

<!-- Selección actual (pareja en preparación) -->
<div class="bloque-pareja-preparacion">
    <h3>Pareja en preparación</h3>
    <div id="jugadores-seleccionados">
        <!-- Aquí aparecerán los nombres de los dos jugadores seleccionados -->
    </div>
    <button id="btn-confirmar-pareja" disabled>Confirmar pareja</button>
</div>

<!-- Tabla de parejas ya confirmadas -->
<div class="bloque-parejas-confirmadas">
    <h3>Parejas confirmadas en este torneo</h3>
    <div id="tabla-parejas-confirmadas">
        <!-- Aquí se cargará por AJAX la lista de parejas guardadas -->
    </div>
</div>

<div class="mensaje-parejas" id="mensaje-parejas" style="display:none;">
    <!-- Aquí se mostrarán los mensajes de éxito/error -->
</div>
