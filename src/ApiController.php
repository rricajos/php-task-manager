<?php

declare(strict_types=1);

namespace MiniProject;

/**
 * Controlador de la API REST para el gestor de tareas.
 * REST API controller for the task manager.
 *
 * Recibe datos de peticiones HTTP, delega al TaskService existente
 * y devuelve respuestas JSON estructuradas. Actúa como puente entre
 * el Router HTTP y la capa de lógica de negocio.
 *
 * Receives data from HTTP requests, delegates to the existing TaskService,
 * and returns structured JSON responses. Acts as a bridge between
 * the HTTP Router and the business logic layer.
 *
 * Reutiliza completamente: TaskService, ExportService, AuthService,
 * y todas las excepciones personalizadas existentes.
 *
 * Fully reuses: TaskService, ExportService, AuthService,
 * and all existing custom exceptions.
 *
 * Soporta aislamiento de datos por usuario: tras la autenticación,
 * se llama a setUserId() para crear un TaskService vinculado al usuario.
 *
 * Supports per-user data isolation: after authentication,
 * setUserId() is called to create a TaskService bound to the user.
 *
 * Características PHP 8: constructor promotion, readonly, named arguments,
 * match, union types
 *
 * PHP 8 features: constructor promotion, readonly, named arguments,
 * match, union types
 */
class ApiController
{
    /**
     * Servicio de tareas vinculado al usuario autenticado.
     * Task service bound to the authenticated user.
     */
    private ?TaskService $userTaskService = null;

    /**
     * Repositorio de etiquetas vinculado al usuario autenticado.
     * Tag repository bound to the authenticated user.
     */
    private ?TagRepository $tagRepository = null;

    /**
     * Inicializa el controlador con los servicios necesarios.
     * Initializes the controller with the required services.
     *
     * @param TaskService $taskService Servicio de lógica de negocio de tareas (por defecto) /
     *                                 Business logic service for tasks (default)
     * @param ExportService $exportService Servicio de exportación (JSON/CSV) /
     *                                     Export service (JSON/CSV)
     * @param AuthService $authService Servicio de autenticación y JWT /
     *                                  Authentication and JWT service
     */
    public function __construct(
        private readonly TaskService $taskService,
        private readonly ExportService $exportService,
        private readonly AuthService $authService,
    ) {
    }

    /**
     * Establece el ID del usuario autenticado y crea servicios vinculados.
     * Sets the authenticated user's ID and creates bound services.
     *
     * Debe llamarse después de requireAuth() en cada ruta protegida.
     * Must be called after requireAuth() on each protected route.
     *
     * @param int $userId ID del usuario autenticado / Authenticated user ID
     */
    public function setUserId(int $userId): void
    {
        $repository = new TaskRepository(userId: $userId);
        $this->userTaskService = new TaskService(repository: $repository);
        $this->tagRepository = new TagRepository(userId: $userId);
    }

    /**
     * Obtiene el servicio de tareas del usuario autenticado.
     * Gets the authenticated user's task service.
     *
     * Si se ha llamado a setUserId(), retorna el servicio vinculado al usuario.
     * En caso contrario, retorna el servicio por defecto.
     *
     * If setUserId() has been called, returns the user-bound service.
     * Otherwise, returns the default service.
     *
     * @return TaskService Servicio de tareas activo / Active task service
     */
    private function getTaskService(): TaskService
    {
        return $this->userTaskService ?? $this->taskService;
    }

    /**
     * Obtiene el repositorio de etiquetas del usuario autenticado.
     * Gets the authenticated user's tag repository.
     *
     * @return TagRepository Repositorio de etiquetas / Tag repository
     */
    private function getTagRepository(): TagRepository
    {
        if ($this->tagRepository === null) {
            throw new AppException('TagRepository not initialized. Call setUserId() first.');
        }

        return $this->tagRepository;
    }

    /**
     * Enriquece un array de tareas con sus etiquetas asociadas.
     * Enriches a task array with associated tags.
     *
     * Realiza una sola consulta para obtener las etiquetas de todas
     * las tareas, evitando el problema N+1.
     *
     * Performs a single query to get tags for all tasks,
     * avoiding the N+1 problem.
     *
     * @param array<int, array<string, mixed>> $tasksArray Tareas en formato array / Tasks in array format
     * @return array<int, array<string, mixed>> Tareas enriquecidas con etiquetas / Tasks enriched with tags
     */
    private function enrichTasksWithTags(array $tasksArray): array
    {
        $taskIds = array_column($tasksArray, 'id');
        $tagsByTask = $this->getTagRepository()->getTagsByTaskIds($taskIds);

        foreach ($tasksArray as &$taskArr) {
            $taskArr['tags'] = $tagsByTask[$taskArr['id']] ?? [];
        }

        return $tasksArray;
    }

