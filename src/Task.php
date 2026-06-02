<?php

declare(strict_types=1);

namespace MiniProject;

/**
 * Enumeración para la prioridad de una tarea.
 * Enumeration for task priority level.
 *
 * Usa backed enums de PHP 8.1 con valores string que coinciden
 * con los almacenados en la base de datos SQLite.
 *
 * Uses PHP 8.1 backed enums with string values matching
 * those stored in the SQLite database.
 */
enum Priority: string
{
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';

    /**
     * Devuelve una representación con color ANSI para la terminal.
     * Returns an ANSI-colored representation for terminal output.
     *
     * @return string Texto coloreado con códigos ANSI / ANSI-colored text
     */
    public function colorized(): string
    {
        return match ($this) {
            self::High   => "\033[31m{$this->value}\033[0m",
            self::Medium => "\033[33m{$this->value}\033[0m",
            self::Low    => "\033[32m{$this->value}\033[0m",
        };
    }

    /**
     * Devuelve el indicador de prioridad para la terminal.
     * Returns the priority indicator symbol for terminal output.
     *
     * @return string Símbolo indicador de prioridad / Priority indicator symbol
     */
    public function indicator(): string
    {
        return match ($this) {
            self::High   => '!!!',
            self::Medium => '!! ',
            self::Low    => '!  ',
        };
    }
}

/**
 * Enumeración para el estado de una tarea.
 * Enumeration for task status.
 *
 * Representa los dos estados posibles: pendiente o completada.
 * Represents the two possible states: pending or completed.
 */
enum Status: string
{
    case Pending = 'pending';
    case Completed = 'completed';

    /**
     * Devuelve una representación con color ANSI para la terminal.
     * Returns an ANSI-colored representation for terminal output.
     *
     * @return string Texto coloreado con códigos ANSI / ANSI-colored text
     */
    public function colorized(): string
    {
        return match ($this) {
            self::Pending   => "\033[33m{$this->value}\033[0m",
            self::Completed => "\033[32m{$this->value}\033[0m",
        };
    }

    /**
     * Devuelve un ícono de casilla para la terminal.
     * Returns a checkbox icon for terminal output.
     *
     * @return string Casilla marcada o vacía / Checked or empty checkbox
     */
    public function checkbox(): string
    {
        return match ($this) {
            self::Pending   => '[ ]',
            self::Completed => '[x]',
        };
    }
}

/**
 * Entidad que representa una tarea del gestor.
 * Entity representing a task in the task manager.
 *
 * Usa propiedades readonly de PHP 8.1 para inmutabilidad,
 * constructor promotion para concisión, y un método de
 * fábrica estático para crear instancias desde filas de BD.
 *
 * Uses PHP 8.1 readonly properties for immutability,
 * constructor promotion for conciseness, and a static
 * factory method to create instances from database rows.
 *
 * PHP 8 features: readonly, constructor promotion, named arguments,
 * enums, union types, static factory method
 */
class Task
{
    /**
     * Crea una nueva instancia de Task.
     * Creates a new Task instance.
     *
     * @param int|null $id Identificador único (null para tareas nuevas) / Unique identifier (null for new tasks)
     * @param string $title Título de la tarea / Task title
     * @param string $description Descripción detallada / Detailed description
     * @param Priority $priority Nivel de prioridad / Priority level
     * @param Status $status Estado actual de la tarea / Current task status
     * @param string $createdAt Fecha y hora de creación / Creation date and time
     * @param string|null $completedAt Fecha de completado (null si pendiente) / Completion date (null if pending)
     * @param string|null $dueDate Fecha límite (null si no tiene) / Due date (null if not set)
     */
    public function __construct(
        public readonly ?int $id,
        public readonly string $title,
        public readonly string $description,
        public readonly Priority $priority,
        public readonly Status $status = Status::Pending,
        public readonly string $createdAt = '',
        public readonly ?string $completedAt = null,
        public readonly ?string $dueDate = null,
    ) {
    }

    /**
     * Método de fábrica estático: crea un Task desde una fila de la base de datos.
     * Static factory method: creates a Task from a database row.
     *
     * Convierte los valores string de la BD a los enums correspondientes
     * usando named arguments para mayor claridad.
     *
     * Converts string values from the database to their corresponding enums
     * using named arguments for clarity.
     *
     * @param array<string, mixed> $row Fila asociativa de la BD / Associative database row
     * @return self Nueva instancia de Task / New Task instance
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            title: $row['title'],
            description: $row['description'] ?? '',
            priority: Priority::from($row['priority']),
            status: Status::from($row['status']),
            createdAt: $row['created_at'] ?? '',
            completedAt: $row['completed_at'],
            dueDate: $row['due_date'] ?? null,
        );
    }

    /**
     * Convierte la tarea a un array asociativo (útil para exportación y API).
     * Converts the task to an associative array (useful for export and API).
     *
     * @return array<string, mixed> Representación en array / Array representation
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'priority' => $this->priority->value,
            'status' => $this->status->value,
            'created_at' => $this->createdAt,
            'completed_at' => $this->completedAt,
            'due_date' => $this->dueDate,
        ];
    }

    /**
     * Genera una representación formateada de la tarea para la terminal.
     * Generates a formatted task representation for terminal output.
     *
     * Incluye un indicador de vencimiento si la tarea está vencida.
     * Includes an overdue indicator if the task is past its due date.
     *
     * @return string Línea formateada con colores ANSI / ANSI-colored formatted line
     */
    public function formatLine(): string
    {
        $checkbox = $this->status->checkbox();
        $indicator = $this->priority->indicator();
        $priority = $this->priority->colorized();

        $overdue = '';
        if (
            $this->dueDate !== null
            && $this->status === Status::Pending
            && $this->dueDate < date('Y-m-d')
        ) {
            $overdue = " \033[31m[VENCIDA]\033[0m";
        }

        return sprintf(
            "  \033[36m#%-4d\033[0m %s %s %-30s [%s]%s",
            $this->id,
            $checkbox,
            $indicator,
            mb_substr($this->title, 0, 30),
            $priority,
            $overdue,
        );
    }

    /**
     * Genera una representación detallada de la tarea.
     * Generates a detailed task representation.
     *
     * @return string Bloque de texto con todos los detalles / Text block with all details
     */
    public function formatDetail(): string
    {
        $lines = [];
        $lines[] = "\033[1;36m--- Detalle de Tarea #{$this->id} ---\033[0m";
        $lines[] = "  Titulo:      \033[1m{$this->title}\033[0m";
        $lines[] = "  Descripcion: {$this->description}";
        $lines[] = "  Prioridad:   {$this->priority->colorized()}";
        $lines[] = "  Estado:      {$this->status->colorized()}";
        $lines[] = "  Creada:      {$this->createdAt}";

        if ($this->completedAt !== null) {
            $lines[] = "  Completada:  {$this->completedAt}";
        }

        if ($this->dueDate !== null) {
            $dueDateText = $this->dueDate;
            if ($this->status === Status::Pending && $this->dueDate < date('Y-m-d')) {
                $dueDateText = "\033[31m{$this->dueDate} (VENCIDA)\033[0m";
            }
            $lines[] = "  Vencimiento: {$dueDateText}";
        }

        $lines[] = "\033[1;36m" . str_repeat('-', 35) . "\033[0m";

        return implode(PHP_EOL, $lines);
    }
}
