<?php

namespace App\Contracts;

interface ExternalGifProvider
{
    public function configured(): bool;

    public function name(): string;

    /** @return list<string> */
    public function allowedMediaHosts(): array;

    /** @return list<array<string, mixed>> */
    public function search(string $query, int $limit): array;

    /** @return array<string, mixed>|null */
    public function find(string $externalId): ?array;

    public function attribution(): ?string;
}
