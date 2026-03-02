<?php

declare(strict_types=1);

namespace UDVoiceAgent\Services;

final class BrandService
{
    private array $brands;

    public function __construct(string $configPath)
    {
        $this->brands = require $configPath;
    }

    public function getBrandConfig(string $brandId): array
    {
        if (isset($this->brands[$brandId])) {
            return $this->brands[$brandId];
        }

        return $this->brands['default'] ?? [
            'mode' => 'chat_only',
            'brand' => [
                'title' => 'Spice Garden',
                'subtitle' => 'AI Assistant',
            ],
            'voiceEnabled' => false,
            'intents' => [],
        ];
    }
}

