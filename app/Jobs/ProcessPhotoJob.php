<?php

namespace App\Jobs;

use App\Enums\PhotoProcessingStatus;
use App\Models\Photo;
use App\Services\Photo\ImageProcessorService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessPhotoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 120, 300];

    public function __construct(
        public Photo $photo
    ) {}

    public function handle(ImageProcessorService $processor): void
    {
        $this->photo->update([
            'processing_status' => PhotoProcessingStatus::PROCESSING,
        ]);

        try {
            $webPath = $processor->generateWebVersion($this->photo->original_path);

            if (! $webPath) {
                throw new Exception('Failed to generate web version');
            }

            $thumbnailPath = $processor->generateThumbnail($this->photo->original_path);

            if (! $thumbnailPath) {
                throw new Exception('Failed to generate thumbnail');
            }

            $ogImagePath = $processor->generateOgImage(
                $this->photo->original_path,
                $this->photo->title,
                $this->photo->exif_summary
            );

            $this->photo->update([
                'web_path' => $webPath,
                'thumbnail_path' => $thumbnailPath,
                'og_image_path' => $ogImagePath,
                'processing_status' => PhotoProcessingStatus::COMPLETED,
            ]);
        } catch (Exception $e) {
            Log::error('Photo processing failed', [
                'photo_id' => $this->photo->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        $this->photo->update([
            'processing_status' => PhotoProcessingStatus::FAILED,
        ]);

        Log::error('Photo processing job failed permanently', [
            'photo_id' => $this->photo->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
