<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Exception;

class GpsIdService
{
    protected $baseUrl;
    protected $username;
    protected $password;

    public function __construct()
    {
        $this->baseUrl = config('services.gpsid.url');
        $this->username = config('services.gpsid.username');
        $this->password = config('services.gpsid.password');
    }

    /**
     * Get the authentication token from GPS.id API.
     * Caches the token for 23 hours to avoid repeated logins.
     */
    public function getToken()
    {
        return Cache::remember('gpsid_token', now()->addHours(23), function () {
            $response = Http::post($this->baseUrl . 'login', [
                'username' => $this->username,
                'password' => $this->password,
            ]);

            $token = $response->json('message.data.token') ?? $response->json('token');

            if ($response->successful() && $token) {
                return $token;
            }

            throw new Exception('Failed to authenticate with GPS.id: ' . $response->body());
        });
    }

    /**
     * Generate a monitoring link for a specific IMEI.
     */
    public function getTrackingLink($imei)
    {
        $token = $this->getToken();

        $response = Http::withToken($token)
            ->post($this->baseUrl . 'share_location/create_share', [
                'imei' => (string) $imei,
                'expiration' => 60, // Link valid for 60 minutes
            ]);

        if ($response->successful()) {
            // The API reference doesn't specify the exact response format for the link,
            // but usually it's in a 'url' or 'link' field, or inside a 'data' object.
            $data = $response->json();
            
            if (isset($data['link'])) {
                return $data['link'];
            } elseif (isset($data['url'])) {
                return $data['url'];
            } elseif (isset($data['data']['link'])) {
                return $data['data']['link'];
            } elseif (isset($data['data']['url'])) {
                return $data['data']['url'];
            }
            
            // Fallback if structure is unknown, just return the whole JSON as string for debugging
            throw new Exception('Unexpected response format from GPS.id share_location: ' . json_encode($data));
        }

        // If unauthorized, clear cache and retry once
        if ($response->status() === 401) {
            Cache::forget('gpsid_token');
            throw new Exception('Unauthorized from GPS.id. Token may be invalid. Please try again.');
        }

        throw new Exception('Failed to generate tracking link from GPS.id: ' . $response->body());
    }

    /**
     * Get IMEI by vehicle plate number from GPS.id API.
     */
    public function getImeiByPlate($plate)
    {
        $token = $this->getToken();

        $response = Http::withToken($token)
            ->get($this->baseUrl . 'vehicle');

        if ($response->successful()) {
            $data = $response->json('message.data');
            
            if (is_array($data)) {
                // Remove spaces and make it uppercase for robust comparison
                $targetPlate = strtoupper(str_replace(' ', '', $plate));

                foreach ($data as $vehicle) {
                    $vehiclePlate = strtoupper(str_replace(' ', '', $vehicle['plate'] ?? ''));
                    if ($vehiclePlate === $targetPlate) {
                        return $vehicle['imei'];
                    }
                }
            }

            throw new Exception("Vehicle with plate '{$plate}' not found in GPS.id.");
        }

        if ($response->status() === 401) {
            Cache::forget('gpsid_token');
            throw new Exception('Unauthorized from GPS.id. Token may be invalid. Please try again.');
        }

        throw new Exception('Failed to get vehicle list from GPS.id: ' . $response->body());
    }
}
