<?php

namespace App\Services\Chat;

use App\Models\Message;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ovozli xabar fayllari.
 *
 * Brauzer qaysi formatda yozsa (Chrome/Android — WebM yoki MP4, Safari — MP4/AAC, Firefox — WebM/Ogg),
 * shu formatda saqlanadi. Faylning turi kengaytma yoki brauzer aytganiga qarab emas,
 * ichidagi "imzo" (magic bytes) bo‘yicha aniqlanadi. Fayllar ommaviy diskda emas —
 * faqat suhbat ishtirokchilariga himoyalangan manzil orqali beriladi.
 */
class VoiceStore
{
    /** @return array{path: string, mime: string} */
    public function store(UploadedFile $file): array
    {
        [$ext, $mime] = $this->detect($file) ?? throw ValidationException::withMessages([
            'voice' => 'Ovozli xabar formati qo‘llab-quvvatlanmaydi.',
        ]);

        $path = 'chat/voice/'.now()->format('Y/m').'/'.Str::uuid().'.'.$ext;
        Storage::disk($this->disk())->put($path, (string) file_get_contents($file->getRealPath()));

        return ['path' => $path, 'mime' => $mime];
    }

    public function delete(?string $path): void
    {
        if ($path) {
            Storage::disk($this->disk())->delete($path);
        }
    }

    /** Faylni uzatish: lokal diskda — Range so‘rovlari bilan (aylantirib eshitish ishlaydi). */
    public function response(Message $message): Response
    {
        $disk = Storage::disk($this->disk());
        abort_unless($message->voice_path && $disk->exists($message->voice_path), 404);

        $headers = [
            'Content-Type' => $message->voice_mime ?: 'application/octet-stream',
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if (config('filesystems.disks.'.$this->disk().'.driver') === 'local') {
            return response()->file($disk->path($message->voice_path), $headers);
        }

        return $disk->response($message->voice_path, null, $headers);
    }

    /** @return array{0: string, 1: string}|null [kengaytma, mime] */
    private function detect(UploadedFile $file): ?array
    {
        $head = (string) @file_get_contents($file->getRealPath(), false, null, 0, 16);
        if (strlen($head) < 12) {
            return null;
        }

        return match (true) {
            str_starts_with($head, "\x1A\x45\xDF\xA3") => ['webm', 'audio/webm'],
            str_starts_with($head, 'OggS') => ['ogg', 'audio/ogg'],
            substr($head, 4, 4) === 'ftyp' => ['m4a', 'audio/mp4'],
            str_starts_with($head, 'RIFF') && substr($head, 8, 4) === 'WAVE' => ['wav', 'audio/wav'],
            str_starts_with($head, 'ID3') || (ord($head[0]) === 0xFF && (ord($head[1]) & 0xE0) === 0xE0) => ['mp3', 'audio/mpeg'],
            default => null,
        };
    }

    private function disk(): string
    {
        return (string) config('fikrlash.chat.voice_disk');
    }
}
