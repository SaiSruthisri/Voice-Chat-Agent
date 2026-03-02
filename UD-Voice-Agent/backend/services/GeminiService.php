<?php

declare(strict_types=1);

namespace UDVoiceAgent\Services;

use UDVoiceAgent\Services\MockBackend;

final class GeminiService
{
    private const VALID_STATES = [
        'IDLE',
        'BROWSING_MENU',
        'CHOOSING_ITEM',
        'CHOOSING_VARIANT',
        'SUGGESTING_ADDONS',
        'ASKING_NOTES',
        'ASKING_PHONE',
        'AWAITING_CONFIRMATION',
        'ORDER_PLACED',
    ];

    private string $apiKey;
    private array $prompts;
    private MockBackend $backend;

    public function __construct(string $apiKey, string $promptsConfigPath)
    {
        $this->apiKey = $apiKey;
        $this->prompts = require $promptsConfigPath;
        $this->backend = new MockBackend();
    }

    /**
     * Entry point for chat/voice messages.
     *
     * This mirrors the TypeScript GeminiAgent:
     * - Uses the same system prompts
     * - Registers the same tools (get_menu, get_restaurant_info, place_order, process_payment)
     * - Lets Gemini call tools, executes them via MockBackend, and sends tool responses back.
     */
    public function converse(string $channel, string $message, string $brandId, string $currentState = 'IDLE', array $history = []): array
    {
        if ($this->apiKey === '') {
            return [
                'reply' => 'Gemini API key is not configured on the server.',
                'state' => 'IDLE',
                'actions' => [],
            ];
        }

        $systemKey = $channel === 'voice' ? 'voice_system' : 'chat_system';
        $systemPrompt = $this->prompts[$systemKey] ?? '';

        $tools = [
            [
                'name' => 'get_menu',
                'description' => 'Retrieves the restaurant menu, including variants (sizes/types) and allowed add-ons for each item.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => new \stdClass(),
                ],
            ],
            [
                'name' => 'get_restaurant_info',
                'description' => 'Returns restaurant details such as address and opening hours.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => new \stdClass(),
                ],
            ],
            [
                'name' => 'place_order',
                'description' => 'Finalizes the order in the backend. ONLY call this after the user has confirmed the final summary and price.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'items' => [
                            'type' => 'ARRAY',
                            'items' => [
                                'type' => 'OBJECT',
                                'properties' => [
                                    'name' => ['type' => 'STRING', 'description' => 'Product name'],
                                    'variant' => ['type' => 'STRING', 'description' => 'Selected variant name'],
                                    'add_ons' => [
                                        'type' => 'ARRAY',
                                        'items' => ['type' => 'STRING'],
                                        'description' => 'Selected add-on names',
                                    ],
                                    'notes' => ['type' => 'STRING', 'description' => 'Notes specific to this item'],
                                ],
                                'required' => ['name'],
                            ],
                        ],
                        'phone' => ['type' => 'STRING', 'description' => 'User phone number'],
                        'global_notes' => ['type' => 'STRING', 'description' => 'Notes for the entire order or delivery'],
                    ],
                    'required' => ['items', 'phone'],
                ],
            ],
            [
                'name' => 'process_payment',
                'description' => 'Processes payment for a confirmed order.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'order_id' => ['type' => 'STRING'],
                        'payment_method' => ['type' => 'STRING'],
                    ],
                    'required' => ['order_id', 'payment_method'],
                ],
            ],
        ];

        $historyLines = [];
        foreach (array_slice($history, -12) as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $role = isset($entry['role']) && is_string($entry['role']) ? strtoupper($entry['role']) : 'USER';
            $content = isset($entry['content']) && is_string($entry['content']) ? trim($entry['content']) : '';
            if ($content === '') {
                continue;
            }

            $historyLines[] = $role . ': ' . $content;
        }

        $historyBlock = !empty($historyLines)
            ? "Conversation history:\n" . implode("\n", $historyLines) . "\n"
            : '';

        $contextualMessage = sprintf(
            "Brand: %s\nChannel: %s\nCurrent state: %s\n%sUser message: %s",
            $brandId,
            $channel,
            $currentState,
            $historyBlock,
            $message
        );

        $result = $this->callGeminiWithTools($systemPrompt, $tools, $contextualMessage);
        $structured = $this->parseStructuredResponse((string) ($result['reply'] ?? ''), $currentState);

        return [
            'reply' => $structured['reply'],
            'state' => $structured['state'],
            'actions' => $structured['actions'],
        ];
    }

    /**
     * Calls the Gemini REST API, handles tool calls via MockBackend, and returns the final text reply.
     */
    private function callGeminiWithTools(string $systemPrompt, array $tools, string $message): array
    {
        $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';
        $headers = [
            'Content-Type: application/json',
            'x-goog-api-key: ' . $this->apiKey,
        ];

        $payload = [
            'systemInstruction' => [
                'parts' => [
                    ['text' => $systemPrompt],
                ],
            ],
            'tools' => [
                ['functionDeclarations' => $tools],
            ],
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $message],
                    ],
                ],
            ],
        ];

        $response = $this->httpPostJson($endpoint, $headers, $payload);

        $iterations = 0;
        while ($iterations < 5 && $this->hasFunctionCalls($response)) {
            $iterations++;
            $functionCalls = $this->extractFunctionCalls($response);
            $toolResponsesParts = [];

            foreach ($functionCalls as $fc) {
                $name = $fc['name'] ?? '';
                $args = $fc['args'] ?? [];
                $apiResult = $this->executeTool($name, $args);

                $toolResponsesParts[] = [
                    'functionResponse' => [
                        'name' => $name,
                        'response' => $apiResult,
                    ],
                ];
            }

            $payload = [
                'systemInstruction' => [
                    'parts' => [
                        ['text' => $systemPrompt],
                    ],
                ],
                'tools' => [
                    ['functionDeclarations' => $tools],
                ],
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            ['text' => $message],
                        ],
                    ],
                    [
                        'role' => 'model',
                        'parts' => $toolResponsesParts,
                    ],
                ],
            ];

            $response = $this->httpPostJson($endpoint, $headers, $payload);
        }

        $text = $this->extractTextReply($response);
        return ['reply' => $text];
    }

    private function httpPostJson(string $url, array $headers, array $body): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode($body),
        ]);
        $raw = curl_exec($ch);
        curl_close($ch);

        if (!is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function hasFunctionCalls(array $response): bool
    {
        foreach ($response['candidates'] ?? [] as $candidate) {
            foreach ($candidate['content']['parts'] ?? [] as $part) {
                if (isset($part['functionCall'])) {
                    return true;
                }
            }
        }
        return false;
    }

    private function extractFunctionCalls(array $response): array
    {
        $calls = [];
        foreach ($response['candidates'] ?? [] as $candidate) {
            foreach ($candidate['content']['parts'] ?? [] as $part) {
                if (isset($part['functionCall'])) {
                    $fc = $part['functionCall'];
                    $calls[] = [
                        'name' => $fc['name'] ?? '',
                        'args' => $fc['args'] ?? [],
                    ];
                }
            }
        }
        return $calls;
    }

    private function extractTextReply(array $response): string
    {
        foreach ($response['candidates'] ?? [] as $candidate) {
            foreach ($candidate['content']['parts'] ?? [] as $part) {
                $text = $part['text'] ?? null;
                if (is_string($text) && trim($text) !== '') {
                    return trim($text);
                }
            }
        }

        return "My connection seems to be acting up. Let's try that one more time!";
    }

    private function parseStructuredResponse(string $rawText, string $defaultState = 'IDLE'): array
    {
        $resolvedDefaultState = in_array($defaultState, self::VALID_STATES, true) ? $defaultState : 'IDLE';
        $trimmed = trim($rawText);
        $fallback = [
            'reply' => $trimmed !== '' ? $trimmed : "My connection seems to be acting up. Let's try that one more time!",
            'state' => $resolvedDefaultState,
            'actions' => [],
        ];

        if ($trimmed === '') {
            return $fallback;
        }

        $candidates = [$trimmed];
        if (preg_match('/```json\s*([\s\S]*?)\s*```/i', $trimmed, $matches) && isset($matches[1])) {
            array_unshift($candidates, trim((string) $matches[1]));
        }

        foreach ($candidates as $candidate) {
            $parsed = json_decode($candidate, true);
            if (!is_array($parsed)) {
                continue;
            }

            $reply = isset($parsed['reply']) && is_string($parsed['reply']) ? trim($parsed['reply']) : $fallback['reply'];
            $state = isset($parsed['state']) && is_string($parsed['state']) && in_array($parsed['state'], self::VALID_STATES, true)
                ? $parsed['state']
                : $resolvedDefaultState;
            $actions = isset($parsed['actions']) && is_array($parsed['actions']) ? $parsed['actions'] : [];

            return [
                'reply' => $reply !== '' ? $reply : $fallback['reply'],
                'state' => $state,
                'actions' => $actions,
            ];
        }

        return $fallback;
    }

    private function executeTool(string $name, array $args): array
    {
        try {
            switch ($name) {
                case 'get_menu':
                    return $this->backend->getMenu();
                case 'get_restaurant_info':
                    return $this->backend->getRestaurantInfo();
                case 'place_order':
                    return $this->backend->placeOrder(
                        $args['items'] ?? [],
                        $args['phone'] ?? null,
                        $args['global_notes'] ?? null
                    );
                case 'process_payment':
                    return $this->backend->processPayment(
                        (string) ($args['order_id'] ?? ''),
                        (string) ($args['payment_method'] ?? '')
                    );
                default:
                    return ['status' => 'error', 'message' => 'Unknown tool.'];
            }
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => 'Backend error.'];
        }
    }
}

