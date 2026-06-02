<?php

declare(strict_types=1);

namespace Tests\Unit;

use MiniProject\CorsMiddleware;
use MiniProject\JsonResponse;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitarios para el middleware CORS.
 * Unit tests for the CORS middleware.
 *
 * @covers \MiniProject\CorsMiddleware
 */
class CorsMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        JsonResponse::enableExit(enabled: false);
    }

    protected function tearDown(): void
    {
        JsonResponse::enableExit(enabled: true);
        putenv('CORS_ORIGIN');
    }

    // ---------------------------------------------------------------
    //  Tests de constructor y origen
    // ---------------------------------------------------------------

    public function testDefaultOriginIsWildcard(): void
    {
        $cors = new CorsMiddleware();

        $this->assertSame('*', $cors->getAllowedOrigin());
    }

    public function testCustomOriginViaConstructor(): void
    {
        $cors = new CorsMiddleware(origin: 'https://example.com');

        $this->assertSame('https://example.com', $cors->getAllowedOrigin());
    }

    public function testOriginFromEnvironmentVariable(): void
    {
        putenv('CORS_ORIGIN=https://env-origin.com');

        $cors = new CorsMiddleware();

        $this->assertSame('https://env-origin.com', $cors->getAllowedOrigin());
    }

    public function testInjectedOriginTakesPrecedenceOverEnv(): void
    {
        putenv('CORS_ORIGIN=https://env-origin.com');

        $cors = new CorsMiddleware(origin: 'https://injected.com');

        $this->assertSame('https://injected.com', $cors->getAllowedOrigin());
    }

    // ---------------------------------------------------------------
    //  Tests de handle()
    // ---------------------------------------------------------------

    public function testHandleContinuesForNonOptionsRequest(): void
    {
        $cors = new CorsMiddleware(origin: 'https://example.com');

        // Non-OPTIONS request should NOT exit — we reach the assertion
        $cors->handle(requestMethod: 'GET');

        $this->assertTrue(true);
    }

    public function testHandleResponds204ForOptionsRequest(): void
    {
        $cors = new CorsMiddleware(origin: 'https://example.com');

        $this->expectException(\RuntimeException::class);

        $cors->handle(requestMethod: 'OPTIONS');
    }

    public function testHandleIsCaseInsensitiveForOptions(): void
    {
        $cors = new CorsMiddleware();

        $this->expectException(\RuntimeException::class);

        $cors->handle(requestMethod: 'options');
    }
}
