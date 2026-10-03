<?php

namespace App\Services\Media;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Rasmlarni xavfsiz qayta ishlash.
 *
 * Har bir rasm GD orqali qayta encode qilinadi (WebP):
 *  - EXIF/GPS va boshqa metadata o‘chadi (privacy);
 *  - rasm ichiga yashirilgan "polyglot" fayllar zararsizlanadi;
 *  - fayl nomi UUID — original nomga ishonilmaydi.
 * Storage disk config orqali: local "public" yoki production'da S3-compatible.
 */
class ImageService
{
    public function storePostImage(UploadedFile $file): string
    {
        $image = $this->load($file);
        $image = $this->resizeToWidth($image, (int) config('fikrlash.media.post_max_width'));

        return $this->save($image, 'posts/'.now()->format('Y/m'));
    }

    public function storeAvatar(UploadedFile $file): string
    {
        $image = $this->load($file);
        $image = $this->squareCrop($image, (int) config('fikrlash.media.avatar_size'));

        return $this->save($image, 'avatars');
    }

    public function delete(?string $path): void
    {
        if ($path) {
            Storage::disk($this->disk())->delete($path);
        }
    }

    private function load(UploadedFile $file): GdImage
    {
        $info = @getimagesize($file->getRealPath());
        $allowed = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];

        if (! $info || ! in_array($info[2], $allowed, true)) {
            throw ValidationException::withMessages(['image' => 'Faqat JPG, PNG yoki WEBP rasm yuklash mumkin.']);
        }

        // Decompression bomb: kichik fayl, lekin ulkan o‘lcham.
        if (config('fikrlash.media.max_pixels') < $info[0] * $info[1]) {
            throw ValidationException::withMessages(['image' => 'Rasm o‘lchami juda katta.']);
        }

        $image = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if (! $image instanceof GdImage) {
            throw ValidationException::withMessages(['image' => 'Rasmni o‘qib bo‘lmadi. Boshqa fayl tanlang.']);
        }

        if ($info[2] === IMAGETYPE_JPEG) {
            $image = $this->applyExifOrientation($image, $file->getRealPath());
        }

        imagepalettetotruecolor($image);
        imagealphablending($image, true);
        imagesavealpha($image, true);

        return $image;
    }

    private function applyExifOrientation(GdImage $image, string $path): GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $orientation = (int) (@exif_read_data($path)['Orientation'] ?? 1);

        return match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };
    }

    private function resizeToWidth(GdImage $image, int $maxWidth): GdImage
    {
        $width = imagesx($image);
        if ($width <= $maxWidth) {
            return $image;
        }

        $scaled = imagescale($image, $maxWidth, -1, IMG_BICUBIC);

        return $scaled ?: $image;
    }

    private function squareCrop(GdImage $image, int $size): GdImage
    {
        $w = imagesx($image);
        $h = imagesy($image);
        $side = min($w, $h);

        $canvas = imagecreatetruecolor($size, $size);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagecopyresampled($canvas, $image, 0, 0, intdiv($w - $side, 2), intdiv($h - $side, 2), $size, $size, $side, $side);

        return $canvas;
    }

    private function save(GdImage $image, string $directory): string
    {
        ob_start();
        imagewebp($image, null, (int) config('fikrlash.media.webp_quality'));
        $binary = (string) ob_get_clean();

        $path = $directory.'/'.Str::uuid().'.webp';
        Storage::disk($this->disk())->put($path, $binary, ['visibility' => 'public', 'ContentType' => 'image/webp']);

        return $path;
    }

    private function disk(): string
    {
        return config('fikrlash.media.disk');
    }
}
