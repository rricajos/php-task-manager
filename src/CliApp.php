<?php

declare(strict_types=1);

namespace MiniProject;

/**
 * Aplicación CLI del Gestor de Tareas — encapsula toda la lógica de interfaz.
 * CLI Application for the Task Manager — encapsulates all interface logic.
 *
 * Clase principal que gestiona la interacción del usuario por terminal,
 * delegando la lógica de negocio a TaskServiceInterface y la exportación
 * a ExportService. Todas las cadenas visibles al usuario permanecen en
 * español; todos los identificadores internos están en inglés.
 *
 * Main class managing user interaction through the terminal, delegating
 * business logic to TaskServiceInterface and export functionality to
 * ExportService. All user-facing strings remain in Spanish; all internal
 * identifiers are in English.
 *
 * Patrones / Patterns: Inyección de Dependencias, Fachada
 *                       Dependency Injection, Facade
 * Características PHP 8 / PHP 8 Features: constructor promotion, readonly,
 *     named arguments, match, class constants, enums
 */
class CliApp
{
    // ---------------------------------------------------------------
    //  Constantes de colores ANSI para la terminal
    //  ANSI color constants for terminal output
    // ---------------------------------------------------------------

    /** Restablecer todos los estilos / Reset all styles */
    private const RESET = "\033[0m";

    /** Texto en negrita / Bold text */
    private const BOLD = "\033[1m";

    /** Texto atenuado / Dim text */
    private const DIM = "\033[2m";

    /** Texto rojo / Red text */
    private const RED = "\033[31m";

    /** Texto verde / Green text */
    private const GREEN = "\033[32m";

    /** Texto amarillo / Yellow text */
    private const YELLOW = "\033[33m";

    /** Texto azul / Blue text */
    private const BLUE = "\033[34m";

    /** Texto magenta / Magenta text */
    private const MAGENTA = "\033[35m";

    /** Texto cian / Cyan text */
    private const CYAN = "\033[36m";

    /** Texto blanco / White text */
    private const WHITE = "\033[37m";

    /** Fondo azul / Blue background */
    private const BG_BLUE = "\033[44m";

    /**
     * Inicializa la aplicación CLI con los servicios inyectados.
     * Initializes the CLI application with the injected services.
     *
     * @param TaskServiceInterface $taskService Servicio de lógica de negocio de tareas / Task business logic service
     * @param ExportService $exportService Servicio de exportación de tareas / Task export service
     */
    public function __construct(
        private readonly TaskServiceInterface $taskService,
        private readonly ExportService $exportService,
    ) {
    }

    // ---------------------------------------------------------------
    //  Métodos auxiliares de la interfaz CLI
    //  CLI interface helper methods
    // ---------------------------------------------------------------

    /**
     * Limpia la pantalla de la terminal.
     * Clears the terminal screen.
     */
    private function clearScreen(): void
    {
        // Usar secuencia ANSI compatible con la mayoria de terminales
        echo "\033[2J\033[H";
    }

    /**
     * Muestra el banner/logo de la aplicación.
     * Shows the application banner/logo.
     */
    private function showBanner(): void
    {
        echo PHP_EOL;
        echo self::CYAN . self::BOLD . '  ╔══════════════════════════════════════════╗' . self::RESET . PHP_EOL;
        echo self::CYAN . self::BOLD . '  ║                                          ║' . self::RESET . PHP_EOL;
        echo self::CYAN . self::BOLD . '  ║     ' . self::WHITE . self::BG_BLUE . ' GESTOR DE TAREAS CLI ' . self::RESET . self::CYAN . self::BOLD . '              ║' . self::RESET . PHP_EOL;
        echo self::CYAN . self::BOLD . '  ║                                          ║' . self::RESET . PHP_EOL;
        echo self::CYAN . self::BOLD . '  ║  ' . self::DIM . 'PHP 8 | SQLite | Patrones de Diseno' . self::RESET . self::CYAN . self::BOLD . ' ║' . self::RESET . PHP_EOL;
        echo self::CYAN . self::BOLD . '  ║                                          ║' . self::RESET . PHP_EOL;
        echo self::CYAN . self::BOLD . '  ╚══════════════════════════════════════════╝' . self::RESET . PHP_EOL;
        echo PHP_EOL;
    }

