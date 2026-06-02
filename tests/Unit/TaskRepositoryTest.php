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

    // ---------------------------------------------------------------
    //  Tests: recurrence / Recurrencia
    // ---------------------------------------------------------------

    public function testSaveAndHydrateRecurrence(): void
    {
        $saved = $this->repository->save(
            new Task(
                id: null,
                title: 'Daily standup',
                description: '',
                priority: Priority::Medium,
                status: Status::Pending,
                recurrence: \MiniProject\RecurrenceInterval::Daily,
            )
        );

        $this->assertSame(\MiniProject\RecurrenceInterval::Daily, $saved->recurrence);
    }

    public function testUpdateRecurrence(): void
    {
        $saved = $this->repository->save(
            new Task(
                id: null,
                title: 'Task',
                description: '',
                priority: Priority::Medium,
                status: Status::Pending,
            )
        );

        $this->assertSame(\MiniProject\RecurrenceInterval::None, $saved->recurrence);

        $updated = $this->repository->update($saved->id, ['recurrence' => 'weekly']);
        $this->assertSame(\MiniProject\RecurrenceInterval::Weekly, $updated->recurrence);
    }

    // ---------------------------------------------------------------
    //  Tests: bulkComplete / Completar en bulk
    // ---------------------------------------------------------------

    public function testBulkCompleteEmptyArrayReturnsZero(): void
    {
        $result = $this->repository->bulkComplete([]);
        $this->assertSame(0, $result['affected']);
        $this->assertSame([], $result['skipped']);
    }

    public function testBulkCompleteAllPendingTasks(): void
    {
        $t1 = $this->repository->save(new Task(null, 'T1', '', Priority::Low, Status::Pending));
        $t2 = $this->repository->save(new Task(null, 'T2', '', Priority::Low, Status::Pending));

        $result = $this->repository->bulkComplete([$t1->id, $t2->id]);

        $this->assertSame(2, $result['affected']);
        $this->assertSame([], $result['skipped']);
        $this->assertSame(Status::Completed, $this->repository->findById($t1->id)->status);
        $this->assertSame(Status::Completed, $this->repository->findById($t2->id)->status);
    }

    public function testBulkCompleteSkipsAlreadyCompleted(): void
    {
        $t1 = $this->repository->save(new Task(null, 'T1', '', Priority::Low, Status::Pending));
        $t2 = $this->repository->save(new Task(null, 'T2', '', Priority::Low, Status::Pending));
        $this->repository->complete($t2->id);

        $result = $this->repository->bulkComplete([$t1->id, $t2->id]);

        $this->assertSame(1, $result['affected']);
        $this->assertContains($t2->id, $result['skipped']);
    }

    public function testBulkCompleteSkipsNonexistentIds(): void
    {
        $result = $this->repository->bulkComplete([9999, 8888]);
        $this->assertSame(0, $result['affected']);
        $this->assertCount(2, $result['skipped']);
    }

    public function testBulkCompleteUserIsolation(): void
    {
        // Create task for user 2
        $pdo = Database::getInstance()->getConnection();
        $pdo->exec("INSERT INTO users (username, password_hash) VALUES ('user2', 'hash2')");
        $repo2 = new TaskRepository(userId: 2);
        $t2 = $repo2->save(new Task(null, 'User2 task', '', Priority::Low, Status::Pending));

        // User 1 tries to bulk-complete user 2's task
        $result = $this->repository->bulkComplete([$t2->id]);

        $this->assertSame(0, $result['affected']);
        // Task still pending for user 2
        $this->assertSame(Status::Pending, $repo2->findById($t2->id)->status);
    }

    // ---------------------------------------------------------------
    //  Tests: bulkDelete / Eliminar en bulk
    // ---------------------------------------------------------------

    public function testBulkDeleteEmptyArrayReturnsZero(): void
    {
        $result = $this->repository->bulkDelete([]);
        $this->assertSame(0, $result['affected']);
    }

    public function testBulkDeleteRemovesTasks(): void
    {
        $t1 = $this->repository->save(new Task(null, 'T1', '', Priority::Low, Status::Pending));
        $t2 = $this->repository->save(new Task(null, 'T2', '', Priority::Low, Status::Pending));

        $result = $this->repository->bulkDelete([$t1->id, $t2->id]);

        $this->assertSame(2, $result['affected']);
        $this->expectException(NotFoundException::class);
        $this->repository->findById($t1->id);
    }

    public function testBulkDeleteIgnoresNonexistentIds(): void
    {
        $t1 = $this->repository->save(new Task(null, 'T1', '', Priority::Low, Status::Pending));
        $result = $this->repository->bulkDelete([$t1->id, 9999]);
        $this->assertSame(1, $result['affected']);
    }

    public function testBulkDeleteUserIsolation(): void
    {
        $pdo = Database::getInstance()->getConnection();
        $pdo->exec("INSERT INTO users (username, password_hash) VALUES ('user2', 'hash2')");
        $repo2 = new TaskRepository(userId: 2);
        $t2 = $repo2->save(new Task(null, 'User2 task', '', Priority::Low, Status::Pending));

        // User 1 tries to delete user 2's task
        $result = $this->repository->bulkDelete([$t2->id]);
        $this->assertSame(0, $result['affected']);
        // Task still exists for user 2
        $this->assertNotNull($repo2->findById($t2->id));
    }
}
