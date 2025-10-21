<?php
/**
 * Personalización de columnas y filtros para el CPT "competicion"
 * Ruta: /saas-torneos-de-raqueta/includes/admin-columns/columns-competicion.php
 */

// SOLO en admin y para el CPT correcto
add_action('admin_init', function() {
    $screen = get_current_screen();
    if (is_admin() && isset($_GET['post_type']) && $_GET['post_type'] === 'competicion') {
        // Hooks de columnas y filtros
        add_filter('manage_competicion_posts_columns', 'str_add_columns_competicion');
        add_action('manage_competicion_posts_custom_column', 'str_show_columns_competicion', 10, 2);
        add_filter('manage_edit-competicion_sortable_columns', 'str_sortable_columns_competicion');
        add_action('restrict_manage_posts', 'str_filter_columns_competicion');
        add_filter('parse_query', 'str_columns_competicion_filter_query');
    }
});

/**
 * Añade las nuevas columnas al listado del CPT competición.
 * Ahora el Título va primero y el ID es la segunda columna.
 */
function str_add_columns_competicion($columns) {
    $new_columns = array();

    // Título como primera columna
    $new_columns['title'] = __('Título');
    // ID como segunda columna
    $new_columns['id'] = 'ID';
    // El resto de columnas personalizadas
    $new_columns['usuario_creador'] = __('Usuario creador');
    $new_columns['n_jugadores'] = __('Nº jugadores');
    $new_columns['tipo_competicion'] = __('Tipo competición');
    $new_columns['deporte'] = __('Deporte');
    // Fecha siempre al final
    $new_columns['date'] = $columns['date'];
    return $new_columns;
}

/**
 * Muestra el contenido de cada columna personalizada.
 */
function str_show_columns_competicion($column, $post_id) {
    switch ($column) {
        case 'id':
            echo esc_html($post_id);
            break;
        case 'usuario_creador':
            $user_id = get_post_field('post_author', $post_id);
            $user = get_userdata($user_id);
            echo $user ? esc_html($user->display_name) : '-';
            break;
        case 'n_jugadores':
            $n_jugadores = get_field('n_jugadores', $post_id);
            echo $n_jugadores ? esc_html($n_jugadores) : '-';
            break;
        case 'tipo_competicion':
            $tipo = get_field('tipo_competicion', $post_id);
            echo $tipo ? esc_html($tipo) : '-';
            break;
        case 'deporte':
            $deporte = get_field('deporte', $post_id);
            echo $deporte ? esc_html($deporte) : '-';
            break;
    }
}

/**
 * Hace las columnas ordenables si lo necesitas (opcional, aquí solo para ID y título).
 */
function str_sortable_columns_competicion($columns) {
    $columns['id'] = 'ID';
    $columns['usuario_creador'] = 'author';
    return $columns;
}

/**
 * Añade los filtros arriba de la tabla de competiciones.
 */
function str_filter_columns_competicion() {
    global $typenow;
    if ($typenow !== 'competicion') return;

    // Filtro por usuario creador
    wp_dropdown_users(array(
        'show_option_all' => 'Todos los usuarios',
        'name' => 'admin_competicion_user',
        'selected' => isset($_GET['admin_competicion_user']) ? intval($_GET['admin_competicion_user']) : 0,
        'include_selected' => true,
        'who' => 'authors'
    ));

    // Filtro por nº jugadores
    $valores = array(4, 8, 16, 32, 64, 128, 256);
    $valor_actual = isset($_GET['admin_n_jugadores']) ? intval($_GET['admin_n_jugadores']) : '';
    echo '<select name="admin_n_jugadores"><option value="">Todos los jugadores</option>';
    foreach ($valores as $valor) {
        printf(
            '<option value="%d"%s>%d</option>',
            $valor,
            $valor == $valor_actual ? ' selected="selected"' : '',
            $valor
        );
    }
    echo '</select>';

    // Filtro por tipo de competición
    $tipos = array('torneo' => 'Torneo', 'ranking' => 'Ranking');
    $tipo_actual = isset($_GET['admin_tipo_competicion']) ? $_GET['admin_tipo_competicion'] : '';
    echo '<select name="admin_tipo_competicion"><option value="">Todos los tipos</option>';
    foreach ($tipos as $key => $label) {
        printf(
            '<option value="%s"%s>%s</option>',
            esc_attr($key),
            $key === $tipo_actual ? ' selected="selected"' : '',
            esc_html($label)
        );
    }
    echo '</select>';

    // Filtro por deporte
    $deportes = array('padel' => 'Pádel', 'tenis' => 'Tenis', 'pickleball' => 'Pickleball', 'tenis_playa' => 'Tenis Playa');
    $deporte_actual = isset($_GET['admin_deporte']) ? $_GET['admin_deporte'] : '';
    echo '<select name="admin_deporte"><option value="">Todos los deportes</option>';
    foreach ($deportes as $key => $label) {
        printf(
            '<option value="%s"%s>%s</option>',
            esc_attr($key),
            $key === $deporte_actual ? ' selected="selected"' : '',
            esc_html($label)
        );
    }
    echo '</select>';
}

/**
 * Lógica de filtrado según los dropdowns.
 */
function str_columns_competicion_filter_query($query) {
    global $pagenow, $typenow;
    if ($pagenow === 'edit.php' && $typenow === 'competicion' && $query->is_main_query()) {
        // Filtrar por usuario
        if (!empty($_GET['admin_competicion_user'])) {
            $query->set('author', intval($_GET['admin_competicion_user']));
        }
        // Filtrar por nº jugadores (ACF)
        if (!empty($_GET['admin_n_jugadores'])) {
            $meta_query = $query->get('meta_query', array());
            $meta_query[] = array(
                'key' => 'n_jugadores',
                'value' => intval($_GET['admin_n_jugadores']),
                'compare' => '='
            );
            $query->set('meta_query', $meta_query);
        }
        // Filtrar por tipo de competición (ACF)
        if (!empty($_GET['admin_tipo_competicion'])) {
            $meta_query = $query->get('meta_query', array());
            $meta_query[] = array(
                'key' => 'tipo_competicion',
                'value' => sanitize_text_field($_GET['admin_tipo_competicion']),
                'compare' => '='
            );
            $query->set('meta_query', $meta_query);
        }
        // Filtrar por deporte (ACF)
        if (!empty($_GET['admin_deporte'])) {
            $meta_query = $query->get('meta_query', array());
            $meta_query[] = array(
                'key' => 'deporte',
                'value' => sanitize_text_field($_GET['admin_deporte']),
                'compare' => '='
            );
            $query->set('meta_query', $meta_query);
        }
    }
}
