<?php

namespace App\Actions\Orders;

use App\Models\OrderComplaint;
use App\Models\OrderTask;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final class StoreOrderEvidence
{
    /** @param list<UploadedFile> $photos */
    public function handle(OrderTask|OrderComplaint $subject, array $photos): void
    {
        if ($subject->media()->where('collection_name', 'evidence')->count() + count($photos) > 3) {
            throw ValidationException::withMessages(['photos' => 'Maksimal tiga foto untuk setiap tugas atau komplain.']);
        }
        $added = [];
        try {
            foreach ($photos as $photo) {
                $media = $subject->addMedia($photo)->usingFileName(Str::uuid().'.'.$photo->extension())
                    ->toMediaCollection('evidence', 'local');
                $added[] = $media;
                DB::afterRollBack(static fn () => $media->delete());
            }
        } catch (Throwable $exception) {
            foreach ($added as $media) {
                $media->delete();
            }
            throw $exception;
        }
    }
}
