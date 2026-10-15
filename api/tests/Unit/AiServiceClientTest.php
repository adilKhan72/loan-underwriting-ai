<?php

namespace Tests\Unit;

use App\Enums\DocumentType;
use App\Services\Ai\AiServiceClient;
use App\Services\Ai\AiServiceRejected;
use App\Services\Ai\AiServiceUnavailable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiServiceClientTest extends TestCase
{
    private function client(): AiServiceClient
    {
        return new AiServiceClient('http://ai-service:8001', 5);
    }

    public function test_it_posts_the_document_type_and_text(): void
    {
        Http::fake(['http://ai-service:8001/v1/extract' => Http::response(['status' => 'ok', 'data' => [], 'errors' => [], 'attempts' => 1, 'provider' => 'fixture'])]);

        $result = $this->client()->extract(DocumentType::BankStatement, 'redacted text');

        $this->assertSame('ok', $result['status']);
        Http::assertSent(fn ($request) => $request['document_type'] === 'bank_statement' && $request['text'] === 'redacted text');
    }

    public function test_server_errors_are_transient(): void
    {
        Http::fake(['*' => Http::response(['detail' => 'openai error 429'], 502)]);

        $this->expectException(AiServiceUnavailable::class);
        $this->expectExceptionMessage('openai error 429');

        $this->client()->extract(DocumentType::Paystub, 'x');
    }

    public function test_connection_failures_are_transient(): void
    {
        Http::fake(fn () => throw new ConnectionException('connection refused'));

        $this->expectException(AiServiceUnavailable::class);

        $this->client()->extract(DocumentType::Paystub, 'x');
    }

    public function test_client_errors_are_permanent(): void
    {
        Http::fake(['*' => Http::response(['detail' => []], 422)]);

        $this->expectException(AiServiceRejected::class);

        $this->client()->extract(DocumentType::Paystub, 'x');
    }
}
