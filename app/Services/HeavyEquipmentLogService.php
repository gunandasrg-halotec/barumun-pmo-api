<?php

namespace App\Services;

use App\Models\FuelStockReceipt;
use App\Models\HeavyEquipmentActivityType;
use App\Models\HeavyEquipmentCostItem;
use App\Models\HeavyEquipmentLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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
                'fuel_liters_dex_lite' => $data['fuel_liters_dex_lite'] ?? null,
                'fuel_liters_pertadex' => $data['fuel_liters_pertadex'] ?? null,
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
                    'description'   => $activity['description'] ?? null,
                    'repair_cost'   => $activity['repair_cost'] ?? null,
                ]);
            }

            $systemManagedItems = HeavyEquipmentCostItem::where('is_system_managed', true)
                ->where('is_active', true)
                ->get();
            $systemManagedIds = $systemManagedItems->pluck('id')->all();

            foreach ($data['costs'] ?? [] as $cost) {
                if (empty($cost['cost_item_id']) || in_array($cost['cost_item_id'], $systemManagedIds, true)) {
                    continue;
                }
                $log->costs()->create([
                    'heavy_equipment_cost_item_id' => $cost['cost_item_id'],
                    'amount'                       => $cost['amount'] ?? 0,
                ]);
            }

            // Item biaya yang dikelola sistem (mis. gaji harian) selalu otomatis
            // ditambahkan sesuai default_amount-nya, tidak diisi oleh user.
            foreach ($systemManagedItems as $item) {
                $log->costs()->create([
                    'heavy_equipment_cost_item_id' => $item->id,
                    'amount'                       => $item->default_amount ?? 0,
                ]);
            }

            foreach ($photos as $file) {
                if (!$file instanceof UploadedFile) {
                    continue;
                }
                $folder      = BucketFolder . '/heavy-equipment-logs/' . $log->heavy_equipment_id;
                $compressed  = $this->compressForStorage($file);
                $filename    = uniqid('', true) . '.jpg';
                $storagePath = $folder . '/' . $filename;
                Storage::disk('s3')->put($storagePath, fopen($compressed, 'r'));
                if ($compressed !== $file->getRealPath()) {
                    @unlink($compressed);
                }
                $log->photos()->create([
                    'storage_path'       => $storagePath,
                    'original_file_name' => $file->getClientOriginalName(),
                    'mime_type'          => 'image/jpeg',
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

        $typeNames = HeavyEquipmentActivityType::pluck('name', 'code');

        $pekerjaan = [];
        foreach ($log->activities ?? [] as $act) {
            $label  = $typeNames[$act->activity_type] ?? $act->activity_type;
            $volume = $act->volume !== null ? number_format((float)$act->volume, 0, ',', '.') : null;
            $unit   = $act->unit ?? '';
            $suffix = $volume !== null ? (': ' . $volume . ($unit ? ' ' . $unit : '')) : '';
            $line   = '- ' . $label . $suffix;
            if (!empty($act->description)) {
                $line .= ' [' . $act->description . ']';
            }
            if ($act->repair_cost !== null) {
                $line .= ' · Biaya: Rp ' . number_format((float)$act->repair_cost, 0, ',', '.');
            }
            $pekerjaan[] = $line;
        }

        $bbmParts = [];
        if ($log->fuel_liters !== null)          $bbmParts[] = 'Solar: ' . number_format((float)$log->fuel_liters, 0, ',', '.') . ' ltr';
        if ($log->fuel_liters_dex_lite !== null) $bbmParts[] = 'Dex Lite: ' . number_format((float)$log->fuel_liters_dex_lite, 0, ',', '.') . ' ltr';
        if ($log->fuel_liters_pertadex !== null) $bbmParts[] = 'Pertadex: ' . number_format((float)$log->fuel_liters_pertadex, 0, ',', '.') . ' ltr';
        $bbm = $bbmParts ? implode(', ', $bbmParts) : '-';
        $kenek   = $log->kenek ?: '-';
        $area    = $log->area ? " ({$log->area})" : '';

        $equipment = $log->equipment;
        $alatId    = $equipment?->code ?? '-';
        $alatNama  = trim(($equipment?->brand ?? '') . ' ' . ($equipment?->type ?? ''));

        $lines = [
            '🚜 *Laporan Penggunaan Alat Berat*',
            '',
            '*Alat*     : [' . $alatId . '] ' . $alatNama,
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

        // ── Stock BBM setelah laporan ini masuk ──
        $kebun = $log->kebun;
        $stockLines = $this->buildStockSummary($kebun);
        if ($stockLines) {
            $lines[] = '';
            $lines[] = '*📦 Stock BBM (' . $kebun . ')*:';
            foreach ($stockLines as $sl) {
                $lines[] = $sl;
            }
        }

        $photos = $log->photos ?? collect();
        if ($photos->isNotEmpty()) {
            try {
                $expiry = now()->addDays(7);
                $lines[] = '';
                foreach ($photos as $i => $photo) {
                    if (!$photo->storage_path) continue;
                    $photoUrl = Storage::disk('s3')->temporaryUrl($photo->storage_path, $expiry);
                    $lines[] = '📷 Foto ' . ($i + 1) . ': ' . $photoUrl;
                }
            } catch (\Exception) {
                // S3 tidak terkonfigurasi — lewati
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Hitung saldo BBM per jenis untuk kebun tertentu.
     * Solar    → penerimaan fuel_stock_receipts − pemakaian heavy_equipment_logs.fuel_liters
     * Dex Lite → penerimaan − heavy_equipment_logs.fuel_liters_dex_lite
     * Pertadex → penerimaan − heavy_equipment_logs.fuel_liters_pertadex
     *
     * @return string[]  baris-baris teks untuk pesan WA, atau [] jika tidak ada data
     */
    private function buildStockSummary(string $kebun): array
    {
        $fmt = fn (float $v) => number_format($v, 0, ',', '.') . ' L';

        $lines = [];
        foreach (['solar' => 'Solar ☀', 'dex_lite' => 'Dex Lite ⛽', 'pertadex' => 'Pertadex ⛽'] as $fuelType => $label) {
            $received = (float) FuelStockReceipt::where('fuel_type', $fuelType)
                ->where('kebun', $kebun)
                ->sum('total_liters');

            $usageCol = match ($fuelType) {
                'solar'    => 'fuel_liters',
                'dex_lite' => 'fuel_liters_dex_lite',
                'pertadex' => 'fuel_liters_pertadex',
            };
            $used = (float) HeavyEquipmentLog::where('kebun', $kebun)
                ->whereNotNull($usageCol)
                ->sum($usageCol);

            if ($received == 0 && $used == 0) {
                continue;
            }

            $saldo = $received - $used;
            $lines[] = '- ' . $label . ': Saldo *' . $fmt($saldo) . '* (Σ terima ' . $fmt($received) . ' − pakai ' . $fmt($used) . ')';
        }

        return $lines;
    }

    /**
     * Kompres gambar pakai GD sebelum upload ke S3.
     * Hasilkan path ke file temp JPEG. Jika sudah kecil atau format tak didukung,
     * kembalikan path asli ($file->getRealPath()) sehingga caller bisa skip unlink.
     */
    private function compressForStorage(UploadedFile $file, int $maxDim = 1280, int $quality = 80): string
    {
        if (!extension_loaded('gd')) {
            return $file->getRealPath();
        }

        $path = $file->getRealPath();
        $mime = $file->getMimeType();

        $src = match ($mime) {
            'image/jpeg', 'image/jpg' => @imagecreatefromjpeg($path),
            'image/png'               => @imagecreatefrompng($path),
            'image/webp'              => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default                   => false,
        };

        if (!$src) {
            return $path;
        }

        $w     = imagesx($src);
        $h     = imagesy($src);
        $scale = min(1.0, $maxDim / max($w, $h));

        if ($scale >= 1.0 && $mime === 'image/jpeg') {
            imagedestroy($src);
            return $path;
        }

        $nw  = (int) round($w * $scale);
        $nh  = (int) round($h * $scale);
        $dst = imagecreatetruecolor($nw, $nh);

        if ($mime === 'image/png') {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $white = imagecolorallocate($dst, 255, 255, 255);
            imagefill($dst, 0, 0, $white);
        }

        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($src);

        $outPath = sys_get_temp_dir() . '/' . uniqid('pmo_img_', true) . '.jpg';
        imagejpeg($dst, $outPath, $quality);
        imagedestroy($dst);

        return $outPath;
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
