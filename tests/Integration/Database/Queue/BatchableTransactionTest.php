<?php

namespace Illuminate\Tests\Integration\Database\Queue;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Tests\Integration\Database\DatabaseTestCase;
use Orchestra\Testbench\Attributes\WithMigration;
use Symfony\Component\Process\Exception\ProcessSignaledException;
use Symfony\Component\Process\Process;
use Throwable;
use function Orchestra\Testbench\remote;

#[WithMigration('laravel', 'queue')]
class BatchableTransactionTest extends DatabaseTestCase
{
    use DatabaseMigrations;

    protected function defineEnvironment($app)
    {
        if (Env::get('DB_CONNECTION') === 'testing') {
            // $this->markTestSkipped('Test does not support using :memory: database connection');
        }

        $app['config']->set([
            'database.default' => 'sqlite',
            'queue.default' => 'database',
            'queue.batching.database' => 'sqlite',
        ]);
    }

    public function testItCanHandleTimeoutJob()
    {
        Bus::batch([new Fixtures\TimeOutJobWithTransaction()])
            ->allowFailures()
            ->dispatch();

        $this->assertSame(1, DB::table('jobs')->count());
        $this->assertSame(0, DB::table('failed_jobs')->count());
        $this->assertSame(1, DB::table('job_batches')->count());

        try {
            remote('queue:work', [
                'DB_CONNECTION' => config('database.default'),
                'QUEUE_CONNECTION' => config('queue.default'),
            ])->run();
        } catch (Throwable $e) {
            $this->assertInstanceOf(ProcessSignaledException::class, $e);
            $this->assertSame('The process has been signaled with signal "9".', $e->getMessage());
        }

        $this->assertSame(0, DB::table('jobs')->count());
        $this->assertSame(1, DB::table('failed_jobs')->count());
    }
}
