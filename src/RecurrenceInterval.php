<?php

declare(strict_types=1);

namespace MiniProject;

/**
 * Enumeración para el intervalo de recurrencia de una tarea.
 * Enumeration for the recurrence interval of a task.
 *
 * Cuando se completa una tarea recurrente, el servicio crea
 * automáticamente la siguiente ocurrencia con la fecha calculada.
 *
 * When a recurring task is completed, the service automatically
 * creates the next occurrence with the calculated due date.
 *
 * PHP 8 features: backed enum, match expression
 */
enum RecurrenceInterval: string
{
    case None = 'none';
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';

    /**
     * Calcula la siguiente fecha de vencimiento desde una fecha base.
     * Calculates the next due date from a base date.
     *
     * Devuelve null si el intervalo es None. Si $fromDate es null o
     * inválido, usa la fecha actual como base.
     *
     * Returns null if the interval is None. If $fromDate is null or
     * invalid, uses today as the base date.
     *
     * @param string|null $fromDate Fecha base en formato Y-m-d / Base date in Y-m-d format
     * @return string|null Siguiente fecha en formato Y-m-d, o null si no hay recurrencia / Next date in Y-m-d format, or null if no recurrence
     */
    public function nextDueDate(?string $fromDate): ?string
    {
        if ($this === self::None) {
            return null;
        }

        $base = null;
        if ($fromDate !== null && $fromDate !== '') {
            $base = \DateTimeImmutable::createFromFormat('Y-m-d', $fromDate);
        }

        if (!$base instanceof \DateTimeImmutable) {
            $base = new \DateTimeImmutable();
        }

        $interval = match ($this) {
            self::Daily => new \DateInterval('P1D'),
            self::Weekly => new \DateInterval('P1W'),
            self::Monthly => new \DateInterval('P1M'),
            self::None => null,
        };

        if ($interval === null) {
            return null;
        }

        return $base->add($interval)->format('Y-m-d');
    }

    /**
     * Devuelve una etiqueta legible para el intervalo.
     * Returns a human-readable label for the interval.
     *
     * @return string Etiqueta legible / Human-readable label
     */
    public function label(): string
    {
        return match ($this) {
            self::None => 'No recurrence',
            self::Daily => 'Daily',
            self::Weekly => 'Weekly',
            self::Monthly => 'Monthly',
        };
    }
}
