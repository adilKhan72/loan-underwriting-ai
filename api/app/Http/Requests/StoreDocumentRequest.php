<?php

namespace App\Http\Requests;

use App\Enums\DocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentRequest extends FormRequest
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
            'type' => ['required', Rule::enum(DocumentType::class)],
            // Content-sniffed, not extension-based: `mimetypes` checks the actual bytes.
            'file' => ['required', 'file', 'max:10240', 'mimetypes:application/pdf,image/png,image/jpeg'],
        ];
    }
}
