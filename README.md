# Laravel 360 View

A 360° product viewer for Laravel. Give it a set of photos taken around an object and visitors can drag, swipe or use the arrow keys to spin it.

- One Blade component: `<x-view360 />`
- No JavaScript dependencies and no build step
- Mouse, touch and keyboard control, optional autoplay
- Frames load only when the viewer is near the screen, with a progress bar
- Helpers to validate, store and list uploaded frames on any filesystem disk

Requires PHP 8.2+ and Laravel 11, 12 or 13.

## Installation

```bash
composer require jasbros2568/laravel-360-view
```

That is all: the service provider is discovered automatically, and the component inlines its small CSS and JS the first time it is used on a page.

For production you will usually want the assets served as cacheable files instead:

```bash
php artisan vendor:publish --tag=view360-assets
```

Once `public/vendor/view360` exists the component links to those files automatically. Re-run the command after updating the package (or add `@php artisan vendor:publish --tag=laravel-assets --ansi --force` to the `post-update-cmd` scripts in your `composer.json`).

## Usage

### From a list of image URLs

```blade
<x-view360 :images="$urls" alt="Solitaire engagement ring" />
```

`$urls` is an array of frame URLs in rotation order. 24 to 72 frames gives a smooth spin.

### From a storage directory

```blade
<x-view360 directory="products/42/spin" />
<x-view360 directory="products/42/spin" disk="s3" />
```

Every image in the directory is used, in natural file-name order (`frame-2.jpg` comes before `frame-10.jpg`).

### Fallback when there are no frames

Whatever you put inside the component is shown when there are no frames:

```blade
<x-view360 :directory="'products/'.$product->id.'/spin'">
    <img src="{{ $product->image_url }}" alt="{{ $product->name }}">
</x-view360>
```

### Options

| Attribute | Default | What it does |
|---|---|---|
| `images` | – | Array of frame URLs in rotation order |
| `directory` / `disk` | – / `public` | Or: read the frames from a storage directory |
| `alt` | `360° view` | Accessible name of the viewer |
| `sensitivity` | `8` | Pixels of drag per frame; lower spins faster |
| `autoplay` | `false` | Spin by itself until the visitor interacts |
| `speed` | `12` | Frames per second while autoplaying |
| `reverse` | `false` | Flip the direction of rotation |
| `loop` | `true` | Wrap around; set to `false` for a partial sweep such as 180° |
| `start` | `1` | Frame shown first (1-based) |
| `aspect` | `1 / 1` | CSS `aspect-ratio` of the viewer |
| `hint` | `Drag to rotate` | Text shown until the first interaction; `:hint="false"` hides it |

```blade
<x-view360 :images="$urls" autoplay :speed="20" :sensitivity="5" aspect="4 / 3" class="rounded-xl" />
```

Any other attribute (`class`, `style`, `id`, …) is passed to the viewer element. Autoplay is skipped for visitors who prefer reduced motion.

Change the defaults for the whole application by publishing the config:

```bash
php artisan vendor:publish --tag=view360-config
```

## Uploading frames

```blade
<form method="POST" action="{{ route('products.spin', $product) }}" enctype="multipart/form-data">
    @csrf
    <input type="file" name="frames[]" accept="image/*" multiple>
    <button>Upload 360° photos</button>
</form>
```

```php
use jasbros2568\View360\Facades\View360;

public function spin(Request $request, Product $product)
{
    $request->validate(View360::rules('frames'));

    View360::store($request->file('frames'), "products/{$product->id}/spin");

    return back();
}
```

- `View360::rules('frames')` – validation rules built from the config (image types, at least 2 frames, `max_frames`, `max_size`).
- `View360::store($files, $directory, $disk = null, $replace = true)` – stores the frames as `001.jpg`, `002.jpg`… in the natural order of their original file names and returns the paths. By default it replaces the frames already in the directory; pass `replace: false` to append.
- `View360::frames($directory, $disk = null)` – the frame URLs, in order.
- `View360::paths($directory, $disk = null)` – the storage paths, in order.
- `View360::delete($directory, $disk = null)` – removes a spin's frames.

The disk must be publicly reachable. For the default `public` disk run `php artisan storage:link` once.

> PHP limits uploads with `upload_max_filesize`, `post_max_size` and `max_file_uploads` (20 files per request by default). Raise them if you upload many frames at once.

## Styling

The viewer fills the width of its container. Theme it with CSS variables:

```css
.view360 {
    --view360-bg: #f7f3ee;      /* background behind the frames */
    --view360-fit: contain;     /* object-fit of the frames: contain | cover */
    --view360-accent: #a16207;  /* loading bar and focus ring */
}
```

To change the markup, publish the view with `php artisan vendor:publish --tag=view360-views`.

## JavaScript API

```js
const viewer = View360.get(document.querySelector('.view360'));

viewer.next();      // one frame forward
viewer.prev();
viewer.goTo(12);    // 0-based frame index
viewer.play();      // start autoplay
viewer.stop();      // stop autoplay
viewer.destroy();

// After adding viewers to the page yourself (AJAX, Alpine, Livewire updates):
View360.mount(container);
```

The element fires `view360:ready` when all frames are loaded and `view360:change` on every frame change (`event.detail` is `{ index, total }`). Both bubble.

Viewers are initialised on page load and after `livewire:navigated` and `turbo:load`.

### Bundling the assets yourself

Set `VIEW360_ASSETS=none` and import the files from the package:

```js
import '../../vendor/jasbros2568/laravel-360-view/resources/dist/view360.css';
import '../../vendor/jasbros2568/laravel-360-view/resources/dist/view360.js';
```

`VIEW360_ASSETS` also accepts `inline` and `published` to force either mode. If your site uses a strict Content-Security-Policy, publish the assets rather than relying on inline mode.

## Testing

```bash
composer install
composer test
```

## License

MIT. See [LICENSE](LICENSE).
