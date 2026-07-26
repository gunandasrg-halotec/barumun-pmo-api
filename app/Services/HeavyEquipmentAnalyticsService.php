<?php

namespace App\Services;

use App\Enums\HeavyEquipmentActivityType;
use App\Models\HeavyEquipmentLog;
use Carbon\Carbon;

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
        $totalHours = 0.0;   // jam kerja dari sesi pagi/sore
        $totalCost  = 0.0;

        // Ringkasan per jenis pekerjaan: hasil kerja, jam kerja, biaya teralokasi
        $byActivity = [];
        foreach (HeavyEquipmentActivityType::cases() as $type) {
            $byActivity[$type->value] = [
                'activity_type'  => $type->value,
                'label'          => $type->label(),
                'unit'           => $type->unit(),
                'total_volume'   => 0.0,
                'total_minutes'  => 0.0,
                'entry_count'    => 0,
                'allocated_cost' => 0.0,
            ];
        }

        $daily         = [];
        $byEquip       = [];
        $byCostItem    = [];
        $dailyActivity = []; // [date][activity_type] => volume

        foreach ($logs as $log) {
            $date = $log->log_date?->toDateString() ?? '';
            $fuel = (float) ($log->fuel_liters ?? 0);
            $cost = (float) $log->costs->sum('amount');

            $totalFuel  += $fuel;
            $totalCost  += $cost;
            $totalHours += $this->workSessionHours($log);

            // menit per pekerjaan pada log ini (untuk alokasi biaya)
            $logActMinutes = [];
            $logTotalMin   = 0.0;
            foreach ($log->activities as $act) {
                $mins = $this->activityMinutes($act, $date);
                $logActMinutes[] = [$act, $mins];
                $logTotalMin += $mins;
            }

            $logMeter = 0.0;
            $logPokok = 0.0;
            foreach ($logActMinutes as [$act, $mins]) {
                $type = $act->activity_type;
                $vol  = (float) ($act->volume ?? 0);

                if (in_array($type, self::METER_TYPES, true)) {
                    $logMeter += $vol;
                } elseif (in_array($type, self::POKOK_TYPES, true)) {
                    $logPokok += $vol;
                }

                if (isset($byActivity[$type])) {
                    $byActivity[$type]['total_volume']  += $vol;
                    $byActivity[$type]['total_minutes'] += $mins;
                    $byActivity[$type]['entry_count']   += 1;
                    // alokasi biaya harian sebanding porsi jam pekerjaan
                    if ($logTotalMin > 0) {
                        $byActivity[$type]['allocated_cost'] += $cost * ($mins / $logTotalMin);
                    }
                }

                if ($vol > 0) {
                    $dailyActivity[$date][$type] = ($dailyActivity[$date][$type] ?? 0) + $vol;
                }
            }
            $totalMeter += $logMeter;
            $totalPokok += $logPokok;

            if (!isset($daily[$date])) {
                $daily[$date] = ['date' => $date, 'fuel_liters' => 0.0, 'cost' => 0.0, 'meter' => 0.0, 'pokok' => 0.0];
            }
            $daily[$date]['fuel_liters'] += $fuel;
            $daily[$date]['cost']        += $cost;
            $daily[$date]['meter']       += $logMeter;
            $daily[$date]['pokok']       += $logPokok;

            $eq   = $log->equipment;
            $eqId = $log->heavy_equipment_id;
            if (!isset($byEquip[$eqId])) {
                $byEquip[$eqId] = [
                    'equipment' => $eq ? ['id' => $eq->id, 'code' => $eq->code, 'type' => $eq->type, 'brand' => $eq->brand] : null,
                    'days' => 0, 'fuel_liters' => 0.0, 'cost' => 0.0,
                ];
            }
            $byEquip[$eqId]['days']        += 1;
            $byEquip[$eqId]['fuel_liters'] += $fuel;
            $byEquip[$eqId]['cost']        += $cost;

            foreach ($log->costs as $c) {
                $name = $c->costItem->name ?? '(item dihapus)';
                $byCostItem[$name] = ($byCostItem[$name] ?? 0.0) + (float) $c->amount;
            }
        }

        // finalisasi by_activity: jam & biaya per satuan
        $byActivityList = [];
        foreach ($byActivity as $row) {
            $hours   = round($row['total_minutes'] / 60, 2);
            $alloc   = round($row['allocated_cost'], 2);
            $volume  = round($row['total_volume'], 2);
            $byActivityList[] = [
                'activity_type' => $row['activity_type'],
                'label'         => $row['label'],
                'unit'          => $row['unit'],
                'total_volume'  => $volume,
                'total_hours'   => $hours,
                'entry_count'   => $row['entry_count'],
                'total_cost'    => $alloc,
                'cost_per_unit' => ($row['unit'] && $volume > 0) ? round($alloc / $volume, 2) : null,
                'cost_per_hour' => $hours > 0 ? round($alloc / $hours, 2) : null,
                // Kecepatan kerja = hasil pekerjaan / jam kerja
                'speed_per_hour' => ($row['unit'] && $hours > 0) ? round($volume / $hours, 2) : null,
            ];
        }

        ksort($daily);
        $cumFuel = 0.0;
        $cumCost = 0.0;
        $dailySeries = [];
        foreach ($daily as $row) {
            $cumFuel += $row['fuel_liters'];
            $cumCost += $row['cost'];
            $dailySeries[] = [
                'date'            => $row['date'],
                'fuel_liters'     => round($row['fuel_liters'], 2),
                'cost'            => round($row['cost'], 2),
                'meter'           => round($row['meter'], 2),
                'pokok'           => round($row['pokok'], 2),
                'cumulative_fuel' => round($cumFuel, 2),
                'cumulative_cost' => round($cumCost, 2),
            ];
        }

        $byCostItemList = [];
        foreach ($byCostItem as $name => $total) {
            $byCostItemList[] = ['name' => $name, 'total' => round($total, 2)];
        }
        usort($byCostItemList, fn ($a, $b) => $b['total'] <=> $a['total']);

        // Hasil kerja per hari per jenis pekerjaan (hanya tipe bersatuan/volume), + kumulatif per tipe.
        $volumeTypes = array_values(array_filter(
            HeavyEquipmentActivityType::cases(),
            fn ($t) => $t->unit() !== null
        ));
        $cumByType = [];
        $activityDailySeries = [];
        foreach (array_keys($daily) as $date) {
            $row = ['date' => $date];
            foreach ($volumeTypes as $type) {
                $vol = round($dailyActivity[$date][$type->value] ?? 0, 2);
                $cumByType[$type->value] = ($cumByType[$type->value] ?? 0) + $vol;
                $row[$type->value] = $vol;
                $row[$type->value . '_cum'] = round($cumByType[$type->value], 2);
            }
            $activityDailySeries[] = $row;
        }
        $activityDailyTypes = array_map(fn ($t) => [
            'value' => $t->value,
            'label' => $t->label(),
            'unit'  => $t->unit(),
        ], $volumeTypes);

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
            'by_activity'  => $byActivityList,
            'daily_series' => $dailySeries,
            'by_equipment' => array_values($byEquip),
            'by_cost_item' => $byCostItemList,
            'activity_daily_series' => $activityDailySeries,
            'activity_daily_types'  => $activityDailyTypes,
        ];
    }

    /** Jam kerja dari sesi pagi + sore. */
    private function workSessionHours(HeavyEquipmentLog $log): float
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

    /** Durasi satu pekerjaan dalam menit (mendukung lintas tanggal, mis. Roling). */
    private function activityMinutes($activity, string $logDate): float
    {
        if (!$activity->start_time || !$activity->end_time) {
            return 0.0;
        }
        $sd = $activity->start_date ? $activity->start_date->toDateString() : $logDate;
        $ed = $activity->end_date ? $activity->end_date->toDateString() : $sd;
        try {
            $start = Carbon::parse($sd . ' ' . $activity->start_time);
            $end   = Carbon::parse($ed . ' ' . $activity->end_time);
        } catch (\Throwable $e) {
            return 0.0;
        }
        if ($end->lessThanOrEqualTo($start)) {
            return 0.0;
        }
        return (float) $start->diffInMinutes($end);
    }
}
