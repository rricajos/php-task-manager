<?php

declare(strict_types=1);

namespace Tests\Unit;

use MiniProject\Priority;
use MiniProject\Status;
use MiniProject\Task;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitarios para la entidad Task y los enums Priority/Status.
 * Unit tests for the Task entity and Priority/Status enums.
 *
 * @covers \MiniProject\Task
 * @covers \MiniProject\Priority
 * @covers \MiniProject\Status
 */
class TaskTest extends TestCase
{
    // ---------------------------------------------------------------
    //  Tests de creacion de Task
    // ---------------------------------------------------------------

    public function testCreateTaskWithAllProperties(): void
    {
        $task = new Task(
            id: 1,
            title: 'Comprar leche',
            description: 'Ir al supermercado',
            priority: Priority::High,
            status: Status::Pending,
            createdAt: '2026-01-15 10:00:00',
            completedAt: null,
        );

        $this->assertSame(1, $task->id);
        $this->assertSame('Comprar leche', $task->title);
        $this->assertSame('Ir al supermercado', $task->description);
        $this->assertSame(Priority::High, $task->priority);
        $this->assertSame(Status::Pending, $task->status);
        $this->assertSame('2026-01-15 10:00:00', $task->createdAt);
        $this->assertNull($task->completedAt);
    }

    public function testCreateTaskWithDefaultValues(): void
    {
        $task = new Task(
            id: null,
            title: 'Tarea nueva',
            description: '',
            priority: Priority::Medium,
        );

        $this->assertNull($task->id);
        $this->assertSame(Status::Pending, $task->status);
        $this->assertSame('', $task->createdAt);
        $this->assertNull($task->completedAt);
    }

    public function testCreateCompletedTask(): void
    {
        $task = new Task(
            id: 5,
            title: 'Tarea terminada',
            description: 'Ya esta lista',
            priority: Priority::Low,
            status: Status::Completed,
            createdAt: '2026-01-10 08:00:00',
            completedAt: '2026-01-12 14:30:00',
        );

        $this->assertSame(Status::Completed, $task->status);
        $this->assertSame('2026-01-12 14:30:00', $task->completedAt);
    }

    // ---------------------------------------------------------------
    //  Tests del metodo de fabrica fromRow()
    // ---------------------------------------------------------------

    public function testFromRowWithValidData(): void
    {
        $row = [
            'id' => '7',
            'title' => 'Estudiar PHP',
            'description' => 'Repasar enums y readonly',
            'priority' => 'high',
            'status' => 'pending',
            'created_at' => '2026-05-01 09:00:00',
            'completed_at' => null,
        ];

        $task = Task::fromRow($row);

        $this->assertSame(7, $task->id);
        $this->assertSame('Estudiar PHP', $task->title);
        $this->assertSame('Repasar enums y readonly', $task->description);
        $this->assertSame(Priority::High, $task->priority);
        $this->assertSame(Status::Pending, $task->status);
        $this->assertSame('2026-05-01 09:00:00', $task->createdAt);
        $this->assertNull($task->completedAt);
    }

    public function testFromRowWithCompletedTask(): void
    {
        $row = [
            'id' => '3',
            'title' => 'Hacer ejercicio',
            'description' => '30 minutos de cardio',
            'priority' => 'medium',
            'status' => 'completed',
            'created_at' => '2026-04-20 07:00:00',
            'completed_at' => '2026-04-20 07:35:00',
        ];

        $task = Task::fromRow($row);

        $this->assertSame(3, $task->id);
        $this->assertSame(Priority::Medium, $task->priority);
        $this->assertSame(Status::Completed, $task->status);
        $this->assertSame('2026-04-20 07:35:00', $task->completedAt);
    }

    public function testFromRowWithNullDescription(): void
    {
        $row = [
            'id' => '1',
            'title' => 'Sin descripcion',
            'description' => null,
            'priority' => 'low',
            'status' => 'pending',
            'created_at' => null,
            'completed_at' => null,
        ];

        $task = Task::fromRow($row);

        $this->assertSame('', $task->description);
        $this->assertSame('', $task->createdAt);
    }

