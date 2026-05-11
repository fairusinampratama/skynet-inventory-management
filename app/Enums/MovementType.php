<?php

namespace App\Enums;

enum MovementType: string
{
    case StockIn = 'stock_in';
    case StockOut = 'stock_out';
    case Transfer = 'transfer';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::StockIn => 'Barang Masuk',
            self::StockOut => 'Barang Keluar',
            self::Transfer => 'Transfer',
            self::Adjustment => 'Penyesuaian',
        };
    }
}
