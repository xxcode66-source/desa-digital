<?php
// Fase 4 — API PBB (PEMBANGUNAN.md 4.3). Warga: cek NOP (nama WP disamarkan + rate limit),
// ajukan klaim kepemilikan, lihat "NOP saya" + tunggakan, dan riwayat klaimnya.
// RT/Kolektor: lihat tagihan lingkup RT & ajukan slip setoran. Bendahara menilai setoran
// lewat panel Filament. TANPA pembayaran in-app: status lunas = verifikasi internal desa.
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PbbBill;
use App\Models\PbbClaim;
use App\Models\PbbDepositBatch;
use App\Models\PbbObject;
use App\Services\AuditLogger;
use App\Services\PbbDepositService;
use App\Support\PbbStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PbbController extends Controller
{
    public function __construct(private readonly PbbDepositService $deposits)
    {
    }

    /** Cek NOP (publik, dibatasi): tampilkan objek + ringkasan tagihan, nama WP disamarkan. */
    public function cekNop(Request $request)
    {
        $data = $request->validate([
            'nop' => ['required', 'string', 'max:32'],
            'village_id' => ['required', 'integer'],
        ], [
            'nop.required' => 'Masuk dulu Nomor Objek Pajak (NOP)-nya.',
            'village_id.required' => 'Desa tidak dikenali.',
        ]);

        $nop = $this->normalizeNop($data['nop']);

        $object = PbbObject::where('village_id', $data['village_id'])
            ->where('nop', $nop)
            ->with('bills')
            ->first();

        if (!$object) {
            return response()->json([
                'message' => 'NOP tidak ditemukan di desa ini. Pastikan 18 digit NOP benar.',
            ], 404);
        }

        return response()->json(['data' => $this->presentObjectLookup($object)]);
    }

    /** Ajukan klaim kepemilikan NOP (multipart + foto bukti SPPT/surat tanah). */
    public function storeClaim(Request $request)
    {
        $this->authorize('create', PbbClaim::class);
        $user = $request->user();

        $data = $request->validate([
            'nop' => ['required', 'string', 'max:32'],
            'note' => ['nullable', 'string', 'max:1000'],
            'evidence' => [
                'required', 'file',
                'mimetypes:' . implode(',', config('desa.pbb.allowed_mime')),
                'max:' . config('desa.pbb.evidence_max_kb'),
            ],
        ], [
            'nop.required' => 'Isi NOP yang ingin diklaim.',
            'evidence.required' => 'Lampirkan foto bukti (SPPT / surat tanah).',
            'evidence.mimetypes' => 'Bukti harus berupa gambar (JPG/PNG/WebP).',
            'evidence.max' => 'Ukuran foto bukti terlalu besar (maks ~3 MB).',
        ]);

        $nop = $this->normalizeNop($data['nop']);
        $object = PbbObject::where('village_id', $user->village_id)->where('nop', $nop)->first();
        if (!$object) {
            return response()->json(['message' => 'NOP tidak ditemukan di desa Anda.'], 422);
        }

        $claim = PbbClaim::firstOrNew(['user_id' => $user->id, 'pbb_object_id' => $object->id]);
        if ($claim->exists && in_array($claim->status, [PbbStatus::CLAIM_PENDING, PbbStatus::CLAIM_APPROVED], true)) {
            $msg = $claim->status === PbbStatus::CLAIM_APPROVED
                ? 'NOP ini sudah atas nama Anda.'
                : 'Klaim NOP ini masih menunggu verifikasi desa.';

            return response()->json(['message' => $msg], 422);
        }

        if ($file = $request->file('evidence')) {
            $path = sprintf('pbb/claims/%d/%s.%s', $user->id, (string) Str::uuid(), $file->getClientOriginalExtension() ?: 'bin');
            Storage::disk('local')->put($path, file_get_contents($file->getRealPath()));
            $claim->fill([
                'disk' => 'local',
                'evidence_path' => $path,
                'evidence_name' => $file->getClientOriginalName(),
            ]);
        }

        $claim->fill([
            'village_id' => $user->village_id,
            'pbb_object_id' => $object->id,
            'note' => $data['note'] ?? null,
            'status' => PbbStatus::CLAIM_PENDING,
            'reviewed_by' => null,
            'review_note' => null,
            'reviewed_at' => null,
        ])->save();

        AuditLogger::record('pbb.claim.submitted', $claim, 'Warga mengajukan klaim kepemilikan NOP.', ['nop' => $object->nop]);

        return response()->json(['message' => 'Klaim terkirim, menunggu verifikasi desa.', 'data' => $this->presentClaim($claim->load('object'))], 201);
    }

    /** Daftar NOP yang sudah atas nama warga (klaim disetujui) + ringkasan tunggakan. */
    public function mineObjects(Request $request)
    {
        $user = $request->user();
        $objects = PbbObject::with('bills')
            ->where('village_id', $user->village_id)
            ->whereHas('approvedClaims', fn ($q) => $q->where('user_id', $user->id))
            ->get();

        return response()->json([
            'data' => $objects->map(fn (PbbObject $o) => [
                'id' => $o->id,
                'nop' => $o->nop,
                'wp_name' => $o->wp_name,
                'address' => $o->address,
                'rt' => $o->rt,
                'rw' => $o->rw,
                'arrears_count' => $o->bills->filter(fn (PbbBill $b) => $b->isArrears())->count(),
                'arrears_total' => (float) $o->bills->filter(fn (PbbBill $b) => $b->isArrears())->sum(fn (PbbBill $b) => $b->total()),
                'bills' => $o->bills->map(fn (PbbBill $b) => $this->presentBill($b, lookup: false))->values(),
            ])->values(),
        ]);
    }

    /** Riwayat klaim NOP milik warga. */
    public function mineClaims(Request $request)
    {
        $claims = $request->user()->pbbClaims()->with('object')->latest()->get();

        return response()->json([
            'data' => $claims->map(fn (PbbClaim $c) => $this->presentClaim($c))->values(),
        ]);
    }

    /** Tagihan dalam lingkup RT kolektor — utk memilih NOP yang akan disetor. */
    public function collectorBills(Request $request)
    {
        $user = $request->user();
        abort_unless($user->isCollector() || $user->isStaff(), 403, 'Hanya RT/kolektor atau petugas desa.');

        $scopeRt = $user->collectorRtScope();
        $year = $request->integer('year') ?: null;

        $bills = PbbBill::with('object')
            ->where('pbb_bills.village_id', $user->village_id)
            ->when($scopeRt !== null, fn ($q) => $q->whereHas('object', fn ($o) => $o->where('rt', $scopeRt)))
            ->when($year, fn ($q) => $q->where('year', $year))
            ->orderBy('status')
            ->latest('year')
            ->limit(100)
            ->get();

        return response()->json([
            'meta' => ['scope_rt' => $scopeRt],
            'data' => $bills->map(fn (PbbBill $b) => $this->presentBill($b->loadMissing('object'), lookup: false))->values(),
        ]);
    }

    /** RT/kolektor mengajukan slip setoran berisi beberapa tagihan (multipart, foto slip opsional). */
    public function storeDeposit(Request $request)
    {
        $this->authorize('create', PbbDepositBatch::class);
        $user = $request->user();

        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'note' => ['nullable', 'string', 'max:1000'],
            'bills' => ['required', 'array', 'min:1', 'max:100'],
            'bills.*.pbb_bill_id' => ['required', 'integer'],
            'bills.*.amount_claimed' => ['nullable', 'numeric', 'min:0'],
            'evidence' => [
                'nullable', 'file',
                'mimetypes:' . implode(',', config('desa.pbb.allowed_mime')),
                'max:' . config('desa.pbb.evidence_max_kb'),
            ],
        ], [
            'title.required' => 'Beri judul slip setoran (mis. Setoran RT 03).',
            'bills.required' => 'Pilih minimal satu tagihan untuk disetor.',
            'evidence.mimetypes' => 'Foto bukti harus berupa gambar (JPG/PNG/WebP).',
        ]);

        $items = collect($data['bills'])
            ->map(fn ($l) => [
                'pbb_bill_id' => (int) $l['pbb_bill_id'],
                'amount_claimed' => $l['amount_claimed'] ?? 0,
            ])
            ->all();

        $batch = $this->deposits->submitBatch($user, $data['title'], $items, $data['note'] ?? null);

        if ($file = $request->file('evidence')) {
            $path = sprintf('pbb/deposits/%d/%s.%s', $batch->id, (string) Str::uuid(), $file->getClientOriginalExtension() ?: 'bin');
            Storage::disk('local')->put($path, file_get_contents($file->getRealPath()));
            $batch->items()->update([
                'disk' => 'local',
                'evidence_path' => $path,
                'evidence_name' => $file->getClientOriginalName(),
            ]);
        }

        return response()->json([
            'message' => 'Setoran tercatat, menunggu ACC bendahara.',
            'data' => $this->presentBatch($batch->load('items.bill.object')),
        ], 201);
    }

    /** Daftar setoran milik kolektor yang login. */
    public function myDeposits(Request $request)
    {
        $batches = $request->user()->pbbDepositBatches()->with('items.bill.object')->latest()->get();

        return response()->json([
            'data' => $batches->map(fn (PbbDepositBatch $b) => $this->presentBatch($b))->values(),
        ]);
    }

    /** Unduh bukti klaim (disk privat; hanya pemilik/peninjau yang diizinkan). */
    public function claimEvidence(PbbClaim $claim)
    {
        $this->authorize('downloadEvidence', $claim);
        abort_unless($claim->evidence_path, 404);

        $full = Storage::disk($claim->disk ?: 'local')->path($claim->evidence_path);
        abort_unless(is_file($full), 404);

        return response()->download($full, $claim->evidence_name ?: basename($claim->evidence_path));
    }

    // ---- helper & presenter ----

    private function normalizeNop(string $nop): string
    {
        return substr(preg_replace('/\D+/', '', $nop) ?? '', 0, (int) config('desa.pbb.nop_max_len'));
    }

    /** Versi cek NOP: nama WP disamarkan, tanpa data sensitif lainnya. */
    private function presentObjectLookup(PbbObject $object): array
    {
        $arrears = $object->bills->filter(fn (PbbBill $b) => $b->isArrears());

        return [
            'nop' => $object->nop,
            'wp_name_masked' => PbbStatus::maskName($object->wp_name),
            'address' => $object->address,
            'rt' => $object->rt,
            'rw' => $object->rw,
            'land_area' => $object->land_area,
            'building_area' => $object->building_area,
            'arrears_count' => $arrears->count(),
            'arrears_total' => (float) $arrears->sum(fn (PbbBill $b) => $b->total()),
            'bills' => $object->bills->map(fn (PbbBill $b) => $this->presentBill($b, lookup: true))->values(),
            'disclaimer' => 'Info sementara. Nama wajib pajak disamarkan. Pelunasan resmi mengikuti kantor pajak.',
        ];
    }

    private function presentBill(PbbBill $bill, bool $lookup): array
    {
        $row = [
            'id' => $bill->id,
            'year' => $bill->year,
            'amount' => (float) $bill->amount,
            'penalty' => (float) $bill->penalty,
            'total' => $bill->total(),
            'status' => $bill->status,
            'status_label' => $bill->statusLabel(),
            'is_arrears' => $bill->isArrears(),
            'due_date' => $bill->due_date?->toDateString(),
        ];

        if (!$lookup) {
            $row['nop'] = $bill->object?->nop;
            $row['wp_name'] = $bill->object?->wp_name;
            $row['rt'] = $bill->object?->rt;
        }

        return $row;
    }

    private function presentClaim(PbbClaim $claim): array
    {
        return [
            'id' => $claim->id,
            'nop' => $claim->object?->nop,
            'wp_name' => $claim->object?->wp_name,
            'address' => $claim->object?->address,
            'status' => $claim->status,
            'status_label' => $claim->statusLabel(),
            'note' => $claim->note,
            'review_note' => $claim->review_note,
            'has_evidence' => (bool) $claim->evidence_path,
            'evidence_url' => $claim->evidence_path ? "/api/pbb/claims/{$claim->id}/evidence" : null,
            'reviewed_at' => $claim->reviewed_at?->toIso8601String(),
            'created_at' => $claim->created_at?->toIso8601String(),
        ];
    }

    private function presentBatch(PbbDepositBatch $batch): array
    {
        return [
            'id' => $batch->id,
            'title' => $batch->title,
            'total_amount' => (float) $batch->total_amount,
            'item_count' => $batch->item_count,
            'status' => $batch->status,
            'status_label' => $batch->statusLabel(),
            'note' => $batch->note,
            'reviewed_at' => $batch->reviewed_at?->toIso8601String(),
            'created_at' => $batch->created_at?->toIso8601String(),
            'items' => $batch->items->map(fn ($item) => [
                'id' => $item->id,
                'nop' => $item->bill?->object?->nop,
                'year' => $item->bill?->year,
                'amount_claimed' => (float) $item->amount_claimed,
                'bill_total' => $item->bill?->total(),
                'status' => $item->status,
                'status_label' => $item->statusLabel(),
                'review_note' => $item->review_note,
            ])->values(),
        ];
    }
}
