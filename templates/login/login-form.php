<?php
// Redirección si ya está logueado
if (!is_admin() && is_user_logged_in() && !current_user_can('administrator')) {
    wp_redirect(home_url('/mis-competiciones'));
    exit;
}

?>

<form method="post" action="">
    <h3>Iniciar sesión</h3>

    <?php if (isset($_GET['login']) && $_GET['login'] == 'failed'): ?>
        <div class="str-error">Usuario o contraseña incorrectos.</div>
    <?php endif; ?>

    <label for="username">Usuario o correo electrónico</label>
    <input type="text" name="log" id="username" required>

    <label for="password">Contraseña</label>
    <input type="password" name="pwd" id="password" required>

    <input type="submit" value="Entrar">
    <input type="hidden" name="str_login_frontend" value="1">
</form>
