<?php

declare(strict_types=1);

namespace App\Support\Qwixx;

use App\Enums\Qwixx\Color;
use InvalidArgumentException;

/**
 * Expands the array shorthands in config/qwixx.php into validated Layout
 * value objects. Two row shorthands are accepted:
 *
 *   Solid row:    ['color' => 'red', 'numbers' => [2, 3, ..., 12]]
 *   Per-cell row: ['lock' => 'yellow', 'cells' => [[2, 'yellow'], [3, 'blue'], ...]]
 */
final class LayoutFactory
{
    /**
     * @param  array<string, mixed>  $definition
     */
    public function make(string $id, array $definition): Layout
    {
        $rowDefinitions = $definition['rows'] ?? [];

        if (count($rowDefinitions) !== 4) {
            throw new InvalidArgumentException("Layout [$id] must define exactly 4 rows.");
        }

        $rows = [];

        foreach (array_values($rowDefinitions) as $index => $rowDefinition) {
            $rows[] = $this->makeRow($id, $index, $rowDefinition);
        }

        $this->assertColorBalance($id, $rows);

        return new Layout(
            id: $id,
            name: $definition['name'] ?? $id,
            description: $definition['description'] ?? '',
            rows: $rows,
        );
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function makeRow(string $layoutId, int $index, array $definition): Row
    {
        if (isset($definition['numbers'])) {
            $lock = $this->color($layoutId, $definition['color'] ?? null);
            $cells = array_map(
                fn (int $number): Cell => new Cell($number, $lock),
                array_values($definition['numbers']),
            );
        } elseif (isset($definition['cells'])) {
            $lock = $this->color($layoutId, $definition['lock'] ?? null);
            $cells = array_map(
                fn (array $pair): Cell => new Cell((int) $pair[0], $this->color($layoutId, $pair[1] ?? null)),
                array_values($definition['cells']),
            );
        } else {
            throw new InvalidArgumentException("Layout [$layoutId] row $index needs either 'numbers' or 'cells'.");
        }

        $numbers = array_map(fn (Cell $cell): int => $cell->number, $cells);
        sort($numbers);

        if ($numbers !== range(2, 12)) {
            throw new InvalidArgumentException(
                "Layout [$layoutId] row $index must contain each number 2-12 exactly once.",
            );
        }

        return new Row(index: $index, cells: $cells, lockColor: $lock);
    }

    private function color(string $layoutId, mixed $value): Color
    {
        return Color::tryFrom((string) $value)
            ?? throw new InvalidArgumentException("Layout [$layoutId] uses unknown color [$value].");
    }

    /**
     * A well-formed sheet gives every color exactly 11 cells and exactly one
     * lock. One lock per color means the four rows lock in four distinct
     * colors, so the totals strip can label each row's score by its lock.
     *
     * @param  list<Row>  $rows
     */
    private function assertColorBalance(string $layoutId, array $rows): void
    {
        $cellCounts = array_fill_keys(array_column(Color::cases(), 'value'), 0);
        $lockCounts = $cellCounts;

        foreach ($rows as $row) {
            $lockCounts[$row->lockColor->value]++;

            foreach ($row->cells as $cell) {
                $cellCounts[$cell->color->value]++;
            }
        }

        foreach (Color::cases() as $color) {
            if ($cellCounts[$color->value] !== 11) {
                throw new InvalidArgumentException(
                    "Layout [$layoutId] must have exactly 11 {$color->value} cells, found {$cellCounts[$color->value]}.",
                );
            }

            if ($lockCounts[$color->value] !== 1) {
                throw new InvalidArgumentException(
                    "Layout [$layoutId] must have exactly one {$color->value} lock, found {$lockCounts[$color->value]}.",
                );
            }
        }
    }
}
