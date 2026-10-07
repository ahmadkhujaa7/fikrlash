<?php

namespace App\Services\Chat;

use App\Models\MessageAttachment;
use App\Services\Media\ImageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chatdagi rasm va videolar.
 *  - Rasm GD orqali qayta encode qilinadi (WebP): EXIF/GPS o‘chadi, ichiga yashirilgan fayl zararsizlanadi.
 *  - Video o‘zgarishsiz saqlanadi, lekin turi ichki imzosi bo‘yicha tekshiriladi (MP4/MOV/WebM);
 *    muqova (birinchi kadr) brauzerda olinadi va rasm sifatida saqlanadi.
 *  - Fayllar yopiq diskda; faqat suhbat ishtirokchilariga himoyalangan manzil orqali beriladi.
 */
class ChatMediaStore
{
    public function __construct(private ImageService $images) {}

    /** @return array{kind: string, path: string, mime: string, size: int, width: int, height: int} */
    public function storeImage(UploadedFile $file): array
    {
        $stored = $this->images->storeTo($file, $this->disk(), 'chat/images/'.now()->format('Y/m'), (int) config('fikrlash.chat.image_max_width'));

        return ['kind' => 'image', 'mime' => 'image/webp'] + $stored;
    }

    /** @return array{kind: string, path: string, mime: string, size: int, width: int|null, height: int|null, duration: int|null, poster_path: string|null} */
    public function storeVideo(UploadedFile $file, ?UploadedFile $poster, ?int $duration, ?int $width, ?int $height): array
    {
        [$ext, $mime] = $this->detectVideo($file) ?? throw ValidationException::withMessages([
            'files' => 'Video formati qo‘llab-quvvatlanmaydi (MP4, MOV yoki WebM bo‘lsin).',
        ]);

        $path = 'chat/videos/'.now()->format('Y/m').'/'.Str::uuid().'.'.$ext;
        $stream = fopen($file->getRealPath(), 'rb');
        Storage::disk($this->disk())->writeStream($path, $stream);
        is_resource($stream) && fclose($stream);

        $posterPath = null;
        if ($poster) {
            try {
                $posterPath = $this->images->storeTo($poster, $this->disk(), 'chat/posters/'.now()->format('Y/m'), 960)['path'];
            } catch (ValidationException) {
                $posterPath = null; // muqovasiz ham ishlaydi
            }
        }

        $max = (int) config('fikrlash.chat.video_max_seconds');

        return [
            'kind' => 'video', 'path' => $path, 'mime' => $mime, 'size' => (int) $file->getSize(),
            'width' => $width ? min($width, 10000) : null, 'height' => $height ? min($height, 10000) : null,
            'duration' => $duration !== null ? max(0, min($duration, $max)) : null, 'poster_path' => $posterPath,
        ];
    }

    /** @param  array<int, array{path: string, poster_path?: string|null}>  $stored */
    public function deleteMany(array $stored): void
    {
        foreach ($stored as $item) {
            $this->delete($item['path'] ?? null);
            $this->delete($item['poster_path'] ?? null);
        }
    }

    public function delete(?string $path): void
    {
        if ($path) {
            Storage::disk($this->disk())->delete($path);
        }
    }

    /** Uzatish: lokal diskda — Range so‘rovlari bilan (videoni aylantirib ko‘rish ishlaydi). */
    public function response(MessageAttachment $attachment, bool $poster = false): Response
    {
        $path = $poster ? $attachment->poster_path : $attachment->path;
        $disk = Storage::disk($this->disk());
        abort_unless($path && $disk->exists($path), 404);

        $headers = [
            'Content-Type' => $poster ? 'image/webp' : $attachment->mime,
            'Cache-Control' => 'private, max-age=604800, immutable',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline',
        ];

        if (config('filesystems.disks.'.$this->disk().'.driver') === 'local') {
            return response()->file($disk->path($path), $headers);
        }

        return $disk->response($path, null, $headers);
    }

    /** @return array{0: string, 1: string}|null [kengaytma, mime] */
    private function detectVideo(UploadedFile $file): ?array
    {
        $head = (string) @file_get_contents($file->getRealPath(), false, null, 0, 16);
        if (strlen($head) < 12) {
            return null;
        }
        if (str_starts_with($head, "\x1A\x45\xDF\xA3")) {
            return ['webm', 'video/webm'];
        }
        if (substr($head, 4, 4) === 'ftyp') {
            return substr($head, 8, 2) === 'qt' ? ['mov', 'video/quicktime'] : ['mp4', 'video/mp4'];
        }

        return null;
    }

    private function disk(): string
    {
        return (string) config('fikrlash.chat.media_disk');
    }
}
