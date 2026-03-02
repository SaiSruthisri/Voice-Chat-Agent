<?php

declare(strict_types=1);

use UDVoiceAgent\Services\BrandService;
use UDVoiceAgent\Services\IntentService;
use UDVoiceAgent\Services\GeminiService;
use UDVoiceAgent\Services\MockBackend;

header('Content-Type: application/json');

$baseDir = __DIR__;

require_once $baseDir . '/services/BrandService.php';
require_once $baseDir . '/services/IntentService.php';
require_once $baseDir . '/services/GeminiService.php';
require_once $baseDir . '/services/MockBackend.php';

function jsonResponse(int $status, array $data): void
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

$brandService = new BrandService($baseDir . '/config/brands.php');
$intentService = new IntentService($baseDir . '/config/intents.php');
$mockBackend = new MockBackend();

if ($uri === '/api/brand-config' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $brandId = isset($_GET['brandId']) && is_string($_GET['brandId']) ? $_GET['brandId'] : 'default';
    $brandConfig = $brandService->getBrandConfig($brandId);
    $intents = $intentService->getIntentsForBrand($brandConfig);

    jsonResponse(200, [
        'brandId' => $brandId,
        'mode' => $brandConfig['mode'],
        'assetBaseUrl' => $brandConfig['assetBaseUrl'] ?? '/assets',
        'brand' => $brandConfig['brand'],
        'voiceEnabled' => $brandConfig['voiceEnabled'],
        'intents' => $intents,
    ]);
}

if ($uri === '/api/conversation' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $payload = json_decode($raw ?: '[]', true) ?: [];

    $brandId = is_string($payload['brandId'] ?? null) ? $payload['brandId'] : 'default';
    $brandConfig = $brandService->getBrandConfig($brandId);

    $mode = $brandConfig['mode'] ?? 'chat_only';
    $channel = is_string($payload['channel'] ?? null) ? $payload['channel'] : 'chat'; // chat | voice

    if ($mode === 'chat_only' && $channel !== 'chat') {
        jsonResponse(400, ['error' => 'Voice channel is disabled for this brand.']);
    }
    if ($mode === 'voice_only' && $channel !== 'voice') {
        jsonResponse(400, ['error' => 'Chat channel is disabled for this brand.']);
    }

    $message = is_string($payload['message'] ?? null) ? trim($payload['message']) : '';
    $intentKey = is_string($payload['intentKey'] ?? null) ? $payload['intentKey'] : null;
    $currentState = is_string($payload['currentState'] ?? null) ? $payload['currentState'] : 'IDLE';
    $history = is_array($payload['history'] ?? null) ? $payload['history'] : [];

    if ($intentKey) {
        $intent = $intentService->getIntent($intentKey);
        if ($intent && !empty($intent['userMessage'])) {
            $message = $intent['userMessage'];
        }
    }

    if ($message === '') {
        jsonResponse(400, ['error' => 'Message is required.']);
    }

    $geminiService = new GeminiService(
        getenv('GEMINI_API_KEY') ?: '',
        $baseDir . '/config/prompts.php'
    );

    $modelResponse = $geminiService->converse($channel, $message, $brandId, $currentState, $history);

    jsonResponse(200, [
        'brandId' => $brandId,
        'channel' => $channel,
        'reply' => $modelResponse['reply'] ?? '',
        'state' => $modelResponse['state'] ?? 'IDLE',
        'actions' => $modelResponse['actions'] ?? [],
    ]);
}

if ($uri === '/api/tools/get_menu' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    jsonResponse(200, $mockBackend->getMenu());
}

if ($uri === '/api/tools/get_restaurant_info' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    jsonResponse(200, $mockBackend->getRestaurantInfo());
}

if ($uri === '/api/tools/place_order' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $payload = json_decode($raw ?: '[]', true) ?: [];
    $items = is_array($payload['items'] ?? null) ? $payload['items'] : [];
    $phone = isset($payload['phone']) && is_string($payload['phone']) ? $payload['phone'] : null;
    $globalNotes = isset($payload['global_notes']) && is_string($payload['global_notes']) ? $payload['global_notes'] : null;
    jsonResponse(200, $mockBackend->placeOrder($items, $phone, $globalNotes));
}

if ($uri === '/api/tools/process_payment' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $payload = json_decode($raw ?: '[]', true) ?: [];
    $orderId = isset($payload['order_id']) && is_string($payload['order_id']) ? $payload['order_id'] : '';
    $method = isset($payload['payment_method']) && is_string($payload['payment_method']) ? $payload['payment_method'] : '';
    jsonResponse(200, $mockBackend->processPayment($orderId, $method));
}

jsonResponse(404, ['error' => 'Not found']);

