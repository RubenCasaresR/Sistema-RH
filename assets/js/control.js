/* ============================================================
   Control RH - JavaScript del módulo
   ============================================================ */

(function() {
    'use strict';

    const APP = document.querySelector('meta[name="base-url"]')?.content || '';
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';

    /* === TABLERO ANUAL === */
    window.openTableroModal = function(tareaId, tareaNombre, mes, anio, estatusActual, notasActual) {
        const modal = document.getElementById('modalTablero');
        if (!modal) return;

        document.getElementById('tableroTareaId').value = tareaId;
        document.getElementById('tableroAnio').value = anio;
        document.getElementById('tableroMes').value = mes;
        document.getElementById('tableroTareaNombre').textContent = tareaNombre;
        document.getElementById('tableroMesLabel').textContent = mesNombre(mes) + ' ' + anio;
        document.getElementById('tableroNotas').value = notasActual || '';

        const btns = modal.querySelectorAll('.modal-estatus-btn');
        btns.forEach(function(btn) {
            btn.classList.remove('selected');
            if (btn.dataset.estatus === estatusActual) {
                btn.classList.add('selected');
            }
        });

        modal.classList.add('modal-open');
    };

    window.selectTableroEstatus = function(el) {
        const modal = document.getElementById('modalTablero');
        modal.querySelectorAll('.modal-estatus-btn').forEach(function(b) { b.classList.remove('selected'); });
        el.classList.add('selected');
    };

    window.saveTableroAvance = async function() {
        const modal = document.getElementById('modalTablero');
        const tareaId = document.getElementById('tableroTareaId').value;
        const anio = document.getElementById('tableroAnio').value;
        const mes = document.getElementById('tableroMes').value;
        const notas = document.getElementById('tableroNotas').value;
        const selected = modal.querySelector('.modal-estatus-btn.selected');
        if (!selected) { alert('Seleccione un estatus.'); return; }

        const estatus = selected.dataset.estatus;

        try {
            const resp = await fetch(APP + '/api/control.php?action=tablero_update', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ tarea_id: parseInt(tareaId), anio: parseInt(anio), mes: parseInt(mes), estatus: estatus, notas: notas, csrf_token: CSRF })
            });
            const data = await resp.json();
            if (data.success) {
                modal.classList.remove('modal-open');
                location.reload();
            } else {
                alert(data.message);
            }
        } catch (e) {
            alert('Error de conexión.');
        }
    };

    /* === INDICADORES === */
    window.calcularIndicadores = async function(anio, mes) {
        if (!confirm('Recalcular indicadores para ' + mesNombre(mes) + ' ' + anio + '? Esto consultará datos actuales del sistema.')) return;

        const btn = event.target.closest('button');
        if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Calculando...'; }

        try {
            const resp = await fetch(APP + '/api/control.php?action=calcular', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ anio: parseInt(anio), mes: parseInt(mes), csrf_token: CSRF })
            });
            const data = await resp.json();
            if (data.success) {
                location.reload();
            } else {
                alert(data.message);
                if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-calculator"></i> Recalcular indicadores'; }
            }
        } catch (e) {
            alert('Error de conexión.');
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-calculator"></i> Recalcular indicadores'; }
        }
    };

    /* === CHECKLIST === */
    window.saveChecklistBulk = async function() {
        const rows = document.querySelectorAll('.checklist-row');
        const items = [];
        rows.forEach(function(row) {
            const id = parseInt(row.dataset.id);
            const estatus = row.querySelector('.cl-estatus')?.value || 'na';
            const notas = row.querySelector('.cl-notas')?.value || '';
            const fecha = row.querySelector('.cl-fecha')?.value || '';
            if (id > 0) {
                items.push({ id: id, estatus: estatus, notas: notas, fecha_completado: fecha });
            }
        });

        if (items.length === 0) return;

        const btn = event.target.closest('button');
        if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Guardando...'; }

        try {
            const resp = await fetch(APP + '/api/control.php?action=checklist_bulk', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ items: items, csrf_token: CSRF })
            });
            const data = await resp.json();
            if (data.success) {
                location.reload();
            } else {
                alert(data.message);
                if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-save"></i> Guardar cambios'; }
            }
        } catch (e) {
            alert('Error de conexión.');
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-save"></i> Guardar cambios'; }
        }
    };

    /* === INCIDENCIAS === */
    window.deleteIncidencia = async function(id) {
        if (!confirm('¿Eliminar esta incidencia? Esta acción no se puede deshacer.')) return;

        try {
            const resp = await fetch(APP + '/api/control.php?action=incidencias_delete', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id, csrf_token: CSRF })
            });
            const data = await resp.json();
            if (data.success) {
                window.location.href = APP + '/modules/control/incidencias.php';
            } else {
                alert(data.message);
            }
        } catch (e) {
            alert('Error de conexión.');
        }
    };

    /* === HELPERS === */
    function mesNombre(m) {
        const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        return meses[(parseInt(m) - 1)] || '';
    }

    window.mesNombre = mesNombre;

})();
