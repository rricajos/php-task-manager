<?php

declare(strict_types=1);

namespace MiniProject;

/**
 * Enrutador simple para la API REST.
 * Simple router for the REST API.
 *
 * Registra rutas con método HTTP y patrón URI, resuelve la ruta
 * correspondiente a una petición entrante, y extrae parámetros
 * dinámicos de la URI (ej: /tasks/{id}).
 *
 * Registers routes with HTTP method and URI pattern, resolves the
 * matching route for an incoming request, and extracts dynamic
 * parameters from the URI (e.g., /tasks/{id}).
 *
 * PHP 8 features: constructor promotion, readonly, named arguments, match
 */

/**
 * Representa una ruta registrada en el enrutador.
 * Represents a registered route in the router.
 *
 * PHP 8 features: readonly, constructor promotion
 */
class Route
{
    /** @var string Expresión regular compilada / Compiled regular expression */
    public readonly string $regex;

    /** @var string[] Nombres de los parámetros dinámicos / Dynamic parameter names */
    public readonly array $paramNames;

    /**
     * @param string $method Método HTTP (GET, POST, PATCH, DELETE) / HTTP method
     * @param string $pattern Patrón de URI con placeholders / URI pattern with placeholders
     * @param \Closure $handler Función manejadora / Handler function
     */
    public function __construct(
        public readonly string $method,
        public readonly string $pattern,
        public readonly \Closure $handler,
    ) {
        preg_match_all('/{(\w+)}/', $this->pattern, $matches);
        $this->paramNames = $matches[1];

        $regex = preg_replace('/{(\w+)}/', '(?P<$1>[^/]+)', $this->pattern);
        $this->regex = '#^' . $regex . '$#';
    }
}

/**
 * Resultado de la resolución de una ruta.
 * Result of route resolution.
 *
 * PHP 8 features: readonly, constructor promotion
 */
class RouteMatch
{
    /**
     * @param \Closure $handler Función manejadora / Handler function
     * @param array<string, string> $params Parámetros extraídos de la URI / Extracted URI parameters
     */
    public function __construct(
        public readonly \Closure $handler,
        public readonly array $params = [],
    ) {
    }
}

/**
 * Enrutador principal de la API REST.
 * Main REST API router.
 *
 * Gestiona el registro y resolución de rutas HTTP. Soporta
 * parámetros dinámicos en la URI y devuelve respuestas JSON
 * apropiadas para rutas no encontradas o métodos no permitidos.
 *
 * Manages HTTP route registration and resolution. Supports
 * dynamic URI parameters and returns appropriate JSON responses
 * for not found routes or disallowed methods.
 */
class Router
{
    /** @var Route[] Rutas registradas / Registered routes */
    private array $routes = [];

    /**
     * Registra una ruta GET. / Registers a GET route.
     *
     * @param string $pattern Patrón de URI / URI pattern
     * @param \Closure $handler Función manejadora / Handler function
     * @return self Para encadenamiento fluido / For fluent chaining
     */
    public function get(string $pattern, \Closure $handler): self
    {
        return $this->addRoute(method: 'GET', pattern: $pattern, handler: $handler);
    }

    /**
     * Registra una ruta POST. / Registers a POST route.
     *
     * @param string $pattern Patrón de URI / URI pattern
     * @param \Closure $handler Función manejadora / Handler function
     * @return self Para encadenamiento fluido / For fluent chaining
     */
    public function post(string $pattern, \Closure $handler): self
    {
        return $this->addRoute(method: 'POST', pattern: $pattern, handler: $handler);
    }

    /**
     * Registra una ruta PATCH. / Registers a PATCH route.
     *
     * @param string $pattern Patrón de URI / URI pattern
     * @param \Closure $handler Función manejadora / Handler function
     * @return self Para encadenamiento fluido / For fluent chaining
     */
    public function patch(string $pattern, \Closure $handler): self
    {
        return $this->addRoute(method: 'PATCH', pattern: $pattern, handler: $handler);
    }

    /**
     * Registra una ruta PUT. / Registers a PUT route.
     *
     * @param string $pattern Patrón de URI / URI pattern
     * @param \Closure $handler Función manejadora / Handler function
     * @return self Para encadenamiento fluido / For fluent chaining
     */
    public function put(string $pattern, \Closure $handler): self
    {
        return $this->addRoute(method: 'PUT', pattern: $pattern, handler: $handler);
    }

    /**
     * Registra una ruta DELETE. / Registers a DELETE route.
     *
     * @param string $pattern Patrón de URI / URI pattern
     * @param \Closure $handler Función manejadora / Handler function
     * @return self Para encadenamiento fluido / For fluent chaining
     */
    public function delete(string $pattern, \Closure $handler): self
    {
        return $this->addRoute(method: 'DELETE', pattern: $pattern, handler: $handler);
    }

    /**
     * Registra una ruta con método y patrón específicos.
     * Registers a route with specific method and pattern.
     *
     * @param string $method Método HTTP / HTTP method
     * @param string $pattern Patrón de URI / URI pattern
     * @param \Closure $handler Función manejadora / Handler function
     * @return self Para encadenamiento fluido / For fluent chaining
     */
    private function addRoute(string $method, string $pattern, \Closure $handler): self
    {
        $this->routes[] = new Route(
            method: strtoupper($method),
            pattern: $pattern,
            handler: $handler,
        );

        return $this;
    }

    /**
     * Resuelve la ruta que coincide con el método y URI proporcionados.
     * Resolves the route matching the given method and URI.
     *
     * @param string $method Método HTTP de la petición / Request HTTP method
     * @param string $uri URI de la petición (sin query string) / Request URI (no query string)
     * @return RouteMatch Resultado con handler y parámetros / Result with handler and parameters
     */
    public function resolve(string $method, string $uri): RouteMatch
    {
        $method = strtoupper($method);
        $uri = rtrim($uri, '/') ?: '/';

        $patternMatches = false;
        $allowedMethods = [];

        foreach ($this->routes as $route) {
            if (preg_match($route->regex, $uri, $matches)) {
                if ($route->method === $method) {
                    $params = array_filter(
                        $matches,
                        fn (string $key): bool => !is_numeric($key),
                        ARRAY_FILTER_USE_KEY,
                    );

                    return new RouteMatch(
                        handler: $route->handler,
                        params: $params,
                    );
                }

                $patternMatches = true;
                $allowedMethods[] = $route->method;
            }
        }

        if ($patternMatches) {
            $allowed = implode(', ', array_unique($allowedMethods));
            header("Allow: {$allowed}");
            JsonResponse::error(
                message: "Method {$method} not allowed. Accepted methods: {$allowed}",
                code: 405,
            );
        }

        JsonResponse::error(
            message: "Route not found: {$method} {$uri}",
            code: 404,
        );
    }
}
