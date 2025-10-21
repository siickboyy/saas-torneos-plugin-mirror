// Antiguo assets/js/form-resultado-partido.js
document.addEventListener('DOMContentLoaded', function() {
    const tablaWrap = document.querySelector('.str-partido-tabla-wrap');
    if (!tablaWrap) {
        console.log('[JS][form-resultado-partido] No hay tabla de partido en el DOM');
        return;
    }

    // === Validación min/max para inputs de sets ===
    tablaWrap.querySelectorAll('.str-set-input').forEach(input => {
        input.addEventListener('input', function(e) {
            let val = parseInt(this.value, 10);
            if (isNaN(val)) {
                this.value = '';
                return;
            }
            if (val < 0) this.value = 0;
            if (val > 7) this.value = 7;
        });
    });

    // Botón de confirmar
    const btnConfirmar = tablaWrap.querySelector('.str-btn-confirmar-resultado');
    if (!btnConfirmar) {
        console.log('[JS][form-resultado-partido] No hay botón de confirmar resultado');
    }

    if (btnConfirmar) {
        btnConfirmar.addEventListener('click', function(e) {
            e.preventDefault();

            // Recopilar datos de sets (adaptado a los nombres correctos)
            let sets = [];
            let setsTemp = {};

            // Recoger los inputs de juegos
            tablaWrap.querySelectorAll('.str-set-input').forEach(input => {
                let setIdx = input.dataset.set;
                let equipo = input.dataset.equipo;
                let valor = input.value;
                if (!setsTemp[setIdx]) setsTemp[setIdx] = {};
                setsTemp[setIdx][`juegos_equipo_${equipo}`] = valor;
            });

            // Recoger selects de tipo de set si existen
            tablaWrap.querySelectorAll('.str-set-tipo').forEach((select, idx) => {
                if (!setsTemp[idx]) setsTemp[idx] = {};
                setsTemp[idx]['tipo_set'] = select.value;
            });

            // Convertir a array y filtrar sets vacíos
            for (let i = 0; i < Object.keys(setsTemp).length; i++) {
                let s = setsTemp[i];
                // Solo añadir si hay algún valor
                if (
                    (s.juegos_equipo_1 && s.juegos_equipo_1 !== '') ||
                    (s.juegos_equipo_2 && s.juegos_equipo_2 !== '')
                ) {
                    sets.push({
                        juegos_equipo_1: s.juegos_equipo_1 || '',
                        juegos_equipo_2: s.juegos_equipo_2 || '',
                        tipo_set: s.tipo_set || 'Normal'
                    });
                }
            }

            console.log('[JS][form-resultado-partido] Sets a enviar:', sets);

            if (sets.length === 0) {
                alert('Debes introducir al menos un set con resultado.');
                console.log('[JS][form-resultado-partido] No hay sets válidos');
                return;
            }

            // Deshabilitar botón para evitar doble envío
            btnConfirmar.disabled = true;
            btnConfirmar.textContent = 'Guardando...';
            console.log('[JS][form-resultado-partido] Enviando datos vía AJAX...', {
                partido_id: btnConfirmar.dataset.partido,
                sets: sets
            });

            // Preparar datos AJAX
            const data = new FormData();
            data.append('action', 'str_guardar_resultado');
            data.append('partido_id', btnConfirmar.dataset.partido);
            data.append('sets', JSON.stringify(sets));

            // AJAX WordPress
            fetch(str_ajax_obj.ajax_url, {
                method: 'POST',
                credentials: 'same-origin',
                body: data
            })
            .then(response => response.json())
            .then(res => {
                btnConfirmar.disabled = false;
                btnConfirmar.textContent = 'Confirmar resultado';
                if (res.success) {
                    console.log('[JS][form-resultado-partido] Resultado guardado correctamente', res);
                    location.reload();
                } else {
                    console.log('[JS][form-resultado-partido] Error al guardar resultado', res);
                    alert(res.data || 'Error al guardar el resultado');
                }
            })
            .catch(err => {
                btnConfirmar.disabled = false;
                btnConfirmar.textContent = 'Confirmar resultado';
                alert('Error de red al guardar resultado');
                console.error('[JS][form-resultado-partido] Error de red', err);
            });
        });
    }

    // Puedes añadir aquí logs para eventos de UX dinámicos si vas a implementar sets dinámicos.
});
