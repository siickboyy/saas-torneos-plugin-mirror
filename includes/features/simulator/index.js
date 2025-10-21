// /assets/js/simulador-torneo.js
// Versión: siempre "por parejas" (sin selector de tipo) y sin campo de pistas.
// Añadido: botón "Crear competición" -> guarda snapshot y redirige a /crear-competicion/?simulacion_id=ID

jQuery(document).ready(function($) {
    var $form = $('#form-simulador-torneo');
    var $msg  = $('#simulador-msg-sugerencias');
    var $res  = $('#simulador-resultados');
    var $acciones = $('#simulador-acciones');
    var $btnCrear = $('#btn-crear-competicion');

    // Estado local de la última simulación (para guardar snapshot con coherencia)
    var lastSimParams = null;   // { n_jugadores, n_grupos, fase_final, organizar_final }
    var lastSimOK = false;      // si hubo resultados HTML válidos

    function limpiarUI() {
        $msg.html('');
        $res.html('');
        $acciones.hide();
        lastSimOK = false;
        lastSimParams = null;
    }

    function ponerLoader() {
        $res.html('<div style="color:#3273f8;font-weight:500;margin:24px 0;">Calculando simulación...</div>');
    }

    function scrollToMensajes() {
        var top = $msg.offset() ? $msg.offset().top - 70 : 0;
        if (top > 0) {
            window.scrollTo({ top: top, behavior: 'smooth' });
        }
    }

    // Envío principal del simulador
    $form.on('submit', function(e) {
        e.preventDefault();

        limpiarUI();

        // Recoger datos del formulario
        var n_jugadores_raw = $('#simulador_n_jugadores').val();
        var n_jugadores     = parseInt(n_jugadores_raw, 10);
        var n_grupos_val    = $('#simulador_n_grupos').val();
        var n_grupos        = parseInt(n_grupos_val, 10) || ''; // puede venir vacío
        var fase_final      = $('#simulador_fase_final').val(); // 'final' | 'semifinal' | 'cuartos' | 'octavos'
        var organizar_final = $('input[name="simulador_organizar_final"]:checked').val(); // 'premios_grupo' | 'mezclar'

        // Validación frontend básica
        if (!n_jugadores || n_jugadores < 2) {
            $msg.html('<span class="msg-error" style="color:#d9534f;">Introduce un número de jugadores válido (mínimo 2).</span>');
            scrollToMensajes();
            return;
        }

        ponerLoader();

        var $submitBtn = $form.find('button[type="submit"]');
        var oldText = $submitBtn.text();
        $submitBtn.prop('disabled', true).text('Simulando...');

        // Llamada AJAX a WP (ya no enviamos tipo_torneo ni n_pistas)
        $.ajax({
            url: (typeof str_ajax_obj !== 'undefined') ? str_ajax_obj.ajax_url : '',
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'str_simular_torneo',
                n_jugadores: n_jugadores,
                n_grupos: n_grupos,
                fase_final: fase_final,
                organizar_final: organizar_final,
                _ajax_nonce: (typeof str_ajax_obj !== 'undefined') ? str_ajax_obj.nonce : ''
            }
        })
        .done(function(resp) {
            if (resp && resp.success && resp.data) {
                // Mensajes educativos / advertencias
                if (resp.data.sugerencias) {
                    $msg.html(resp.data.sugerencias);
                    scrollToMensajes();
                } else {
                    $msg.html('');
                }

                // Resultados (si hay incompatibilidad vendrá vacío)
                if (resp.data.resultados_html) {
                    $res.html(resp.data.resultados_html);
                    // Guardamos estado de la última simulación (para crear competición)
                    lastSimParams = {
                        n_jugadores: n_jugadores,
                        n_grupos: (resp.data.n_grupos_actual || n_grupos || ''),
                        fase_final: fase_final,
                        organizar_final: organizar_final
                    };
                    lastSimOK = true;
                    $acciones.show(); // habilitamos barra con "Crear competición"
                } else {
                    lastSimOK = false;
                    lastSimParams = {
                        n_jugadores: n_jugadores,
                        n_grupos: (resp.data && resp.data.n_grupos_actual) ? resp.data.n_grupos_actual : (n_grupos || ''),
                        fase_final: fase_final,
                        organizar_final: organizar_final
                    };
                    // Si no hay resultados, ocultamos el CTA de crear competición
                    $acciones.hide();
                    if (!resp.data.sugerencias) {
                        $res.html('<div style="color:#d9534f;">No se pudieron calcular resultados.</div>');
                    } else {
                        $res.html(''); // dejamos libre para leer el aviso
                    }
                }
            } else {
                var msg = (resp && resp.data && resp.data.msg) ? resp.data.msg : 'Error inesperado.';
                $res.html('<div style="color:#d9534f;">' + msg + '</div>');
                $acciones.hide();
                lastSimOK = false;
                lastSimParams = null;
            }
        })
        .fail(function() {
            $res.html('<div style="color:#d9534f;">Error de conexión AJAX.</div>');
            $acciones.hide();
            lastSimOK = false;
            lastSimParams = null;
        })
        .always(function() {
            $submitBtn.prop('disabled', false).text(oldText);
        });
    });

    // ─────────────────────────────────────────────────────────────────────────
    // Utilidades
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Participantes competitivos actuales (SIEMPRE por parejas):
     * total_competitivos = floor(n_jugadores / 2)
     */
    function participantesCompetitivos(n_jugadores) {
        return Math.floor((parseInt(n_jugadores, 10) || 0) / 2);
    }

    // Si no hay nº de grupos, estimamos como hace el backend (objetivo 4 por grupo)
    function estimarNumGruposSiVacio(n_grupos, total_participantes) {
        if (n_grupos && parseInt(n_grupos, 10) > 0) return parseInt(n_grupos, 10);
        return Math.max(1, Math.ceil(total_participantes / 4));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CTAs dinámicos en el aviso (incompatibilidades)
    // ─────────────────────────────────────────────────────────────────────────

    // 1) 📈 Añadir participantes y re-simular (manteniendo fijo el nº de grupos efectivo)
    $(document).on('click', '.str-btn-ajustar-jugadores', function() {
        var $btn = $(this);

        // Requisitos mínimos por grupo para la fase elegida (del backend)
        var minPorGrupo = parseInt($btn.data('min-por-grupo'), 10) || 0;
        // Nº de grupos efectivo usado en la simulación (para fijarlo y evitar el bucle)
        var gEfectivo   = parseInt($btn.data('g-efectivo'), 10) || 0;

        // Leer estado actual del formulario
        var n_jugadores  = parseInt($('#simulador_n_jugadores').val(), 10) || 0;
        var n_grupos_raw = $('#simulador_n_grupos').val();
        var n_grupos     = parseInt(n_grupos_raw, 10) || 0;

        // Determinar "G fijo" a usar:
        var total_competitivos = participantesCompetitivos(n_jugadores);
        var grupos_efectivos = gEfectivo || n_grupos || estimarNumGruposSiVacio(n_grupos, total_competitivos);

        // Fijamos el nº de grupos en el formulario
        $('#simulador_n_grupos').val(grupos_efectivos);

        // Participantes competitivos totales requeridos
        var total_competitivos_requeridos = minPorGrupo * grupos_efectivos;
        var n_jugadores_requeridos = total_competitivos_requeridos * 2;

        var nuevo_valor = Math.max(n_jugadores, n_jugadores_requeridos);
        if (!nuevo_valor || nuevo_valor < 2) nuevo_valor = 2;

        $('#simulador_n_jugadores').val(nuevo_valor);

        // Re-simular automáticamente
        $form.trigger('submit');
    });

    // 2) 🔄 Cambiar a fase compatible y re-simular
    $(document).on('click', '.str-btn-ajustar-fase', function() {
        var faseCompatible = $(this).data('fase-compatible'); // 'final' | 'semifinal' | 'cuartos' | 'octavos'
        if (faseCompatible) {
            $('#simulador_fase_final').val(String(faseCompatible));
        }
        // Re-simular automáticamente
        $form.trigger('submit');
    });

    // 3) 🧩 Ajustar a N grupos y re-simular
    $(document).on('click', '.str-btn-ajustar-grupos', function() {
        var gruposRecomendados = parseInt($(this).data('grupos-recomendados'), 10) || 0;
        if (gruposRecomendados > 0) {
            $('#simulador_n_grupos').val(gruposRecomendados);
        }
        // Re-simular automáticamente
        $form.trigger('submit');
    });

    // ─────────────────────────────────────────────────────────────────────────
    // NUEVO: Crear competición desde la simulación
    // ─────────────────────────────────────────────────────────────────────────
    $btnCrear.on('click', function(e) {
        e.preventDefault();

        // Comprobamos que existe una simulación válida previa
        if (!lastSimParams || !lastSimParams.n_jugadores) {
            $msg.html('<span class="msg-error" style="color:#d9534f;">Primero simula el torneo para poder crear la competición.</span>');
            scrollToMensajes();
            return;
        }

        // Opcional: si no hay resultados válidos, avisamos (permitimos continuar si así lo decides)
        if (!lastSimOK) {
            $msg.append('<div class="msg-warning" style="color:#e5771a;margin-top:6px;">⚠ Estás creando la competición sin resultados visualizados. Continuará con la última configuración introducida.</div>');
            scrollToMensajes();
        }

        // Botón en estado cargando
        var oldText = $btnCrear.text();
        $btnCrear.prop('disabled', true).text('Preparando…');

        // Enviamos al backend para que guarde SNAPSHOT coherente y devuelva simulacion_id
        $.ajax({
            url: (typeof str_ajax_obj !== 'undefined') ? str_ajax_obj.ajax_url : '',
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'str_guardar_simulacion',
                n_jugadores: lastSimParams.n_jugadores,
                n_grupos: lastSimParams.n_grupos || '',
                fase_final: lastSimParams.fase_final,
                organizar_final: lastSimParams.organizar_final,
                _ajax_nonce: (typeof str_ajax_obj !== 'undefined') ? str_ajax_obj.nonce : ''
            }
        })
        .done(function(resp) {
            if (resp && resp.success && resp.data && resp.data.simulacion_id) {
                // Redirigimos a la página de creación con simulacion_id
                var baseURL = (typeof str_ajax_obj !== 'undefined' && str_ajax_obj.crear_competicion_url) ? str_ajax_obj.crear_competicion_url : '/crear-competicion/';
                // Pasamos flags para prefill (si los necesitas en tu shortcode)
                var url = baseURL + (baseURL.indexOf('?') === -1 ? '?' : '&') +
                          'simulacion_id=' + encodeURIComponent(resp.data.simulacion_id) +
                          '&prefill=1';
                window.location.href = url;
            } else {
                var msg = (resp && resp.data && resp.data.msg) ? resp.data.msg : 'No se pudo guardar la simulación.';
                $msg.html('<span class="msg-error" style="color:#d9534f;">' + msg + '</span>');
                scrollToMensajes();
                $btnCrear.prop('disabled', false).text(oldText);
            }
        })
        .fail(function() {
            $msg.html('<span class="msg-error" style="color:#d9534f;">Error de conexión al guardar la simulación.</span>');
            scrollToMensajes();
            $btnCrear.prop('disabled', false).text(oldText);
        });
    });
});
