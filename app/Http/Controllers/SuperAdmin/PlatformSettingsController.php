<?php
namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Helpers\CakeshopHelper;
use App\Services\BackupService;
use App\Traits\UploadsFiles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PlatformSettingsController extends Controller
{
    use UploadsFiles;
    public function __construct(private BackupService $backups)
    {
    }

    public function index(Request $request)
    {
        $tab      = $request->input('tab', 'platform');
        if ($tab === 'philsms') {
            $tab = 'sms';
        }
        $platform = DB::table('platform_settings')->first()
            ?? (object)['platform_name' => 'Cake Shop Platform'];

        $files = $this->backups->listBackups();

        return view('superadmin.settings', compact('tab', 'platform', 'files'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'platform_name'              => 'required|string|min:3|max:100',
            'platform_tagline'           => 'nullable|string|max:200',
            'platform_email'             => 'nullable|email|max:150',
            'platform_phone'             => 'nullable|string|max:20',
            'platform_primary_color'     => 'nullable|regex:/^#[0-9A-Fa-f]{6}$/',
            'platform_bg_color'          => 'nullable|regex:/^#[0-9A-Fa-f]{6}$/',
            'platform_bg_gradient_start' => 'nullable|regex:/^#[0-9A-Fa-f]{6}$/',
            'platform_bg_gradient_end'   => 'nullable|regex:/^#[0-9A-Fa-f]{6}$/',
            'platform_logo'              => 'nullable|image|mimes:jpg,jpeg,png|max:3072',
            'platform_bg_image'          => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $bgType    = $request->input('platform_bg_type', 'color');
        $bgOpacity = max(0.1, min(1.0, (float) $request->input('platform_bg_opacity', 1.0)));

        $updates = [
            'platform_name'              => trim($request->input('platform_name')),
            'platform_tagline'           => trim($request->input('platform_tagline', '')),
            'platform_email'             => trim($request->input('platform_email', '')),
            'platform_phone'             => trim($request->input('platform_phone', '')),
            'platform_primary_color'     => $request->input('platform_primary_color', '#7B3A0F'),
            'platform_bg_type'           => $bgType,
            'platform_bg_color'          => $request->input('platform_bg_color', '#FFF8F8'),
            'platform_bg_gradient_start' => $request->input('platform_bg_gradient_start', '#fff7fb'),
            'platform_bg_gradient_end'   => $request->input('platform_bg_gradient_end', '#ffe3f1'),
            'platform_bg_opacity'        => $bgOpacity,
            'updated_at'                 => now(),
        ];

        if ($request->hasFile('platform_logo') && $request->file('platform_logo')->isValid()) {
            $updates['platform_logo'] = $this->uploadFile($request->file('platform_logo'), 'uploads/platform');
        }
        if ($request->hasFile('platform_bg_image') && $request->file('platform_bg_image')->isValid()) {
            $updates['platform_bg_image'] = $this->uploadFile($request->file('platform_bg_image'), 'uploads/platform');
        }

        $existing = DB::table('platform_settings')->first();
        if ($existing) {
            DB::table('platform_settings')->where('id', $existing->id)->update($updates);
        } else {
            $updates['created_at'] = now();
            DB::table('platform_settings')->insert($updates);
        }

        return redirect()->route('superadmin.settings', ['tab' => 'platform'])->with('msg', 'Platform settings updated successfully.');
    }

    public function savePaymongo(Request $request)
    {
        $mode = $request->input('paymongo_mode', 'test');
        if (!in_array($mode, ['test', 'live'])) $mode = 'test';

        $testPublic  = trim($request->input('paymongo_test_public', ''));
        $livePublic  = trim($request->input('paymongo_live_public', ''));

        if ($mode === 'live' && (empty($request->input('paymongo_live_secret')) || empty($livePublic))) {
            return back()->with('err', 'Cannot switch to Live mode — enter your Live Secret Key and Live Public Key first.');
        }

        $updates = ['paymongo_mode' => $mode, 'updated_at' => now()];

        if ($testPublic) $updates['paymongo_test_public']  = $testPublic;
        if (!empty($request->input('paymongo_test_secret'))) $updates['paymongo_test_secret'] = trim($request->input('paymongo_test_secret'));
        if ($livePublic) $updates['paymongo_live_public']  = $livePublic;
        if (!empty($request->input('paymongo_live_secret'))) $updates['paymongo_live_secret'] = trim($request->input('paymongo_live_secret'));

        $activePublic = $mode === 'live' ? $livePublic : $testPublic;
        if ($activePublic) $updates['paymongo_public_key'] = $activePublic;
        $activeSecret = trim($request->input('paymongo_' . $mode . '_secret', ''));
        if ($activeSecret) $updates['paymongo_secret_key'] = $activeSecret;

        $existing = DB::table('platform_settings')->first();
        if ($existing) {
            DB::table('platform_settings')->where('id', $existing->id)->update($updates);
        } else {
            $updates['platform_name'] = 'Cake Shop Platform';
            $updates['created_at']    = now();
            DB::table('platform_settings')->insert($updates);
        }

        return redirect()->route('superadmin.settings', ['tab' => 'paymongo'])->with('msg', 'PayMongo settings saved! Mode: ' . strtoupper($mode));
    }

    public function saveDevMode(Request $request)
    {
        $devMode  = $request->boolean('dev_mode');
        $existing = DB::table('platform_settings')->first();
        if ($existing) {
            DB::table('platform_settings')->where('id', $existing->id)->update([
                'dev_mode'   => $devMode,
                'updated_at' => now(),
            ]);
        }
        $status = $devMode ? 'ON — OTP and SMS previews are now visible on screen.' : 'OFF — SMS previews are hidden.';
        return redirect()->route('superadmin.settings', ['tab' => 'platform'])->with('msg', "Developer Mode {$status}");
    }

    public function saveUnisms(Request $request)
    {
        $token  = trim($request->input('philsms_token', ''));
        $sender = trim($request->input('philsms_sender', ''));

        $updates = ['updated_at' => now(), 'philsms_sender' => $sender ?: null];
        if (!empty($token)) $updates['philsms_token'] = $token;

        $existing = DB::table('platform_settings')->first();
        if ($existing) {
            DB::table('platform_settings')->where('id', $existing->id)->update($updates);
        } else {
            $updates['platform_name'] = 'Cake Shop Platform';
            $updates['created_at']    = now();
            DB::table('platform_settings')->insert($updates);
        }

        return redirect()->route('superadmin.settings', ['tab' => 'sms'])->with('msg', 'UniSMS settings saved!');
    }

    public function createBackup()
    {
        try {
            $info = $this->backups->createDatabaseBackup('manual');
            $this->backups->pruneOldBackups((int) DB::table('platform_settings')->value('backup_retention_count') ?: 14);

            $user = session('user') ?? ['id' => 'system', 'role' => 'superadmin'];
            CakeshopHelper::logActivity($user['id'], $user['role'], 'Backup Database', $info['name']);

            return redirect()->route('superadmin.settings', ['tab' => 'backup'])->with('msg', "Backup created successfully: {$info['name']}");
        } catch (\Throwable $e) {
            Log::error('Super admin backup failed: ' . $e->getMessage(), ['exception' => $e]);
            return redirect()->route('superadmin.settings', ['tab' => 'backup'])->with('err', 'Backup failed: ' . $e->getMessage());
        }
    }

    public function createFullBackup()
    {
        try {
            $info = $this->backups->createFullBackup('manual');
            $this->backups->pruneOldBackups((int) DB::table('platform_settings')->value('backup_retention_count') ?: 14);

            $user = session('user') ?? ['id' => 'system', 'role' => 'superadmin'];
            CakeshopHelper::logActivity($user['id'], $user['role'], 'Full Backup', $info['name']);

            return redirect()->route('superadmin.settings', ['tab' => 'backup'])->with('msg', "Full backup created successfully: {$info['name']}");
        } catch (\Throwable $e) {
            Log::error('Super admin full backup failed: ' . $e->getMessage(), ['exception' => $e]);
            return redirect()->route('superadmin.settings', ['tab' => 'backup'])->with('err', 'Full backup failed: ' . $e->getMessage());
        }
    }

    public function restore(Request $request)
    {
        try {
            $result = $this->backups->restoreSqlBackup((string) $request->input('file', ''));

            $user = session('user') ?? ['id' => 'system', 'role' => 'superadmin'];
            CakeshopHelper::logActivity($user['id'], $user['role'], 'Restore Database', $result['restored']['name'] . ' | safety: ' . $result['safety']['name']);

            return redirect()->route('superadmin.settings', ['tab' => 'backup'])->with('msg', "Database restored from {$result['restored']['name']}. Safety backup created first: {$result['safety']['name']}");
        } catch (\Throwable $e) {
            Log::error('Super admin restore failed: ' . $e->getMessage(), ['file' => $request->input('file'), 'exception' => $e]);
            return redirect()->route('superadmin.settings', ['tab' => 'backup'])->with('err', 'Restore failed: ' . $e->getMessage());
        }
    }

    public function deleteBackup(Request $request)
    {
        try {
            $info = $this->backups->deleteBackup((string) $request->input('file', ''));
            $user = session('user') ?? ['id' => 'system', 'role' => 'superadmin'];
            CakeshopHelper::logActivity($user['id'], $user['role'], 'Delete Backup', $info['name']);

            return redirect()->route('superadmin.settings', ['tab' => 'backup'])->with('msg', 'Backup deleted.');
        } catch (\Throwable $e) {
            return redirect()->route('superadmin.settings', ['tab' => 'backup'])->with('err', 'Delete failed: ' . $e->getMessage());
        }
    }

    public function downloadBackup(Request $request)
    {
        try {
            $path = $this->backups->resolveBackupPath((string) $request->input('file', ''));
            $user = session('user') ?? ['id' => 'system', 'role' => 'superadmin'];
            CakeshopHelper::logActivity($user['id'], $user['role'], 'Download Backup', basename($path));

            return response()->download($path, basename($path));
        } catch (\Throwable $e) {
            return redirect()->route('superadmin.settings', ['tab' => 'backup'])->with('err', 'Download failed: ' . $e->getMessage());
        }
    }

    public function uploadBackup(Request $request)
    {
        $request->validate([
            'backup_file' => 'required|file|mimes:sql,txt|max:51200',
        ]);

        try {
            $info = $this->backups->storeUploadedSql($request->file('backup_file'));
            $user = session('user') ?? ['id' => 'system', 'role' => 'superadmin'];
            CakeshopHelper::logActivity($user['id'], $user['role'], 'Upload Backup', $info['name']);

            return redirect()->route('superadmin.settings', ['tab' => 'backup'])->with('msg', "Backup uploaded: {$info['name']}");
        } catch (\Throwable $e) {
            return redirect()->route('superadmin.settings', ['tab' => 'backup'])->with('err', 'Upload failed: ' . $e->getMessage());
        }
    }

    public function saveBackupSettings(Request $request)
    {
        $validated = $request->validate([
            'backup_auto_enabled' => 'nullable|boolean',
            'backup_frequency' => 'required|in:daily,weekly,monthly',
            'backup_retention_count' => 'required|integer|min:1|max:100',
            'backup_include_uploads' => 'nullable|boolean',
        ]);

        $updates = [
            'backup_auto_enabled' => $request->boolean('backup_auto_enabled'),
            'backup_frequency' => $validated['backup_frequency'],
            'backup_retention_count' => (int) $validated['backup_retention_count'],
            'backup_include_uploads' => $request->boolean('backup_include_uploads'),
            'updated_at' => now(),
        ];

        $existing = DB::table('platform_settings')->first();
        if ($existing) {
            DB::table('platform_settings')->where('id', $existing->id)->update($updates);
        } else {
            $updates['platform_name'] = 'Cake Shop Platform';
            $updates['created_at'] = now();
            DB::table('platform_settings')->insert($updates);
        }

        return redirect()->route('superadmin.settings', ['tab' => 'backup'])->with('msg', 'Backup automation settings saved.');
    }
}
