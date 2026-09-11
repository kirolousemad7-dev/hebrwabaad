<?php

namespace App\Services;

use App\Exceptions\ContentWorkflowException;
use App\Models\ContentMedia;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ContentMediaService
{
    private const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    private const VIDEO_MIMES = ['video/mp4', 'video/webm'];

    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    private const VIDEO_EXTENSIONS = ['mp4', 'webm'];

    private const MAX_IMAGE_BYTES = 8 * 1024 * 1024;

    private const MAX_VIDEO_BYTES = 40 * 1024 * 1024;

    private const MAX_DIMENSION = 8000;

    public function store(User $user, UploadedFile $file, string $collection = 'gallery'): ContentMedia
    {
        $mime = (string) ($file->getMimeType() ?: '');
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $size = (int) $file->getSize();

        $this->assertAllowed($mime, $extension, $size, $file);

        $uuid = (string) Str::uuid();
        $safeName = $uuid.'.'.$extension;
        $path = 'content/'.$safeName;
        $stored = $file->storeAs('content', $safeName, 'local');

        if ($stored === false) {
            throw new ContentWorkflowException('تعذر حفظ الملف.', 500);
        }

        $width = null;
        $height = null;
        $thumbnailPath = null;

        if (in_array($mime, self::IMAGE_MIMES, true)) {
            $dimensions = @getimagesize($file->getRealPath() ?: '');
            if (is_array($dimensions)) {
                $width = (int) $dimensions[0];
                $height = (int) $dimensions[1];
            }
            $thumbnailPath = $this->makeThumbnail($path, $mime, $uuid);
        }

        return ContentMedia::query()->create([
            'id' => $uuid,
            'uploaded_by' => $user->id,
            'disk' => 'local',
            'path' => $path,
            'thumbnail_path' => $thumbnailPath,
            'original_name' => Str::limit((string) $file->getClientOriginalName(), 180, ''),
            'mime_type' => $mime,
            'extension' => $extension,
            'size' => $size,
            'width' => $width,
            'height' => $height,
            'collection' => $collection,
        ]);
    }

    public function attach(ContentMedia $media, object $parent, string $collection): void
    {
        $media->forceFill([
            'attachable_type' => $parent->getMorphClass(),
            'attachable_id' => $parent->getKey(),
            'collection' => $collection,
        ])->save();
    }

    public function ownedBy(User $user, string $uuid): ContentMedia
    {
        $media = ContentMedia::query()->find($uuid);

        if ($media === null || (int) $media->uploaded_by !== (int) $user->id) {
            throw new ContentWorkflowException('الملف غير صالح.', 422);
        }

        return $media;
    }

    private function assertAllowed(string $mime, string $extension, int $size, UploadedFile $file): void
    {
        $isImage = in_array($mime, self::IMAGE_MIMES, true) && in_array($extension, self::IMAGE_EXTENSIONS, true);
        $isVideo = in_array($mime, self::VIDEO_MIMES, true) && in_array($extension, self::VIDEO_EXTENSIONS, true);

        if (! $isImage && ! $isVideo) {
            throw new ContentWorkflowException('نوع الملف غير مسموح.', 422);
        }

        if ($isImage && $size > self::MAX_IMAGE_BYTES) {
            throw new ContentWorkflowException('حجم الصورة يتجاوز الحد المسموح.', 422);
        }

        if ($isVideo && $size > self::MAX_VIDEO_BYTES) {
            throw new ContentWorkflowException('حجم الفيديو يتجاوز الحد المسموح.', 422);
        }

        if ($isImage) {
            $dimensions = @getimagesize($file->getRealPath() ?: '');
            if (! is_array($dimensions)) {
                throw new ContentWorkflowException('تعذر قراءة أبعاد الصورة.', 422);
            }
            if ($dimensions[0] > self::MAX_DIMENSION || $dimensions[1] > self::MAX_DIMENSION) {
                throw new ContentWorkflowException('أبعاد الصورة تتجاوز الحد المسموح.', 422);
            }
        }
    }

    private function makeThumbnail(string $path, string $mime, string $uuid): ?string
    {
        if (! function_exists('imagecreatetruecolor')) {
            return null;
        }

        $absolute = Storage::disk('local')->path($path);
        $source = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($absolute),
            'image/png' => @imagecreatefrompng($absolute),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($absolute) : null,
            'image/gif' => @imagecreatefromgif($absolute),
            default => null,
        };

        if (! $source) {
            return null;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $targetWidth = min(800, $width);
        $targetHeight = (int) round($height * ($targetWidth / max($width, 1)));
        $thumb = imagecreatetruecolor($targetWidth, max($targetHeight, 1));

        if ($thumb === false) {
            imagedestroy($source);

            return null;
        }

        imagecopyresampled($thumb, $source, 0, 0, 0, 0, $targetWidth, max($targetHeight, 1), $width, $height);
        $thumbPath = 'content/thumbs/'.$uuid.'.jpg';
        Storage::disk('local')->makeDirectory('content/thumbs');
        imagejpeg($thumb, Storage::disk('local')->path($thumbPath), 82);
        imagedestroy($thumb);
        imagedestroy($source);

        return $thumbPath;
    }
}
