<?php

namespace Tests\Unit;

use App\Models\Applicant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicantTest extends TestCase
{
    use RefreshDatabase;

    public function test_ssn_is_normalised_to_digits_and_last_four_kept_in_sync(): void
    {
        $applicant = Applicant::factory()->create(['ssn' => '900-55-1234']);

        $this->assertSame('900551234', $applicant->ssn);
        $this->assertSame('1234', $applicant->ssn_last4);
        $this->assertSame('***-**-1234', $applicant->maskedSsn());

        $applicant->update(['ssn' => '900 55 9999']);

        $this->assertSame('9999', $applicant->fresh()->ssn_last4);
    }

    public function test_sensitive_fields_are_hidden_from_serialisation(): void
    {
        $array = Applicant::factory()->create()->toArray();

        $this->assertArrayNotHasKey('ssn', $array);
        $this->assertArrayNotHasKey('date_of_birth', $array);
    }
}
