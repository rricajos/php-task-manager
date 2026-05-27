<?php

declare(strict_types=1);

namespace MiniProject;

/**
 * Servicio de logica de negocio para tareas.
 *
 * Capa intermedia entre la interfaz de usuario y el repositorio.
 * Se encarga de validar datos de entrada, delegar operaciones
 * al repositorio y formatear la salida.
 *
 * Patron: Inyeccion de Dependencias (recibe el repositorio en el constructor)
 * Caracteristicas PHP 8: constructor promotion, readonly, named arguments,
 * union types, match
 */
class TaskService
{
    /**
     * Inicializa el servicio con inyeccion de dependencias.
     *
     * @param TaskRepository $repository Repositorio de acceso a datos
     */
    public function __construct(
        private readonly TaskRepository $repository,
    ) {}

    /**
     * Crea y guarda una nueva tarea validando los datos de entrada.
     *
     * @param string $titulo Titulo de la tarea (no puede estar vacio)
     * @param string $descripcion Descripcion opcional de la tarea
     * @param string $prioridad Prioridad como string: 'alta', 'media' o 'baja'
     * @param string|null $fechaVencimiento Fecha de vencimiento en formato Y-m-d (opcional)
     * @return Task La tarea creada con su id asignado
     * @throws ValidationException Si los datos no son validos
     */
    public function crearTarea(
        string $titulo,
        string $descripcion,
        string $prioridad,
        ?string $fechaVencimiento = null,
    ): Task {
        // Validar titulo (obligatorio, no vacio)
        $titulo = trim($titulo);
        if ($titulo === '') {
            throw new ValidationException(
                message: 'El titulo de la tarea no puede estar vacio',
                code: ValidationException::ERROR_CAMPO_VACIO,
                campo: 'titulo',
            );
        }

        // Validar longitud maxima del titulo
        if (mb_strlen($titulo) > 100) {
            throw new ValidationException(
                message: 'El titulo no puede superar los 100 caracteres',
                code: ValidationException::ERROR_LONGITUD_INVALIDA,
                campo: 'titulo',
            );
        }

        // Validar y convertir prioridad de string a enum
        $prioridadEnum = $this->validarPrioridad($prioridad);

        // Sanitizar descripcion
        $descripcion = trim($descripcion);

        // Validar fecha de vencimiento si se proporciona
        $fechaVencimientoValidada = $this->validarFechaVencimiento($fechaVencimiento);

        // Crear la entidad Task con named arguments
        $task = new Task(
            id: null,
            titulo: $titulo,
            descripcion: $descripcion,
            prioridad: $prioridadEnum,
            estado: Status::Pendiente,
            fechaVencimiento: $fechaVencimientoValidada,
        );

        // Delegar al repositorio para persistir
        return $this->repository->save($task);
    }

    /**
     * Obtiene una tarea por su ID.
     *
     * @param int|string $id Identificador de la tarea
     * @return Task La tarea encontrada
     * @throws ValidationException Si el ID no es valido
     * @throws NotFoundException Si la tarea no existe
     */
    public function obtenerTarea(int|string $id): Task
    {
        $idValidado = $this->validarId($id);

        return $this->repository->findById($idValidado);
    }

