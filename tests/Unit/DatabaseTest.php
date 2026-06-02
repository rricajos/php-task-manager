<?php

declare(strict_types=1);

namespace Tests\Unit;

use MiniProject\Database;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitarios para la clase Database (Singleton con SQLite).
 * Unit tests for the Database class (Singleton with SQLite).
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

        // Despues de resetear, se debe crear una nueva instancia (objeto diferente)
        $this->assertNotSame($instance1, $instance2);
    }

    public function testInMemoryDatabase(): void
    {
        Database::resetInstance();
        $db = Database::getInstance(':memory:');

        $pdo = $db->getConnection();

        // La conexion debe funcionar - verificar con una consulta simple
        $result = $pdo->query('SELECT 1 as test');
        $row = $result->fetch();

        $this->assertSame(1, (int) $row['test']);
    }

    // ---------------------------------------------------------------
    //  Tests de estructura de tablas
    // ---------------------------------------------------------------

    public function testTablesCreated(): void
    {
        Database::resetInstance();
        $db = Database::getInstance(':memory:');
        $pdo = $db->getConnection();

        // Verificar que existe la tabla users
        $stmtUsers = $pdo->query('PRAGMA table_info(users)');
        $usersColumns = $stmtUsers->fetchAll();
        $this->assertNotEmpty($usersColumns, 'La tabla users debe existir');

        // Verificar que existe la tabla tasks
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
            fn (array $col): string => $col['name'],
            $columns,
        );

        $this->assertContains('user_id', $columnNames);
    }

    public function testTasksTableHasDueDateColumn(): void
    {
        Database::resetInstance();
        $db = Database::getInstance(':memory:');
        $pdo = $db->getConnection();

        $stmt = $pdo->query('PRAGMA table_info(tasks)');
        $columns = $stmt->fetchAll();

        $columnNames = array_map(
            fn (array $col): string => $col['name'],
            $columns,
        );

        $this->assertContains('due_date', $columnNames);
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
            fn (array $col): string => $col['name'],
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
            fn (array $col): string => $col['name'],
            $columns,
        );

        $expectedColumns = [
            'id',
            'user_id',
            'title',
            'description',
            'priority',
            'status',
            'created_at',
            'completed_at',
            'due_date',
        ];

        foreach ($expectedColumns as $expected) {
            $this->assertContains($expected, $columnNames, "La columna '{$expected}' debe existir en tasks");
        }
    }

    public function testTagsTableExists(): void
    {
        Database::resetInstance();
        $db = Database::getInstance(':memory:');
        $pdo = $db->getConnection();

        $stmt = $pdo->query(
            "SELECT name FROM sqlite_master WHERE type='table' AND name='tags'"
        );
        $result = $stmt->fetch();

        $this->assertNotFalse($result);
        $this->assertSame('tags', $result['name']);
    }

    public function testTagsTableHasExpectedColumns(): void
    {
        Database::resetInstance();
        $db = Database::getInstance(':memory:');
        $pdo = $db->getConnection();

        $stmt = $pdo->query('PRAGMA table_info(tags)');
        $columns = $stmt->fetchAll();

        $columnNames = array_map(
            fn (array $col): string => $col['name'],
            $columns,
        );

        $this->assertContains('id', $columnNames);
        $this->assertContains('user_id', $columnNames);
        $this->assertContains('name', $columnNames);
        $this->assertContains('color', $columnNames);
        $this->assertContains('created_at', $columnNames);
    }

    public function testTaskTagsTableExists(): void
    {
        Database::resetInstance();
        $db = Database::getInstance(':memory:');
        $pdo = $db->getConnection();

        $stmt = $pdo->query(
            "SELECT name FROM sqlite_master WHERE type='table' AND name='task_tags'"
        );
        $result = $stmt->fetch();

        $this->assertNotFalse($result);
        $this->assertSame('task_tags', $result['name']);
    }

    public function testRateLimitsTableExists(): void
    {
        Database::resetInstance();
        $db = Database::getInstance(':memory:');
        $pdo = $db->getConnection();

        $stmt = $pdo->query(
            "SELECT name FROM sqlite_master WHERE type='table' AND name='rate_limits'"
        );
        $result = $stmt->fetch();

        $this->assertNotFalse($result);
        $this->assertSame('rate_limits', $result['name']);
    }

    public function testRateLimitsTableHasExpectedColumns(): void
    {
        Database::resetInstance();
        $db = Database::getInstance(':memory:');
        $pdo = $db->getConnection();

        $stmt = $pdo->query('PRAGMA table_info(rate_limits)');
        $columns = $stmt->fetchAll();

        $columnNames = array_map(
            fn (array $col): string => $col['name'],
            $columns,
        );

        $this->assertContains('id', $columnNames);
        $this->assertContains('rate_key', $columnNames);
        $this->assertContains('timestamp', $columnNames);
    }

    public function testCascadeDeleteRemovesTasksWhenUserDeleted(): void
    {
        Database::resetInstance();
        Database::getInstance(':memory:');

        $pdo = Database::getInstance()->getConnection();

        // Create a user
        $pdo->exec("INSERT INTO users (username, password_hash) VALUES ('cascade_user', 'hash')");
        $userId = (int) $pdo->lastInsertId();

        // Create tasks for that user
        $stmt = $pdo->prepare(
            "INSERT INTO tasks (user_id, title, priority, status) VALUES (:uid, 'Test Task', 'medium', 'pending')",
        );
        $stmt->execute([':uid' => $userId]);
        $stmt->execute([':uid' => $userId]);

        // Verify tasks exist
        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM tasks WHERE user_id = :uid');
        $countStmt->execute([':uid' => $userId]);
        $this->assertSame(2, (int) $countStmt->fetchColumn());

        // Delete user — should cascade to tasks
        $pdo->prepare('DELETE FROM users WHERE id = :uid')->execute([':uid' => $userId]);

        // Tasks should be gone
        $countStmt->execute([':uid' => $userId]);
        $this->assertSame(0, (int) $countStmt->fetchColumn());

        Database::resetInstance();
    }
}
