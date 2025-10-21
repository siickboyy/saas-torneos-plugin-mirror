<?php
// CPT: Competición (torneos, rankings, ligas, etc.)

function str_register_cpt_competicion() {
    $labels = [
        'name'               => __('Competiciones', 'saas-torneos'),
        'singular_name'      => __('Competición', 'saas-torneos'),
        'add_new'            => __('Añadir nueva', 'saas-torneos'),
        'add_new_item'       => __('Añadir nueva competición', 'saas-torneos'),
        'edit_item'          => __('Editar competición', 'saas-torneos'),
        'new_item'           => __('Nueva competición', 'saas-torneos'),
        'all_items'          => __('Todas las competiciones', 'saas-torneos'),
        'view_item'          => __('Ver competición', 'saas-torneos'),
        'search_items'       => __('Buscar competiciones', 'saas-torneos'),
        'not_found'          => __('No se encontraron competiciones', 'saas-torneos'),
        'not_found_in_trash' => __('No hay competiciones en la papelera', 'saas-torneos'),
        'menu_name'          => __('Competiciones', 'saas-torneos')
    ];

    $args = [
        'labels'             => $labels,
        'public'             => true, // ✅ importante para que genere URLs
        'show_ui'            => true,
        'show_in_menu'       => true,
        'capability_type'    => 'post',
        'hierarchical'       => false,
        'supports'           => ['title'],
        'menu_position'      => 20,
        'menu_icon'          => 'dashicons-awards',
        'has_archive'        => true, // ✅ permite archivo /competicion/
        'rewrite'            => [
            'slug' => 'competicion',
            'with_front' => false
        ],
        'show_in_rest'       => false
    ];

    register_post_type('competicion', $args);
}
add_action('init', 'str_register_cpt_competicion');