    /**
     * Muestra el menú principal con opciones numeradas.
     * Shows the main menu with numbered options.
     */
    private function showMenu(): void
    {
        echo self::YELLOW . self::BOLD . '  ┌─────────── MENU PRINCIPAL ───────────┐' . self::RESET . PHP_EOL;
        echo self::YELLOW              . '  │                                       │' . self::RESET . PHP_EOL;
        echo self::YELLOW              . '  │  ' . self::GREEN  . '1.' . self::RESET . ' Agregar tarea                   ' . self::YELLOW . '│' . self::RESET . PHP_EOL;
        echo self::YELLOW              . '  │  ' . self::GREEN  . '2.' . self::RESET . ' Listar tareas                   ' . self::YELLOW . '│' . self::RESET . PHP_EOL;
        echo self::YELLOW              . '  │  ' . self::GREEN  . '3.' . self::RESET . ' Completar tarea                 ' . self::YELLOW . '│' . self::RESET . PHP_EOL;
        echo self::YELLOW              . '  │  ' . self::GREEN  . '4.' . self::RESET . ' Eliminar tarea                  ' . self::YELLOW . '│' . self::RESET . PHP_EOL;
        echo self::YELLOW              . '  │  ' . self::GREEN  . '5.' . self::RESET . ' Buscar tareas                   ' . self::YELLOW . '│' . self::RESET . PHP_EOL;
        echo self::YELLOW              . '  │  ' . self::GREEN  . '6.' . self::RESET . ' Exportar tareas                 ' . self::YELLOW . '│' . self::RESET . PHP_EOL;
        echo self::YELLOW              . '  │  ' . self::GREEN  . '7.' . self::RESET . ' Estadisticas                    ' . self::YELLOW . '│' . self::RESET . PHP_EOL;
        echo self::YELLOW              . '  │  ' . self::GREEN  . '8.' . self::RESET . ' Editar tarea                    ' . self::YELLOW . '│' . self::RESET . PHP_EOL;
        echo self::YELLOW              . '  │  ' . self::RED    . '0.' . self::RESET . ' Salir                           ' . self::YELLOW . '│' . self::RESET . PHP_EOL;
        echo self::YELLOW              . '  │                                       │' . self::RESET . PHP_EOL;
        echo self::YELLOW . self::BOLD . '  └───────────────────────────────────────┘' . self::RESET . PHP_EOL;
        echo PHP_EOL;
    }

    /**
     * Lee una línea de entrada del usuario desde STDIN.
     * Reads a line of user input from STDIN.
     *
     * @param string $prompt Texto a mostrar como indicador / Text to show as prompt
     * @return string Texto ingresado por el usuario (sin salto de línea) / User input (without newline)
     */
    private function readInput(string $prompt): string
    {
        echo self::CYAN . "  {$prompt}" . self::RESET;
        $input = fgets(STDIN);

        // Si STDIN se cierra (ej: pipe, EOF), retornar string vacio
        if ($input === false) {
            return '';
        }

        return trim($input);
    }

    /**
     * Muestra un mensaje de éxito en verde.
     * Shows a success message in green.
     *
     * @param string $message Mensaje a mostrar / Message to display
     */
    private function showSuccess(string $message): void
    {
        echo PHP_EOL . self::GREEN . self::BOLD . '  [OK] ' . self::RESET . self::GREEN . $message . self::RESET . PHP_EOL;
    }

    /**
     * Muestra un mensaje de error en rojo.
     * Shows an error message in red.
     *
     * @param string $message Mensaje de error a mostrar / Error message to display
     */
    private function showError(string $message): void
    {
        echo PHP_EOL . self::RED . self::BOLD . '  [ERROR] ' . self::RESET . self::RED . $message . self::RESET . PHP_EOL;
    }

