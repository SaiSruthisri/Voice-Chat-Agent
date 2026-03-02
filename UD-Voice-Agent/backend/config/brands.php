<?php

declare(strict_types=1);

/**
 * Brand-level widget and behavior configuration.
 *
 * This is the single source of truth for:
 * - Which channel(s) are allowed per brand (chat_only, voice_only, hybrid)
 * - Widget titles / subtitles
 * - Whether voice is enabled
 * - Which high-level intents are exposed in the UI
 */

return [
    // Example brand matching your current React demo
    'voice_first' => [
        'mode' => 'hybrid', // chat_only | voice_only | hybrid
        'assetBaseUrl' => '/assets',
        'brand' => [
            'title' => 'Spice Garden AI',
            'subtitle' => 'Voice Ordering Assistant',
        ],
        'voiceEnabled' => true,
        'intents' => [
            'intent.menu.show',
            'intent.restaurant.info',
            'intent.order.start',
            'intent.order.track',
            'intent.menu.popular',
        ],
    ],

    // Safe default brand – primarily chat
    'default' => [
        'mode' => 'chat_only',
        'assetBaseUrl' => '/assets',
        'brand' => [
            'title' => 'Spice Garden',
            'subtitle' => 'AI Assistant',
        ],
        'voiceEnabled' => false,
        'intents' => [
            'intent.menu.show',
            'intent.restaurant.info',
            'intent.order.start',
        ],
    ],
];

