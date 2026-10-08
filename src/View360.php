<?php

namespace jasbros2568\View360;

use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use RuntimeException;

/**
 * Helpers around the frames of a 360° spin: listing them from a disk, storing uploads and validating them.
 * A spin is simply a folder of images taken around the object, shown in natural file-name order.
 */
class View360
{
    public function __construct(private readonly FilesystemFactory $filesystems) {}

    /**
     * Public URLs of the frames in a directory, in natural order (frame-2 before frame-10).
     *
     * @return list<string>
     */
    public function frames(string $directory, ?string $disk = null): array
    {
        $storage = $this->disk($disk);

        return array_map(fn (string $path): string => $storage->url($path), $this->paths($directory, $disk));
    }

    /**
     * Storage paths of the frames in a directory, in natural order.
     *
     * @return list<string>
     */
    public function paths(string $directory, ?string $disk = null): array
    {
        $extensions = array_map('strtolower', (array) config('view360.extensions', []));
        $paths = array_values(array_filter(
            $this->disk($disk)->files(trim($directory, '/')),
            fn (string $path): bool => in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), $extensions, true),
        ));

        natcasesort($paths);

        return array_values($paths);
    }

    /**
     * Store uploaded frames as 001.jpg, 002.jpg… in the natural order of their original file names
     * (so the order the photographer exported them in is kept) and return the stored paths.
     *
     * Any frames already in the directory are replaced unless $replace is false, in which case the new
     * frames are appended after the existing ones.
     *
     * @param  iterable<UploadedFile>  $files
     * @return list<string>
     */
    public function store(iterable $files, string $directory, ?string $disk = null, bool $replace = true): array
    {
        $files = is_array($files) ? $files : iterator_to_array($files, false);

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                throw new InvalidArgumentException('View360::store() expects uploaded files.');
            }
        }

        usort($files, fn (UploadedFile $a, UploadedFile $b): int => strnatcasecmp($a->getClientOriginalName(), $b->getClientOriginalName()));

        $directory = trim($directory, '/');
        $storage = $this->disk($disk);
        $offset = 0;

        if ($replace) {
            $this->delete($directory, $disk);
        } else {
            $offset = count($this->paths($directory, $disk));
        }

        $width = max(3, strlen((string) ($offset + count($files))));
        $stored = [];

        foreach ($files as $index => $file) {
            // The extension comes from the file's real type, never from the name the browser sent.
            $name = str_pad((string) ($offset + $index + 1), $width, '0', STR_PAD_LEFT).'.'.($file->extension() ?: 'jpg');
            $path = $file->storeAs($directory, $name, ['disk' => $this->diskName($disk)]);

            if ($path === false) {
                throw new RuntimeException("Could not store the 360° frame [$name] in [$directory].");
            }

            $stored[] = $path;
        }

        return $stored;
    }

    /** Delete every frame of a spin (other files in the directory are left alone). */
    public function delete(string $directory, ?string $disk = null): void
    {
        if ($paths = $this->paths($directory, $disk)) {
            $this->disk($disk)->delete($paths);
        }
    }

    /**
     * Validation rules for a multi-file "frames" input, built from the package config.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(string $attribute = 'frames', int $min = 2): array
    {
        return [
            $attribute => ['required', 'array', 'min:'.$min, 'max:'.(int) config('view360.max_frames', 144)],
            $attribute.'.*' => ['image', 'mimes:'.implode(',', (array) config('view360.extensions')), 'max:'.(int) config('view360.max_size', 5120)],
        ];
    }

    private function disk(?string $disk): Filesystem
    {
        return $this->filesystems->disk($this->diskName($disk));
    }

    private function diskName(?string $disk): string
    {
        return $disk ?? (string) config('view360.disk', 'public');
    }
}
