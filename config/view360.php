<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Storage disk
    |--------------------------------------------------------------------------
    |
    | Disk used by View360::store() and View360::frames() when none is passed.
    | It must be publicly reachable (its url() is what the browser loads).
    |
    */

    'disk' => env('VIEW360_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Frame uploads
    |--------------------------------------------------------------------------
    |
    | Limits applied by View360::rules() when validating uploaded frames.
    | max_size is in kilobytes per frame.
    |
    */

    'extensions' => ['jpg', 'jpeg', 'png', 'webp', 'avif'],

    'max_frames' => 144,

    'max_size' => 5120,

    /*
    |--------------------------------------------------------------------------
    | Assets
    |--------------------------------------------------------------------------
    |
    | "auto"      use the published files in public/vendor/view360 when they
    |             exist, otherwise inline the CSS and JS (zero set-up).
    | "published" always link to the published files.
    | "inline"    always inline.
    | "none"      output nothing: you bundle resources/dist yourself.
    |
    */

    'assets' => env('VIEW360_ASSETS', 'auto'),

    /*
    |--------------------------------------------------------------------------
    | Viewer defaults
    |--------------------------------------------------------------------------
    |
    | Defaults for <x-view360>; every one can be overridden per component.
    |
    */

    'defaults' => [
        // Pixels of horizontal drag needed to advance one frame.
        'sensitivity' => 8,
        // Spin by itself until the visitor interacts.
        'autoplay' => false,
        // Frames per second while autoplaying.
        'speed' => 12,
        // Flip the direction if dragging right turns the object the wrong way.
        'reverse' => false,
        // Wrap around at the last frame (turn off for a partial sweep, e.g. 180°).
        'loop' => true,
        // CSS aspect-ratio of the stage.
        'aspect' => '1 / 1',
        // Text shown until the first interaction (null hides it).
        'hint' => 'Drag to rotate',
    ],

];
