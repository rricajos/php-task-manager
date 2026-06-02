<?php

declare(strict_types=1);

namespace Tests\Unit;

use MiniProject\Database;
use MiniProject\NotFoundException;
use MiniProject\Priority;
use MiniProject\Status;
use MiniProject\Task;
use MiniProject\TaskRepository;
use MiniProject\TaskService;
use MiniProject\ValidationException;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitarios para TaskService con base de datos SQLite en memoria.
 * Unit tests for TaskService with in-memory SQLite database.
 *
 * @covers \MiniProject\TaskService
 * @covers \MiniProject\TaskRepository
 * @covers \MiniProject\Database
 */
class TaskServiceTest extends TestCase
{
    private TaskService $service;
    private TaskRepository $repository;

    protected function setUp(): void
    {
        // Resetear el singleton para que cada test tenga su propia BD en memoria
        Database::resetInstance();
        Database::getInstance(':memory:');

        // Insertar un usuario de prueba para que el FK sea valido
        $pdo = Database::getInstance()->getConnection();
        $pdo->exec('INSERT INTO users (username, password_hash) VALUES (\'testuser\', \'hash\')');

        $this->repository = new TaskRepository(userId: 1);
        $this->service = new TaskService(repository: $this->repository);
    }

    protected function tearDown(): void
    {
        Database::resetInstance();
    }

    // ---------------------------------------------------------------
    //  Tests for createTask with valid data
    // ---------------------------------------------------------------

    public function testCreateTaskWithValidData(): void
    {
        $task = $this->service->createTask(
            title: 'Comprar pan',
            description: 'En la panaderia de la esquina',
            priority: 'high',
        );

        $this->assertInstanceOf(Task::class, $task);
        $this->assertNotNull($task->id);
        $this->assertSame('Comprar pan', $task->title);
        $this->assertSame('En la panaderia de la esquina', $task->description);
        $this->assertSame(Priority::High, $task->priority);
        $this->assertSame(Status::Pending, $task->status);
        $this->assertNotEmpty($task->createdAt);
    }

    public function testCreateTaskWithMediumPriority(): void
    {
        $task = $this->service->createTask(
            title: 'Estudiar PHP',
            description: 'Repasar patrones de diseno',
            priority: 'medium',
        );

        $this->assertSame(Priority::Medium, $task->priority);
    }

    public function testCreateTaskWithLowPriority(): void
    {
        $task = $this->service->createTask(
            title: 'Ordenar escritorio',
            description: '',
            priority: 'low',
        );

        $this->assertSame(Priority::Low, $task->priority);
    }

    public function testCreateTaskWithEmptyDescription(): void
    {
        $task = $this->service->createTask(
            title: 'Tarea sin descripcion',
            description: '',
            priority: 'medium',
        );

        $this->assertSame('', $task->description);
    }

    public function testCreateTaskTrimsTitle(): void
    {
        $task = $this->service->createTask(
            title: '   Titulo con espacios   ',
            description: '',
            priority: 'low',
        );

        $this->assertSame('Titulo con espacios', $task->title);
    }

    public function testCreateMultipleTasksAssignsConsecutiveIds(): void
    {
        $task1 = $this->service->createTask('Primera tarea', '', 'high');
        $task2 = $this->service->createTask('Segunda tarea', '', 'medium');
        $task3 = $this->service->createTask('Tercera tarea', '', 'low');

        $this->assertSame(1, $task1->id);
        $this->assertSame(2, $task2->id);
        $this->assertSame(3, $task3->id);
    }

    // ---------------------------------------------------------------
    //  Tests for validation: empty title
    // ---------------------------------------------------------------

    public function testCreateTaskRejectsEmptyTitle(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Task title cannot be empty');

        $this->service->createTask(
            title: '',
            description: 'Tiene descripcion pero no titulo',
            priority: 'high',
        );
    }

    public function testCreateTaskRejectsWhitespaceOnlyTitle(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Task title cannot be empty');

        $this->service->createTask(
            title: '     ',
            description: '',
            priority: 'medium',
        );
    }

