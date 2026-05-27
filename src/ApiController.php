<?php

declare(strict_types=1);

namespace MiniProject;

/**
 * Controlador de la API REST para el gestor de tareas.
 *
 * Recibe datos de peticiones HTTP, delega al TaskService existente
 * y devuelve respuestas JSON estructuradas. Actua como puente entre
 * el Router HTTP y la capa de logica de negocio.
 *
 * Reutiliza completamente: TaskService, ExportService, AuthService,
 * y todas las excepciones personalizadas existentes.
 *
 * Soporta aislamiento de datos por usuario: tras la autenticacion,
 * se llama a setUserId() para crear un TaskService vinculado al usuario.
 *
 * Caracteristicas PHP 8: constructor promotion, readonly, named arguments,
 * match, union types
 */
class ApiController
{
    /** Servicio de tareas vinculado al usuario autenticado */
    private ?TaskService $userTaskService = null;

    /**
     * Inicializa el controlador con los servicios necesarios.
     *
     * @param TaskService $taskService Servicio de logica de negocio de tareas (por defecto)
     * @param ExportService $exportService Servicio de exportacion (JSON/CSV)
     * @param AuthService $authService Servicio de autenticacion y JWT
     */
    public function __construct(
        private readonly TaskService $taskService,
        private readonly ExportService $exportService,
        private readonly AuthService $authService,
    ) {}

    /**
     * Establece el ID del usuario autenticado y crea servicios vinculados.
     *
     * Debe llamarse despues de requireAuth() en cada ruta protegida.
     *
     * @param int $userId ID del usuario autenticado
     */
    public function setUserId(int $userId): void
    {
        $repository = new TaskRepository(userId: $userId);
        $this->userTaskService = new TaskService(repository: $repository);
    }

    /**
     * Obtiene el servicio de tareas del usuario autenticado.
     *
     * Si se ha llamado a setUserId(), retorna el servicio vinculado al usuario.
     * En caso contrario, retorna el servicio por defecto.
     *
     * @return TaskService Servicio de tareas activo
     */
    private function getTaskService(): TaskService
    {
        return $this->userTaskService ?? $this->taskService;
    }

    // ---------------------------------------------------------------
    //  Endpoints de autenticacion (publicos, sin token)
    // ---------------------------------------------------------------

