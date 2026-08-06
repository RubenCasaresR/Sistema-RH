# Despliegue en Neubox — Guía paso a paso

Despliegue del Sistema RH en hosting compartido Neubox (cPanel, plan Tell It).
Reemplaza los placeholders (`tudominio.com`, `usuario_sistema_rh`, etc.) por tus valores reales.

---

## 0. Supuestos

- Cuenta Neubox activa con el plan **Tell It** y tu **dominio** apuntando a Neubox (nameservers o A record) ya agregado en cPanel.
- Versión local del proyecto lista y con la historia git purgada (sin credenciales filtradas).
- En producción, la base de datos se crea vacía y se llena con el bundle `sql/sistema_rh_deploy.sql`.

---

## 1. Preparación local (ya completada)

| Artefacto | Descripción |
|---|---|
| `sql/sistema_rh_deploy.sql` | Bundle único (schema + migraciones + seeds), sin `USE`; importable directo en phpMyAdmin. |
| `sql/backup.php` | Script de backup por cron (dump SQL + zip de `uploads/`). |
| `sql/build_deploy_bundle.php` | Regenera el bundle si cambian los SQL (requiere cargar `.env`). |
| `.env.example` | Plantilla con placeholders (DB, SMTP, `APP_ENV`, `SESSION_COOKIE_SECURE`). |

Regenerar el bundle tras cambios en SQL:

```bash
php sql/build_deploy_bundle.php
```

---

## 2. cPanel — Entorno

1. **Versión de PHP**: cPanel → **MultiPHP Manager** → selecciona el dominio → **PHP 8.2** o **8.3**.
   (El código requiere PHP ≥ 8.0; usa `str_starts_with`.)
2. **Base de datos**: cPanel → **MySQL Databases**:
   - Crear BD: `usuario_sistema_rh` (prefijo de cuenta + nombre).
   - Crear usuario: `usuario_rh` con contraseña **nueva y fuerte** (NO reutilizar la pass que quedó filtrada en GitHub).
   - Asignar **Todos los privilegios** sobre la BD creada.
   - Anota: nombre de BD, usuario, host (normalmente `localhost`) y contraseña.
3. **Correo SMTP**: cPanel → **Email Accounts** → crear `no-reply@tudominio.com`.
   - Host SMTP: `mail.tudominio.com` · Puerto: `587` · Cifrado: `TLS` (alternativa `465`/`SSL`).
   - Anota usuario (correo completo) y contraseña.

> **Importante**: si AutoSSL aún no está activo sobre el dominio, el `.htaccess` redirige todo HTTP→HTTPS (301) y puede generar bucle hasta que exista el certificado. Activa primero **SSL/TLS Status → Run AutoSSL** (o el certificado que corresponda).

---

## 3. Importar la base de datos

1. cPanel → **phpMyAdmin** → selecciona la BD creada.
2. Pestaña **Importar** → elegir archivo `sql/sistema_rh_deploy.sql` → **Continuar**.
3. Verificar en la BD:
   - **38 tablas**.
   - `users` con `admin` (role 1) y `employees` con al menos 5 registros de ejemplo.
   - Tarifas fiscales: `tax_isr_tariff`, `tax_uma`, `tax_subsidio_tariff` con datos.
   - Columna `users.password_change_required` = 1 (obliga a cambiar contraseña en el primer login).

---

## 4. Subir archivos por FTP

- Cliente FTP (FileZilla/Total Commander): subir **todo** el contenido del proyecto a `public_html/`.
- **Incluir** `vendor/` (PHPMailer). No se ejecuta `composer install` en el servidor.
- **NO subir**: `.env`, `.git/`, `tests/` (opcional), ni `sql/*.sql` con datos sensibles (opcional; el bundle ya está en tu máquina).
- Verificar que `uploads/` y sus subcarpetas existan (se suben tal cual).

**Alternativa por FTP** a `public_html/`:

```text
/ (raíz del proyecto)        -> /public_html/
```

---

## 5. Crear `.env` en el servidor

Crear el archivo `.env` en `public_html/` usando **cPanel → File Manager → Editor** (no subirlo por FTP; así nunca viaja en claro).

Modelo:

```ini
APP_ENV=production
APP_URL=https://tudominio.com
SESSION_TIMEOUT=1800
SESSION_COOKIE_SECURE=true

DB_HOST=localhost
DB_PORT=3306
DB_NAME=usuario_sistema_rh
DB_USER=usuario_rh
DB_PASS=TU_PASS_NUEVA_Y_SEGURA
DB_CHARSET=utf8mb4

SMTP_HOST=mail.tudominio.com
SMTP_PORT=587
SMTP_USER=no-reply@tudominio.com
SMTP_PASS=TU_PASS_SMTP
SMTP_ENCRYPTION=tls
MAIL_FROM=no-reply@tudominio.com

BACKUP_DIR=/home/USUARIO_C_PANEL/sistema_rh_backups
BACKUP_KEEP=14
```

> `BACKUP_DIR` debe estar **fuera del webroot** (`/home/USUARIO/...`), nunca dentro de `public_html/`.
> `SESSION_COOKIE_SECURE=true` solo tras tener HTTPS/SSL funcionando.

---

## 6. Pruebas post-despliegue

1. **HTTPS**: abrir `https://tudominio.com` → debe redirigir correctamente y no marcar error de certificado.
2. **Login admin**: `admin` + contraseña temporal (la del seed) → debe **forzar el cambio de contraseña**.
3. **Correo**: usar *¿Olvidaste tu contraseña?* → debe llegar el correo vía SMTP (revisar también spam).
4. **Nómina**: calcular una quincena (por ejemplo 2026-07) → verificar ISR/IMSS con tarifas 2026 y que no muestre errores.
5. **Módulos**: asistencia, vacaciones, incidencias, tablero de control.
6. **Seguridad**: con DevTools/curl revisar cabeceras de respuesta:
   `Content-Security-Policy`, `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Strict-Transport-Security`.
   Y que `display_errors` esté desactivado (no debe verse ningún error PHP en pantalla).
7. Verificar que URLs bloqueadas den 403: `/config/`, `/includes/`, `/sql/`, `/vendor/`, `/.env`, `/_test*.php`.

---

## 7. Backups automáticos (cron)

1. Crear el directorio destino fuera del webroot, p. ej. `/home/USUARIO/sistema_rh_backups`.
2. Probar manualmente por SSH o File Manager (terminal):

   ```bash
   php /home/USUARIO/public_html/sql/backup.php
   ```

   Debe generar `sistema_rh_AAAAAMMDD_HHMMSS.sql` y `uploads_AAAAAMMDD_HHMMSS.zip`.
   > Requiere la extensión **ZipArchive** (activada por defecto en cPanel; si falta, solo se omite el ZIP de uploads).

3. Configurar el cron: cPanel → **Cron Jobs**:

   ```text
   0 4 * * * php /home/USUARIO/public_html/sql/backup.php >> /home/USUARIO/sistema_rh_backups/cron.log 2>&1
   ```

   (Diario a las 04:00, hora del servidor.)
4. Opcional: descargar una copia del backup a tu máquina con cierta frecuencia (rotación local de 14 copias).

---

## 8. Cierre y buenas prácticas

- **Hacer el repositorio GitHub privado** (Settings → Danger Zone → Change visibility → Private) para que GitHub purgue los commits viejos que contenían la credencial.
- No reutilizar contraseñas que hayan estado expuestas en el historial público.
- Mantener `vendor/` sincronizado: en local, `composer install --no-dev` antes de cada despliegue.
- Revisar periódicamente los logs de error de PHP (cPanel → Error logs) en producción.

---

## 9. Solución de problemas rápida

| Síntoma | Causa probable | Solución |
|---|---|---|
| Bucle de redirección HTTP→HTTPS | SSL no activo aún | Activar AutoSSL, luego probar de nuevo. |
| `Error interno del sistema` | Credenciales BD mal en `.env` | Revisar `DB_NAME/DB_USER/DB_PASS` y permisos en cPanel. |
| No llegan correos | SMTP mal configurado / puerto bloqueado | Revisar `SMTP_*` y probar puerto `465`/`SSL`. |
| Zips de uploads no se crean | Falta `ZipArchive` | Activar la extensión en Select PHP Version. |
| `password_change_required` no se cumple | Sesión anterior con cookie vieja | Borrar cookies / probar en ventana incógnito. |
| 403 en `/.env` | Regla del `.htaccess` | Es el comportamiento esperado (protección). |