    /**
     * Muestra un mensaje informativo en azul.
     * Shows an informational message in blue.
     *
     * @param string $message Mensaje informativo / Informational message
     */
    private function showInfo(string $message): void
    {
        echo self::BLUE . "  {$message}" . self::RESET . PHP_EOL;
    }

    /**
     * Pausa la ejecución hasta que el usuario presione Enter.
     * Pauses execution until the user presses Enter.
     */
    private function pause(): void
    {
        echo PHP_EOL;
        $this->readInput('Presiona Enter para continuar...');
    }

    /**
     * Muestra una lista de tareas formateada en la terminal.
     * Shows a formatted task list in the terminal.
     *
     * @param Task[] $tasks Lista de tareas a mostrar / List of tasks to display
     */
    private function showTaskList(array $tasks): void
    {
        if (empty($tasks)) {
            $this->showInfo('No se encontraron tareas.');

            return;
        }

        echo PHP_EOL;
        echo self::BOLD . '  ' . str_pad('ID', 6) . str_pad('Estado', 6) . str_pad('Pri', 6)
           . str_pad('Titulo', 32) . 'Prioridad' . self::RESET . PHP_EOL;
        echo self::DIM . '  ' . str_repeat('-', 60) . self::RESET . PHP_EOL;

        foreach ($tasks as $task) {
            echo $task->formatLine() . PHP_EOL;
        }

        echo self::DIM . '  ' . str_repeat('-', 60) . self::RESET . PHP_EOL;
        echo self::DIM . '  Total: ' . count($tasks) . ' tarea(s)' . self::RESET . PHP_EOL;
    }

    // ---------------------------------------------------------------
    //  Métodos de cada acción del menú
    //  Menu action methods
    // ---------------------------------------------------------------

    /**
     * Acción: Agregar una nueva tarea.
     * Solicita título, descripción, prioridad y fecha de vencimiento al usuario.
     *
     * Action: Add a new task.
     * Asks the user for title, description, priority and due date.
     */
    private function actionAddTask(): void
    {
        echo PHP_EOL . self::MAGENTA . self::BOLD . '  === AGREGAR NUEVA TAREA ===' . self::RESET . PHP_EOL . PHP_EOL;

        // Solicitar datos al usuario
        $title = $this->readInput('Titulo: ');

        if ($title === '') {
            $this->showError('El titulo no puede estar vacio.');

            return;
        }

        $description = $this->readInput('Descripcion (opcional): ');

        echo self::CYAN . '  Prioridad (' . self::RED . 'high' . self::RESET . self::CYAN . '/' . self::YELLOW . 'medium' . self::RESET . self::CYAN . '/' . self::GREEN . 'low' . self::RESET . self::CYAN . '): ' . self::RESET;
        $priority = trim(fgets(STDIN) ?: 'medium');

        // Si el usuario no ingresa nada, usar 'medium' como predeterminado
        if ($priority === '') {
            $priority = 'medium';
        }

        $dueDate = $this->readInput('Fecha de vencimiento (YYYY-MM-DD, opcional): ');
        if ($dueDate === '') {
            $dueDate = null;
        }

        try {
            $task = $this->taskService->createTask(
                title: $title,
                description: $description,
                priority: $priority,
                dueDate: $dueDate,
            );

            $this->showSuccess("Tarea #{$task->id} creada exitosamente.");
            echo PHP_EOL;
            echo $task->formatDetail() . PHP_EOL;
        } catch (ValidationException $e) {
            $this->showError($e->getMessage());
        }
    }

