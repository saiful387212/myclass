<?php
/**
 * ClassMate - PWA Manifest (Dynamic)
 * Generates manifest.json with correct absolute URLs
 * so that mobile browsers resolve start_url, scope, and icon paths correctly.
 */

require_once __DIR__ . '/includes/config.php';

header('Content-Type: application/manifest+json');
header('Cache-Control: public, max-age=86400');

$manifest = [
    'name' => 'ClassMate - Class Representative Management System',
    'short_name' => 'ClassMate',
    'description' => 'Complete class management system for students and administrators',
    'start_url' => url('index.php'),
    'id' => '/myclass/',
    'scope' => url(''),
    'display' => 'standalone',
    'display_override' => ['standalone', 'minimal-ui'],
    'orientation' => 'any',
    'background_color' => '#4F46E5',
    'theme_color' => '#4F46E5',
    'categories' => ['education', 'productivity', 'management'],
    'lang' => 'en',
    'dir' => 'ltr',
    'icons' => [
        [
            'src' => asset('icons/web-app-manifest-192x192.png'),
            'sizes' => '192x192',
            'type' => 'image/png',
            'purpose' => 'any'
        ],
        [
            'src' => asset('icons/web-app-manifest-192x192.png'),
            'sizes' => '192x192',
            'type' => 'image/png',
            'purpose' => 'maskable'
        ],
        [
            'src' => asset('icons/web-app-manifest-512x512.png'),
            'sizes' => '512x512',
            'type' => 'image/png',
            'purpose' => 'any'
        ],
        [
            'src' => asset('icons/web-app-manifest-maskable-512x512.png'),
            'sizes' => '512x512',
            'type' => 'image/png',
            'purpose' => 'maskable'
        ],
        [
            'src' => asset('icons/apple-touch-icon.png'),
            'sizes' => '180x180',
            'type' => 'image/png',
            'purpose' => 'any'
        ]
    ],
    'shortcuts' => [
        [
            'name' => 'Dashboard',
            'short_name' => 'Dashboard',
            'description' => 'Go to dashboard',
            'url' => url('student/dashboard.php'),
            'icons' => [
                [
                    'src' => asset('icons/web-app-manifest-192x192.png'),
                    'sizes' => '192x192',
                    'type' => 'image/png'
                ]
            ]
        ],
        [
            'name' => 'Classes',
            'short_name' => 'Classes',
            'description' => 'View your classes',
            'url' => url('student/classes.php'),
            'icons' => [
                [
                    'src' => asset('icons/web-app-manifest-192x192.png'),
                    'sizes' => '192x192',
                    'type' => 'image/png'
                ]
            ]
        ],
        [
            'name' => 'Assignments',
            'short_name' => 'Assignments',
            'description' => 'View assignments',
            'url' => url('student/assignments.php'),
            'icons' => [
                [
                    'src' => asset('icons/web-app-manifest-192x192.png'),
                    'sizes' => '192x192',
                    'type' => 'image/png'
                ]
            ]
        ],
        [
            'name' => 'Login',
            'short_name' => 'Login',
            'description' => 'Login to ClassMate',
            'url' => url('auth/login.php'),
            'icons' => [
                [
                    'src' => asset('icons/web-app-manifest-192x192.png'),
                    'sizes' => '192x192',
                    'type' => 'image/png'
                ]
            ]
        ]
    ],
    'prefer_related_applications' => false,
    'related_applications' => []
];

echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
