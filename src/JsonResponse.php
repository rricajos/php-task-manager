<?php

declare(strict_types=1);

namespace MiniProject;

/**
 * Helper para respuestas JSON en la API REST.
 * Helper for JSON responses in the REST API.
 *
 * Proporciona métodos estáticos para generar respuestas HTTP consistentes
 * con el Content-Type correcto y códigos de estado apropiados.
 *
 * Provides static methods to generate consistent HTTP responses
 * with the correct Content-Type and appropriate status codes.
 *
 * PHP 8 features: named arguments, match
 */
class JsonResponse
{
    /**
     * Controla si exit() se ejecuta al enviar respuestas.
     * En modo testing, se lanza una excepción en lugar de exit().
     *
     * Controls whether exit() is called when sending responses.
     * In testing mode, a RuntimeException is thrown instead of exit().
     */
    private static bool $exitEnabled = true;

    /**
     * Habilita o deshabilita la llamada a exit() al enviar respuestas.
     * Enables or disables the exit() call when sending responses.
     *
     * @param bool $enabled true para exit() normal, false para excepción (testing)
     *                      true for normal exit(), false for exception (testing)
     */
    public static function enableExit(bool $enabled = true): void
    {
        self::$exitEnabled = $enabled;
    }

    /**
     * Envía una respuesta JSON exitosa.
     * Sends a successful JSON response.
     *
     * @param mixed $data Datos a incluir / Data to include
     * @param int $code Código de estado HTTP / HTTP status code
     * @param string $message Mensaje descriptivo / Descriptive message
     * @return never Termina la ejecución / Terminates execution
     */
    public static function success(
        mixed $data = null,
        int $code = 200,
        string $message = 'OK',
    ): never {
        self::send(
            body: [
                'success' => true,
                'message' => $message,
                'data' => $data,
            ],
            code: $code,
        );
    }

    /**
     * Envía una respuesta JSON de error.
     * Sends an error JSON response.
     *
     * @param string $message Mensaje del error / Error message
     * @param int $code Código de estado HTTP / HTTP status code
     * @param array<string, mixed>|null $errors Detalles del error / Error details
     * @return never Termina la ejecución / Terminates execution
     */
    public static function error(
        string $message,
        int $code = 400,
        ?array $errors = null,
    ): never {
        $body = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $body['errors'] = $errors;
        }

        self::send(body: $body, code: $code);
    }

    /**
     * Envía una respuesta JSON paginada exitosa.
     * Sends a successful paginated JSON response.
     *
     * @param array<int, mixed> $data Datos de la página actual / Current page data
     * @param array<string, int> $pagination Metadatos de paginación / Pagination metadata
     * @param string $message Mensaje descriptivo / Descriptive message
     * @return never Termina la ejecución / Terminates execution
     */
    public static function paginated(array $data, array $pagination, string $message = 'OK'): never
    {
        self::send(
            body: [
                'success' => true,
                'message' => $message,
                'data' => $data,
                'pagination' => $pagination,
            ],
            code: 200,
        );
    }

    /**
     * Envía una respuesta HTTP 204 No Content (sin cuerpo).
     * Sends an HTTP 204 No Content response (no body).
     *
     * Usado para operaciones exitosas que no retornan datos (ej: DELETE).
     * Used for successful operations that return no data (e.g., DELETE).
     *
     * @return never Termina la ejecución / Terminates execution
     */
    public static function noContent(): never
    {
        http_response_code(204);

        if (self::$exitEnabled) {
            exit;
        }

        throw new \RuntimeException('JsonResponse: exit disabled for testing');
    }

    /**
     * Envía una respuesta HTTP con contenido crudo (no JSON).
     * Sends an HTTP response with raw (non-JSON) content.
     *
     * Útil para exportaciones directas (CSV, archivos).
     * Useful for direct exports (CSV, files).
     *
     * @param string $content Contenido de la respuesta / Response content
     * @param int $code Código de estado HTTP / HTTP status code
     * @param string $contentType Tipo de contenido / Content type
     * @param array<string, string> $headers Cabeceras adicionales / Additional headers
     * @return never Termina la ejecución / Terminates execution
     */
    public static function raw(
        string $content,
        int $code = 200,
        string $contentType = 'application/octet-stream',
        array $headers = [],
    ): never {
        http_response_code($code);
        header("Content-Type: {$contentType}");

        foreach ($headers as $name => $value) {
            header("{$name}: {$value}");
        }

        echo $content;

        if (self::$exitEnabled) {
            exit;
        }

        throw new \RuntimeException('JsonResponse: exit disabled for testing');
    }

    /**
     * Envía la respuesta HTTP con cabeceras JSON y código de estado.
     * Sends the HTTP response with JSON headers and status code.
     *
     * @param array<string, mixed> $body Cuerpo de la respuesta / Response body
     * @param int $code Código de estado HTTP / HTTP status code
     * @return never Termina la ejecución / Terminates execution
     */
    private static function send(array $body, int $code): never
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode(
            value: $body,
            flags: JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );

        if (self::$exitEnabled) {
            exit;
        }

        throw new \RuntimeException('JsonResponse: exit disabled for testing');
    }
}
