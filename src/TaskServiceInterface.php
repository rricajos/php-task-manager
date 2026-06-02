<?php

declare(strict_types=1);

namespace MiniProject;

/**
 * Interfaz para el servicio de lógica de negocio de tareas.
 * Interface for the task business logic service.
 *
 * Define el contrato que debe cumplir cualquier implementación
 * del servicio de tareas, permitiendo desacoplamiento e inyección
 * de dependencias.
 *
 * Defines the contract for any task service implementation,
 * enabling decoupling and dependency injection.
 */
interface TaskServiceInterface
{
    /**
     * Crea y guarda una nueva tarea.
     * Creates and saves a new task.
     *
     * @param string $title Título de la tarea / Task title
     * @param string $description Descripción / Description
     * @param string $priority Prioridad como string / Priority as string
     * @param string|null $dueDate Fecha de vencimiento / Due date
     * @param string|null $recurrence Intervalo de recurrencia / Recurrence interval
     * @return Task La tarea creada / The created task
     */
    public function createTask(
        string $title,
        string $description,
        string $priority,
        ?string $dueDate = null,
        ?string $recurrence = null,
    ): Task;

    /**
     * Obtiene una tarea por su ID.
     * Gets a task by its ID.
     *
     * @param int|string $id Identificador de la tarea / Task identifier
     * @return Task La tarea encontrada / The found task
     */
    public function getTask(int|string $id): Task;

    /**
     * Actualiza campos de una tarea existente.
     * Updates fields of an existing task.
     *
     * @param int|string $id Identificador de la tarea / Task identifier
     * @param string|null $title Nuevo título / New title
     * @param string|null $description Nueva descripción / New description
     * @param string|null $priority Nueva prioridad / New priority
     * @param string|null $dueDate Nueva fecha de vencimiento / New due date
     * @param string|null $recurrence Nuevo intervalo de recurrencia / New recurrence interval
     * @return Task Tarea actualizada / Updated task
     */
    public function updateTask(
        int|string $id,
        ?string $title = null,
        ?string $description = null,
        ?string $priority = null,
        ?string $dueDate = null,
        ?string $recurrence = null,
    ): Task;

    /**
     * Lista tareas con filtro, paginación y ordenamiento.
     * Lists tasks with filter, pagination and sorting.
     *
     * @param string $filter Filtro de estado / Status filter
     * @param int $page Número de página / Page number
     * @param int $perPage Resultados por página / Results per page
     * @param string $sortBy Campo de ordenamiento / Sort field
     * @param string $sortDir Dirección de orden / Sort direction
     * @param string|null $priority Filtro de prioridad / Priority filter
     * @return Task[]|array{tasks: Task[], total: int, page: int, per_page: int, total_pages: int}
     */
    public function listTasks(
        string $filter = 'all',
        int $page = 0,
        int $perPage = 20,
        string $sortBy = 'created_at',
        string $sortDir = 'DESC',
        ?string $priority = null,
    ): array;

    /**
     * Marca una tarea como completada.
     * Marks a task as completed.
     *
     * @param int|string $id Identificador de la tarea / Task identifier
     * @return Task Tarea completada / Completed task
     */
    public function completeTask(int|string $id): Task;

    /**
     * Elimina una tarea por su ID.
     * Deletes a task by its ID.
     *
     * @param int|string $id Identificador de la tarea / Task identifier
     * @return bool true si se eliminó correctamente / true if deleted successfully
     */
    public function deleteTask(int|string $id): bool;

    /**
     * Busca tareas por palabra clave.
     * Searches tasks by keyword.
     *
     * @param string $keyword Palabra clave / Keyword
     * @param int $page Número de página / Page number
     * @param int $perPage Resultados por página / Results per page
     * @return Task[]|array{tasks: Task[], total: int, page: int, per_page: int, total_pages: int}
     */
    public function searchTasks(string $keyword, int $page = 0, int $perPage = 20): array;

    /**
     * Completa múltiples tareas en una sola operación.
     * Completes multiple tasks in a single operation.
     *
     * @param array<mixed> $ids IDs de las tareas a completar / IDs of the tasks to complete
     * @return array{affected: int, skipped: int[]} Resultado de la operación / Operation result
     */
    public function bulkComplete(array $ids): array;

    /**
     * Elimina múltiples tareas en una sola operación.
     * Deletes multiple tasks in a single operation.
     *
     * @param array<mixed> $ids IDs de las tareas a eliminar / IDs of the tasks to delete
     * @return array{affected: int} Resultado de la operación / Operation result
     */
    public function bulkDelete(array $ids): array;

    /**
     * Obtiene estadísticas de las tareas.
     * Gets task statistics.
     *
     * @return array<string, int|array<string, int>> Datos estadísticos / Statistical data
     */
    public function getStatistics(): array;

    /**
     * Obtiene todas las tareas para exportación.
     * Gets all tasks for export.
     *
     * @return Task[] Todas las tareas / All tasks
     */
    public function getAllForExport(): array;

    /**
     * Formatea las estadísticas para la salida en terminal.
     * Formats statistics for terminal output.
     *
     * @param array<string, int|array<string, int>> $stats Datos estadísticos / Statistical data
     * @return string Texto formateado con colores ANSI / ANSI-colored formatted text
     */
    public function formatStatistics(array $stats): string;
}
