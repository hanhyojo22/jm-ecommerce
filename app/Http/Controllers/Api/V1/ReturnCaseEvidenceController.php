<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ReturnCaseEvidence;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReturnCaseEvidenceController extends Controller
{
    /**
     * Serve return evidence by its short-lived signed URL.
     */
    public function show(ReturnCaseEvidence $evidence): StreamedResponse
    {
        return Storage::disk('chat_attachments')->download($evidence->path, $evidence->original_name);
    }
}
