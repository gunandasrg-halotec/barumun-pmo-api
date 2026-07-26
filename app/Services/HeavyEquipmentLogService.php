<?php

namespace App\Services;

use App\Enums\HeavyEquipmentActivityType;
use App\Models\HeavyEquipmentLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class HeavyEquipmentLogService
{
    /**
     * Simpan laporan harian dari lapangan: log + rincian pekerjaan + biaya + foto (S3).
     *
     * @param array               $data   hasil FormRequest::validated()
     * @param UploadedFile[]       $photos file foto ($request->file('photos'))
     */
    public function createFromPublic(array $data, array $photos = [], ?string $ip = null): HeavyEquipmentLog
    {
        $log = DB::transaction(function () use ($data, $photos, $ip) {
            $log = HeavyEquipmentLog::create([
                'heavy_equipment_id'   => $data['heavy_equipment_id'],
                'log_date'             => $data['log_date'],
                'kebun'                => $data['kebun'],
                'area'                 => $data['area'] ?? null,
                'operator'             => $data['operator'],
                'kenek'                => $data['kenek'] ?? null,
                'fuel_liters'          => $data['fuel_liters'] ?? null,
                'work_morning_start'   => $this->normalizeTime($data['work_morning_start'] ?? null),
                'work_morning_end'     => $this->normalizeTime($data['work_morning_end'] ?? null),
                'work_afternoon_start' => $this->normalizeTime($data['work_afternoon_start'] ?? null),
                'work_afternoon_end'   => $this->normalizeTime($data['work_afternoon_end'] ?? null),
                'note'                 => $data['note'] ?? null,
                'source'               => 'PUBLIC',
                'submitted_ip'         => $ip,
            ]);

            foreach ($data['activities'] ?? [] as $activity) {
                if (empty($activity['activity_type'])) {
                    continue;
                }
                $log->activities()->create([
                    'activity_type' => $activity['activity_type'],
                    'start_date'    => $activity['start_date'] ?? null,
                    'end_date'      => $activity['end_date'] ?? null,
                    'start_time'    => $this->normalizeTime($activity['start_time'] ?? null),
                    'end_time'      => $this->normalizeTime($activity['end_time'] ?? null),
                    'volume'        => $activity['volume'] ?? null,
                    'unit'          => $activity['unit'] ?? null,
                ]);
            }

            foreach ($data['costs'] ?? [] as $cost) {
                if (empty($cost['cost_item_id'])) {
                    continue;
                }
                $log->costs()->create([
                    'heavy_equipment_cost_item_id' => $cost['cost_item_id'],
                    'amount'                       => $cost['amount'] ?? 0,
                ]);
            }

            foreach ($photos as $file) {
                if (!$file instanceof UploadedFile) {
                    continue;
                }
                $storagePath = $file->store(BucketFolder . '/heavy-equipment-logs/' . $log->heavy_equipment_id, 's3');
                $log->photos()->create([
                    'storage_path'       => $storagePath,
                    'original_file_name' => $file->getClientOriginalName(),
                    'mime_type'          => $file->getMimeType(),
                ]);
            }

            return $log->fresh(['equipment', 'activities', 'costs.costItem', 'photos']);
        });

        // Kirim WA di luar transaksi agar tidak menahan koneksi DB saat HTTP call.
        $this->sendWhatsAppNotification($log);

        return $log;
    }

    private function sendWhatsAppNotification(HeavyEquipmentLog $log): void
    {
        $recipient = config('whatsapp.alat_berat_recipient');
        if (! $recipient) {
            return;
        }

        $message = $this->buildWhatsAppMessage($log);
        app(WhatsAppService::class)->send($recipient, $message);
    }

    private function buildWhatsAppMessage(HeavyEquipmentLog $log): string
    {
        $months = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
                   'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

        $date  = $log->log_date?->toDateString();
        $parts = $date ? explode('-', $date) : [];
        $tanggal = count($parts) === 3
            ? (int)$parts[2] . ' ' . ($months[(int)$parts[1]] ?? '') . ' ' . $parts[0]
            : ($date ?? '-');

        $jamPagi  = $this->formatJamSesi($log->work_morning_start, $log->work_morning_end);
        $jamSore  = $this->formatJamSesi($log->work_afternoon_start, $log->work_afternoon_end);
        $jamKerja = array_filter([$jamPagi, $jamSore]);

        $pekerjaan = [];
        foreach ($log->activities ?? [] as $act) {
            $label  = HeavyEquipmentActivityType::tryFrom($act->activity_type)?->label() ?? $act->activity_type;
            $volume = $act->volume !== null ? number_format((float)$act->volume, 0, ',', '.') : null;
            $unit   = $act->unit ?? '';
            $suffix = $volume !== null ? (': ' . $volume . ($unit ? ' ' . $unit : '')) : '';
            $pekerjaan[] = '- ' . $label . $suffix;
        }

        $bbm     = $log->fuel_liters !== null ? number_format((float)$log->fuel_liters, 0, ',', '.') . ' ltr' : '-';
        $kenek   = $log->kenek ?: '-';
        $area    = $log->area ? " ({$log->area})" : '';

        $lines = [
            '🚜 *Laporan Alat Berat Masuk*',
            '',
            '*Kebun*    : ' . $log->kebun . $area,
            '*Tanggal*  : ' . $tanggal,
            '*Jam Kerja*: ' . (implode(', ', $jamKerja) ?: '-'),
            '*Operator* : ' . $log->operator,
            '*Kenek*    : ' . $kenek,
            '',
            '*Pekerjaan*:',
        ];

        if ($pekerjaan) {
            foreach ($pekerjaan as $p) {
                $lines[] = $p;
            }
        } else {
            $lines[] = '- (tidak ada pekerjaan)';
        }

        $lines[] = '';
        $lines[] = '*BBM*      : ' . $bbm;

        return implode("\n", $lines);
    }

    private function formatJamSesi(?string $start, ?string $end): string
    {
        if (! $start || ! $end) {
            return '';
        }
        return substr($start, 0, 5) . '–' . substr($end, 0, 5);
    }

    /** Ubah "08.00" / "8:0" → "08:00"; kosong → null. */
    private function normalizeTime(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $value = str_replace('.', ':', trim($value));
        if (!str_contains($value, ':')) {
            return null;
        }
        [$h, $m] = array_pad(explode(':', $value), 2, '0');
        return sprintf('%02d:%02d', (int) $h, (int) $m);
    }
}
