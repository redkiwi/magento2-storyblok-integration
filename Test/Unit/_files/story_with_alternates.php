<?php

return [
    'story' => [
        'name' => 'Home',
        'parent_id' => 0,
        'group_id' => 'aaa-bbb-ccc',
        'alternates' => [
            [
                'id' => 200,
                'name' => 'Home NL',
                'slug' => 'home',
                'full_slug' => 'nl/home',
                'is_folder' => false,
                'parent_id' => 0,
                'published' => true,
            ],
            [
                'id' => 300,
                'name' => 'Home FR',
                'slug' => 'home',
                'full_slug' => 'fr/home',
                'is_folder' => false,
                'parent_id' => 0,
                'published' => true,
            ],
            [
                'id' => 400,
                'name' => 'Home DE (draft)',
                'slug' => 'home',
                'full_slug' => 'de/home',
                'is_folder' => false,
                'parent_id' => 0,
                'published' => false,
            ],
        ],
        'created_at' => '2024-01-01T00:00:00.000Z',
        'updated_at' => '2024-01-01T00:00:00.000Z',
        'published_at' => '2024-01-01T00:00:00.000Z',
        'id' => 100,
        'uuid' => 'aaa-bbb-ccc-ddd',
        'is_folder' => false,
        'content' => [
            '_uid' => 'uid-1',
            'component' => 'page',
            'body' => [],
        ],
        'published' => true,
        'slug' => 'home',
        'full_slug' => 'en/home',
        'default_full_slug' => 'home',
        'lang' => 'default',
        'translated_slugs' => [
            [
                'lang' => 'nl',
                'slug' => 'home',
                'path' => 'nl/startpagina',
                'name' => 'Home NL',
            ],
            [
                'lang' => 'fr',
                'slug' => 'home',
                'path' => 'fr/accueil',
                'name' => 'Home FR',
            ],
        ],
    ],
];
