/**
 * Login — Manejo del formulario de inicio de sesión.
 * Envío asíncrono (AJAX) con fetch, sin recargar la página.
 */

document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('loginForm');
    if (!form) return;

    const errorDiv = document.getElementById('loginError');
    const submitBtn = document.getElementById('loginBtn');

    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('expired') === '1') {
        showError('Sesión expirada por inactividad. Inicia sesión nuevamente.');
    }

    form.addEventListener('submit', async function (e) {
        e.preventDefault();

        const username = document.getElementById('username').value.trim();
        const password = document.getElementById('password').value;

        // Validación simple en cliente
        if (!username || !password) {
            showError('Por favor complete todos los campos.');
            return;
        }

        setLoading(true);
        hideError();

        try {
            const response = await fetch(APP_URL + '/api/auth.php?action=login', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ username: username, password: password })
            });

            const data = await response.json();

            if (data.success) {
                window.location.href = APP_URL + '/modules/reports/dashboard.php';
            } else {
                showError(data.message || 'Credenciales inválidas.');
                setLoading(false);
            }
        } catch (err) {
            showError('Error de conexión. Intente de nuevo.');
            setLoading(false);
        }
    });

    function showError(msg) {
        if (errorDiv) {
            errorDiv.textContent = msg;
            errorDiv.style.display = 'block';
        }
    }

    function hideError() {
        if (errorDiv) {
            errorDiv.style.display = 'none';
            errorDiv.textContent = '';
        }
    }

    function setLoading(loading) {
        if (!submitBtn) return;
        submitBtn.disabled = loading;
        submitBtn.textContent = loading ? 'Entrando...' : 'Entrar';
    }
});
