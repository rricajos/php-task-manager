<?php

declare(strict_types=1);

namespace Tests\Unit;

use MiniProject\RecurrenceInterval;
use PHPUnit\Framework\TestCase;

/**
 * Tests para el enum RecurrenceInterval.
 * Tests for the RecurrenceInterval enum.
 */
class RecurrenceIntervalTest extends TestCase
{
    // ---------------------------------------------------------------
    //  Enum cases / Casos del enum
    // ---------------------------------------------------------------

    public function testEnumHasFourCases(): void
    {
        $this->assertCount(4, RecurrenceInterval::cases());
    }

    public function testNoneValue(): void
    {
        $this->assertSame('none', RecurrenceInterval::None->value);
    }

    public function testDailyValue(): void
    {
        $this->assertSame('daily', RecurrenceInterval::Daily->value);
    }

    public function testWeeklyValue(): void
    {
        $this->assertSame('weekly', RecurrenceInterval::Weekly->value);
    }

    public function testMonthlyValue(): void
    {
        $this->assertSame('monthly', RecurrenceInterval::Monthly->value);
    }

    public function testTryFromReturnsCorrectEnum(): void
    {
        $this->assertSame(RecurrenceInterval::Daily, RecurrenceInterval::tryFrom('daily'));
        $this->assertSame(RecurrenceInterval::Weekly, RecurrenceInterval::tryFrom('weekly'));
        $this->assertSame(RecurrenceInterval::Monthly, RecurrenceInterval::tryFrom('monthly'));
        $this->assertSame(RecurrenceInterval::None, RecurrenceInterval::tryFrom('none'));
        $this->assertNull(RecurrenceInterval::tryFrom('invalid'));
    }

    // ---------------------------------------------------------------
    //  label() / Etiquetas
    // ---------------------------------------------------------------

    public function testLabels(): void
    {
        $this->assertSame('No recurrence', RecurrenceInterval::None->label());
        $this->assertSame('Daily', RecurrenceInterval::Daily->label());
        $this->assertSame('Weekly', RecurrenceInterval::Weekly->label());
        $this->assertSame('Monthly', RecurrenceInterval::Monthly->label());
    }

    // ---------------------------------------------------------------
    //  nextDueDate() — None / Sin recurrencia
    // ---------------------------------------------------------------

    public function testNoneReturnsNull(): void
    {
        $this->assertNull(RecurrenceInterval::None->nextDueDate('2026-06-01'));
        $this->assertNull(RecurrenceInterval::None->nextDueDate(null));
    }

    // ---------------------------------------------------------------
    //  nextDueDate() — Daily / Diaria
    // ---------------------------------------------------------------

    public function testDailyAddsOneDay(): void
    {
        $result = RecurrenceInterval::Daily->nextDueDate('2026-06-01');
        $this->assertSame('2026-06-02', $result);
    }

    public function testDailyAcrossMonthBoundary(): void
    {
        $result = RecurrenceInterval::Daily->nextDueDate('2026-05-31');
        $this->assertSame('2026-06-01', $result);
    }

    public function testDailyAcrossYearBoundary(): void
    {
        $result = RecurrenceInterval::Daily->nextDueDate('2026-12-31');
        $this->assertSame('2027-01-01', $result);
    }

    // ---------------------------------------------------------------
    //  nextDueDate() — Weekly / Semanal
    // ---------------------------------------------------------------

    public function testWeeklyAddsSevenDays(): void
    {
        $result = RecurrenceInterval::Weekly->nextDueDate('2026-06-01');
        $this->assertSame('2026-06-08', $result);
    }

    public function testWeeklyAcrossMonthBoundary(): void
    {
        $result = RecurrenceInterval::Weekly->nextDueDate('2026-06-28');
        $this->assertSame('2026-07-05', $result);
    }

    // ---------------------------------------------------------------
    //  nextDueDate() — Monthly / Mensual
    // ---------------------------------------------------------------

    public function testMonthlyAddsOneMonth(): void
    {
        $result = RecurrenceInterval::Monthly->nextDueDate('2026-06-01');
        $this->assertSame('2026-07-01', $result);
    }

    public function testMonthlyAcrossYearBoundary(): void
    {
        $result = RecurrenceInterval::Monthly->nextDueDate('2026-12-15');
        $this->assertSame('2027-01-15', $result);
    }

    public function testMonthlyFromEndOfMonth(): void
    {
        // PHP DateInterval handles month-end overflow (Jan 31 + 1M = Mar 3 in non-leap year)
        $result = RecurrenceInterval::Monthly->nextDueDate('2026-01-31');
        $this->assertNotNull($result);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $result);
    }

    // ---------------------------------------------------------------
    //  nextDueDate() — null/invalid base date / Fecha base nula o inválida
    // ---------------------------------------------------------------

    public function testNullDateUsesToday(): void
    {
        $result = RecurrenceInterval::Daily->nextDueDate(null);
        $this->assertNotNull($result);
        $expected = (new \DateTimeImmutable())->modify('+1 day')->format('Y-m-d');
        $this->assertSame($expected, $result);
    }

    public function testInvalidDateUsesToday(): void
    {
        $result = RecurrenceInterval::Weekly->nextDueDate('not-a-date');
        $this->assertNotNull($result);
        $expected = (new \DateTimeImmutable())->modify('+7 days')->format('Y-m-d');
        $this->assertSame($expected, $result);
    }

    public function testEmptyStringDateUsesToday(): void
    {
        $result = RecurrenceInterval::Monthly->nextDueDate('');
        $this->assertNotNull($result);
        $expected = (new \DateTimeImmutable())->modify('+1 month')->format('Y-m-d');
        $this->assertSame($expected, $result);
    }
}
