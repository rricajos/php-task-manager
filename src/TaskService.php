<?php

declare(strict_types=1);

namespace MiniProject;

/**
 * Servicio de lógica de negocio para tareas.
 * Business logic service for tasks.
 *
 * Capa intermedia entre la interfaz de usuario y el repositorio.
 * Se encarga de validar datos de entrada, delegar operaciones
 * al repositorio y formatear la salida.
 *
 * Intermediate layer between the user interface and the repository.
 * Handles input validation, delegates operations to the repository,
 * and formats the output.
 *
 * Patrón: Inyección de Dependencias (recibe el repositorio en el constructor)
 * Características PHP 8: constructor promotion, readonly, named arguments,
 * union types, match
 *
 * Pattern: Dependency Injection (receives the repository in the constructor)
 * PHP 8 features: constructor promotion, readonly, named arguments,
 * union types, match
 */
class TaskService implements TaskServiceInterface
{
    /**
     * Inicializa el servicio con inyección de dependencias.
     * Initializes the service with dependency injection.
     *
     * @param TaskRepository $repository Repositorio de acceso a datos / Data access repository
     */
    public function __construct(
        private readonly TaskRepository $repository,
    ) {
    }

    /**
     * Crea y guarda una nueva tarea validando los datos de entrada.
     * Creates and saves a new task after validating the input data.
     *
     * @param string $title Título de la tarea (no puede estar vacío) / Task title (cannot be empty)
     * @param string $description Descripción opcional de la tarea / Optional task description
     * @param string $priority Prioridad como string: 'high', 'medium' o 'low' / Priority as string: 'high', 'medium' or 'low'
     * @param string|null $dueDate Fecha de vencimiento en formato Y-m-d (opcional) / Due date in Y-m-d format (optional)
     * @return Task La tarea creada con su id asignado / The created task with its assigned id
     * @throws ValidationException Si los datos no son válidos / If the data is not valid
     */
    public function createTask(
        string $title,
        string $description,
        string $priority,
        ?string $dueDate = null,
    ): Task {
        // Validate title (required, non-empty, max 100 chars)
        $title = $this->validateTitle($title);

        // Validate and convert priority from string to enum
        $priorityEnum = $this->validatePriority($priority);

        // Sanitize description
        $description = trim($description);

        // Validate due date if provided
        $validatedDueDate = $this->validateDueDate($dueDate);

        // Create the Task entity with named arguments
        $task = new Task(
            id: null,
            title: $title,
            description: $description,
            priority: $priorityEnum,
            status: Status::Pending,
            dueDate: $validatedDueDate,
        );

        // Delegate to the repository to persist
        return $this->repository->save($task);
    }

    /**
     * Obtiene una tarea por su ID.
     * Gets a task by its ID.
     *
     * @param int|string $id Identificador de la tarea / Task identifier
     * @return Task La tarea encontrada / The found task
     * @throws ValidationException Si el ID no es válido / If the ID is not valid
     * @throws NotFoundException Si la tarea no existe / If the task does not exist
     */
    public function getTask(int|string $id): Task
    {
        $validatedId = $this->validateId($id);

        return $this->repository->findById($validatedId);
    }

    /**
     * Actualiza campos de una tarea existente.
     * Solo actualiza los campos que se proporcionan (no null).
     *
     * Updates fields of an existing task.
     * Only updates the fields that are provided (not null).
     *
     * @param int|string $id Identificador de la tarea / Task identifier
     * @param string|null $title Nuevo título (null para no cambiar) / New title (null to keep unchanged)
     * @param string|null $description Nueva descripción (null para no cambiar) / New description (null to keep unchanged)
     * @param string|null $priority Nueva prioridad (null para no cambiar) / New priority (null to keep unchanged)
     * @param string|null $dueDate Nueva fecha de vencimiento (null para no cambiar) / New due date (null to keep unchanged)
     * @return Task Tarea actualizada / Updated task
     * @throws ValidationException Si los datos no son válidos / If the data is not valid
     * @throws NotFoundException Si la tarea no existe / If the task does not exist
     */
    public function updateTask(
        int|string $id,
        ?string $title = null,
        ?string $description = null,
        ?string $priority = null,
        ?string $dueDate = null,
    ): Task {
        $validatedId = $this->validateId($id);

        $data = [];

        // Validate and add title if provided
        if ($title !== null) {
            $data['title'] = $this->validateTitle($title);
        }

        // Add description if provided
        if ($description !== null) {
            $data['description'] = trim($description);
        }

        // Validate and add priority if provided
        if ($priority !== null) {
            $priorityEnum = $this->validatePriority($priority);
            $data['priority'] = $priorityEnum->value;
        }

        // Validate and add due date if provided
        if ($dueDate !== null) {
            // Allow empty string to remove the due date
            if ($dueDate === '') {
                $data['due_date'] = null;
            } else {
                $data['due_date'] = $this->validateDueDate($dueDate);
            }
        }

        if (empty($data)) {
            // No changes, return the task unmodified
            return $this->repository->findById($validatedId);
        }

        return $this->repository->update(id: $validatedId, data: $data);
    }

