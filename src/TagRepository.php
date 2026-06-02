<?php

declare(strict_types=1);

namespace MiniProject;

use PDO;
use PDOException;

/**
 * Repositorio de etiquetas - Patrón Repository.
 * Tag repository - Repository Pattern.
 *
 * Gestiona las operaciones CRUD para etiquetas y las relaciones
 * many-to-many entre tareas y etiquetas (tabla task_tags).
 *
 * Manages CRUD operations for tags and the many-to-many
 * relationships between tasks and tags (task_tags table).
 *
 * Todas las consultas filtran por user_id para garantizar
 * aislamiento de datos entre usuarios.
 *
 * All queries filter by user_id to ensure data isolation
 * between users.
 *
 * PHP 8 features: constructor promotion, readonly, named arguments
 */
class TagRepository
{
    /**
     * Conexión PDO obtenida del Singleton Database.
     * PDO connection obtained from the Database Singleton.
     */
    private readonly PDO $pdo;

    /**
     * Inicializa el repositorio con el ID del usuario propietario.
     * Initializes the repository with the owning user's ID.
     *
     * @param int $userId ID del usuario autenticado / Authenticated user ID
     */
    public function __construct(
        private readonly int $userId,
    ) {
        $this->pdo = Database::getInstance()->getConnection();
    }