    // ---------------------------------------------------------------
    //  Tests del metodo toArray()
    // ---------------------------------------------------------------

    public function testToArrayReturnsCorrectStructure(): void
    {
        $task = new Task(
            id: 10,
            title: 'Revisar codigo',
            description: 'Code review del PR #42',
            priority: Priority::High,
            status: Status::Pending,
            createdAt: '2026-03-15 16:00:00',
            completedAt: null,
        );

        $array = $task->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('title', $array);
        $this->assertArrayHasKey('description', $array);
        $this->assertArrayHasKey('priority', $array);
        $this->assertArrayHasKey('status', $array);
        $this->assertArrayHasKey('created_at', $array);
        $this->assertArrayHasKey('completed_at', $array);

        $this->assertSame(10, $array['id']);
        $this->assertSame('Revisar codigo', $array['title']);
        $this->assertSame('Code review del PR #42', $array['description']);
        $this->assertSame('high', $array['priority']);
        $this->assertSame('pending', $array['status']);
        $this->assertSame('2026-03-15 16:00:00', $array['created_at']);
        $this->assertNull($array['completed_at']);
    }

    public function testToArrayWithCompletedTask(): void
    {
        $task = new Task(
            id: 2,
            title: 'Desplegar app',
            description: 'Deploy a produccion',
            priority: Priority::Medium,
            status: Status::Completed,
            createdAt: '2026-02-01 12:00:00',
            completedAt: '2026-02-01 14:00:00',
        );

        $array = $task->toArray();

        $this->assertSame('completed', $array['status']);
        $this->assertSame('medium', $array['priority']);
        $this->assertSame('2026-02-01 14:00:00', $array['completed_at']);
    }

    public function testToArrayUsesEnumValuesAsString(): void
    {
        $task = new Task(
            id: 1,
            title: 'Test',
            description: '',
            priority: Priority::Low,
            status: Status::Pending,
        );

        $array = $task->toArray();

        // Los valores del enum se almacenan como string en el array
        $this->assertSame('low', $array['priority']);
        $this->assertSame('pending', $array['status']);
    }

    // ---------------------------------------------------------------
    //  Tests del enum Priority
    // ---------------------------------------------------------------

    public function testPriorityHasThreeCases(): void
    {
        $cases = Priority::cases();

        $this->assertCount(3, $cases);
        $this->assertSame(Priority::High, $cases[0]);
        $this->assertSame(Priority::Medium, $cases[1]);
        $this->assertSame(Priority::Low, $cases[2]);
    }

    public function testPriorityBackedEnumValues(): void
    {
        $this->assertSame('high', Priority::High->value);
        $this->assertSame('medium', Priority::Medium->value);
        $this->assertSame('low', Priority::Low->value);
    }

    public function testPriorityFromValidString(): void
    {
        $this->assertSame(Priority::High, Priority::from('high'));
        $this->assertSame(Priority::Medium, Priority::from('medium'));
        $this->assertSame(Priority::Low, Priority::from('low'));
    }

    public function testPriorityFromInvalidStringThrowsException(): void
    {
        $this->expectException(\ValueError::class);
        Priority::from('urgente');
    }

    public function testPriorityTryFromReturnsNullForInvalidValue(): void
    {
        $this->assertNull(Priority::tryFrom('urgente'));
        $this->assertNull(Priority::tryFrom(''));
        $this->assertNull(Priority::tryFrom('ALTA'));
    }

    public function testPriorityColorizedContainsValue(): void
    {
        // El metodo colorized() envuelve el valor con codigos ANSI
        $this->assertStringContainsString('high', Priority::High->colorized());
        $this->assertStringContainsString('medium', Priority::Medium->colorized());
        $this->assertStringContainsString('low', Priority::Low->colorized());
    }

