<?php

declare(strict_types=1);

namespace App\Support\Qwixx;

final readonly class Layout
{
    /**
     * @param  list<Row>  $rows  exactly 4
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $description,
        public array $rows,
    ) {}

    /**
     * JSON-safe shape consumed by the client-side game engine.
     *
     * @return array{id: string, rows: list<array{lock: string, cells: list<array{n: int, c: string}>}>}
     */
    public function toClientArray(): array
    {
        return [
            'id' => $this->id,
            'rows' => array_map(fn (Row $row): array => [
                'lock' => $row->lockColor->value,
                'cells' => array_map(
                    fn (Cell $cell): array => ['n' => $cell->number, 'c' => $cell->color->value],
                    $row->cells,
                ),
            ], $this->rows),
        ];
    }
}
