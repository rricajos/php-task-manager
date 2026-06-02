<?php

declare(strict_types=1);

namespace Tests\Unit;

use MiniProject\Database;
use MiniProject\JsonResponse;
use MiniProject\Middleware;
use MiniProject\RateLimiter;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitarios para el limitador de tasa basado en SQLite.
 * Unit tests for the SQLite-based rate limiter.
 *
 * @covers \MiniProject\RateLimiter
 * @covers \MiniProject\Middleware
 */
class RateLimiterTest extends TestCase
{
    private \PDO $pdo;

    protected function setUp(): void
    {
        Database::resetInstance();
        Database::getInstance(':memory:');
        $this->pdo = Database::getInstance()->getConnection();
        JsonResponse::enableExit(enabled: false);
    }

    protected function tearDown(): void
    {
        JsonResponse::enableExit(enabled: true);
        Database::resetInstance();
    }

    // ---------------------------------------------------------------
    //  Tests de tabla rate_limits
    // ---------------------------------------------------------------

    public function testRateLimitsTableExists(): void
    {
        $stmt = $this->pdo->query(
            "SELECT name FROM sqlite_master WHERE type='table' AND name='rate_limits'"
        );
        $result = $stmt->fetch();

        $this->assertNotFalse($result);
        $this->assertSame('rate_limits', $result['name']);
    }

    // ---------------------------------------------------------------
    //  Tests de check()
    // ---------------------------------------------------------------

    public function testFirstRequestIsAllowed(): void
    {
        $limiter = new RateLimiter(pdo: $this->pdo);

        $result = $limiter->check(key: 'test:ip1', maxRequests: 5, windowSeconds: 60);

        $this->assertTrue($result);
    }

    public function testRemainingDecreasesWithEachRequest(): void
    {
        $limiter = new RateLimiter(pdo: $this->pdo);

        $limiter->check(key: 'test:ip2', maxRequests: 5, windowSeconds: 60);
        $this->assertSame(4, $limiter->getRemainingRequests());

        $limiter->check(key: 'test:ip2', maxRequests: 5, windowSeconds: 60);
        $this->assertSame(3, $limiter->getRemainingRequests());
    }

    public function testRequestBlockedWhenLimitReached(): void
    {
        $limiter = new RateLimiter(pdo: $this->pdo);

        // Use all 3 allowed requests
        for ($i = 0; $i < 3; $i++) {
            $this->assertTrue(
                $limiter->check(key: 'test:ip3', maxRequests: 3, windowSeconds: 60)
            );
        }

        // 4th request should be blocked
        $this->assertFalse(
            $limiter->check(key: 'test:ip3', maxRequests: 3, windowSeconds: 60)
        );
        $this->assertSame(0, $limiter->getRemainingRequests());
    }

    public function testDifferentKeysTrackedIndependently(): void
    {
        $limiter = new RateLimiter(pdo: $this->pdo);

        // Exhaust limit for key A
        $limiter->check(key: 'test:keyA', maxRequests: 1, windowSeconds: 60);
        $this->assertFalse(
            $limiter->check(key: 'test:keyA', maxRequests: 1, windowSeconds: 60)
        );

        // Key B should still be allowed
        $this->assertTrue(
            $limiter->check(key: 'test:keyB', maxRequests: 1, windowSeconds: 60)
        );
    }

    // ---------------------------------------------------------------
    //  Tests de cleanup()
    // ---------------------------------------------------------------

    public function testExpiredEntriesAreCleaned(): void
    {
        $limiter = new RateLimiter(pdo: $this->pdo);

        // Insert an old entry manually (1 hour ago)
        $oldTimestamp = microtime(true) - 3700;
        $stmt = $this->pdo->prepare(
            'INSERT INTO rate_limits (rate_key, timestamp) VALUES (:key, :ts)'
        );
        $stmt->execute([':key' => 'test:old', ':ts' => $oldTimestamp]);

        // Cleanup should remove it
        $deleted = $limiter->cleanup(olderThanSeconds: 3600);

        $this->assertSame(1, $deleted);
    }

    // ---------------------------------------------------------------
    //  Tests de getRetryAfter() y getResetAt()
    // ---------------------------------------------------------------

    public function testGetRetryAfterReturnsPositiveWhenBlocked(): void
    {
        $limiter = new RateLimiter(pdo: $this->pdo);

        $limiter->check(key: 'test:retry', maxRequests: 1, windowSeconds: 60);
        $limiter->check(key: 'test:retry', maxRequests: 1, windowSeconds: 60);

        $this->assertGreaterThan(0, $limiter->getRetryAfter());
    }

    public function testGetResetAtReturnsFutureTimestamp(): void
    {
        $limiter = new RateLimiter(pdo: $this->pdo);

        $limiter->check(key: 'test:reset', maxRequests: 5, windowSeconds: 60);

        $this->assertGreaterThan(time(), $limiter->getResetAt());
    }

    // ---------------------------------------------------------------
    //  Tests de Middleware::rateLimit()
    // ---------------------------------------------------------------

    public function testMiddlewareRateLimitAllowsUnderLimit(): void
    {
        $limiter = new RateLimiter(pdo: $this->pdo);

        // Should not throw or exit
        Middleware::rateLimit(
            limiter: $limiter,
            key: 'test:mw1',
            maxRequests: 10,
            windowSeconds: 60,
        );

        $this->assertTrue(true);
    }

    public function testMiddlewareRateLimitReturns429WhenExceeded(): void
    {
        $limiter = new RateLimiter(pdo: $this->pdo);

        // Exhaust the limit
        $limiter->check(key: 'test:mw2', maxRequests: 1, windowSeconds: 60);

        // Next call via Middleware should return 429
        ob_start();
        try {
            Middleware::rateLimit(
                limiter: $limiter,
                key: 'test:mw2',
                maxRequests: 1,
                windowSeconds: 60,
            );
        } catch (\RuntimeException) {
            // Expected: JsonResponse throws RuntimeException when exit is disabled
        }
        $output = ob_get_clean();

        $data = json_decode($output, true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('Too many requests', $data['message']);
    }

    public function testSingleRequestLimitAllowsExactlyOne(): void
    {
        $limiter = new RateLimiter(pdo: $this->pdo);

        $this->assertTrue(
            $limiter->check(key: 'test:single', maxRequests: 1, windowSeconds: 60)
        );
        $this->assertSame(0, $limiter->getRemainingRequests());

        $this->assertFalse(
            $limiter->check(key: 'test:single', maxRequests: 1, windowSeconds: 60)
        );
    }
}