    // ---------------------------------------------------------------
    //  Endpoints de autenticación (públicos, sin token)
    //  Authentication endpoints (public, no token required)
    // ---------------------------------------------------------------

    /**
     * POST /auth/register - Registra un nuevo usuario.
     * POST /auth/register - Registers a new user.
     *
     * Espera un cuerpo JSON con 'username' y 'password'.
     * Retorna los datos del usuario creado con código 201.
     *
     * Expects a JSON body with 'username' and 'password'.
     * Returns the created user data with status code 201.
     *
     * @param array<string, mixed> $body Cuerpo de la petición / Request body (username, password)
     */
    public function register(array $body): void
    {
        try {
            $username = $body['username'] ?? '';
            $password = $body['password'] ?? '';

            $user = $this->authService->register(
                username: (string) $username,
                password: (string) $password,
            );

            JsonResponse::success(
                data: $user,
                code: 201,
                message: 'User registered successfully',
            );
        } catch (ValidationException $e) {
            JsonResponse::error(
                message: $e->getMessage(),
                code: 422,
                errors: ['field' => $e->field],
            );
        } catch (AppException $e) {
            JsonResponse::error(
                message: $e->getMessage(),
                code: 409,
            );
        }
    }

    /**
     * POST /auth/login - Autentica un usuario y retorna un token JWT.
     * POST /auth/login - Authenticates a user and returns a JWT token.
     *
     * Espera un cuerpo JSON con 'username' y 'password'.
     * Retorna el token JWT, tipo, tiempo de expiración y datos del usuario.
     *
     * Expects a JSON body with 'username' and 'password'.
     * Returns the JWT token, type, expiration time, and user data.
     *
     * @param array<string, mixed> $body Cuerpo de la petición / Request body (username, password)
     */
    public function login(array $body): void
    {
        try {
            $username = $body['username'] ?? '';
            $password = $body['password'] ?? '';

            $result = $this->authService->login(
                username: (string) $username,
                password: (string) $password,
            );

            JsonResponse::success(
                data: $result,
                message: 'Login successful',
            );
        } catch (ValidationException $e) {
            JsonResponse::error(
                message: $e->getMessage(),
                code: 422,
                errors: ['field' => $e->field],
            );
        } catch (AppException $e) {
            JsonResponse::error(
                message: $e->getMessage(),
                code: 401,
            );
        }
    }

    /**
     * GET /auth/me - Obtiene el perfil del usuario autenticado.
     * GET /auth/me - Gets the authenticated user's profile.
     *
     * @param array<string, mixed> $userData Datos del token JWT / JWT token data (user_id, username)
     */
    public function profile(array $userData): void
    {
        try {
            $user = $this->authService->getProfile($userData['user_id']);
            JsonResponse::success(data: $user, message: 'Profile retrieved successfully');
        } catch (NotFoundException $e) {
            JsonResponse::error(message: $e->getMessage(), code: 404);
        }
    }

    // ---------------------------------------------------------------
    //  Endpoints de tareas (protegidos, requieren token)
    //  Task endpoints (protected, token required)
    // ---------------------------------------------------------------

