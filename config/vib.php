<?php

return [
    // Disk untuk file privat (foto, dll). Ganti ke 's3' saat pindah ke cloud
    'private_disk' => env('PRIVATE_DISK', 'local'),
];