<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'label',
        'description',
    ];

    /**
     * Secrets: stored encrypted with the app key (type 'encrypted') and
     * never printed back into a page — the Settings form only shows whether
     * one is saved.
     */
    public const ENCRYPTED_KEYS = ['sms_api_key'];

    /**
     * Values already read during this request. Settings are read in loops
     * (three per SMS sent) and the cache store is the database, so a cache
     * lookup would be a query too — remembering them per request is the
     * saving that matters. The memo is a scoped container binding (one per
     * request / app instance, see AppServiceProvider) and is cleared
     * whenever a setting is saved.
     */
    private static function memo(): \ArrayObject
    {
        return app('settings.memo');
    }

    protected static function booted(): void
    {
        static::saved(fn () => self::memo()->exchangeArray([]));
        static::deleted(fn () => self::memo()->exchangeArray([]));
    }

    /**
     * Get a setting value by key.
     */
    public static function get($key, $default = null)
    {
        $memo = self::memo();
        if (! $memo->offsetExists($key)) {
            $memo[$key] = self::where('key', $key)->first();
        }

        $setting = $memo[$key];

        if (! $setting) {
            return $default;
        }

        return match ($setting->type) {
            'boolean'   => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
            'integer'   => (int) $setting->value,
            'json'      => json_decode($setting->value, true),
            'encrypted' => self::decrypted($setting->value, $default),
            default     => $setting->value,
        };
    }

    /**
     * Set a setting value by key.
     */
    public static function set($key, $value, $type = 'string')
    {
        if (in_array($key, self::ENCRYPTED_KEYS, true)) {
            $value = ($value === null || $value === '') ? '' : Crypt::encryptString((string) $value);
            $type = 'encrypted';
        } elseif (is_array($value)) {
            $value = json_encode($value);
            $type = 'json';
        } elseif (is_bool($value)) {
            $value = $value ? 'true' : 'false';
            $type = 'boolean';
        }

        return self::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'type' => $type]
        );
    }

    /** Whether a secret setting has a value saved (without revealing it). */
    public static function hasSecret(string $key): bool
    {
        return (string) self::get($key, '') !== '';
    }

    private static function decrypted(?string $value, $default)
    {
        if ($value === null || $value === '') {
            return $default;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            // Saved before encryption existed (plain text) — still usable;
            // it's encrypted the next time the Settings form is saved.
            return $value;
        }
    }
}
