<?php

return [
    'driver' => 'dompdf',
    'dompdf' => [
        'is_remote_enabled' => false,
        'chroot' => resource_path('views/reports'),
    ],
];