    /**
     * Obtiene todas las etiquetas del usuario.
     * Gets all tags for the user.
     *
     * @return array<int, array{id: int, name: string, color: string, created_at: string}> Lista de etiquetas / Tag list
     * @throws AppException Si ocurre un error de base de datos / If a database error occurs
     */
    public function findAll(): array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT id, name, color, created_at FROM tags WHERE user_id = :user_id ORDER BY name ASC'
            );
            $stmt->execute([':user_id' => $this->userId]);

            return array_map(
                callback: fn (array $row): array => [
                    'id' => (int) $row['id'],
                    'name' => $row['name'],
                    'color' => $row['color'],
                    'created_at' => $row['created_at'],
                ],
                array: $stmt->fetchAll(),
            );
        } catch (PDOException $e) {
            throw new AppException(
                message: "Error fetching tags: {$e->getMessage()}",
                code: AppException::ERROR_DATABASE,
                previous: $e,
            );
        }
    }

    /**
     * Busca una etiqueta por su ID (solo del usuario actual).
     * Finds a tag by its ID (current user only).
     *
     * @param int $id Identificador de la etiqueta / Tag identifier
     * @return array{id: int, name: string, color: string, created_at: string} Datos de la etiqueta / Tag data
     * @throws NotFoundException Si la etiqueta no existe o no pertenece al usuario / If the tag does not exist or does not belong to the user
     */
    public function findById(int $id): array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT id, name, color, created_at FROM tags WHERE id = :id AND user_id = :user_id'
            );
            $stmt->execute([
                ':id' => $id,
                ':user_id' => $this->userId,
            ]);
            $row = $stmt->fetch();

            if ($row === false) {
                throw new NotFoundException(
                    resource: 'tag',
                    identifier: $id,
                );
            }

            return [
                'id' => (int) $row['id'],
                'name' => $row['name'],
                'color' => $row['color'],
                'created_at' => $row['created_at'],
            ];
        } catch (PDOException $e) {
            throw new AppException(
                message: "Error finding tag #{$id}: {$e->getMessage()}",
                code: AppException::ERROR_DATABASE,
                previous: $e,
            );
        }
    }

    /**
     * Crea una nueva etiqueta para el usuario.
     * Creates a new tag for the user.
     *
     * @param string $name Nombre de la etiqueta / Tag name
     * @param string $color Color hexadecimal / Hex color
     * @return array{id: int, name: string, color: string, created_at: string} Etiqueta creada / Created tag
     * @throws AppException Si la etiqueta ya existe o error de BD / If tag already exists or DB error
     */
    public function create(string $name, string $color = '#6b7280'): array
    {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO tags (user_id, name, color) VALUES (:user_id, :name, :color)'
            );
            $stmt->execute([
                ':user_id' => $this->userId,
                ':name' => $name,
                ':color' => $color,
            ]);

            $newId = (int) $this->pdo->lastInsertId();

            return $this->findById($newId);
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'UNIQUE constraint failed')) {
                throw new AppException(
                    message: "Tag '{$name}' already exists",
                    code: AppException::ERROR_GENERAL,
                );
            }

            throw new AppException(
                message: "Error creating tag: {$e->getMessage()}",
                code: AppException::ERROR_DATABASE,
                previous: $e,
            );
        }
    }

    /**
     * Actualiza una etiqueta existente.
     * Updates an existing tag.
     *
     * @param int $id Identificador de la etiqueta / Tag identifier
     * @param array<string, string> $data Campos a actualizar / Fields to update
     * @return array{id: int, name: string, color: string, created_at: string} Etiqueta actualizada / Updated tag
     * @throws NotFoundException Si la etiqueta no existe / If the tag does not exist
     */
    public function update(int $id, array $data): array
    {
        $this->findById($id);

        $allowedColumns = ['name', 'color'];
        $invalidColumns = array_diff(array_keys($data), $allowedColumns);
        if ($invalidColumns !== []) {
            throw new AppException(
                message: 'Invalid update columns: ' . implode(', ', $invalidColumns),
                code: AppException::ERROR_GENERAL,
            );
        }

        try {
            $sets = [];
            $params = [':id' => $id, ':user_id' => $this->userId];

            foreach ($data as $column => $val) {
                $sets[] = "{$column} = :{$column}";
                $params[":{$column}"] = $val;
            }

            $sql = 'UPDATE tags SET ' . implode(', ', $sets) . ' WHERE id = :id AND user_id = :user_id';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return $this->findById($id);
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'UNIQUE constraint failed')) {
                throw new AppException(
                    message: 'Tag name already exists',
                    code: AppException::ERROR_GENERAL,
                );
            }

            throw new AppException(
                message: "Error updating tag #{$id}: {$e->getMessage()}",
                code: AppException::ERROR_DATABASE,
                previous: $e,
            );
        }
    }

    /**
     * Elimina una etiqueta (y sus relaciones con tareas).
     * Deletes a tag (and its task relationships).
     *
     * @param int $id Identificador de la etiqueta / Tag identifier
     * @throws NotFoundException Si la etiqueta no existe / If the tag does not exist
     */
    public function delete(int $id): void
    {
        $this->findById($id);

        try {
            $stmt = $this->pdo->prepare(
                'DELETE FROM tags WHERE id = :id AND user_id = :user_id'
            );
            $stmt->execute([
                ':id' => $id,
                ':user_id' => $this->userId,
            ]);
        } catch (PDOException $e) {
            throw new AppException(
                message: "Error deleting tag #{$id}: {$e->getMessage()}",
                code: AppException::ERROR_DATABASE,
                previous: $e,
            );
        }
    }

    /**
     * Obtiene las etiquetas asociadas a una lista de tareas.
     * Gets the tags associated with a list of tasks.
     *
     * Retorna un array indexado por task_id donde cada valor
     * es un array de etiquetas con id, nombre y color.
     *
     * Returns an array indexed by task_id where each value
     * is an array of tags with id, name, and color.
     *
     * @param int[] $taskIds Lista de IDs de tareas / List of task IDs
     * @return array<int, array<int, array{id: int, name: string, color: string}>> Etiquetas por tarea / Tags per task
     */
    public function getTagsByTaskIds(array $taskIds): array
    {
        if ($taskIds === []) {
            return [];
        }

        try {
            $placeholders = implode(',', array_fill(0, count($taskIds), '?'));
            $sql = "SELECT tt.task_id, t.id, t.name, t.color
                    FROM task_tags tt
                    JOIN tags t ON t.id = tt.tag_id
                    WHERE tt.task_id IN ({$placeholders}) AND t.user_id = ?
                    ORDER BY t.name ASC";

            $stmt = $this->pdo->prepare($sql);
            $params = [...array_values($taskIds), $this->userId];
            $stmt->execute($params);

            $result = [];
            foreach ($stmt->fetchAll() as $row) {
                $taskId = (int) $row['task_id'];
                $result[$taskId][] = [
                    'id' => (int) $row['id'],
                    'name' => $row['name'],
                    'color' => $row['color'],
                ];
            }

            return $result;
        } catch (PDOException $e) {
            throw new AppException(
                message: "Error fetching tags for tasks: {$e->getMessage()}",
                code: AppException::ERROR_DATABASE,
                previous: $e,
            );
        }
    }

    /**
     * Sincroniza las etiquetas de una tarea (reemplaza todas).
     * Syncs a task's tags (replaces all).
     *
     * Verifica que la tarea pertenece al usuario y que todos
     * los tag_ids pertenecen al usuario antes de sincronizar.
     *
     * Verifies that the task belongs to the user and that all
     * tag_ids belong to the user before syncing.
     *
     * @param int $taskId ID de la tarea / Task ID
     * @param int[] $tagIds Lista de IDs de etiquetas / List of tag IDs
     * @return array<int, array{id: int, name: string, color: string}> Etiquetas sincronizadas / Synced tags
     * @throws NotFoundException Si la tarea no existe / If the task does not exist
     */
    public function syncTaskTags(int $taskId, array $tagIds): array
    {
        // Verify task belongs to user
        $taskStmt = $this->pdo->prepare(
            'SELECT id FROM tasks WHERE id = :id AND user_id = :user_id'
        );
        $taskStmt->execute([':id' => $taskId, ':user_id' => $this->userId]);
        if ($taskStmt->fetch() === false) {
            throw new NotFoundException(resource: 'task', identifier: $taskId);
        }

        try {
            // Remove all existing tags for this task
            $deleteStmt = $this->pdo->prepare('DELETE FROM task_tags WHERE task_id = :task_id');
            $deleteStmt->execute([':task_id' => $taskId]);

            if ($tagIds === []) {
                return [];
            }

            // Validate all tag IDs belong to user
            $placeholders = implode(',', array_fill(0, count($tagIds), '?'));
            $validateStmt = $this->pdo->prepare(
                "SELECT id FROM tags WHERE id IN ({$placeholders}) AND user_id = ?"
            );
            $validateStmt->execute([...array_values($tagIds), $this->userId]);
            $validIds = array_column($validateStmt->fetchAll(), 'id');

            // Insert valid tag associations
            $insertStmt = $this->pdo->prepare(
                'INSERT OR IGNORE INTO task_tags (task_id, tag_id) VALUES (:task_id, :tag_id)'
            );

            foreach ($validIds as $tagId) {
                $insertStmt->execute([
                    ':task_id' => $taskId,
                    ':tag_id' => (int) $tagId,
                ]);
            }

            $tags = $this->getTagsByTaskIds([$taskId]);

            return $tags[$taskId] ?? [];
        } catch (PDOException $e) {
            throw new AppException(
                message: "Error syncing tags for task #{$taskId}: {$e->getMessage()}",
                code: AppException::ERROR_DATABASE,
                previous: $e,
            );
        }
    }
}
