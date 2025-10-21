<?php
/**
 * Registro del Custom Post Type "grupo" para la gestión de grupos en torneos.
 * Este CPT permitirá organizar las parejas/jugadores en grupos para la fase de grupos de cualquier competición.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Evita el acceso directo.
}

function str_registrar_cpt_grupo() {
    $labels = array(
        'name'                  => 'Grupos',
        'singular_name'         => 'Grupo',
        'menu_name'             => 'Grupos',
        'name_admin_bar'        => 'Grupo',
        'add_new'               => 'Añadir nuevo',
        'add_new_item'          => 'Añadir nuevo grupo',
        'new_item'              => 'Nuevo grupo',
        'edit_item'             => 'Editar grupo',
        'view_item'             => 'Ver grupo',
        'all_items'             => 'Todos los grupos',
        'search_items'          => 'Buscar grupos',
        'parent_item_colon'     => 'Grupo padre:',
        'not_found'             => 'No se han encontrado grupos.',
        'not_found_in_trash'    => 'No se han encontrado grupos en la papelera.',
    );

    $args = array(
        'labels'                => $labels,
        'public'                => false, // No público, solo accesible vía queries o backend.
        'show_ui'               => true,  // Se muestra en el admin para facilitar test y gestión.
        'show_in_menu'          => true,
        'menu_icon'             => 'dashicons-groups',
        'supports'              => array( 'title' ),
        'has_archive'           => false,
        'rewrite'               => false,
        'show_in_rest'          => false,
        'capability_type'       => 'post',
    );

    register_post_type( 'grupo', $args );
}
add_action( 'init', 'str_registrar_cpt_grupo' );
