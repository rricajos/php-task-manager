<?php

declare(strict_types=1);

namespace Tests\Unit;

use MiniProject\JsonResponse;
use MiniProject\RouteMatch;
use MiniProject\Router;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitarios para el enrutador de la API REST.
 * Unit tests for the REST API router.
 *
 * @covers \MiniProject\Router
 * @covers \MiniProject\Route
 * @covers \MiniProject\RouteMatch
 */
class RouterTest extends TestCase
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
    //  Tests de registro y resolucion de rutas por metodo HTTP
    // ---------------------------------------------------------------

    public function testGetRouteMatches(): void
    {
        $router = new Router();
        $handler = fn () => 'get response';

        $router->get('/test', $handler);

        $match = $router->resolve('GET', '/test');

        $this->assertInstanceOf(RouteMatch::class, $match);
        $this->assertSame($handler, $match->handler);
        $this->assertEmpty($match->params);
    }

    public function testPostRouteMatches(): void
    {
        $router = new Router();
        $handler = fn () => 'post response';

        $router->post('/test', $handler);

        $match = $router->resolve('POST', '/test');

        $this->assertInstanceOf(RouteMatch::class, $match);
        $this->assertSame($handler, $match->handler);
    }

    public function testPatchRouteMatches(): void
    {
        $router = new Router();
        $handler = fn () => 'patch response';

        $router->patch('/test', $handler);

        $match = $router->resolve('PATCH', '/test');

        $this->assertInstanceOf(RouteMatch::class, $match);
        $this->assertSame($handler, $match->handler);
    }

    public function testDeleteRouteMatches(): void
    {
        $router = new Router();
        $handler = fn () => 'delete response';

        $router->delete('/test', $handler);

        $match = $router->resolve('DELETE', '/test');

        $this->assertInstanceOf(RouteMatch::class, $match);
        $this->assertSame($handler, $match->handler);
    }

    public function testPutRouteMatches(): void
    {
        $router = new Router();
        $handler = fn () => 'put response';

        $router->put('/test', $handler);

        $match = $router->resolve('PUT', '/test');

        $this->assertInstanceOf(RouteMatch::class, $match);
        $this->assertSame($handler, $match->handler);
    }

    // ---------------------------------------------------------------
    //  Tests de extraccion de parametros
    // ---------------------------------------------------------------

    public function testParameterExtraction(): void
    {
        $router = new Router();
        $handler = fn () => 'task detail';

        $router->get('/tasks/{id}', $handler);

        $match = $router->resolve('GET', '/tasks/42');

        $this->assertSame($handler, $match->handler);
        $this->assertArrayHasKey('id', $match->params);
        $this->assertSame('42', $match->params['id']);
    }

    public function testMultipleParameters(): void
    {
        $router = new Router();
        $handler = fn () => 'nested resource';

        $router->get('/users/{userId}/tasks/{taskId}', $handler);

        $match = $router->resolve('GET', '/users/5/tasks/99');

        $this->assertArrayHasKey('userId', $match->params);
        $this->assertArrayHasKey('taskId', $match->params);
        $this->assertSame('5', $match->params['userId']);
        $this->assertSame('99', $match->params['taskId']);
    }

    // ---------------------------------------------------------------
    //  Tests de normalizacion de URI
    // ---------------------------------------------------------------

    public function testTrailingSlashNormalized(): void
    {
        $router = new Router();
        $handler = fn () => 'tasks list';

        $router->get('/tasks', $handler);

        // URI con barra final debe coincidir
        $match = $router->resolve('GET', '/tasks/');

        $this->assertInstanceOf(RouteMatch::class, $match);
        $this->assertSame($handler, $match->handler);
    }

    public function testMethodIsCaseInsensitive(): void
    {
        $router = new Router();
        $handler = fn () => 'response';

        $router->get('/test', $handler);

        // Resolver con metodo en minusculas
        $match = $router->resolve('get', '/test');

        $this->assertInstanceOf(RouteMatch::class, $match);
        $this->assertSame($handler, $match->handler);
    }

    // ---------------------------------------------------------------
    //  Tests de interfaz fluida
    // ---------------------------------------------------------------

    public function testFluentInterface(): void
    {
        $router = new Router();
        $handler = fn () => 'response';

        $result = $router
            ->get('/a', $handler)
            ->post('/b', $handler)
            ->patch('/c', $handler)
            ->put('/d', $handler)
            ->delete('/e', $handler);

        $this->assertInstanceOf(Router::class, $result);
    }

    // ---------------------------------------------------------------
    //  Tests de orden de registro de rutas
    // ---------------------------------------------------------------

    public function testSpecificRouteBeforeParameterized(): void
    {
        $router = new Router();
        $searchHandler = fn () => 'search';
        $detailHandler = fn () => 'detail';

        // Registrar ruta especifica antes de la ruta parametrizada
        $router->get('/tasks/search', $searchHandler);
        $router->get('/tasks/{id}', $detailHandler);

        // /tasks/search debe coincidir con la ruta especifica, no la parametrizada
        $match = $router->resolve('GET', '/tasks/search');

        $this->assertSame($searchHandler, $match->handler);
        // No debe tener parametro 'id' ya que coincidio la ruta especifica
        $this->assertArrayNotHasKey('id', $match->params);
    }

    public function testParameterizedRouteMatchesNonSpecific(): void
    {
        $router = new Router();
        $searchHandler = fn () => 'search';
        $detailHandler = fn () => 'detail';

        $router->get('/tasks/search', $searchHandler);
        $router->get('/tasks/{id}', $detailHandler);

        // /tasks/42 debe coincidir con la ruta parametrizada
        $match = $router->resolve('GET', '/tasks/42');

        $this->assertSame($detailHandler, $match->handler);
        $this->assertSame('42', $match->params['id']);
    }

    // ---------------------------------------------------------------
    //  Tests de multiples rutas con diferentes metodos en el mismo patron
    // ---------------------------------------------------------------

    public function testSamePatternDifferentMethods(): void
    {
        $router = new Router();
        $getHandler = fn () => 'get tasks';
        $postHandler = fn () => 'create task';

        $router->get('/tasks', $getHandler);
        $router->post('/tasks', $postHandler);

        $getMatch = $router->resolve('GET', '/tasks');
        $this->assertSame($getHandler, $getMatch->handler);

        $postMatch = $router->resolve('POST', '/tasks');
        $this->assertSame($postHandler, $postMatch->handler);
    }

    // ---------------------------------------------------------------
    //  Tests de 404 y 405
    // ---------------------------------------------------------------

    public function testResolveNotFoundRoute(): void
    {
        $router = new Router();
        $router->get('/tasks', fn () => 'tasks');

        ob_start();

        try {
            $router->resolve('GET', '/nonexistent');
            $this->fail('Should have thrown RuntimeException for 404');
        } catch (\RuntimeException) {
            // Expected: JsonResponse::error throws when exit disabled
        }

        $output = ob_get_clean();
        $data = json_decode($output, true);

        $this->assertNotNull($data);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('Route not found', $data['message']);
    }

    public function testResolveMethodNotAllowed(): void
    {
        $router = new Router();
        $router->get('/tasks', fn () => 'tasks list');
        $router->post('/tasks', fn () => 'create task');

        ob_start();

        try {
            $router->resolve('DELETE', '/tasks');
            $this->fail('Should have thrown RuntimeException for 405');
        } catch (\RuntimeException) {
            // Expected
        }

        $output = ob_get_clean();
        $data = json_decode($output, true);

        $this->assertNotNull($data);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('not allowed', $data['message']);
    }
}
