<?php

declare(strict_types=1);

/**
 * Bootstrap compartido para los puntos de entrada (api.php y app.php).
 *
 * Registra el autoloader PSR-4 con mapa de clases que comparten archivo.
 * Varias clases/enums/interfaces viven en el mismo archivo PHP,
 * por lo que necesitamos mapearlas al archivo correcto.
 */

spl_autoload_register(function (string $className): void {
    $prefix = 'MiniProject\\';
    $baseDir = __DIR__ . '/';

    $len = strlen($prefix);
    if (strncmp($prefix, $className, $len) !== 0) {
        return;
    }

    $relativeClass = substr($className, $len);

    $classMap = [
        // Enums definidos en Task.php
        'Priority'             => 'Task.php',
        'Status'               => 'Task.php',
        // Excepciones definidas en AppException.php
        'ValidationException'  => 'AppException.php',
        'NotFoundException'    => 'AppException.php',
        // Interfaz y exportadores definidos en ExportService.php
        'ExporterInterface'    => 'ExportService.php',
        'JsonExporter'         => 'ExportService.php',
        'CsvExporter'          => 'ExportService.php',
        // Clases del Router definidas en Router.php
        'Route'                => 'Router.php',
        'RouteMatch'           => 'Router.php',
    ];

    if (isset($classMap[$relativeClass])) {
        $file = $baseDir . $classMap[$relativeClass];
    } else {
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    }

    if (file_exists($file)) {
        require_once $file;
    }
});
