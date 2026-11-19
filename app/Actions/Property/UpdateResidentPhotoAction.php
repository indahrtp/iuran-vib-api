<?php

namespace App\Actions\Property;

use App\Models\Resident;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class UpdateResidentPhotoAction
{
    public function store(Resident $resident, UploadedFile $file): Resident
    {
        $disk = config('vib.private_disk');
        $old  = $resident->photo_path;

        $path = $file->store('residents/photos', $disk);
        $resident->update(['photo_path' => $path]);

        if ($old) {
            Storage::disk($disk)->delete($old);
        }

        return $resident->refresh();
    }

    public function remove(Resident $resident): Resident
    {
        if ($resident->photo_path) {
            Storage::disk(config('vib.private_disk'))->delete($resident->photo_path);
            $resident->update(['photo_path' => null]);
        }

        return $resident->refresh();
    }
}