    /**
     * Actualiza campos de una tarea existente.
     *
     * Solo actualiza los campos que se proporcionan (no null).
     *
     * @param int|string $id Identificador de la tarea
     * @param string|null $titulo Nuevo titulo (null para no cambiar)
     * @param string|null $descripcion Nueva descripcion (null para no cambiar)
     * @param string|null $prioridad Nueva prioridad (null para no cambiar)
     * @param string|null $fechaVencimiento Nueva fecha de vencimiento (null para no cambiar)
     * @return Task Tarea actualizada
     * @throws ValidationException Si los datos no son validos
     * @throws NotFoundException Si la tarea no existe
     */
    public function actualizarTarea(
        int|string $id,
        ?string $titulo = null,
        ?string $descripcion = null,
        ?string $prioridad = null,
        ?string $fechaVencimiento = null,
    ): Task {
        $idValidado = $this->validarId($id);

        $data = [];

        // Validar y agregar titulo si se proporciona
        if ($titulo !== null) {
            $titulo = trim($titulo);
            if ($titulo === '') {
                throw new ValidationException(
                    message: 'El titulo de la tarea no puede estar vacio',
                    code: ValidationException::ERROR_CAMPO_VACIO,
                    campo: 'titulo',
                );
            }
            if (mb_strlen($titulo) > 100) {
                throw new ValidationException(
                    message: 'El titulo no puede superar los 100 caracteres',
                    code: ValidationException::ERROR_CAMPO_VACIO,
                    campo: 'titulo',
                );
            }
            $data['titulo'] = $titulo;
        }

        // Agregar descripcion si se proporciona
        if ($descripcion !== null) {
            $data['descripcion'] = trim($descripcion);
        }

        // Validar y agregar prioridad si se proporciona
        if ($prioridad !== null) {
            $prioridadEnum = $this->validarPrioridad($prioridad);
            $data['prioridad'] = $prioridadEnum->value;
        }

        // Validar y agregar fecha de vencimiento si se proporciona
        if ($fechaVencimiento !== null) {
            // Permitir cadena vacia para eliminar la fecha de vencimiento
            if ($fechaVencimiento === '') {
                $data['fecha_vencimiento'] = null;
            } else {
                $data['fecha_vencimiento'] = $this->validarFechaVencimiento($fechaVencimiento);
            }
        }

        if (empty($data)) {
            // No hay cambios, retornar la tarea sin modificar
            return $this->repository->findById($idValidado);
        }

        return $this->repository->update(id: $idValidado, data: $data);
    }

    /**
     * Lista tareas con filtro opcional por estado, paginacion y ordenamiento.
     *
     * Cuando se llama sin parametros de paginacion (page=0), retorna
     * todas las tareas como array simple (compatibilidad con CLI).
     * Cuando se especifica page >= 1, retorna un array con metadatos
     * de paginacion para la API.
     *
     * @param string $filtro Filtro de estado: 'pendiente', 'completada' o 'todas'
     * @param int $page Numero de pagina (0 = sin paginacion, >=1 = paginado)
     * @param int $perPage Resultados por pagina (1-100)
     * @param string $sortBy Campo de ordenamiento
     * @param string $sortDir Direccion: 'ASC' o 'DESC'
     * @param string|null $priority Filtro adicional de prioridad
     * @return Task[]|array{tareas: array, total: int, page: int, per_page: int, total_pages: int} Lista de tareas o respuesta paginada
     * @throws ValidationException Si el filtro no es valido
     */
    public function listarTareas(
        string $filtro = 'todas',
        int $page = 0,
        int $perPage = 20,
        string $sortBy = 'fecha_creacion',
        string $sortDir = 'DESC',
        ?string $priority = null,
    ): array {
        $filtro = strtolower(trim($filtro));

        // Validar filtro de estado
        $statusValue = match ($filtro) {
            'todas', 'all' => null,
            'pendiente', 'pendientes', 'pending' => 'pendiente',
            'completada', 'completadas', 'completed' => 'completada',
            default => throw new ValidationException(
                message: "Filtro de estado invalido: '{$filtro}'. Usa: todas, pendiente o completada",
                code: ValidationException::ERROR_ESTADO_INVALIDO,
                campo: 'estado',
            ),
        };

        // Modo sin paginacion (compatibilidad con CLI)
        if ($page === 0) {
            if ($statusValue === null && $priority === null) {
                return $this->repository->findAll();
            }
            if ($statusValue !== null && $priority === null) {
                return $this->repository->findByStatus(Status::from($statusValue));
            }
            // Si hay filtro de prioridad, usar paginacion sin limite
            return $this->repository->findAllPaginated(
                limit: PHP_INT_MAX,
                offset: 0,
                sortBy: $sortBy,
                sortDir: $sortDir,
                priority: $priority,
                status: $statusValue,
            );
        }

        // Modo paginado (API)
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $tareas = $this->repository->findAllPaginated(
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
            'tareas' => $tareas,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
        ];
    }

