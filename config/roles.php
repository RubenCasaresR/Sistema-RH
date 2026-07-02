<?php
/**
 * RBAC - Definición de roles y permisos.
 *
 * Convención de claves: {recurso}.{acción}
 *   create / read / update / delete
 */

/**
 * Lista completa de permisos disponibles en el sistema.
 */
function getAvailablePermissions(): array
{
    return [
        'employees.create',
        'employees.read',
        'employees.update',
        'employees.delete',
        'employees.export',

        'attendance.read',
        'attendance.clock',
        'attendance.reports',
        'attendance.correct',
        'attendance.export',

        'documents.upload',
        'documents.read',
        'documents.delete',

        'leave.request',
        'leave.approve',
        'leave.read',

        'announcements.create',
        'announcements.read',
        'announcements.delete',

        'recruitment.create',
        'recruitment.read',
        'recruitment.update',
        'recruitment.hire',

        'performance.create',
        'performance.read',
        'performance.update',

        'payroll.read',
        'payroll.calculate',
        'payroll.export',

        'reports.dashboard',
        'reports.export',

        'audit.read',

        'users.create',
        'users.read',
        'users.update',
        'users.delete',
    ];
}

/**
 * Devuelve los permisos asignados a cada rol.
 * Rol => [permiso1, permiso2, ...]
 */
function getRolePermissions(): array
{
    return [
        // Administrador RH: acceso total
        'Administrador RH' => [
            'employees.create', 'employees.read', 'employees.update', 'employees.delete',
            'attendance.read', 'attendance.clock', 'attendance.reports', 'attendance.correct', 'attendance.export',
            'documents.upload', 'documents.read', 'documents.delete',
            'leave.request', 'leave.approve', 'leave.read',
            'announcements.create', 'announcements.read', 'announcements.delete',
            'recruitment.create', 'recruitment.read', 'recruitment.update', 'recruitment.hire',
            'performance.create', 'performance.read', 'performance.update',
            'payroll.read', 'payroll.calculate', 'payroll.export',
            'reports.dashboard', 'reports.export',
            'audit.read',
            'users.create', 'users.read', 'users.update', 'users.delete',
        ],

        // Gerente RH: casi todo excepto payroll.calculate
        'Gerente RH' => [
            'employees.create', 'employees.read', 'employees.update', 'employees.delete',
            'attendance.read', 'attendance.clock', 'attendance.reports', 'attendance.correct', 'attendance.export',
            'documents.upload', 'documents.read', 'documents.delete',
            'leave.request', 'leave.approve', 'leave.read',
            'announcements.create', 'announcements.read', 'announcements.delete',
            'recruitment.create', 'recruitment.read', 'recruitment.update', 'recruitment.hire',
            'performance.create', 'performance.read', 'performance.update',
            'payroll.read', 'payroll.export',
            'reports.dashboard', 'reports.export',
            'audit.read',
            'users.read',
        ],

        // Jefe de área: su equipo, asistencia, permisos
        'Jefe de área' => [
            'employees.read',
            'attendance.read', 'attendance.reports',
            'leave.request', 'leave.approve', 'leave.read',
            'announcements.read',
            'performance.create', 'performance.read', 'performance.update',
            'reports.dashboard',
        ],

        // Empleado: solo su información
        'Empleado' => [
            'attendance.clock',
            'leave.request', 'leave.read',
            'announcements.read',
            'documents.read',
            'reports.dashboard',
        ],

        // Dirección: visión estratégica
        'Dirección' => [
            'employees.read', 'employees.export',
            'attendance.read', 'attendance.reports',
            'leave.read',
            'announcements.read',
            'payroll.read',
            'reports.dashboard', 'reports.export',
        ],
    ];
}

/**
 * Verifica si un rol tiene un permiso específico.
 */
function hasPermission(string $roleName, string $permissionKey): bool
{
    $perms = getRolePermissions();
    return isset($perms[$roleName]) && in_array($permissionKey, $perms[$roleName], true);
}