    /**
     * Lista tareas con filtro opcional por estado, paginación y ordenamiento.
     * Cuando se llama sin parámetros de paginación (page=0), retorna
     * todas las tareas como array simple (compatibilidad con CLI).
     * Cuando se especifica page >= 1, retorna un array con metadatos
     * de paginación para la API.
     *
     * Lists tasks with optional status filter, pagination and sorting.
     * When called without pagination parameters (page=0), returns
     * all tasks as a simple array (CLI compatibility).
     * When page >= 1 is specified, returns an array with pagination
     * metadata for the API.
     *
     * @param string $filter Filtro de estado / Status filter: 'pending', 'completed' or 'all'
     * @param int $page Número de página (0 = sin paginación, >=1 = paginado) / Page number (0 = no pagination, >=1 = paginated)
     * @param int $perPage Resultados por página (1-100) / Results per page (1-100)
     * @param string $sortBy Campo de ordenamiento / Sort field
     * @param string $sortDir Dirección: 'ASC' o 'DESC' / Direction: 'ASC' or 'DESC'
     * @param string|null $priority Filtro adicional de prioridad / Additional priority filter
     * @return Task[]|array{tasks: Task[], total: int, page: int, per_page: int, total_pages: int} Lista de tareas o respuesta paginada / Task list or paginated response
     * @throws ValidationException Si el filtro no es válido / If the filter is not valid
     */
    public function listTasks(
        string $filter = 'all',
        int $page = 0,
        int $perPage = 20,
        string $sortBy = 'created_at',
        string $sortDir = 'DESC',
        ?string $priority = null,
    ): array {
        $filter = strtolower(trim($filter));

        // Validate status filter
        $statusValue = match ($filter) {
            'all' => null,
            'pending' => 'pending',
            'completed' => 'completed',
            default => throw new ValidationException(
                message: "Invalid status filter: '{$filter}'. Use: all, pending or completed",
                code: ValidationException::ERROR_INVALID_STATUS,
                field: 'status',
            ),
        };

        // Non-paginated mode (CLI compatibility)
        if ($page === 0) {
            if ($statusValue === null && $priority === null) {
                return $this->repository->findAll();
            }
            if ($statusValue !== null && $priority === null) {
                return $this->repository->findByStatus(Status::from($statusValue));
            }

            // If there is a priority filter, use unlimited pagination
            return $this->repository->findAllPaginated(
                limit: PHP_INT_MAX,
                offset: 0,
                sortBy: $sortBy,
                sortDir: $sortDir,
                priority: $priority,
                status: $statusValue,
            );
        }

        // Paginated mode (API)
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $tasks = $this->repository->findAllPaginated(
            limit: $perPage,
            offset: $offset,
            sortBy: $sortBy,
            sortDir: $sortDir,
            priority: $priority,
            status: $statusValue,
        );

        $total = $this->repository->countFiltered(
            priority: $priority,
            status: $statusValue,
        );

        $totalPages = (int) ceil($total / $perPage);

        return [
            'tasks' => $tasks,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
        ];
    }

    /**
     * Marca una tarea como completada por su ID.
     * Marks a task as completed by its ID.
     *
     * @param int|string $id Identificador de la tarea (se valida como entero positivo) / Task identifier (validated as positive integer)
     * @return Task Tarea actualizada con estado completada / Updated task with completed status
     * @throws ValidationException Si el ID no es válido / If the ID is not valid
     * @throws NotFoundException Si la tarea no existe / If the task does not exist
     */
    public function completeTask(int|string $id): Task
    {
        $validatedId = $this->validateId($id);

        // Verify the task is not already completed
        $task = $this->repository->findById($validatedId);
        if ($task->status === Status::Completed) {
            throw new ValidationException(
                message: "Task #{$validatedId} is already completed",
                code: ValidationException::ERROR_INVALID_STATUS,
                field: 'status',
            );
        }

        return $this->repository->complete($validatedId);
    }

