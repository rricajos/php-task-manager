<?php

declare(strict_types=1);

namespace Tests\Unit;

use MiniProject\Database;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitarios para la clase Database (Singleton con SQLite).
 *
 * @covers \MiniProject\Database
 */
class DatabaseTest extends TestCase
{
    protected function tearDown(): void
    {
        Database::resetInstance();
    }

    // ---------------------------------------------------------------
    //  Tests del patron Singleton
    // ---------------------------------------------------------------

    public function testGetInstanceReturnsSameInstance(): void
    {
        Database::resetInstance();
        $instance1 = Database::getInstance(':memory:');
        $instance2 = Database::getInstance();

        $this->assertSame($instance1, $instance2);
    }

    public function testGetConnectionReturnsPDO(): void
    {
        Database::resetInstance();
        $db = Database::getInstance(':memory:');

        $connection = $db->getConnection();

        $this->assertInstanceOf(PDO::class, $connection);
    }

    public function testResetInstanceAllowsNewCreation(): void
    {
        Database::resetInstance();
        $instance1 = Database::getInstance(':memory:');

        Database::resetInstance();
        $instance2 = Database::getInstance(':memory:');

        // After reset, a new instance should be created (different object)
        $this->assertNotSame($instance1, $instance2);
    }

    public function testInMemoryDatabase(): void
    {
        Database::resetInstance();
        $db = Database::getInstance(':memory:');

        $pdo = $db->getConnection();

        // The connection should work - verify by running a simple query
        $result = $pdo->query('SELECT 1 as test');
        $row = $result->fetch();

        $this->assertSame('1', $row['test']);
    }

    // ---------------------------------------------------------------
    //  Tests de estructura de tablas
    // ---------------------------------------------------------------

    public function testTablesCreated(): void
    {
        Database::resetInstance();
        $db = Database::getInstance(':memory:');
        $pdo = $db->getConnection();

        // Check that users table exists
        $stmtUsers = $pdo->query('PRAGMA table_info(users)');
        $usersColumns = $stmtUsers->fetchAll();
        $this->assertNotEmpty($usersColumns, 'La tabla users debe existir');

        // Check that tasks table exists
        $stmtTasks = $pdo->query('PRAGMA table_info(tasks)');
        $tasksColumns = $stmtTasks->fetchAll();
        $this->assertNotEmpty($tasksColumns, 'La tabla tasks debe existir');
    }

    public function testTasksTableHasUserIdColumn(): void
    {
        Database::resetInstance();
        $db = Database::getInstance(':memory:');
        $pdo = $db->getConnection();

        $stmt = $pdo->query('PRAGMA table_info(tasks)');
        $columns = $stmt->fetchAll();

        $columnNames = array_map(
            fn(array $col): string => $col['name'],
            $columns,
        );

        $this->assertContains('user_id', $columnNames);
    }

    public function testTasksTableHasFechaVencimientoColumn(): void
    {
        Database::resetInstance();
        $db = Database::getInstance(':memory:');
        $pdo = $db->getConnection();

        $stmt = $pdo->query('PRAGMA table_info(tasks)');
        $columns = $stmt->fetchAll();

        $columnNames = array_map(
            fn(array $col): string => $col['name'],
            $columns,
        );

        $this->assertContains('fecha_vencimiento', $columnNames);
    }

    public function testForeignKeysEnabled(): void
    {
        Database::resetInstance();
        $db = Database::getInstance(':memory:');
        $pdo = $db->getConnection();

        $stmt = $pdo->query('PRAGMA foreign_keys');
        $result = $stmt->fetch();

        $this->assertSame(1, (int) $result['foreign_keys']);
    }

    // ---------------------------------------------------------------
    //  Tests adicionales de estructura
    // ---------------------------------------------------------------

    public function testUsersTableHasExpectedColumns(): void
    {
        Database::resetInstance();
        $db = Database::getInstance(':memory:');
        $pdo = $db->getConnection();

        $stmt = $pdo->query('PRAGMA table_info(users)');
        $columns = $stmt->fetchAll();

        $columnNames = array_map(
            fn(array $col): string => $col['name'],
            $columns,
        );

        $this->assertContains('id', $columnNames);
        $this->assertContains('username', $columnNames);
        $this->assertContains('password_hash', $columnNames);
        $this->assertContains('created_at', $columnNames);
    }

    public function testTasksTableHasAllExpectedColumns(): void
    {
        Database::resetInstance();
        $db = Database::getInstance(':memory:');
        $pdo = $db->getConnection();

        $stmt = $pdo->query('PRAGMA table_info(tasks)');
        $columns = $stmt->fetchAll();

        $columnNames = array_map(
            fn(array $col): string => $col['name'],
            $columns,
        );

        $expectedColumns = [
            'id',
            'user_id',
            'titulo',
            'descripcion',
            'prioridad',
            'estado',
            'fecha_creacion',
            'fecha_completada',
            'fecha_vencimiento',
        ];

        foreach ($expectedColumns as $expected) {
            $this->assertContains($expected, $columnNames, "La columna '{$expected}' debe existir en tasks");
        }
    }
}
