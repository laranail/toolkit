<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Traits;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

trait FileProcessingTrait
{
    /**
     * Get file contents.
     *
     * @return string
     */
    public function getFile(string $filename, string $directory = 'uploads')
    {
        $filePath = $directory . '/' . $filename;

        if (Storage::exists($filePath)) {
            $fileContents = Storage::get($filePath);

            return $fileContents;
        }

        return 'File not found';
    }

    /**
     * Upload a file.
     *
     * @return string
     */
    public function uploadFile(UploadedFile $file, string $directory = 'uploads')
    {
        $filename = uniqid() . '_' . $file->getClientOriginalName();

        $file->storeAs($directory, $filename);

        return $filename;
    }

    /**
     * Upload multiple files.
     *
     * @return array
     */
    public function uploadFiles(array $files, string $directory = 'uploads')
    {
        $filenames = [];

        foreach ($files as $file) {
            $filenames[] = $this->uploadFile($file, $directory);
        }

        return $filenames;
    }

    /**
     * Delete a file.
     *
     * @return void
     */
    public function deleteFile(string $filename, string $directory = 'uploads')
    {
        Storage::delete($directory . '/' . $filename);
    }

    /**
     * Delete multiple files.
     *
     * @return void
     */
    public function deleteFiles(array $filenames, string $directory = 'uploads')
    {
        foreach ($filenames as $filename) {
            $this->deleteFile($filename, $directory);
        }
    }
}
