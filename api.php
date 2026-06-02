<?php

declare(strict_types=1);

/**
 * API REST del Gestor de Tareas - Punto de entrada.
 * Task Manager REST API - Entry point.
 *
 * Servidor HTTP para la API REST que expone los endpoints de
 * autenticación y gestión de tareas.
 *
 * HTTP server for the REST API exposing authentication
 * and task management endpoints.
 *
 *   php -S localhost:8080 api.php
 *
 * PHP 8 features: named arguments, match
 */

// ---------------------------------------------------------------
//  Static frontend files / Archivos estáticos del frontend
// ---------------------------------------------------------------

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if ($requestPath === '/' || $requestPath === '/index.html') {
    $indexFile = __DIR__ . '/public/index.html';
    if (is_file($indexFile)) {
        header('Content-Type: text/html; charset=UTF-8');
        readfile($indexFile);

        return;
    }
}

$staticFile = __DIR__ . '/public' . $requestPath;
if (is_file($staticFile)) {
    $mimeTypes = [
        'css'  => 'text/css; charset=UTF-8',
        'js'   => 'application/javascript; charset=UTF-8',
        'html' => 'text/html; charset=UTF-8',
        'png'  => 'image/png',
        'svg'  => 'image/svg+xml',
        'ico'  => 'image/x-icon',
        'json' => 'application/json',
    ];
    $ext = strtolower(pathinfo($staticFile, PATHINFO_EXTENSION));
    header('Content-Type: ' . ($mimeTypes[$ext] ?? 'application/octet-stream'));
    readfile($staticFile);

    return;
}

// ---------------------------------------------------------------
//  Shared autoloader / Autoloader compartido
// ---------------------------------------------------------------

require_once __DIR__ . '/src/bootstrap.php';

use MiniProject\ApiController;
use MiniProject\AppException;
use MiniProject\AuthService;
use MiniProject\CorsMiddleware;
use MiniProject\Database;
use MiniProject\ExportService;
use MiniProject\JsonResponse;
use MiniProject\Middleware;
use MiniProject\RateLimiter;
use MiniProject\Router;
use MiniProject\TaskRepository;
use MiniProject\TaskService;

// ---------------------------------------------------------------
//  CORS middleware / Middleware CORS
// ---------------------------------------------------------------

$cors = new CorsMiddleware();
$cors->handle(requestMethod: $_SERVER['REQUEST_METHOD']);

// ---------------------------------------------------------------
//  Rate limiting / Limitación de tasa
// ---------------------------------------------------------------

try {
    Database::getInstance();

    $rateLimiter = new RateLimiter();
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

    // Stricter limits for auth endpoints (brute-force protection)
    // Límites más estrictos para endpoints de autenticación (protección contra fuerza bruta)
    $isAuthEndpoint = is_string($requestUri) && str_starts_with($requestUri, '/auth/');
    $maxRequests = $isAuthEndpoint ? 10 : 60;

    Middleware::rateLimit(
        limiter: $rateLimiter,
        key: "ip:{$clientIp}",
        maxRequests: $maxRequests,
        windowSeconds: 60,
    );
} catch (AppException $e) {
    JsonResponse::error(
        message: "API initialization error: {$e->getMessage()}",
        code: 500,
    );
}

// ---------------------------------------------------------------
//  Service initialization / Inicialización de servicios
// ---------------------------------------------------------------

try {

    $repository = new TaskRepository(userId: 1);
    $taskService = new TaskService(repository: $repository);
    $exportService = new ExportService(
        outputDir: __DIR__ . '/data',
    );
    $authService = new AuthService();

    $controller = new ApiController(
        taskService: $taskService,
        exportService: $exportService,
        authService: $authService,
    );
} catch (AppException $e) {
    JsonResponse::error(
        message: "API initialization error: {$e->getMessage()}",
        code: 500,
    );
}

// ---------------------------------------------------------------
//  Route configuration / Configuración de rutas
// ---------------------------------------------------------------

$router = new Router();

// --- Public auth routes / Rutas públicas de autenticación ---

$router->post('/auth/register', function () use ($controller): void {
    $body = Middleware::jsonBody();
    $controller->register($body);
});

$router->post('/auth/login', function () use ($controller): void {
    $body = Middleware::jsonBody();
    $controller->login($body);
});

$router->get('/auth/me', function () use ($controller, $authService): void {
    $userData = Middleware::requireAuth($authService, $controller);
    $controller->profile($userData);
});

// --- Protected task routes / Rutas protegidas de tareas ---
// Specific routes (search, stats, export) go BEFORE parameterized ({id})
// Las rutas específicas van ANTES de las parametrizadas ({id})

$router->get('/tasks/search', function () use ($controller, $authService): void {
    Middleware::requireAuth($authService, $controller);
    $controller->searchTasks();
});

$router->get('/tasks/stats', function () use ($controller, $authService): void {
    Middleware::requireAuth($authService, $controller);
    $controller->statistics();
});

$router->get('/tasks/export', function () use ($controller, $authService): void {
    Middleware::requireAuth($authService, $controller);
    $controller->exportTasks();
});

$router->get('/tasks', function () use ($controller, $authService): void {
    Middleware::requireAuth($authService, $controller);
    $controller->listTasks();
});

$router->get('/tasks/{id}', function (array $params) use ($controller, $authService): void {
    Middleware::requireAuth($authService, $controller);
    $controller->getTask($params);
});

$router->post('/tasks', function () use ($controller, $authService): void {
    Middleware::requireAuth($authService, $controller);
    $body = Middleware::jsonBody();
    $controller->createTask($body);
});

$router->patch('/tasks/{id}', function (array $params) use ($controller, $authService): void {
    Middleware::requireAuth($authService, $controller);
    $body = Middleware::jsonBody();
    $controller->updateTask($params, $body);
});

$router->patch('/tasks/{id}/complete', function (array $params) use ($controller, $authService): void {
    Middleware::requireAuth($authService, $controller);
    $controller->completeTask($params);
});

$router->delete('/tasks/{id}', function (array $params) use ($controller, $authService): void {
    Middleware::requireAuth($authService, $controller);
    $controller->deleteTask($params);
});

// --- Protected tag routes / Rutas protegidas de etiquetas ---

$router->get('/tags', function () use ($controller, $authService): void {
    Middleware::requireAuth($authService, $controller);
    $controller->listTags();
});

$router->post('/tags', function () use ($controller, $authService): void {
    Middleware::requireAuth($authService, $controller);
    $body = Middleware::jsonBody();
    $controller->createTag($body);
});

$router->patch('/tags/{id}', function (array $params) use ($controller, $authService): void {
    Middleware::requireAuth($authService, $controller);
    $body = Middleware::jsonBody();
    $controller->updateTag($params, $body);
});

$router->delete('/tags/{id}', function (array $params) use ($controller, $authService): void {
    Middleware::requireAuth($authService, $controller);
    $controller->deleteTag($params);
});

$router->post('/tasks/{id}/tags', function (array $params) use ($controller, $authService): void {
    Middleware::requireAuth($authService, $controller);
    $body = Middleware::jsonBody();
    $controller->syncTaskTags($params, $body);
});

// ---------------------------------------------------------------
//  Request dispatch / Despacho de petición
// ---------------------------------------------------------------

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    $match = $router->resolve(method: $method, uri: $uri);
    ($match->handler)($match->params);
} catch (AppException $e) {
    JsonResponse::error(
        message: $e->getMessage(),
        code: 500,
    );
} catch (\Throwable $e) {
    JsonResponse::error(
        message: 'Internal server error: ' . $e->getMessage(),
        code: 500,
    );
}
