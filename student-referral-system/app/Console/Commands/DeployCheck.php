<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

/**
 * Checks that a server is safe to put on the internet before anyone opens it:
 * production mode, no debug pages, real browser origins, a protected ML
 * engine, and no demo accounts still using the seeded password.
 *
 * Run it on the server after every deploy: php artisan deploy:check
 * Exit code is non-zero when anything FAILs, so a deploy script can stop.
 */
class DeployCheck extends Command
{
    protected $signature = 'deploy:check';

    protected $description = 'Check this server is configured safely before it goes live';

    /** The password every seeded demo account starts with (database/seeders). */
    private const SEEDED_PASSWORD = 'password';

    /** @var list<array{0: string, 1: string, 2: string}> status, check, detail */
    private array $rows = [];

    public function handle(): int
    {
        $this->checkMode();
        $this->checkUrls();
        $this->checkDatabase();
        $this->checkMlEngine();
        $this->checkSeededPasswords();
        $this->checkStorage();
        $this->checkBackups();

        $this->table(['', 'Check', 'Detail'], $this->rows);

        $fails = count(array_filter($this->rows, fn ($r) => $r[0] === 'FAIL'));
        $warns = count(array_filter($this->rows, fn ($r) => $r[0] === 'WARN'));

        if ($fails > 0) {
            $this->error("{$fails} problem(s) must be fixed before this server goes live.");
            return self::FAILURE;
        }

        $this->info($warns > 0
            ? "Ready, with {$warns} warning(s) worth a look."
            : 'Ready to go live.');

        return self::SUCCESS;
    }

    private function checkMode(): void
    {
        $env = (string) config('app.env');
        $this->row($env === 'production', 'APP_ENV is production', "APP_ENV={$env}");

        $debug = (bool) config('app.debug');
        $this->row(! $debug, 'Debug pages are off', $debug
            ? 'APP_DEBUG=true shows stack traces and settings to anyone who triggers an error'
            : 'APP_DEBUG=false');

        $this->row(filled(config('app.key')), 'APP_KEY is set', filled(config('app.key'))
            ? 'set'
            : 'run: php artisan key:generate');

        $level = (string) config('logging.channels.daily.level', config('logging.channels.single.level', 'debug'));
        $this->row($level !== 'debug', 'Log level is not debug', "LOG_LEVEL={$level}", warnOnly: true);
    }

    private function checkUrls(): void
    {
        $url = (string) config('app.url');
        $this->row(str_starts_with($url, 'https://'), 'APP_URL uses HTTPS', "APP_URL={$url}");

        $origins = (array) config('cors.allowed_origins', []);
        $patterns = (array) config('cors.allowed_origins_patterns', []);
        $insecure = array_filter($origins, fn ($o) => ! str_starts_with($o, 'https://') || str_contains($o, 'localhost') || str_contains($o, '127.0.0.1'));

        if ($origins === [] || in_array('*', $origins, true)) {
            $this->row(false, 'Browser origins are listed', 'set CORS_ALLOWED_ORIGINS to the Teacher and Student portal addresses');
        } elseif ($insecure !== [] || $patterns !== []) {
            $this->row(false, 'Browser origins are listed', 'only https:// portal addresses — no localhost: ' . implode(', ', array_merge($insecure, $patterns)));
        } else {
            $this->row(true, 'Browser origins are listed', implode(', ', $origins));
        }
    }

    private function checkDatabase(): void
    {
        $connection = (array) config('database.connections.' . config('database.default'), []);
        $user = (string) ($connection['username'] ?? '');
        $password = (string) ($connection['password'] ?? '');

        $this->row($password !== '', 'Database user has a password', $password === ''
            ? "DB_USERNAME={$user} with an empty DB_PASSWORD"
            : "DB_USERNAME={$user}");
        $this->row($user !== 'root', 'App uses its own database user', $user === 'root'
            ? 'create a user with rights to this database only, instead of root'
            : "DB_USERNAME={$user}", warnOnly: true);
    }

    private function checkMlEngine(): void
    {
        $url = (string) config('services.ml.url');
        $key = (string) config('services.ml.key');
        $local = (bool) preg_match('#^https?://(127\.0\.0\.1|localhost)(:\d+)?$#', $url);

        if ($key === '') {
            // Open endpoints are tolerable only while nothing else can reach them.
            $this->row(false, 'ML engine is protected', $local
                ? 'no ML_ENGINE_KEY; fine only while the engine stays on 127.0.0.1 behind the firewall'
                : "ML_ENGINE_URL={$url} is reachable from outside and ML_ENGINE_KEY is empty", warnOnly: $local);
        } else {
            $this->row(true, 'ML engine is protected', 'ML_ENGINE_KEY is set');
        }

        try {
            $ok = Http::timeout(3)->get($url . '/')->successful();
        } catch (\Throwable) {
            $ok = false;
        }
        $this->row($ok, 'ML engine answers', $ok
            ? $url
            : "no answer from {$url}; reports will be saved as \"Pending AI\" until it is up", warnOnly: true);

        $this->row(! config('services.ml.retrain_enabled'), 'In-app retraining is off', config('services.ml.retrain_enabled')
            ? 'ML_RETRAIN_ENABLED=true lets the admin page overwrite the validated model'
            : 'ML_RETRAIN_ENABLED=false', warnOnly: true);
    }

    /** Seeded demo accounts all start with the same well-known password. */
    private function checkSeededPasswords(): void
    {
        $weak = User::query()
            ->whereNotNull('password')
            ->get(['id', 'email', 'password'])
            ->filter(fn (User $u) => Hash::check(self::SEEDED_PASSWORD, $u->password))
            ->map(fn (User $u) => $u->email ?: "user #{$u->id}")
            ->values();

        $this->row($weak->isEmpty(), 'No account uses the demo password', $weak->isEmpty()
            ? 'none'
            : $weak->count() . ' account(s) still sign in with "' . self::SEEDED_PASSWORD . '": ' . $weak->take(5)->implode(', ')
                . ($weak->count() > 5 ? ', …' : ''));
    }

    private function checkStorage(): void
    {
        $paths = [storage_path('logs'), storage_path('framework/cache'), base_path('bootstrap/cache')];
        $notWritable = array_filter($paths, fn ($p) => ! is_writable($p));

        $this->row($notWritable === [], 'Storage folders are writable', $notWritable === []
            ? 'storage/ and bootstrap/cache/'
            : 'not writable by this user: ' . implode(', ', $notWritable));
    }

    private function checkBackups(): void
    {
        $binary = (string) config('backup.mysqldump');
        $found = is_file($binary) || $this->onPath($binary);

        $this->row($found, 'mysqldump is available for backups', $found
            ? $binary
            : "DB_DUMP_BINARY={$binary} not found; php artisan db:backup will fail", warnOnly: true);
    }

    private function onPath(string $binary): bool
    {
        if (str_contains($binary, '/') || str_contains($binary, '\\')) {
            return false;
        }

        foreach (explode(PATH_SEPARATOR, (string) getenv('PATH')) as $dir) {
            foreach (['', '.exe'] as $ext) {
                if (is_file($dir . DIRECTORY_SEPARATOR . $binary . $ext)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function row(bool $ok, string $check, string $detail, bool $warnOnly = false): void
    {
        $this->rows[] = [$ok ? 'PASS' : ($warnOnly ? 'WARN' : 'FAIL'), $check, $detail];
    }
}
