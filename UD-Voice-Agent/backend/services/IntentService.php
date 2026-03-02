<?php

declare(strict_types=1);

namespace UDVoiceAgent\Services;

final class IntentService
{
    private array $intents;

    public function __construct(string $configPath)
    {
        $this->intents = require $configPath;
    }

    public function getIntent(string $key): ?array
    {
        return $this->intents[$key] ?? null;
    }

    public function getIntentsForBrand(array $brandConfig): array
    {
        $result = [];
        foreach ($brandConfig['intents'] ?? [] as $intentKey) {
            if (isset($this->intents[$intentKey])) {
                $result[$intentKey] = $this->intents[$intentKey];
            }
        }

        return $result;
    }
}

