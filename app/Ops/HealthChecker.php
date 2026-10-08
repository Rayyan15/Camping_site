<?php

namespace App\Ops;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthChecker
{
    /**
     * @return array<string, array{ok: bool, detail: string}>
     */
    public function run(): array
    {
        return [
            'database' => $this->guard(fn () => $this->database()),
            'cache' => $this->guard(fn () => $this->cache()),
            'storage writable' => $this->guard(fn () => $this->storageWritable()),
            'storage link' => $this->guard(fn () => $this->storageLink()),
            'scheduler heartbeat' => $this->guard(fn () => $this->schedulerHeartbeat()),
            'failed jobs' => $this->guard(fn () => $this->failedJobs()),
            'queue backlog' => $this->guard(fn () => $this->queueBacklog()),
        ];
    }

    /**
     * @param  array<string, array{ok: bool, detail: string}>  $results
     */
    public function passes(array $results): bool
    {
        return collect($results)->every(fn (array $result): bool => $result['ok']);
    }

    /**
     * @return array{ok: bool, detail: string}
     */
    private function guard(callable $check): array
    {
        try {
            return $check();
        } catch (Throwable $e) {
            return $this->result(false, $e->getMessage());
        }
    }

    /**
     * @return array{ok: bool, detail: string}
     */
    private function result(bool $ok, string $detail): array
    {
        return ['ok' => $ok, 'detail' => $detail];
    }

    private function database(): array
    {
        DB::select('select 1');

        return $this->result(true, 'terhubung ke '.config('database.default'));
    }

    private function cache(): array
    {
        $key = 'ops:health-probe';
        Cache::put($key, 'ok', 10);
        $ok = Cache::get($key) === 'ok';
        Cache::forget($key);

        return $this->result($ok, $ok ? 'baca tulis berhasil ('.config('cache.default').')' : 'nilai tidak terbaca kembali');
    }

    private function storageWritable(): array
    {
        $directories = [storage_path('app'), storage_path('framework'), storage_path('logs')];
        $blocked = array_filter($directories, fn (string $dir): bool => ! is_dir($dir) || ! is_writable($dir));

        return $blocked === []
            ? $this->result(true, 'storage dapat ditulis')
            : $this->result(false, 'tidak dapat ditulis: '.implode(', ', $blocked));
    }

    private function storageLink(): array
    {
        $missing = array_filter(array_keys(config('filesystems.links')), fn (string $link): bool => ! file_exists($link));

        return $missing === []
            ? $this->result(true, 'symlink storage ada')
            : $this->result(false, 'jalankan php artisan storage:link, belum ada: '.implode(', ', $missing));
    }

    private function schedulerHeartbeat(): array
    {
        $lastRun = Cache::get(config('ops.heartbeat_cache_key'));

        if ($lastRun === null) {
            return $this->result(false, 'belum pernah berjalan, cek cron schedule:run');
        }

        $age = now()->getTimestamp() - (int) $lastRun;
        $maxAge = (int) config('ops.heartbeat_max_age_seconds');

        return $this->result($age <= $maxAge, "terakhir berjalan {$age} detik lalu (batas {$maxAge})");
    }

    private function failedJobs(): array
    {
        $count = DB::table(config('queue.failed.table'))->count();
        $limit = (int) config('ops.max_failed_jobs');

        return $this->result($count <= $limit, "{$count} job gagal (batas {$limit})");
    }

    private function queueBacklog(): array
    {
        if (config('queue.default') !== 'database') {
            return $this->result(true, 'dilewati, antrean bukan database');
        }

        $oldest = DB::table(config('queue.connections.database.table'))->whereNull('reserved_at')->min('available_at');
        $wait = $oldest === null ? 0 : max(0, now()->getTimestamp() - (int) $oldest);
        $limit = (int) config('ops.max_queue_wait_seconds');

        return $this->result($wait <= $limit, "job tertua menunggu {$wait} detik (batas {$limit}), cek worker supervisor");
    }
}
