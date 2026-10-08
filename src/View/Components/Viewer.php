<?php

namespace jasbros2568\View360\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use jasbros2568\View360\View360;

/**
 * <x-view360 :images="$urls" /> or <x-view360 directory="products/42/spin" />
 */
class Viewer extends Component
{
    /** @var list<string> */
    public array $frames;

    /** @var array<string, mixed> Options read by the JavaScript viewer. */
    public array $options;

    public string $aspect;

    public ?string $hint;

    /**
     * @param  iterable<string>|null  $images  Frame URLs in rotation order.
     * @param  string|null  $directory  Or: a directory of frames on $disk (natural file-name order).
     */
    public function __construct(
        View360 $view360,
        ?iterable $images = null,
        ?string $directory = null,
        ?string $disk = null,
        public string $alt = '360° view',
        ?int $sensitivity = null,
        ?bool $autoplay = null,
        ?int $speed = null,
        ?bool $reverse = null,
        ?bool $loop = null,
        ?string $aspect = null,
        string|bool|null $hint = null,
        int $start = 1,
    ) {
        $defaults = (array) config('view360.defaults', []);

        $this->frames = $images !== null
            ? array_values(array_filter(is_array($images) ? $images : iterator_to_array($images, false)))
            : ($directory !== null ? $view360->frames($directory, $disk) : []);

        $this->aspect = $aspect ?? (string) ($defaults['aspect'] ?? '1 / 1');
        $this->hint = $hint === false ? null : (is_string($hint) ? $hint : ($defaults['hint'] ?? null));
        $this->options = [
            'frames' => $this->frames,
            'sensitivity' => max(1, $sensitivity ?? (int) ($defaults['sensitivity'] ?? 8)),
            'autoplay' => $autoplay ?? (bool) ($defaults['autoplay'] ?? false),
            'speed' => max(1, $speed ?? (int) ($defaults['speed'] ?? 12)),
            'reverse' => $reverse ?? (bool) ($defaults['reverse'] ?? false),
            'loop' => $loop ?? (bool) ($defaults['loop'] ?? true),
            'start' => min(max(1, $start), max(1, count($this->frames))) - 1,
        ];
    }

    /** How the CSS and JS reach the page: "published", "inline" or "none". */
    public function assetMode(): string
    {
        $mode = (string) config('view360.assets', 'auto');

        if ($mode === 'auto') {
            return is_file(public_path('vendor/view360/view360.js')) ? 'published' : 'inline';
        }

        return in_array($mode, ['published', 'inline', 'none'], true) ? $mode : 'inline';
    }

    /** Contents of a bundled asset, for inline mode. */
    public function assetContents(string $file): string
    {
        return (string) file_get_contents(__DIR__.'/../../../resources/dist/'.$file);
    }

    /** URL of a published asset, cache-busted with the file's modification time. */
    public function assetUrl(string $file): string
    {
        $path = public_path('vendor/view360/'.$file);

        return asset('vendor/view360/'.$file).(is_file($path) ? '?v='.filemtime($path) : '');
    }

    public function render(): View
    {
        return view('view360::components.viewer');
    }
}