    /**
     * Acción: Listar tareas con filtro por estado.
     * Action: List tasks with status filter.
     */
    private function actionListTasks(): void
    {
        echo PHP_EOL . self::MAGENTA . self::BOLD . '  === LISTAR TAREAS ===' . self::RESET . PHP_EOL . PHP_EOL;

        echo self::CYAN . '  Filtrar por estado (' . self::YELLOW . 'pending' . self::RESET . self::CYAN . '/' . self::GREEN . 'completed' . self::RESET . self::CYAN . '/' . self::BOLD . 'all' . self::RESET . self::CYAN . '): ' . self::RESET;
        $filter = trim(fgets(STDIN) ?: 'all');

        if ($filter === '') {
            $filter = 'all';
        }

        try {
            /** @var Task[] $tasks */
            $tasks = $this->taskService->listTasks($filter);
            $this->showTaskList($tasks);
        } catch (ValidationException $e) {
            $this->showError($e->getMessage());
        }
    }

    /**
     * Acción: Marcar una tarea como completada por ID.
     * Action: Mark a task as completed by ID.
     */
    private function actionCompleteTask(): void
    {
        echo PHP_EOL . self::MAGENTA . self::BOLD . '  === COMPLETAR TAREA ===' . self::RESET . PHP_EOL . PHP_EOL;

        // Primero mostrar las tareas pendientes
        try {
            /** @var Task[] $pending */
            $pending = $this->taskService->listTasks('pending');
            if (empty($pending)) {
                $this->showInfo('No hay tareas pendientes para completar.');

                return;
            }
            $this->showTaskList($pending);
        } catch (AppException $e) {
            $this->showError($e->getMessage());

            return;
        }

        echo PHP_EOL;
        $id = $this->readInput('ID de la tarea a completar: ');

        if ($id === '') {
            $this->showError('Debes ingresar un ID.');

            return;
        }

        try {
            $task = $this->taskService->completeTask($id);
            $this->showSuccess("Tarea #{$task->id} marcada como completada.");
            echo PHP_EOL;
            echo $task->formatDetail() . PHP_EOL;
        } catch (ValidationException | NotFoundException $e) {
            $this->showError($e->getMessage());
        }
    }

    /**
     * Acción: Eliminar una tarea por ID.
     * Action: Delete a task by ID.
     */
    private function actionDeleteTask(): void
    {
        echo PHP_EOL . self::MAGENTA . self::BOLD . '  === ELIMINAR TAREA ===' . self::RESET . PHP_EOL . PHP_EOL;

        // Mostrar todas las tareas para referencia
        try {
            /** @var Task[] $all */
            $all = $this->taskService->listTasks('all');
            if (empty($all)) {
                $this->showInfo('No hay tareas para eliminar.');

                return;
            }
            $this->showTaskList($all);
        } catch (AppException $e) {
            $this->showError($e->getMessage());

            return;
        }

        echo PHP_EOL;
        $id = $this->readInput('ID de la tarea a eliminar: ');

        if ($id === '') {
            $this->showError('Debes ingresar un ID.');

            return;
        }

        // Confirmar eliminacion
        $confirmation = $this->readInput("Seguro que deseas eliminar la tarea #{$id}? (s/n): ");

        if (strtolower($confirmation) !== 's') {
            $this->showInfo('Eliminacion cancelada.');

            return;
        }

        try {
            $this->taskService->deleteTask($id);
            $this->showSuccess("Tarea #{$id} eliminada permanentemente.");
        } catch (ValidationException | NotFoundException $e) {
            $this->showError($e->getMessage());
        }
    }

    /**
     * Acción: Buscar tareas por palabra clave.
     * Action: Search tasks by keyword.
     */
    private function actionSearchTasks(): void
    {
        echo PHP_EOL . self::MAGENTA . self::BOLD . '  === BUSCAR TAREAS ===' . self::RESET . PHP_EOL . PHP_EOL;

        $keyword = $this->readInput('Palabra clave: ');

        if ($keyword === '') {
            $this->showError('Debes ingresar una palabra clave.');

            return;
        }

        try {
            /** @var Task[] $results */
            $results = $this->taskService->searchTasks($keyword);

            echo PHP_EOL . self::DIM . "  Resultados para: \"{$keyword}\"" . self::RESET . PHP_EOL;
            $this->showTaskList($results);
        } catch (ValidationException $e) {
            $this->showError($e->getMessage());
        }
    }

