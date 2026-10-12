<?php

/*
| Halimun Highland palette, one source for the admin panel (Filament colors, panel CSS variables, charts).
| The public site declares the same values as Tailwind tokens in resources/css/app.css; change both together.
| One dominant (forest), one accent (ember), warm neutrals (cream, sand, ink).
*/

return [
    // Full 50-950 scale so Filament can use it directly as the primary palette; 100, 300 and 700-950
    // are the public site tokens, the rest are interpolated between them.
    'forest' => [
        '50' => '#f1f6f2',
        '100' => '#dfe9e1',
        '200' => '#c4d7c9',
        '300' => '#a9c4b0',
        '400' => '#7fa38a',
        '500' => '#4f7a5c',
        '600' => '#3c6649',
        '700' => '#2f5a3d',
        '800' => '#24452f',
        '900' => '#1c3526',
        '950' => '#14261b',
    ],
    // Neutral scale with a slight forest tint, used as Filament's gray: page, cards, borders and muted
    // text in both themes come from one hue family, so nothing reads as a separate black or gray.
    'neutral' => [
        '50' => '#f7f8f7',
        '100' => '#eef1ef',
        '200' => '#dde3df',
        '300' => '#c3ccc6',
        '400' => '#94a199',
        '500' => '#5f6e64',
        '600' => '#4f5c53',
        '700' => '#3a463e',
        '800' => '#26322b',
        '900' => '#1a251e',
        '950' => '#111a14',
    ],
    'cream' => '#f7f1e5',
    'cream_deep' => '#efe5d1',
    'sand' => '#e4d6b8',
    'sand_dark' => '#7a6a47',
    'ember' => '#b3441f',
    'ember_dark' => '#8f3416',
    'ember_light' => '#e8845c',
    'ember_soft' => '#f3d9cc',
    'ink' => '#1f2a22',
    'ink_soft' => '#4a5a4f',
];
