<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Kode akses (PIN) halaman publik laporan alat berat
    |--------------------------------------------------------------------------
    | Dipakai oleh App\Http\Middleware\PinAuthMiddleware untuk memproteksi
    | endpoint publik /v1/public/heavy-equipment/* (dikirim via header
    | X-Access-Pin). Ubah lewat env HEAVY_EQUIPMENT_ACCESS_PIN lalu restart
    | container agar config:cache memuat ulang.
    */
    'access_pin' => env('HEAVY_EQUIPMENT_ACCESS_PIN'),
];
