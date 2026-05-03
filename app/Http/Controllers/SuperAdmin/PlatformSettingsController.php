<?php
namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Helpers\CakeshopHelper;
use App\Traits\UploadsFiles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PlatformSettingsController extends Controller
{
    use UploadsFiles;
    public function index(Request $request)
    {
        $tab      = $request->input('tab', 'platform');
        if ($tab === 'philsms') {
            $tab = 'sms';
        }
        $platform = DB::table('platform_settings')->first()
            ?? (object)['platform_name' => 'Cake Shop Platform'];

        $files = [];
        $backupsDir = storage_path('app/backups');
        if (is_dir($backupsDir)) {
            $files = array_filter(glob($backupsDir . '/*.sql') ?: [], 'is_file');
            usort($files, fn($a, $b) => filemtime($b) - filemtime($a));
        }

        return view('superadmin.settings', compact('tab', 'platform', 'files'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'platform_name'              => 'required|string|min:3|max:100',
            'platform_tagline'           => 'nullable|string|max:200',
            'platform_email'             => 'nullable|email|max:150',
            'platform_phone'             => 'nullable|string|max:20',
            'commission_rate_basic'      => 'required|numeric|min:0|max:100',
            'commission_rate_verified'   => 'required|numeric|min:0|max:100',
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
            'commission_rate_basic'      => round((float) $request->input('commission_rate_basic', 0), 2),
            'commission_rate_verified'   => round((float) $request->input('commission_rate_verified', 0), 2),
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

        $updates = ['updated_at' => now()];
        if (!empty($token))  $updates['philsms_token']  = $token;
        if (!empty($sender)) $updates['philsms_sender'] = $sender;

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
            $backupsDir = storage_path('app/backups');
            if (!is_dir($backupsDir) && !mkdir($backupsDir, 0755, true) && !is_dir($backupsDir)) {
                return redirect()->route('superadmin.settings', ['tab' => 'backup'])->with('err', 'Backup folder could not be created.');
            }

            if (!is_writable($backupsDir)) {
                return redirect()->route('superadmin.settings', ['tab' => 'backup'])->with('err', 'Backup folder is not writable.');
            }

            $connection = config('database.default');
            $database = config("database.connections.{$connection}.database") ?: $connection;
            $content = CakeshopHelper::exportSql((string) $database);

            if (trim($content) === '') {
                return redirect()->route('superadmin.settings', ['tab' => 'backup'])->with('err', 'Backup was not created because the database export was empty.');
            }

            $fname = 'berrybase_' . $connection . '_' . date('Y-m-d_H-i-s') . '.sql';
            $path = $backupsDir . DIRECTORY_SEPARATOR . $fname;

            if (file_put_contents($path, $content, LOCK_EX) === false) {
                return redirect()->route('superadmin.settings', ['tab' => 'backup'])->with('err', 'Failed to write backup file.');
            }

            $user = session('user') ?? ['id' => 'system', 'role' => 'superadmin'];
            CakeshopHelper::logActivity($user['id'], $user['role'], 'Backup Database', $fname);

            return redirect()->route('superadmin.settings', ['tab' => 'backup'])->with('msg', "Backup created successfully: {$fname}");
        } catch (\Throwable $e) {
            Log::error('Super admin backup failed: ' . $e->getMessage(), ['exception' => $e]);
            return redirect()->route('superadmin.settings', ['tab' => 'backup'])->with('err', 'Backup failed. Please check the database connection and storage permissions.');
        }
    }

    public function restore(Request $request)
    {
        $file       = preg_replace('/[^A-Za-z0-9._-]/', '', $request->input('file', ''));
        $backupsDir = storage_path('app/backups');
        $path       = $backupsDir . DIRECTORY_SEPARATOR . $file;
        if (!$file || !is_file($path)) {
            return redirect()->route('superadmin.settings', ['tab' => 'backup'])->with('err', 'Backup file not found.');
        }

        if (pathinfo($path, PATHINFO_EXTENSION) !== 'sql') {
            return redirect()->route('superadmin.settings', ['tab' => 'backup'])->with('err', 'Only SQL backup files can be restored.');
        }

        try {
            $sql = file_get_contents($path);
            if ($sql === false || trim($sql) === '') {
                return redirect()->route('superadmin.settings', ['tab' => 'backup'])->with('err', 'Backup file is empty or unreadable.');
            }

            DB::transaction(function () use ($sql) {
                DB::unprepared($sql);
            });

            $user = session('user') ?? ['id' => 'system', 'role' => 'superadmin'];
            CakeshopHelper::logActivity($user['id'], $user['role'], 'Restore Database', $file);

            return redirect()->route('superadmin.settings', ['tab' => 'backup'])->with('msg', "Database restored from: {$file}");
        } catch (\Throwable $e) {
            Log::error('Super admin restore failed: ' . $e->getMessage(), ['file' => $file, 'exception' => $e]);
            return redirect()->route('superadmin.settings', ['tab' => 'backup'])->with('err', 'Restore failed. The backup file may be incompatible or incomplete.');
        }
    }

    public function deleteBackup(Request $request)
    {
        $file = preg_replace('/[^A-Za-z0-9._-]/', '', $request->input('file', ''));
        $path = storage_path('app/backups') . DIRECTORY_SEPARATOR . $file;
        if ($file && is_file($path)) {
            if (!unlink($path)) {
                return redirect()->route('superadmin.settings', ['tab' => 'backup'])->with('err', 'Backup file could not be deleted.');
            }
            $user = session('user') ?? ['id' => 'system', 'role' => 'superadmin'];
            CakeshopHelper::logActivity($user['id'], $user['role'], 'Delete Backup', $file);
        }
        return redirect()->route('superadmin.settings', ['tab' => 'backup'])->with('msg', 'Backup deleted.');
    }
}
