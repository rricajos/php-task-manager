<?php

declare(strict_types=1);

namespace MiniProject;

use PDO;
use PDOException;

/**
 * Repositorio de tareas - Patr\u00f3n Repository.
 * Task repository - Repository Pattern.
 *
 * Encapsula toda la l\u00f3gica de acceso a datos para la entidad Task.
 * Usa sentencias preparadas (prepared statements) para prevenir
 * inyecci\u00f3n SQL y manejo de errores adecuado.
 *
 * Encapsulates all data access logic for the Task entity.
 * Uses prepared statements to prevent SQL injection
 * and provides proper error handling.
 *
 * Todas las consultas filtran por user_id para garantizar aislamiento
 * de datos entre usuarios.
 *
 * All queries filter by user_id to ensure data isolation
 * between users.
 *
 * Patr\u00f3n: Repository / Pattern: Repository
 * Caracter\u00edsticas PHP 8: constructor promotion, readonly, named arguments, match
 * PHP 8 features: constructor promotion, readonly, named arguments, match
 */
class TaskRepository
{
    /**
     * Columnas que pueden ser actualizadas mediante update().
     * Columns that can be updated via update().
     *
     * Whitelist de seguridad para prevenir SQL injection a
     * través de nombres de columna no validados.
     *
     * Security whitelist to prevent SQL injection through
     * unvalidated column names.
     *
     * @var string[]
     */
    private const ALLOWED_UPDATE_COLUMNS = [
        'title',
        'description',
        'priority',
        'status',
        'due_date',
        'completed_at',
        'recurrence',
    ];

