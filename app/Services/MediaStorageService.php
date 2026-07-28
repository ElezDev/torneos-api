<?php

namespace App\Services;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MediaStorageService
{
    public function url(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return $this->disk()->url($path);
    }

    public function storeImage(UploadedFile $file, string $directory): string
    {
        $this->assertValidImage($file);

        $filename = Str::uuid()->toString().'.'.$file->guessExtension();
        $path = $file->storeAs($directory, $filename, 'public');

        if (! is_string($path) || $path === '' || ! $this->disk()->exists($path)) {
            throw ValidationException::withMessages([
                'image' => ['No se pudo guardar la imagen en el servidor. Revisá permisos de storage.'],
            ]);
        }

        return $path;
    }

    public function replace(?string $oldPath, UploadedFile $file, string $directory): string
    {
        $path = $this->storeImage($file, $directory);
        $this->delete($oldPath);

        return $path;
    }

    public function delete(?string $path): void
    {
        if (! $path) {
            return;
        }

        $disk = $this->disk();

        if ($disk->exists($path)) {
            $disk->delete($path);
        }
    }

    private function disk(): FilesystemAdapter
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('public');

        return $disk;
    }

    private function assertValidImage(UploadedFile $file): void
    {
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

        if (! in_array($file->getMimeType(), $allowed, true)) {
            throw ValidationException::withMessages([
                'image' => ['Solo se permiten imágenes JPG, PNG, WEBP o GIF.'],
            ]);
        }

        if ($file->getSize() > 5 * 1024 * 1024) {
            throw ValidationException::withMessages([
                'image' => ['La imagen no puede superar 5 MB.'],
            ]);
        }
    }
}
