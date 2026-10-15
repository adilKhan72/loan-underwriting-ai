<?php

namespace Tests\Unit;

use App\Support\Pii\PiiRedactor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PiiRedactorTest extends TestCase
{
    /**
     * @return array<string, array{string, string, string}>
     */
    public static function identifiers(): array
    {
        return [
            'dashed ssn' => ['Employee SSN: 900-12-3456 Dept 4', 'Employee SSN: [SSN] Dept 4', 'SSN'],
            'labelled 9-digit ssn' => ['Social Security Number: 900123456', '[SSN]', 'SSN'],
            'routing number' => ['Routing #: 121000358', '[ROUTING_NUMBER]', 'ROUTING_NUMBER'],
            'account number' => ['Account No. 0012345678 checking', '[ACCOUNT_NUMBER] checking', 'ACCOUNT_NUMBER'],
            'masked account number' => ['Acct: XXXX1234', '[ACCOUNT_NUMBER]', 'ACCOUNT_NUMBER'],
            'card number' => ['Card 4111 1111 1111 1111 exp', 'Card [CARD_NUMBER] exp', 'CARD_NUMBER'],
            'email' => ['Contact jane.doe@example.com today', 'Contact [EMAIL] today', 'EMAIL'],
            'phone' => ['Call (555) 010-2000 now', 'Call [PHONE] now', 'PHONE'],
            'date of birth' => ['DOB: 04/12/1990', '[DATE_OF_BIRTH]', 'DATE_OF_BIRTH'],
        ];
    }

    #[DataProvider('identifiers')]
    public function test_it_redacts_each_identifier_type(string $input, string $expected, string $type): void
    {
        $result = (new PiiRedactor)->redact($input);

        $this->assertSame($expected, $result->text);
        $this->assertSame(1, $result->counts[$type]);
    }

    public function test_it_keeps_the_numbers_underwriting_needs(): void
    {
        $paystub = "Pay Period: 09/01/2026 - 09/14/2026\nGROSS PAY 2,107.00 YTD 36,750.00\nNET PAY 1,577.95\nEmployee ID: 48213";

        $result = (new PiiRedactor)->redact($paystub);

        $this->assertSame($paystub, $result->text);
        $this->assertSame(0, $result->total());
    }

    public function test_it_counts_every_replacement_without_keeping_values(): void
    {
        $result = (new PiiRedactor)->redact('SSN 900-12-3456 and 900-65-4321, email a@b.co');

        $this->assertSame(['SSN' => 2, 'EMAIL' => 1], $result->counts);
        $this->assertStringNotContainsString('900', $result->text);
    }

    public function test_routing_number_is_not_double_counted_as_an_account_number(): void
    {
        $result = (new PiiRedactor)->redact('Routing Number: 121000358 Account Number: 000123456789');

        $this->assertSame('[ROUTING_NUMBER] [ACCOUNT_NUMBER]', $result->text);
        $this->assertSame(['ROUTING_NUMBER' => 1, 'ACCOUNT_NUMBER' => 1], $result->counts);
    }
}
