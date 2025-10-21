<?php
/**
 * Personalización de columnas y filtros para el CPT "jugador_deportes"
 * Ruta: /saas-torneos-de-raqueta/includes/admin-columns/columns-jugadores.php
 */

// SOLO en admin y para el CPT correcto
add_action('admin_init', function() {
    $screen = get_current_screen();
    if (is_admin() && isset($_GET['post_type']) && $_GET['post_type'] === 'jugador_deportes') {
        add_filter('manage_jugador_deportes_posts_columns', 'str_add_columns_jugadores');
        add_action('manage_jugador_deportes_posts_custom_column', 'str_show_columns_jugadores', 10, 2);
        add_filter('manage_edit-jugador_deportes_sortable_columns', 'str_sortable_columns_jugadores');
        add_action('restrict_manage_posts', 'str_filter_columns_jugadores');
        add_filter('parse_query', 'str_columns_jugadores_filter_query');
    }
});

/**
 * Añade las nuevas columnas al listado del CPT jugador_deportes.
 * Título va primero, ID como segunda columna.
 */
function str_add_columns_jugadores($columns) {
    $new_columns = array();

    $new_columns['title'] = __('Título');
    $new_columns['id'] = 'ID';
    $new_columns['usuario_creador'] = __('Usuario creador');
    $new_columns['date'] = $columns['date'];
    return $new_columns;
}

/**
 * Muestra el contenido de cada columna personalizada.
 */
function str_show_columns_jugadores($column, $post_id) {
    switch ($column) {
        case 'id':
            echo esc_html($post_id);
            break;
        case 'usuario_creador':
            $user_id = get_post_field('post_author', $post_id);
            $user = get_userdata($user_id);
            echo $user ? esc_html($user->display_name) : '-';
            break;
    }
}

/**
 * Hace las columnas ordenables (opcional).
 */
function str_sortable_columns_jugadores($columns) {
    $columns['id'] = 'ID';
    $columns['usuario_creador'] = 'author';
    return $columns;
}

/**
 * Filtros arriba de la tabla de jugadores.
 */
function str_filter_columns_jugadores() {
    global $typenow;
    if ($typenow !== 'jugador_deportes') return;

    // Filtro por usuario creador
    wp_dropdown_users(array(
        'show_option_all' => 'Todos los usuarios',
        'name' => 'admin_jugador_user',
        'selected' => isset($_GET['admin_jugador_user']) ? intval($_GET['admin_jugador_user']) : 0,
        'include_selected' => true,
        'who' => 'authors'
    ));

    // No hay filtro por fecha aquí, ya que WordPress ya lo ofrece por defecto (arriba: "Todas las fechas")
}

/**
 * Lógica de filtrado según los dropdowns.
 */
function str_columns_jugadores_filter_query($query) {
    global $pagenow, $typenow;
    if ($pagenow === 'edit.php' && $typenow === 'jugador_deportes' && $query->is_main_query()) {
        // Filtrar por usuario
        if (!empty($_GET['admin_jugador_user'])) {
            $query->set('author', intval($_GET['admin_jugador_user']));
        }
    }
}
