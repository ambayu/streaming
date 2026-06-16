<?php

namespace App\Services;

use App\Models\StreamSetting;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class YouTubeAutomationService
{
    public function prepare(StreamSetting $setting): array
    {
        return $this->runAction($setting, 'prepare');
    }

    public function checkLiveStatus(StreamSetting $setting): array
    {
        if (!empty($setting->google_oauth_refresh_token)) {
            $result = app(YouTubeOAuthService::class)->checkLiveStatus($setting);
            $setting->update([
                'youtube_last_prepare_status' => $result['status'] ?? (($result['success'] ?? false) ? 'ok' : 'error'),
                'youtube_last_prepare_message' => $result['message'] ?? null,
                'youtube_connected_at' => !empty($result['session_valid']) ? now() : $setting->youtube_connected_at,
                'youtube_last_checked_at' => now(),
            ]);

            return $result;
        }

        return $this->runAction($setting, 'status');
    }

    protected function runAction(StreamSetting $setting, string $action): array
    {
        $cookiePath = $setting->youtube_cookie_path;
        $hasCookie = !empty($cookiePath) && Storage::disk('local')->exists($cookiePath);

        if (!$hasCookie && $action === 'status' && empty($setting->youtube_channel_id)) {
            return [
                'success' => false,
                'status' => 'missing_public_channel',
                'message' => 'Isi Channel ID, URL channel, atau @handle agar status live bisa dicek otomatis tanpa cookie.',
            ];
        }

        $scriptPath = base_path('youtube_live_helper.js');
        if (!file_exists($scriptPath)) {
            return [
                'success' => false,
                'status' => 'missing_script',
                'message' => 'Script automasi YouTube tidak ditemukan.',
            ];
        }

        $nodePath = trim((string) shell_exec('which node 2>/dev/null'));
        if ($nodePath === '') {
            $nodePath = trim((string) shell_exec('command -v node 2>/dev/null'));
        }

        if ($nodePath === '') {
            return [
                'success' => false,
                'status' => 'missing_node',
                'message' => 'Node.js tidak ditemukan di server.',
            ];
        }

        $usesPublicStatusCheck = $action === 'status' && !empty($setting->youtube_channel_id);
        $sessionDir = storage_path('app/youtube-sessions/' . $setting->user_id);
        if ($usesPublicStatusCheck) {
            $sessionDir .= '/status-check';
        }

        $payload = [
            'action' => $action,
            'userId' => (int) $setting->user_id,
            'googleEmail' => $setting->google_email,
            'channelId' => $setting->youtube_channel_id,
            'cookiePath' => $hasCookie ? Storage::disk('local')->path($cookiePath) : null,
            'sessionDir' => $sessionDir,
            'screenshotPath' => storage_path('app/youtube-sessions/' . $setting->user_id . '/last-' . $action . '.png'),
        ];

        if (!is_dir(dirname($payload['screenshotPath']))) {
            mkdir(dirname($payload['screenshotPath']), 0755, true);
        }

        $process = new Process(
            [$nodePath, $scriptPath, base64_encode(json_encode($payload))],
            base_path(),
            ['PATH' => '/usr/bin:/bin:/usr/local/bin:/usr/sbin:/sbin'],
            null,
            120
        );
        $process->run();

        $output = trim($process->getOutput());
        $errorOutput = trim($process->getErrorOutput());
        $result = null;

        if ($output !== '') {
            $decoded = json_decode($output, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $result = $decoded;
            }
        }

        if (!$result) {
            $result = [
                'success' => false,
                'status' => 'invalid_output',
                'message' => $errorOutput !== '' ? $errorOutput : 'Automasi YouTube tidak mengembalikan output yang valid.',
            ];
        }

        $setting->update([
            'youtube_last_prepare_status' => $result['status'] ?? ($result['success'] ? 'ok' : 'error'),
            'youtube_last_prepare_message' => $result['message'] ?? null,
            'youtube_connected_at' => !empty($result['session_valid']) ? now() : $setting->youtube_connected_at,
            'youtube_last_checked_at' => now(),
        ]);

        return $result;
    }
}
