<?php

namespace Tests\Feature;

use App\Mail\InvoiceIssuedMail;
use App\Mail\SupplierLoginOtpMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ProductionMailQueueConfigTest extends TestCase
{
    public function test_mailables_implement_should_queue(): void
    {
        $this->assertTrue(is_subclass_of(SupplierLoginOtpMail::class, ShouldQueue::class));
        $this->assertTrue(class_exists(InvoiceIssuedMail::class));
    }

    public function test_queue_default_connection_reads_env_database_safely(): void
    {
        Config::set('queue.default', 'database');
        $this->assertSame('database', config('queue.default'));
        $this->assertArrayHasKey('database', config('queue.connections'));
        $this->assertArrayHasKey('redis', config('queue.connections'));
    }

    public function test_filesystem_s3_disk_is_configured_from_env_placeholders(): void
    {
        $s3 = config('filesystems.disks.s3');
        $this->assertSame('s3', $s3['driver']);
        $this->assertArrayHasKey('bucket', $s3);
        $this->assertArrayHasKey('endpoint', $s3);
    }

    public function test_sanctum_expiration_can_be_configured(): void
    {
        config(['sanctum.expiration' => 480]);
        $this->assertSame(480, config('sanctum.expiration'));
    }
}
