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
        $data = $request->except(['_token', '_method']);

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

        return redirect()->route('admin.settings.index')->with('success', 'System settings updated successfully.');
    }
}
