<?php

declare(strict_types=1);

namespace Tests\Unit;

use MiniProject\ApiController;
use MiniProject\AuthService;
use MiniProject\Database;
use MiniProject\ExportService;
use MiniProject\JsonResponse;
use MiniProject\Middleware;
use MiniProject\TaskRepository;
use MiniProject\TaskService;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitarios para el middleware HTTP de la API REST.
 * Unit tests for the REST API HTTP middleware.
 *
 * @covers \MiniProject\Middleware
 */
class MiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        JsonResponse::enableExit(false);
    }

    protected function tearDown(): void
    {
        JsonResponse::enableExit(true);
    }

    // ---------------------------------------------------------------
    //  Test: jsonBody with valid JSON
    // ---------------------------------------------------------------

    public function testJsonBodyWithValidJson(): void
    {
        $result = Middleware::jsonBody('{"title":"Test","priority":"high"}');

        $this->assertIsArray($result);
        $this->assertSame('Test', $result['title']);
        $this->assertSame('high', $result['priority']);
    }

    // ---------------------------------------------------------------
    //  Test: jsonBody with empty input
    // ---------------------------------------------------------------

    public function testJsonBodyWithEmptyInput(): void
    {
        $result = Middleware::jsonBody('');

        $this->assertSame([], $result);
    }

    // ---------------------------------------------------------------
    //  Test: jsonBody with null input returns empty
    // ---------------------------------------------------------------

    public function testJsonBodyWithNullInputReturnsEmpty(): void
    {
        $result = Middleware::jsonBody(null);

        $this->assertSame([], $result);
    }

    // ---------------------------------------------------------------
    //  Test: jsonBody with invalid JSON returns error
    // ---------------------------------------------------------------

    public function testJsonBodyWithInvalidJsonReturnsError(): void
    {
        ob_start();
        try {
            Middleware::jsonBody('not json');
        } catch (\RuntimeException) {
            // Expected: JsonResponse throws RuntimeException when exit is disabled
        }
        $output = ob_get_clean();

        $data = json_decode($output, true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('valid JSON', $data['message']);
    }

    // ---------------------------------------------------------------
    //  Test: jsonBody with JSON string rejects non-array
    // ---------------------------------------------------------------

    public function testJsonBodyWithJsonStringRejectsNonArray(): void
    {
        ob_start();
        try {
            Middleware::jsonBody('"just a string"');
        } catch (\RuntimeException) {
            // Expected: JsonResponse throws RuntimeException when exit is disabled
        }
        $output = ob_get_clean();

        $data = json_decode($output, true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('valid JSON', $data['message']);
    }

    // ---------------------------------------------------------------
    //  Test: jsonBody with JSON array accepted
    // ---------------------------------------------------------------

    public function testJsonBodyWithJsonArrayAccepted(): void
    {
        $result = Middleware::jsonBody('[1,2,3]');

        $this->assertSame([1, 2, 3], $result);
    }

    // ---------------------------------------------------------------
    //  Test: requireAuth with valid token
    // ---------------------------------------------------------------

    public function testRequireAuthWithValidToken(): void
    {
        Database::resetInstance();
        Database::getInstance(':memory:');

        $authService = new AuthService(jwtSecret: 'test_middleware_secret');
        $authService->register(username: 'mwuser', password: 'password123');
        $loginResult = $authService->login(username: 'mwuser', password: 'password123');
        $token = $loginResult['token'];

        $repository = new TaskRepository(userId: 1);
        $taskService = new TaskService(repository: $repository);
        $exportService = new ExportService(outputDir: sys_get_temp_dir());
        $controller = new ApiController(
            taskService: $taskService,
            exportService: $exportService,
            authService: $authService,
        );

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;

        $result = Middleware::requireAuth($authService, $controller);

        $this->assertArrayHasKey('user_id', $result);
        $this->assertSame('mwuser', $result['username']);

        unset($_SERVER['HTTP_AUTHORIZATION']);
        Database::resetInstance();
    }

    // ---------------------------------------------------------------
    //  Test: requireAuth with missing token returns 401
    // ---------------------------------------------------------------

    public function testRequireAuthWithMissingTokenReturns401(): void
    {
        Database::resetInstance();
        Database::getInstance(':memory:');

        $authService = new AuthService(jwtSecret: 'test_middleware_secret');
        $repository = new TaskRepository(userId: 1);
        $taskService = new TaskService(repository: $repository);
        $exportService = new ExportService(outputDir: sys_get_temp_dir());
        $controller = new ApiController(
            taskService: $taskService,
            exportService: $exportService,
            authService: $authService,
        );

        // Do NOT set HTTP_AUTHORIZATION
        unset($_SERVER['HTTP_AUTHORIZATION']);

        ob_start();
        try {
            Middleware::requireAuth($authService, $controller);
        } catch (\RuntimeException) {
            // Expected: JsonResponse throws RuntimeException when exit is disabled
        }
        $output = ob_get_clean();

        $data = json_decode($output, true);
        $this->assertFalse($data['success']);
        $this->assertMatchesRegularExpression('/token/i', $data['message']);

        Database::resetInstance();
    }
}
