<?php

declare(strict_types=1);

namespace Tests\Integration;

use MiniProject\AuthService;
use MiniProject\Database;
use MiniProject\ExportService;
use MiniProject\Priority;
use MiniProject\Status;
use MiniProject\Task;
use MiniProject\TaskRepository;
use MiniProject\TaskService;
use PHPUnit\Framework\TestCase;

/**
 * Tests de integración que verifican el flujo completo de la API.
 * Integration tests verifying the full API workflow.
 *
 * @covers \MiniProject\AuthService
 * @covers \MiniProject\TaskService
 * @covers \MiniProject\TaskRepository
 * @covers \MiniProject\ExportService
 * @covers \MiniProject\Database
 */
class ApiWorkflowTest extends TestCase
{
    private AuthService $auth;
    private TaskService $taskService;
    private ExportService $exportService;

    protected function setUp(): void
    {
        Database::resetInstance();
        Database::getInstance(':memory:');

        $this->auth = new AuthService(jwtSecret: 'test_secret_key_for_phpunit');

        // Register a default test user and create services with their userId
        $user = $this->auth->register(username: 'testuser', password: 'password123');
        $repository = new TaskRepository(userId: $user['id']);
        $this->taskService = new TaskService(repository: $repository);
        $this->exportService = new ExportService(outputDir: sys_get_temp_dir() . '/test_export_' . uniqid());
    }

    protected function tearDown(): void
    {
        Database::resetInstance();
    }

    // ---------------------------------------------------------------
    //  Tests de registro y autenticacion
    // ---------------------------------------------------------------

    public function testRegisterAndLogin(): void
    {
        $registered = $this->auth->register(
            username: 'newuser',
            password: 'secret123',
        );

        $this->assertArrayHasKey('id', $registered);
        $this->assertSame('newuser', $registered['username']);

        $loginResult = $this->auth->login(
            username: 'newuser',
            password: 'secret123',
        );

        $this->assertArrayHasKey('token', $loginResult);
        $this->assertSame('Bearer', $loginResult['type']);
        $this->assertIsInt($loginResult['expires_in']);
        $this->assertSame('newuser', $loginResult['user']['username']);

        // Validar el token
        $payload = $this->auth->validateToken($loginResult['token']);
        $this->assertSame($registered['id'], $payload['user_id']);
    }

    // ---------------------------------------------------------------
    //  Tests de aislamiento de usuarios
    // ---------------------------------------------------------------

    public function testUserIsolation(): void
    {
        // Crear tareas para el usuario 1 (usuario de prueba por defecto del setUp)
        $this->taskService->createTask('Tarea de user 1', '', 'high');
        $this->taskService->createTask('Otra tarea de user 1', '', 'medium');

        $user1Tasks = $this->taskService->listTasks('all');
        $this->assertCount(2, $user1Tasks);

        // Crear usuario 2 y su propio TaskService
        $user2 = $this->auth->register(username: 'user2', password: 'password456');
        $repo2 = new TaskRepository(userId: $user2['id']);
        $service2 = new TaskService(repository: $repo2);

        // El usuario 2 no debe ver tareas
        $user2Tasks = $service2->listTasks('all');
        $this->assertCount(0, $user2Tasks);

        // Crear una tarea para el usuario 2
        $service2->createTask('Tarea de user 2', '', 'low');

        // El usuario 2 solo debe ver su tarea
        $user2Tasks = $service2->listTasks('all');
        $this->assertCount(1, $user2Tasks);

        // El usuario 1 debe seguir viendo solo sus tareas
        $user1Tasks = $this->taskService->listTasks('all');
        $this->assertCount(2, $user1Tasks);
    }

    // ---------------------------------------------------------------
    //  Tests de CRUD completo con edicion
    // ---------------------------------------------------------------

    public function testFullCrudWithEditing(): void
    {
        // 1. CREAR
        $task = $this->taskService->createTask(
            title: 'Tarea CRUD',
            description: 'Descripcion original',
            priority: 'medium',
        );

        $this->assertNotNull($task->id);
        $this->assertSame('Tarea CRUD', $task->title);
        $this->assertSame(Status::Pending, $task->status);

        // 2. LEER
        $retrieved = $this->taskService->getTask($task->id);
        $this->assertSame($task->id, $retrieved->id);
        $this->assertSame('Tarea CRUD', $retrieved->title);

        // 3. ACTUALIZAR
        $updated = $this->taskService->updateTask(
            id: $task->id,
            title: 'Tarea CRUD Editada',
            priority: 'high',
        );
        $this->assertSame('Tarea CRUD Editada', $updated->title);
        $this->assertSame(Priority::High, $updated->priority);
        $this->assertSame('Descripcion original', $updated->description);

        // 4. COMPLETAR
        $completed = $this->taskService->completeTask($task->id);
        $this->assertSame(Status::Completed, $completed->status);
        $this->assertNotNull($completed->completedAt);

        // 5. ELIMINAR
        $deleted = $this->taskService->deleteTask($task->id);
        $this->assertTrue($deleted);

        $tasks = $this->taskService->listTasks('all');
        $this->assertCount(0, $tasks);
    }

