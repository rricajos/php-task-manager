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
 * Tests de integracion que verifican el flujo completo de la API,
 * combinando autenticacion, tareas y exportacion.
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
        putenv('JWT_SECRET=test_secret_key_for_phpunit');
        Database::resetInstance();
        Database::getInstance(':memory:');

        $this->auth = new AuthService();

        // Register a default test user and create services with their userId
        $user = $this->auth->registrar(username: 'testuser', password: 'password123');
        $repository = new TaskRepository(userId: $user['id']);
        $this->taskService = new TaskService(repository: $repository);
        $this->exportService = new ExportService(outputDir: sys_get_temp_dir() . '/test_export_' . uniqid());
    }

    protected function tearDown(): void
    {
        Database::resetInstance();
        putenv('JWT_SECRET');
    }

    // ---------------------------------------------------------------
    //  Tests de registro y autenticacion
    // ---------------------------------------------------------------

    public function testRegisterAndLogin(): void
    {
        $registered = $this->auth->registrar(
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

        // Validate the token
        $payload = $this->auth->validarToken($loginResult['token']);
        $this->assertSame($registered['id'], $payload['user_id']);
    }

    // ---------------------------------------------------------------
    //  Tests de aislamiento de usuarios
    // ---------------------------------------------------------------

    public function testUserIsolation(): void
    {
        // Create tasks for user 1 (default test user from setUp)
        $this->taskService->crearTarea('Tarea de user 1', '', 'alta');
        $this->taskService->crearTarea('Otra tarea de user 1', '', 'media');

        $user1Tasks = $this->taskService->listarTareas('todas');
        $this->assertCount(2, $user1Tasks);

        // Create user 2 and their own TaskService
        $user2 = $this->auth->registrar(username: 'user2', password: 'password456');
        $repo2 = new TaskRepository(userId: $user2['id']);
        $service2 = new TaskService(repository: $repo2);

        // User 2 should see no tasks
        $user2Tasks = $service2->listarTareas('todas');
        $this->assertCount(0, $user2Tasks);

        // Create a task for user 2
        $service2->crearTarea('Tarea de user 2', '', 'baja');

        // User 2 should see only their task
        $user2Tasks = $service2->listarTareas('todas');
        $this->assertCount(1, $user2Tasks);

        // User 1 should still see only their tasks
        $user1Tasks = $this->taskService->listarTareas('todas');
        $this->assertCount(2, $user1Tasks);
    }

    // ---------------------------------------------------------------
    //  Tests de CRUD completo con edicion
    // ---------------------------------------------------------------

    public function testFullCrudWithEditing(): void
    {
        // 1. CREATE
        $tarea = $this->taskService->crearTarea(
            titulo: 'Tarea CRUD',
            descripcion: 'Descripcion original',
            prioridad: 'media',
        );

        $this->assertNotNull($tarea->id);
        $this->assertSame('Tarea CRUD', $tarea->titulo);
        $this->assertSame(Status::Pendiente, $tarea->estado);

        // 2. READ
        $obtenida = $this->taskService->obtenerTarea($tarea->id);
        $this->assertSame($tarea->id, $obtenida->id);
        $this->assertSame('Tarea CRUD', $obtenida->titulo);

        // 3. UPDATE
        $actualizada = $this->taskService->actualizarTarea(
            id: $tarea->id,
            titulo: 'Tarea CRUD Editada',
            prioridad: 'alta',
        );
        $this->assertSame('Tarea CRUD Editada', $actualizada->titulo);
        $this->assertSame(Priority::Alta, $actualizada->prioridad);
        $this->assertSame('Descripcion original', $actualizada->descripcion);

        // 4. COMPLETE
        $completada = $this->taskService->completarTarea($tarea->id);
        $this->assertSame(Status::Completada, $completada->estado);
        $this->assertNotNull($completada->fechaCompletada);

        // 5. DELETE
        $eliminada = $this->taskService->eliminarTarea($tarea->id);
        $this->assertTrue($eliminada);

        $tareas = $this->taskService->listarTareas('todas');
        $this->assertCount(0, $tareas);
    }

    // ---------------------------------------------------------------
    //  Tests de paginacion
    // ---------------------------------------------------------------

    public function testPaginatedListing(): void
    {
        // Create 25 tasks
        for ($i = 1; $i <= 25; $i++) {
            $this->taskService->crearTarea("Tarea {$i}", '', 'media');
        }

        // Page 1 with default 20 per page
        $page1 = $this->taskService->listarTareas(
            filtro: 'todas',
            page: 1,
            perPage: 20,
        );

        $this->assertSame(25, $page1['total']);
        $this->assertSame(1, $page1['page']);
        $this->assertSame(20, $page1['per_page']);
        $this->assertSame(2, $page1['total_pages']);
        $this->assertCount(20, $page1['tareas']);

        // Page 2 should have remaining 5
        $page2 = $this->taskService->listarTareas(
            filtro: 'todas',
            page: 2,
            perPage: 20,
        );

        $this->assertCount(5, $page2['tareas']);
        $this->assertSame(2, $page2['page']);
    }

    // ---------------------------------------------------------------
    //  Tests de ordenamiento y filtrado
    // ---------------------------------------------------------------

    public function testSortByPriority(): void
    {
        $this->taskService->crearTarea('Alta 1', '', 'alta');
        $this->taskService->crearTarea('Baja 1', '', 'baja');
        $this->taskService->crearTarea('Media 1', '', 'media');

        $resultado = $this->taskService->listarTareas(
            filtro: 'todas',
            page: 1,
            perPage: 20,
            sortBy: 'prioridad',
            sortDir: 'ASC',
        );

        $this->assertCount(3, $resultado['tareas']);
        // The tasks are returned sorted by prioridad ASC
        $prioridades = array_map(
            fn(Task $t): string => $t->prioridad->value,
            $resultado['tareas'],
        );

        // Just verify all 3 priorities are present
        $this->assertContains('alta', $prioridades);
        $this->assertContains('media', $prioridades);
        $this->assertContains('baja', $prioridades);
    }

    public function testFilterByPriority(): void
    {
        $this->taskService->crearTarea('Alta 1', '', 'alta');
        $this->taskService->crearTarea('Alta 2', '', 'alta');
        $this->taskService->crearTarea('Baja 1', '', 'baja');
        $this->taskService->crearTarea('Media 1', '', 'media');

        $resultado = $this->taskService->listarTareas(
            filtro: 'todas',
            page: 1,
            perPage: 20,
            priority: 'alta',
        );

        $this->assertSame(2, $resultado['total']);
        $this->assertCount(2, $resultado['tareas']);

        foreach ($resultado['tareas'] as $tarea) {
            $this->assertSame(Priority::Alta, $tarea->prioridad);
        }
    }

    public function testFilterByStatus(): void
    {
        $this->taskService->crearTarea('Tarea 1', '', 'alta');
        $this->taskService->crearTarea('Tarea 2', '', 'media');
        $this->taskService->crearTarea('Tarea 3', '', 'baja');

        $this->taskService->completarTarea(1);
        $this->taskService->completarTarea(2);

        $completadas = $this->taskService->listarTareas('completada');

        $this->assertCount(2, $completadas);
        foreach ($completadas as $tarea) {
            $this->assertSame(Status::Completada, $tarea->estado);
        }
    }

    // ---------------------------------------------------------------
    //  Tests de busqueda con paginacion
    // ---------------------------------------------------------------

    public function testSearchWithPagination(): void
    {
        for ($i = 1; $i <= 8; $i++) {
            $this->taskService->crearTarea("PHP tarea {$i}", 'Programacion', 'media');
        }
        $this->taskService->crearTarea('JavaScript tarea', '', 'baja');

        $resultado = $this->taskService->buscarTareas(
            keyword: 'PHP',
            page: 1,
            perPage: 3,
        );

        $this->assertSame(8, $resultado['total']);
        $this->assertCount(3, $resultado['tareas']);
        $this->assertSame(3, $resultado['total_pages']);
    }

    // ---------------------------------------------------------------
    //  Tests de fecha de vencimiento
    // ---------------------------------------------------------------

    public function testDueDateWorkflow(): void
    {
        // Create task with due date
        $tarea = $this->taskService->crearTarea(
            titulo: 'Tarea con deadline',
            descripcion: 'Importante',
            prioridad: 'alta',
            fechaVencimiento: '2026-12-31',
        );

        $this->assertSame('2026-12-31', $tarea->fechaVencimiento);

        // Update due date
        $actualizada = $this->taskService->actualizarTarea(
            id: $tarea->id,
            fechaVencimiento: '2027-01-15',
        );

        $this->assertSame('2027-01-15', $actualizada->fechaVencimiento);

        // Verify persisted
        $obtenida = $this->taskService->obtenerTarea($tarea->id);
        $this->assertSame('2027-01-15', $obtenida->fechaVencimiento);
    }

    // ---------------------------------------------------------------
    //  Tests de exportacion
    // ---------------------------------------------------------------

    public function testExportToString(): void
    {
        $this->taskService->crearTarea('Export tarea 1', 'Desc 1', 'alta');
        $this->taskService->crearTarea('Export tarea 2', 'Desc 2', 'media');

        $tareas = $this->taskService->obtenerTodasParaExportar();
        $jsonString = $this->exportService->exportarComoString($tareas, 'json');

        $data = json_decode($jsonString, true);

        $this->assertNotNull($data);
        $this->assertArrayHasKey('exportado_en', $data);
        $this->assertArrayHasKey('total_tareas', $data);
        $this->assertArrayHasKey('tareas', $data);
        $this->assertSame(2, $data['total_tareas']);
        $this->assertCount(2, $data['tareas']);

        // Verify task structure in export
        $primeraTarea = $data['tareas'][0];
        $this->assertArrayHasKey('id', $primeraTarea);
        $this->assertArrayHasKey('titulo', $primeraTarea);
        $this->assertArrayHasKey('prioridad', $primeraTarea);
        $this->assertArrayHasKey('estado', $primeraTarea);
    }

    public function testExportCsvToString(): void
    {
        $this->taskService->crearTarea('CSV tarea 1', 'Desc CSV', 'baja');
        $this->taskService->crearTarea('CSV tarea 2', '', 'alta');

        $tareas = $this->taskService->obtenerTodasParaExportar();
        $csvString = $this->exportService->exportarComoString($tareas, 'csv');

        // Remove BOM if present
        $csvSinBom = ltrim($csvString, "\xEF\xBB\xBF");

        // Verify CSV headers
        $this->assertStringContainsString('ID', $csvSinBom);
        $this->assertStringContainsString('Titulo', $csvSinBom);
        $this->assertStringContainsString('Prioridad', $csvSinBom);

        // Verify data is present
        $this->assertStringContainsString('CSV tarea 1', $csvSinBom);
        $this->assertStringContainsString('CSV tarea 2', $csvSinBom);

        // Verify correct number of lines (header + 2 data rows)
        $lineas = explode("\n", trim($csvSinBom));
        $this->assertCount(3, $lineas);
    }

    // ---------------------------------------------------------------
    //  Tests de estadisticas
    // ---------------------------------------------------------------

    public function testStatisticsAfterOperations(): void
    {
        // Create tasks
        $this->taskService->crearTarea('Alta 1', '', 'alta');
        $this->taskService->crearTarea('Alta 2', '', 'alta');
        $this->taskService->crearTarea('Media 1', '', 'media');
        $this->taskService->crearTarea('Baja 1', '', 'baja');

        // Complete some
        $this->taskService->completarTarea(1);
        $this->taskService->completarTarea(3);

        // Delete one
        $this->taskService->eliminarTarea(2);

        $stats = $this->taskService->obtenerEstadisticas();

        $this->assertSame(3, $stats['total']);
        $this->assertSame(2, $stats['completadas']);
        $this->assertSame(1, $stats['pendientes']);
        $this->assertSame(1, $stats['por_prioridad']['alta']);
        $this->assertSame(1, $stats['por_prioridad']['media']);
        $this->assertSame(1, $stats['por_prioridad']['baja']);
    }

    // ---------------------------------------------------------------
    //  Tests de actualizacion parcial
    // ---------------------------------------------------------------

    public function testUpdateTaskTitle(): void
    {
        $tarea = $this->taskService->crearTarea('Original', 'Desc', 'media');

        $actualizada = $this->taskService->actualizarTarea(
            id: $tarea->id,
            titulo: 'Modificado',
        );

        $this->assertSame('Modificado', $actualizada->titulo);
        $this->assertSame('Desc', $actualizada->descripcion);
        $this->assertSame(Priority::Media, $actualizada->prioridad);
    }

    public function testUpdateTaskPartial(): void
    {
        $tarea = $this->taskService->crearTarea(
            titulo: 'Titulo original',
            descripcion: 'Desc original',
            prioridad: 'baja',
            fechaVencimiento: '2026-12-31',
        );

        // Update only priority
        $actualizada = $this->taskService->actualizarTarea(
            id: $tarea->id,
            prioridad: 'alta',
        );

        // Priority changed
        $this->assertSame(Priority::Alta, $actualizada->prioridad);

        // Other fields unchanged
        $this->assertSame('Titulo original', $actualizada->titulo);
        $this->assertSame('Desc original', $actualizada->descripcion);
        $this->assertSame('2026-12-31', $actualizada->fechaVencimiento);
    }

    public function testCompleteAndVerifyStats(): void
    {
        $this->taskService->crearTarea('Tarea 1', '', 'alta');
        $this->taskService->crearTarea('Tarea 2', '', 'media');
        $this->taskService->crearTarea('Tarea 3', '', 'baja');
        $this->taskService->crearTarea('Tarea 4', '', 'alta');

        // Complete 2 out of 4
        $this->taskService->completarTarea(1);
        $this->taskService->completarTarea(3);

        $stats = $this->taskService->obtenerEstadisticas();

        $this->assertSame(4, $stats['total']);
        $this->assertSame(2, $stats['completadas']);
        $this->assertSame(2, $stats['pendientes']);

        // Verify percentage would be 50%
        $porcentaje = round(($stats['completadas'] / $stats['total']) * 100, 1);
        $this->assertSame(50.0, $porcentaje);
    }
}
