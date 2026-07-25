<?php

namespace App\Enums;

enum HeavyEquipmentActivityType: string
{
    case ROLING        = 'ROLING';
    case PARIT_BATAS   = 'PARIT_BATAS';
    case PARIT_LEMBAH  = 'PARIT_LEMBAH';
    case CHIPPING      = 'CHIPPING';
    case TUMBANG_POKOK = 'TUMBANG_POKOK';
    case BUKA_JALAN    = 'BUKA_JALAN';

    public function label(): string
    {
        return match ($this) {
            self::ROLING        => 'Roling',
            self::PARIT_BATAS   => 'Buat Parit Batas (3m x 3m x 3m)',
            self::PARIT_LEMBAH  => 'Buat Parit Lembah (1m x 1m x 1m)',
            self::CHIPPING      => 'Chipping',
            self::TUMBANG_POKOK => 'Tumbang Pokok Kayu',
            self::BUKA_JALAN    => 'Buka Jalan / Terasan',
        };
    }

    /** Satuan hasil pekerjaan; null = pekerjaan hanya berbasis waktu. */
    public function unit(): ?string
    {
        return match ($this) {
            self::PARIT_BATAS, self::PARIT_LEMBAH, self::BUKA_JALAN => 'm',
            self::CHIPPING, self::TUMBANG_POKOK                     => 'pokok',
            self::ROLING                                           => null,
        };
    }

    /** @return string[] */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** @return array<int, array{value:string, label:string, unit:?string}> */
    public static function options(): array
    {
        return array_map(fn (self $c) => [
            'value' => $c->value,
            'label' => $c->label(),
            'unit'  => $c->unit(),
        ], self::cases());
    }
}
