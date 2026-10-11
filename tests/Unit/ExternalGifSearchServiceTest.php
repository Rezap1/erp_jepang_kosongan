<?php

namespace Tests\Unit;

use App\Contracts\ExternalGifProvider;
use App\Services\Academic\DisabledExternalGifProvider;
use App\Services\Academic\ExternalGifSearchService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ExternalGifSearchServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_disabled_provider_returns_a_safe_fallback(): void
    {
        $result = (new ExternalGifSearchService(new DisabledExternalGifProvider))->search('senyum');

        $this->assertFalse($result['enabled']);
        $this->assertSame([], $result['results']);
        $this->assertStringContainsString('belum dikonfigurasi', $result['message']);
    }

    public function test_search_only_returns_https_media_from_the_provider_allowlist(): void
    {
        $provider = $this->provider([
            $this->gif('valid', 'https://cdn.media.example.test/valid.gif'),
            $this->gif('http', 'http://media.example.test/http.gif'),
            $this->gif('suffix', 'https://media.example.test.evil.test/suffix.gif'),
            $this->gif('private', 'https://127.0.0.1/private.gif'),
            $this->gif('credentials', 'https://user@media.example.test/credentials.gif'),
        ]);

        $result = (new ExternalGifSearchService($provider))->search('senyum');

        $this->assertTrue($result['enabled']);
        $this->assertSame(['valid'], collect($result['results'])->pluck('id')->all());
        $this->assertSame('Fake GIF Provider', $result['attribution']);
    }

    public function test_lookup_revalidates_the_server_returned_id_and_url(): void
    {
        $provider = $this->provider([], $this->gif('different', 'https://media.example.test/different.gif'));

        $this->expectException(\InvalidArgumentException::class);
        (new ExternalGifSearchService($provider))->resolve('requested');
    }

    public function test_provider_failure_does_not_escape_the_search_boundary(): void
    {
        $provider = new class implements ExternalGifProvider
        {
            public function configured(): bool
            {
                return true;
            }

            public function name(): string
            {
                return 'failing';
            }

            public function allowedMediaHosts(): array
            {
                return ['media.example.test'];
            }

            public function search(string $query, int $limit): array
            {
                throw new \RuntimeException('secret upstream detail');
            }

            public function find(string $externalId): ?array
            {
                throw new \RuntimeException('secret upstream detail');
            }

            public function attribution(): ?string
            {
                return 'Failing';
            }
        };

        $result = (new ExternalGifSearchService($provider))->search('senyum');

        $this->assertSame([], $result['results']);
        $this->assertStringNotContainsString('secret upstream detail', $result['message']);
    }

    private function gif(string $id, string $url): array
    {
        return ['id' => $id, 'title' => 'GIF '.$id, 'alt' => 'GIF '.$id, 'url' => $url, 'preview_url' => $url];
    }

    private function provider(array $searchResults, ?array $resolved = null): ExternalGifProvider
    {
        return new class($searchResults, $resolved) implements ExternalGifProvider
        {
            public function __construct(private array $searchResults, private ?array $resolved) {}

            public function configured(): bool
            {
                return true;
            }

            public function name(): string
            {
                return 'fake';
            }

            public function allowedMediaHosts(): array
            {
                return ['media.example.test', '127.0.0.1'];
            }

            public function search(string $query, int $limit): array
            {
                return array_slice($this->searchResults, 0, $limit);
            }

            public function find(string $externalId): ?array
            {
                return $this->resolved;
            }

            public function attribution(): ?string
            {
                return 'Fake GIF Provider';
            }
        };
    }
}