    // ---------------------------------------------------------------
    //  Tests de paginacion
    // ---------------------------------------------------------------

    public function testPaginatedListing(): void
    {
        // Crear 25 tareas
        for ($i = 1; $i <= 25; $i++) {
            $this->taskService->createTask("Tarea {$i}", '', 'medium');
        }

        // Pagina 1 con 20 por pagina por defecto
        $page1 = $this->taskService->listTasks(
            filter: 'all',
            page: 1,
            perPage: 20,
        );

        $this->assertSame(25, $page1['total']);
        $this->assertSame(1, $page1['page']);
        $this->assertSame(20, $page1['per_page']);
        $this->assertSame(2, $page1['total_pages']);
        $this->assertCount(20, $page1['tasks']);

        // Pagina 2 debe tener las 5 restantes
        $page2 = $this->taskService->listTasks(
            filter: 'all',
            page: 2,
            perPage: 20,
        );

        $this->assertCount(5, $page2['tasks']);
        $this->assertSame(2, $page2['page']);
    }

    // ---------------------------------------------------------------
    //  Tests de ordenamiento y filtrado
    // ---------------------------------------------------------------

    public function testSortByPriority(): void
    {
        $this->taskService->createTask('Alta 1', '', 'high');
        $this->taskService->createTask('Baja 1', '', 'low');
        $this->taskService->createTask('Media 1', '', 'medium');

        $result = $this->taskService->listTasks(
            filter: 'all',
            page: 1,
            perPage: 20,
            sortBy: 'priority',
            sortDir: 'ASC',
        );

        $this->assertCount(3, $result['tasks']);
        // Las tareas se retornan ordenadas por prioridad ASC
        $priorities = array_map(
            fn (Task $t): string => $t->priority->value,
            $result['tasks'],
        );

        // Verificar que las 3 prioridades estan presentes
        $this->assertContains('high', $priorities);
        $this->assertContains('medium', $priorities);
        $this->assertContains('low', $priorities);
    }

    public function testFilterByPriority(): void
    {
        $this->taskService->createTask('Alta 1', '', 'high');
        $this->taskService->createTask('Alta 2', '', 'high');
        $this->taskService->createTask('Baja 1', '', 'low');
        $this->taskService->createTask('Media 1', '', 'medium');

        $result = $this->taskService->listTasks(
            filter: 'all',
            page: 1,
            perPage: 20,
            priority: 'high',
        );

        $this->assertSame(2, $result['total']);
        $this->assertCount(2, $result['tasks']);

        foreach ($result['tasks'] as $task) {
            $this->assertSame(Priority::High, $task->priority);
        }
    }

    public function testFilterByStatus(): void
    {
        $this->taskService->createTask('Tarea 1', '', 'high');
        $this->taskService->createTask('Tarea 2', '', 'medium');
        $this->taskService->createTask('Tarea 3', '', 'low');

        $this->taskService->completeTask(1);
        $this->taskService->completeTask(2);

        $completed = $this->taskService->listTasks('completed');

        $this->assertCount(2, $completed);
        foreach ($completed as $task) {
            $this->assertSame(Status::Completed, $task->status);
        }
    }

    // ---------------------------------------------------------------
    //  Tests de busqueda con paginacion
    // ---------------------------------------------------------------

    public function testSearchWithPagination(): void
    {
        for ($i = 1; $i <= 8; $i++) {
            $this->taskService->createTask("PHP tarea {$i}", 'Programacion', 'medium');
        }
        $this->taskService->createTask('JavaScript tarea', '', 'low');

        $result = $this->taskService->searchTasks(
            keyword: 'PHP',
            page: 1,
            perPage: 3,
        );

        $this->assertSame(8, $result['total']);
        $this->assertCount(3, $result['tasks']);
        $this->assertSame(3, $result['total_pages']);
    }

    // ---------------------------------------------------------------
    //  Tests de fecha de vencimiento
    // ---------------------------------------------------------------

    public function testDueDateWorkflow(): void
    {
        // Crear tarea con fecha de vencimiento
        $task = $this->taskService->createTask(
            title: 'Tarea con deadline',
            description: 'Importante',
            priority: 'high',
            dueDate: '2026-12-31',
        );

        $this->assertSame('2026-12-31', $task->dueDate);

        // Actualizar fecha de vencimiento
        $updated = $this->taskService->updateTask(
            id: $task->id,
            dueDate: '2027-01-15',
        );

        $this->assertSame('2027-01-15', $updated->dueDate);

        // Verificar persistencia
        $retrieved = $this->taskService->getTask($task->id);
        $this->assertSame('2027-01-15', $retrieved->dueDate);
    }

    // ---------------------------------------------------------------
    //  Tests de exportacion
    // ---------------------------------------------------------------

