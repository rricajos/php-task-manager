<?php

declare(strict_types=1);

namespace MiniProject;

use PDO;
use PDOException;

/**
 * Repositorio de tareas - Patron Repository.
 *
 * Encapsula toda la logica de acceso a datos para la entidad Task.
 * Usa sentencias preparadas (prepared statements) para prevenir
 * inyeccion SQL y manejo de errores adecuado.
 *
 * Todas las consultas filtran por user_id para garantizar aislamiento
 * de datos entre usuarios.
 *
 * Patron: Repository
 * Caracteristicas PHP 8: constructor promotion, readonly, named arguments, match
 */
class TaskRepository
{
    /** Conexion PDO obtenida del Singleton Database */
    private readonly PDO $pdo;

    /**
     * Inicializa el repositorio con el ID del usuario propietario.
     *
     * @param int $userId ID del usuario autenticado
     */
    public function __construct(
        private readonly int $userId,
    ) {
        $this->pdo = Database::getInstance()->getConnection();
    }

    /**
     * Obtiene todas las tareas del usuario ordenadas por fecha de creacion descendente.
     *
     * @return Task[] Lista de todas las tareas del usuario
     * @throws AppException Si ocurre un error de base de datos
     */
    public function findAll(): array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT * FROM tasks WHERE user_id = :user_id ORDER BY fecha_creacion DESC'
            );
            $stmt->execute([':user_id' => $this->userId]);
            $rows = $stmt->fetchAll();

            // Mapear cada fila a una instancia de Task usando el metodo de fabrica
            return array_map(
                callback: fn (array $row): Task => Task::fromRow($row),
                array: $rows,
            );
        } catch (PDOException $e) {
            throw new AppException(
                message: "Error al obtener tareas: {$e->getMessage()}",
                code: AppException::ERROR_DATABASE,
                previous: $e,
            );
        }
    }

    /**
     * Busca una tarea por su identificador unico (solo del usuario actual).
     *
     * @param int $id Identificador de la tarea
     * @return Task La tarea encontrada
     * @throws NotFoundException Si la tarea no existe o no pertenece al usuario
     * @throws AppException Si ocurre un error de base de datos
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
                    recurso: 'tarea',
                    identificador: $id,
                );
            }

            return Task::fromRow($row);
        } catch (NotFoundException $e) {
            // Re-lanzar NotFoundException tal cual
            throw $e;
        } catch (PDOException $e) {
            throw new AppException(
                message: "Error al buscar tarea #{$id}: {$e->getMessage()}",
                code: AppException::ERROR_DATABASE,
                previous: $e,
            );
        }
    }

    /**
     * Filtra tareas del usuario por estado (pendiente o completada).
     *
     * @param Status $estado Estado por el cual filtrar
     * @return Task[] Lista de tareas con el estado dado
     * @throws AppException Si ocurre un error de base de datos
     */
    public function findByStatus(Status $estado): array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT * FROM tasks WHERE user_id = :user_id AND estado = :estado ORDER BY fecha_creacion DESC'
            );
            $stmt->execute([
                ':user_id' => $this->userId,
                ':estado' => $estado->value,
            ]);
            $rows = $stmt->fetchAll();

            return array_map(
                callback: fn (array $row): Task => Task::fromRow($row),
                array: $rows,
            );
        } catch (PDOException $e) {
            throw new AppException(
                message: "Error al filtrar tareas por estado: {$e->getMessage()}",
                code: AppException::ERROR_DATABASE,
                previous: $e,
            );
        }
    }

    /**
     * Obtiene tareas del usuario con paginacion, ordenamiento y filtros opcionales.
     *
     * Construye una consulta dinamica con clausula WHERE para user_id y filtros
     * opcionales de prioridad y estado. Valida los campos de ordenamiento
     * contra una lista blanca para prevenir inyeccion SQL.
     *
     * @param int $limit Numero maximo de resultados por pagina
     * @param int $offset Desplazamiento desde el inicio
     * @param string $sortBy Campo de ordenamiento (validado contra whitelist)
     * @param string $sortDir Direccion de ordenamiento: 'ASC' o 'DESC'
     * @param string|null $priority Filtro opcional de prioridad (alta, media, baja)
     * @param string|null $status Filtro opcional de estado (pendiente, completada)
     * @return Task[] Lista de tareas paginadas
     * @throws AppException Si ocurre un error de base de datos
     */
    public function findAllPaginated(
        int $limit = 20,
        int $offset = 0,
        string $sortBy = 'fecha_creacion',
        string $sortDir = 'DESC',
        ?string $priority = null,
        ?string $status = null,
    ): array {
        // Validar sortBy contra whitelist para prevenir inyeccion SQL
        $allowedSortBy = ['fecha_creacion', 'titulo', 'prioridad', 'estado', 'fecha_vencimiento'];
        if (!in_array($sortBy, $allowedSortBy, strict: true)) {
            $sortBy = 'fecha_creacion';
        }

        // Validar sortDir
        $sortDir = strtoupper($sortDir);
        if (!in_array($sortDir, ['ASC', 'DESC'], strict: true)) {
            $sortDir = 'DESC';
        }

        try {
            // Construir clausula WHERE dinamica
            $where = 'WHERE user_id = :user_id';
            $params = [':user_id' => $this->userId];

            if ($priority !== null) {
                $where .= ' AND prioridad = :prioridad';
                $params[':prioridad'] = $priority;
            }

            if ($status !== null) {
                $where .= ' AND estado = :estado';
                $params[':estado'] = $status;
            }

            $sql = "SELECT * FROM tasks {$where} ORDER BY {$sortBy} {$sortDir} LIMIT :limit OFFSET :offset";
            $stmt = $this->pdo->prepare($sql);

            // Bindear parametros de paginacion como enteros
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
                message: "Error al obtener tareas paginadas: {$e->getMessage()}",
                code: AppException::ERROR_DATABASE,
                previous: $e,
            );
        }
    }

    /**
     * Cuenta el total de tareas del usuario que coinciden con los filtros.
     *
     * Usado para calcular metadatos de paginacion (total de paginas, etc).
     *
     * @param string|null $priority Filtro opcional de prioridad
     * @param string|null $status Filtro opcional de estado
     * @return int Numero total de tareas que coinciden
     * @throws AppException Si ocurre un error de base de datos
     */
    public function countFiltered(?string $priority = null, ?string $status = null): int
    {
        try {
            $where = 'WHERE user_id = :user_id';
            $params = [':user_id' => $this->userId];

            if ($priority !== null) {
                $where .= ' AND prioridad = :prioridad';
                $params[':prioridad'] = $priority;
            }

            if ($status !== null) {
                $where .= ' AND estado = :estado';
                $params[':estado'] = $status;
            }

            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM tasks {$where}");
            $stmt->execute($params);

            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            throw new AppException(
                message: "Error al contar tareas filtradas: {$e->getMessage()}",
                code: AppException::ERROR_DATABASE,
                previous: $e,
            );
        }
    }

    /**
     * Busca tareas del usuario cuyo titulo o descripcion contengan la palabra clave.
     *
     * Soporta paginacion con limit/offset para resultados grandes.
     *
     * @param string $keyword Palabra clave de busqueda
     * @param int $limit Numero maximo de resultados por pagina
     * @param int $offset Desplazamiento desde el inicio
     * @return Task[] Lista de tareas que coinciden con la busqueda
     * @throws AppException Si ocurre un error de base de datos
     */
    public function search(string $keyword, int $limit = 20, int $offset = 0): array
    {
        try {
            $sql = 'SELECT * FROM tasks
                 WHERE user_id = :user_id AND (titulo LIKE :keyword OR descripcion LIKE :keyword)
                 ORDER BY fecha_creacion DESC
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
                message: "Error al buscar tareas: {$e->getMessage()}",
                code: AppException::ERROR_DATABASE,
                previous: $e,
            );
        }
    }

    /**
     * Cuenta el total de tareas del usuario que coinciden con la busqueda.
     *
     * Usado para calcular metadatos de paginacion en busquedas.
     *
     * @param string $keyword Palabra clave de busqueda
     * @return int Numero total de tareas que coinciden
     * @throws AppException Si ocurre un error de base de datos
     */
    public function countSearch(string $keyword): int
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT COUNT(*) FROM tasks
                 WHERE user_id = :user_id AND (titulo LIKE :keyword OR descripcion LIKE :keyword)'
            );
            $stmt->execute([
                ':user_id' => $this->userId,
                ':keyword' => "%{$keyword}%",
            ]);

            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            throw new AppException(
                message: "Error al contar resultados de busqueda: {$e->getMessage()}",
                code: AppException::ERROR_DATABASE,
                previous: $e,
            );
        }
    }

    /**
     * Guarda una nueva tarea en la base de datos asociada al usuario actual.
     *
     * @param Task $task Tarea a guardar (sin id asignado)
     * @return Task Nueva instancia con el id generado por la BD
     * @throws AppException Si ocurre un error de base de datos
     */
    public function save(Task $task): Task
    {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO tasks (user_id, titulo, descripcion, prioridad, estado, fecha_vencimiento)
                 VALUES (:user_id, :titulo, :descripcion, :prioridad, :estado, :fecha_vencimiento)'
            );

            $stmt->execute([
                ':user_id' => $this->userId,
                ':titulo' => $task->titulo,
                ':descripcion' => $task->descripcion,
                ':prioridad' => $task->prioridad->value,
                ':estado' => $task->estado->value,
                ':fecha_vencimiento' => $task->fechaVencimiento,
            ]);

            // Obtener el id generado y retornar la tarea completa desde la BD
            $nuevoId = (int) $this->pdo->lastInsertId();

            return $this->findById($nuevoId);
        } catch (PDOException $e) {
            throw new AppException(
                message: "Error al guardar tarea: {$e->getMessage()}",
                code: AppException::ERROR_DATABASE,
                previous: $e,
            );
        }
    }

    /**
     * Actualiza campos de una tarea existente del usuario actual.
     *
     * @param int $id Identificador de la tarea a actualizar
     * @param array<string, mixed> $data Campos a actualizar (nombre_columna => valor)
     * @return Task Tarea actualizada
     * @throws NotFoundException Si la tarea no existe o no pertenece al usuario
     * @throws AppException Si ocurre un error de base de datos
     */
    public function update(int $id, array $data): Task
    {
        // Verificar que la tarea existe y pertenece al usuario
        $this->findById($id);

        try {
            $sets = [];
            $params = [':id' => $id, ':user_id' => $this->userId];

            foreach ($data as $col => $val) {
                $sets[] = "{$col} = :{$col}";
                $params[":{$col}"] = $val;
            }

            $sql = 'UPDATE tasks SET ' . implode(', ', $sets) . ' WHERE id = :id AND user_id = :user_id';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return $this->findById($id);
        } catch (NotFoundException $e) {
            throw $e;
        } catch (PDOException $e) {
            throw new AppException(
                message: "Error al actualizar tarea #{$id}: {$e->getMessage()}",
                code: AppException::ERROR_DATABASE,
                previous: $e,
            );
        }
    }

    /**
     * Marca una tarea como completada y registra la fecha de completado.
     *
     * @param int $id Identificador de la tarea a completar
     * @return Task Tarea actualizada con estado completada
     * @throws NotFoundException Si la tarea no existe o no pertenece al usuario
     * @throws AppException Si ocurre un error de base de datos
     */
    public function complete(int $id): Task
    {
        // Verificar que la tarea existe y pertenece al usuario
        $this->findById($id);

        try {
            $stmt = $this->pdo->prepare(
                'UPDATE tasks
                 SET estado = :estado, fecha_completada = CURRENT_TIMESTAMP
                 WHERE id = :id AND user_id = :user_id'
            );

            $stmt->execute([
                ':estado' => Status::Completada->value,
                ':id' => $id,
                ':user_id' => $this->userId,
            ]);

            return $this->findById($id);
        } catch (NotFoundException $e) {
            throw $e;
        } catch (PDOException $e) {
            throw new AppException(
                message: "Error al completar tarea #{$id}: {$e->getMessage()}",
                code: AppException::ERROR_DATABASE,
                previous: $e,
            );
        }
    }

    /**
     * Elimina una tarea del usuario de la base de datos de forma permanente.
     *
     * @param int $id Identificador de la tarea a eliminar
     * @return bool true si se elimino correctamente
     * @throws NotFoundException Si la tarea no existe o no pertenece al usuario
     * @throws AppException Si ocurre un error de base de datos
     */
    public function delete(int $id): bool
    {
        // Verificar que la tarea existe y pertenece al usuario
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
        } catch (NotFoundException $e) {
            throw $e;
        } catch (PDOException $e) {
            throw new AppException(
                message: "Error al eliminar tarea #{$id}: {$e->getMessage()}",
                code: AppException::ERROR_DATABASE,
                previous: $e,
            );
        }
    }

    /**
     * Obtiene estadisticas generales de las tareas del usuario.
     *
     * Retorna un array asociativo con:
     * - total: numero total de tareas
     * - completadas: tareas completadas
     * - pendientes: tareas pendientes
     * - por_prioridad: desglose por nivel de prioridad
     *
     * @return array<string, int|array<string, int>> Estadisticas calculadas
     * @throws AppException Si ocurre un error de base de datos
     */
    public function getStatistics(): array
    {
        try {
            // Conteo total
            $stmtTotal = $this->pdo->prepare(
                'SELECT COUNT(*) FROM tasks WHERE user_id = :user_id'
            );
            $stmtTotal->execute([':user_id' => $this->userId]);
            $total = (int) $stmtTotal->fetchColumn();

            // Conteo por estado
            $stmtEstado = $this->pdo->prepare(
                'SELECT estado, COUNT(*) as cantidad FROM tasks WHERE user_id = :user_id GROUP BY estado'
            );
            $stmtEstado->execute([':user_id' => $this->userId]);
            $porEstado = [];
            foreach ($stmtEstado->fetchAll() as $row) {
                $porEstado[$row['estado']] = (int) $row['cantidad'];
            }

            // Conteo por prioridad
            $stmtPrioridad = $this->pdo->prepare(
                'SELECT prioridad, COUNT(*) as cantidad FROM tasks WHERE user_id = :user_id GROUP BY prioridad'
            );
            $stmtPrioridad->execute([':user_id' => $this->userId]);
            $porPrioridad = [];
            foreach ($stmtPrioridad->fetchAll() as $row) {
                $porPrioridad[$row['prioridad']] = (int) $row['cantidad'];
            }

            return [
                'total' => $total,
                'completadas' => $porEstado['completada'] ?? 0,
                'pendientes' => $porEstado['pendiente'] ?? 0,
                'por_prioridad' => [
                    'alta' => $porPrioridad['alta'] ?? 0,
                    'media' => $porPrioridad['media'] ?? 0,
                    'baja' => $porPrioridad['baja'] ?? 0,
                ],
            ];
        } catch (PDOException $e) {
            throw new AppException(
                message: "Error al obtener estadisticas: {$e->getMessage()}",
                code: AppException::ERROR_DATABASE,
                previous: $e,
            );
        }
    }
}
