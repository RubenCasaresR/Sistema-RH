/**
 * Login — Manejo del formulario de inicio de sesión.
 * Toggle de contraseña, remember me, animaciones, envío AJAX.
 */

document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('loginForm');
    if (!form) return;

    const errorDiv = document.getElementById('loginError');
    const submitBtn = document.getElementById('loginBtn');
    const passwordInput = document.getElementById('password');
    const passwordToggle = document.getElementById('passwordToggle');

    // --- Animación de shake en el container ---
    function shakeContainer() {
        const container = document.querySelector('.login-container');
        if (container) {
            container.classList.remove('shake');
            void container.offsetWidth;
            container.classList.add('shake');
            setTimeout(function () {
                container.classList.remove('shake');
            }, 500);
        }
    }

    // --- Toggle de visibilidad de contraseña ---
    if (passwordToggle && passwordInput) {
        passwordToggle.addEventListener('click', function () {
            const isPassword = passwordInput.type === 'password';
            passwordInput.type = isPassword ? 'text' : 'password';
            passwordToggle.classList.toggle('visible', !isPassword);
            passwordToggle.setAttribute('aria-label', isPassword ? 'Ocultar contraseña' : 'Mostrar contraseña');
        });
    }

    // --- Marcar input como "filled" cuando tiene valor ---
    document.querySelectorAll('.login-form input').forEach(function (input) {
        input.addEventListener('input', function () {
            this.classList.toggle('filled', this.value.trim() !== '');
        });

        if (input.value.trim() !== '') {
            input.classList.add('filled');
        }
    });

    // --- Submit del formulario ---
    form.addEventListener('submit', async function (e) {
        e.preventDefault();

        const username = document.getElementById('username').value.trim();
        const password = passwordInput.value;
        const rememberMe = document.getElementById('remember_me').checked;

        if (!username || !password) {
            showError('Por favor complete todos los campos.');
            shakeContainer();
            return;
        }

        setLoading(true);
        hideError();

        try {
            const response = await fetch(APP_URL + '/api/auth.php?action=login', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    username: username,
                    password: password,
                    remember_me: rememberMe,
                    csrf_token: document.querySelector('input[name="csrf_token"]').value
                })
            });

            const data = await response.json();

            if (data.success) {
                window.location.href = APP_URL + '/modules/reports/dashboard.php';
            } else {
                showError(data.message || 'Credenciales inválidas.');
                shakeContainer();
                setLoading(false);
            }
        } catch (err) {
            showError('Error de conexión. Intente de nuevo.');
            setLoading(false);
        }
    });

    // --- Funciones auxiliares ---
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
        submitBtn.classList.toggle('loading', loading);
    }
});