    /**
     * Acción: Exportar tareas a archivo (JSON o CSV).
     * Action: Export tasks to file (JSON or CSV).
     */
    private function actionExportTasks(): void
    {
        echo PHP_EOL . self::MAGENTA . self::BOLD . '  === EXPORTAR TAREAS ===' . self::RESET . PHP_EOL . PHP_EOL;

        // Mostrar formatos disponibles
        $formats = $this->exportService->availableFormats();
        $formatsStr = implode(', ', $formats);
        echo self::CYAN . "  Formato ({$formatsStr}): " . self::RESET;
        $format = trim(fgets(STDIN) ?: 'json');

        if ($format === '') {
            $format = 'json';
        }

        try {
            $tasks = $this->taskService->getAllForExport();

            if (empty($tasks)) {
                $this->showInfo('No hay tareas para exportar.');

                return;
            }

            $path = $this->exportService->export(tasks: $tasks, format: $format);
            $this->showSuccess('Tareas exportadas exitosamente.');
            $this->showInfo("Archivo: {$path}");
            $this->showInfo('Total exportadas: ' . count($tasks) . ' tarea(s)');
        } catch (ValidationException | AppException $e) {
            $this->showError($e->getMessage());
        }
    }

    /**
     * Acción: Mostrar estadísticas de las tareas.
     * Action: Show task statistics.
     */
    private function actionStatistics(): void
    {
        try {
            $stats = $this->taskService->getStatistics();
            echo $this->taskService->formatStatistics($stats);
        } catch (AppException $e) {
            $this->showError($e->getMessage());
        }
    }

    /**
     * Acción: Editar una tarea existente.
     * Muestra las tareas, solicita el ID, y permite modificar
     * título, descripción, prioridad y fecha de vencimiento.
     * Dejar un campo vacío mantiene el valor actual.
     *
     * Action: Edit an existing task.
     * Displays tasks, asks for an ID, and allows modifying
     * title, description, priority and due date.
     * Leaving a field empty keeps the current value.
     */
    private function actionEditTask(): void
    {
        echo PHP_EOL . self::MAGENTA . self::BOLD . '  === EDITAR TAREA ===' . self::RESET . PHP_EOL . PHP_EOL;

        // Mostrar todas las tareas para referencia
        try {
            /** @var Task[] $all */
            $all = $this->taskService->listTasks('all');
            if (empty($all)) {
                $this->showInfo('No hay tareas para editar.');

                return;
            }
            $this->showTaskList($all);
        } catch (AppException $e) {
            $this->showError($e->getMessage());

            return;
        }

        echo PHP_EOL;
        $id = $this->readInput('ID de la tarea a editar: ');

        if ($id === '') {
            $this->showError('Debes ingresar un ID.');

            return;
        }

        // Obtener la tarea actual para mostrar valores
        try {
            $currentTask = $this->taskService->getTask($id);
        } catch (ValidationException | NotFoundException $e) {
            $this->showError($e->getMessage());

            return;
        }

        echo PHP_EOL;
        echo $currentTask->formatDetail() . PHP_EOL;
        echo PHP_EOL;
        $this->showInfo('Ingresa los nuevos valores (Enter para mantener el actual):');
        echo PHP_EOL;

        // Solicitar nuevos valores
        $newTitle = $this->readInput("Titulo [{$currentTask->title}]: ");
        $newDescription = $this->readInput("Descripcion [{$currentTask->description}]: ");

        echo self::CYAN . "  Prioridad [{$currentTask->priority->value}] (" . self::RED . 'high' . self::RESET . self::CYAN . '/' . self::YELLOW . 'medium' . self::RESET . self::CYAN . '/' . self::GREEN . 'low' . self::RESET . self::CYAN . '): ' . self::RESET;
        $newPriority = trim(fgets(STDIN) ?: '');

        $currentDate = $currentTask->dueDate ?? 'ninguna';
        $newDate = $this->readInput("Fecha vencimiento [{$currentDate}] (YYYY-MM-DD, 'borrar' para quitar): ");

        // Preparar parametros: null = no cambiar, cadena = nuevo valor
        $title = $newTitle !== '' ? $newTitle : null;
        $description = $newDescription !== '' ? $newDescription : null;
        $priority = $newPriority !== '' ? $newPriority : null;

        $dueDate = null;
        if ($newDate !== '') {
            if (strtolower($newDate) === 'borrar') {
                // Cadena vacia indica eliminar la fecha
                $dueDate = '';
            } else {
                $dueDate = $newDate;
            }
        }

        try {
            $updatedTask = $this->taskService->updateTask(
                id: $id,
                title: $title,
                description: $description,
                priority: $priority,
                dueDate: $dueDate,
            );

            $this->showSuccess("Tarea #{$updatedTask->id} actualizada exitosamente.");
            echo PHP_EOL;
            echo $updatedTask->formatDetail() . PHP_EOL;
        } catch (ValidationException | NotFoundException $e) {
            $this->showError($e->getMessage());
        }
    }

