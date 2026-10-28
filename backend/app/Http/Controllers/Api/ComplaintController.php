<?php
// Fase 3 — API pengaduan (PEMBANGUNAN.md 4.5). Warga mengajukan & memantau laporannya,
// papan publik menampilkan laporan warga (identitas disamarkan bila anonim), dan balasan
// petugas tampil sebagai kronologi. Perubahan status dilakukan staff lewat panel Filament.
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\ComplaintPhoto;
use App\Models\ComplaintReply;
use App\Services\AuditLogger;
use App\Services\ComplaintWorkflow;
use App\Support\ComplaintStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ComplaintController extends Controller
{
    public function __construct(private readonly ComplaintWorkflow $workflow)
    {
    }

    /** Kategori baku pengaduan (config desa) untuk mengisi dropdown formulir. */
    public function categories()
    {
        $options = collect(config('desa.complaints.categories'))
            ->map(fn (string $label, string $code) => ['code' => $code, 'label' => $label])
            ->values();

        return response()->json(['data' => $options]);
    }

    /** Papan publik: daftar pengaduan (identitas pelapor disamarkan bila anonim). Tanpa login. */
    public function index(Request $request)
    {
        $complaints = Complaint::query()
            ->with('user:id,name')
            ->when($request->integer('village_id'), fn ($q, int $v) => $q->where('village_id', $v))
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => $complaints->through(fn (Complaint $c) => $this->presentPublic($c))->items(),
            'meta' => ['total' => $complaints->total(), 'page' => $complaints->currentPage()],
        ]);
    }

    /** Daftar pengaduan milik warga yang login (reporter selalu tampil untuk pemilik). */
    public function mine(Request $request)
    {
        $complaints = $request->user()->complaints()
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => $complaints->through(fn (Complaint $c) => $this->presentSummary($c))->items(),
            'meta' => ['total' => $complaints->total(), 'page' => $complaints->currentPage()],
        ]);
    }

    /** Ajukan pengaduan (multipart: isian + photos[]). Hanya warga terverifikasi. */
    public function store(Request $request)
    {
        $user = $request->user();
        $this->authorize('create', Complaint::class);

        $maxPhotos = (int) config('desa.complaints.max_photos');

        $data = $request->validate([
            'category' => ['required', 'string', Rule::in(array_keys(config('desa.complaints.categories')))],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:5000'],
            'location' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_anonymous' => ['boolean'],
            'photos' => ['sometimes', 'array', 'max:' . $maxPhotos],
            'photos.*' => [
                'file',
                'mimetypes:' . implode(',', config('desa.complaints.allowed_mime')),
                'max:' . config('desa.complaints.max_photo_kb'),
            ],
        ], [
            'category.required' => 'Pilih dulu kategori laporannya.',
            'category.in' => 'Kategori laporan tidak dikenali.',
            'title.required' => 'Judul laporan tidak boleh kosong.',
            'description.required' => 'Jelaskan laporannya sebentar.',
            'photos.*.mimetypes' => 'Foto bukti harus berupa gambar (JPG/PNG/WebP).',
            'photos.*.max' => 'Ukuran foto terlalu besar (maks ~3 MB, hemat kuota).',
        ]);

        $complaint = Complaint::create([
            'village_id' => $user->village_id,
            'user_id' => $user->id,
            'category' => $data['category'],
            'title' => $data['title'],
            'description' => $data['description'],
            'location' => $data['location'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'status' => ComplaintStatus::ACCEPTED,
            'is_anonymous' => (bool) ($data['is_anonymous'] ?? false),
            'submitted_at' => now(),
        ]);

        $this->storePhotos($complaint, $request->file('photos', []), $user->id);

        $complaint->replies()->create([
            'user_id' => $user->id,
            'body' => 'Laporan diajukan warga.',
            'from_status' => null,
            'to_status' => ComplaintStatus::ACCEPTED,
            'is_staff' => false,
        ]);
        AuditLogger::record('complaint.submitted', $complaint, 'Warga mengajukan pengaduan.', ['category' => $complaint->category]);

        return response()->json(['message' => 'Laporan berhasil dikirim.', 'data' => $this->present($complaint->fresh())], 201);
    }

    /** Detail satu pengaduan + kronologi + foto (pemilik / petugas desa). */
    public function show(Request $request, Complaint $complaint)
    {
        $this->authorize('view', $complaint);

        return response()->json([
            'data' => $this->present($complaint->load('user:id,name', 'replies.author:id,name', 'photos')),
        ]);
    }

    /** Balasan warga/petugas pada pengaduan yang masih terbuka. */
    public function reply(Request $request, Complaint $complaint)
    {
        $this->authorize('reply', $complaint);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ], [
            'body.required' => 'Tulis dulu balasannya.',
        ]);

        $this->workflow->reply($complaint, $validated['body'], $request->user());

        return response()->json([
            'message' => 'Balasan terkirim.',
            'data' => $this->present($complaint->fresh(['user:id,name', 'replies.author:id,name', 'photos'])),
        ]);
    }

    /** Unduh foto bukti (disk privat; hanya yang boleh melihat pengaduan). */
    public function photo(Request $request, Complaint $complaint, ComplaintPhoto $photo)
    {
        $this->authorize('view', $complaint);
        abort_unless($photo->complaint_id === $complaint->id, 404);

        $full = Storage::disk($photo->disk)->path($photo->path);
        abort_unless(is_file($full), 404);

        return response()->download($full, $photo->original_name ?: basename($photo->path));
    }

    private function storePhotos(Complaint $complaint, array $files, int $userId): void
    {
        foreach ($files as $file) {
            if (!$file) {
                continue;
            }
            $path = sprintf('complaints/%d/%s.%s', $complaint->id, (string) \Illuminate\Support\Str::uuid(), $file->getClientOriginalExtension() ?: 'bin');
            Storage::disk('local')->put($path, file_get_contents($file->getRealPath()));

            ComplaintPhoto::create([
                'complaint_id' => $complaint->id,
                'user_id' => $userId,
                'disk' => 'local',
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime' => $file->getClientMimeType(),
                'size' => $file->getSize(),
            ]);
        }
    }

    private function categoryLabel(string $code): string
    {
        return config('desa.complaints.categories')[$code] ?? $code;
    }

    private function present(Complaint $complaint): array
    {
        return [
            'id' => $complaint->id,
            'category' => $complaint->category,
            'category_label' => $this->categoryLabel($complaint->category),
            'title' => $complaint->title,
            'description' => $complaint->description,
            'location' => $complaint->location,
            'latitude' => $complaint->latitude,
            'longitude' => $complaint->longitude,
            'status' => $complaint->status,
            'status_label' => $complaint->statusLabel(),
            'is_anonymous' => $complaint->is_anonymous,
            'reporter' => ['id' => $complaint->user_id, 'name' => $complaint->user?->name],
            'closed' => $complaint->isClosed(),
            'submitted_at' => $complaint->submitted_at?->toIso8601String(),
            'resolved_at' => $complaint->resolved_at?->toIso8601String(),
            'photos' => $complaint->photos?->map(fn (ComplaintPhoto $p) => [
                'id' => $p->id,
                'name' => $p->original_name,
                'download_url' => "/api/complaints/{$complaint->id}/photos/{$p->id}",
            ])->values(),
            'timeline' => $complaint->replies?->map(fn (ComplaintReply $r) => [
                'id' => $r->id,
                'body' => $r->body,
                'is_staff' => $r->is_staff,
                'author' => $r->user_id === null ? 'Sistem' : ($r->author?->name ?? 'Petugas'),
                'to_status' => $r->to_status,
                'to_label' => $r->statusLabel(),
                'at' => $r->created_at?->toIso8601String(),
            ])->values(),
        ];
    }

    /** Versi papan publik: tanpa identitas pelapor bila anonim, tanpa kronologi internal. */
    private function presentPublic(Complaint $complaint): array
    {
        return [
            'id' => $complaint->id,
            'category_label' => $this->categoryLabel($complaint->category),
            'title' => $complaint->title,
            'description' => $complaint->description,
            'location' => $complaint->location,
            'status' => $complaint->status,
            'status_label' => $complaint->statusLabel(),
            'reporter_name' => $complaint->publicReporterName(),
            'submitted_at' => $complaint->submitted_at?->toIso8601String(),
            'resolved_at' => $complaint->resolved_at?->toIso8601String(),
        ];
    }

    private function presentSummary(Complaint $complaint): array
    {
        return [
            'id' => $complaint->id,
            'category_label' => $this->categoryLabel($complaint->category),
            'title' => $complaint->title,
            'status' => $complaint->status,
            'status_label' => $complaint->statusLabel(),
            'closed' => $complaint->isClosed(),
            'submitted_at' => $complaint->submitted_at?->toIso8601String(),
        ];
    }
}
