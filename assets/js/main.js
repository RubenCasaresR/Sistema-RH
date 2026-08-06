/**
 * main.js — Funcionalidad global del sistema
 * Toggle de sidebar, notificaciones, helpers.
 */

const APP_URL = document.querySelector('meta[name="base-url"]')?.content || document.location.origin;

document.addEventListener('DOMContentLoaded', function () {
    initSidebar();
    initFlashMessages();
});

function initSidebar() {
    const sidebar = document.querySelector('.sidebar');
    if (!sidebar) return;
    // En dispositivos móviles, se podría añadir un toggle hamburguesa
}

function initFlashMessages() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function (alert) {
        setTimeout(function () {
            alert.style.transition = 'opacity 0.4s ease';
            alert.style.opacity = '0';
            setTimeout(function () { alert.remove(); }, 400);
        }, 5000);
    });
}

async function apiFetch(url, options = {}) {
    const defaultOptions = {
        headers: { 'Content-Type': 'application/json' },
    };
    const merged = Object.assign({}, defaultOptions, options);
    try {
        const response = await fetch(url, merged);
        const text = await response.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            data = { success: false, message: 'Respuesta inválida del servidor.' };
        }
        if (!response.ok) {
            data.success = false;
            data.status = response.status;
            if (response.status === 401) {
                window.location.href = data.redirect || APP_URL + '/modules/auth/login.php';
            }
        }
        return data;
    } catch (err) {
        console.error('API Error:', err);
        return { success: false, message: 'Error de conexión.' };
    }
}

async function apiFetchAction(module, action, data = {}) {
    return apiFetch(APP_URL + '/api/' + module + '.php?action=' + action, {
        method: 'POST',
        body: JSON.stringify(data),
    });
}

async function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]')?.content;
    if (meta) return meta;
    try {
        const r = await fetch(APP_URL + '/api/csrf.php');
        const d = await r.json();
        return d.token || '';
    } catch (e) {
        return '';
    }
}

async function downloadFile(url, opts = {}) {
    const token = await getCsrfToken();
    try {
        const response = await fetch(url, { headers: { 'X-CSRF-Token': token } });
        if (!response.ok) {
            let data = null;
            try { data = await response.json(); } catch (e) { /* ignore */ }
            if (response.status === 401) {
                window.location.href = (data && data.redirect) || APP_URL + '/modules/auth/login.php';
                return false;
            }
            alert(data && data.message ? data.message : 'No se pudo obtener el archivo.');
            return false;
        }
        const disposition = response.headers.get('Content-Disposition') || '';
        const match = disposition.match(/filename="?([^";]+)"?/i);
        const filename = match ? match[1] : 'archivo';
        const blob = await response.blob();
        const objectUrl = URL.createObjectURL(blob);
        if (opts.preview) {
            window.open(objectUrl, '_blank');
            setTimeout(() => URL.revokeObjectURL(objectUrl), 60000);
        } else {
            const a = document.createElement('a');
            a.href = objectUrl;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            a.remove();
            setTimeout(() => URL.revokeObjectURL(objectUrl), 10000);
        }
        return true;
    } catch (err) {
        console.error('Download Error:', err);
        alert('Error de conexión.');
        return false;
    }
}

function openModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.add('modal-open');
}

function closeModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.remove('modal-open');
}

// Close modal on backdrop click
document.addEventListener('click', function (e) {
    if (e.target.classList.contains('modal')) {
        e.target.classList.remove('modal-open');
    }
});

function initDarkMode() {
    var toggle = document.getElementById('darkModeToggle');
    var icon = document.getElementById('darkModeIcon');
    if (!toggle) return;
    function setDM(on) {
        document.documentElement.classList.toggle('dark-mode', on);
        localStorage.setItem('darkMode', on ? 'true' : 'false');
        toggle.checked = on;
        if (icon) icon.className = on ? 'fa-solid fa-sun' : 'fa-regular fa-moon';
    }
    setDM(localStorage.getItem('darkMode') === 'true');
    toggle.addEventListener('change', function() { setDM(this.checked); });
}
document.addEventListener('DOMContentLoaded', function() {
    initDarkMode();
});

function downloadDoc(id) {
    if (typeof id === 'object') id = id.dataset.id;
    downloadFile(APP_URL + '/api/files.php?id=' + id);
    return false;
}
