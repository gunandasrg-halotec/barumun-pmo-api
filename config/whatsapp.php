<?php

return [
    /*
     * URL gateway, tanpa trailing slash.
     * Sistem akan menambahkan /send/message secara otomatis.
     * Kosongkan untuk menonaktifkan pengiriman WA.
     */
    'gateway_url'  => env('WA_GATEWAY_URL', ''),
    'username'     => env('WA_GATEWAY_USERNAME', ''),
    'password'     => env('WA_GATEWAY_PASSWORD', ''),
    'device_id'    => env('WA_GATEWAY_DEVICE_ID', 'barumun'),

    /*
     * Nomor/group ID penerima notifikasi alat berat.
     *
     * Individual : format internasional, mis. "6281234567890"
     * Grup WA    : ID grup, mis. "628xxx-1234567890@g.us"
     *
     * Local      → kirim ke nomor admin (WA_ALAT_BERAT_RECIPIENT_LOCAL)
     * Production → kirim ke grup "BPN Barokah" (WA_ALAT_BERAT_RECIPIENT)
     *
     * Isi WA_ALAT_BERAT_RECIPIENT di env/api.env.dev (produksi).
     * Isi WA_ALAT_BERAT_RECIPIENT_LOCAL di docker/local/.env.local (lokal).
     */
    'alat_berat_recipient' => env('WA_ALAT_BERAT_RECIPIENT', ''),
];
