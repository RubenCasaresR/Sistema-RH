<?php
$user = currentUser();
$userPerms = loadUserPermissions();
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar">
    <div class="sidebar-brand">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        <span>RH Sistema</span>
    </div>

    <nav class="sidebar-nav">
        <ul>
            <?php if (can('reports.dashboard')): ?>
                <li><a href="<?= APP_URL ?>/modules/reports/dashboard.php" class="<?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-chart-pie nav-icon"></i> <span class="nav-text">Dashboard</span>
                </a></li>
            <?php endif; ?>

            <?php if (can('employees.read')): ?>
                <li><a href="<?= APP_URL ?>/modules/employees/index.php" class="<?= $currentPage === 'index.php' && basename(dirname($_SERVER['PHP_SELF'])) === 'employees' ? 'active' : '' ?>">
                    <i class="fa-solid fa-users nav-icon"></i> <span class="nav-text">Empleados</span>
                </a></li>
                <?php if (can('employees.create')): ?>
                    <li class="sub-item"><a href="<?= APP_URL ?>/modules/employees/import.php" class="<?= basename($_SERVER['PHP_SELF']) === 'import.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-upload nav-icon" style="opacity:0.5;"></i> <span class="nav-text">Importar CSV</span>
                    </a></li>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (can('attendance.read')): ?>
                <li><a href="<?= APP_URL ?>/modules/attendance/index.php" class="<?= basename(dirname($_SERVER['PHP_SELF'])) === 'attendance' ? 'active' : '' ?>">
                    <i class="fa-solid fa-clock nav-icon"></i> <span class="nav-text">Asistencia</span>
                </a></li>
            <?php endif; ?>

            <?php if (can('documents.upload') || can('documents.read')): ?>
                <li><a href="<?= APP_URL ?>/modules/documents/index.php" class="<?= basename(dirname($_SERVER['PHP_SELF'])) === 'documents' ? 'active' : '' ?>">
                    <i class="fa-solid fa-file-lines nav-icon"></i> <span class="nav-text">Documentos</span>
                </a></li>
            <?php endif; ?>

            <?php if (can('leave.request') || can('leave.approve')): ?>
                <li>
                    <a href="<?= APP_URL ?>/modules/leave/requests.php" class="<?= basename(dirname($_SERVER['PHP_SELF'])) === 'leave' && basename($_SERVER['PHP_SELF']) === 'requests.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-calendar-check nav-icon"></i> <span class="nav-text">Vacaciones</span>
                    </a>
                </li>
                <?php if (can('leave.approve')): ?>
                <li>
                    <a href="<?= APP_URL ?>/modules/leave/approval.php" class="<?= basename($_SERVER['PHP_SELF']) === 'approval.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-check-circle nav-icon"></i> <span class="nav-text">Autorizaciones</span>
                    </a>
                </li>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (can('announcements.read')): ?>
                <li><a href="<?= APP_URL ?>/modules/communication/announcements.php" class="<?= basename(dirname($_SERVER['PHP_SELF'])) === 'communication' ? 'active' : '' ?>">
                    <i class="fa-solid fa-bullhorn nav-icon"></i> <span class="nav-text">Comunicados</span>
                </a></li>
            <?php endif; ?>

            <?php if (can('recruitment.read')): ?>
                <li><a href="<?= APP_URL ?>/modules/recruitment/vacancies.php" class="<?= basename(dirname($_SERVER['PHP_SELF'])) === 'recruitment' ? 'active' : '' ?>">
                    <i class="fa-solid fa-user-plus nav-icon"></i> <span class="nav-text">Reclutamiento</span>
                </a></li>
            <?php endif; ?>

            <?php if (can('performance.read')): ?>
                <li><a href="<?= APP_URL ?>/modules/performance/index.php" class="<?= basename(dirname($_SERVER['PHP_SELF'])) === 'performance' && basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-chart-bar nav-icon"></i> <span class="nav-text">Desempeño</span>
                </a></li>
                <li><a href="<?= APP_URL ?>/modules/performance/training.php" class="<?= basename($_SERVER['PHP_SELF']) === 'training.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-graduation-cap nav-icon"></i> <span class="nav-text">Capacitación</span>
                </a></li>
            <?php endif; ?>

            <?php if (can('payroll.read')): ?>
                <li><a href="<?= APP_URL ?>/modules/payroll/index.php" class="<?= basename(dirname($_SERVER['PHP_SELF'])) === 'payroll' ? 'active' : '' ?>">
                    <i class="fa-solid fa-file-invoice-dollar nav-icon"></i> <span class="nav-text">Nómina</span>
                </a></li>
            <?php endif; ?>

            <?php if (can('users.read')): ?>
                <li><a href="<?= APP_URL ?>/modules/users/index.php" class="<?= basename(dirname($_SERVER['PHP_SELF'])) === 'users' ? 'active' : '' ?>">
                    <i class="fa-solid fa-user-shield nav-icon"></i> <span class="nav-text">Usuarios</span>
                </a></li>
            <?php endif; ?>

            <?php if (can('audit.read')): ?>
                <li><a href="<?= APP_URL ?>/modules/audit/index.php" class="<?= basename(dirname($_SERVER['PHP_SELF'])) === 'audit' ? 'active' : '' ?>">
                    <i class="fa-solid fa-clipboard-list nav-icon"></i> <span class="nav-text">Auditoría</span>
                </a></li>
            <?php endif; ?>
        </ul>
    </nav>

    <div class="sidebar-footer">
        <div class="user-info">
            <span class="user-name"><?= htmlspecialchars($user['name'] ?? $user['username']) ?></span>
            <span class="user-role"><?= htmlspecialchars($user['role_name'] ?? '') ?></span>
        </div>
        <div class="sidebar-footer-links">
            <label class="dark-mode-toggle" title="Modo oscuro">
                <i class="fa-regular fa-moon" id="darkModeIcon"></i>
                <input type="checkbox" id="darkModeToggle">
            </label>
            <a href="<?= APP_URL ?>/modules/auth/change_password.php" class="btn-logout"><i class="fa-solid fa-key"></i> <span>Cambiar contraseña</span></a>
            <a href="<?= APP_URL ?>/modules/auth/logout.php" class="btn-logout" onclick="return confirm('¿Cerrar sesión?')"><i class="fa-solid fa-sign-out-alt"></i> <span>Cerrar sesión</span></a>
        </div>
    </div>
</aside>
