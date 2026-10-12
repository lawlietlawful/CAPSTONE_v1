<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Setting;

class SettingController extends Controller
{
    public function index()
    {
        // Load all settings grouped by their 'group' field, or just raw
        $settings = Setting::all()->keyBy('key');

        return view('admin.settings.index', compact('settings'));
    }

    public function update(Request $request)
    {
        // Secrets are handled on their own: encrypted at rest, and a blank
        // field means "keep the saved one" (the form never shows it).
        $data = $request->except(array_merge(['_token', '_method', 'clear_sms_api_key'], Setting::ENCRYPTED_KEYS));
        $before = Setting::whereNotIn('key', Setting::ENCRYPTED_KEYS)->pluck('value', 'key');
        $changed = [];

        if ($request->boolean('clear_sms_api_key')) {
            Setting::set('sms_api_key', '');
            $changed[] = 'SMS API key removed';
        } elseif ($request->filled('sms_api_key')) {
            Setting::set('sms_api_key', $request->input('sms_api_key'));
            $changed[] = 'SMS API key replaced';
        }

        foreach ($data as $key => $value) {
            // Determine type loosely
            $type = 'string';
            if (is_numeric($value)) {
                $type = 'integer';
            } elseif ($value === 'true' || $value === 'false' || $value === '1' || $value === '0') {
                $type = 'boolean';
            }

            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value, 'type' => $type]
            );
        }

        // Also handle checkboxes that might be unchecked (and thus not sent in POST)
        // If we expect specific boolean settings, we should check for them:
        $booleanKeys = [
            'sms_enabled',
            'auto_flag_high_risk',
        ];

        foreach ($booleanKeys as $bKey) {
            if (!$request->has($bKey)) {
                Setting::updateOrCreate(
                    ['key' => $bKey],
                    ['value' => '0', 'type' => 'boolean']
                );
            }
        }

        // Which settings changed — names only, never values.
        $after = Setting::whereNotIn('key', Setting::ENCRYPTED_KEYS)->pluck('value', 'key');
        foreach ($after as $key => $value) {
            if ((string) ($before[$key] ?? '') !== (string) $value) {
                $changed[] = $key;
            }
        }
        if ($changed) {
            \App\Models\AuditLog::record('settings.updated', null, 'Changed settings: ' . implode(', ', $changed));
        }

        return redirect()->route('admin.settings.index')->with('success', 'System settings updated successfully.');
    }
}
