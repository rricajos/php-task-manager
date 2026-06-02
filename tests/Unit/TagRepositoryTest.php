<?php

declare(strict_types=1);

namespace Tests\Unit;

use MiniProject\AppException;
use MiniProject\Database;
use MiniProject\NotFoundException;
use MiniProject\TagRepository;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitarios para TagRepository.
 * Unit tests for TagRepository.
 *
 * @covers \MiniProject\TagRepository
 */
class TagRepositoryTest extends TestCase
{
    private \PDO $pdo;
    private int $userId;

    protected function setUp(): void
    {
        Database::resetInstance();
        Database::getInstance(':memory:');
        $this->pdo = Database::getInstance()->getConnection();

        // Create a test user
        $this->pdo->exec("INSERT INTO users (username, password_hash) VALUES ('tag_user', 'hash')");
        $this->userId = (int) $this->pdo->lastInsertId();
    }

    protected function tearDown(): void
    {
        Database::resetInstance();
    }

    // ---------------------------------------------------------------
    //  Tests de tabla / Table tests
    // ---------------------------------------------------------------

    public function testTagsTableExists(): void
    {
        $stmt = $this->pdo->query(
            "SELECT name FROM sqlite_master WHERE type='table' AND name='tags'"
        );
        $result = $stmt->fetch();

        $this->assertNotFalse($result);
        $this->assertSame('tags', $result['name']);
    }

    public function testTaskTagsTableExists(): void
    {
        $stmt = $this->pdo->query(
            "SELECT name FROM sqlite_master WHERE type='table' AND name='task_tags'"
        );
        $result = $stmt->fetch();

        $this->assertNotFalse($result);
        $this->assertSame('task_tags', $result['name']);
    }

