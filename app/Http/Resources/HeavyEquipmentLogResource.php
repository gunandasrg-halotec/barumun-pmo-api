<?php

namespace App\Http\Resources;

use App\Core\ModelResource;
use Illuminate\Http\Request;

class HeavyEquipmentLogResource extends ModelResource
{
    /** MySQL TIME kembali sebagai "08:00:00" — potong ke "08:00" untuk tampilan. */
    private static function shortTime(?string $t): ?string
    {
        return $t ? substr($t, 0, 5) : null;
    }

    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'equipment'  => $this->whenLoaded('equipment', fn () => $this->equipment ? [
                'id'    => $this->equipment->id,
                'code'  => $this->equipment->code,
                'type'  => $this->equipment->type,
                'brand' => $this->equipment->brand,
            ] : null),
            'log_date'             => $this->log_date?->toDateString(),
            'kebun'                => $this->kebun,
            'area'                 => $this->area,
            'operator'             => $this->operator,
            'kenek'                => $this->kenek,
            'fuel_liters'          => $this->fuel_liters !== null ? (float) $this->fuel_liters : null,
            'fuel_liters_dex_lite' => $this->fuel_liters_dex_lite !== null ? (float) $this->fuel_liters_dex_lite : null,
            'work_morning_start'   => self::shortTime($this->work_morning_start),
            'work_morning_end'     => self::shortTime($this->work_morning_end),
            'work_afternoon_start' => self::shortTime($this->work_afternoon_start),
            'work_afternoon_end'   => self::shortTime($this->work_afternoon_end),
            'note'                 => $this->note,
            'source'               => $this->source,
            'total_cost'           => (float) $this->total_cost,
            'activities' => $this->whenLoaded('activities', fn () => $this->activities->map(fn ($a) => [
                'id'            => $a->id,
                'activity_type' => $a->activity_type,
                'start_date'    => $a->start_date?->toDateString(),
                'end_date'      => $a->end_date?->toDateString(),
                'start_time'    => self::shortTime($a->start_time),
                'end_time'      => self::shortTime($a->end_time),
                'volume'        => $a->volume !== null ? (float) $a->volume : null,
                'description'   => $a->description,
                'repair_cost'   => $a->repair_cost !== null ? (float) $a->repair_cost : null,
                'unit'          => $a->unit,
            ])->values()),
            'costs' => $this->whenLoaded('costs', fn () => $this->costs->map(fn ($c) => [
                'id'           => $c->id,
                'cost_item_id' => $c->heavy_equipment_cost_item_id,
                'name'         => $c->relationLoaded('costItem') && $c->costItem ? $c->costItem->name : null,
                'sort_order'   => $c->relationLoaded('costItem') && $c->costItem ? (int) $c->costItem->sort_order : 0,
                'amount'       => (float) $c->amount,
            ])->values()),
            'photos' => $this->whenLoaded('photos', fn () => $this->photos->map(fn ($p) => [
                'id'                 => $p->id,
                'original_file_name' => $p->original_file_name,
                'mime_type'          => $p->mime_type,
                'photo_date'         => $p->photo_date?->toDateString(),
                // Paksa https: app di balik proxy nginx, request internal ber-scheme
                // http sehingga route() bisa menghasilkan http (mixed-content di web).
                'download_url'       => str_replace('http://', 'https://', route('heavy-equipment.photo.download', [
                    'log'   => $p->heavy_equipment_log_id,
                    'photo' => $p->id,
                ])),
            ])->values()),
            'created_at' => $this->created_at,
        ];
    }
}