    /**
     * Conexi\u00f3n PDO obtenida del Singleton Database.
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
     * Obtiene todas las tareas del usuario ordenadas por fecha de creaci\u00f3n descendente.
     * Retrieves all tasks for the user ordered by creation date descending.
     *
     * @return Task[] Lista de todas las tareas del usuario / List of all user tasks
     * @throws AppException Si ocurre un error de base de datos / If a database error occurs
     */
    public function findAll(): array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT * FROM tasks WHERE user_id = :user_id ORDER BY created_at DESC'
            );
            $stmt->execute([':user_id' => $this->userId]);
            $rows = $stmt->fetchAll();

            // Map each row to a Task instance using the factory method
            return array_map(
                callback: fn (array $row): Task => Task::fromRow($row),
                array: $rows,
            );
        } catch (PDOException $e) {
            throw new AppException(
                message: "Error fetching tasks: {$e->getMessage()}",
                code: AppException::ERROR_DATABASE,
                previous: $e,
            );
        }
    }

    /**
     * Busca una tarea por su identificador \u00fanico (solo del usuario actual).
     * Finds a task by its unique identifier (current user only).
     *
     * @param int $id Identificador de la tarea / Task identifier
     * @return Task La tarea encontrada / The found task
     * @throws NotFoundException Si la tarea no existe o no pertenece al usuario / If the task does not exist or does not belong to the user
     * @throws AppException Si ocurre un error de base de datos / If a database error occurs
     */
    public function findById(int $id): Task
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT * FROM tasks WHERE id = :id AND user_id = :user_id'
            );
            $stmt->execute([
                ':id' => $id,
                ':user_id' => $this->userId,
            ]);
            $row = $stmt->fetch();

            if ($row === false) {
                throw new NotFoundException(
                    resource: 'task',
                    identifier: $id,
                );
            }

            return Task::fromRow($row);
        } catch (PDOException $e) {
            throw new AppException(
                message: "Error finding task #{$id}: {$e->getMessage()}",
                code: AppException::ERROR_DATABASE,
                previous: $e,
            );
        }
    }

    /**
     * Filtra tareas del usuario por estado (pendiente o completada).
     * Filters user tasks by status (pending or completed).
     *
     * @param Status $status Estado por el cual filtrar / Status to filter by
     * @return Task[] Lista de tareas con el estado dado / List of tasks with the given status
     * @throws AppException Si ocurre un error de base de datos / If a database error occurs
     */
    public function findByStatus(Status $status): array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT * FROM tasks WHERE user_id = :user_id AND status = :status ORDER BY created_at DESC'
            );
            $stmt->execute([
                ':user_id' => $this->userId,
                ':status' => $status->value,
            ]);
            $rows = $stmt->fetchAll();

            return array_map(
                callback: fn (array $row): Task => Task::fromRow($row),
                array: $rows,
            );
        } catch (PDOException $e) {
            throw new AppException(
                message: "Error filtering tasks by status: {$e->getMessage()}",
                code: AppException::ERROR_DATABASE,
                previous: $e,
            );
        }
    }

    /**
     * Obtiene tareas del usuario con paginaci\u00f3n, ordenamiento y filtros opcionales.
     * Retrieves user tasks with pagination, sorting, and optional filters.
     *
     * Construye una consulta din\u00e1mica con cl\u00e1usula WHERE para user_id y filtros
     * opcionales de prioridad y estado. Valida los campos de ordenamiento
     * contra una lista blanca para prevenir inyecci\u00f3n SQL.
     *
     * Builds a dynamic query with a WHERE clause for user_id and optional
     * priority and status filters. Validates sort fields against a whitelist
     * to prevent SQL injection.
     *
     * @param int $limit N\u00famero m\u00e1ximo de resultados por p\u00e1gina / Maximum number of results per page
     * @param int $offset Desplazamiento desde el inicio / Offset from the beginning
     * @param string $sortBy Campo de ordenamiento (validado contra whitelist) / Sort field (validated against whitelist)
     * @param string $sortDir Direcci\u00f3n de ordenamiento: 'ASC' o 'DESC' / Sort direction: 'ASC' or 'DESC'
     * @param string|null $priority Filtro opcional de prioridad (high, medium, low) / Optional priority filter (high, medium, low)
     * @param string|null $status Filtro opcional de estado (pending, completed) / Optional status filter (pending, completed)
     * @return Task[] Lista de tareas paginadas / Paginated task list
     * @throws AppException Si ocurre un error de base de datos / If a database error occurs
     */
    public function findAllPaginated(
        int $limit = 20,
        int $offset = 0,
        string $sortBy = 'created_at',
        string $sortDir = 'DESC',
        ?string $priority = null,
        ?string $status = null,
    ): array {
        // Validate sortBy against whitelist to prevent SQL injection
        $allowedSortBy = ['created_at', 'title', 'priority', 'status', 'due_date'];
        if (!in_array($sortBy, $allowedSortBy, strict: true)) {
            $sortBy = 'created_at';
        }

        // Validate sortDir
        $sortDir = strtoupper($sortDir);
        if (!in_array($sortDir, ['ASC', 'DESC'], strict: true)) {
            $sortDir = 'DESC';
        }

        try {
            // Build dynamic WHERE clause
            $where = 'WHERE user_id = :user_id';
            $params = [':user_id' => $this->userId];

            if ($priority !== null) {
                $where .= ' AND priority = :priority';
                $params[':priority'] = $priority;
            }

            if ($status !== null) {
                $where .= ' AND status = :status';
                $params[':status'] = $status;
            }

            $sql = "SELECT * FROM tasks {$where} ORDER BY {$sortBy} {$sortDir} LIMIT :limit OFFSET :offset";
            $stmt = $this->pdo->prepare($sql);

            // Bind pagination parameters as integers
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

            $stmt->execute();
            $rows = $stmt->fetchAll();

            return array_map(
                callback: fn (array $row): Task => Task::fromRow($row),
                array: $rows,
            );
        } catch (PDOException $e) {
            throw new AppException(
                message: "Error fetching paginated tasks: {$e->getMessage()}",
                code: AppException::ERROR_DATABASE,
                previous: $e,
            );
        }
    }

    /**
     * Cuenta el total de tareas del usuario que coinciden con los filtros.
     * Counts the total user tasks matching the given filters.
     *
     * Usado para calcular metadatos de paginaci\u00f3n (total de p\u00e1ginas, etc).
     * Used to calculate pagination metadata (total pages, etc).
     *
     * @param string|null $priority Filtro opcional de prioridad / Optional priority filter
     * @param string|null $status Filtro opcional de estado / Optional status filter
     * @return int N\u00famero total de tareas que coinciden / Total number of matching tasks
     * @throws AppException Si ocurre un error de base de datos / If a database error occurs
     */
    public function countFiltered(?string $priority = null, ?string $status = null): int
    {
        try {
            $where = 'WHERE user_id = :user_id';
            $params = [':user_id' => $this->userId];

            if ($priority !== null) {
                $where .= ' AND priority = :priority';
                $params[':priority'] = $priority;
            }

            if ($status !== null) {
                $where .= ' AND status = :status';
                $params[':status'] = $status;
            }

            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM tasks {$where}");
            $stmt->execute($params);

            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            throw new AppException(
                message: "Error counting filtered tasks: {$e->getMessage()}",
                code: AppException::ERROR_DATABASE,
                previous: $e,
            );
        }
    }

    /**
     * Busca tareas del usuario cuyo t\u00edtulo o descripci\u00f3n contengan la palabra clave.
     * Searches user tasks whose title or description contain the keyword.
     *
     * Soporta paginaci\u00f3n con limit/offset para resultados grandes.
     * Supports pagination with limit/offset for large result sets.
     *
     * @param string $keyword Palabra clave de b\u00fasqueda / Search keyword
     * @param int $limit N\u00famero m\u00e1ximo de resultados por p\u00e1gina / Maximum number of results per page
     * @param int $offset Desplazamiento desde el inicio / Offset from the beginning
     * @return Task[] Lista de tareas que coinciden con la b\u00fasqueda / List of tasks matching the search
     * @throws AppException Si ocurre un error de base de datos / If a database error occurs
     */
    public function search(string $keyword, int $limit = 20, int $offset = 0): array
    {
        try {
            $sql = 'SELECT * FROM tasks
                 WHERE user_id = :user_id AND (title LIKE :keyword OR description LIKE :keyword)
                 ORDER BY created_at DESC
                 LIMIT :limit OFFSET :offset';
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':user_id', $this->userId);
            $stmt->bindValue(':keyword', "%{$keyword}%");
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll();

            return array_map(
                callback: fn (array $row): Task => Task::fromRow($row),
                array: $rows,
            );
        } catch (PDOException $e) {
            throw new AppException(
                message: "Error searching tasks: {$e->getMessage()}",
                code: AppException::ERROR_DATABASE,
                previous: $e,
            );
        }
    }

    /**
     * Cuenta el total de tareas del usuario que coinciden con la b\u00fasqueda.
     * Counts the total user tasks matching the search.
     *
     * Usado para calcular metadatos de paginaci\u00f3n en b\u00fasquedas.
     * Used to calculate pagination metadata for searches.
     *
     * @param string $keyword Palabra clave de b\u00fasqueda / Search keyword
     * @return int N\u00famero total de tareas que coinciden / Total number of matching tasks
     * @throws AppException Si ocurre un error de base de datos / If a database error occurs
     */
    public function countSearch(string $keyword): int
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT COUNT(*) FROM tasks
                 WHERE user_id = :user_id AND (title LIKE :keyword OR description LIKE :keyword)'
            );
            $stmt->execute([
                ':user_id' => $this->userId,
                ':keyword' => "%{$keyword}%",
            ]);

            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            throw new AppException(
                message: "Error counting search results: {$e->getMessage()}",
                code: AppException::ERROR_DATABASE,
                previous: $e,
            );
        }
    }

    /**
     * Guarda una nueva tarea en la base de datos asociada al usuario actual.
     * Saves a new task to the database associated with the current user.
     *
     * @param Task $task Tarea a guardar (sin id asignado) / Task to save (without assigned id)
     * @return Task Nueva instancia con el id generado por la BD / New instance with the database-generated id
     * @throws AppException Si ocurre un error de base de datos / If a database error occurs
     */
    public function save(Task $task): Task
    {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO tasks (user_id, title, description, priority, status, due_date, recurrence)
                 VALUES (:user_id, :title, :description, :priority, :status, :due_date, :recurrence)'
            );

            $stmt->execute([
                ':user_id' => $this->userId,
                ':title' => $task->title,
                ':description' => $task->description,
                ':priority' => $task->priority->value,
                ':status' => $task->status->value,
                ':due_date' => $task->dueDate,
                ':recurrence' => $task->recurrence->value,
            ]);

            // Get the generated id and return the complete task from the database
            $newId = (int) $this->pdo->lastInsertId();

            return $this->findById($newId);
        } catch (PDOException $e) {
            throw new AppException(
                message: "Error saving task: {$e->getMessage()}",
                code: AppException::ERROR_DATABASE,
                previous: $e,
            );
        }
    }

    /**
     * Actualiza campos de una tarea existente del usuario actual.
     * Updates fields of an existing task belonging to the current user.
     *
     * @param int $id Identificador de la tarea a actualizar / Identifier of the task to update
     * @param array<string, mixed> $data Campos a actualizar (nombre_columna => valor) / Fields to update (column_name => value)
     * @return Task Tarea actualizada / Updated task
     * @throws NotFoundException Si la tarea no existe o no pertenece al usuario / If the task does not exist or does not belong to the user
     * @throws AppException Si ocurre un error de base de datos / If a database error occurs
     */
    public function update(int $id, array $data): Task
    {
        // Verify that the task exists and belongs to the user
        $this->findById($id);

        // Validate column names against whitelist to prevent SQL injection
        $invalidColumns = array_diff(array_keys($data), self::ALLOWED_UPDATE_COLUMNS);
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

            $sql = 'UPDATE tasks SET ' . implode(', ', $sets) . ' WHERE id = :id AND user_id = :user_id';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return $this->findById($id);
        } catch (PDOException $e) {
            throw new AppException(
                message: "Error updating task #{$id}: {$e->getMessage()}",
                code: AppException::ERROR_DATABASE,
                previous: $e,
            );
        }
    }

    /**
     * Marca una tarea como completada y registra la fecha de completado.
     * Marks a task as completed and records the completion date.
     *
     * @param int $id Identificador de la tarea a completar / Identifier of the task to complete
     * @return Task Tarea actualizada con estado completada / Updated task with completed status
     * @throws NotFoundException Si la tarea no existe o no pertenece al usuario / If the task does not exist or does not belong to the user
     * @throws AppException Si ocurre un error de base de datos / If a database error occurs
     */
    public function complete(int $id): Task
    {
        // Verify that the task exists and belongs to the user
        $this->findById($id);

        try {
            $stmt = $this->pdo->prepare(
                'UPDATE tasks
                 SET status = :status, completed_at = CURRENT_TIMESTAMP
                 WHERE id = :id AND user_id = :user_id'
            );

            $stmt->execute([
                ':status' => Status::Completed->value,
                ':id' => $id,
                ':user_id' => $this->userId,
            ]);

            return $this->findById($id);
        } catch (PDOException $e) {
            throw new AppException(
                message: "Error completing task #{$id}: {$e->getMessage()}",
                code: AppException::ERROR_DATABASE,
                previous: $e,
            );
        }
    }

    /**
     * Elimina una tarea del usuario de la base de datos de forma permanente.
     * Permanently deletes a user task from the database.
     *
     * @param int $id Identificador de la tarea a eliminar / Identifier of the task to delete
     * @return bool true si se elimin\u00f3 correctamente / true if successfully deleted
     * @throws NotFoundException Si la tarea no existe o no pertenece al usuario / If the task does not exist or does not belong to the user
     * @throws AppException Si ocurre un error de base de datos / If a database error occurs
     */
    public function delete(int $id): bool
    {
        // Verify that the task exists and belongs to the user
        $this->findById($id);

        try {
            $stmt = $this->pdo->prepare(
                'DELETE FROM tasks WHERE id = :id AND user_id = :user_id'
            );
            $stmt->execute([
                ':id' => $id,
                ':user_id' => $this->userId,
            ]);

            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            throw new AppException(
                message: "Error deleting task #{$id}: {$e->getMessage()}",
                code: AppException::ERROR_DATABASE,
                previous: $e,
            );
        }
    }

    /**
     * Completa múltiples tareas en una sola transacción.
     * Completes multiple tasks in a single transaction.
     *
     * Solo completa tareas pendientes del usuario actual. Las tareas
     * que no existen, ya están completadas, o pertenecen a otro usuario
     * se cuentan como 'skipped' sin lanzar error.
     *
     * Only completes pending tasks belonging to the current user. Tasks
     * that do not exist, are already completed, or belong to another user
     * are counted as 'skipped' without throwing an error.
     *
     * @param int[] $ids IDs de las tareas a completar / IDs of the tasks to complete
     * @return array{affected: int, skipped: int[]} Resultado con tareas afectadas e IDs omitidos / Result with affected count and skipped IDs
     * @throws AppException Si ocurre un error de base de datos / If a database error occurs
     */
    public function bulkComplete(array $ids): array
    {
        if ($ids === []) {
            return ['affected' => 0, 'skipped' => []];
        }

        try {
            $this->pdo->beginTransaction();
            $affected = 0;
            $skipped = [];

            $stmt = $this->pdo->prepare(
                'UPDATE tasks
                 SET status = :completed, completed_at = CURRENT_TIMESTAMP
                 WHERE id = :id AND user_id = :user_id AND status = :pending'
            );

            foreach ($ids as $id) {
                $stmt->execute([
                    ':completed' => Status::Completed->value,
                    ':id' => $id,
                    ':user_id' => $this->userId,
                    ':pending' => Status::Pending->value,
                ]);

                if ($stmt->rowCount() > 0) {
                    $affected++;
                } else {
                    $skipped[] = $id;
                }
            }

            $this->pdo->commit();

            return ['affected' => $affected, 'skipped' => $skipped];
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw new AppException(
                message: "Error completing tasks in bulk: {$e->getMessage()}",
                code: AppException::ERROR_DATABASE,
                previous: $e,
            );
        }
    }

    /**
     * Elimina múltiples tareas en una sola transacción.
     * Deletes multiple tasks in a single transaction.
     *
     * Solo elimina tareas del usuario actual. Los IDs que no existen
     * o pertenecen a otro usuario simplemente no generan filas afectadas.
     *
     * Only deletes tasks belonging to the current user. IDs that do not
     * exist or belong to another user simply yield no affected rows.
     *
     * @param int[] $ids IDs de las tareas a eliminar / IDs of the tasks to delete
     * @return array{affected: int} Resultado con número de tareas eliminadas / Result with number of deleted tasks
     * @throws AppException Si ocurre un error de base de datos / If a database error occurs
     */
    public function bulkDelete(array $ids): array
    {
        if ($ids === []) {
            return ['affected' => 0];
        }

        try {
            $this->pdo->beginTransaction();

            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql = "DELETE FROM tasks WHERE user_id = ? AND id IN ({$placeholders})";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$this->userId, ...$ids]);
            $affected = $stmt->rowCount();

            $this->pdo->commit();

            return ['affected' => $affected];
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw new AppException(
                message: "Error deleting tasks in bulk: {$e->getMessage()}",
                code: AppException::ERROR_DATABASE,
                previous: $e,
            );
        }
    }

    /**
     * Obtiene estad\u00edsticas generales de las tareas del usuario.
     * Retrieves general statistics for the user's tasks.
     *
     * Retorna un array asociativo con:
     * - total: n\u00famero total de tareas
     * - completed: tareas completadas
     * - pending: tareas pendientes
     * - by_priority: desglose por nivel de prioridad
     *
     * Returns an associative array with:
     * - total: total number of tasks
     * - completed: completed tasks
     * - pending: pending tasks
     * - by_priority: breakdown by priority level
     *
     * @return array<string, int|array<string, int>> Estad\u00edsticas calculadas / Computed statistics
     * @throws AppException Si ocurre un error de base de datos / If a database error occurs
     */
    public function getStatistics(): array
    {
        try {
            // Total count
            $stmtTotal = $this->pdo->prepare(
                'SELECT COUNT(*) FROM tasks WHERE user_id = :user_id'
            );
            $stmtTotal->execute([':user_id' => $this->userId]);
            $total = (int) $stmtTotal->fetchColumn();

            // Count by status
            $stmtStatus = $this->pdo->prepare(
                'SELECT status, COUNT(*) as count FROM tasks WHERE user_id = :user_id GROUP BY status'
            );
            $stmtStatus->execute([':user_id' => $this->userId]);
            $byStatus = [];
            foreach ($stmtStatus->fetchAll() as $row) {
                $byStatus[$row['status']] = (int) $row['count'];
            }

            // Count by priority
            $stmtPriority = $this->pdo->prepare(
                'SELECT priority, COUNT(*) as count FROM tasks WHERE user_id = :user_id GROUP BY priority'
            );
            $stmtPriority->execute([':user_id' => $this->userId]);
            $byPriority = [];
            foreach ($stmtPriority->fetchAll() as $row) {
                $byPriority[$row['priority']] = (int) $row['count'];
            }

            return [
                'total' => $total,
                'completed' => $byStatus['completed'] ?? 0,
                'pending' => $byStatus['pending'] ?? 0,
                'by_priority' => [
                    'high' => $byPriority['high'] ?? 0,
                    'medium' => $byPriority['medium'] ?? 0,
                    'low' => $byPriority['low'] ?? 0,
                ],
            ];
        } catch (PDOException $e) {
            throw new AppException(
                message: "Error fetching statistics: {$e->getMessage()}",
                code: AppException::ERROR_DATABASE,
                previous: $e,
            );
        }
    }
}
