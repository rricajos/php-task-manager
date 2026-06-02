<?php

declare(strict_types=1);

/**
 * Bootstrap compartido para los puntos de entrada (api.php y app.php).
 * Shared bootstrap for entry points (api.php and app.php).
 *
 * Registra el autoloader PSR-4 con mapa de clases que comparten archivo.
 * Varias clases/enums/interfaces viven en el mismo archivo PHP,
 * por lo que necesitamos mapearlas al archivo correcto.
 *
 * Registers a PSR-4 autoloader with a class map for shared files.
 * Several classes/enums/interfaces live in the same PHP file,
 * so we need to map them to the correct file.
 */

spl_autoload_register(function (string $className): void {
    $prefix = 'MiniProject\\';
    $baseDir = __DIR__ . '/';

    $len = strlen($prefix);
    if (strncmp($prefix, $className, $len) !== 0) {
        return;
    }

    $relativeClass = substr($className, $len);

    // Mapa de clases que comparten archivo.
    // Class map for classes sharing the same file.
    $classMap = [
        // Enums definidos en Task.php / Enums defined in Task.php
        'Priority'             => 'Task.php',
        'Status'               => 'Task.php',
        // Excepciones definidas en AppException.php / Exceptions in AppException.php
        'ValidationException'  => 'AppException.php',
        'NotFoundException'    => 'AppException.php',
        // Interfaz y exportadores en ExportService.php / Interface and exporters in ExportService.php
        'ExporterInterface'    => 'ExportService.php',
        'JsonExporter'         => 'ExportService.php',
        'CsvExporter'          => 'ExportService.php',
        // Clases del Router en Router.php / Router classes in Router.php
        'Route'                => 'Router.php',
        'RouteMatch'           => 'Router.php',
    ];

    if (isset($classMap[$relativeClass])) {
        $file = $baseDir . $classMap[$relativeClass];
    } else {
        // Resolución estándar PSR-4 / Standard PSR-4 resolution
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    }

    if (file_exists($file)) {
        require_once $file;
    }
});
