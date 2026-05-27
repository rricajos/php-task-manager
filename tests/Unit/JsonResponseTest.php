<?php

declare(strict_types=1);

namespace Tests\Unit;

use MiniProject\JsonResponse;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitarios para JsonResponse.
 *
 * Usa JsonResponse::enableExit(false) para que en lugar de exit()
 * se lance una RuntimeException, permitiendo capturar la salida
 * sin necesidad de procesos separados.
 *
 * @covers \MiniProject\JsonResponse
 */
class JsonResponseTest extends TestCase
{
    protected function setUp(): void
    {
        JsonResponse::enableExit(false);
    }

    protected function tearDown(): void
    {
        JsonResponse::enableExit(true);
    }

    public function testSuccessResponse(): void
    {
        ob_start();

        try {
            JsonResponse::success(
                data: ['id' => 1, 'titulo' => 'Test'],
                code: 200,
                message: 'Operacion exitosa',
            );
        } catch (\RuntimeException) {
            // Expected: exit disabled for testing
        }

        $output = ob_get_clean();
        $data = json_decode($output, true);

        $this->assertNotNull($data, 'La respuesta debe ser JSON valido');
        $this->assertTrue($data['success']);
        $this->assertSame('Operacion exitosa', $data['message']);
        $this->assertIsArray($data['data']);
        $this->assertSame(1, $data['data']['id']);
    }

    public function testErrorResponse(): void
    {
        ob_start();

        try {
            JsonResponse::error(
                message: 'Recurso no encontrado',
                code: 404,
            );
        } catch (\RuntimeException) {
            // Expected: exit disabled for testing
        }

        $output = ob_get_clean();
        $data = json_decode($output, true);

        $this->assertNotNull($data, 'La respuesta debe ser JSON valido');
        $this->assertFalse($data['success']);
        $this->assertSame('Recurso no encontrado', $data['message']);
    }

    public function testPaginatedResponse(): void
    {
        ob_start();

        try {
            JsonResponse::paginated(
                data: [['id' => 1], ['id' => 2]],
                pagination: [
                    'page' => 1,
                    'per_page' => 20,
                    'total' => 2,
                    'total_pages' => 1,
                ],
            );
        } catch (\RuntimeException) {
            // Expected: exit disabled for testing
        }

        $output = ob_get_clean();
        $data = json_decode($output, true);

        $this->assertNotNull($data, 'La respuesta debe ser JSON valido');
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('pagination', $data);
        $this->assertCount(2, $data['data']);
        $this->assertSame(1, $data['pagination']['page']);
    }

    public function testErrorWithDetails(): void
    {
        ob_start();

        try {
            JsonResponse::error(
                message: 'Validacion fallida',
                code: 422,
                errors: [
                    'titulo' => 'El titulo es obligatorio',
                    'prioridad' => 'Prioridad invalida',
                ],
            );
        } catch (\RuntimeException) {
            // Expected: exit disabled for testing
        }

        $output = ob_get_clean();
        $data = json_decode($output, true);

        $this->assertNotNull($data, 'La respuesta debe ser JSON valido');
        $this->assertFalse($data['success']);
        $this->assertArrayHasKey('errors', $data);
        $this->assertArrayHasKey('titulo', $data['errors']);
        $this->assertArrayHasKey('prioridad', $data['errors']);
    }

    public function testSuccessWithCustomStatusCode(): void
    {
        ob_start();

        try {
            JsonResponse::success(
                data: ['id' => 1],
                code: 201,
                message: 'Recurso creado',
            );
        } catch (\RuntimeException) {
            // Expected: exit disabled for testing
        }

        $output = ob_get_clean();
        $data = json_decode($output, true);

        $this->assertNotNull($data, 'La respuesta debe ser JSON valido');
        $this->assertTrue($data['success']);
        $this->assertSame('Recurso creado', $data['message']);
    }

    public function testNoContentResponse(): void
    {
        ob_start();

        try {
            JsonResponse::noContent();
        } catch (\RuntimeException) {
            // Expected: exit disabled for testing
        }

        $output = ob_get_clean();

        // 204 No Content should have empty body
        $this->assertSame('', $output);
    }
}
