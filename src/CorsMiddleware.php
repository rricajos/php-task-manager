<?php

declare(strict_types=1);

namespace MiniProject;

/**
 * Middleware CORS para la API REST.
 * CORS middleware for the REST API.
 *
 * Gestiona las cabeceras de intercambio de recursos entre orígenes (CORS)
 * y el manejo de peticiones preflight OPTIONS.
 *
 * Manages Cross-Origin Resource Sharing (CORS) headers and
 * preflight OPTIONS request handling.
 *
 * PHP 8 features: constructor promotion, readonly, named arguments
 */
class CorsMiddleware
{
    /**
     * Métodos HTTP permitidos por defecto.
     * Default allowed HTTP methods.
     */
    private const DEFAULT_METHODS = 'GET, POST, PUT, PATCH, DELETE, OPTIONS';

    /**
     * Cabeceras permitidas por defecto.
     * Default allowed headers.
     */
    private const DEFAULT_HEADERS = 'Content-Type, Authorization';

    /**
     * Tiempo de caché del preflight en segundos (1 hora).
     * Preflight cache time in seconds (1 hour).
     */
    private const DEFAULT_MAX_AGE = 3600;

    /**
     * Origen permitido para CORS.
     * Allowed origin for CORS.
     */
    private readonly string $allowedOrigin;

    /**
     * Inicializa el middleware CORS con origen configurable.
     * Initializes the CORS middleware with a configurable origin.
     *
     * Lee el origen de: parámetro inyectado > variable de entorno CORS_ORIGIN > '*'.
     * Reads the origin from: injected parameter > CORS_ORIGIN env var > '*'.
     *
     * @param string $origin Origen permitido (vacío = leer de entorno) / Allowed origin (empty = read from env)
     */
    public function __construct(string $origin = '')
    {
        if ($origin !== '') {
            $this->allowedOrigin = $origin;
        } else {
            $envOrigin = getenv('CORS_ORIGIN');
            $this->allowedOrigin = ($envOrigin !== false && $envOrigin !== '') ? $envOrigin : '*';
        }
    }

    /**
     * Envía las cabeceras CORS y maneja las peticiones preflight.
     * Sends CORS headers and handles preflight requests.
     *
     * Si la petición es OPTIONS (preflight), responde 204 y termina.
     * Si no, envía las cabeceras CORS y continúa.
     *
     * If the request is OPTIONS (preflight), responds with 204 and exits.
     * Otherwise, sends CORS headers and continues.
     *
     * @param string $requestMethod Método HTTP de la petición / HTTP request method
     */
    public function handle(string $requestMethod): void
    {
        header("Access-Control-Allow-Origin: {$this->allowedOrigin}");
        header('Access-Control-Allow-Methods: ' . self::DEFAULT_METHODS);
        header('Access-Control-Allow-Headers: ' . self::DEFAULT_HEADERS);
        header('Access-Control-Max-Age: ' . self::DEFAULT_MAX_AGE);

        if (strtoupper($requestMethod) === 'OPTIONS') {
            JsonResponse::noContent();
        }
    }

    /**
     * Obtiene el origen permitido configurado.
     * Gets the configured allowed origin.
     *
     * @return string Origen permitido / Allowed origin
     */
    public function getAllowedOrigin(): string
    {
        return $this->allowedOrigin;
    }
}
