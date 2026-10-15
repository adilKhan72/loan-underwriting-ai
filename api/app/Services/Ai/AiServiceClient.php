<?php

namespace App\Services\Ai;

use App\Enums\DocumentType;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Thin client for ai-service (ADR-0002). Translates HTTP outcomes into the two cases a caller acts on:
 * retry later (AiServiceUnavailable) or stop (AiServiceRejected).
 */
class AiServiceClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly int $timeout,
    ) {}

    /**
     * @return array{status: string, data: array<string, mixed>|null, errors: list<string>, attempts: int, provider: string}
     */
    public function extract(DocumentType $type, string $redactedText): array
    {
        return $this->post('/v1/extract', [
            'document_type' => $type->value,
            'text' => $redactedText,
        ]);
    }

    /**
     * Sends decision facts only (outcome, policy version, rule codes and messages), never applicant data.
     *
     * @param  list<array{code: string, outcome: string, message: string}>  $ruleResults
     * @return array{underwriter_summary: string, applicant_message: string, principal_reasons: list<string>, provider: string}
     */
    public function explain(string $outcome, string $policyVersion, array $ruleResults): array
    {
        return $this->post('/v1/explain', [
            'outcome' => $outcome,
            'policy_version' => $policyVersion,
            'rule_results' => $ruleResults,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function post(string $path, array $payload): array
    {
        try {
            $response = Http::baseUrl($this->baseUrl)
                ->timeout($this->timeout)
                ->acceptJson()
                ->post($path, $payload);
        } catch (ConnectionException $e) {
            throw new AiServiceUnavailable('ai-service unreachable: '.$e->getMessage(), previous: $e);
        }

        if ($response->serverError()) {
            throw new AiServiceUnavailable("ai-service returned {$response->status()}: ".$response->json('detail', ''));
        }

        if ($response->clientError()) {
            throw new AiServiceRejected("ai-service rejected the request ({$response->status()})");
        }

        return $response->json();
    }
}
