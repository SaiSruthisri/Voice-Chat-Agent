<?php

declare(strict_types=1);

namespace UDVoiceAgent\Services;

final class MockBackend
{
    private array $menuRegistry;
    private array $orders = [];

    public function __construct()
    {
        $this->menuRegistry = [
            [
                'sku' => 'PROD-COKE',
                'name' => 'Coke',
                'price' => 3.00,
                'category' => 'beverage',
                'description' => 'Refreshing 330ml can.',
                'allowedAddOns' => [
                    ['id' => 'ADD-ICE', 'name' => 'Extra Ice', 'price' => 0],
                    ['id' => 'ADD-LEMON', 'name' => 'Slice of Lemon', 'price' => 0.50],
                ],
            ],
            [
                'sku' => 'PROD-BIR-01',
                'name' => 'Chicken Biryani',
                'price' => 18.00,
                'category' => 'main',
                'description' => 'Aromatic basmati rice with chicken.',
                'variants' => [
                    ['sku' => 'SKU-BIR-REG', 'name' => 'Regular', 'price' => 18.00],
                    ['sku' => 'SKU-BIR-SPICY', 'name' => 'Extra Spicy', 'price' => 18.00],
                    ['sku' => 'SKU-BIR-PEAS', 'name' => 'With Extra Peas', 'price' => 19.50],
                ],
            ],
            [
                'sku' => 'PROD-BC',
                'name' => 'Butter Chicken',
                'price' => 15.50,
                'category' => 'main',
                'description' => 'Creamy tomato curry.',
                'variants' => [
                    ['sku' => 'SKU-BC-CLASSIC', 'name' => 'Classic', 'price' => 15.50],
                    ['sku' => 'SKU-BC-PEAS', 'name' => 'With Peas', 'price' => 16.50],
                ],
                'allowedAddOns' => [
                    ['id' => 'ADD-CHEESE', 'name' => 'Extra Cheese', 'price' => 2.00],
                ],
            ],
            [
                'sku' => 'PROD-MILK',
                'name' => 'Milk',
                'price' => 0,
                'category' => 'dairy',
                'description' => 'Fresh Milk',
                'variants' => [
                    ['sku' => 'MILK-1L', 'name' => '1 Liter', 'price' => 2.50],
                    ['sku' => 'MILK-2L', 'name' => '2 Liter', 'price' => 4.50],
                ],
            ],
        ];
    }

    public function getMenu(): array
    {
        return [
            'status' => 'success',
            'data' => $this->menuRegistry,
            'message' => 'Menu loaded.',
        ];
    }

    public function getRestaurantInfo(): array
    {
        return [
            'status' => 'success',
            'data' => [
                'address' => '12, MG Road',
                'hours' => '11am-11pm',
            ],
            'message' => 'Success',
        ];
    }

    public function placeOrder(array $items, ?string $phone, ?string $globalNotes = null): array
    {
        $processedItems = [];
        $total = 0.0;

        if (empty($items)) {
            return ['status' => 'error', 'message' => 'The order is empty.'];
        }

        foreach ($items as $itemReq) {
            $name = isset($itemReq['name']) ? (string) $itemReq['name'] : '';
            if ($name === '') {
                return ['status' => 'error', 'message' => 'Item name required.'];
            }

            $product = null;
            foreach ($this->menuRegistry as $menuItem) {
                if (mb_strtolower((string) $menuItem['name']) === mb_strtolower($name)) {
                    $product = $menuItem;
                    break;
                }
            }

            if ($product === null) {
                return ['status' => 'error', 'message' => "Item {$name} not found."];
            }

            $variantName = isset($itemReq['variant']) ? (string) $itemReq['variant'] : null;
            $addOns = isset($itemReq['add_ons']) && is_array($itemReq['add_ons']) ? $itemReq['add_ons'] : [];
            $notes = isset($itemReq['notes']) ? (string) $itemReq['notes'] : null;

            if (!empty($product['variants'])) {
                $chosenVariant = null;
                foreach ($product['variants'] as $variant) {
                    if ($variantName && stripos((string) $variant['name'], $variantName) !== false) {
                        $chosenVariant = $variant;
                        break;
                    }
                }

                if ($chosenVariant === null) {
                    return [
                        'status' => 'clarification_needed',
                        'message' => "Which type of {$product['name']}?",
                        'options' => array_column($product['variants'], 'name'),
                    ];
                }

                $processedItems[] = [
                    'sku' => $chosenVariant['sku'],
                    'name' => $product['name'],
                    'variantName' => $chosenVariant['name'],
                    'quantity' => 1,
                    'addOns' => $addOns,
                    'notes' => $notes,
                ];
                $total += (float) $chosenVariant['price'];
            } else {
                $processedItems[] = [
                    'sku' => $product['sku'],
                    'name' => $product['name'],
                    'quantity' => 1,
                    'addOns' => $addOns,
                    'notes' => $notes,
                ];
                $total += (float) $product['price'];
            }

            if (!empty($addOns) && !empty($product['allowedAddOns'])) {
                foreach ($addOns as $addonName) {
                    foreach ($product['allowedAddOns'] as $addon) {
                        if (mb_strtolower((string) $addon['name']) === mb_strtolower((string) $addonName)) {
                            $total += (float) $addon['price'];
                        }
                    }
                }
            }
        }

        if (!$phone) {
            return [
                'status' => 'error',
                'errorCode' => 'MISSING_PHONE',
                'message' => 'Phone number required.',
            ];
        }

        $orderId = 'ORD-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 4));

        $order = [
            'order_id' => $orderId,
            'status' => 'confirmed',
            'items' => $processedItems,
            'phone' => $phone,
            'total_amount' => $total,
            'global_notes' => $globalNotes,
            'estimated_time' => '25 mins',
        ];

        $this->orders[] = $order;

        return [
            'status' => 'success',
            'data' => $order,
            'message' => 'Order successfully placed!',
        ];
    }

    public function processPayment(string $orderId, string $method): array
    {
        return [
            'status' => 'success',
            'message' => 'Payment processed.',
        ];
    }
}

