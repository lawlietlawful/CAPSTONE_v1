<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Dumps the app database to storage/app/backups and keeps the newest few.
 *
 * An unclean MySQL shutdown once left tables unreadable and the only copy
 * of the data was the one taken by hand before repairing it. This makes a
 * copy every day without anyone having to remember.
 *
 * Restore: mysql -u root student_referral_db < storage/app/backups/<file>.sql
 */
class BackupDatabase extends Command
{
    protected $signature = 'db:backup
                            {--if-older-than= : Skip when the newest backup is younger than this many hours}';

    protected $description = 'Back up the database with mysqldump and keep only the newest copies';

    /** mysqldump writes this as its very last line; without it the dump was cut short. */
    private const COMPLETE_MARKER = '-- Dump completed';

    public function handle(): int
    {
        $connection = config('database.connections.' . config('database.default'));

        if (! in_array($connection['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            $this->error('Backups need a MySQL/MariaDB connection.');
            return self::FAILURE;
        }

        $dir = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, config('backup.path')), DIRECTORY_SEPARATOR);
        File::ensureDirectoryExists($dir);

        $prefix = $connection['database'] . '_';
        $existing = $this->backups($dir, $prefix);

        if (($hours = $this->option('if-older-than')) !== null && $existing) {
            $newest = File::lastModified(end($existing));
            if ($newest > now()->subHours((float) $hours)->getTimestamp()) {
                $this->info('A recent backup already exists — skipping.');
                return self::SUCCESS;
            }
        }

        $file = $dir . DIRECTORY_SEPARATOR . $prefix . now()->format('Y-m-d_His') . '.sql';

        // The password goes in the environment, not the arguments, so it
        // never shows in a process list.
        $result = Process::timeout(600)
            ->env(array_filter(['MYSQL_PWD' => $connection['password'] ?? '']))
            ->run([
                config('backup.mysqldump'),
                '--host=' . $connection['host'],
                '--port=' . $connection['port'],
                '--user=' . $connection['username'],
                '--single-transaction',      // consistent copy without locking the app out
                '--routines',
                '--triggers',
                '--default-character-set=utf8mb4',
                '--result-file=' . $file,
                $connection['database'],
            ]);

        if (! $result->successful() || ! $this->isComplete($file)) {
            File::delete($file);
            $reason = trim($result->errorOutput()) ?: 'the dump was incomplete';
            Log::error('Database backup failed: ' . $reason);
            $this->error('Backup failed: ' . $reason);
            return self::FAILURE;
        }

        $removed = $this->prune($dir, $prefix);
        $size = number_format(File::size($file) / 1048576, 2);

        Log::info("Database backed up to {$file} ({$size} MB)");
        $this->info("Backed up to {$file} ({$size} MB)" . ($removed ? ", removed {$removed} old backup(s)" : ''));

        return self::SUCCESS;
    }

    /** This database's backups, oldest first (the names sort by date). */
    private function backups(string $dir, string $prefix): array
    {
        $files = File::glob($dir . DIRECTORY_SEPARATOR . $prefix . '*.sql');
        sort($files);

        return $files;
    }

    private function isComplete(string $file): bool
    {
        if (! is_file($file) || filesize($file) === 0) {
            return false;
        }

        $handle = fopen($file, 'rb');
        fseek($handle, -min(200, filesize($file)), SEEK_END);
        $tail = fread($handle, 200);
        fclose($handle);

        return str_contains($tail, self::COMPLETE_MARKER);
    }

    private function prune(string $dir, string $prefix): int
    {
        $old = array_slice(array_reverse($this->backups($dir, $prefix)), max(1, (int) config('backup.keep')));
        File::delete($old);

        return count($old);
    }
}
