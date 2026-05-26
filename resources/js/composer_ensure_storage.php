<?php

$paths = [
    'storage/app',
    'storage/app/public',
    'storage/app/private',
    'storage/framework',
    'storage/framework/cache',
    'storage/framework/sessions',
    'storage/framework/views',
    'storage/logs',
];

foreach ($paths as $path) {
    if (!is_dir($path)) {
        mkdir($path, 0777, true);
        echo "Created: $path\n";
    }
}
