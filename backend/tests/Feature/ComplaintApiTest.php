<?php
// Test API pengaduan Fase 3 (PEMBANGUNAN.md 4.5): kategori, pengajuan + foto, penolakan akun
// belum terverifikasi, daftar/lacak milik sendiri, balasan, unduh foto, dan papan publik
// yang menyamarkan pelapor anonim namun tetap menampilkan nama pelapor non-anonim.

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\User;
use App\Models\Village;
use App\Support\ComplaintStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ComplaintApiTest extends TestCase
{
    use RefreshDatabase;

    private Village $village;
    private User $warga;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->village = Village::factory()->create();
        $this->warga = User::factory()->verified()->create(['village_id' => $this->village->id]);
        $this->warga->assignRole('warga');

        Sanctum::actingAs($this->warga);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'category' => 'jalan_rusak',
            'title' => 'Jalan berlubang depan pasar',
            'description' => 'Lubang besar menggenang air, rawan kecelakaan.',
            'location' => 'Jl. Melati RT 3',
            'photos' => [UploadedFile::fake()->image('lubang.jpg')],
        ], $overrides);
    }

    public function test_categories_come_from_config(): void
    {
        $this->getJson('/api/complaints/categories')
            ->assertOk()
            ->assertJsonPath('data.0.code', 'jalan_rusak')
            ->assertJsonStructure(['data' => [['code', 'label']]]);
    }

    public function test_verified_warga_can_submit_with_photo(): void
    {
        $response = $this->postJson('/api/complaints', $this->payload())->assertStatus(201);

        $id = $response->json('data.id');
        $complaint = Complaint::find($id);

        $this->assertSame($this->village->id, $complaint->village_id);
        $this->assertSame(ComplaintStatus::ACCEPTED, $complaint->status);
        $this->assertCount(1, $complaint->photos);
        $this->assertCount(1, $complaint->replies()->where('to_status', ComplaintStatus::ACCEPTED)->get());
        $this->assertDatabaseHas('audit_logs', ['action' => 'complaint.submitted', 'subject_id' => $id]);
    }

    public function test_unverified_warga_cannot_submit(): void
    {
        $pending = User::factory()->create(['village_id' => $this->village->id]);
        $pending->assignRole('warga');
        Sanctum::actingAs($pending);

        $this->postJson('/api/complaints', $this->payload())->assertStatus(403);
        $this->assertSame(0, Complaint::count());
    }

    public function test_submit_requires_title_and_valid_category(): void
    {
        $this->postJson('/api/complaints', $this->payload(['title' => '']))
            ->assertStatus(422)->assertJsonValidationErrors('title');
        $this->postJson('/api/complaints', $this->payload(['category' => 'tidak_ada']))
            ->assertStatus(422)->assertJsonValidationErrors('category');
    }

    public function test_mine_lists_only_own_complaints(): void
    {
        $milikku = Complaint::factory()->create(['village_id' => $this->village->id, 'user_id' => $this->warga->id]);
        Complaint::factory()->create(['village_id' => $this->village->id]);

        $this->getJson('/api/my-complaints')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $milikku->id);
    }

    public function test_show_is_scoped_to_owner_or_staff(): void
    {
        $complaint = Complaint::factory()->create([
            'village_id' => $this->village->id, 'user_id' => $this->warga->id,
            'is_anonymous' => true,
        ]);
        $punOrang = Complaint::factory()->create(['village_id' => $this->village->id]);

        $this->getJson("/api/complaints/{$complaint->id}")->assertOk();
        $this->getJson("/api/complaints/{$punOrang->id}")->assertForbidden();

        // Petugas desa yang sama tetap bisa melihat walau pelapor anonim.
        $operator = User::factory()->create(['village_id' => $this->village->id]);
        $operator->assignRole('operator');
        Sanctum::actingAs($operator);
        $this->getJson("/api/complaints/{$complaint->id}")
            ->assertOk()
            ->assertJsonPath('data.reporter.name', $complaint->user->name);
    }

    public function test_owner_can_reply_while_open_but_not_after_close(): void
    {
        $open = Complaint::factory()->create(['village_id' => $this->village->id, 'user_id' => $this->warga->id]);
        $this->postJson("/api/complaints/{$open->id}/replies", ['body' => 'Ada updates?'])
            ->assertOk()->assertJsonCount(1, 'data.timeline');

        $done = Complaint::factory()->done()->create(['village_id' => $this->village->id, 'user_id' => $this->warga->id]);
        $this->postJson("/api/complaints/{$done->id}/replies", ['body' => 'Masih ada?'])->assertForbidden();
    }

    public function test_reply_requires_body(): void
    {
        $open = Complaint::factory()->create(['village_id' => $this->village->id, 'user_id' => $this->warga->id]);
        $this->postJson("/api/complaints/{$open->id}/replies", [])->assertStatus(422)->assertJsonValidationErrors('body');
    }

    public function test_photo_download_requires_view_permission(): void
    {
        $complaint = Complaint::factory()->create(['village_id' => $this->village->id, 'user_id' => $this->warga->id]);
        $photo = $complaint->photos()->create([
            'user_id' => $this->warga->id, 'disk' => 'local',
            'path' => 'complaints/seed.jpg', 'original_name' => 'seed.jpg',
        ]);
        Storage::disk('local')->put('complaints/seed.jpg', 'x');

        $this->getJson("/api/complaints/{$complaint->id}/photos/{$photo->id}")->assertOk();

        Sanctum::actingAs(User::factory()->verified()->create(['village_id' => $this->village->id]));
        $this->getJson("/api/complaints/{$complaint->id}/photos/{$photo->id}")->assertForbidden();
    }

    public function test_public_wall_masks_anonymous_but_shows_named_reporter(): void
    {
        $anon = Complaint::factory()->anonymous()->create(['village_id' => $this->village->id]);
        $terang = Complaint::factory()->create(['village_id' => $this->village->id]);
        $terang->load('user:id,name');

        // Tanpa login.
        $this->withoutMiddleware(\Illuminate\Auth\Middleware\EnsureAuthenticated::class);
        $response = $this->getJson('/api/complaints?village_id=' . $this->village->id)->assertOk();

        $items = collect($response->json('data'))->keyBy('id');
        $this->assertSame('Warga anonim', $items[$anon->id]['reporter_name']);
        $this->assertSame($terang->user->name, $items[$terang->id]['reporter_name']);
    }
}
