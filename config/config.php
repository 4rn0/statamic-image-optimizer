<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Assets
    |--------------------------------------------------------------------------
    |
    | Every image Asset you upload will be optimized automatically.
    |
    */

    'assets' => true,

    /*
    |--------------------------------------------------------------------------
    | Glide
    |--------------------------------------------------------------------------
    |
    | Every Glide manipulation will be optimized automatically.
    |
    */

    'glide' => true,

    /*
    |--------------------------------------------------------------------------
    | Log
    |--------------------------------------------------------------------------
    |
    | Save detailed optimization output to the log
    |
    */

    'log' => false,

    /*
    |--------------------------------------------------------------------------
    | Optimizers
    |--------------------------------------------------------------------------
    |
    | Image optimizer commands
    |
    | You can use `:file` to reference the full path to the image you are
    | optimizing and `:temp` to use a temporary output file if the optimizer
    | requires it. The contents of the `:temp` file will automatically be copied
    | back to the original file after the optimization.
    |
    */

    'optimizers' => [
        [
            'executable' => 'jpegoptim',
            'arguments' => '--all-progressive -m85 :file',
            'mimetype' => 'image/jpeg',
        ],

        [
            'executable' => 'gifsicle',
            'arguments' => '-b -O3 :file',
            'mimetype' => 'image/gif',
        ],

        [
            'executable' => 'pngquant',
            'arguments' => '--force --output=:file :file',
            'mimetype' => 'image/png',
        ],

        [
            'executable' => 'optipng',
            'arguments' => '-i0 -o2 :file',
            'mimetype' => 'image/png',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Paths
    |--------------------------------------------------------------------------
    |
    | Search for optimizers in the following paths
    |
    */

    'paths' => [
        '/opt/homebrew/bin',
        '/opt/homebrew/sbin',
        '/usr/local',
        '/usr/local/bin',
        '/usr/bin',
        '/usr/sbin',
        '/usr/local/bin',
        '/usr/local/sbin',
        '/bin',
        '/sbin',
    ],
];
