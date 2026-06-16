<?php

namespace App\Services;

use App\Models\StreamSetting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

class YouTubeOAuthService
{
    public function scopes(): array
    {
        return [
            'openid',
            'email',
            'profile',
            'https://www.googleapis.com/auth/youtube.readonly',
        ];
    }

    public function isConfigured(): bool
    {
        return !empty(config('services.google_youtube.client_id'))
            && !empty(config('services.google_youtube.client_secret'));
    }

    public function redirectUri(): string
    {
        return config('services.google_youtube.redirect_uri')
            ?: route('stream.youtubeOAuthCallback');
    }

    public function authorizationUrl(string $state): string
    {
        $query = http_build_query([
            'client_id' => config('services.google_youtube.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => implode(' ', $this->scopes()),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . $query;
    }

    public function fetchTokenFromCode(string $code): array
    {
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => config('services.google_youtube.client_id'),
            'client_secret' => config('services.google_youtube.client_secret'),
            'redirect_uri' => $this->redirectUri(),
            'grant_type' => 'authorization_code',
        ]);

        if (!$response->successful()) {
            return [
                'success' => false,
                'message' => 'Gagal menukar kode OAuth Google: ' . $response->body(),
            ];
        }

        return array_merge(['success' => true], $response->json());
    }

    public function refreshAccessToken(StreamSetting $setting): array
    {
        if (empty($setting->google_oauth_refresh_token)) {
            return [
                'success' => false,
                'message' => 'Refresh token OAuth YouTube belum tersedia.',
            ];
        }

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => config('services.google_youtube.client_id'),
            'client_secret' => config('services.google_youtube.client_secret'),
            'refresh_token' => Crypt::decryptString($setting->google_oauth_refresh_token),
            'grant_type' => 'refresh_token',
        ]);

        if (!$response->successful()) {
            return [
                'success' => false,
                'message' => 'Gagal refresh token OAuth YouTube: ' . $response->body(),
            ];
        }

        return array_merge(['success' => true], $response->json());
    }

    public function fetchUserInfo(string $accessToken): array
    {
        $response = Http::withToken($accessToken)
            ->get('https://www.googleapis.com/oauth2/v2/userinfo');

        if (!$response->successful()) {
            return [];
        }

        return $response->json();
    }

    public function checkLiveStatus(StreamSetting $setting): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'status' => 'oauth_not_configured',
                'message' => 'Google OAuth YouTube belum dikonfigurasi di .env.',
            ];
        }

        $token = $this->refreshAccessToken($setting);
        if (!($token['success'] ?? false)) {
            return [
                'success' => false,
                'status' => 'oauth_login_required',
                'message' => $token['message'] ?? 'OAuth YouTube perlu dihubungkan ulang.',
            ];
        }

        $response = Http::withToken($token['access_token'])
            ->get('https://www.googleapis.com/youtube/v3/liveBroadcasts', [
                'part' => 'id,snippet,status,contentDetails',
                'broadcastStatus' => 'active',
                'broadcastType' => 'all',
                'mine' => 'true',
                'maxResults' => 5,
            ]);

        if (!$response->successful()) {
            return [
                'success' => false,
                'status' => 'oauth_api_error',
                'message' => 'Gagal cek live lewat YouTube API: ' . $response->body(),
            ];
        }

        $items = $response->json('items') ?: [];
        $isLive = count($items) > 0;
        $title = $isLive ? ($items[0]['snippet']['title'] ?? 'Live aktif') : null;

        return [
            'success' => true,
            'status' => $isLive ? 'already_live' : 'not_live',
            'is_live' => $isLive,
            'session_valid' => true,
            'message' => $isLive
                ? 'YouTube API mendeteksi live aktif: ' . $title
                : 'YouTube API tidak mendeteksi live aktif. Aman untuk start streaming.',
            'items' => $items,
        ];
    }
}
