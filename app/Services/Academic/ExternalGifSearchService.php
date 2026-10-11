<?php

namespace App\Services\Academic;

use App\Contracts\ExternalGifProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

final class ExternalGifSearchService
{
    public function __construct(private ExternalGifProvider $provider) {}

    public function providerName(): string
    {
        return $this->provider->name();
    }

    /** @return array{enabled:bool,provider:?string,attribution:?string,results:list<array<string,mixed>>,message:?string} */
    public function search(string $query, int $limit = 12): array
    {
        $query = trim($query);
        if (! $this->provider->configured()) {
            return $this->unavailable('Pencarian GIF online belum dikonfigurasi. GIF internal tetap dapat digunakan.');
        }
        if (mb_strlen($query) < 2 || mb_strlen($query) > 50) {
            return $this->unavailable('Masukkan kata pencarian sepanjang 2–50 karakter.', true);
        }

        $limit = max(1, min($limit, 20));
        try {
            $items = Cache::remember(
                'announcement_gif_search_'.hash('sha256', $this->provider->name().'|'.mb_strtolower($query).'|'.$limit),
                now()->addMinutes(5),
                fn (): array => $this->provider->search($query, $limit),
            );
            $results = collect($items)
                ->map(fn (array $item): ?array => $this->sanitize($item))
                ->filter()->take($limit)->values()->all();

            return [
                'enabled' => true,
                'provider' => $this->provider->name(),
                'attribution' => $this->provider->attribution(),
                'results' => $results,
                'message' => $results === [] ? 'GIF tidak ditemukan.' : null,
            ];
        } catch (\Throwable $e) {
            Log::warning('External GIF search failed', ['provider' => $this->provider->name(), 'exception' => get_class($e)]);

            return $this->unavailable('Pencarian GIF online sedang tidak tersedia. GIF internal tetap dapat digunakan.', true);
        }
    }

    /** @return array<string,mixed> */
    public function resolve(string $externalId): array
    {
        $externalId = trim($externalId);
        if (! $this->provider->configured()) {
            throw new \InvalidArgumentException('Pencarian GIF online belum dikonfigurasi.');
        }
        if ($externalId === '' || ! preg_match('/^[A-Za-z0-9_-]{1,191}$/', $externalId)) {
            throw new \InvalidArgumentException('GIF online tidak valid.');
        }

        try {
            $item = $this->provider->find($externalId);
            $safe = $item ? $this->sanitize($item) : null;
        } catch (\Throwable $e) {
            Log::warning('External GIF lookup failed', ['provider' => $this->provider->name(), 'exception' => get_class($e)]);
            throw new \InvalidArgumentException('GIF online sedang tidak tersedia.');
        }
        if (! $safe || ! hash_equals($externalId, (string) $safe['id'])) {
            throw new \InvalidArgumentException('GIF online tidak tersedia.');
        }

        return $safe;
    }

    /** @return array<string,mixed>|null */
    private function sanitize(array $item): ?array
    {
        $id = trim((string) ($item['id'] ?? ''));
        $url = $this->safeMediaUrl($item['url'] ?? null);
        $preview = $this->safeMediaUrl($item['preview_url'] ?? null);
        if ($id === '' || ! preg_match('/^[A-Za-z0-9_-]{1,191}$/', $id) || ! $url || ! $preview) {
            return null;
        }

        return [
            'id' => $id,
            'title' => mb_substr(trim((string) ($item['title'] ?? 'GIF')), 0, 120),
            'alt' => mb_substr(trim((string) ($item['alt'] ?? $item['title'] ?? 'GIF')), 0, 500),
            'url' => $url,
            'preview_url' => $preview,
            'width' => max(1, min((int) ($item['width'] ?? 320), 2048)),
            'height' => max(1, min((int) ($item['height'] ?? 240), 2048)),
        ];
    }

    private function safeMediaUrl(mixed $value): ?string
    {
        $url = trim((string) $value);
        $parts = parse_url($url);
        if (! is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || isset($parts['user'])
            || isset($parts['pass'])
            || (isset($parts['port']) && (int) $parts['port'] !== 443)) {
            return null;
        }
        $host = strtolower(trim((string) ($parts['host'] ?? '')));
        if ($host === 'localhost' || str_ends_with($host, '.localhost')) {
            return null;
        }
        if (filter_var($host, FILTER_VALIDATE_IP)
            && ! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return null;
        }
        $allowed = collect($this->provider->allowedMediaHosts())
            ->map(fn ($candidate): string => strtolower(trim((string) $candidate)))
            ->filter();
        $trusted = $host !== '' && $allowed->contains(
            fn (string $domain): bool => $host === $domain || str_ends_with($host, '.'.$domain)
        );

        return $trusted ? $url : null;
    }

    /** @return array{enabled:bool,provider:?string,attribution:?string,results:array,message:string} */
    private function unavailable(string $message, bool $providerConfigured = false): array
    {
        return [
            'enabled' => $providerConfigured,
            'provider' => $providerConfigured ? $this->provider->name() : null,
            'attribution' => $providerConfigured ? $this->provider->attribution() : null,
            'results' => [],
            'message' => $message,
        ];
    }
}
