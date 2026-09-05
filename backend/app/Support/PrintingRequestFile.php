<?php

namespace App\Support;

use App\Models\PrintingRequest;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PrintingRequestFile
{
    public static function download(PrintingRequest $printingRequest): StreamedResponse
    {
        $path = $printingRequest->file_path;

        if (! is_string($path) || $path === '' || ! Storage::disk('local')->exists($path)) {
            throw new NotFoundHttpException;
        }

        return Storage::disk('local')->download($path, $printingRequest->original_filename);
    }
}
