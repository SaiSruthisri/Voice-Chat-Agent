<?php

declare(strict_types=1);

/**
 * Centralized intent definitions shared by chat and voice.
 *
 * Each intent can map to:
 * - label: human-readable label (for buttons / quick actions)
 * - userMessage: canonical user utterance to send to the model
 * - tools: which backend tools this intent is allowed to trigger
 */

return [
    'intent.menu.show' => [
        'label' => 'Show menu',
        'userMessage' => 'Show menu',
        'tools' => ['get_menu'],
    ],
    'intent.restaurant.info' => [
        'label' => 'Restaurant info',
        'userMessage' => 'Restaurant info',
        'tools' => ['get_restaurant_info'],
    ],
    'intent.order.start' => [
        'label' => 'Start order',
        'userMessage' => 'I want to place an order. If any order already exists in this chat, resume it and do not reset my items.',
        'tools' => ['get_menu'],
    ],
    'intent.order.track' => [
        'label' => 'Track order',
        'userMessage' => 'Track my order',
        'tools' => ['track_order'],
    ],
    'intent.menu.popular' => [
        'label' => 'Popular items',
        'userMessage' => 'Show popular items',
        'tools' => ['get_menu'],
    ],
    'intent.item.done_adding' => [
        'label' => 'Done adding items',
        'userMessage' => 'Done adding items',
        'tools' => ['place_order'],
    ],
    'intent.variant.default' => [
        'label' => 'Default option',
        'userMessage' => 'Default option',
        'tools' => ['place_order'],
    ],
    'intent.addon.none' => [
        'label' => 'No add-ons',
        'userMessage' => 'No add-ons',
        'tools' => ['place_order'],
    ],
    'intent.phone.use_saved' => [
        'label' => 'Use my saved number',
        'userMessage' => 'Use my saved number',
        'tools' => ['place_order'],
    ],
    'intent.phone.share_now' => [
        'label' => 'Share phone now',
        'userMessage' => 'I will share my number now',
        'tools' => ['place_order'],
    ],
    'intent.phone.skip' => [
        'label' => 'Skip for now',
        'userMessage' => 'Skip for now. I do not want to share my phone number right now. Pause checkout and ask what else I want to do.',
        'tools' => ['place_order'],
    ],
    'intent.order.confirm' => [
        'label' => 'Confirm order',
        'userMessage' => 'Confirm order',
        'tools' => ['place_order'],
    ],
    'intent.order.edit_items' => [
        'label' => 'Edit items',
        'userMessage' => 'Edit items',
        'tools' => ['get_menu'],
    ],
    'intent.order.edit_notes' => [
        'label' => 'Edit notes',
        'userMessage' => 'Edit notes',
        'tools' => ['place_order'],
    ],
    'intent.order.cancel' => [
        'label' => 'Cancel order',
        'userMessage' => 'Cancel order',
        'tools' => ['place_order'],
    ],
    'intent.payment.pay_now' => [
        'label' => 'Pay now',
        'userMessage' => 'Pay now',
        'tools' => ['process_payment'],
    ],
    'intent.order.new' => [
        'label' => 'New order',
        'userMessage' => 'Start a new order',
        'tools' => ['get_menu'],
    ],
    'intent.restaurant.call' => [
        'label' => 'Call restaurant',
        'userMessage' => 'Call restaurant',
        'tools' => ['get_restaurant_info'],
    ],
    'intent.input.custom' => [
        'label' => 'Type your message',
        'userMessage' => null,
        'tools' => [],
    ],
];

