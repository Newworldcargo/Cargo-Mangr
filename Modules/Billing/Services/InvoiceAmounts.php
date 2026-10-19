<?php

namespace Modules\Billing\Services;

use DomainException;

final class InvoiceAmounts
{
    // Bound all operands before multiplication to stay within signed 64-bit integers.
    public const MAX_MINOR = 100000000000;

    public static function decimal($value, int $maximum = self::MAX_MINOR): int
    {
        if ((!is_string($value) && !is_int($value)) ||
            !preg_match('/^(0|[1-9][0-9]{0,9})(?:\.([0-9]{1,2}))?$/D', (string) $value, $match)) {
            throw new DomainException('Enter a positive amount with no more than two decimal places.');
        }
        $minor = ((int) $match[1] * 100) + (int) str_pad($match[2] ?? '', 2, '0');
        if ($minor > $maximum) {
            throw new DomainException('The amount exceeds the supported limit.');
        }
        return $minor;
    }

    public static function calculate(array $items): array
    {
        if (count($items) < 1 || count($items) > 100) {
            throw new DomainException('An invoice must contain between 1 and 100 items.');
        }
        $result = ['subtotal_minor' => 0, 'discount_minor' => 0, 'tax_minor' => 0, 'total_minor' => 0, 'items' => []];
        foreach (array_values($items) as $position => $item) {
            $description = trim((string) ($item['description'] ?? ''));
            if ($description === '' || mb_strlen($description) > 500) {
                throw new DomainException('Each item needs a description of no more than 500 characters.');
            }
            $quantity = self::decimal($item['quantity'] ?? '', 1000000);
            if ($quantity === 0) {
                throw new DomainException('Quantity must be greater than zero.');
            }
            $price = self::decimal($item['unit_price'] ?? '');
            $subtotal = intdiv($quantity * $price + 50, 100);
            $discount = self::decimal($item['discount'] ?? '0');
            $taxRate = self::decimal($item['tax_percent'] ?? '0', 10000);
            if ($discount > $subtotal || $subtotal > self::MAX_MINOR) {
                throw new DomainException('Check the item amount and discount.');
            }
            $tax = intdiv(($subtotal - $discount) * $taxRate + 5000, 10000);
            $line = [
                'position' => $position, 'description' => $description,
                'quantity_hundredths' => $quantity, 'unit_price_minor' => $price,
                'subtotal_minor' => $subtotal, 'discount_minor' => $discount,
                'tax_basis_points' => $taxRate, 'tax_minor' => $tax,
                'total_minor' => $subtotal - $discount + $tax,
            ];
            foreach (['subtotal_minor', 'discount_minor', 'tax_minor', 'total_minor'] as $key) {
                $result[$key] += $line[$key];
                if ($result[$key] > self::MAX_MINOR) {
                    throw new DomainException('The invoice exceeds the supported limit.');
                }
            }
            $result['items'][] = $line;
        }
        if ($result['total_minor'] === 0) {
            throw new DomainException('The invoice total must be greater than zero.');
        }
        return $result;
    }
}