    public function testExportToString(): void
    {
        $this->taskService->createTask('Export tarea 1', 'Desc 1', 'high');
        $this->taskService->createTask('Export tarea 2', 'Desc 2', 'medium');

        $tasks = $this->taskService->getAllForExport();
        $jsonString = $this->exportService->exportAsString($tasks, 'json');

        $data = json_decode($jsonString, true);

        $this->assertNotNull($data);
        $this->assertArrayHasKey('exported_at', $data);
        $this->assertArrayHasKey('total_tasks', $data);
        $this->assertArrayHasKey('tasks', $data);
        $this->assertSame(2, $data['total_tasks']);
        $this->assertCount(2, $data['tasks']);

        // Verificar estructura de la tarea en exportacion
        $firstTask = $data['tasks'][0];
        $this->assertArrayHasKey('id', $firstTask);
        $this->assertArrayHasKey('title', $firstTask);
        $this->assertArrayHasKey('priority', $firstTask);
        $this->assertArrayHasKey('status', $firstTask);
    }

    public function testExportCsvToString(): void
    {
        $this->taskService->createTask('CSV tarea 1', 'Desc CSV', 'low');
        $this->taskService->createTask('CSV tarea 2', '', 'high');

        $tasks = $this->taskService->getAllForExport();
        $csvString = $this->exportService->exportAsString($tasks, 'csv');

        // Eliminar BOM si esta presente
        $csvSinBom = ltrim($csvString, "\xEF\xBB\xBF");

        // Verificar cabeceras CSV
        $this->assertStringContainsString('ID', $csvSinBom);
        $this->assertStringContainsString('Title', $csvSinBom);
        $this->assertStringContainsString('Priority', $csvSinBom);

        // Verificar que los datos estan presentes
        $this->assertStringContainsString('CSV tarea 1', $csvSinBom);
        $this->assertStringContainsString('CSV tarea 2', $csvSinBom);

        // Verificar numero correcto de lineas (cabecera + 2 filas de datos)
        $lines = explode("\n", trim($csvSinBom));
        $this->assertCount(3, $lines);
    }

    // ---------------------------------------------------------------
    //  Tests de estadisticas
    // ---------------------------------------------------------------

    public function testStatisticsAfterOperations(): void
    {
        // Crear tareas
        $this->taskService->createTask('Alta 1', '', 'high');
        $this->taskService->createTask('Alta 2', '', 'high');
        $this->taskService->createTask('Media 1', '', 'medium');
        $this->taskService->createTask('Baja 1', '', 'low');

        // Completar algunas
        $this->taskService->completeTask(1);
        $this->taskService->completeTask(3);

        // Eliminar una
        $this->taskService->deleteTask(2);

        $stats = $this->taskService->getStatistics();

        $this->assertSame(3, $stats['total']);
        $this->assertSame(2, $stats['completed']);
        $this->assertSame(1, $stats['pending']);
        $this->assertSame(1, $stats['by_priority']['high']);
        $this->assertSame(1, $stats['by_priority']['medium']);
        $this->assertSame(1, $stats['by_priority']['low']);
    }

    // ---------------------------------------------------------------
    //  Tests de actualizacion parcial
    // ---------------------------------------------------------------

    public function testUpdateTaskTitle(): void
    {
        $task = $this->taskService->createTask('Original', 'Desc', 'medium');

        $updated = $this->taskService->updateTask(
            id: $task->id,
            title: 'Modificado',
        );

        $this->assertSame('Modificado', $updated->title);
        $this->assertSame('Desc', $updated->description);
        $this->assertSame(Priority::Medium, $updated->priority);
    }

    public function testUpdateTaskPartial(): void
    {
        $task = $this->taskService->createTask(
            title: 'Titulo original',
            description: 'Desc original',
            priority: 'low',
            dueDate: '2026-12-31',
        );

        // Actualizar solo la prioridad
        $updated = $this->taskService->updateTask(
            id: $task->id,
            priority: 'high',
        );

        // La prioridad cambio
        $this->assertSame(Priority::High, $updated->priority);

        // Los demas campos no cambian
        $this->assertSame('Titulo original', $updated->title);
        $this->assertSame('Desc original', $updated->description);
        $this->assertSame('2026-12-31', $updated->dueDate);
    }

    public function testCompleteAndVerifyStats(): void
    {
        $this->taskService->createTask('Tarea 1', '', 'high');
        $this->taskService->createTask('Tarea 2', '', 'medium');
        $this->taskService->createTask('Tarea 3', '', 'low');
        $this->taskService->createTask('Tarea 4', '', 'high');

        // Completar 2 de 4
        $this->taskService->completeTask(1);
        $this->taskService->completeTask(3);

        $stats = $this->taskService->getStatistics();

        $this->assertSame(4, $stats['total']);
        $this->assertSame(2, $stats['completed']);
        $this->assertSame(2, $stats['pending']);

        // Verificar que el porcentaje seria 50%
        $percentage = round(($stats['completed'] / $stats['total']) * 100, 1);
        $this->assertSame(50.0, $percentage);
    }
}
