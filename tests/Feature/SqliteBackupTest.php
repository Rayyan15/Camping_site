<?php

namespace Tests\Feature;

use App\Ops\BackupPruner;
use App\Ops\DatabaseBackup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SqliteBackupTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = storage_path('framework/testing/backups-'.getmypid());
        config(['backup.directory' => $this->directory]);

        // No RefreshDatabase: VACUUM cannot run inside its wrapping transaction, and production runs
        // the backup from the scheduler outside any transaction too.
        DB::statement('create table guests (name text)');
        DB::table('guests')->insert(['name' => 'Budi']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);

        parent::tearDown();
    }

    public function test_sqlite_snapshot_is_a_gzipped_database_the_pruner_recognises(): void
    {
        $path = app(DatabaseBackup::class)->create();

        $this->assertStringEndsWith('.sqlite.gz', $path);
        $this->assertStringStartsWith('SQLite format 3', (string) gzdecode((string) file_get_contents($path)));
        $this->assertSame([$path], app(BackupPruner::class)->expired([$path], now()->addDays(30), 14));
    }

    public function test_backup_command_succeeds_on_sqlite(): void
    {
        $this->artisan('app:backup-database')->assertSuccessful();

        $this->assertCount(1, File::files($this->directory));
    }
}
