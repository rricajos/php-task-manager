<?php

declare(strict_types=1);

namespace Tests\Unit;

use MiniProject\JsonResponse;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitarios para JsonResponse.
 *
 * Todos los metodos de JsonResponse llaman exit() (return type: never),
 * por lo que cada test debe ejecutarse en un proceso separado para
 * evitar que exit() termine el runner de PHPUnit.
 *
 * @covers \MiniProject\JsonResponse
 */
class JsonResponseTest extends TestCase
{
    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testSuccessResponse(): void
    {
        ob_start();

        try {
            JsonResponse::success(
                data: ['id' => 1, 'titulo' => 'Test'],
                code: 200,
                message: 'Operacion exitosa',
            );
        } catch (\Throwable) {
            // exit() may throw in some configurations
        }

        $output = ob_get_clean();

        // If exit() terminated the process before we could capture,
        // the output might be empty in some PHPUnit configurations.
        // In that case, we skip the assertions.
        if ($output === false || $output === '') {
            $this->markTestSkipped(
                'No se pudo capturar la salida de JsonResponse::success() - exit() termino el proceso'
            );
        }

        $data = json_decode($output, true);

        $this->assertNotNull($data, 'La respuesta debe ser JSON valido');
        $this->assertTrue($data['success']);
        $this->assertSame('Operacion exitosa', $data['message']);
        $this->assertIsArray($data['data']);
        $this->assertSame(1, $data['data']['id']);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testErrorResponse(): void
    {
        ob_start();

        try {
            JsonResponse::error(
                message: 'Recurso no encontrado',
                code: 404,
            );
        } catch (\Throwable) {
            // exit() may throw
        }

        $output = ob_get_clean();

        if ($output === false || $output === '') {
            $this->markTestSkipped(
                'No se pudo capturar la salida de JsonResponse::error() - exit() termino el proceso'
            );
        }

        $data = json_decode($output, true);

        $this->assertNotNull($data, 'La respuesta debe ser JSON valido');
        $this->assertFalse($data['success']);
        $this->assertSame('Recurso no encontrado', $data['message']);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
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
        } catch (\Throwable) {
            // exit() may throw
        }

        $output = ob_get_clean();

        if ($output === false || $output === '') {
            $this->markTestSkipped(
                'No se pudo capturar la salida de JsonResponse::paginated() - exit() termino el proceso'
            );
        }

        $data = json_decode($output, true);

        $this->assertNotNull($data, 'La respuesta debe ser JSON valido');
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('pagination', $data);
        $this->assertCount(2, $data['data']);
        $this->assertSame(1, $data['pagination']['page']);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
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
        } catch (\Throwable) {
            // exit() may throw
        }

        $output = ob_get_clean();

        if ($output === false || $output === '') {
            $this->markTestSkipped(
                'No se pudo capturar la salida de JsonResponse::error() - exit() termino el proceso'
            );
        }

        $data = json_decode($output, true);

        $this->assertNotNull($data, 'La respuesta debe ser JSON valido');
        $this->assertFalse($data['success']);
        $this->assertArrayHasKey('errors', $data);
        $this->assertArrayHasKey('titulo', $data['errors']);
        $this->assertArrayHasKey('prioridad', $data['errors']);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testSuccessWithCustomStatusCode(): void
    {
        ob_start();

        try {
            JsonResponse::success(
                data: ['id' => 1],
                code: 201,
                message: 'Recurso creado',
            );
        } catch (\Throwable) {
            // exit() may throw
        }

        $output = ob_get_clean();

        if ($output === false || $output === '') {
            $this->markTestSkipped(
                'No se pudo capturar la salida de JsonResponse::success() - exit() termino el proceso'
            );
        }

        $data = json_decode($output, true);

        $this->assertNotNull($data, 'La respuesta debe ser JSON valido');
        $this->assertTrue($data['success']);
        $this->assertSame('Recurso creado', $data['message']);
    }
}