    /**
     * GET /tasks - Lista tareas con paginación, ordenamiento y filtros.
     * GET /tasks - Lists tasks with pagination, sorting, and filters.
     *
     * Soporta query parameters:
     * Supports query parameters:
     * - status: pendiente|completada|all (filtro de estado / status filter)
     * - page: número de página / page number (defecto/default: 1)
     * - per_page: resultados por página / results per page (defecto/default: 20, max: 100)
     * - sort: campo de ordenamiento / sort field (defecto/default: created_at)
     * - order: asc|desc (defecto/default: desc)
     * - priority: alta|media|baja (filtro de prioridad / priority filter)
     */
    public function listTasks(): void
    {
        try {
            // Obtener parámetros del query string / Get query string parameters
            $filter = (string) ($_GET['status'] ?? 'all');
            $page = (int) ($_GET['page'] ?? 1);
            $perPage = (int) ($_GET['per_page'] ?? 20);
            $sortBy = (string) ($_GET['sort'] ?? 'created_at');
            $sortDir = strtoupper((string) ($_GET['order'] ?? 'DESC'));
            $priority = isset($_GET['priority']) ? (string) $_GET['priority'] : null;

            /** @var array{tasks: Task[], total: int, page: int, per_page: int, total_pages: int} $result */
            $result = $this->getTaskService()->listTasks(
                filter: $filter,
                page: $page,
                perPage: $perPage,
                sortBy: $sortBy,
                sortDir: $sortDir,
                priority: $priority,
            );

            // Convertir las tareas a arrays para la respuesta JSON
            // Convert tasks to arrays for the JSON response
            $tasksArray = array_map(
                callback: fn (Task $t): array => $t->toArray(),
                array: $result['tasks'],
            );

            $tasksArray = $this->enrichTasksWithTags($tasksArray);

            JsonResponse::paginated(
                data: $tasksArray,
                pagination: [
                    'total' => $result['total'],
                    'page' => $result['page'],
                    'per_page' => $result['per_page'],
                    'total_pages' => $result['total_pages'],
                ],
                message: 'Tasks retrieved successfully',
            );
        } catch (ValidationException $e) {
            JsonResponse::error(
                message: $e->getMessage(),
                code: 422,
                errors: ['field' => $e->field],
            );
        }
    }

    /**
     * GET /tasks/{id} - Obtiene una tarea por su ID.
     * GET /tasks/{id} - Gets a task by its ID.
     *
     * @param array<string, string> $params Parámetros de la ruta / Route parameters (contiene/contains 'id')
     */
    public function getTask(array $params): void
    {
        try {
            $id = $params['id'] ?? '0';

            $task = $this->getTaskService()->getTask($id);

            $taskArray = $this->enrichTasksWithTags([$task->toArray()]);

            JsonResponse::success(
                data: $taskArray[0],
                message: 'Task retrieved successfully',
            );
        } catch (NotFoundException $e) {
            JsonResponse::error(
                message: $e->getMessage(),
                code: 404,
            );
        } catch (ValidationException $e) {
            JsonResponse::error(
                message: $e->getMessage(),
                code: 422,
                errors: ['field' => $e->field],
            );
        } catch (AppException $e) {
            JsonResponse::error(
                message: $e->getMessage(),
                code: 500,
            );
        }
    }

    /**
     * POST /tasks - Crea una nueva tarea.
     * POST /tasks - Creates a new task.
     *
     * Espera un cuerpo JSON con:
     * Expects a JSON body with:
     * - title (string, obligatorio/required): Título de la tarea / Task title
     * - description (string, opcional/optional): Descripción detallada / Detailed description
     * - priority (string, opcional/optional): 'alta', 'media' o 'baja' (defecto/default: 'media')
     * - due_date (string, opcional/optional): Fecha de vencimiento / Due date (YYYY-MM-DD)
     *
     * @param array<string, mixed> $body Cuerpo de la petición / Request body
     */
    public function createTask(array $body): void
    {
        try {
            $title = $body['title'] ?? '';
            $description = $body['description'] ?? '';
            $priority = $body['priority'] ?? 'media';
            $dueDate = $body['due_date'] ?? null;

            $task = $this->getTaskService()->createTask(
                title: (string) $title,
                description: (string) $description,
                priority: (string) $priority,
                dueDate: $dueDate !== null ? (string) $dueDate : null,
            );

            // Sync tags if provided / Sincronizar etiquetas si se proporcionan
            $tagIds = $body['tag_ids'] ?? null;
            if (is_array($tagIds) && $task->id !== null) {
                $this->getTagRepository()->syncTaskTags(
                    taskId: $task->id,
                    tagIds: array_map('intval', $tagIds),
                );
            }

            $taskArray = $this->enrichTasksWithTags([$task->toArray()]);

            JsonResponse::success(
                data: $taskArray[0],
                code: 201,
                message: 'Task created successfully',
            );
        } catch (ValidationException $e) {
            JsonResponse::error(
                message: $e->getMessage(),
                code: 422,
                errors: ['field' => $e->field],
            );
        }
    }

