<?php

namespace app\Rules;

final class InventoryCalculator
{
    public static function calculate(array $items): array
    {
        $containers = [];
        $counts = array_count_values(array_column($items, 'name'));
        foreach ($items as $item) {
            $d = $item['data'];
            if (!empty($d['extradimensional']) && empty($d['container']) && ($d['quantity'] ?? 1) == 1 && $counts[$item['name']] === 1) {
                $containers[$item['name']] = ['capacity' => (float)($d['capacity'] ?? 0), 'bulk' => 0];
            }
        }
        foreach ($items as $item) {
            $d = $item['data'];
            $name = $d['container'] ?? '';
            if (isset($containers[$name])) {
                $containers[$name]['bulk'] += (float)($d['bulk'] ?? 0) * (int)($d['quantity'] ?? 1);
            }
        }
        $bulk = 0;
        foreach ($items as $item) {
            $d = $item['data'];
            $container = $containers[$d['container'] ?? ''] ?? null;
            // An invalid or overfilled container never silently removes carried weight.
            if (!$container || $container['bulk'] > $container['capacity']) {
                $bulk += (float)($d['bulk'] ?? 0) * (int)($d['quantity'] ?? 1);
            }
        }
        return ['bulk' => $bulk, 'containers' => $containers];
    }

    public static function equipped(array $data): bool
    {
        return !empty($data['equipped']) && empty($data['container']) && (int)($data['quantity'] ?? 1) > 0;
    }
}
