<?php

declare(strict_types=1);

namespace MiniProject;

/**
 * Middleware HTTP para la API REST.
 * HTTP middleware for the REST API.
 *
 * Proporciona funciones auxiliares para el procesamiento de peticiones:
 * parseo del cuerpo JSON y autenticación mediante token JWT.
 *
 * Provides helper functions for request processing:
 * JSON body parsing and JWT token authentication.
 */
class Middleware
{
    /**
     * Obtiene el cuerpo de la petición como array asociativo.
     * Gets the request body as an associative array.
     *
     * Lee el body de la petición HTTP, lo decodifica como JSON
     * y retorna un array. Si el body está vacío o no es JSON válido,
     * responde con error 400.
     *
     * Reads the HTTP request body, decodes it as JSON and returns
     * an array. If the body is empty or not valid JSON, responds
     * with a 400 error.
     *
     * @param string|null $rawInput Input crudo para testing (null usa php://input) / Raw input for testing (null uses php://input)
     * @return array<string, mixed> Cuerpo decodificado / Decoded body
     */
    public static function jsonBody(?string $rawInput = null): array
    {
        $raw = $rawInput ?? file_get_contents('php://input');

        if ($raw === false || $raw === '') {
            return [];
        }

        $data = json_decode($raw, true);

        if (!is_array($data)) {
            JsonResponse::error(
                message: 'Request body must be valid JSON',
                code: 400,
            );
        }

        return $data;
    }

    /**
     * Middleware de autenticación: verifica el token JWT.
     * Authentication middleware: verifies the JWT token.
     *
     * Extrae y valida el token Bearer del header Authorization.
     * Si el token es inválido o no está presente, responde con 401.
     * Si es válido, establece el userId en el controlador.
     *
     * Extracts and validates the Bearer token from the Authorization header.
     * If the token is invalid or missing, responds with 401.
     * If valid, sets the userId on the controller.
     *
     * @param AuthServiceInterface $authService Servicio de autenticación / Authentication service
     * @param ApiController $controller Controlador de la API / API controller
     * @return array{user_id: int, username: string} Datos del usuario autenticado / Authenticated user data
     */
    public static function requireAuth(AuthServiceInterface $authService, ApiController $controller): array
    {
        try {
            $userData = $authService->authenticate();
            $controller->setUserId((int) $userData['user_id']);

            return $userData;
        } catch (AppException $e) {
            JsonResponse::error(
                message: $e->getMessage(),
                code: 401,
            );
        }
    }

    /**
     * Middleware de limitación de tasa: verifica los límites por clave.
     * Rate limiting middleware: checks limits per key.
     *
     * Verifica que el cliente no haya excedido el número máximo
     * de peticiones permitidas en la ventana de tiempo configurada.
     * Envía cabeceras X-RateLimit-* y responde 429 si se excede el límite.
     *
     * Checks that the client has not exceeded the maximum number of
     * allowed requests in the configured time window. Sends
     * X-RateLimit-* headers and responds 429 if the limit is exceeded.
     *
     * @param RateLimiter $limiter Instancia del limitador de tasa / Rate limiter instance
     * @param string $key Identificador del cliente (IP, etc.) / Client identifier (IP, etc.)
     * @param int $maxRequests Peticiones máximas por ventana / Maximum requests per window
     * @param int $windowSeconds Duración de la ventana en segundos / Window duration in seconds
     */
    public static function rateLimit(
        RateLimiter $limiter,
        string $key,
        int $maxRequests,
        int $windowSeconds,
    ): void {
        $allowed = $limiter->check(
            key: $key,
            maxRequests: $maxRequests,
            windowSeconds: $windowSeconds,
        );

        $limiter->sendHeaders(maxRequests: $maxRequests);

        if (!$allowed) {
            JsonResponse::error(
                message: 'Too many requests. Please try again later.',
                code: 429,
            );
        }
    }
}