    public function testPriorityIndicatorReturnsCorrectSymbols(): void
    {
        $this->assertSame('!!!', Priority::High->indicator());
        $this->assertSame('!! ', Priority::Medium->indicator());
        $this->assertSame('!  ', Priority::Low->indicator());
    }

    // ---------------------------------------------------------------
    //  Tests del enum Status
    // ---------------------------------------------------------------

    public function testStatusHasTwoCases(): void
    {
        $cases = Status::cases();

        $this->assertCount(2, $cases);
        $this->assertSame(Status::Pending, $cases[0]);
        $this->assertSame(Status::Completed, $cases[1]);
    }

    public function testStatusBackedEnumValues(): void
    {
        $this->assertSame('pending', Status::Pending->value);
        $this->assertSame('completed', Status::Completed->value);
    }

    public function testStatusFromValidString(): void
    {
        $this->assertSame(Status::Pending, Status::from('pending'));
        $this->assertSame(Status::Completed, Status::from('completed'));
    }

    public function testStatusFromInvalidStringThrowsException(): void
    {
        $this->expectException(\ValueError::class);
        Status::from('cancelada');
    }

    public function testStatusColorizedContainsValue(): void
    {
        $this->assertStringContainsString('pending', Status::Pending->colorized());
        $this->assertStringContainsString('completed', Status::Completed->colorized());
    }

    public function testStatusCheckboxReturnsFormat(): void
    {
        $this->assertSame('[ ]', Status::Pending->checkbox());
        $this->assertSame('[x]', Status::Completed->checkbox());
    }

    // ---------------------------------------------------------------
    //  Tests de metodos de formato
    // ---------------------------------------------------------------

    public function testFormatLineContainsIdAndTitle(): void
    {
        $task = new Task(
            id: 42,
            title: 'Tarea de prueba',
            description: '',
            priority: Priority::High,
            status: Status::Pending,
        );

        $linea = $task->formatLine();

        $this->assertStringContainsString('42', $linea);
        $this->assertStringContainsString('Tarea de prueba', $linea);
    }

    public function testFormatDetailContainsCompleteInfo(): void
    {
        $task = new Task(
            id: 1,
            title: 'Mi tarea',
            description: 'Una descripcion detallada',
            priority: Priority::Medium,
            status: Status::Completed,
            createdAt: '2026-01-01 00:00:00',
            completedAt: '2026-01-02 12:00:00',
        );

        $detalle = $task->formatDetail();

        $this->assertStringContainsString('Mi tarea', $detalle);
        $this->assertStringContainsString('Una descripcion detallada', $detalle);
        $this->assertStringContainsString('2026-01-01 00:00:00', $detalle);
        $this->assertStringContainsString('2026-01-02 12:00:00', $detalle);
    }

    public function testFormatDetailWithoutCompletedAt(): void
    {
        $task = new Task(
            id: 1,
            title: 'Pendiente',
            description: '',
            priority: Priority::Low,
            status: Status::Pending,
            createdAt: '2026-01-01 00:00:00',
            completedAt: null,
        );

        $detalle = $task->formatDetail();

        // No debe contener la linea "Completada:" si completedAt es null
        $this->assertStringNotContainsString('Completada:', $detalle);
    }

    // ---------------------------------------------------------------
    //  Tests de la propiedad dueDate
    // ---------------------------------------------------------------

    public function testCreateTaskWithDueDate(): void
    {
        $task = new Task(
            id: 1,
            title: 'Tarea con vencimiento',
            description: 'Debe completarse antes de la fecha',
            priority: Priority::High,
            status: Status::Pending,
            createdAt: '2026-05-01 10:00:00',
            completedAt: null,
            dueDate: '2026-12-31',
        );

        $this->assertSame('2026-12-31', $task->dueDate);
    }

