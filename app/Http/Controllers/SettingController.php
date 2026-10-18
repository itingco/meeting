<?php
namespace App\Http\Controllers;
use App\Models\IntegrationSetting;
use App\Models\MeetingRecipient;
use App\Services\GoogleDriveService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;
class SettingController extends Controller
{
    public function recipients(GoogleDriveService $drive)
    {
        return view('settings.recipients', [
            'recipients' => MeetingRecipient::orderBy('id')->get(),
            'driveConnected' => $drive->connected(),
        ]);
    }
    public function saveRecipients(Request $request)
    {
        $input = $request->validate(['recipients' => 'required|array', 'recipients.*.email' => 'nullable|email|max:200']);
        foreach (MeetingRecipient::all() as $recipient) {
            $submitted = $input['recipients'][$recipient->id] ?? [];
            $recipient->update([
                'email' => $submitted['email'] ?? null,
                'enabled' => !$recipient->is_executive && $request->boolean('recipients.'.$recipient->id.'.enabled'),
            ]);
        }
        return back()->with('message', 'Pengaturan tersimpan. Routing tetap NONAKTIF sampai env diubah.');
    }
    public function googleConnect(Request $request, GoogleDriveService $drive)
    {
        try {
            $client = $drive->client();
            $state = Str::random(48);
            $request->session()->put('google_oauth_state', $state);
            $client->setState($state);
            return redirect()->away($client->createAuthUrl());
        } catch (\Throwable $e) { return redirect()->route('settings.recipients')->with('error', $e->getMessage()); }
    }
    public function googleCallback(Request $request, GoogleDriveService $drive)
    {
        $state = $request->session()->pull('google_oauth_state');
        if (!$state || !hash_equals($state, (string) $request->query('state'))) abort(403, 'OAuth state tidak sesuai.');
        if (!$request->filled('code')) return redirect()->route('settings.recipients')->with('error', 'Google OAuth dibatalkan: '.$request->query('error', '-'));
        try {
            $token = $drive->client()->fetchAccessTokenWithAuthCode((string) $request->query('code'));
            if (isset($token['error'])) throw new RuntimeException((string) $token['error']);
            $drive->storeToken($token);
            return redirect()->route('settings.recipients')->with('message', 'Google Drive berhasil terhubung. Aktifkan GOOGLE_DRIVE_ENABLED=true.');
        } catch (\Throwable $e) { return redirect()->route('settings.recipients')->with('error', 'OAuth gagal: '.$e->getMessage()); }
    }
    public function googleDisconnect()
    {
        IntegrationSetting::where('name', 'google_token')->delete();
        return back()->with('message', 'Token Google Drive lokal dihapus. Nonaktifkan GOOGLE_DRIVE_ENABLED.');
    }
}
