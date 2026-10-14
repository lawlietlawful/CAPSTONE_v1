<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * php artisan db:backup — an unclean MySQL shutdown once left the data
 * readable only because a copy had been taken by hand first.
 */
class DatabaseBackupTest extends TestCase
{
    private string $dir;
    private string $prefix;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = storage_path('framework/testing/backups-' . uniqid());
        config(['backup.path' => $this->dir, 'backup.keep' => 3, 'backup.mysqldump' => 'mysqldump']);
        $this->prefix = config('database.connections.mysql.database') . '_';
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    /** Fake mysqldump: write what it would to --result-file. */
    private function fakeDump(string $body = "-- dump\n-- Dump completed on 2026-10-14 08:00:00\n", int $exit = 0): void
    {
        Process::fake(function (PendingProcess $process) use ($body, $exit) {
            $arg = collect($process->command)->first(fn ($a) => str_starts_with($a, '--result-file='));
            file_put_contents(substr($arg, strlen('--result-file=')), $body);

            return Process::result(errorOutput: $exit ? 'mysqldump: Got error: 2002' : '', exitCode: $exit);
        });
    }

    private function backups(): array
    {
        $files = array_map('basename', File::glob($this->dir . '/*.sql'));
        sort($files);

        return $files;
    }

    private function oldBackup(string $stamp, int $ageHours = 48): string
    {
        File::ensureDirectoryExists($this->dir);
        $path = $this->dir . '/' . $this->prefix . $stamp . '.sql';
        file_put_contents($path, "-- Dump completed\n");
        touch($path, now()->subHours($ageHours)->getTimestamp());

        return basename($path);
    }

    public function test_it_dumps_the_database_into_the_backup_folder(): void
    {
        $this->fakeDump();

        $this->artisan('db:backup')->assertSuccessful()->expectsOutputToContain('Backed up to');

        $this->assertCount(1, $this->backups());
        $this->assertStringStartsWith($this->prefix . now()->format('Y-m-d'), $this->backups()[0]);
        Process::assertRan(fn (PendingProcess $p) => $p->command[0] === 'mysqldump'
            && in_array('--single-transaction', $p->command, true)
            && end($p->command) === config('database.connections.mysql.database'));
    }

    public function test_the_password_never_appears_in_the_command_line(): void
    {
        config(['database.connections.mysql.password' => 's3cret-pass']);
        $this->fakeDump();

        $this->artisan('db:backup')->assertSuccessful();

        Process::assertRan(fn (PendingProcess $p) => ! str_contains(implode(' ', $p->command), 's3cret-pass')
            && ($p->environment['MYSQL_PWD'] ?? null) === 's3cret-pass');
    }

    public function test_a_failed_dump_leaves_no_partial_file(): void
    {
        $this->fakeDump("-- half a dump\nINSERT INTO", exit: 2);

        $this->artisan('db:backup')->assertFailed()->expectsOutputToContain('mysqldump: Got error: 2002');

        $this->assertSame([], $this->backups());
    }

    public function test_a_dump_cut_short_is_thrown_away_even_if_mysqldump_exited_cleanly(): void
    {
        $this->fakeDump("-- MariaDB dump\nINSERT INTO `students` VALUES (1,");

        $this->artisan('db:backup')->assertFailed()->expectsOutputToContain('incomplete');

        $this->assertSame([], $this->backups());
    }

    public function test_a_failed_backup_never_deletes_the_older_good_ones(): void
    {
        $kept = [$this->oldBackup('2026-10-01_080000'), $this->oldBackup('2026-10-02_080000'),
                 $this->oldBackup('2026-10-03_080000'), $this->oldBackup('2026-10-04_080000')];
        $this->fakeDump('', exit: 2);

        $this->artisan('db:backup')->assertFailed();

        $this->assertSame($kept, $this->backups());
    }

    public function test_only_the_newest_backups_are_kept(): void
    {
        $this->oldBackup('2026-10-01_080000');
        $this->oldBackup('2026-10-02_080000');
        $third = $this->oldBackup('2026-10-03_080000');
        $fourth = $this->oldBackup('2026-10-04_080000');
        File::put($this->dir . '/notes.txt', 'not a backup');
        File::put($this->dir . '/other_db_2026-09-01_080000.sql', 'another database');
        $this->fakeDump();

        $this->artisan('db:backup')->assertSuccessful()->expectsOutputToContain('removed 2 old backup(s)');

        $files = $this->backups();
        $this->assertCount(4, $files);   // keep = 3 of ours, plus the other database's file
        $this->assertContains($third, $files);
        $this->assertContains($fourth, $files);
        $this->assertContains('other_db_2026-09-01_080000.sql', $files);
        $this->assertFileExists($this->dir . '/notes.txt');
    }

    public function test_if_older_than_skips_when_a_recent_backup_exists(): void
    {
        $this->oldBackup('2026-10-14_070000', ageHours: 2);
        $this->fakeDump();

        $this->artisan('db:backup', ['--if-older-than' => 20])->assertSuccessful()->expectsOutputToContain('skipping');

        Process::assertNothingRan();
    }

    public function test_if_older_than_backs_up_when_the_newest_is_old_enough(): void
    {
        $this->oldBackup('2026-10-13_070000', ageHours: 25);
        $this->fakeDump();

        $this->artisan('db:backup', ['--if-older-than' => 20])->assertSuccessful()->expectsOutputToContain('Backed up to');

        $this->assertCount(2, $this->backups());
    }

    public function test_the_backup_is_scheduled(): void
    {
        $events = collect(app(Schedule::class)->events())->filter(fn ($e) => str_contains($e->command, 'db:backup'));

        $this->assertCount(1, $events);
        $this->assertStringContainsString('--if-older-than=20', $events->first()->command);
        $this->assertSame('0 * * * *', $events->first()->expression);
    }
}
