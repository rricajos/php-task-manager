<?php

declare(strict_types=1);

namespace MiniProject;

use PDO;

/**
 * Limitador de tasa basado en ventana deslizante con SQLite.
 * Sliding window rate limiter using SQLite.
 *
 * Rastrea peticiones por clave (IP o usuario) usando una ventana
 * deslizante almacenada en la tabla rate_limits de SQLite. Envía
 * cabeceras estándar de rate limit (X-RateLimit-*).
 *
 * Tracks requests per key (IP or user) using a sliding window
 * stored in the SQLite rate_limits table. Sends standard rate
 * limit headers (X-RateLimit-*).
 *
 * PHP 8 features: constructor promotion, readonly, named arguments
 */
class RateLimiter
{
    /**
     * Conexión PDO a la base de datos.
     * PDO connection to the database.
     */
    private readonly PDO $pdo;

    /**
     * Peticiones restantes en la ventana actual.
     * Remaining requests in the current window.
     */
    private int $remaining = 0;

    /**
     * Momento en que la ventana se resetea (Unix timestamp).
     * When the window resets (Unix timestamp).
     */
    private int $resetAt = 0;

    /**
     * Inicializa el limitador de tasa.
     * Initializes the rate limiter.
     *
     * @param PDO|null $pdo Conexión PDO (null = obtener del Singleton) /
     *                      PDO connection (null = get from Singleton)
     */
    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getInstance()->getConnection();
    }

    /**
     * Verifica si una petición está permitida dentro del límite de tasa.
     * Checks if a request is allowed within the rate limit.
     *
     * Usa una ventana deslizante: cuenta las peticiones para la clave
     * dada dentro de los últimos $windowSeconds segundos. Si el conteo
     * supera $maxRequests, la petición es rechazada.
     *
     * Uses a sliding window: counts requests for the given key within
     * the last $windowSeconds seconds. If the count exceeds $maxRequests,
     * the request is rejected.
     *
     * @param string $key Identificador del cliente (IP, user ID, etc.) / Client identifier
     * @param int $maxRequests Máximo de peticiones por ventana / Maximum requests per window
     * @param int $windowSeconds Duración de la ventana en segundos / Window duration in seconds
     * @return bool true si la petición está permitida / true if the request is allowed
     */
    public function check(string $key, int $maxRequests, int $windowSeconds): bool
    {
        $now = microtime(as_float: true);
        $windowStart = $now - $windowSeconds;
        $this->resetAt = (int) ceil($now) + $windowSeconds;

        // Limpiar entradas expiradas para esta clave / Clean expired entries for this key
        $deleteStmt = $this->pdo->prepare(
            'DELETE FROM rate_limits WHERE rate_key = :key AND timestamp < :window_start'
        );
        $deleteStmt->execute([
            ':key' => $key,
            ':window_start' => $windowStart,
        ]);

        // Contar peticiones en la ventana actual / Count requests in the current window
        $countStmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM rate_limits WHERE rate_key = :key AND timestamp >= :window_start'
        );
        $countStmt->execute([
            ':key' => $key,
            ':window_start' => $windowStart,
        ]);
        $currentCount = (int) $countStmt->fetchColumn();

        if ($currentCount >= $maxRequests) {
            $this->remaining = 0;

            return false;
        }

        // Registrar la petición actual / Record the current request
        $insertStmt = $this->pdo->prepare(
            'INSERT INTO rate_limits (rate_key, timestamp) VALUES (:key, :timestamp)'
        );
        $insertStmt->execute([
            ':key' => $key,
            ':timestamp' => $now,
        ]);

        $this->remaining = $maxRequests - $currentCount - 1;

        return true;
    }

    /**
     * Obtiene las peticiones restantes en la ventana actual.
     * Gets the remaining requests in the current window.
     *
     * @return int Peticiones restantes / Remaining requests
     */
    public function getRemainingRequests(): int
    {
        return $this->remaining;
    }

    /**
     * Obtiene el número de segundos hasta que se puede reintentar.
     * Gets the number of seconds until a retry is possible.
     *
     * @return int Segundos hasta el reseteo / Seconds until reset
     */
    public function getRetryAfter(): int
    {
        return max(0, $this->resetAt - time());
    }

    /**
     * Obtiene el timestamp Unix de reseteo de la ventana.
     * Gets the Unix timestamp of the window reset.
     *
     * @return int Timestamp de reseteo / Reset timestamp
     */
    public function getResetAt(): int
    {
        return $this->resetAt;
    }

    /**
     * Envía las cabeceras estándar de rate limit en la respuesta HTTP.
     * Sends standard rate limit headers in the HTTP response.
     *
     * @param int $maxRequests Límite máximo configurado / Configured maximum limit
     */
    public function sendHeaders(int $maxRequests): void
    {
        header("X-RateLimit-Limit: {$maxRequests}");
        header("X-RateLimit-Remaining: {$this->remaining}");
        header("X-RateLimit-Reset: {$this->resetAt}");
    }

    /**
     * Limpia todas las entradas expiradas de la tabla (mantenimiento).
     * Cleans all expired entries from the table (maintenance).
     *
     * Se puede llamar periódicamente para mantener la tabla pequeña.
     * Can be called periodically to keep the table small.
     *
     * @param int $olderThanSeconds Eliminar entradas más antiguas que N segundos /
     *                              Delete entries older than N seconds
     * @return int Número de filas eliminadas / Number of deleted rows
     */
    public function cleanup(int $olderThanSeconds = 3600): int
    {
        $cutoff = microtime(as_float: true) - $olderThanSeconds;
        $stmt = $this->pdo->prepare(
            'DELETE FROM rate_limits WHERE timestamp < :cutoff'
        );
        $stmt->execute([':cutoff' => $cutoff]);

        return $stmt->rowCount();
    }
}
