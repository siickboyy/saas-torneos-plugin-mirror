<?php
/**
 * Lógica personalizada de login frontend
 */

function str_procesar_login_frontend() {
    if (!isset($_POST['str_login_frontend'])) return;

    $creds = [
        'user_login'    => sanitize_user($_POST['log']),
        'user_password' => $_POST['pwd'],
        'remember'      => true
    ];

    $user = wp_signon($creds, false);

    if (is_wp_error($user)) {
        wp_redirect(add_query_arg('login', 'failed', wp_get_referer()));
        exit;
    }

    // Redirección según rol
    if (in_array('cliente', $user->roles)) {
        wp_redirect(home_url('/mis-competiciones'));
    } elseif (in_array('jugador', $user->roles)) {
        wp_redirect(home_url('/zona-jugador'));
    } else {
        wp_redirect(admin_url()); // Admin
    }

    exit;
}
add_action('init', 'str_procesar_login_frontend');

function str_procesar_registro_cliente() {
    if (!isset($_POST['str_registro_cliente'])) return;

    $username = sanitize_user($_POST['str_username']);
    $email    = sanitize_email($_POST['str_email']);
    $password = $_POST['str_password'];

    if (username_exists($username) || email_exists($email)) {
        wp_redirect(add_query_arg('registro', 'fallido', wp_get_referer()));
        exit;
    }

    $user_id = wp_create_user($username, $password, $email);

    if (is_wp_error($user_id)) {
        wp_redirect(add_query_arg('registro', 'fallido', wp_get_referer()));
        exit;
    }

    // Asignar rol cliente
    wp_update_user([
        'ID' => $user_id,
        'role' => 'cliente'
    ]);

    // Loguear y redirigir
    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id);

    wp_redirect(home_url('/mis-competiciones'));
    exit;
}
add_action('init', 'str_procesar_registro_cliente');