    /**
     * POST /auth/register - Registra un nuevo usuario.
     *
     * Espera un cuerpo JSON con 'username' y 'password'.
     * Retorna los datos del usuario creado con codigo 201.
     *
     * @param array<string, mixed> $body Cuerpo de la peticion (username, password)
     */
    public function registrar(array $body): void
    {
        try {
            $username = $body['username'] ?? '';
            $password = $body['password'] ?? '';

            $usuario = $this->authService->registrar(
                username: (string) $username,
                password: (string) $password,
            );

            JsonResponse::success(
                data: $usuario,
                code: 201,
                message: 'Usuario registrado exitosamente',
            );
        } catch (ValidationException $e) {
            JsonResponse::error(
                message: $e->getMessage(),
                code: 422,
                errors: ['campo' => $e->campo],
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
     *
     * Espera un cuerpo JSON con 'username' y 'password'.
     * Retorna el token JWT, tipo, tiempo de expiracion y datos del usuario.
     *
     * @param array<string, mixed> $body Cuerpo de la peticion (username, password)
     */
    public function login(array $body): void
    {
        try {
            $username = $body['username'] ?? '';
            $password = $body['password'] ?? '';

            $resultado = $this->authService->login(
                username: (string) $username,
                password: (string) $password,
            );

            JsonResponse::success(
                data: $resultado,
                message: 'Login exitoso',
            );
        } catch (ValidationException $e) {
            JsonResponse::error(
                message: $e->getMessage(),
                code: 422,
                errors: ['campo' => $e->campo],
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
     *
     * @param array<string, mixed> $userData Datos del token JWT (user_id, username)
     */
    public function perfil(array $userData): void
    {
        try {
            $usuario = $this->authService->obtenerPerfil($userData['user_id']);
            JsonResponse::success(data: $usuario, message: 'Perfil obtenido exitosamente');
        } catch (NotFoundException $e) {
            JsonResponse::error(message: $e->getMessage(), code: 404);
        }
    }

    // ---------------------------------------------------------------
    //  Endpoints de tareas (protegidos, requieren token)
    // ---------------------------------------------------------------

    /**
     * GET /tasks - Lista tareas con paginacion, ordenamiento y filtros.
     *
     * Soporta query parameters:
     * - status: pendiente|completada|todas (filtro de estado)
     * - page: numero de pagina (defecto: 1)
     * - per_page: resultados por pagina (defecto: 20, max: 100)
     * - sort: campo de ordenamiento (defecto: fecha_creacion)
     * - order: asc|desc (defecto: desc)
     * - priority: alta|media|baja (filtro de prioridad)
     */
    public function listarTareas(): void
    {
        try {
            // Obtener parametros del query string
            $filtro = (string) ($_GET['status'] ?? 'todas');
            $page = (int) ($_GET['page'] ?? 1);
            $perPage = (int) ($_GET['per_page'] ?? 20);
            $sortBy = (string) ($_GET['sort'] ?? 'fecha_creacion');
            $sortDir = strtoupper((string) ($_GET['order'] ?? 'DESC'));
            $priority = isset($_GET['priority']) ? (string) $_GET['priority'] : null;

            $resultado = $this->getTaskService()->listarTareas(
                filtro: $filtro,
                page: $page,
                perPage: $perPage,
                sortBy: $sortBy,
                sortDir: $sortDir,
                priority: $priority,
            );

            // Convertir las tareas a arrays para la respuesta JSON
            $tareasArray = array_map(
                callback: fn(Task $t): array => $t->toArray(),
                array: $resultado['tareas'],
            );

            JsonResponse::paginated(
                data: $tareasArray,
                pagination: [
                    'total' => $resultado['total'],
                    'page' => $resultado['page'],
                    'per_page' => $resultado['per_page'],
                    'total_pages' => $resultado['total_pages'],
                ],
                message: 'Tareas obtenidas exitosamente',
            );
        } catch (ValidationException $e) {
            JsonResponse::error(
                message: $e->getMessage(),
                code: 422,
                errors: ['campo' => $e->campo],
            );
        }
    }

    /**
     * GET /tasks/{id} - Obtiene una tarea por su ID.
     *
     * @param array<string, string> $params Parametros de la ruta (contiene 'id')
     */
    public function obtenerTarea(array $params): void
    {
        try {
            $id = $params['id'] ?? '0';

            $tarea = $this->getTaskService()->obtenerTarea($id);

            JsonResponse::success(
                data: $tarea->toArray(),
                message: 'Tarea obtenida exitosamente',
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
                errors: ['campo' => $e->campo],
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
     *
     * Espera un cuerpo JSON con:
     * - title (string, obligatorio): Titulo de la tarea
     * - description (string, opcional): Descripcion detallada
     * - priority (string, opcional): 'alta', 'media' o 'baja' (defecto: 'media')
     * - due_date (string, opcional): Fecha de vencimiento en formato YYYY-MM-DD
     *
     * @param array<string, mixed> $body Cuerpo de la peticion
     */
    public function crearTarea(array $body): void
    {
        try {
            $titulo = $body['title'] ?? '';
            $descripcion = $body['description'] ?? '';
            $prioridad = $body['priority'] ?? 'media';
            $fechaVencimiento = $body['due_date'] ?? null;

            $tarea = $this->getTaskService()->crearTarea(
                titulo: (string) $titulo,
                descripcion: (string) $descripcion,
                prioridad: (string) $prioridad,
                fechaVencimiento: $fechaVencimiento !== null ? (string) $fechaVencimiento : null,
            );

            JsonResponse::success(
                data: $tarea->toArray(),
                code: 201,
                message: 'Tarea creada exitosamente',
            );
        } catch (ValidationException $e) {
            JsonResponse::error(
                message: $e->getMessage(),
                code: 422,
                errors: ['campo' => $e->campo],
            );
        }
    }

    /**
     * PUT /tasks/{id} - Actualiza una tarea existente.
     *
     * Espera un cuerpo JSON con los campos a actualizar:
     * - title (string, opcional): Nuevo titulo
     * - description (string, opcional): Nueva descripcion
     * - priority (string, opcional): Nueva prioridad
     * - due_date (string, opcional): Nueva fecha de vencimiento (YYYY-MM-DD o vacio para eliminar)
     *
     * @param array<string, string> $params Parametros de la ruta (contiene 'id')
     * @param array<string, mixed> $body Cuerpo de la peticion
     */
    public function actualizarTarea(array $params, array $body): void
    {
        try {
            $id = $params['id'] ?? '0';
            $titulo = isset($body['title']) ? (string) $body['title'] : null;
            $descripcion = isset($body['description']) ? (string) $body['description'] : null;
            $prioridad = isset($body['priority']) ? (string) $body['priority'] : null;
            $fechaVencimiento = isset($body['due_date']) ? (string) $body['due_date'] : null;

            $tarea = $this->getTaskService()->actualizarTarea(
                id: $id,
                titulo: $titulo,
                descripcion: $descripcion,
                prioridad: $prioridad,
                fechaVencimiento: $fechaVencimiento,
            );

            JsonResponse::success(
                data: $tarea->toArray(),
                message: 'Tarea actualizada exitosamente',
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
                errors: ['campo' => $e->campo],
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
     *
     * @param array<string, string> $params Parametros de la ruta (contiene 'id')
     */
    public function completarTarea(array $params): void
    {
        try {
            $id = $params['id'] ?? '0';

            $tarea = $this->getTaskService()->completarTarea($id);

            JsonResponse::success(
                data: $tarea->toArray(),
                message: 'Tarea marcada como completada',
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
                errors: ['campo' => $e->campo],
            );
        }
    }

    /**
     * DELETE /tasks/{id} - Elimina una tarea permanentemente.
     *
     * @param array<string, string> $params Parametros de la ruta (contiene 'id')
     */
    public function eliminarTarea(array $params): void
    {
        try {
            $id = $params['id'] ?? '0';

            $this->getTaskService()->eliminarTarea($id);

            JsonResponse::success(
                message: "Tarea #{$id} eliminada permanentemente",
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
                errors: ['campo' => $e->campo],
            );
        }
    }

    /**
     * GET /tasks/search?q=keyword&page=1&per_page=20 - Busca tareas por palabra clave.
     *
     * El parametro de busqueda se pasa como query string ?q=keyword.
     * Busca en titulo y descripcion de las tareas con paginacion.
     */
    public function buscarTareas(): void
    {
        try {
            $keyword = (string) ($_GET['q'] ?? '');
            $page = (int) ($_GET['page'] ?? 1);
            $perPage = (int) ($_GET['per_page'] ?? 20);

            $resultado = $this->getTaskService()->buscarTareas(
                keyword: $keyword,
                page: $page,
                perPage: $perPage,
            );

            $tareasArray = array_map(
                callback: fn(Task $t): array => $t->toArray(),
                array: $resultado['tareas'],
            );

            JsonResponse::paginated(
                data: $tareasArray,
                pagination: [
                    'total' => $resultado['total'],
                    'page' => $resultado['page'],
                    'per_page' => $resultado['per_page'],
                    'total_pages' => $resultado['total_pages'],
                ],
                message: 'Busqueda completada',
            );
        } catch (ValidationException $e) {
            JsonResponse::error(
                message: $e->getMessage(),
                code: 422,
                errors: ['campo' => $e->campo],
            );
        }
    }

    /**
     * GET /tasks/stats - Obtiene estadisticas de las tareas.
     *
     * Retorna totales, desglose por estado y por prioridad,
     * ademas del porcentaje de completado.
     */
    public function estadisticas(): void
    {
        try {
            $stats = $this->getTaskService()->obtenerEstadisticas();

            // Calcular porcentaje de completado
            $porcentaje = $stats['total'] > 0
                ? round(($stats['completadas'] / $stats['total']) * 100, 1)
                : 0.0;

            $stats['porcentaje_completado'] = $porcentaje;

            JsonResponse::success(
                data: $stats,
                message: 'Estadisticas obtenidas exitosamente',
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
     *
     * El formato se especifica con el query parameter ?format=json|csv.
     * Por defecto exporta en JSON. Envia el contenido directamente al
     * cliente con las cabeceras apropiadas para descarga.
     */
    public function exportarTareas(): void
    {
        try {
            $formato = strtolower((string) ($_GET['format'] ?? 'json'));

            $tareas = $this->getTaskService()->obtenerTodasParaExportar();

            if (empty($tareas)) {
                JsonResponse::success(
                    data: null,
                    message: 'No hay tareas para exportar',
                );
            }

            // Obtener contenido como string en lugar de escribir a archivo
            $contenido = $this->exportService->exportarComoString(
                tasks: $tareas,
                formato: $formato,
            );

            // Establecer cabeceras segun el formato y enviar contenido
            if ($formato === 'csv') {
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="tareas_' . date('Y-m-d_His') . '.csv"');
            } else {
                header('Content-Type: application/json; charset=utf-8');
                header('Content-Disposition: attachment; filename="tareas_' . date('Y-m-d_His') . '.json"');
            }

            echo $contenido;
            exit;
        } catch (ValidationException $e) {
            JsonResponse::error(
                message: $e->getMessage(),
                code: 422,
                errors: ['campo' => $e->campo],
            );
        } catch (AppException $e) {
            JsonResponse::error(
                message: $e->getMessage(),
                code: 500,
            );
        }
    }
}
