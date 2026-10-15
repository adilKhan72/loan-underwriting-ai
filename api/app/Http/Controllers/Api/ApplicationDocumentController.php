<?php

namespace App\Http\Controllers\Api;

use App\Audit\AuditLog;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Models\LoanApplication;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class ApplicationDocumentController extends Controller
{
    /**
     * Files go to the private `local` disk under a generated name; the client's filename is only kept as metadata.
     */
    public function store(StoreDocumentRequest $request, LoanApplication $application, AuditLog $audit): JsonResponse
    {
        abort_unless($application->status->acceptsDocuments(), 409, 'Documents can only be added to draft applications.');

        $file = $request->file('file');
        $sha256 = hash_file('sha256', $file->getRealPath());

        abort_if(
            $application->documents()->where('sha256', $sha256)->exists(),
            409,
            'This file is already attached to the application.'
        );

        $path = $file->storeAs(
            "applications/{$application->id}",
            Str::ulid().'.'.$file->extension(),
            'local'
        );

        $document = $application->documents()->create([
            'type' => $request->validated('type'),
            'disk' => 'local',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'sha256' => $sha256,
        ]);

        $audit->record($application, 'document.uploaded', [
            'document_id' => $document->id,
            'type' => $document->type->value,
            'size_bytes' => $document->size_bytes,
            'sha256' => $document->sha256,
        ], $request->user());

        return (new DocumentResource($document))->response()->setStatusCode(201);
    }
}
