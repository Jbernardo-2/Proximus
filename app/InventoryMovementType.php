<?php

namespace App;

enum InventoryMovementType: string
{
    case Opening = 'opening';
    case Receipt = 'receipt';
    case AdjustmentIn = 'adjustment_in';
    case AdjustmentOut = 'adjustment_out';
    case CustomerReturn = 'customer_return';
    case SupplierReturn = 'supplier_return';
    case PhysicalCountIn = 'physical_count_in';
    case PhysicalCountOut = 'physical_count_out';
    case OrderReservation = 'order_reservation';
    case OrderReservationRelease = 'order_reservation_release';

    public function label(): string
    {
        return match ($this) {
            self::Opening => 'Inventario inicial',
            self::Receipt => 'Entrada de proveedor',
            self::AdjustmentIn => 'Ajuste de entrada',
            self::AdjustmentOut => 'Ajuste de salida',
            self::CustomerReturn => 'Devolución de cliente',
            self::SupplierReturn => 'Devolución a proveedor',
            self::PhysicalCountIn => 'Diferencia positiva de conteo',
            self::PhysicalCountOut => 'Diferencia negativa de conteo',
            self::OrderReservation => 'Reserva de pedido',
            self::OrderReservationRelease => 'Liberación de pedido',
        };
    }

    public static function fromDocumentType(InventoryDocumentType $type): self
    {
        return match ($type) {
            InventoryDocumentType::Opening => self::Opening,
            InventoryDocumentType::Receipt => self::Receipt,
            InventoryDocumentType::AdjustmentIn => self::AdjustmentIn,
            InventoryDocumentType::AdjustmentOut => self::AdjustmentOut,
            InventoryDocumentType::CustomerReturn => self::CustomerReturn,
            InventoryDocumentType::SupplierReturn => self::SupplierReturn,
        };
    }
}
