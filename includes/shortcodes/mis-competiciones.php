<?php
// Shortcode: [mis_competiciones]
function str_shortcode_mis_competiciones() {
    if (!is_user_logged_in() || (!current_user_can('cliente') && !current_user_can('administrator'))) {
        return '<p>Acceso restringido. Debes iniciar sesión como cliente.</p>';
    }

    ob_start();

    $current_user_id = get_current_user_id();

    $args = [
        'post_type'      => 'competicion',
        'post_status'    => 'publish',
        'author'         => $current_user_id,
        'posts_per_page' => -1,
        'orderby'        => 'date',
        'order'          => 'DESC'
    ];

    $competencias = new WP_Query($args);
    ?>

    <div class="str-dashboard-container">
        <h2 class="str-bienvenida">Mis competiciones</h2>
        <p class="str-tabla-intro">
            Aquí puedes gestionar y consultar todas las competiciones que has creado. Usa el buscador o haz clic en los encabezados para ordenar la tabla.
        </p>

        <?php if ($competencias->have_posts()): ?>
            <div class="str-tabla-header-controls">
                <div class="str-table-search-wrapper">
                    <input type="text" id="str-table-search" class="str-table-search" placeholder="Buscar competición..." autocomplete="off">
                </div>
            </div>
            <div class="str-tabla-wrapper">
                <table class="str-tabla" id="str-tabla-competicion">
                    <thead>
                        <tr>
                            <th data-sort="competicion" class="str-table-sortable">
                                Competición
                                <span class="str-table-sort-icon" data-dir="desc"><i class="fas fa-sort"></i></span>
                            </th>
                            <th data-sort="fecha" class="str-table-sortable">
                                Fecha
                                <span class="str-table-sort-icon" data-dir="desc"><i class="fas fa-sort"></i></span>
                            </th>
                            <th data-sort="jugadores" class="str-table-sortable">
                                Jugadores
                                <span class="str-table-sort-icon" data-dir="desc"><i class="fas fa-sort"></i></span>
                            </th>
                            <th>Enlace</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($competencias->have_posts()): $competencias->the_post();
                            $fecha     = get_field('fecha');
                            $jugadores = get_field('numero_jugadores');
                            ?>
                            <tr>
                                <td><?php the_title(); ?></td>
                                <td><?php echo esc_html($fecha); ?></td>
                                <td><?php echo esc_html($jugadores); ?></td>
                                <td>
                                    <a href="<?php the_permalink(); ?>" class="str-dashboard-btn str-btn-ver">Ver competición</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p>Aún no has creado ninguna competición.</p>
        <?php endif; ?>

        <?php wp_reset_postdata(); ?>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var input = document.getElementById('str-table-search');
        var table = document.getElementById('str-tabla-competicion');
        if (!table) return;

        // Filtro en vivo
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

        // Ordenar columnas
        let sortDirection = {};
        table.querySelectorAll('th.str-table-sortable').forEach(function(header, idx) {
            header.addEventListener('click', function() {
                var rows = Array.from(table.tBodies[0].rows);
                var colIdx = idx;
                var dataType = header.dataset.sort;
                sortDirection[dataType] = !sortDirection[dataType];
                var direction = sortDirection[dataType] ? 1 : -1;

                // Cambia el icono de todas las cabeceras
                table.querySelectorAll('th .str-table-sort-icon i').forEach(function(icon) {
                    icon.className = "fas fa-sort";
                });
                let currentIcon = header.querySelector('.str-table-sort-icon i');
                if (direction === 1) {
                    currentIcon.className = "fas fa-sort-up";
                } else {
                    currentIcon.className = "fas fa-sort-down";
                }

                rows.sort(function(a, b) {
                    var cellA = a.cells[colIdx].textContent.trim().toLowerCase();
                    var cellB = b.cells[colIdx].textContent.trim().toLowerCase();
                    if (dataType === "jugadores") {
                        cellA = parseInt(cellA) || 0; cellB = parseInt(cellB) || 0;
                        return direction * (cellA - cellB);
                    } else if (dataType === "fecha") {
                        var dA = cellA.replace(/(\d{2})\/(\d{2})\/(\d{4})/, '$3-$2-$1');
                        var dB = cellB.replace(/(\d{2})\/(\d{2})\/(\d{4})/, '$3-$2-$1');
                        return direction * (dA.localeCompare(dB));
                    } else {
                        return direction * cellA.localeCompare(cellB);
                    }
                });
                rows.forEach(function(row) { table.tBodies[0].appendChild(row); });
            });
        });
    });
    </script>

    <?php
    return ob_get_clean();
}
add_shortcode('mis_competiciones', 'str_shortcode_mis_competiciones');