    /**
     * PATCH /tasks/{id} - Actualiza parcialmente una tarea existente.
     * PATCH /tasks/{id} - Partially updates an existing task.
     *
     * Espera un cuerpo JSON con los campos a actualizar:
     * Expects a JSON body with the fields to update:
     * - title (string, opcional/optional): Nuevo título / New title
     * - description (string, opcional/optional): Nueva descripción / New description
     * - priority (string, opcional/optional): Nueva prioridad / New priority
     * - due_date (string, opcional/optional): Nueva fecha de vencimiento / New due date (YYYY-MM-DD o/or empty to remove)
     *
     * @param array<string, string> $params Parámetros de la ruta / Route parameters (contiene/contains 'id')
     * @param array<string, mixed> $body Cuerpo de la petición / Request body
     */
    public function updateTask(array $params, array $body): void
    {
        try {
            $id = $params['id'] ?? '0';
            $title = isset($body['title']) ? (string) $body['title'] : null;
            $description = isset($body['description']) ? (string) $body['description'] : null;
            $priority = isset($body['priority']) ? (string) $body['priority'] : null;
            $dueDate = isset($body['due_date']) ? (string) $body['due_date'] : null;

            $task = $this->getTaskService()->updateTask(
                id: $id,
                title: $title,
                description: $description,
                priority: $priority,
                dueDate: $dueDate,
            );

            // Sync tags if provided / Sincronizar etiquetas si se proporcionan
            $tagIds = $body['tag_ids'] ?? null;
            if (is_array($tagIds) && $task->id !== null) {
                $this->getTagRepository()->syncTaskTags(
                    taskId: $task->id,
                    tagIds: array_map('intval', $tagIds),
                );
            }

            $taskArray = $this->enrichTasksWithTags([$task->toArray()]);

            JsonResponse::success(
                data: $taskArray[0],
                message: 'Task updated successfully',
            );
        } catch (NotFoundException $e) {
            JsonResponse::error(
                message: $e->getMessage(),
                code: 404,
            );
        } catch (ValidationException $e) {
            JsonResponse::error(
                message: $e->getMessage(),
                code: 422,
                errors: ['field' => $e->field],
            );
        } catch (AppException $e) {
            JsonResponse::error(
                message: $e->getMessage(),
                code: 500,
            );
        }
    }

    /**
     * PATCH /tasks/{id}/complete - Marca una tarea como completada.
     * PATCH /tasks/{id}/complete - Marks a task as completed.
     *
     * @param array<string, string> $params Parámetros de la ruta / Route parameters (contiene/contains 'id')
     */
    public function completeTask(array $params): void
    {
        try {
            $id = $params['id'] ?? '0';

            $task = $this->getTaskService()->completeTask($id);

            $taskArray = $this->enrichTasksWithTags([$task->toArray()]);

            JsonResponse::success(
                data: $taskArray[0],
                message: 'Task marked as completed',
            );
        } catch (NotFoundException $e) {
            JsonResponse::error(
                message: $e->getMessage(),
                code: 404,
            );
        } catch (ValidationException $e) {
            JsonResponse::error(
                message: $e->getMessage(),
                code: 422,
                errors: ['field' => $e->field],
            );
        }
    }

    /**
     * DELETE /tasks/{id} - Elimina una tarea permanentemente.
     * DELETE /tasks/{id} - Permanently deletes a task.
     *
     * @param array<string, string> $params Parámetros de la ruta / Route parameters (contiene/contains 'id')
     */
    public function deleteTask(array $params): void
    {
        try {
            $id = $params['id'] ?? '0';

            $this->getTaskService()->deleteTask($id);

            JsonResponse::noContent();
        } catch (NotFoundException $e) {
            JsonResponse::error(
                message: $e->getMessage(),
                code: 404,
            );
        } catch (ValidationException $e) {
            JsonResponse::error(
                message: $e->getMessage(),
                code: 422,
                errors: ['field' => $e->field],
            );
        }
    }

