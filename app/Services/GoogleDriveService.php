<?php
namespace App\Services;
use App\Models\IntegrationSetting;
use App\Models\Meeting;
use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class GoogleDriveService
{
    public function client(): Client
    {
        if (!config('meeting.google_client_id') || !config('meeting.google_client_secret')) {
            throw new RuntimeException('Google OAuth client ID/secret belum disetel.');
        }
        $client = new Client();
        $client->setClientId(config('meeting.google_client_id'));
        $client->setClientSecret(config('meeting.google_client_secret'));
        $client->setRedirectUri(config('meeting.google_redirect_uri'));
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->setScopes([Drive::DRIVE]);
        return $client;
    }
    public function connected(): bool
    {
        return (bool) IntegrationSetting::where('name', 'google_token')->first()?->value;
    }
    public function authorized(): Client
    {
        $record = IntegrationSetting::where('name', 'google_token')->first();
        $saved = json_decode($record?->value ?: '{}', true);
        if (!$saved || empty($saved['access_token'])) throw new RuntimeException('Google Drive belum tersambung.');
        $client = $this->client();
        $client->setAccessToken($saved);
        if ($client->isAccessTokenExpired()) {
            if (empty($saved['refresh_token'])) throw new RuntimeException('Google OAuth refresh token tidak tersedia. Hubungkan ulang Drive.');
            $updated = $client->fetchAccessTokenWithRefreshToken($saved['refresh_token']);
            if (isset($updated['error'])) throw new RuntimeException('Refresh Google OAuth gagal: '.$updated['error']);
            $newToken = array_merge($saved, $updated);
            $record->update(['value' => json_encode($newToken)]);
            $client->setAccessToken($newToken);
        }
        return $client;
    }
    public function storeToken(array $token): void
    {
        if (empty($token['access_token'])) throw new RuntimeException('Google tidak mengembalikan access token.');
        $record = IntegrationSetting::where('name', 'google_token')->first();
        $prior = $record ? json_decode($record->value, true) : [];
        IntegrationSetting::updateOrCreate(['name' => 'google_token'], ['value' => json_encode(array_merge($prior ?: [], $token))]);
    }
    private function findOrCreate(Drive $drive, string $parentId, string $name): string
    {
        $nameEscaped = str_replace(["\\", "'"], ["\\\\", "\\'"], $name);
        $query = "name = '{$nameEscaped}' and mimeType = 'application/vnd.google-apps.folder' and '{$parentId}' in parents and trashed = false";
        $list = $drive->files->listFiles(['q' => $query, 'fields' => 'files(id,name),nextPageToken', 'pageSize' => 10]);
        if (count($list->getFiles()) > 0) return $list->getFiles()[0]->getId();
        $folder = new DriveFile(['name' => $name, 'mimeType' => 'application/vnd.google-apps.folder', 'parents' => [$parentId]]);
        return $drive->files->create($folder, ['fields' => 'id'])->getId();
    }
    public function folderFor(Meeting $meeting): string
    {
        if (!config('meeting.drive_parent_id')) throw new RuntimeException('GOOGLE_DRIVE_PARENT_ID belum disetel.');
        // Serialisasi pembuatan folder per meeting agar worker paralel tidak membuat folder ganda.
        return DB::transaction(function () use ($meeting) {
            $locked = Meeting::whereKey($meeting->id)->lockForUpdate()->firstOrFail();
            if ($locked->drive_folder_id) return $locked->drive_folder_id;
            $drive = new Drive($this->authorized());
            $dateName = $locked->started_at->timezone(config('app.timezone'))->format('Y-m-d');
            $dateFolderId = $this->findOrCreate($drive, config('meeting.drive_parent_id'), $dateName);
            $sessionName = $locked->started_at->timezone(config('app.timezone'))->format('Hi').'_'.preg_replace('/[^A-Za-z0-9_-]/', '_', substr($locked->title, 0, 50)).'_'.substr($locked->uuid, 0, 8);
            $folderId = $this->findOrCreate($drive, $dateFolderId, $sessionName);
            $locked->update(['drive_folder_id' => $folderId]);
            return $folderId;
        }, 3);
    }
    public function upload(Meeting $meeting, string $localFile, string $remoteName, string $mime): string
    {
        if (!is_file($localFile)) throw new RuntimeException('File upload tidak ditemukan.');
        $folderId = $this->folderFor($meeting);
        $drive = new Drive($this->authorized());
        // Retry/idempotensi: jika upload sudah berhasil namun update DB terputus,
        // jangan membuat salinan file dengan nama sama di folder meeting.
        $escaped = str_replace(["\\", "'"], ["\\\\", "\\'"], $remoteName);
        $existing = $drive->files->listFiles([
            'q' => "name = '{$escaped}' and '{$folderId}' in parents and trashed = false",
            'fields' => 'files(id,name),nextPageToken', 'pageSize' => 10,
        ])->getFiles();
        if (count($existing)) return $existing[0]->getId();
        $meta = new DriveFile(['name' => $remoteName, 'parents' => [$folderId]]);
        $result = $drive->files->create($meta, [
            'data' => file_get_contents($localFile),
            'mimeType' => $mime, 'uploadType' => 'multipart', 'fields' => 'id',
        ]);
        return $result->getId();
    }
}
