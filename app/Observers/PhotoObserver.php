<?php

namespace App\Observers;

use App\Models\Photo;

class PhotoObserver
{
    public function deleting(Photo $photo): void
    {
        $photo->deleteAllFiles();
    }
}