    /**
     * GET /tasks/search?q=keyword&page=1&per_page=20 - Busca tareas por palabra clave.
     * GET /tasks/search?q=keyword&page=1&per_page=20 - Searches tasks by keyword.
     *
     * El parámetro de búsqueda se pasa como query string ?q=keyword.
     * Busca en título y descripción de las tareas con paginación.
     *
     * The search parameter is passed as query string ?q=keyword.
     * Searches in task title and description with pagination.
     */
    public function searchTasks(): void
    {
        try {
            $keyword = (string) ($_GET['q'] ?? '');
            $page = (int) ($_GET['page'] ?? 1);
            $perPage = (int) ($_GET['per_page'] ?? 20);

            /** @var array{tasks: Task[], total: int, page: int, per_page: int, total_pages: int} $result */
            $result = $this->getTaskService()->searchTasks(
                keyword: $keyword,
                page: $page,
                perPage: $perPage,
            );

            $tasksArray = array_map(
                callback: fn (Task $t): array => $t->toArray(),
                array: $result['tasks'],
            );

            $tasksArray = $this->enrichTasksWithTags($tasksArray);

            JsonResponse::paginated(
                data: $tasksArray,
                pagination: [
                    'total' => $result['total'],
                    'page' => $result['page'],
                    'per_page' => $result['per_page'],
                    'total_pages' => $result['total_pages'],
                ],
                message: 'Search completed',
            );
        } catch (ValidationException $e) {
            JsonResponse::error(
                message: $e->getMessage(),
                code: 422,
                errors: ['field' => $e->field],
            );
        }
    }

    /**
     * GET /tasks/stats - Obtiene estadísticas de las tareas.
     * GET /tasks/stats - Gets task statistics.
     *
     * Retorna totales, desglose por estado y por prioridad,
     * además del porcentaje de completado.
     *
     * Returns totals, breakdown by status and priority,
     * plus the completion percentage.
     */
    public function statistics(): void
    {
        try {
            $stats = $this->getTaskService()->getStatistics();

            // Calcular porcentaje de completado / Calculate completion percentage
            $percentage = (int) $stats['total'] > 0
                ? round(((int) $stats['completed'] / (int) $stats['total']) * 100, 1)
                : 0.0;

            $stats['completion_percentage'] = $percentage;

            JsonResponse::success(
                data: $stats,
                message: 'Statistics retrieved successfully',
            );
        } catch (AppException $e) {
            JsonResponse::error(
                message: $e->getMessage(),
                code: 500,
            );
        }
    }

    /**
     * GET /tasks/export?format=json|csv - Exporta tareas como descarga directa.
     * GET /tasks/export?format=json|csv - Exports tasks as a direct download.
     *
     * El formato se especifica con el query parameter ?format=json|csv.
     * Por defecto exporta en JSON. Envía el contenido directamente al
     * cliente con las cabeceras apropiadas para descarga.
     *
     * The format is specified with the query parameter ?format=json|csv.
     * Defaults to JSON. Sends the content directly to the client
     * with the appropriate headers for download.
     */
    public function exportTasks(): void
    {
        try {
            $format = strtolower((string) ($_GET['format'] ?? 'json'));

            $tasks = $this->getTaskService()->getAllForExport();

            // Obtener contenido como string en lugar de escribir a archivo
            // Get content as string instead of writing to file
            $content = $this->exportService->exportAsString(
                tasks: $tasks,
                format: $format,
            );

            // Enviar respuesta directa con cabeceras apropiadas
            // Send direct response with appropriate headers
            JsonResponse::raw(
                content: $content,
                contentType: $format === 'csv' ? 'text/csv; charset=utf-8' : 'application/json; charset=utf-8',
                headers: ['Content-Disposition' => 'attachment; filename="tasks_' . date('Y-m-d_His') . '.' . $format . '"'],
            );
        } catch (ValidationException $e) {
            JsonResponse::error(
                message: $e->getMessage(),
                code: 422,
                errors: ['field' => $e->field],
            );
        } catch (AppException $e) {
            JsonResponse::error(
                message: $e->getMessage(),
                code: 500,
            );
        }
    }

    // ---------------------------------------------------------------
    //  Endpoints de etiquetas (protegidos, requieren token)
    //  Tag endpoints (protected, token required)
    // ---------------------------------------------------------------

    /**
     * GET /tags - Lista todas las etiquetas del usuario.
     * GET /tags - Lists all tags for the user.
     */
    public function listTags(): void
    {
        try {
            $tags = $this->getTagRepository()->findAll();

            JsonResponse::success(
                data: $tags,
                message: 'Tags retrieved successfully',
            );
        } catch (AppException $e) {
            JsonResponse::error(
                message: $e->getMessage(),
                code: 500,
            );
        }
    }