    // ---------------------------------------------------------------
    //  Bucle principal de la aplicación
    //  Main application loop
    // ---------------------------------------------------------------

    /**
     * Ejecuta el bucle principal de la aplicación CLI.
     * Inicializa la base de datos, crea los servicios y gestiona
     * el ciclo de vida del menú interactivo.
     *
     * Runs the main CLI application loop.
     * Initializes the database, creates the services and manages
     * the interactive menu lifecycle.
     */
    public function run(): void
    {
        try {
            // Inicializar la base de datos (Singleton)
            Database::getInstance();

            // Bucle principal del menu
            $running = true;

            while ($running) {
                $this->clearScreen();
                $this->showBanner();
                $this->showMenu();

                $option = $this->readInput('Selecciona una opcion [0-8]: ');

                // Usar match para despachar la opcion seleccionada
                match ($option) {
                    '1' => $this->actionAddTask(),
                    '2' => $this->actionListTasks(),
                    '3' => $this->actionCompleteTask(),
                    '4' => $this->actionDeleteTask(),
                    '5' => $this->actionSearchTasks(),
                    '6' => $this->actionExportTasks(),
                    '7' => $this->actionStatistics(),
                    '8' => $this->actionEditTask(),
                    '0', 'q', 'salir', 'exit' => $running = false,
                    default => $this->showError("Opcion no valida: '{$option}'. Usa un numero del 0 al 8."),
                };

                // Pausar antes de volver al menu (excepto al salir)
                if ($running) {
                    $this->pause();
                }
            }

            // Mensaje de despedida
            echo PHP_EOL;
            echo self::GREEN . self::BOLD . '  Hasta luego! Gracias por usar el Gestor de Tareas.' . self::RESET . PHP_EOL;
            echo PHP_EOL;

        } catch (AppException $e) {
            // Error critico de la aplicacion
            echo PHP_EOL;
            echo self::RED . self::BOLD . '  ERROR CRITICO: ' . self::RESET . self::RED . $e->getMessage() . self::RESET . PHP_EOL;
            echo self::DIM . "  Codigo: {$e->getCode()}" . self::RESET . PHP_EOL;
            echo PHP_EOL;
            exit(1);
        } catch (\Throwable $e) {
            // Error inesperado no manejado
            echo PHP_EOL;
            echo self::RED . self::BOLD . '  ERROR INESPERADO: ' . self::RESET . self::RED . $e->getMessage() . self::RESET . PHP_EOL;
            echo self::DIM . "  Archivo: {$e->getFile()}:{$e->getLine()}" . self::RESET . PHP_EOL;
            echo PHP_EOL;
            exit(2);
        }
    }
}
