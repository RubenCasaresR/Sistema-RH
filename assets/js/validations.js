/**
 * validations.js — Validaciones del lado cliente
 * CURP, RFC, NSS en tiempo real.
 */

document.addEventListener('DOMContentLoaded', function () {
    const curpInput = document.getElementById('curp');
    const rfcInput = document.getElementById('rfc');
    const nssInput = document.getElementById('nss');

    if (curpInput) {
        curpInput.addEventListener('input', function () {
            this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 18);
        });
        curpInput.addEventListener('blur', function () {
            if (this.value.length > 0 && this.value.length !== 18) {
                showFieldError(this, 'Deben ser 18 caracteres.');
            } else {
                clearFieldError(this);
            }
        });
    }

    if (rfcInput) {
        rfcInput.addEventListener('input', function () {
            this.value = this.value.toUpperCase().replace(/[^A-Z0-9Ñ&]/g, '').slice(0, 13);
        });
        rfcInput.addEventListener('blur', function () {
            if (this.value.length > 0 && !/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/.test(this.value)) {
                showFieldError(this, 'RFC inválido.');
            } else {
                clearFieldError(this);
            }
        });
    }

    if (nssInput) {
        nssInput.addEventListener('input', function () {
            this.value = this.value.replace(/\D/g, '').slice(0, 11);
        });
        nssInput.addEventListener('blur', function () {
            if (this.value.length > 0 && this.value.length !== 11) {
                showFieldError(this, 'Deben ser 11 dígitos.');
            } else {
                clearFieldError(this);
            }
        });
    }
});

function showFieldError(input, message) {
    let error = input.parentElement.querySelector('.field-error');
    if (!error) {
        error = document.createElement('span');
        error.className = 'field-error';
        error.style.cssText = 'font-size:0.78rem;color:#d63031;margin-top:2px;display:block;';
        input.parentElement.appendChild(error);
    }
    error.textContent = message;
    input.style.borderColor = '#d63031';
}

function clearFieldError(input) {
    const error = input.parentElement.querySelector('.field-error');
    if (error) error.remove();
    input.style.borderColor = '';
}
