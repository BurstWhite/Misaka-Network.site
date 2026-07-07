<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class AdminImageUploadService
{
    public function store(UploadedFile $file, string $bucket): array
    {
        $bucket = in_array($bucket, ['knowledge', 'notice'], true) ? $bucket : 'knowledge';
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'jpg');
        $extension = $extension === 'jpeg' ? 'jpg' : $extension;
        $datePath = now()->format('Y/m');
        $uploadRoot = base_path(".docker/.data/uploads/{$bucket}/{$datePath}");

        File::ensureDirectoryExists($uploadRoot, 0755, true);

        $filename = Str::uuid()->toString() . ".{$extension}";
        $file->move($uploadRoot, $filename);

        $relativePath = "uploads/{$bucket}/{$datePath}/{$filename}";
        $url = "/{$relativePath}";
        $baseUrl = rtrim((string) admin_setting('app_url', ''), '/');

        return [
            'url' => $url,
            'absolute_url' => $baseUrl ? $baseUrl . $url : url($url),
            'markdown' => "![图片]({$url})",
        ];
    }
}
