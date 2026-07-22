<?php

declare(strict_types=1);

final class Paginator
{
    public int $page;
    public int $perPage;
    public int $total;

    public function __construct(int $total, int $page = 1, int $perPage = 15)
    {
        $this->total = max(0, $total);
        $this->page = max(1, $page);
        $this->perPage = max(1, $perPage);
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    public function pages(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }
}
