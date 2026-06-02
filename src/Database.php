<?php

declare(strict_types=1);

namespace MiniProject;

use PDO;
use PDOException;

/**
 * Conexión a base de datos usando el patrón Singleton.
 * Database connection using the Singleton pattern.
 *
 * Garantiza una única instancia de conexión PDO a SQLite durante
 * todo el ciclo de vida de la aplicación. Crea las tablas de usuarios
 * y tareas automáticamente si no existen.
 *
 * Ensures a single PDO connection instance to SQLite throughout
 * the application lifecycle. Automatically creates user and task
 * tables if they don't exist.
 *
 * Pattern: Singleton
 * PHP 8 features: constructor promotion, readonly, match
 */
class Database
{
    /** Instancia única (Singleton) / Singleton instance */
    private static ?self $instance = null;

    /** Conexión PDO a SQLite / PDO connection to SQLite */
    private readonly PDO $pdo;

    /**
     * Constructor privado para prevenir instanciación directa.
     * Private constructor to prevent direct instantiation.
     *
     * @param string $dbPath Ruta al archivo SQLite / Path to the SQLite file
     */
    private function __construct(
        private readonly string $dbPath,
    ) {
        try {
            $dir = dirname($this->dbPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $this->pdo = new PDO(
                dsn: "sqlite:{$this->dbPath}",
            );

            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            $this->pdo->exec('PRAGMA foreign_keys = ON');
            $this->pdo->exec('PRAGMA journal_mode = WAL');

            $this->initializeTables();
        } catch (PDOException $e) {
            throw new AppException(
                message: "Database connection error: {$e->getMessage()}",
                code: AppException::ERROR_DATABASE,
                previous: $e,
            );
        }
    }

    /**
     * Obtiene la instancia única de la base de datos (Singleton).
     * Gets the singleton Database instance.
     *
     * @param string|null $dbPath Ruta al archivo SQLite / Path to SQLite file
     * @return self Instancia única / Singleton instance
     */
    public static function getInstance(?string $dbPath = null): self
    {
        if (self::$instance === null) {
            $envPath = getenv('DB_PATH');
            $defaultPath = dirname(__DIR__) . '/data/tasks.db';

            if ($dbPath !== null) {
                $path = $dbPath;
            } elseif ($envPath !== false && $envPath !== '') {
                $path = str_starts_with($envPath, '/')
                    ? $envPath
                    : dirname(__DIR__) . '/' . $envPath;
            } else {
                $path = $defaultPath;
            }

            self::$instance = new self($path);
        }

        return self::$instance;
    }

    /**
     * Obtiene la conexión PDO subyacente.
     * Gets the underlying PDO connection.
     *
     * @return PDO Instancia de conexión / Connection instance
     */
    public function getConnection(): PDO
    {
        return $this->pdo;
    }

    /**
     * Crea las tablas necesarias si no existen.
     * Creates the required tables if they don't exist.
     *
     * Se crean en orden: primero users (sin dependencias),
     * luego tasks (con FK a users).
     *
     * Created in order: users first (no dependencies),
     * then tasks (with FK to users).
     */
    private function initializeTables(): void
    {
        $sqlUsers = <<<'SQL'
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        SQL;

        $this->pdo->exec($sqlUsers);

        $sqlTasks = <<<'SQL'
            CREATE TABLE IF NOT EXISTS tasks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                title TEXT NOT NULL,
                description TEXT DEFAULT '',
                priority TEXT NOT NULL DEFAULT 'medium' CHECK(priority IN ('high', 'medium', 'low')),
                status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending', 'completed')),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                completed_at DATETIME DEFAULT NULL,
                due_date DATE DEFAULT NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        SQL;

        $this->pdo->exec($sqlTasks);

        $sqlTags = <<<'SQL'
            CREATE TABLE IF NOT EXISTS tags (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                color TEXT NOT NULL DEFAULT '#6b7280',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE(user_id, name)
            )
        SQL;

        $this->pdo->exec($sqlTags);

        $sqlTaskTags = <<<'SQL'
            CREATE TABLE IF NOT EXISTS task_tags (
                task_id INTEGER NOT NULL,
                tag_id INTEGER NOT NULL,
                PRIMARY KEY (task_id, tag_id),
                FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
                FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
            )
        SQL;

        $this->pdo->exec($sqlTaskTags);

        $sqlRateLimits = <<<'SQL'
            CREATE TABLE IF NOT EXISTS rate_limits (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                rate_key TEXT NOT NULL,
                timestamp REAL NOT NULL
            )
        SQL;

        $this->pdo->exec($sqlRateLimits);

        $this->pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_rate_limits_key_ts ON rate_limits (rate_key, timestamp)'
        );
    }

    /**
     * Resetea la instancia Singleton (útil para testing).
     * Resets the Singleton instance (useful for testing).
     */
    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    /**
     * Prevenir clonación del Singleton.
     * Prevent Singleton cloning.
     */
    private function __clone(): void
    {
    }

    /**
     * Prevenir deserialización del Singleton.
     * Prevent Singleton deserialization.
     */
    public function __wakeup(): void
    {
        throw new AppException('Cannot deserialize a Singleton');
    }
}
