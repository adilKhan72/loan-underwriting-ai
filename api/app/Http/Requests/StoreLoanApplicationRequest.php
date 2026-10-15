<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLoanApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'applicant' => ['required', 'array'],
            'applicant.first_name' => ['required', 'string', 'max:100'],
            'applicant.last_name' => ['required', 'string', 'max:100'],
            'applicant.email' => ['required', 'email', 'max:255'],
            'applicant.phone' => ['nullable', 'string', 'max:30'],
            'applicant.date_of_birth' => ['required', 'date_format:Y-m-d', 'before_or_equal:-18 years'],
            'applicant.ssn' => ['required', 'string', 'regex:/^\d{3}-?\d{2}-?\d{4}$/'],

            'amount_requested_cents' => ['required', 'integer', 'min:10000', 'max:5000000'],
            'term_months' => ['required', 'integer', 'between:3,60'],
            'purpose' => ['nullable', 'string', 'max:255'],
            'stated_monthly_income_cents' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'applicant.date_of_birth.before_or_equal' => 'The applicant must be at least 18 years old.',
            'applicant.ssn.regex' => 'The SSN must be 9 digits (dashes optional).',
        ];
    }
}
