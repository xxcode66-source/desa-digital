<?php
// Test layanan audit log (PEMBANGUNAN.md bagian 5: semua tindakan penting tercatat).

namespace Tests\Feature;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLoggerTest extends TestCase
{
    use RefreshDatabase;

    public function test_record_creates_audit_log_with_actor(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $log = AuditLogger::record('auth.login', $user, 'Warga masuk');

        $this->assertDatabaseHas('audit_logs', [
            'id' => $log->id,
            'user_id' => $user->id,
            'action' => 'auth.login',
            'subject_type' => $user->getMorphClass(),
            'subject_id' => $user->id,
        ]);
    }

    public function test_stores_properties_as_json(): void
    {
        $log = AuditLogger::record('test.action', null, null, ['foo' => 'bar']);

        $this->assertSame(['foo' => 'bar'], $log->fresh()->properties);
    }
}
