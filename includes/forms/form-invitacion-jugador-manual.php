<?php
// /includes/forms/form-invitacion-jugador-manual.php
?>

<form id="form-invitacion-jugador-manual" autocomplete="off" style="padding: 10px;">
    <div>
        <label>Nombre*</label>
        <input type="text" name="nombre" required>
    </div>
    <div>
        <label>Apellidos*</label>
        <input type="text" name="apellidos" required>
    </div>
    <div>
        <label>Email*</label>
        <input type="email" name="email" required>
    </div>
    <div>
        <label>Teléfono</label>
        <input type="text" name="telefono">
    </div>
    <input type="hidden" name="torneo_id" id="inv-torneo-id-manual" value="">
    <button type="submit" id="btn-enviar-invitacion-jugador-manual">Guardar jugador</button>
    <div id="mensaje-invitacion-jugador-manual" style="margin-top:8px;"></div>
</form>