    public function testTagsTableHasExpectedColumns(): void
    {
        $stmt = $this->pdo->query('PRAGMA table_info(tags)');
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

    // ---------------------------------------------------------------
    //  Tests CRUD / CRUD tests
    // ---------------------------------------------------------------

    public function testCreateTag(): void
    {
        $repo = new TagRepository(userId: $this->userId);

        $tag = $repo->create(name: 'Work');

        $this->assertSame('Work', $tag['name']);
        $this->assertSame('#6b7280', $tag['color']);
        $this->assertIsInt($tag['id']);
    }

    public function testCreateTagWithCustomColor(): void
    {
        $repo = new TagRepository(userId: $this->userId);

        $tag = $repo->create(name: 'Urgent', color: '#ef4444');

        $this->assertSame('Urgent', $tag['name']);
        $this->assertSame('#ef4444', $tag['color']);
    }

    public function testCreateDuplicateTagThrows(): void
    {
        $repo = new TagRepository(userId: $this->userId);
        $repo->create(name: 'Duplicate');

        $this->expectException(AppException::class);
        $this->expectExceptionMessage('already exists');

        $repo->create(name: 'Duplicate');
    }

    public function testFindAllReturnsAllUserTags(): void
    {
        $repo = new TagRepository(userId: $this->userId);
        $repo->create(name: 'Alpha');
        $repo->create(name: 'Beta');
        $repo->create(name: 'Gamma');

        $tags = $repo->findAll();

        $this->assertCount(3, $tags);
        // Sorted alphabetically
        $this->assertSame('Alpha', $tags[0]['name']);
        $this->assertSame('Beta', $tags[1]['name']);
        $this->assertSame('Gamma', $tags[2]['name']);
    }

    public function testFindByIdReturnsTag(): void
    {
        $repo = new TagRepository(userId: $this->userId);
        $created = $repo->create(name: 'FindMe');

        $found = $repo->findById($created['id']);

        $this->assertSame($created['id'], $found['id']);
        $this->assertSame('FindMe', $found['name']);
    }

    public function testFindByIdThrowsForNonExistentTag(): void
    {
        $repo = new TagRepository(userId: $this->userId);

        $this->expectException(NotFoundException::class);

        $repo->findById(9999);
    }

    public function testUpdateTagName(): void
    {
        $repo = new TagRepository(userId: $this->userId);
        $tag = $repo->create(name: 'OldName');

        $updated = $repo->update(id: $tag['id'], data: ['name' => 'NewName']);

        $this->assertSame('NewName', $updated['name']);
    }

    public function testUpdateTagColor(): void
    {
        $repo = new TagRepository(userId: $this->userId);
        $tag = $repo->create(name: 'Colored');

        $updated = $repo->update(id: $tag['id'], data: ['color' => '#10b981']);

        $this->assertSame('#10b981', $updated['color']);
    }

    public function testDeleteTag(): void
    {
        $repo = new TagRepository(userId: $this->userId);
        $tag = $repo->create(name: 'ToDelete');

        $repo->delete($tag['id']);

        $this->expectException(NotFoundException::class);
        $repo->findById($tag['id']);
    }

    public function testDeleteNonExistentTagThrows(): void
    {
        $repo = new TagRepository(userId: $this->userId);

        $this->expectException(NotFoundException::class);

        $repo->delete(9999);
    }

    // ---------------------------------------------------------------
    //  Tests de aislamiento de usuarios / User isolation tests
    // ---------------------------------------------------------------

    public function testTagsAreIsolatedByUser(): void
    {
        // Create second user
        $this->pdo->exec("INSERT INTO users (username, password_hash) VALUES ('other_user', 'hash')");
        $otherUserId = (int) $this->pdo->lastInsertId();

        $repo1 = new TagRepository(userId: $this->userId);
        $repo2 = new TagRepository(userId: $otherUserId);

        $repo1->create(name: 'User1Tag');
        $repo2->create(name: 'User2Tag');

        $tags1 = $repo1->findAll();
        $tags2 = $repo2->findAll();

        $this->assertCount(1, $tags1);
        $this->assertSame('User1Tag', $tags1[0]['name']);
        $this->assertCount(1, $tags2);
        $this->assertSame('User2Tag', $tags2[0]['name']);
    }

    public function testSameTagNameAllowedForDifferentUsers(): void
    {
        $this->pdo->exec("INSERT INTO users (username, password_hash) VALUES ('other_user2', 'hash')");
        $otherUserId = (int) $this->pdo->lastInsertId();

        $repo1 = new TagRepository(userId: $this->userId);
        $repo2 = new TagRepository(userId: $otherUserId);

        $tag1 = $repo1->create(name: 'SharedName');
        $tag2 = $repo2->create(name: 'SharedName');

        $this->assertSame('SharedName', $tag1['name']);
        $this->assertSame('SharedName', $tag2['name']);
        $this->assertNotSame($tag1['id'], $tag2['id']);
    }

    // ---------------------------------------------------------------
    //  Tests de task-tag relationships
    // ---------------------------------------------------------------

    public function testSyncTaskTags(): void
    {
        $repo = new TagRepository(userId: $this->userId);
        $tag1 = $repo->create(name: 'Tag1');
        $tag2 = $repo->create(name: 'Tag2');

        // Create a task
        $this->pdo->prepare(
            "INSERT INTO tasks (user_id, title, priority, status) VALUES (:uid, 'Test', 'medium', 'pending')"
        )->execute([':uid' => $this->userId]);
        $taskId = (int) $this->pdo->lastInsertId();

        $tags = $repo->syncTaskTags(taskId: $taskId, tagIds: [$tag1['id'], $tag2['id']]);

        $this->assertCount(2, $tags);
    }

    public function testSyncTaskTagsReplacesExisting(): void
    {
        $repo = new TagRepository(userId: $this->userId);
        $tag1 = $repo->create(name: 'First');
        $tag2 = $repo->create(name: 'Second');
        $tag3 = $repo->create(name: 'Third');

        $this->pdo->prepare(
            "INSERT INTO tasks (user_id, title, priority, status) VALUES (:uid, 'Test', 'medium', 'pending')"
        )->execute([':uid' => $this->userId]);
        $taskId = (int) $this->pdo->lastInsertId();

        // Assign tag1 and tag2
        $repo->syncTaskTags(taskId: $taskId, tagIds: [$tag1['id'], $tag2['id']]);

        // Replace with tag3 only
        $tags = $repo->syncTaskTags(taskId: $taskId, tagIds: [$tag3['id']]);

        $this->assertCount(1, $tags);
        $this->assertSame('Third', $tags[0]['name']);
    }

    public function testSyncTaskTagsWithEmptyArrayRemovesAll(): void
    {
        $repo = new TagRepository(userId: $this->userId);
        $tag = $repo->create(name: 'ToRemove');

        $this->pdo->prepare(
            "INSERT INTO tasks (user_id, title, priority, status) VALUES (:uid, 'Test', 'medium', 'pending')"
        )->execute([':uid' => $this->userId]);
        $taskId = (int) $this->pdo->lastInsertId();

        $repo->syncTaskTags(taskId: $taskId, tagIds: [$tag['id']]);
        $tags = $repo->syncTaskTags(taskId: $taskId, tagIds: []);

        $this->assertSame([], $tags);
    }

    public function testSyncTaskTagsThrowsForNonExistentTask(): void
    {
        $repo = new TagRepository(userId: $this->userId);

        $this->expectException(NotFoundException::class);

        $repo->syncTaskTags(taskId: 9999, tagIds: []);
    }

    public function testGetTagsByTaskIds(): void
    {
        $repo = new TagRepository(userId: $this->userId);
        $tag1 = $repo->create(name: 'A');
        $tag2 = $repo->create(name: 'B');

        // Create two tasks
        $stmt = $this->pdo->prepare(
            "INSERT INTO tasks (user_id, title, priority, status) VALUES (:uid, :title, 'medium', 'pending')"
        );
        $stmt->execute([':uid' => $this->userId, ':title' => 'Task 1']);
        $taskId1 = (int) $this->pdo->lastInsertId();

        $stmt->execute([':uid' => $this->userId, ':title' => 'Task 2']);
        $taskId2 = (int) $this->pdo->lastInsertId();

        // Tag task 1 with both, task 2 with one
        $repo->syncTaskTags(taskId: $taskId1, tagIds: [$tag1['id'], $tag2['id']]);
        $repo->syncTaskTags(taskId: $taskId2, tagIds: [$tag1['id']]);

        $result = $repo->getTagsByTaskIds([$taskId1, $taskId2]);

        $this->assertCount(2, $result[$taskId1]);
        $this->assertCount(1, $result[$taskId2]);
    }

    public function testGetTagsByTaskIdsWithEmptyArray(): void
    {
        $repo = new TagRepository(userId: $this->userId);

        $result = $repo->getTagsByTaskIds([]);

        $this->assertSame([], $result);
    }

    public function testDeleteTagRemovesTaskAssociations(): void
    {
        $repo = new TagRepository(userId: $this->userId);
        $tag = $repo->create(name: 'Ephemeral');

        $this->pdo->prepare(
            "INSERT INTO tasks (user_id, title, priority, status) VALUES (:uid, 'Test', 'medium', 'pending')"
        )->execute([':uid' => $this->userId]);
        $taskId = (int) $this->pdo->lastInsertId();

        $repo->syncTaskTags(taskId: $taskId, tagIds: [$tag['id']]);

        // Verify tag is attached
        $tagsBefore = $repo->getTagsByTaskIds([$taskId]);
        $this->assertCount(1, $tagsBefore[$taskId]);

        // Delete tag
        $repo->delete($tag['id']);

        // Tag association should be gone (CASCADE)
        $tagsAfter = $repo->getTagsByTaskIds([$taskId]);
        $this->assertEmpty($tagsAfter[$taskId] ?? []);
    }

    public function testDeleteTaskRemovesTagAssociations(): void
    {
        $repo = new TagRepository(userId: $this->userId);
        $tag = $repo->create(name: 'Persistent');

        $this->pdo->prepare(
            "INSERT INTO tasks (user_id, title, priority, status) VALUES (:uid, 'ToDelete', 'medium', 'pending')"
        )->execute([':uid' => $this->userId]);
        $taskId = (int) $this->pdo->lastInsertId();

        $repo->syncTaskTags(taskId: $taskId, tagIds: [$tag['id']]);

        // Delete task — CASCADE should remove task_tags entries
        $this->pdo->prepare('DELETE FROM tasks WHERE id = :id')->execute([':id' => $taskId]);

        // Tag itself should still exist
        $found = $repo->findById($tag['id']);
        $this->assertSame('Persistent', $found['name']);

        // But no task associations
        $count = $this->pdo->query('SELECT COUNT(*) FROM task_tags')->fetchColumn();
        $this->assertSame(0, (int) $count);
    }
}
