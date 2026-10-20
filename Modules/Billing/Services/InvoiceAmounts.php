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
            $quantity = self::quantity($item['quantity'] ?? '');
            if ($quantity === 0) {
                throw new DomainException('Quantity must be greater than zero.');
            }
            $price = self::decimal($item['unit_price'] ?? '');
            $subtotal = intdiv($quantity * $price + 500, 1000);
            $discount = self::decimal($item['discount'] ?? '0');
            $inputDiscount = $discount;
            $taxRate = self::decimal($item['tax_percent'] ?? '0', 10000);
            if ($discount > $subtotal || $subtotal > self::MAX_MINOR) {
                throw new DomainException('Check the item amount and discount.');
            }
            $mode = $item['tax_mode'] ?? 'exclusive';
            if (!in_array($mode, ['inclusive', 'exclusive'], true)) throw new DomainException('Choose tax-inclusive or tax-exclusive pricing.');
            if ($mode === 'inclusive') {
                $gross = $subtotal - $discount;
                $divisor = 10000 + $taxRate;
                $net = intdiv($gross * 10000 + intdiv($divisor, 2), $divisor);
                $subtotal = intdiv($subtotal * 10000 + intdiv($divisor, 2), $divisor);
                $discount = $subtotal - $net;
                $tax = $gross - $net;
            } else {
                $tax = intdiv(($subtotal - $discount) * $taxRate + 5000, 10000);
            }
            $line = [
                'position' => $position, 'description' => $description,
                'quantity_hundredths' => intdiv($quantity + 5, 10), 'quantity_thousandths' => $quantity, 'unit_price_minor' => $price,
                'input_discount_minor' => $inputDiscount, 'tax_mode' => $mode,
                'tax_category' => $item['tax_category'] ?? null, 'tax_label' => $item['tax_label'] ?? null,
                'tax_id' => $item['tax_id'] ?? null, 'service_id' => $item['service_id'] ?? null, 'unit' => $item['unit'] ?? null,
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

    public static function quantity($value): int
    {
        if ((!is_string($value) && !is_int($value)) || !preg_match('/^(0|[1-9][0-9]{0,4})(?:\.([0-9]{1,3}))?$/D', (string) $value, $match)) {
            throw new DomainException('Enter a quantity with no more than three decimal places.');
        }
        $amount = (int) $match[1] * 1000 + (int) str_pad($match[2] ?? '', 3, '0');
        if ($amount > 10000000) throw new DomainException('Quantity cannot exceed 10,000.');
        return $amount;
    }

    public static function editableLine(object $line): array
    {
        return ['description'=>$line->description,
            'quantity'=>number_format(($line->quantity_thousandths ?? $line->quantity_hundredths*10)/1000,3,'.',''),
            'unit_price'=>number_format($line->unit_price_minor/100,2,'.',''),
            'discount'=>number_format(($line->input_discount_minor ?? $line->discount_minor)/100,2,'.',''),
            'tax_percent'=>number_format($line->tax_basis_points/100,2,'.',''), 'tax_id'=>$line->tax_id ?? '',
            'tax_category'=>$line->tax_category??null,'tax_label'=>$line->tax_label??null,
            'unit'=>$line->unit ?? 'service','service_id'=>$line->service_id ?? '', 'tax_mode'=>$line->tax_mode ?? 'exclusive'];
    }
}
