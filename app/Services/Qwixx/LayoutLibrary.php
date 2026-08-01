<?php

declare(strict_types=1);

namespace App\Services\Qwixx;

use App\Support\Qwixx\Layout;
use App\Support\Qwixx\LayoutFactory;
use Illuminate\Support\Collection;

/**
 * Builds the in-memory scoresheet layout library from config/qwixx.php.
 */
final class LayoutLibrary
{
    /** @var Collection<string, Layout>|null */
    private ?Collection $cache = null;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly array $config = [],
        private readonly LayoutFactory $factory = new LayoutFactory,
    ) {}

    /**
     * @return Collection<string, Layout> keyed by layout id
     */
    public function all(): Collection
    {
        return $this->cache ??= collect($this->config['layouts'] ?? [])
            ->map(fn (array $definition, string $id): Layout => $this->factory->make($id, $definition));
    }

    public function find(string $id): ?Layout
    {
        return $this->all()->get($id);
    }
}
