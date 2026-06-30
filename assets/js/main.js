/**
 * main.js — Funcionalidad global del sistema
 * Toggle de sidebar, notificaciones, helpers.
 */

const APP_URL = document.location.origin + '/Sistema%20RH';

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
        return await response.json();
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
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    if (!token) {
        fetch(APP_URL + '/api/csrf.php')
            .then(r => r.json())
            .then(d => { window.location.href = APP_URL + '/api/files.php?id=' + id + '&token=' + d.token; });
        return false;
    }
    window.location.href = APP_URL + '/api/files.php?id=' + id + '&token=' + token;
    return false;
}
