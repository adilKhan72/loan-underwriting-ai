<?php

namespace App\Models;

use Database\Factories\ApplicantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['first_name', 'last_name', 'email', 'phone', 'date_of_birth', 'ssn'])]
#[Hidden(['ssn', 'date_of_birth'])]
class Applicant extends Model
{
    /** @use HasFactory<ApplicantFactory> */
    use HasFactory, HasUlids;

    protected static function booted(): void
    {
        // Normalise to digits and keep the display-safe last four in sync with the encrypted value.
        static::saving(function (Applicant $applicant) {
            if ($applicant->isDirty('ssn')) {
                $digits = preg_replace('/\D/', '', (string) $applicant->ssn);
                $applicant->ssn = $digits;
                $applicant->ssn_last4 = substr($digits, -4);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ssn' => 'encrypted',
            'date_of_birth' => 'encrypted',
        ];
    }

    public function maskedSsn(): string
    {
        return '***-**-'.$this->ssn_last4;
    }

    /**
     * @return HasMany<LoanApplication, $this>
     */
    public function loanApplications(): HasMany
    {
        return $this->hasMany(LoanApplication::class);
    }
}
