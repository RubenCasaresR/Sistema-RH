<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("Acceso denegado: este script solo puede ejecutarse desde la linea de comandos (cron).\n");
}

require __DIR__ . '/../config/app.php';
require __DIR__ . '/../config/database.php';

$backupDir = env('BACKUP_DIR', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'backups');
$keepCount = (int) env('BACKUP_KEEP', 14);
$timestamp = date('Ymd_His');
$backupDir = rtrim($backupDir, '/\\');

function ensureBackupDir(string $dir): void
{
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
        fwrite(STDERR, "ERROR: no se pudo crear el directorio de backups: $dir\n");
        exit(1);
    }
    $protect = $dir . DIRECTORY_SEPARATOR . '.htaccess';
    if (!file_exists($protect)) {
        @file_put_contents($protect, "Require all denied\n");
    }
}

function getTables(PDO $pdo): array
{
    return $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
}

function dumpTable(PDO $pdo, string $table, $fh): void
{
    $safe = '`' . str_replace('`', '``', $table) . '`';
    fwrite($fh, "\n-- ------------------------------------------------------------\n");
    fwrite($fh, "-- Estructura de la tabla {$safe}\n");
    fwrite($fh, "-- ------------------------------------------------------------\n");
    fwrite($fh, "DROP TABLE IF EXISTS {$safe};\n\n");

    $create = $pdo->query("SHOW CREATE TABLE {$safe}")->fetch();
    fwrite($fh, $create['Create Table'] . ";\n\n");

    $count = (int) $pdo->query("SELECT COUNT(*) FROM {$safe}")->fetchColumn();
    if ($count === 0) {
        fwrite($fh, "-- (sin datos)\n\n");
        return;
    }

    fwrite($fh, "-- Datos de la tabla {$safe} ({$count} filas)\n");
    $stmt = $pdo->query("SELECT * FROM {$safe}");
    $rows = $stmt->fetchAll();
    $batch = [];
    foreach ($rows as $row) {
        $values = [];
        foreach ($row as $value) {
            if ($value === null) {
                $values[] = 'NULL';
            } else {
                $values[] = $pdo->quote((string) $value);
            }
        }
        $batch[] = '(' . implode(', ', $values) . ')';
        if (count($batch) >= 500) {
            fwrite($fh, "INSERT INTO {$safe} VALUES\n" . implode(",\n", $batch) . ";\n");
            $batch = [];
        }
    }
    if ($batch !== []) {
        fwrite($fh, "INSERT INTO {$safe} VALUES\n" . implode(",\n", $batch) . ";\n");
    }
    fwrite($fh, "\n");
}

function dumpDatabase(PDO $pdo, string $path): void
{
    $fh = fopen($path, 'w');
    if ($fh === false) {
        fwrite(STDERR, "ERROR: no se pudo escribir el archivo de dump: $path\n");
        exit(1);
    }

    fwrite($fh, "-- ============================================================\n");
    fwrite($fh, "-- SISTEMA RH - Backup de la base de datos\n");
    fwrite($fh, "-- Fecha: " . date('Y-m-d H:i:s') . "\n");
    fwrite($fh, "-- Generado por sql/backup.php (no editar a mano)\n");
    fwrite($fh, "-- ============================================================\n\n");
    fwrite($fh, "SET NAMES utf8mb4;\n");
    fwrite($fh, "SET FOREIGN_KEY_CHECKS = 0;\n\n");

    foreach (getTables($pdo) as $table) {
        dumpTable($pdo, $table, $fh);
    }

    fwrite($fh, "SET FOREIGN_KEY_CHECKS = 1;\n");
    fwrite($fh, "-- Fin del backup\n");
    fclose($fh);
}

function addDirToZip(ZipArchive $zip, string $base, string $dir): void
{
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $local = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($base))), '/');
        $zip->addFile($file->getPathname(), $local);
    }
}

function rotateBackups(string $dir, string $prefix, int $keep): void
{
    $files = glob($dir . DIRECTORY_SEPARATOR . $prefix . '_*.{sql,zip}', GLOB_BRACE);
    if ($files === false) {
        return;
    }
    sort($files);
    while (count($files) > $keep) {
        $oldest = array_shift($files);
        @unlink($oldest);
        echo "  Rotacion: se elimino $oldest\n";
    }
}

ensureBackupDir($backupDir);

echo "Backup de SISTEMA RH\n";
echo "Directorio: $backupDir\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n";

$pdo = getDB();

$sqlFile = $backupDir . DIRECTORY_SEPARATOR . 'sistema_rh_' . $timestamp . '.sql';
echo "Dump de base de datos...\n";
dumpDatabase($pdo, $sqlFile);
echo "  OK: $sqlFile\n";

$zipFile = $backupDir . DIRECTORY_SEPARATOR . 'uploads_' . $timestamp . '.zip';
if (class_exists('ZipArchive') && is_dir(UPLOAD_PATH)) {
    echo "Comprimiendo uploads/...\n";
    $zip = new ZipArchive();
    if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
        addDirToZip($zip, rtrim(UPLOAD_PATH, '/\\'), UPLOAD_PATH);
        $zip->close();
        echo "  OK: $zipFile\n";
    } else {
        fwrite(STDERR, "  ADVERTENCIA: no se pudo crear el ZIP de uploads\n");
        @unlink($zipFile);
    }
} else {
    echo "  (ZipArchive no disponible o uploads/ inexistente: se omite el ZIP)\n";
}

echo "Rotando backups (se conservan los ultimos $keepCount)...\n";
rotateBackups($backupDir, 'sistema_rh', $keepCount);
rotateBackups($backupDir, 'uploads', $keepCount);

echo "Backup completado.\n";
