<?php

namespace jasbros2568\View360\Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use jasbros2568\View360\Tests\TestCase;

class ViewerComponentTest extends TestCase
{
    /** @return array<string, mixed> */
    private function viewerOptions(string $html): array
    {
        $this->assertSame(1, preg_match('/data-view360="([^"]*)"/', $html, $match), 'The viewer element was not rendered.');

        return json_decode(html_entity_decode($match[1]), true);
    }

    public function test_it_renders_the_frames_and_default_options(): void
    {
        $html = Blade::render('<x-view360 :images="$images" alt="Solitaire ring" class="my-viewer" style="max-width: 500px" />', ['images' => ['/a/1.jpg', '/a/2.jpg', '/a/3.jpg']]);
        $options = $this->viewerOptions($html);

        $this->assertSame(['/a/1.jpg', '/a/2.jpg', '/a/3.jpg'], $options['frames']);
        $this->assertSame(['sensitivity' => 8, 'autoplay' => false, 'speed' => 12, 'reverse' => false, 'loop' => true, 'start' => 0], array_diff_key($options, ['frames' => 1]));
        $this->assertStringContainsString('class="view360 my-viewer"', $html);
        $this->assertStringContainsString('aspect-ratio: 1 / 1', $html);
        $this->assertStringContainsString('max-width: 500px', $html); // the caller's style is kept
        $this->assertStringContainsString('aria-label="Solitaire ring"', $html);
        $this->assertStringContainsString('aria-valuemax="3"', $html);
        $this->assertStringContainsString('<img class="view360__frame" src="/a/1.jpg"', $html); // works before / without JS
        $this->assertStringContainsString('Drag to rotate', $html);
    }

    public function test_options_can_be_overridden_per_component_and_by_config(): void
    {
        config(['view360.defaults.speed' => 30, 'view360.defaults.hint' => 'Spin me']);

        $html = Blade::render('<x-view360 :images="$images" autoplay reverse :loop="false" :sensitivity="3" :start="2" aspect="4 / 3" />', ['images' => ['/1.jpg', '/2.jpg']]);
        $options = $this->viewerOptions($html);

        $this->assertTrue($options['autoplay']);
        $this->assertTrue($options['reverse']);
        $this->assertFalse($options['loop']);
        $this->assertSame(3, $options['sensitivity']);
        $this->assertSame(30, $options['speed']); // from config
        $this->assertSame(1, $options['start']); // 1-based prop, 0-based option
        $this->assertStringContainsString('aspect-ratio: 4 / 3', $html);
        $this->assertStringContainsString('src="/2.jpg"', $html);
        $this->assertStringContainsString('Spin me', $html);

        $this->assertStringNotContainsString('class="view360__hint"', Blade::render('<x-view360 :images="$images" :hint="false" />', ['images' => ['/1.jpg', '/2.jpg']]));
    }

    public function test_frames_can_come_from_a_storage_directory(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('spins/ring/2.jpg', 'x');
        Storage::disk('public')->put('spins/ring/1.jpg', 'x');
        Storage::disk('public')->put('spins/ring/10.jpg', 'x');

        $options = $this->viewerOptions(Blade::render('<x-view360 directory="spins/ring" />'));

        $this->assertSame(array_map(fn ($n) => Storage::disk('public')->url("spins/ring/$n.jpg"), [1, 2, 10]), $options['frames']);
    }

    public function test_without_frames_the_slot_is_rendered_as_a_fallback(): void
    {
        $html = Blade::render('<x-view360 :images="[]"><img src="/photo.jpg" alt="Ring"></x-view360>');

        $this->assertStringContainsString('<img src="/photo.jpg" alt="Ring">', $html);
        $this->assertStringNotContainsString('role="slider"', $html);
        $this->assertStringNotContainsString('<script', $html);
    }

    public function test_assets_are_inlined_once_until_they_are_published(): void
    {
        File::deleteDirectory(public_path('vendor/view360'));

        $html = Blade::render('<x-view360 :images="$images" /><x-view360 :images="$images" />', ['images' => ['/1.jpg', '/2.jpg']]);

        $this->assertSame(2, substr_count($html, 'role="slider"'));
        $this->assertSame(1, substr_count($html, '<script>')); // @once
        $this->assertSame(1, substr_count($html, '<style>'));
        $this->assertStringContainsString('window.View360', $html);

        $this->artisan('vendor:publish', ['--tag' => 'view360-assets'])->assertSuccessful();
        $this->assertFileExists(public_path('vendor/view360/view360.js'));
        $this->assertFileExists(public_path('vendor/view360/view360.css'));

        $html = Blade::render('<x-view360 :images="$images" />', ['images' => ['/1.jpg', '/2.jpg']]);
        $this->assertStringContainsString('vendor/view360/view360.js?v=', $html);
        $this->assertStringContainsString('vendor/view360/view360.css?v=', $html);
        $this->assertStringNotContainsString('window.View360', $html);

        config(['view360.assets' => 'none']);
        $html = Blade::render('<x-view360 :images="$images" />', ['images' => ['/1.jpg', '/2.jpg']]);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('<link', $html);

        File::deleteDirectory(public_path('vendor/view360'));
    }

    public function test_frame_urls_are_escaped(): void
    {
        $html = Blade::render('<x-view360 :images="$images" />', ['images' => ['/1.jpg?a=1&b="><script>alert(1)</script>', '/2.jpg']]);

        $this->assertStringNotContainsString('"><script>alert(1)', $html);
        $this->assertSame('/1.jpg?a=1&b="><script>alert(1)</script>', $this->viewerOptions($html)['frames'][0]);
    }
}
