<?php

declare(strict_types=1);

namespace Kami\Cocktail\Services;

use Throwable;
use Thumbhash\Thumbhash;
use Illuminate\Support\Str;
use Illuminate\Log\LogManager;
use Kami\Cocktail\Models\Image;
use Illuminate\Support\Facades\Storage;
use Illuminate\Filesystem\FilesystemAdapter;
use Intervention\Image\Image as InterventionImage;
use Kami\Cocktail\Exceptions\ImageUploadException;
use function Thumbhash\extract_size_and_pixels_with_gd;
use Kami\Cocktail\DataObjects\Cocktail\Image as ImageDTO;

class ImageService
{
    protected FilesystemAdapter $disk;

    public function __construct(
        private readonly LogManager $log,
    ) {
        $this->disk = Storage::disk('bar-assistant');
    }

    /**
     * Uploads and saves an image with filepath
     *
     * @param array<\Kami\Cocktail\DataObjects\Cocktail\Image> $requestImages
     * @param int $userId
     * @return array<\Kami\Cocktail\Models\Image>
     * @throws ImageUploadException
     */
    public function uploadAndSaveImages(array $requestImages, int $userId): array
    {
        $images = [];
        foreach ($requestImages as $dtoImage) {
            if (!($dtoImage instanceof ImageDTO) || $dtoImage->file === null) {
                continue;
            }

            $filename = Str::random(40);
            /** @phpstan-ignore-next-line */
            $fileExtension = $dtoImage->file->extension ?? 'jpg';
            $filepath = 'temp/' . $filename . '.' . $fileExtension;

            $thumbHash = null;
            try {
                $thumbHash = $this->generateThumbHash($dtoImage->file);
            } catch (Throwable $e) {
                $this->log->info('[IMAGE_SERVICE] ThumbHash Error | ' . $e->getMessage());
                continue;
            }

            try {
                $this->disk->put($filepath, (string) $dtoImage->file->encode());
            } catch (Throwable $e) {
                $this->log->info('[IMAGE_SERVICE] ' . $e->getMessage());
                continue;
            }

            $image = new Image();
            $image->copyright = $dtoImage->copyright;
            $image->file_path = $filepath;
            $image->file_extension = $fileExtension;
            $image->user_id = $userId;
            $image->sort = $dtoImage->sort;
            $image->placeholder_hash = $thumbHash;
            $image->save();

            $this->log->info('[IMAGE_SERVICE] Image created with id: ' . $image->id);

            $images[] = $image;
        }

        return $images;
    }

    /**
     * Update image by id
     *
     * @param \Kami\Cocktail\DataObjects\Cocktail\Image $imageDTO Image object
     * @return \Kami\Cocktail\Models\Image Database image model
     */
    public function updateImage(ImageDTO $imageDTO): Image
    {
        $image = Image::findOrFail($imageDTO->id);

        if ($imageDTO->copyright) {
            $image->copyright = $imageDTO->copyright;
        }

        if ($imageDTO->sort) {
            $image->sort = $imageDTO->sort;
        }

        $image->save();

        $this->log->info('[IMAGE_SERVICE] Image updated with id: ' . $image->id);

        return $image;
    }

    /**
     * Generates ThumbHash key
     * @see https://evanw.github.io/thumbhash/
     *
     * @param InterventionImage $image
     * @param bool $destroyInstance Used for memory management
     * @return string
     */
    public function generateThumbHash(InterventionImage $image, bool $destroyInstance = false): string
    {
        // Temporary increase memory limit to handle large images
        // TODO: Move to imagick?
        ini_set('memory_limit', '512M');

        if ($destroyInstance) {
            $content = $image->fit(100)->encode(null, 20);
            $image->destroy();
        } else {
            $image->backup();
            $content = $image->fit(100)->encode(null, 20);
            $image->reset();
        }

        [$width, $height, $pixels] = extract_size_and_pixels_with_gd($content);
        $hash = Thumbhash::RGBAToHash($width, $height, $pixels);
        $key = Thumbhash::convertHashToString($hash);

        return $key;
    }
}
