<?php

namespace App\Services\Academic;

use App\Contracts\ExternalGifProvider;

final class DisabledExternalGifProvider implements ExternalGifProvider
{
    public function configured(): bool
    {
        return false;
    }

    public function name(): string
    {
        return 'disabled';
    }

    public function allowedMediaHosts(): array
    {
        return [];
    }

    public function search(string $query, int $limit): array
    {
        return [];
    }

    public function find(string $externalId): ?array
    {
        return null;
    }

    public function attribution(): ?string
    {
        return null;
    }
}
