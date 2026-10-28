<?php
namespace App\Services;

use App\Models\ProductReport;
use App\Models\Shop;
use App\Models\ShopStats;
use App\Models\User;
use App\Support\UmkmStatus;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya tempat perubahan status toko & laporan moderasi UMKM terjadi
 * (PEMBANGUNAN.md 4.4 & 5). Memastikan transisi sah, mencatat audit, menghitung statistik
 * penjual (dilihat & klik WA), dan membangun tautan chat WhatsApp. TIDAK ada pembayaran:
 * WA hanyalah jembatan ke penjual untuk transaksi offline.
 */
class UmkmService
{
    /** Operator/kades/admin meninjau & memoderasi toko di satu desa yang sama. */
    private const REVIEWERS = ['operator', 'kades', 'admin'];

    /** Setujui toko (pending -> aktif) atau aktifkan kembali (nonaktif -> aktif). */
    public function approve(Shop $shop, User $reviewer, ?string $note = null): Shop
    {
        $this->assertReviewer($shop, $reviewer);

        return $this->transition($shop, UmkmStatus::SHOP_ACTIVE, $reviewer, $note, 'umkm.shop.approved');
    }

    /** Tolak toko (pending -> ditolak). Alasan wajib, tampil ke pemilik. */
    public function reject(Shop $shop, User $reviewer, string $note): Shop
    {
        $this->assertReviewer($shop, $reviewer);

        if (blank($note)) {
            throw new \InvalidArgumentException('Penolakan toko wajib menyertakan alasan.');
        }

        return $this->transition($shop, UmkmStatus::SHOP_REJECTED, $reviewer, $note, 'umkm.shop.rejected');
    }

    /** Bekukan sementara (aktif -> nonaktif), mis. pasca laporan melanggar. */
    public function suspend(Shop $shop, User $reviewer, ?string $note = null): Shop
    {
        $this->assertReviewer($shop, $reviewer);

        return $this->transition($shop, UmkmStatus::SHOP_SUSPENDED, $reviewer, $note, 'umkm.shop.suspended');
    }

    /** Pemilik memperbaiki & mengajukan ulang toko yang ditolak (ditolak -> pending). */
    public function resubmit(Shop $shop): Shop
    {
        if (!UmkmStatus::canShopTransition($shop->status, UmkmStatus::SHOP_PENDING)) {
            throw new \InvalidArgumentException('Toko tidak bisa diajukan ulang pada status ini.');
        }

        $shop->forceFill(['status' => UmkmStatus::SHOP_PENDING, 'review_note' => null])->save();

        AuditLogger::record('umkm.shop.resubmitted', $shop, 'Pemilik mengajukan ulang toko yang ditolak.');

        return $shop->refresh();
    }

    /**
     * Tangani laporan moderasi. $outcome = 'ditindak' | 'diabaikan'. Bila ditindak dan
     * $hideShop, toko terkait dibekukan. Status laporan wajib `baru`.
     */
    public function handleReport(ProductReport $report, User $actor, string $outcome, ?string $note = null, bool $hideShop = false): ProductReport
    {
        $this->assertReviewer($report->shop ?? $report->product?->shop, $actor);

        if ($report->status !== UmkmStatus::REPORT_NEW) {
            throw new \InvalidArgumentException('Laporan sudah ditangani sebelumnya.');
        }

        if (!in_array($outcome, [UmkmStatus::REPORT_RESOLVED, UmkmStatus::REPORT_DISMISSED], true)) {
            throw new \InvalidArgumentException('Hasil penanganan tidak dikenal.');
        }

        return DB::transaction(function () use ($report, $actor, $outcome, $note, $hideShop): ProductReport {
            if ($outcome === UmkmStatus::REPORT_RESOLVED && $hideShop) {
                $shop = $report->shop ?? $report->product?->shop;
                if ($shop && $shop->status === UmkmStatus::SHOP_ACTIVE) {
                    $this->transition($shop, UmkmStatus::SHOP_SUSPENDED, $actor, $note, 'umkm.shop.suspended');
                }
            }

            $report->forceFill([
                'status' => $outcome,
                'handled_by' => $actor->id,
                'handle_note' => $note,
                'handled_at' => now(),
            ])->save();

            AuditLogger::record('umkm.report.handled', $report, 'Laporan moderasi ditangani: ' . UmkmStatus::label($outcome), [
                'outcome' => $outcome,
                'hide_shop' => $hideShop,
            ]);

            return $report->refresh();
        });
    }

    /** Catat satu "dilihat" utk statistik penjual. */
    public function recordView(Shop $shop): void
    {
        $this->statsFor($shop)->increment('views_count');
    }

    /** Catat satu klik "Chat WhatsApp". */
    public function recordWaClick(Shop $shop): void
    {
        $this->statsFor($shop)->increment('wa_clicks_count');
    }

    /**
     * Bangun tautan wa.me dengan pesan otomatis terisi (PEMBANGUNAN.md 4.4). Nomor dinormalisasi
     * ke format internasional (62). Produk = pesan menyebut produk; selain itu menyebut toko.
     */
    public function waLink(Shop $shop, ?string $productName = null, ?string $villageName = null): string
    {
        $digits = $this->normalizeWaNumber($shop->wa_number);
        $subject = $productName ?: $shop->name;
        $prefix = $villageName ? "Aplikasi Desa {$villageName}" : 'Aplikasi Desa';
        $message = "Halo, saya lihat *{$subject}* di {$prefix}. Apakah masih tersedia?";

        return 'https://wa.me/' . $digits . '?text=' . rawurlencode($message);
    }

    private function normalizeWaNumber(?string $number): string
    {
        $digits = preg_replace('/\D+/', '', (string) $number) ?? '';

        return match (true) {
            str_starts_with($digits, '62') => $digits,
            str_starts_with($digits, '0') => '62' . substr($digits, 1),
            str_starts_with($digits, '8') => '62' . $digits,
            default => $digits,
        };
    }

    private function statsFor(Shop $shop): ShopStats
    {
        return ShopStats::firstOrCreate(['shop_id' => $shop->id]);
    }

    private function transition(Shop $shop, string $to, User $actor, ?string $note, string $auditAction): Shop
    {
        $from = $shop->status;

        if (!UmkmStatus::canShopTransition($from, $to)) {
            throw new \InvalidArgumentException("Transisi status toko tidak sah: {$from} -> {$to}.");
        }

        $shop->forceFill([
            'status' => $to,
            'reviewed_by' => $actor->id,
            'review_note' => $note,
            'reviewed_at' => now(),
        ])->save();

        AuditLogger::record($auditAction, $shop, "Status toko diubah: " . UmkmStatus::label($from) . ' -> ' . UmkmStatus::label($to), [
            'from' => $from,
            'to' => $to,
        ]);

        return $shop->refresh();
    }

    private function assertReviewer(?Shop $shop, User $actor): void
    {
        // Untuk laporan tanpa toko eksplisit (sudah terhapus), biarkan tetap diverifikasi via policy.
        if ($shop && !($actor->hasAnyRole(self::REVIEWERS) && $actor->village_id === $shop->village_id)) {
            throw new AuthorizationException('Hanya petugas desa terkait yang dapat meninjau UMKM.');
        }

        if (!$shop && !$actor->hasAnyRole(self::REVIEWERS)) {
            throw new AuthorizationException('Hanya petugas desa yang dapat menangani laporan.');
        }
    }
}
