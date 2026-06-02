<?php

declare(strict_types=1);

/**
 * Gestor de Tareas CLI — Punto de entrada.
 * Task Manager CLI — Entry point.
 *
 * Aplicación interactiva de línea de comandos para gestionar tareas
 * usando SQLite como almacenamiento.
 *
 * Interactive command-line application for managing tasks
 * using SQLite as storage.
 *
 * Uso / Usage: php app.php
 *
 * PHP 8 features: named arguments, match, readonly, enums
 */

// ---------------------------------------------------------------
//  Shared autoloader / Autoloader compartido
// ---------------------------------------------------------------

require_once __DIR__ . '/src/bootstrap.php';

use MiniProject\CliApp;
use MiniProject\ExportService;
use MiniProject\TaskRepository;
use MiniProject\TaskService;

// ---------------------------------------------------------------
//  Service initialization and run / Inicialización y ejecución
// ---------------------------------------------------------------

$repository = new TaskRepository(userId: 1);
$taskService = new TaskService(repository: $repository);
$exportService = new ExportService(outputDir: __DIR__ . '/data');

$app = new CliApp(
    taskService: $taskService,
    exportService: $exportService,
);
$app->run();
