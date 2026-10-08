<?php

namespace jasbros2568\View360\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use jasbros2568\View360\Facades\View360;
use jasbros2568\View360\Tests\TestCase;

class FramesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_frames_are_listed_in_natural_order_and_only_images(): void
    {
        foreach (['frame-10.jpg', 'frame-2.jpg', 'frame-1.jpg', 'notes.txt', 'Frame-3.PNG'] as $name) {
            Storage::disk('public')->put("spins/ring/$name", 'x');
        }

        $this->assertSame(
            ['spins/ring/frame-1.jpg', 'spins/ring/frame-2.jpg', 'spins/ring/Frame-3.PNG', 'spins/ring/frame-10.jpg'],
            View360::paths('spins/ring'),
        );
        $this->assertSame(Storage::disk('public')->url('spins/ring/frame-1.jpg'), View360::frames('/spins/ring/')[0]);
        $this->assertSame([], View360::frames('spins/missing'));
    }

    public function test_uploaded_frames_are_stored_numbered_in_the_order_of_their_original_names(): void
    {
        $files = [
            UploadedFile::fake()->image('IMG_0010.jpg'),
            UploadedFile::fake()->image('IMG_0002.jpg'),
            UploadedFile::fake()->image('IMG_0001.png'),
        ];

        $stored = View360::store($files, 'spins/ring');

        $this->assertSame(['spins/ring/001.png', 'spins/ring/002.jpg', 'spins/ring/003.jpg'], $stored);
        Storage::disk('public')->assertExists($stored);
    }

    public function test_storing_replaces_the_previous_spin_unless_appending(): void
    {
        View360::store([UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg'), UploadedFile::fake()->image('c.jpg')], 'spins/ring');
        Storage::disk('public')->put('spins/ring/readme.txt', 'kept');

        View360::store([UploadedFile::fake()->image('x.jpg'), UploadedFile::fake()->image('y.jpg')], 'spins/ring');
        $this->assertSame(['spins/ring/001.jpg', 'spins/ring/002.jpg'], View360::paths('spins/ring'));
        Storage::disk('public')->assertExists('spins/ring/readme.txt'); // only frames are replaced

        View360::store([UploadedFile::fake()->image('z.jpg')], 'spins/ring', replace: false);
        $this->assertSame(['spins/ring/001.jpg', 'spins/ring/002.jpg', 'spins/ring/003.jpg'], View360::paths('spins/ring'));

        View360::delete('spins/ring');
        $this->assertSame([], View360::paths('spins/ring'));
    }

    public function test_another_disk_can_be_used(): void
    {
        Storage::fake('s3');

        View360::store([UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')], 'spins/ring', 's3');

        Storage::disk('s3')->assertExists('spins/ring/001.jpg');
        Storage::disk('public')->assertMissing('spins/ring/001.jpg');
        $this->assertCount(2, View360::frames('spins/ring', 's3'));
    }

    public function test_only_uploaded_files_can_be_stored(): void
    {
        $this->expectException(InvalidArgumentException::class);

        View360::store(['not-a-file'], 'spins/ring');
    }

    public function test_validation_rules_follow_the_config(): void
    {
        config(['view360.max_frames' => 3, 'view360.max_size' => 100]);
        $rules = View360::rules('spin');

        $this->assertTrue(Validator::make(['spin' => [UploadedFile::fake()->image('1.jpg'), UploadedFile::fake()->image('2.png')]], $rules)->passes());
        $this->assertTrue(Validator::make(['spin' => [UploadedFile::fake()->image('1.jpg')]], $rules)->fails()); // a spin needs at least 2 frames
        $this->assertTrue(Validator::make(['spin' => array_fill(0, 4, UploadedFile::fake()->image('1.jpg'))], $rules)->fails()); // too many
        $this->assertTrue(Validator::make(['spin' => [UploadedFile::fake()->image('1.jpg'), UploadedFile::fake()->create('2.pdf', 10, 'application/pdf')]], $rules)->fails());
        $this->assertTrue(Validator::make(['spin' => [UploadedFile::fake()->image('1.jpg'), UploadedFile::fake()->image('2.jpg')->size(500)]], $rules)->fails()); // too large
    }
}