    /**
     * Marca una tarea como completada por su ID.
     *
     * @param int|string $id Identificador de la tarea (se valida como entero positivo)
     * @return Task Tarea actualizada con estado completada
     * @throws ValidationException Si el ID no es valido
     * @throws NotFoundException Si la tarea no existe
     */
    public function completarTarea(int|string $id): Task
    {
        $idValidado = $this->validarId($id);

        // Verificar que la tarea no este ya completada
        $tarea = $this->repository->findById($idValidado);
        if ($tarea->estado === Status::Completada) {
            throw new ValidationException(
                message: "La tarea #{$idValidado} ya esta completada",
                code: ValidationException::ERROR_ESTADO_INVALIDO,
                campo: 'estado',
            );
        }

        return $this->repository->complete($idValidado);
    }

    /**
     * Elimina una tarea por su ID.
     *
     * @param int|string $id Identificador de la tarea
     * @return bool true si se elimino correctamente
     * @throws ValidationException Si el ID no es valido
     * @throws NotFoundException Si la tarea no existe
     */
    public function eliminarTarea(int|string $id): bool
    {
        $idValidado = $this->validarId($id);

        return $this->repository->delete($idValidado);
    }

    /**
     * Busca tareas por palabra clave en titulo y descripcion.
     *
     * Cuando se llama sin paginacion (page=0), retorna un array simple
     * de tareas (compatibilidad con CLI). Con page >= 1, retorna
     * respuesta paginada para la API.
     *
     * @param string $keyword Palabra clave de busqueda
     * @param int $page Numero de pagina (0 = sin paginacion, >=1 = paginado)
     * @param int $perPage Resultados por pagina (1-100)
     * @return Task[]|array{tareas: array, total: int, page: int, per_page: int, total_pages: int} Lista de tareas o respuesta paginada
     * @throws ValidationException Si la palabra clave esta vacia
     */
    public function buscarTareas(string $keyword, int $page = 0, int $perPage = 20): array
    {
        $keyword = trim($keyword);

        if ($keyword === '') {
            throw new ValidationException(
                message: 'La palabra clave de busqueda no puede estar vacia',
                code: ValidationException::ERROR_CAMPO_VACIO,
                campo: 'keyword',
            );
        }

        // Modo sin paginacion (compatibilidad con CLI)
        if ($page === 0) {
            return $this->repository->search($keyword);
        }

        // Modo paginado (API)
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $tareas = $this->repository->search(
            keyword: $keyword,
            limit: $perPage,
            offset: $offset,
        );

        $total = $this->repository->countSearch($keyword);
        $totalPages = (int) ceil($total / $perPage);

        return [
            'tareas' => $tareas,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
        ];
    }

    /**
     * Obtiene estadisticas formateadas para mostrar en la terminal.
     *
     * @return array<string, int|array<string, int>> Datos estadisticos
     */
    public function obtenerEstadisticas(): array
    {
        return $this->repository->getStatistics();
    }

    /**
     * Obtiene todas las tareas (sin filtro) para exportacion.
     *
     * @return Task[] Todas las tareas en la base de datos
     */
    public function obtenerTodasParaExportar(): array
    {
        return $this->repository->findAll();
    }

