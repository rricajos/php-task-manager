<?php

declare(strict_types=1);

namespace Tests\Unit;

use MiniProject\AppException;
use MiniProject\Database;
use MiniProject\NotFoundException;
use MiniProject\Priority;
use MiniProject\Status;
use MiniProject\Task;
use MiniProject\TaskRepository;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitarios para TaskRepository con base de datos SQLite en memoria.
 * Unit tests for TaskRepository with in-memory SQLite database.
 *
 * @covers \MiniProject\TaskRepository
 * @covers \MiniProject\Database
 */
class TaskRepositoryTest extends TestCase
{
    private TaskRepository $repository;

    protected function setUp(): void
    {
        Database::resetInstance();
        Database::getInstance(':memory:');

        // Insertar un usuario de prueba para que el FK sea valido
        $pdo = Database::getInstance()->getConnection();
        $pdo->exec('INSERT INTO users (username, password_hash) VALUES (\'testuser\', \'hash\')');

        $this->repository = new TaskRepository(userId: 1);
    }

    protected function tearDown(): void
    {
        Database::resetInstance();
    }

    // ---------------------------------------------------------------
    //  Test: update rejects invalid column names (SQL injection guard)
    // ---------------------------------------------------------------

    public function testUpdateRejectsInvalidColumnNames(): void
    {
        $saved = $this->repository->save(
            new Task(
                id: null,
                title: 'Test',
                description: '',
                priority: Priority::Medium,
                status: Status::Pending,
            )
        );

        $this->expectException(AppException::class);
        $this->expectExceptionMessage('Invalid update columns');

        $this->repository->update($saved->id, [
            'title' => 'safe',
            'id = 1; DROP TABLE tasks; --' => 'attack',
        ]);
    }

    // ---------------------------------------------------------------
    //  Test: update accepts valid columns
    // ---------------------------------------------------------------

    public function testUpdateAcceptsValidColumns(): void
    {
        $saved = $this->repository->save(
            new Task(
                id: null,
                title: 'Test',
                description: '',
                priority: Priority::Medium,
                status: Status::Pending,
            )
        );

        $updated = $this->repository->update($saved->id, [
            'title' => 'Updated',
            'description' => 'New desc',
            'priority' => 'high',
        ]);

        $this->assertSame('Updated', $updated->title);
        $this->assertSame('New desc', $updated->description);
        $this->assertSame(Priority::High, $updated->priority);
    }

    // ---------------------------------------------------------------
    //  Test: findAll returns tasks for the user
    // ---------------------------------------------------------------

    public function testFindAllReturnsTasksForUser(): void
    {
        $this->repository->save(
            new Task(
                id: null,
                title: 'Test',
                description: '',
                priority: Priority::Medium,
                status: Status::Pending,
            )
        );

        $tasks = $this->repository->findAll();

        $this->assertCount(1, $tasks);
        $this->assertSame('Test', $tasks[0]->title);
    }

    // ---------------------------------------------------------------
    //  Test: findById throws for nonexistent task
    // ---------------------------------------------------------------

    public function testFindByIdThrowsForNonexistent(): void
    {
        $this->expectException(NotFoundException::class);

        $this->repository->findById(9999);
    }

    // ---------------------------------------------------------------
    //  Test: delete removes a task
    // ---------------------------------------------------------------

    public function testDeleteRemovesTask(): void
    {
        $saved = $this->repository->save(
            new Task(
                id: null,
                title: 'Test',
                description: '',
                priority: Priority::Medium,
                status: Status::Pending,
            )
        );

        $this->repository->delete($saved->id);

        $tasks = $this->repository->findAll();
        $this->assertCount(0, $tasks);
    }

    // ---------------------------------------------------------------
    //  Test: complete marks a task as completed
    // ---------------------------------------------------------------

    public function testCompleteMarksTaskAsCompleted(): void
    {
        $saved = $this->repository->save(
            new Task(
                id: null,
                title: 'Test',
                description: '',
                priority: Priority::Medium,
                status: Status::Pending,
            )
        );

        $completed = $this->repository->complete($saved->id);

        $this->assertSame(Status::Completed, $completed->status);
        $this->assertNotNull($completed->completedAt);
    }

    // ---------------------------------------------------------------
    //  Test: getStatistics returns correct counts
    // ---------------------------------------------------------------

    public function testGetStatisticsReturnsCorrectCounts(): void
    {
        $this->repository->save(
            new Task(
                id: null,
                title: 'Test',
                description: '',
                priority: Priority::Medium,
                status: Status::Pending,
            )
        );
        $this->repository->save(
            new Task(
                id: null,
                title: 'Test',
                description: '',
                priority: Priority::Medium,
                status: Status::Pending,
            )
        );
        $saved3 = $this->repository->save(
            new Task(
                id: null,
                title: 'Test',
                description: '',
                priority: Priority::Medium,
                status: Status::Pending,
            )
        );

        $this->repository->complete($saved3->id);

        $stats = $this->repository->getStatistics();

        $this->assertSame(3, $stats['total']);
        $this->assertSame(1, $stats['completed']);
        $this->assertSame(2, $stats['pending']);
        $this->assertArrayHasKey('high', $stats['by_priority']);
        $this->assertArrayHasKey('medium', $stats['by_priority']);
        $this->assertArrayHasKey('low', $stats['by_priority']);
    }
}
