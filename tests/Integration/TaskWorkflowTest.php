<?php

declare(strict_types=1);

namespace Tests\Integration;

use MiniProject\Database;
use MiniProject\NotFoundException;
use MiniProject\Priority;
use MiniProject\Status;
use MiniProject\Task;
use MiniProject\TaskRepository;
use MiniProject\TaskService;
use PHPUnit\Framework\TestCase;

/**
 * Tests de integración que verifican el flujo completo de trabajo.
 * Integration tests verifying the complete task workflow.
 *
 * @covers \MiniProject\TaskService
 * @covers \MiniProject\TaskRepository
 * @covers \MiniProject\Database
 * @covers \MiniProject\Task
 */
class TaskWorkflowTest extends TestCase
{
    private TaskService $service;
    private TaskRepository $repository;

    protected function setUp(): void
    {
        Database::resetInstance();
        Database::getInstance(':memory:');

        // Insertar un usuario de prueba para satisfacer la FK
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
    //  Test de flujo completo: crear -> buscar -> completar -> verificar -> eliminar
    // ---------------------------------------------------------------

    public function testCompleteTaskWorkflow(): void
    {
        // 1. Crear una tarea
        $createdTask = $this->service->createTask(
            title: 'Implementar autenticacion',
            description: 'Agregar login con JWT',
            priority: 'high',
        );

        $this->assertNotNull($createdTask->id);
        $this->assertSame('Implementar autenticacion', $createdTask->title);
        $this->assertSame(Status::Pending, $createdTask->status);
        $this->assertNull($createdTask->completedAt);

        $taskId = $createdTask->id;

        // 2. Buscar la tarea por ID usando el repositorio
        $foundTask = $this->repository->findById($taskId);

        $this->assertSame($taskId, $foundTask->id);
        $this->assertSame('Implementar autenticacion', $foundTask->title);
        $this->assertSame('Agregar login con JWT', $foundTask->description);
        $this->assertSame(Priority::High, $foundTask->priority);

        // 3. Completar la tarea
        $completedTask = $this->service->completeTask($taskId);

        $this->assertSame(Status::Completed, $completedTask->status);
        $this->assertNotNull($completedTask->completedAt);

        // 4. Verificar que el estado persiste al volver a buscar
        $verifiedTask = $this->repository->findById($taskId);

        $this->assertSame(Status::Completed, $verifiedTask->status);
        $this->assertNotNull($verifiedTask->completedAt);

        // 5. Eliminar la tarea
        $eliminada = $this->service->deleteTask($taskId);

        $this->assertTrue($eliminada);

        // 6. Verificar que la tarea ya no existe
        $this->expectException(NotFoundException::class);
        $this->repository->findById($taskId);
    }

    // ---------------------------------------------------------------
    //  Test de busqueda
    // ---------------------------------------------------------------

    public function testSearchFindsMatchingTasks(): void
    {
        $this->service->createTask('Revisar codigo PHP', 'Refactorizar modulos', 'high');
        $this->service->createTask('Escribir documentacion', 'Documentar la API REST', 'medium');
        $this->service->createTask('Configurar CI/CD', 'Pipeline de PHP con GitHub Actions', 'low');
        $this->service->createTask('Comprar monitor', 'Para la oficina', 'low');

        // Buscar por palabra clave en titulo
        $phpResults = $this->service->searchTasks('PHP');
        $this->assertCount(2, $phpResults);

        // Buscar por palabra clave en descripcion
        $apiResults = $this->service->searchTasks('API');
        $this->assertCount(1, $apiResults);
        $this->assertSame('Escribir documentacion', $apiResults[0]->title);
    }

    public function testSearchNoMatchesReturnsEmpty(): void
    {
        $this->service->createTask('Tarea de prueba', 'Descripcion simple', 'medium');

        $results = $this->service->searchTasks('JavaScript');

        $this->assertCount(0, $results);
        $this->assertIsArray($results);
    }

    public function testSearchIsCaseInsensitive(): void
    {
        $this->service->createTask('Estudiar PHP avanzado', '', 'high');

        // SQLite LIKE es case-insensitive por defecto para ASCII
        $results = $this->service->searchTasks('php');

        $this->assertCount(1, $results);
    }

    // ---------------------------------------------------------------
    //  Tests de estadisticas
    // ---------------------------------------------------------------

    public function testStatisticsReturnCorrectCounts(): void
    {
        // Crear tareas con distintas prioridades
        $this->service->createTask('Alta 1', '', 'high');
        $this->service->createTask('Alta 2', '', 'high');
        $this->service->createTask('Media 1', '', 'medium');
        $this->service->createTask('Baja 1', '', 'low');
        $this->service->createTask('Baja 2', '', 'low');

        // Completar algunas
        $this->service->completeTask(1); // Alta 1
        $this->service->completeTask(3); // Media 1

        $stats = $this->service->getStatistics();

        // Totales
        $this->assertSame(5, $stats['total']);
        $this->assertSame(2, $stats['completed']);
        $this->assertSame(3, $stats['pending']);

        // Por prioridad
        $this->assertSame(2, $stats['by_priority']['high']);
        $this->assertSame(1, $stats['by_priority']['medium']);
        $this->assertSame(2, $stats['by_priority']['low']);
    }

    public function testStatisticsWithEmptyDatabase(): void
    {
        $stats = $this->service->getStatistics();

        $this->assertSame(0, $stats['total']);
        $this->assertSame(0, $stats['completed']);
        $this->assertSame(0, $stats['pending']);
        $this->assertSame(0, $stats['by_priority']['high']);
        $this->assertSame(0, $stats['by_priority']['medium']);
        $this->assertSame(0, $stats['by_priority']['low']);
    }

    public function testStatisticsAllCompleted(): void
    {
        $this->service->createTask('T1', '', 'high');
        $this->service->createTask('T2', '', 'medium');
        $this->service->completeTask(1);
        $this->service->completeTask(2);

        $stats = $this->service->getStatistics();

        $this->assertSame(2, $stats['total']);
        $this->assertSame(2, $stats['completed']);
        $this->assertSame(0, $stats['pending']);
    }

    // ---------------------------------------------------------------
    //  Tests de filtrado por estado
    // ---------------------------------------------------------------

    public function testFilterByPendingStatus(): void
    {
        $this->service->createTask('Pendiente 1', '', 'high');
        $this->service->createTask('Pendiente 2', '', 'medium');
        $this->service->createTask('Sera completada', '', 'low');
        $this->service->completeTask(3);

        $pending = $this->service->listTasks('pending');

        $this->assertCount(2, $pending);
        foreach ($pending as $task) {
            $this->assertSame(Status::Pending, $task->status);
        }
    }

    public function testFilterByCompletedStatus(): void
    {
        $this->service->createTask('T1', '', 'high');
        $this->service->createTask('T2', '', 'medium');
        $this->service->createTask('T3', '', 'low');
        $this->service->completeTask(1);
        $this->service->completeTask(2);

        $completed = $this->service->listTasks('completed');

        $this->assertCount(2, $completed);
        foreach ($completed as $task) {
            $this->assertSame(Status::Completed, $task->status);
        }
    }

    public function testFilterAll(): void
    {
        $this->service->createTask('T1', '', 'high');
        $this->service->createTask('T2', '', 'medium');
        $this->service->completeTask(1);

        $all = $this->service->listTasks('all');

        $this->assertCount(2, $all);
    }

    public function testFilterAcceptsStatusVariants(): void
    {
        $this->service->createTask('Task 1', '', 'high');
        $this->service->createTask('Task 2', '', 'medium');

        // English filters should work
        $all = $this->service->listTasks('all');
        $this->assertCount(2, $all);

        $pending = $this->service->listTasks('pending');
        $this->assertCount(2, $pending);

        // Spanish aliases should now throw exceptions
        $this->expectException(\MiniProject\ValidationException::class);
        $this->expectExceptionMessage('Invalid status filter');

        $this->service->listTasks('pendientes');
    }

    // ---------------------------------------------------------------
    //  Tests de flujo con multiples operaciones
    // ---------------------------------------------------------------

    public function testCreateMultipleTasksAndDeleteSome(): void
    {
        $t1 = $this->service->createTask('Tarea 1', '', 'high');
        $t2 = $this->service->createTask('Tarea 2', '', 'medium');
        $t3 = $this->service->createTask('Tarea 3', '', 'low');

        // Eliminar la tarea del medio
        $this->service->deleteTask($t2->id);

        $all = $this->service->listTasks('all');
        $this->assertCount(2, $all);

        // Verificar que las tareas correctas sobrevivieron
        $ids = array_map(fn (Task $t) => $t->id, $all);
        $this->assertContains($t1->id, $ids);
        $this->assertContains($t3->id, $ids);
        $this->assertNotContains($t2->id, $ids);
    }

    public function testCompleteAndVerifyStatisticsChange(): void
    {
        $this->service->createTask('T1', '', 'high');
        $this->service->createTask('T2', '', 'medium');

        // Antes de completar
        $statsBefore = $this->service->getStatistics();
        $this->assertSame(0, $statsBefore['completed']);
        $this->assertSame(2, $statsBefore['pending']);

        // Completar una tarea
        $this->service->completeTask(1);

        // Despues de completar
        $statsAfter = $this->service->getStatistics();
        $this->assertSame(1, $statsAfter['completed']);
        $this->assertSame(1, $statsAfter['pending']);
    }

    public function testDeleteNonExistentTaskThrowsException(): void
    {
        $this->expectException(NotFoundException::class);

        $this->service->deleteTask(999);
    }

    public function testCompleteNonExistentTaskThrowsException(): void
    {
        $this->expectException(NotFoundException::class);

        $this->service->completeTask(999);
    }

    public function testNotFoundExceptionContainsResourceData(): void
    {
        try {
            $this->repository->findById(42);
            $this->fail('Deberia haber lanzado NotFoundException');
        } catch (NotFoundException $e) {
            $this->assertSame('task', $e->resource);
            $this->assertSame(42, $e->identifier);
            $this->assertStringContainsString('42', $e->getMessage());
        }
    }

    public function testCompleteWorkflowWithMultipleTasks(): void
    {
        // Crear varias tareas
        $shopping = $this->service->createTask('Comprar viveres', 'Lista del super', 'high');
        $studying = $this->service->createTask('Estudiar para examen', 'Capitulos 5 al 8', 'high');
        $cleaning = $this->service->createTask('Limpiar casa', 'Aspirar y trapear', 'medium');
        $exercise = $this->service->createTask('Hacer ejercicio', '30 min cardio', 'low');

        // Verificar que hay 4 tareas pendientes
        $this->assertCount(4, $this->service->listTasks('pending'));
        $this->assertCount(0, $this->service->listTasks('completed'));

        // Completar dos tareas
        $this->service->completeTask($shopping->id);
        $this->service->completeTask($cleaning->id);

        // Verificar filtrado
        $this->assertCount(2, $this->service->listTasks('pending'));
        $this->assertCount(2, $this->service->listTasks('completed'));
        $this->assertCount(4, $this->service->listTasks('all'));

        // Buscar tareas
        $results = $this->service->searchTasks('examen');
        $this->assertCount(1, $results);
        $this->assertSame('Estudiar para examen', $results[0]->title);

        // Estadisticas finales
        $stats = $this->service->getStatistics();
        $this->assertSame(4, $stats['total']);
        $this->assertSame(2, $stats['completed']);
        $this->assertSame(2, $stats['pending']);
        $this->assertSame(2, $stats['by_priority']['high']);
        $this->assertSame(1, $stats['by_priority']['medium']);
        $this->assertSame(1, $stats['by_priority']['low']);

        // Eliminar una tarea completada
        $this->service->deleteTask($shopping->id);

        // Verificar despues de eliminar
        $this->assertCount(3, $this->service->listTasks('all'));
        $statsPostDelete = $this->service->getStatistics();
        $this->assertSame(3, $statsPostDelete['total']);
        $this->assertSame(1, $statsPostDelete['completed']);
        $this->assertSame(1, $statsPostDelete['by_priority']['high']);
    }

    // ---------------------------------------------------------------
    //  Tests de edicion (updateTask)
    // ---------------------------------------------------------------

    public function testCompleteWorkflowWithEditing(): void
    {
        // 1. Crear tarea
        $task = $this->service->createTask(
            title: 'Titulo inicial',
            description: 'Descripcion inicial',
            priority: 'low',
        );

        $this->assertSame('Titulo inicial', $task->title);
        $this->assertSame('Descripcion inicial', $task->description);
        $this->assertSame(Priority::Low, $task->priority);

        // 2. Editar titulo
        $edited = $this->service->updateTask(
            id: $task->id,
            title: 'Titulo editado',
        );

        $this->assertSame('Titulo editado', $edited->title);
        $this->assertSame('Descripcion inicial', $edited->description);
        $this->assertSame(Priority::Low, $edited->priority);

        // 3. Editar multiples campos
        $edited2 = $this->service->updateTask(
            id: $task->id,
            description: 'Descripcion editada',
            priority: 'high',
        );

        $this->assertSame('Titulo editado', $edited2->title);
        $this->assertSame('Descripcion editada', $edited2->description);
        $this->assertSame(Priority::High, $edited2->priority);

        // 4. Verificar que la tarea persiste con los cambios
        $verified = $this->service->getTask($task->id);
        $this->assertSame('Titulo editado', $verified->title);
        $this->assertSame('Descripcion editada', $verified->description);
        $this->assertSame(Priority::High, $verified->priority);

        // 5. Completar la tarea editada
        $completed = $this->service->completeTask($task->id);
        $this->assertSame(Status::Completed, $completed->status);
    }

    public function testEditingWithDueDate(): void
    {
        // Crear tarea con fecha de vencimiento
        $task = $this->service->createTask(
            title: 'Tarea con deadline',
            description: '',
            priority: 'high',
            dueDate: '2026-06-15',
        );

        $this->assertSame('2026-06-15', $task->dueDate);

        // Cambiar la fecha de vencimiento
        $edited = $this->service->updateTask(
            id: $task->id,
            dueDate: '2026-07-01',
        );

        $this->assertSame('2026-07-01', $edited->dueDate);

        // Eliminar la fecha de vencimiento
        $withoutDate = $this->service->updateTask(
            id: $task->id,
            dueDate: '',
        );

        $this->assertNull($withoutDate->dueDate);
    }

    public function testGetTaskUsingService(): void
    {
        $creada = $this->service->createTask(
            title: 'Tarea obtenible',
            description: 'Para probar getTask',
            priority: 'medium',
        );

        $obtenida = $this->service->getTask($creada->id);

        $this->assertSame($creada->id, $obtenida->id);
        $this->assertSame('Tarea obtenible', $obtenida->title);
        $this->assertSame('Para probar getTask', $obtenida->description);
    }

    // ---------------------------------------------------------------
    //  Tests de paginacion en flujo de integracion
    // ---------------------------------------------------------------

    public function testPaginatedListing(): void
    {
        // Crear 5 tareas
        for ($i = 1; $i <= 5; $i++) {
            $this->service->createTask("Tarea {$i}", '', 'medium');
        }

        // Pagina 1 con 2 por pagina
        $page1 = $this->service->listTasks(
            filter: 'all',
            page: 1,
            perPage: 2,
        );

        $this->assertSame(5, $page1['total']);
        $this->assertSame(1, $page1['page']);
        $this->assertSame(2, $page1['per_page']);
        $this->assertSame(3, $page1['total_pages']);
        $this->assertCount(2, $page1['tasks']);

        // Pagina 3 con 2 por pagina (solo 1 resultado)
        $page3 = $this->service->listTasks(
            filter: 'all',
            page: 3,
            perPage: 2,
        );

        $this->assertCount(1, $page3['tasks']);
    }
}
