<?php

namespace App\Services;

use App\Enums\HeavyEquipmentActivityType;
use App\Models\HeavyEquipmentLog;

class HeavyEquipmentAnalyticsService
{
    private const METER_TYPES = ['PARIT_BATAS', 'PARIT_LEMBAH', 'BUKA_JALAN'];
    private const POKOK_TYPES = ['CHIPPING', 'TUMBANG_POKOK'];

    /**
     * @param array{date_from?:string,date_to?:string,equipment_id?:string,kebun?:string} $filters
     */
    public function summary(array $filters): array
    {
        $logs = HeavyEquipmentLog::query()
            ->when($filters['equipment_id'] ?? null, fn ($q, $v) => $q->where('heavy_equipment_id', $v))
            ->when($filters['kebun'] ?? null, fn ($q, $v) => $q->where('kebun', $v))
            ->when($filters['date_from'] ?? null, fn ($q, $v) => $q->whereDate('log_date', '>=', $v))
            ->when($filters['date_to'] ?? null, fn ($q, $v) => $q->whereDate('log_date', '<=', $v))
            ->with(['activities', 'costs.costItem', 'equipment'])
            ->orderBy('log_date')
            ->get();

        $totalDays  = $logs->count();
        $totalFuel  = 0.0;
        $totalMeter = 0.0;
        $totalPokok = 0.0;
        $totalHours = 0.0;
        $totalCost  = 0.0;

        // init by_activity untuk semua jenis
        $byActivity = [];
        foreach (HeavyEquipmentActivityType::cases() as $type) {
            $byActivity[$type->value] = [
                'activity_type' => $type->value,
                'label'         => $type->label(),
                'unit'          => $type->unit(),
                'total_volume'  => 0.0,
                'entry_count'   => 0,
            ];
        }

        $daily      = [];   // date => bucket
        $byEquip    = [];   // equipment_id => bucket
        $byCostItem = [];   // name => total

        foreach ($logs as $log) {
            $date = $log->log_date?->toDateString() ?? '';
            $fuel = (float) ($log->fuel_liters ?? 0);
            $cost = (float) $log->costs->sum('amount');

            $totalFuel += $fuel;
            $totalCost += $cost;

            $hours = $this->workHours($log);
            $totalHours += $hours;

            $logMeter = 0.0;
            $logPokok = 0.0;
            foreach ($log->activities as $act) {
                $vol = (float) ($act->volume ?? 0);
                if (in_array($act->activity_type, self::METER_TYPES, true)) {
                    $logMeter += $vol;
                } elseif (in_array($act->activity_type, self::POKOK_TYPES, true)) {
                    $logPokok += $vol;
                }
                if (isset($byActivity[$act->activity_type])) {
                    $byActivity[$act->activity_type]['total_volume'] += $vol;
                    $byActivity[$act->activity_type]['entry_count']  += 1;
                }
            }
            $totalMeter += $logMeter;
            $totalPokok += $logPokok;

            // daily series
            if (!isset($daily[$date])) {
                $daily[$date] = ['date' => $date, 'fuel_liters' => 0.0, 'cost' => 0.0, 'meter' => 0.0, 'pokok' => 0.0];
            }
            $daily[$date]['fuel_liters'] += $fuel;
            $daily[$date]['cost']        += $cost;
            $daily[$date]['meter']       += $logMeter;
            $daily[$date]['pokok']       += $logPokok;

            // by equipment
            $eq = $log->equipment;
            $eqId = $log->heavy_equipment_id;
            if (!isset($byEquip[$eqId])) {
                $byEquip[$eqId] = [
                    'equipment' => $eq ? [
                        'id' => $eq->id, 'code' => $eq->code, 'type' => $eq->type, 'brand' => $eq->brand,
                    ] : null,
                    'days' => 0, 'fuel_liters' => 0.0, 'cost' => 0.0,
                ];
            }
            $byEquip[$eqId]['days']        += 1;
            $byEquip[$eqId]['fuel_liters'] += $fuel;
            $byEquip[$eqId]['cost']        += $cost;

            // by cost item
            foreach ($log->costs as $c) {
                $name = $c->costItem->name ?? '(item dihapus)';
                $byCostItem[$name] = ($byCostItem[$name] ?? 0.0) + (float) $c->amount;
            }
        }

        // cumulative pada daily series
        ksort($daily);
        $cumFuel = 0.0;
        $cumCost = 0.0;
        $dailySeries = [];
        foreach ($daily as $row) {
            $cumFuel += $row['fuel_liters'];
            $cumCost += $row['cost'];
            $row['cumulative_fuel'] = round($cumFuel, 2);
            $row['cumulative_cost'] = round($cumCost, 2);
            $row['fuel_liters']     = round($row['fuel_liters'], 2);
            $row['cost']            = round($row['cost'], 2);
            $row['meter']           = round($row['meter'], 2);
            $row['pokok']           = round($row['pokok'], 2);
            $dailySeries[] = $row;
        }

        $byCostItemList = [];
        foreach ($byCostItem as $name => $total) {
            $byCostItemList[] = ['name' => $name, 'total' => round($total, 2)];
        }
        usort($byCostItemList, fn ($a, $b) => $b['total'] <=> $a['total']);

        return [
            'summary' => [
                'total_days'        => $totalDays,
                'total_fuel_liters' => round($totalFuel, 2),
                'total_meter'       => round($totalMeter, 2),
                'total_pokok'       => round($totalPokok, 2),
                'total_work_hours'  => round($totalHours, 2),
                'total_cost'        => round($totalCost, 2),
                'cost_per_meter'    => $totalMeter > 0 ? round($totalCost / $totalMeter, 2) : null,
                'cost_per_pokok'    => $totalPokok > 0 ? round($totalCost / $totalPokok, 2) : null,
                'cost_per_day'      => $totalDays > 0 ? round($totalCost / $totalDays, 2) : null,
            ],
            'daily_series' => $dailySeries,
            'by_activity'  => array_values($byActivity),
            'by_equipment' => array_values($byEquip),
            'by_cost_item' => $byCostItemList,
        ];
    }

    /** Total jam kerja (sesi pagi + sesi sore) untuk satu log. */
    private function workHours(HeavyEquipmentLog $log): float
    {
        return $this->diffHours($log->work_morning_start, $log->work_morning_end)
            + $this->diffHours($log->work_afternoon_start, $log->work_afternoon_end);
    }

    private function diffHours(?string $start, ?string $end): float
    {
        if (!$start || !$end) {
            return 0.0;
        }
        $s = $this->toMinutes($start);
        $e = $this->toMinutes($end);
        if ($s === null || $e === null || $e <= $s) {
            return 0.0;
        }
        return round(($e - $s) / 60, 2);
    }

    private function toMinutes(string $time): ?int
    {
        $parts = explode(':', $time);
        if (count($parts) < 2) {
            return null;
        }
        return ((int) $parts[0]) * 60 + (int) $parts[1];
    }
}
