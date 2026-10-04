<?php

namespace App;

enum InventoryDocumentType: string
{
    case Opening = 'opening';
    case Receipt = 'receipt';
    case AdjustmentIn = 'adjustment_in';
    case AdjustmentOut = 'adjustment_out';
    case CustomerReturn = 'customer_return';
    case SupplierReturn = 'supplier_return';

    public function label(): string
    {
        return match ($this) {
            self::Opening => 'Inventario inicial',
            self::Receipt => 'Entrada de proveedor',
            self::AdjustmentIn => 'Ajuste de entrada',
            self::AdjustmentOut => 'Ajuste de salida',
            self::CustomerReturn => 'Devolución de cliente',
            self::SupplierReturn => 'Devolución a proveedor',
        };
    }

    public function prefix(): string
    {
        return match ($this) {
            self::Opening => 'INI',
            self::Receipt => 'REC',
            self::AdjustmentIn => 'AJE',
            self::AdjustmentOut => 'AJS',
            self::CustomerReturn => 'DCL',
            self::SupplierReturn => 'DPR',
        };
    }

    public function onHandSign(): int
    {
        return match ($this) {
            self::Opening, self::Receipt, self::AdjustmentIn, self::CustomerReturn => 1,
            self::AdjustmentOut, self::SupplierReturn => -1,
        };
    }

    public function requiresAdjustmentPermission(): bool
    {
        return in_array($this, [self::Opening, self::AdjustmentIn, self::AdjustmentOut], true);
    }
}
