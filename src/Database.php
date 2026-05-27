<?php

declare(strict_types=1);

namespace MiniProject;

use PDO;
use PDOException;

/**
 * Conexion a base de datos usando el patron Singleton.
 *
 * Garantiza una unica instancia de conexion PDO a SQLite durante
 * todo el ciclo de vida de la aplicacion. Crea las tablas de usuarios
 * y tareas automaticamente si no existen.
 *
 * Patron: Singleton
 * Caracteristicas PHP 8: constructor promotion, readonly, match
 */
class Database
{
    /** Instancia unica (Singleton) */
    private static ?self $instance = null;

    /** Conexion PDO a SQLite */
    private readonly PDO $pdo;

    /**
     * Constructor privado para prevenir instanciacion directa.
     *
     * @param string $dbPath Ruta al archivo de base de datos SQLite
     */
    private function __construct(
        private readonly string $dbPath,
    ) {
        try {
            // Asegurar que el directorio de datos exista
            $dir = dirname($this->dbPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            // Crear conexion PDO con SQLite
            $this->pdo = new PDO(
                dsn: "sqlite:{$this->dbPath}",
            );

            // Configurar PDO para lanzar excepciones en errores
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            // Habilitar claves foraneas en SQLite
            $this->pdo->exec('PRAGMA foreign_keys = ON');

            // Habilitar modo WAL para mejor rendimiento concurrente
            $this->pdo->exec('PRAGMA journal_mode = WAL');

            // Inicializar la estructura de la base de datos
            $this->inicializarTablas();
        } catch (PDOException $e) {
            throw new AppException(
                message: "Error al conectar con la base de datos: {$e->getMessage()}",
                code: AppException::ERROR_DATABASE,
                previous: $e,
            );
        }
    }

    /**
     * Obtiene la instancia unica de la base de datos (Singleton).
     *
     * @param string|null $dbPath Ruta al archivo SQLite (solo se usa en la primera llamada)
     * @return self Instancia unica de Database
     */
    public static function getInstance(?string $dbPath = null): self
    {
        if (self::$instance === null) {
            // Leer ruta desde variable de entorno con fallback
            $envPath = getenv('DB_PATH');
            $defaultPath = dirname(__DIR__) . '/data/tasks.db';

            if ($dbPath !== null) {
                $path = $dbPath;
            } elseif ($envPath !== false && $envPath !== '') {
                // Si DB_PATH es relativa, resolverla desde el directorio del proyecto
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
     * Obtiene la conexion PDO subyacente.
     *
     * @return PDO Instancia de conexion PDO
     */
    public function getConnection(): PDO
    {
        return $this->pdo;
    }

    /**
     * Crea las tablas necesarias si no existen.
     *
     * Se crean en orden: primero users (sin dependencias),
     * luego tasks (con FK a users).
     *
     * Estructura de la tabla users:
     * - id: clave primaria autoincremental
     * - username: nombre de usuario unico
     * - password_hash: contrasena hasheada con bcrypt
     * - created_at: fecha de registro
     *
     * Estructura de la tabla tasks:
     * - id: clave primaria autoincremental
     * - user_id: clave foranea a users(id)
     * - titulo: titulo de la tarea (obligatorio)
     * - descripcion: descripcion detallada (opcional)
     * - prioridad: alta, media o baja
     * - estado: pendiente o completada
     * - fecha_creacion: timestamp de creacion
     * - fecha_completada: timestamp de cuando se completo (nullable)
     * - fecha_vencimiento: fecha limite de la tarea (nullable)
     */
    private function inicializarTablas(): void
    {
        // Crear tabla de usuarios primero (dependencia de FK)
        $sqlUsers = <<<'SQL'
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        SQL;

        $this->pdo->exec($sqlUsers);

        // Crear tabla de tareas con FK a users
        $sqlTasks = <<<'SQL'
            CREATE TABLE IF NOT EXISTS tasks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                titulo TEXT NOT NULL,
                descripcion TEXT DEFAULT '',
                prioridad TEXT NOT NULL DEFAULT 'media' CHECK(prioridad IN ('alta', 'media', 'baja')),
                estado TEXT NOT NULL DEFAULT 'pendiente' CHECK(estado IN ('pendiente', 'completada')),
                fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
                fecha_completada DATETIME DEFAULT NULL,
                fecha_vencimiento DATE DEFAULT NULL,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )
        SQL;

        $this->pdo->exec($sqlTasks);
    }

    /**
     * Resetea la instancia Singleton (util para testing).
     */
    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    /**
     * Prevenir clonacion del Singleton.
     */
    private function __clone(): void {}

    /**
     * Prevenir deserializacion del Singleton.
     */
    public function __wakeup(): void
    {
        throw new AppException('No se puede deserializar un Singleton');
    }
}