    /**
     * POST /tags - Crea una nueva etiqueta.
     * POST /tags - Creates a new tag.
     *
     * @param array<string, mixed> $body Cuerpo de la petición / Request body (name, color?)
     */
    public function createTag(array $body): void
    {
        try {
            $name = trim((string) ($body['name'] ?? ''));
            $color = trim((string) ($body['color'] ?? '#6b7280'));

            if ($name === '') {
                JsonResponse::error(
                    message: 'Tag name cannot be empty',
                    code: 422,
                    errors: ['field' => 'name'],
                );
            }

            if (mb_strlen($name) > 30) {
                JsonResponse::error(
                    message: 'Tag name cannot exceed 30 characters',
                    code: 422,
                    errors: ['field' => 'name'],
                );
            }

            $tag = $this->getTagRepository()->create(name: $name, color: $color);

            JsonResponse::success(
                data: $tag,
                code: 201,
                message: 'Tag created successfully',
            );
        } catch (AppException $e) {
            $code = str_contains($e->getMessage(), 'already exists') ? 409 : 500;
            JsonResponse::error(
                message: $e->getMessage(),
                code: $code,
            );
        }
    }

    /**
     * PATCH /tags/{id} - Actualiza una etiqueta existente.
     * PATCH /tags/{id} - Updates an existing tag.
     *
     * @param array<string, string> $params Parámetros de la ruta / Route parameters
     * @param array<string, mixed> $body Cuerpo de la petición / Request body
     */
    public function updateTag(array $params, array $body): void
    {
        try {
            $id = (int) ($params['id'] ?? 0);

            $data = [];
            if (isset($body['name'])) {
                $name = trim((string) $body['name']);
                if ($name === '') {
                    JsonResponse::error(
                        message: 'Tag name cannot be empty',
                        code: 422,
                        errors: ['field' => 'name'],
                    );
                }
                if (mb_strlen($name) > 30) {
                    JsonResponse::error(
                        message: 'Tag name cannot exceed 30 characters',
                        code: 422,
                        errors: ['field' => 'name'],
                    );
                }
                $data['name'] = $name;
            }

            if (isset($body['color'])) {
                $data['color'] = trim((string) $body['color']);
            }

            if ($data === []) {
                $tag = $this->getTagRepository()->findById($id);
            } else {
                $tag = $this->getTagRepository()->update(id: $id, data: $data);
            }

            JsonResponse::success(
                data: $tag,
                message: 'Tag updated successfully',
            );
        } catch (NotFoundException $e) {
            JsonResponse::error(message: $e->getMessage(), code: 404);
        } catch (AppException $e) {
            $code = str_contains($e->getMessage(), 'already exists') ? 409 : 500;
            JsonResponse::error(message: $e->getMessage(), code: $code);
        }
    }

    /**
     * DELETE /tags/{id} - Elimina una etiqueta.
     * DELETE /tags/{id} - Deletes a tag.
     *
     * @param array<string, string> $params Parámetros de la ruta / Route parameters
     */
    public function deleteTag(array $params): void
    {
        try {
            $id = (int) ($params['id'] ?? 0);
            $this->getTagRepository()->delete($id);
            JsonResponse::noContent();
        } catch (NotFoundException $e) {
            JsonResponse::error(message: $e->getMessage(), code: 404);
        } catch (AppException $e) {
            JsonResponse::error(message: $e->getMessage(), code: 500);
        }
    }

    /**
     * POST /tasks/{id}/tags - Sincroniza las etiquetas de una tarea.
     * POST /tasks/{id}/tags - Syncs a task's tags.
     *
     * @param array<string, string> $params Parámetros de la ruta / Route parameters
     * @param array<string, mixed> $body Cuerpo de la petición / Request body (tag_ids: int[])
     */
    public function syncTaskTags(array $params, array $body): void
    {
        try {
            $taskId = (int) ($params['id'] ?? 0);
            $tagIds = $body['tag_ids'] ?? [];

            if (!is_array($tagIds)) {
                JsonResponse::error(
                    message: 'tag_ids must be an array',
                    code: 422,
                    errors: ['field' => 'tag_ids'],
                );
            }

            $tags = $this->getTagRepository()->syncTaskTags(
                taskId: $taskId,
                tagIds: array_map('intval', $tagIds),
            );

            JsonResponse::success(
                data: $tags,
                message: 'Task tags updated successfully',
            );
        } catch (NotFoundException $e) {
            JsonResponse::error(message: $e->getMessage(), code: 404);
        } catch (AppException $e) {
            JsonResponse::error(message: $e->getMessage(), code: 500);
        }
    }
}
