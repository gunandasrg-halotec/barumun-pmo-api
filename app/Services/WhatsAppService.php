<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    /**
     * Kirim pesan WhatsApp ke nomor/grup yang dikonfigurasi.
     * Gagal diam-diam — tidak melempar exception agar tidak memblokir respons API.
     */
    public function send(string $recipient, string $message): void
    {
        $baseUrl  = config('whatsapp.gateway_url');
        $username = config('whatsapp.username');
        $password = config('whatsapp.password');
        $deviceId = config('whatsapp.device_id');

        if (! $baseUrl || ! $recipient) {
            return;
        }

        $endpoint = rtrim($baseUrl, '/');
        if (! str_ends_with($endpoint, '/send/message')) {
            $endpoint .= '/send/message';
        }

        try {
            Http::withBasicAuth($username, $password)
                ->withHeaders(['X-Device-Id' => $deviceId])
                ->timeout(5)
                ->post($endpoint, [
                    'phone'   => $recipient,
                    'message' => $message,
                ]);
        } catch (\Exception $e) {
            Log::error('WhatsApp send failed: ' . $e->getMessage());
        }
    }
}
