<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class WppConnectService
{
    protected string $baseUrl;
    protected string $token;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.wppconnect.base_url'), '/');
        $this->token = (string) config('services.wppconnect.token');
    }

    public function startSession(string $session): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->post("{$this->baseUrl}/api/{$session}/start-session", [
            'webhook' => '',
            'waitQrCode' => true,
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('Error iniciando sesión WPPConnect: ' . $response->body());
        }

        return $response->json();
    }

    public function sendMessage(string $session, string $phone, string $message): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->post("{$this->baseUrl}/api/{$session}/send-message", [
            'phone' => $this->normalizePhone($phone),
            'message' => $message,
            'isGroup' => false,
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('Error enviando mensaje WPPConnect: ' . $response->body());
        }

        return $response->json();
    }

    public function sendImage(string $session, string $phone, string $imageUrl, ?string $caption = null): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->post("{$this->baseUrl}/api/{$session}/send-image", [
            'phone' => $this->normalizePhone($phone),
            'path' => $imageUrl,
            'caption' => $caption ?? '',
            'isGroup' => false,
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('Error enviando imagen WPPConnect: ' . $response->body());
        }

        return $response->json();
    }

    public function sendCampaignImageWithText(string $session, string $phone, string $message): array
    {
        $imageUrl = 'https://metatankpolitic.com/campanapolitica/loperasenadora.png';

        return $this->sendImage($session, $phone, $imageUrl, $message);
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone);
    }
}