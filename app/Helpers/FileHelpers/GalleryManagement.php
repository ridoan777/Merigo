<?php

namespace App\Helpers\FileHelpers;

use App\Models\Workflows\Bar\Event;
use App\Models\Workflows\Bar\EventGallery;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Illuminate\Support\Facades\{Log, Storage};
use Str;

class GalleryManagement
{
	public static function handle(array $validated, string $GALLERY_MODEL_NAME, ?Model $parentObject, string $parentForeignKey, ?string $FILE_FOLDER, ?string $FILE_NAME)
	{
		$DISK_FOLDER = config('filesystems.default');
		$storedGallery = [];

		foreach ($validated['image'] as $gallery) {

			$file = $gallery['file'] ?? null;
			$removeFile = $gallery['remove_image'] ?? false;

			$existingGallery = !empty($gallery['id']) ? $GALLERY_MODEL_NAME::findOrFail((int) $gallery['id']) : null;

			// ------------ FILE HANDLING ------------
			if ($file || $removeFile) {
				$storedfile = FileManagement::handleFile(
					$file,
					$FILE_NAME,
					$existingGallery?->filepath ?? null,
					$FILE_FOLDER,
					$DISK_FOLDER,
					$removeFile
				);
			} else {
				$storedfile = ['path' => $existingGallery?->filepath ?? null];
			}
			// ---------------- FILE HANDLING ----------------

			// ---------------- REMOVE IMAGE ----------------
			if($removeFile && $existingGallery){
				$existingGallery?->delete();
				continue;
			}
			// ---------------- REMOVE IMAGE ----------------

			$storedGallery[] = $GALLERY_MODEL_NAME::updateOrCreate(
				[
					'id' => $gallery['id'] ?? null,
				],
				[
					$parentForeignKey => $parentObject?->id,
					'filename' => $storedfile['file_name'] ?? null,
					'filepath' => $storedfile['path'] ?? null,
					'metadata' => $storedfile['meta'] ?? null,
					'status' => 1,
				]
			);
		}

		return $storedGallery;
	}
}
/*
	USE CASE:
	
	$gallery = GalleryManagement::handle($validated, EventGallery::class, $event, 'event_id', $IMAGE_FOLDER, $FILE_NAME);
*/