    /**
     * Elimina una tarea por su ID.
     * Deletes a task by its ID.
     *
     * @param int|string $id Identificador de la tarea / Task identifier
     * @return bool true si se eliminó correctamente / true if deleted successfully
     * @throws ValidationException Si el ID no es válido / If the ID is not valid
     * @throws NotFoundException Si la tarea no existe / If the task does not exist
     */
    public function deleteTask(int|string $id): bool
    {
        $validatedId = $this->validateId($id);

        return $this->repository->delete($validatedId);
    }

    /**
     * Busca tareas por palabra clave en título y descripción.
     * Cuando se llama sin paginación (page=0), retorna un array simple
     * de tareas (compatibilidad con CLI). Con page >= 1, retorna
     * respuesta paginada para la API.
     *
     * Searches tasks by keyword in title and description.
     * When called without pagination (page=0), returns a simple array
     * of tasks (CLI compatibility). With page >= 1, returns a paginated
     * response for the API.
     *
     * @param string $keyword Palabra clave de búsqueda / Search keyword
     * @param int $page Número de página (0 = sin paginación, >=1 = paginado) / Page number (0 = no pagination, >=1 = paginated)
     * @param int $perPage Resultados por página (1-100) / Results per page (1-100)
     * @return Task[]|array{tasks: Task[], total: int, page: int, per_page: int, total_pages: int} Lista de tareas o respuesta paginada / Task list or paginated response
     * @throws ValidationException Si la palabra clave está vacía / If the keyword is empty
     */
    public function searchTasks(string $keyword, int $page = 0, int $perPage = 20): array
    {
        $keyword = trim($keyword);

        if ($keyword === '') {
            throw new ValidationException(
                message: 'Search keyword cannot be empty',
                code: ValidationException::ERROR_EMPTY_FIELD,
                field: 'keyword',
            );
        }

        // Non-paginated mode (CLI compatibility)
        if ($page === 0) {
            return $this->repository->search($keyword);
        }

        // Paginated mode (API)
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $tasks = $this->repository->search(
            keyword: $keyword,
            limit: $perPage,
            offset: $offset,
        );

        $total = $this->repository->countSearch($keyword);
        $totalPages = (int) ceil($total / $perPage);

        return [
            'tasks' => $tasks,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
        ];
    }

    /**
     * Obtiene estadísticas formateadas para mostrar en la terminal.
     * Gets formatted statistics for terminal display.
     *
     * @return array<string, int|array<string, int>> Datos estadísticos / Statistical data
     */
    public function getStatistics(): array
    {
        return $this->repository->getStatistics();
    }

    /**
     * Obtiene todas las tareas (sin filtro) para exportación.
     * Gets all tasks (unfiltered) for export.
     *
     * @return Task[] Todas las tareas en la base de datos / All tasks in the database
     */
    public function getAllForExport(): array
    {
        return $this->repository->findAll();
    }

    /**
     * Formatea las estadísticas como texto con colores ANSI para la terminal.
     * Formats the statistics as ANSI-colored text for the terminal.
     *
     * @param array<string, mixed> $stats Datos estadísticos del repositorio / Statistical data from the repository
     * @return string Texto formateado con colores / Color-formatted text
     */
    public function formatStatistics(array $stats): string
    {
        $percentage = $stats['total'] > 0
            ? round(($stats['completed'] / $stats['total']) * 100, 1)
            : 0;

        // Build visual progress bar
        $barWidth = 30;
        $filled = $stats['total'] > 0
            ? (int) round(($stats['completed'] / $stats['total']) * $barWidth)
            : 0;
        $empty = $barWidth - $filled;
        $bar = "\033[42m" . str_repeat(' ', $filled) . "\033[0m"
             . "\033[47m" . str_repeat(' ', $empty) . "\033[0m";

        $lines = [];
        $lines[] = '';
        $lines[] = "\033[1;35m========== ESTADISTICAS ==========\033[0m";
        $lines[] = '';
        $lines[] = "  Total de tareas:    \033[1m{$stats['total']}\033[0m";
        $lines[] = "  Completadas:        \033[32m{$stats['completed']}\033[0m";
        $lines[] = "  Pendientes:         \033[33m{$stats['pending']}\033[0m";
        $lines[] = '';
        $lines[] = "  Progreso: [{$bar}] {$percentage}%";
        $lines[] = '';
        $lines[] = "\033[1;35m--- Por prioridad ---\033[0m";
        $lines[] = "  \033[31mAlta:   {$stats['by_priority']['high']}\033[0m";
        $lines[] = "  \033[33mMedia:  {$stats['by_priority']['medium']}\033[0m";
        $lines[] = "  \033[32mBaja:   {$stats['by_priority']['low']}\033[0m";
        $lines[] = '';
        $lines[] = "\033[1;35m==================================\033[0m";

        return implode(PHP_EOL, $lines);
    }

