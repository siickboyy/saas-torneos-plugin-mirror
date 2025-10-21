<?php
// Shortcode: [mis_jugadores]
function str_shortcode_mis_jugadores() {
    if (!is_user_logged_in() || (!current_user_can('cliente') && !current_user_can('administrator'))) {
        return '<p>Acceso restringido. Debes iniciar sesión como cliente.</p>';
    }

    ob_start();

    $current_user_id = get_current_user_id();

    $args = [
        'post_type'      => 'jugador_deportes',
        'post_status'    => 'publish',
        'author'         => $current_user_id,
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC'
    ];

    $jugadores = new WP_Query($args);
    ?>

    <div class="str-dashboard-container">
        <h2 class="str-bienvenida">Mis jugadores</h2>
        <p class="str-tabla-intro">Aquí puedes consultar y gestionar todos los jugadores que tienes registrados en tus competiciones. Usa el buscador o haz clic en el encabezado para ordenar la lista por nombre.
        </p>

        <?php if ($jugadores->have_posts()): ?>
            <div class="str-tabla-header-controls">
                <div class="str-table-search-wrapper">
                    <input type="text" id="str-table-search" class="str-table-search" placeholder="Buscar jugador..." autocomplete="off">
                </div>
            </div>
            <div class="str-tabla-wrapper">
                <table class="str-tabla" id="str-tabla-jugadores">
                    <thead>
                        <tr>
                            <th data-sort="nombre" class="str-table-sortable">
                                Nombre
                                <span class="str-table-sort-icon" data-dir="desc"><i class="fas fa-sort"></i></span>
                            </th>
                            <th>Email</th>
                            <th>Teléfono</th>
                            <th>Perfil</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($jugadores->have_posts()): $jugadores->the_post();
                            $email    = get_field('email');
                            $telefono = get_field('telefono');
                            $perfil_url = get_permalink(); ?>
                            <tr>
                                <td><?php the_title(); ?></td>
                                <td><?php echo esc_html($email); ?></td>
                                <td><?php echo esc_html($telefono); ?></td>
                                <td>
                                    <a href="<?php echo esc_url($perfil_url); ?>" class="str-dashboard-btn str-btn-ver">Ver perfil</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p>No tienes jugadores registrados aún.</p>
        <?php endif; ?>

        <?php wp_reset_postdata(); ?>
    </div>

    <script>
    // === Filtro en vivo por nombre de jugador ===
    document.addEventListener('DOMContentLoaded', function() {
        var input = document.getElementById('str-table-search');
        var table = document.getElementById('str-tabla-jugadores');
        if (!table) return;
        // FILTRO EN VIVO
        if(input){
            input.addEventListener('keyup', function() {
                var filter = input.value.toLowerCase();
                var trs = table.querySelectorAll("tbody tr");
                trs.forEach(function(row) {
                    var cell = row.querySelector("td");
                    if (!cell) return;
                    row.style.display = cell.textContent.toLowerCase().indexOf(filter) > -1 ? "" : "none";
                });
            });
        }

        // ORDENACION SOLO EN NOMBRE
        let sortDirection = {};
        var header = table.querySelector('th.str-table-sortable');
        if (header) {
            header.addEventListener('click', function() {
                var rows = Array.from(table.tBodies[0].rows);
                var colIdx = 0; // Nombre está en la primera columna
                var dataType = 'nombre';
                sortDirection[dataType] = !sortDirection[dataType];
                var direction = sortDirection[dataType] ? 1 : -1;

                // Cambia el icono de la cabecera
                var icon = header.querySelector('.str-table-sort-icon i');
                if (icon) {
                    icon.className = direction === 1 ? "fas fa-sort-up" : "fas fa-sort-down";
                }

                rows.sort(function(a, b) {
                    var cellA = a.cells[colIdx].textContent.trim().toLowerCase();
                    var cellB = b.cells[colIdx].textContent.trim().toLowerCase();
                    return direction * cellA.localeCompare(cellB);
                });
                rows.forEach(function(row) { table.tBodies[0].appendChild(row); });
            });
        }
    });
    </script>

    <?php
    return ob_get_clean();
}
add_shortcode('mis_jugadores', 'str_shortcode_mis_jugadores');