    /**
     * Formatea las estadisticas como texto con colores ANSI para la terminal.
     *
     * @param array<string, mixed> $stats Datos estadisticos del repositorio
     * @return string Texto formateado con colores
     */
    public function formatearEstadisticas(array $stats): string
    {
        $porcentaje = $stats['total'] > 0
            ? round(($stats['completadas'] / $stats['total']) * 100, 1)
            : 0;

        // Construir barra de progreso visual
        $barraAncho = 30;
        $llenos = $stats['total'] > 0
            ? (int) round(($stats['completadas'] / $stats['total']) * $barraAncho)
            : 0;
        $vacios = $barraAncho - $llenos;
        $barra = "\033[42m" . str_repeat(' ', $llenos) . "\033[0m"
               . "\033[47m" . str_repeat(' ', $vacios) . "\033[0m";

        $lineas = [];
        $lineas[] = '';
        $lineas[] = "\033[1;35m========== ESTADISTICAS ==========\033[0m";
        $lineas[] = '';
        $lineas[] = "  Total de tareas:    \033[1m{$stats['total']}\033[0m";
        $lineas[] = "  Completadas:        \033[32m{$stats['completadas']}\033[0m";
        $lineas[] = "  Pendientes:         \033[33m{$stats['pendientes']}\033[0m";
        $lineas[] = '';
        $lineas[] = "  Progreso: [{$barra}] {$porcentaje}%";
        $lineas[] = '';
        $lineas[] = "\033[1;35m--- Por prioridad ---\033[0m";
        $lineas[] = "  \033[31mAlta:   {$stats['por_prioridad']['alta']}\033[0m";
        $lineas[] = "  \033[33mMedia:  {$stats['por_prioridad']['media']}\033[0m";
        $lineas[] = "  \033[32mBaja:   {$stats['por_prioridad']['baja']}\033[0m";
        $lineas[] = '';
        $lineas[] = "\033[1;35m==================================\033[0m";

        return implode(PHP_EOL, $lineas);
    }

    // ---------------------------------------------------------------
    //  Metodos privados de validacion
    // ---------------------------------------------------------------

    /**
     * Valida y convierte un string de prioridad al enum Priority.
     *
     * @param string $prioridad Valor de prioridad como texto
     * @return Priority Enum de prioridad validado
     * @throws ValidationException Si el valor no es una prioridad valida
     */
    private function validarPrioridad(string $prioridad): Priority
    {
        $prioridad = strtolower(trim($prioridad));

        $enum = Priority::tryFrom($prioridad);

        if ($enum === null) {
            $valoresValidos = implode(', ', array_map(
                fn(Priority $p): string => $p->value,
                Priority::cases(),
            ));

            throw new ValidationException(
                message: "Prioridad invalida: '{$prioridad}'. Valores validos: {$valoresValidos}",
                code: ValidationException::ERROR_PRIORIDAD_INVALIDA,
                campo: 'prioridad',
            );
        }

        return $enum;
    }

    /**
     * Valida que un ID sea un entero positivo.
     *
     * @param int|string $id Valor a validar como ID
     * @return int ID validado como entero positivo
     * @throws ValidationException Si el ID no es un entero positivo
     */
    private function validarId(int|string $id): int
    {
        // Si es string, intentar convertir a entero
        if (is_string($id)) {
            $id = trim($id);
            if (!ctype_digit($id)) {
                throw new ValidationException(
                    message: "El ID debe ser un numero entero positivo, se recibio: '{$id}'",
                    code: ValidationException::ERROR_ID_INVALIDO,
                    campo: 'id',
                );
            }
            $id = (int) $id;
        }

        if ($id <= 0) {
            throw new ValidationException(
                message: "El ID debe ser mayor que cero, se recibio: {$id}",
                code: ValidationException::ERROR_ID_INVALIDO,
                campo: 'id',
            );
        }

        return $id;
    }

    /**
     * Valida el formato de la fecha de vencimiento (Y-m-d).
     *
     * @param string|null $fecha Fecha a validar
     * @return string|null Fecha validada o null si no se proporciono
     * @throws ValidationException Si el formato no es valido
     */
    private function validarFechaVencimiento(?string $fecha): ?string
    {
        if ($fecha === null || $fecha === '') {
            return null;
        }

        $fecha = trim($fecha);

        // Validar formato Y-m-d
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $fecha);

        if ($dt === false || $dt->format('Y-m-d') !== $fecha) {
            throw new ValidationException(
                message: "La fecha de vencimiento debe tener formato YYYY-MM-DD, se recibio: '{$fecha}'",
                code: ValidationException::ERROR_FORMATO_INVALIDO,
                campo: 'fecha_vencimiento',
            );
        }

        return $fecha;
    }
}