    // ---------------------------------------------------------------
    //  Private validation methods / Métodos privados de validación
    // ---------------------------------------------------------------

    /**
     * Valida y normaliza el título de una tarea.
     * Validates and normalizes a task title.
     *
     * @param string $title Título a validar / Title to validate
     * @return string Título validado y normalizado / Validated and normalized title
     * @throws ValidationException Si el título está vacío o excede 100 caracteres /
     *                              If the title is empty or exceeds 100 characters
     */
    private function validateTitle(string $title): string
    {
        $title = trim($title);

        if ($title === '') {
            throw new ValidationException(
                message: 'Task title cannot be empty',
                code: ValidationException::ERROR_EMPTY_FIELD,
                field: 'title',
            );
        }

        if (mb_strlen($title) > 100) {
            throw new ValidationException(
                message: 'Title cannot exceed 100 characters',
                code: ValidationException::ERROR_INVALID_LENGTH,
                field: 'title',
            );
        }

        return $title;
    }

    /**
     * Valida y convierte un string de prioridad al enum Priority.
     * Validates and converts a priority string to the Priority enum.
     *
     * @param string $priority Valor de prioridad como texto / Priority value as text
     * @return Priority Enum de prioridad validado / Validated priority enum
     * @throws ValidationException Si el valor no es una prioridad válida / If the value is not a valid priority
     */
    private function validatePriority(string $priority): Priority
    {
        $priority = strtolower(trim($priority));

        $enum = Priority::tryFrom($priority);

        if ($enum === null) {
            $validValues = implode(', ', array_map(
                fn (Priority $p): string => $p->value,
                Priority::cases(),
            ));

            throw new ValidationException(
                message: "Invalid priority: '{$priority}'. Valid values: {$validValues}",
                code: ValidationException::ERROR_INVALID_PRIORITY,
                field: 'priority',
            );
        }

        return $enum;
    }

    /**
     * Valida que un ID sea un entero positivo.
     * Validates that an ID is a positive integer.
     *
     * @param int|string $id Valor a validar como ID / Value to validate as ID
     * @return int ID validado como entero positivo / Validated ID as positive integer
     * @throws ValidationException Si el ID no es un entero positivo / If the ID is not a positive integer
     */
    private function validateId(int|string $id): int
    {
        // If string, attempt to convert to integer
        if (is_string($id)) {
            $id = trim($id);
            if (!ctype_digit($id)) {
                throw new ValidationException(
                    message: "ID must be a positive integer, received: '{$id}'",
                    code: ValidationException::ERROR_INVALID_ID,
                    field: 'id',
                );
            }
            $id = (int) $id;
        }

        if ($id <= 0) {
            throw new ValidationException(
                message: "ID must be greater than zero, received: {$id}",
                code: ValidationException::ERROR_INVALID_ID,
                field: 'id',
            );
        }

        return $id;
    }

    /**
     * Valida el formato de la fecha de vencimiento (Y-m-d).
     * Validates the due date format (Y-m-d).
     *
     * @param string|null $dueDate Fecha a validar / Date to validate
     * @return string|null Fecha validada o null si no se proporcionó / Validated date or null if not provided
     * @throws ValidationException Si el formato no es válido / If the format is not valid
     */
    private function validateDueDate(?string $dueDate): ?string
    {
        if ($dueDate === null || $dueDate === '') {
            return null;
        }

        $dueDate = trim($dueDate);

        // Validate Y-m-d format
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $dueDate);

        if ($dt === false || $dt->format('Y-m-d') !== $dueDate) {
            throw new ValidationException(
                message: "Due date must be in YYYY-MM-DD format, received: '{$dueDate}'",
                code: ValidationException::ERROR_INVALID_FORMAT,
                field: 'due_date',
            );
        }

        return $dueDate;
    }
}
