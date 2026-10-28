<?php
// Fase 2 — API surat untuk warga (PEMBANGUNAN.md 4.2): daftar jenis, ajukan (wizard +
// lampiran), lacak status, dan perbaiki bagian yang ditandai operator. Verifikasi &
// penomoran dilakukan staff lewat panel Filament, bukan di sini.
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Letter;
use App\Models\LetterAttachment;
use App\Models\LetterType;
use App\Services\AuditLogger;
use App\Services\LetterWorkflow;
use App\Support\LetterFormValidator;
use App\Support\LetterStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class LetterController extends Controller
{
    public function __construct(private readonly LetterWorkflow $workflow)
    {
    }

    /** Jenis surat aktif yang tersedia untuk desa warga (bawaan + milik desanya). */
    public function types(Request $request)
    {
        $types = LetterType::availableTo($request->user()->village_id)->get([
            'id', 'code', 'name', 'needs_rt', 'form_schema_json', 'required_attachments_json',
        ]);

        return response()->json(['data' => $types]);
    }

    /** Ajukan surat baru (multipart: data[field] + attachments[]). */
    public function store(Request $request)
    {
        $user = $request->user();
        $this->authorize('create', Letter::class); // hanya warga terverifikasi (PEMBANGUNAN.md 4.1/4.2).

        $base = $request->validate([
            'letter_type_id' => ['required', 'integer', Rule::exists('letter_types', 'id')],
            'attachments' => ['present', 'array', 'min:1', 'max:5'],
            'attachments.*' => [
                'file',
                'mimetypes:' . implode(',', config('desa.letters.allowed_mime')),
                'max:' . config('desa.letters.max_attachment_kb'),
            ],
        ], [
            'attachments.present' => 'Unggah minimal satu lampiran (foto KTP/KK/usaha).',
            'attachments.*.mimetypes' => 'Lampiran harus berupa foto (JPG/PNG/WebP) atau PDF.',
            'attachments.*.max' => 'Ukuran lampiran terlalu besar (maks ~3 MB, hemat kuota).',
        ]);

        $type = LetterType::availableTo($user->village_id)->findOrFail($base['letter_type_id']);

        // Validasi isi formulir dinamis sesuai skema jenis surat.
        $validator = Validator::make(
            $request->all(),
            LetterFormValidator::rules($type->form_schema_json ?? []),
            LetterFormValidator::messages($type->form_schema_json ?? []),
        );
        if ($validator->fails()) {
            return response()->json(['message' => 'Periksa kembali isian surat.', 'errors' => $validator->errors()], 422);
        }

        $letter = Letter::create([
            'village_id' => $user->village_id,
            'letter_type_id' => $type->id,
            'user_id' => $user->id,
            'data_json' => $request->input('data', []),
            'status' => LetterStatus::SUBMITTED,
            'submitted_at' => now(),
        ]);

        $this->storeAttachments($letter, $request->file('attachments', []), $user->id);

        $letter->statusLogs()->create([
            'from_status' => null,
            'to_status' => LetterStatus::SUBMITTED,
            'note' => 'Surat diajukan warga.',
            'actor_id' => $user->id,
        ]);
        AuditLogger::record('letter.submitted', $letter, 'Warga mengajukan surat.', ['type' => $type->code]);

        // Tanpa pengantar RT, langsung antre ke operator.
        if (!$type->needs_rt) {
            $this->workflow->transition($letter, LetterStatus::OPERATOR);
        }

        return response()->json(['message' => 'Surat berhasil diajukan.', 'data' => $this->present($letter->fresh())], 201);
    }

    /** Daftar surat milik warga (ringkas, terbaru dulu). */
    public function index(Request $request)
    {
        $letters = $request->user()->letters()
            ->with('type:id,name,code')
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => $letters->through(fn (Letter $l) => $this->presentSummary($l))->items(),
            'meta' => ['total' => $letters->total(), 'page' => $letters->currentPage()],
        ]);
    }

    /** Detail satu surat + riwayat status + lampiran. */
    public function show(Request $request, Letter $letter)
    {
        $this->authorize('view', $letter);

        return response()->json(['data' => $this->present($letter->load('type', 'attachments', 'statusLogs.actor'))]);
    }

    /** Perbaiki & kirim ulang surat berstatus perlu_perbaikan. */
    public function update(Request $request, Letter $letter)
    {
        $this->authorize('update', $letter);

        $rules = LetterFormValidator::rules($letter->type->form_schema_json ?? []);
        $rules['attachments'] = ['sometimes', 'array', 'max:5'];
        $rules['attachments.*'] = [
            'file',
            'mimetypes:' . implode(',', config('desa.letters.allowed_mime')),
            'max:' . config('desa.letters.max_attachment_kb'),
        ];

        $validator = Validator::make(
            $request->all(),
            $rules,
            LetterFormValidator::messages($letter->type->form_schema_json ?? []),
        );
        if ($validator->fails()) {
            return response()->json(['message' => 'Periksa kembali isian surat.', 'errors' => $validator->errors()], 422);
        }

        $letter->update(['data_json' => $request->input('data', $letter->data_json)]);
        $this->storeAttachments($letter, $request->file('attachments', []), $request->user()->id, 'perbaikan');

        $this->workflow->transition($letter, LetterStatus::SUBMITTED, $request->user(), 'Warga memperbaiki data.');

        if (!$letter->type->needs_rt) {
            $this->workflow->transition($letter->fresh(), LetterStatus::OPERATOR);
        }

        return response()->json(['message' => 'Perbaikan terkirim.', 'data' => $this->present($letter->fresh())]);
    }

    /** Unduh lampiran (disk privat; hanya yang boleh melihat surat). */
    public function attachment(Request $request, Letter $letter, LetterAttachment $attachment)
    {
        $this->authorize('view', $letter);
        abort_unless($attachment->letter_id === $letter->id, 404);

        $full = Storage::disk($attachment->disk)->path($attachment->path);
        abort_unless(is_file($full), 404);

        return response()->download($full, $attachment->original_name ?: basename($attachment->path));
    }

    private function storeAttachments(Letter $letter, array $files, int $userId, string $kind = 'lampiran'): void
    {
        foreach ($files as $file) {
            if (!$file) {
                continue;
            }
            $path = $letter->id ? sprintf('letters/%d/%s.%s', $letter->id, (string) \Illuminate\Support\Str::uuid(), $file->getClientOriginalExtension() ?: 'bin') : null;
            $stored = Storage::disk('local')->put($path, file_get_contents($file->getRealPath()));

            LetterAttachment::create([
                'letter_id' => $letter->id,
                'user_id' => $userId,
                'disk' => 'local',
                'path' => $stored ?: $path,
                'original_name' => $file->getClientOriginalName(),
                'mime' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'kind' => $kind,
            ]);
        }
    }

    private function present(Letter $letter): array
    {
        return [
            'id' => $letter->id,
            'type' => $letter->type?->only(['id', 'name', 'code', 'needs_rt']),
            'status' => $letter->status,
            'status_label' => $letter->statusLabel(),
            'number' => $letter->number,
            'data' => $letter->data_json,
            'note' => $letter->note,
            'flagged_fields' => $letter->flaggedFields(),
            'editable' => $letter->isEditableByCitizen(),
            'submitted_at' => $letter->submitted_at?->toIso8601String(),
            'completed_at' => $letter->completed_at?->toIso8601String(),
            'attachments' => $letter->attachments?->map(fn (LetterAttachment $a) => [
                'id' => $a->id,
                'name' => $a->original_name,
                'download_url' => "/api/letters/{$letter->id}/attachments/{$a->id}",
            ])->values(),
            'history' => $letter->statusLogs?->map(fn ($log) => [
                'from' => $log->from_status,
                'to' => $log->to_status,
                'to_label' => LetterStatus::label($log->to_status),
                'note' => $log->note,
                'flagged' => $log->flagged_fields_json,
                'at' => $log->created_at?->toIso8601String(),
            ])->values(),
        ];
    }

    private function presentSummary(Letter $letter): array
    {
        return [
            'id' => $letter->id,
            'type_name' => $letter->type?->name,
            'status' => $letter->status,
            'status_label' => $letter->statusLabel(),
            'number' => $letter->number,
            'submitted_at' => $letter->submitted_at?->toIso8601String(),
        ];
    }
}
