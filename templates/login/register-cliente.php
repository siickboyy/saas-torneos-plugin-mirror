<?php
// Redirección si ya está logueado
if (!is_admin() && is_user_logged_in() && !current_user_can('administrator')) {
    wp_redirect(home_url('/mis-competiciones'));
    exit;
}

?>

<form method="post" action="">
    <h3>Registro de cliente</h3>

    <?php if (isset($_GET['registro']) && $_GET['registro'] == 'fallido'): ?>
        <div class="str-error">Error en el registro. El usuario o email ya existen.</div>
    <?php endif; ?>

    <label for="username">Nombre de usuario</label>
    <input type="text" name="str_username" id="username" required>

    <label for="email">Correo electrónico</label>
    <input type="email" name="str_email" id="email" required>

    <label for="password">Contraseña</label>
    <input type="password" name="str_password" id="password" required>

    <input type="submit" value="Registrarse">
    <input type="hidden" name="str_registro_cliente" value="1">
</form>