    public function testCreateTaskWithoutDueDateIsNull(): void
    {
        $task = new Task(
            id: 1,
            title: 'Tarea sin vencimiento',
            description: '',
            priority: Priority::Medium,
        );

        $this->assertNull($task->dueDate);
    }

    public function testDueDateInToArray(): void
    {
        $task = new Task(
            id: 5,
            title: 'Tarea con fecha limite',
            description: '',
            priority: Priority::High,
            status: Status::Pending,
            createdAt: '2026-05-01 10:00:00',
            completedAt: null,
            dueDate: '2026-06-15',
        );

        $array = $task->toArray();

        $this->assertArrayHasKey('due_date', $array);
        $this->assertSame('2026-06-15', $array['due_date']);
    }

    public function testDueDateNullInToArray(): void
    {
        $task = new Task(
            id: 1,
            title: 'Sin vencimiento',
            description: '',
            priority: Priority::Low,
        );

        $array = $task->toArray();

        $this->assertArrayHasKey('due_date', $array);
        $this->assertNull($array['due_date']);
    }

    public function testFromRowWithDueDate(): void
    {
        $row = [
            'id' => '10',
            'title' => 'Tarea con fecha',
            'description' => 'Descripcion',
            'priority' => 'high',
            'status' => 'pending',
            'created_at' => '2026-05-01 09:00:00',
            'completed_at' => null,
            'due_date' => '2026-06-30',
        ];

        $task = Task::fromRow($row);

        $this->assertSame('2026-06-30', $task->dueDate);
    }

    public function testFromRowWithoutDueDateIsNull(): void
    {
        $row = [
            'id' => '10',
            'title' => 'Tarea sin fecha',
            'description' => '',
            'priority' => 'medium',
            'status' => 'pending',
            'created_at' => '2026-05-01 09:00:00',
            'completed_at' => null,
        ];

        $task = Task::fromRow($row);

        $this->assertNull($task->dueDate);
    }

    public function testFormatDetailWithDueDate(): void
    {
        $task = new Task(
            id: 1,
            title: 'Tarea con vencimiento',
            description: 'Desc',
            priority: Priority::High,
            status: Status::Pending,
            createdAt: '2026-05-01 10:00:00',
            completedAt: null,
            dueDate: '2099-12-31',
        );

        $detalle = $task->formatDetail();

        $this->assertStringContainsString('Vencimiento:', $detalle);
        $this->assertStringContainsString('2099-12-31', $detalle);
    }

    public function testFormatDetailWithoutDueDate(): void
    {
        $task = new Task(
            id: 1,
            title: 'Sin vencimiento',
            description: '',
            priority: Priority::Low,
            status: Status::Pending,
            createdAt: '2026-05-01 10:00:00',
            completedAt: null,
            dueDate: null,
        );

        $detalle = $task->formatDetail();

        $this->assertStringNotContainsString('Vencimiento:', $detalle);
    }

    public function testFormatLineOverdueTaskShowsIndicator(): void
    {
        // Usar una fecha muy pasada para asegurar que siempre esta "vencida"
        $task = new Task(
            id: 1,
            title: 'Tarea vencida',
            description: '',
            priority: Priority::High,
            status: Status::Pending,
            createdAt: '2020-01-01 10:00:00',
            completedAt: null,
            dueDate: '2020-01-01',
        );

        $linea = $task->formatLine();

        $this->assertStringContainsString('VENCIDA', $linea);
    }

    public function testFormatLineCompletedTaskDoesNotShowOverdue(): void
    {
        // Una tarea completada no debe mostrar VENCIDA aunque due_date sea pasada
        $task = new Task(
            id: 1,
            title: 'Tarea completada',
            description: '',
            priority: Priority::High,
            status: Status::Completed,
            createdAt: '2020-01-01 10:00:00',
            completedAt: '2020-01-02 10:00:00',
            dueDate: '2020-01-01',
        );

        $linea = $task->formatLine();

        $this->assertStringNotContainsString('VENCIDA', $linea);
    }
}
