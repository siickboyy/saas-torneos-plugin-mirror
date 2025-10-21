<?php
// /includes/forms/form-invitacion-jugador-automatica.php
?>

<form id="form-invitacion-jugador" class="form-invitacion-jugador-automatica" autocomplete="off">
    <h3>Invitar jugador al torneo</h3>
    <div class="form-group">
        <label for="inv-nombre">Nombre*</label>
        <input type="text" name="nombre" id="inv-nombre" required>
    </div>
    <div class="form-group">
        <label for="inv-apellidos">Apellidos*</label>
        <input type="text" name="apellidos" id="inv-apellidos" required>
    </div>
    <div class="form-group">
        <label for="inv-email">Email*</label>
        <input type="email" name="email" id="inv-email" required>
    </div>
    <div class="form-group">
        <label for="inv-telefono">Teléfono</label>
        <input type="text" name="telefono" id="inv-telefono">
    </div>
    <div class="form-group">
        <label for="inv-asunto">Asunto del correo*</label>
        <input type="text" name="asunto" id="inv-asunto" placeholder="Únete al torneo..." required>
    </div>
    <div class="form-group">
        <label for="inv-mensaje">Mensaje para el jugador</label>
        <textarea name="mensaje" id="inv-mensaje" placeholder="Escribe el mensaje que recibirá el jugador"></textarea>
    </div>
    <input type="hidden" name="torneo_id" id="inv-torneo-id" value="">
    <button type="submit" id="btn-enviar-invitacion-jugador" class="btn-enviar-invitacion">Enviar invitación</button>
    <div id="mensaje-invitacion-jugador" style="margin-top:8px;"></div>
</form>