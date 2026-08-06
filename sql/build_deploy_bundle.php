<?php
/**
 * Genera sql/sistema_rh_deploy.sql (bundle de despliegue).
 *
 * Concadena el esquema base + migraciones (fase2..fase21) + datos iniciales,
 * eliminando las sentencias "USE <base>;" porque en cPanel la base se
 * selecciona al importar y su nombre lleva el prefijo del usuario.
 *
 * Uso: php sql/build_deploy_bundle.php
 */

$dir = __DIR__ . '/';

$files = [
    'schema.sql',
    'seed_data.sql',
    'migrations/password_resets.sql',
    'migrations/fase2_atendance_documents.sql',
    'migrations/fase3_leave_announcements.sql',
    'migrations/fase4_recruitment_performance.sql',
    'migrations/fase5_payroll_dashboard.sql',
    'migrations/fase6_security_audit.sql',
    'migrations/fase7_payroll_enhancements.sql',
    'migrations/fase8_employees_enhancements.sql',
    'migrations/fase9_attendance_enhancements.sql',
    'migrations/fase10_announcements_enhancements.sql',
    'migrations/fase10_document_versions.sql',
    'migrations/fase11_employees_enhancements.sql',
    'migrations/fase11_recruitment_enhancements.sql',
    'migrations/fase12_performance_enhancements.sql',
    'migrations/fase13_payroll_enhancements.sql',
    'migrations/fase14_remaining_enhancements.sql',
    'migrations/fase15_payroll_bonus.sql',
    'migrations/fase16_payroll_subsidio.sql',
    'migrations/fase17_payroll_adjustments.sql',
    'migrations/fase18_retardo_deducciones.sql',
    'migrations/fase19_payroll_quincenal.sql',
    'migrations/fase20_tablero_control.sql',
    'migrations/fase21_payroll_fixes.sql',
    'seed_data_charts.sql',
];

$out = "-- ============================================================\n";
$out .= "-- SISTEMA RH - Bundle de despliegue (generado automáticamente)\n";
$out .= "-- Orden: schema + seed_data (roles/permisos/admin) + migraciones\n";
$out .= "-- fase2..fase21 + seed_data_charts (datos de ejemplo).\n";
$out .= "-- Se eliminaron las sentencias 'USE <base>;'.\n";
$out .= "-- ============================================================\n\n";

foreach ($files as $f) {
    $path = $dir . $f;
    if (!is_file($path)) {
        fwrite(STDERR, "AVISO: no existe $f\n");
        continue;
    }
    $content = file_get_contents($path);
    $lines = preg_split('/\R/', $content);
    $lines = array_values(array_filter($lines, function ($line) {
        return !preg_match('/^\s*USE\s+[`"]?[a-zA-Z0-9_]+[`"]?\s*;?\s*$/i', $line);
    }));
    $out .= "-- >>> $f\n";
    $out .= implode("\n", $lines) . "\n\n";
}

file_put_contents($dir . 'sistema_rh_deploy.sql', $out);
echo "Bundle generado: " . $dir . "sistema_rh_deploy.sql\n";