    public function testCreateTaskEmptyTitleHasCorrectField(): void
    {
        try {
            $this->service->createTask('', '', 'high');
            $this->fail('Deberia haber lanzado ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame('title', $e->field);
            $this->assertSame(ValidationException::ERROR_EMPTY_FIELD, $e->getCode());
        }
    }

    // ---------------------------------------------------------------
    //  Tests for validation: title too long
    // ---------------------------------------------------------------

    public function testCreateTaskRejectsTitleOver100Characters(): void
    {
        $longTitle = str_repeat('a', 101);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Title cannot exceed 100 characters');

        $this->service->createTask(
            title: $longTitle,
            description: '',
            priority: 'high',
        );
    }

    public function testCreateTaskAccepts100CharacterTitle(): void
    {
        $exactTitle = str_repeat('x', 100);

        $task = $this->service->createTask(
            title: $exactTitle,
            description: '',
            priority: 'medium',
        );

        $this->assertSame(100, mb_strlen($task->title));
    }

    // ---------------------------------------------------------------
    //  Tests for validation: invalid priority
    // ---------------------------------------------------------------

    public function testCreateTaskRejectsInvalidPriority(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid priority');

        $this->service->createTask(
            title: 'Tarea con prioridad invalida',
            description: '',
            priority: 'urgente',
        );
    }

    public function testCreateTaskRejectsEmptyPriority(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->createTask(
            title: 'Tarea sin prioridad',
            description: '',
            priority: '',
        );
    }

    public function testCreateTaskPriorityValidationHasCorrectField(): void
    {
        try {
            $this->service->createTask('Tarea', '', 'inexistente');
            $this->fail('Deberia haber lanzado ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame('priority', $e->field);
            $this->assertSame(ValidationException::ERROR_INVALID_PRIORITY, $e->getCode());
        }
    }

    // ---------------------------------------------------------------
    //  Tests for completeTask
    // ---------------------------------------------------------------

    public function testCompletePendingTask(): void
    {
        $created = $this->service->createTask('Tarea por completar', '', 'high');

        $completed = $this->service->completeTask($created->id);

        $this->assertSame(Status::Completed, $completed->status);
        $this->assertNotNull($completed->completedAt);
    }

    public function testCompleteAlreadyCompletedTaskThrowsException(): void
    {
        $created = $this->service->createTask('Tarea doble completar', '', 'medium');
        $this->service->completeTask($created->id);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('already completed');

        $this->service->completeTask($created->id);
    }

    public function testCompleteTaskWithStringId(): void
    {
        $created = $this->service->createTask('Tarea id string', '', 'low');

        $completed = $this->service->completeTask((string) $created->id);

        $this->assertSame(Status::Completed, $completed->status);
    }

    // ---------------------------------------------------------------
    //  Tests for searchTasks
    // ---------------------------------------------------------------

    public function testSearchTasksWithKeyword(): void
    {
        $this->service->createTask('Comprar leche', 'En el supermercado', 'high');
        $this->service->createTask('Comprar pan', 'En la panaderia', 'medium');
        $this->service->createTask('Estudiar PHP', 'Repasar enums', 'low');

        $results = $this->service->searchTasks('Comprar');

        $this->assertCount(2, $results);
    }

    public function testSearchTasksWithEmptyKeywordThrowsException(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Search keyword cannot be empty');

        $this->service->searchTasks('');
    }

    public function testSearchTasksWithOnlySpacesThrowsException(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->searchTasks('   ');
    }

    public function testSearchTasksByDescription(): void
    {
        $this->service->createTask('Tarea 1', 'Comprar material de oficina', 'high');
        $this->service->createTask('Tarea 2', 'Revisar documentos', 'medium');

        $results = $this->service->searchTasks('oficina');

        $this->assertCount(1, $results);
        $this->assertSame('Tarea 1', $results[0]->title);
    }

    public function testSearchTasksNoResults(): void
    {
        $this->service->createTask('Estudiar PHP', '', 'high');

        $results = $this->service->searchTasks('Python');

        $this->assertCount(0, $results);
    }

    // ---------------------------------------------------------------
    //  Tests for listTasks
    // ---------------------------------------------------------------

    public function testListAllTasks(): void
    {
        $this->service->createTask('Tarea 1', '', 'high');
        $this->service->createTask('Tarea 2', '', 'medium');

        $tasks = $this->service->listTasks('all');

        $this->assertCount(2, $tasks);
    }

    public function testListPendingTasks(): void
    {
        $task = $this->service->createTask('Tarea pendiente', '', 'high');
        $this->service->createTask('Tarea completar', '', 'medium');
        $this->service->completeTask(2);

        $pending = $this->service->listTasks('pending');

        $this->assertCount(1, $pending);
        $this->assertSame(Status::Pending, $pending[0]->status);
    }

    public function testListCompletedTasks(): void
    {
        $this->service->createTask('Tarea 1', '', 'high');
        $this->service->createTask('Tarea 2', '', 'medium');
        $this->service->completeTask(1);

        $completed = $this->service->listTasks('completed');

        $this->assertCount(1, $completed);
        $this->assertSame(Status::Completed, $completed[0]->status);
    }

    public function testListTasksWithInvalidFilterThrowsException(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid status filter');

        $this->service->listTasks('cancelada');
    }

    // ---------------------------------------------------------------
    //  Tests for deleteTask
    // ---------------------------------------------------------------

    public function testDeleteExistingTask(): void
    {
        $this->service->createTask('Tarea a eliminar', '', 'high');

        $result = $this->service->deleteTask(1);

        $this->assertTrue($result);

        // Verificar que la lista esta vacia
        $tasks = $this->service->listTasks('all');
        $this->assertCount(0, $tasks);
    }

    // ---------------------------------------------------------------
    //  Tests for getStatistics
    // ---------------------------------------------------------------

    public function testGetStatisticsWithTasks(): void
    {
        $this->service->createTask('Alta 1', '', 'high');
        $this->service->createTask('Media 1', '', 'medium');
        $this->service->createTask('Baja 1', '', 'low');
        $this->service->completeTask(1);

        $stats = $this->service->getStatistics();

        $this->assertSame(3, $stats['total']);
        $this->assertSame(1, $stats['completed']);
        $this->assertSame(2, $stats['pending']);
        $this->assertSame(1, $stats['by_priority']['high']);
        $this->assertSame(1, $stats['by_priority']['medium']);
        $this->assertSame(1, $stats['by_priority']['low']);
    }

    public function testGetStatisticsWithoutTasks(): void
    {
        $stats = $this->service->getStatistics();

        $this->assertSame(0, $stats['total']);
        $this->assertSame(0, $stats['completed']);
        $this->assertSame(0, $stats['pending']);
        $this->assertSame(0, $stats['by_priority']['high']);
        $this->assertSame(0, $stats['by_priority']['medium']);
        $this->assertSame(0, $stats['by_priority']['low']);
    }

    // ---------------------------------------------------------------
    //  Tests for getTask()
    // ---------------------------------------------------------------

    public function testGetTaskById(): void
    {
        $created = $this->service->createTask(
            title: 'Tarea para obtener',
            description: 'Descripcion de prueba',
            priority: 'high',
        );

        $retrieved = $this->service->getTask($created->id);

        $this->assertSame($created->id, $retrieved->id);
        $this->assertSame('Tarea para obtener', $retrieved->title);
        $this->assertSame('Descripcion de prueba', $retrieved->description);
        $this->assertSame(Priority::High, $retrieved->priority);
    }

    public function testGetTaskByStringId(): void
    {
        $created = $this->service->createTask('Tarea string id', '', 'medium');

        $retrieved = $this->service->getTask((string) $created->id);

        $this->assertSame($created->id, $retrieved->id);
    }

    public function testGetNonExistentTaskThrowsException(): void
    {
        $this->expectException(NotFoundException::class);

        $this->service->getTask(999);
    }

    public function testGetTaskWithInvalidIdThrowsException(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->getTask('abc');
    }

    public function testGetTaskWithZeroIdThrowsException(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->getTask(0);
    }

    // ---------------------------------------------------------------
    //  Tests for updateTask()
    // ---------------------------------------------------------------

    public function testUpdateTaskTitle(): void
    {
        $created = $this->service->createTask('Titulo original', 'Desc', 'high');

        $updated = $this->service->updateTask(
            id: $created->id,
            title: 'Titulo modificado',
        );

        $this->assertSame('Titulo modificado', $updated->title);
        // Las demas propiedades no cambian
        $this->assertSame('Desc', $updated->description);
        $this->assertSame(Priority::High, $updated->priority);
    }

    public function testUpdateTaskDescription(): void
    {
        $created = $this->service->createTask('Titulo', 'Desc original', 'medium');

        $updated = $this->service->updateTask(
            id: $created->id,
            description: 'Desc modificada',
        );

        $this->assertSame('Desc modificada', $updated->description);
        $this->assertSame('Titulo', $updated->title);
    }

    public function testUpdateTaskPriority(): void
    {
        $created = $this->service->createTask('Titulo', '', 'high');

        $updated = $this->service->updateTask(
            id: $created->id,
            priority: 'low',
        );

        $this->assertSame(Priority::Low, $updated->priority);
    }

    public function testUpdateTaskWithNoChangesReturnsOriginal(): void
    {
        $created = $this->service->createTask('Titulo', 'Desc', 'high');

        $unchanged = $this->service->updateTask(id: $created->id);

        $this->assertSame($created->id, $unchanged->id);
        $this->assertSame('Titulo', $unchanged->title);
    }

    public function testUpdateTaskWithEmptyTitleThrowsException(): void
    {
        $created = $this->service->createTask('Titulo valido', '', 'high');

        $this->expectException(ValidationException::class);

        $this->service->updateTask(
            id: $created->id,
            title: '',
        );
    }

    public function testUpdateTaskWithInvalidPriorityThrowsException(): void
    {
        $created = $this->service->createTask('Titulo', '', 'high');

        $this->expectException(ValidationException::class);

        $this->service->updateTask(
            id: $created->id,
            priority: 'urgente',
        );
    }

    public function testUpdateNonExistentTaskThrowsException(): void
    {
        $this->expectException(NotFoundException::class);

        $this->service->updateTask(
            id: 999,
            title: 'Nuevo titulo',
        );
    }

    // ---------------------------------------------------------------
    //  Tests for createTask with dueDate
    // ---------------------------------------------------------------

    public function testCreateTaskWithDueDate(): void
    {
        $task = $this->service->createTask(
            title: 'Tarea con vencimiento',
            description: 'Debe completarse pronto',
            priority: 'high',
            dueDate: '2026-12-31',
        );

        $this->assertSame('2026-12-31', $task->dueDate);
    }

    public function testCreateTaskWithoutDueDate(): void
    {
        $task = $this->service->createTask(
            title: 'Tarea sin fecha',
            description: '',
            priority: 'medium',
        );

        $this->assertNull($task->dueDate);
    }

    public function testCreateTaskWithInvalidDueDateFormat(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->createTask(
            title: 'Tarea con fecha invalida',
            description: '',
            priority: 'high',
            dueDate: '31/12/2026',
        );
    }

    public function testCreateTaskWithEmptyDueDateIsNull(): void
    {
        $task = $this->service->createTask(
            title: 'Tarea fecha vacia',
            description: '',
            priority: 'medium',
            dueDate: '',
        );

        $this->assertNull($task->dueDate);
    }

    // ---------------------------------------------------------------
    //  Tests for updateTask with dueDate
    // ---------------------------------------------------------------

    public function testUpdateTaskWithDueDate(): void
    {
        $created = $this->service->createTask('Titulo', '', 'high');

        $updated = $this->service->updateTask(
            id: $created->id,
            dueDate: '2026-12-25',
        );

        $this->assertSame('2026-12-25', $updated->dueDate);
    }

    public function testUpdateTaskRemoveDueDate(): void
    {
        $created = $this->service->createTask(
            title: 'Titulo',
            description: '',
            priority: 'high',
            dueDate: '2026-12-25',
        );

        // Enviar cadena vacia debe limpiar la fecha de vencimiento
        $updated = $this->service->updateTask(
            id: $created->id,
            dueDate: '',
        );

        $this->assertNull($updated->dueDate);
    }

    public function testUpdateTaskWithInvalidDueDate(): void
    {
        $created = $this->service->createTask('Titulo', '', 'high');

        $this->expectException(ValidationException::class);

        $this->service->updateTask(
            id: $created->id,
            dueDate: 'no-es-fecha',
        );
    }

    // ---------------------------------------------------------------
    //  Tests for listTasks with pagination
    // ---------------------------------------------------------------

    public function testListTasksPaginatedReturnsMetadata(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->service->createTask("Tarea {$i}", '', 'medium');
        }

        $result = $this->service->listTasks(
            filter: 'all',
            page: 1,
            perPage: 2,
        );

        $this->assertArrayHasKey('tasks', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('page', $result);
        $this->assertArrayHasKey('per_page', $result);
        $this->assertArrayHasKey('total_pages', $result);

        $this->assertSame(5, $result['total']);
        $this->assertSame(1, $result['page']);
        $this->assertSame(2, $result['per_page']);
        $this->assertSame(3, $result['total_pages']);
        $this->assertCount(2, $result['tasks']);
    }

    public function testListTasksWithoutPagination(): void
    {
        $this->service->createTask('Tarea 1', '', 'high');
        $this->service->createTask('Tarea 2', '', 'medium');

        // page=0 retorna array simple (compatibilidad)
        $result = $this->service->listTasks(filter: 'all', page: 0);

        $this->assertIsArray($result);
        // Array simple, no estructura paginada
        $this->assertArrayNotHasKey('tasks', $result);
        $this->assertCount(2, $result);
    }

    // ---------------------------------------------------------------
    //  Tests for searchTasks with pagination
    // ---------------------------------------------------------------

    public function testSearchTasksPaginated(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->service->createTask("PHP tarea {$i}", '', 'medium');
        }

        $result = $this->service->searchTasks(
            keyword: 'PHP',
            page: 1,
            perPage: 2,
        );

        $this->assertArrayHasKey('tasks', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertSame(5, $result['total']);
        $this->assertCount(2, $result['tasks']);
    }

    // ---------------------------------------------------------------
    //  Tests for formatStatistics
    // ---------------------------------------------------------------

    public function testFormatStatisticsWithData(): void
    {
        $stats = [
            'total' => 10,
            'completed' => 5,
            'pending' => 5,
            'by_priority' => [
                'high' => 3,
                'medium' => 4,
                'low' => 3,
            ],
        ];

        $texto = $this->service->formatStatistics($stats);

        $this->assertStringContainsString('ESTADISTICAS', $texto);
        $this->assertStringContainsString('10', $texto);
        $this->assertStringContainsString('50', $texto); // 50% porcentaje
    }

    public function testFormatStatisticsWithoutTasks(): void
    {
        $stats = [
            'total' => 0,
            'completed' => 0,
            'pending' => 0,
            'by_priority' => [
                'high' => 0,
                'medium' => 0,
                'low' => 0,
            ],
        ];

        $texto = $this->service->formatStatistics($stats);

        $this->assertStringContainsString('ESTADISTICAS', $texto);
    }

    // --- Edge cases: pagination bounds ---

    public function testListTasksWithNegativePageClampedToOne(): void
    {
        $this->service->createTask('Task 1', '', 'medium');

        $result = $this->service->listTasks(
            filter: 'all',
            page: -5,
            perPage: 20,
        );

        $this->assertArrayHasKey('page', $result);
        $this->assertSame(1, $result['page']);
    }

    public function testListTasksPerPageClampedTo100(): void
    {
        $this->service->createTask('Task 1', '', 'medium');

        $result = $this->service->listTasks(
            filter: 'all',
            page: 1,
            perPage: 500,
        );

        $this->assertSame(100, $result['per_page']);
    }

    public function testListTasksPerPageClampedToOneForZero(): void
    {
        $this->service->createTask('Task 1', '', 'medium');

        $result = $this->service->listTasks(
            filter: 'all',
            page: 1,
            perPage: 0,
        );

        $this->assertSame(1, $result['per_page']);
    }

    // --- Edge cases: unicode titles ---

    public function testCreateTaskWithUnicodeTitle(): void
    {
        $title = str_repeat("\u{00E9}", 100);
        $task = $this->service->createTask($title, '', 'high');

        $this->assertSame(100, mb_strlen($task->title));
    }

    public function testCreateTaskWith101UnicodeCharsRejects(): void
    {
        $title = str_repeat("\u{00E9}", 101);

        $this->expectException(\MiniProject\ValidationException::class);
        $this->expectExceptionMessage('cannot exceed 100 characters');

        $this->service->createTask($title, '', 'high');
    }

    public function testCompleteTaskWithNegativeIdThrows(): void
    {
        $this->expectException(\MiniProject\ValidationException::class);

        $this->service->completeTask(-1);
    }

    // ---------------------------------------------------------------
    //  Tests: recurrence / Recurrencia
    // ---------------------------------------------------------------

    public function testCreateTaskWithRecurrence(): void
    {
        $task = $this->service->createTask('Standup', '', 'medium', null, 'daily');
        $this->assertSame(\MiniProject\RecurrenceInterval::Daily, $task->recurrence);
    }

    public function testCreateTaskWithNullRecurrenceDefaultsToNone(): void
    {
        $task = $this->service->createTask('Task', '', 'medium');
        $this->assertSame(\MiniProject\RecurrenceInterval::None, $task->recurrence);
    }

    public function testCreateTaskWithInvalidRecurrenceThrows(): void
    {
        $this->expectException(\MiniProject\ValidationException::class);
        $this->expectExceptionMessage('Invalid recurrence');

        $this->service->createTask('Task', '', 'medium', null, 'hourly');
    }

    public function testUpdateTaskRecurrence(): void
    {
        $task = $this->service->createTask('Task', '', 'medium');
        $updated = $this->service->updateTask($task->id, recurrence: 'weekly');
        $this->assertSame(\MiniProject\RecurrenceInterval::Weekly, $updated->recurrence);
    }

    public function testCompleteRecurringTaskCreatesNextOccurrence(): void
    {
        $task = $this->service->createTask('Daily task', '', 'high', '2026-06-01', 'daily');
        $this->service->completeTask($task->id);

        // Should now have 2 tasks: the completed one + the next occurrence
        $all = $this->service->listTasks('all');
        $this->assertCount(2, $all);

        $next = array_values(array_filter($all, fn ($t) => $t->status === \MiniProject\Status::Pending));
        $this->assertCount(1, $next);
        $this->assertSame('2026-06-02', $next[0]->dueDate);
        $this->assertSame(\MiniProject\RecurrenceInterval::Daily, $next[0]->recurrence);
    }

    public function testCompleteNonRecurringTaskDoesNotCreateNextOccurrence(): void
    {
        $task = $this->service->createTask('One-time task', '', 'low');
        $this->service->completeTask($task->id);

        $all = $this->service->listTasks('all');
        $this->assertCount(1, $all);
    }

    public function testCompleteRecurringTaskWithNoDueDateUsesRelativeDate(): void
    {
        $task = $this->service->createTask('Weekly task', '', 'medium', null, 'weekly');
        $this->service->completeTask($task->id);

        $pending = array_values(array_filter(
            $this->service->listTasks('all'),
            fn ($t) => $t->status === \MiniProject\Status::Pending,
        ));
        $this->assertCount(1, $pending);
        $expected = (new \DateTimeImmutable())->modify('+7 days')->format('Y-m-d');
        $this->assertSame($expected, $pending[0]->dueDate);
    }

    // ---------------------------------------------------------------
    //  Tests: bulkComplete / Completar en bulk
    // ---------------------------------------------------------------

    public function testBulkCompleteEmptyIdsThrows(): void
    {
        $this->expectException(\MiniProject\ValidationException::class);
        $this->expectExceptionMessage('cannot be empty');

        $this->service->bulkComplete([]);
    }

    public function testBulkCompleteWithNonIntegerThrows(): void
    {
        $this->expectException(\MiniProject\ValidationException::class);

        $this->service->bulkComplete(['abc']);
    }

    public function testBulkCompleteWithZeroIdThrows(): void
    {
        $this->expectException(\MiniProject\ValidationException::class);

        $this->service->bulkComplete([0]);
    }

    public function testBulkCompletePendingTasks(): void
    {
        $t1 = $this->service->createTask('T1', '', 'low');
        $t2 = $this->service->createTask('T2', '', 'low');

        $result = $this->service->bulkComplete([$t1->id, $t2->id]);

        $this->assertSame(2, $result['affected']);
        $this->assertSame([], $result['skipped']);
    }

    public function testBulkCompletePartialResult(): void
    {
        $t1 = $this->service->createTask('T1', '', 'low');
        $t2 = $this->service->createTask('T2', '', 'low');
        $this->service->completeTask($t2->id);

        $result = $this->service->bulkComplete([$t1->id, $t2->id]);

        $this->assertSame(1, $result['affected']);
        $this->assertContains($t2->id, $result['skipped']);
    }

    // ---------------------------------------------------------------
    //  Tests: bulkDelete / Eliminar en bulk
    // ---------------------------------------------------------------

    public function testBulkDeleteEmptyIdsThrows(): void
    {
        $this->expectException(\MiniProject\ValidationException::class);

        $this->service->bulkDelete([]);
    }

    public function testBulkDeleteTasks(): void
    {
        $t1 = $this->service->createTask('T1', '', 'low');
        $t2 = $this->service->createTask('T2', '', 'low');

        $result = $this->service->bulkDelete([$t1->id, $t2->id]);

        $this->assertSame(2, $result['affected']);
        $this->assertCount(0, $this->service->listTasks('all'));
    }

    public function testBulkDeleteIgnoresNonexistentIds(): void
    {
        $t1 = $this->service->createTask('T1', '', 'low');
        $result = $this->service->bulkDelete([$t1->id, 9999]);
        $this->assertSame(1, $result['affected']);
    }

    public function testBulkDeleteWithNegativeIdThrows(): void
    {
        $this->expectException(\MiniProject\ValidationException::class);

        $this->service->bulkDelete([-1]);
    }
}
