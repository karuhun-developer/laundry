<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\OrderComplaint;
use App\Models\OrderTask;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OrderEvidenceController extends Controller
{
    public function __invoke(Request $request, Media $media): BinaryFileResponse
    {
        $subject = $media->model;
        abort_unless(($subject instanceof OrderTask || $subject instanceof OrderComplaint)
            && $media->collection_name === 'evidence' && $media->disk === 'local', 404);
        Gate::authorize('view', $subject->order);

        return response()->file($media->getPath(), ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
