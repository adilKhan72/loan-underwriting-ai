<?php

namespace App\Support\Pii;

/**
 * Replaces personal identifiers with typed placeholders before text leaves the API (ADR-0004).
 *
 * Pattern-based by design: predictable, fast and unit-testable. Order matters, because the most
 * specific patterns run first so a labelled routing number isn't later re-matched as an account number.
 * Underwriting never needs these values from the model; identity comes from the application form.
 */
class PiiRedactor
{
    /**
     * @var array<string, list<string>>
     */
    private const PATTERNS = [
        'SSN' => [
            '/\b\d{3}-\d{2}-\d{4}\b/',
            '/\b(?:SSN|SS#|Social Security(?: No\.?| Number)?)\s*[:#]?\s*\d{9}\b/i',
        ],
        'ROUTING_NUMBER' => [
            '/\b(?:Routing|RTN|ABA)(?: No\.?| Number| #)?\s*[:#]?\s*\d{9}\b/i',
        ],
        'ACCOUNT_NUMBER' => [
            '/\b(?:Account|Acct)(?: No\.?| Number| #)?\s*[:#]?\s*[X*]*\d[\d\- ]{2,16}\d\b/i',
        ],
        'CARD_NUMBER' => [
            '/\b(?:\d{4}[ -]){3}\d{4}\b/',
        ],
        'EMAIL' => [
            '/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i',
        ],
        'PHONE' => [
            '/(?<!\d)(?:\+1[ .-]?)?\(?\d{3}\)?[ .-]\d{3}[ .-]\d{4}(?!\d)/',
        ],
        'DATE_OF_BIRTH' => [
            '/\b(?:DOB|Date of Birth|Birth ?Date)\s*[:#]?\s*\d{1,2}[\/.-]\d{1,2}[\/.-]\d{2,4}\b/i',
        ],
    ];

    public function redact(string $text): RedactionResult
    {
        $counts = [];

        foreach (self::PATTERNS as $type => $patterns) {
            foreach ($patterns as $pattern) {
                $text = preg_replace_callback($pattern, function () use ($type, &$counts) {
                    $counts[$type] = ($counts[$type] ?? 0) + 1;

                    return "[{$type}]";
                }, $text);
            }
        }

        return new RedactionResult($text, $counts);
    }
}
