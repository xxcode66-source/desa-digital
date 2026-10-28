<?php
// Fase 5 — API Marketplace UMKM (PEMBANGUNAN.md 4.4). Warga menjelajahi toko & produk
// desanya (cari/saring/terdekat), melihat detail + tombol "Chat WhatsApp", melaporkan produk/
// toko untuk moderasi, dan pelaku UMKM mengelola toko & katalog produknya. TANPA keranjang/
// pembayaran: WA hanya jembatan ke penjual, transaksi disepakati offline.
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductPhoto;
use App\Models\ProductReport;
use App\Models\Shop;
use App\Services\AuditLogger;
use App\Services\UmkmService;
use App\Support\UmkmStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UmkmController extends Controller
{
    public function __construct(private readonly UmkmService $umkm)
    {
    }

    /** Kategori usaha baku (config desa) untuk mengisi dropdown formulir & filter. */
    public function categories()
    {
        $options = collect(config('desa.umkm.categories'))
            ->map(fn (string $label, string $code) => ['code' => $code, 'label' => $label])
            ->values();

        return response()->json(['data' => $options]);
    }

    /** Marketplace: daftar toko aktif & tidak disembunyikan di desa warga (cari/saring/sort). */
    public function browse(Request $request)
    {
        $user = $request->user();
        $this->authorize('viewAny', Shop::class);

        $query = Shop::query()
            ->withCount('products')
            ->where('village_id', $user->village_id)
            ->where('status', UmkmStatus::SHOP_ACTIVE)
            ->where('is_hidden', false);

        if ($q = trim((string) $request->query('q'))) {
            $query->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('description', 'like', "%{$q}%"));
        }

        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }

        $sort = $request->query('sort', 'baru');
        if ($sort === 'populer') {
            $query->leftJoin('shop_stats', 'shop_stats.shop_id', '=', 'shops.id')
                ->orderByDesc('shop_stats.views_count')
                ->orderByDesc('shops.id');
        } elseif ($sort === 'terdekat' && $user->rt !== null && $user->rt !== '') {
            $query->orderByRaw('ABS(CAST(shops.rt AS INTEGER) - ?) IS NULL', [$user->rt])
                ->orderByRaw('ABS(CAST(shops.rt AS INTEGER) - ?)', [$user->rt])
                ->orderByDesc('shops.id');
        } else {
            $query->latest('shops.created_at')->orderByDesc('shops.id');
        }

        $shops = $query->select('shops.*')->paginate(15);

        return response()->json([
            'data' => $shops->through(fn (Shop $s) => $this->presentShopCard($s))->items(),
            'meta' => ['total' => $shops->total(), 'page' => $shops->currentPage()],
        ]);
    }

    /** Detail satu toko + produk & tautan WA. Mencatat satu "dilihat" untuk statistik penjual. */
    public function shopDetail(Request $request, Shop $shop)
    {
        $this->authorize('view', $shop);

        if ($shop->isPubliclyVisible()) {
            $this->umkm->recordView($shop);
        }

        $shop->load(['products' => fn ($q) => $q->orderByDesc('is_available')->latest(), 'village']);

        return response()->json([
            'data' => $this->presentShopDetail($shop),
        ]);
    }

    /** Klik "Chat WhatsApp" pada toko: catat statistik & kembalikan tautan wa.me. */
    public function shopWa(Request $request, Shop $shop)
    {
        $this->authorize('view', $shop);

        $product = $request->integer('product')
            ? $shop->products()->whereKey($request->integer('product'))->first()
            : null;

        $this->umkm->recordWaClick($shop);

        return response()->json([
            'data' => ['wa_url' => $this->umkm->waLink($shop, $product?->name, $shop->village?->name)],
        ]);
    }

    /** Unduh foto toko (disk privat; hanya yang boleh melihat toko). */
    public function shopPhoto(Shop $shop)
    {
        $this->authorize('view', $shop);
        abort_unless($shop->photo_path, 404);

        $full = Storage::disk($shop->disk ?: 'local')->path($shop->photo_path);
        abort_unless(is_file($full), 404);

        return response()->download($full, $shop->photo_name ?: basename($shop->photo_path));
    }

    /** Detail satu produk + foto + tautan WA. Mencatat "dilihat" pada tokonya. */
    public function productDetail(Product $product)
    {
        $this->authorize('view', $product);

        if ($product->shop?->isPubliclyVisible()) {
            $this->umkm->recordView($product->shop);
        }

        $product->load(['photos', 'shop.village']);

        return response()->json([
            'data' => $this->presentProduct($product, detail: true),
        ]);
    }

    /** Unduh foto produk (disk privat; hanya yang boleh melihat produk). */
    public function productPhoto(Product $product, ProductPhoto $photo)
    {
        $this->authorize('view', $product);
        abort_unless($photo->product_id === $product->id, 404);

        $full = Storage::disk($photo->disk ?: 'local')->path($photo->path);
        abort_unless(is_file($full), 404);

        return response()->download($full, $photo->original_name ?: basename($photo->path));
    }

    /** Lapor satu toko (moderasi operator). Hanya akun terverifikasi. */
    public function reportShop(Request $request, Shop $shop)
    {
        return $this->storeReport($request, villageId: $shop->village_id, product: null, shop: $shop);
    }

    /** Lapor satu produk (moderasi operator). Hanya akun terverifikasi. */
    public function reportProduct(Request $request, Product $product)
    {
        return $this->storeReport($request, villageId: $product->village_id, product: $product, shop: $product->shop);
    }

    /** Toko milik pelaku yang login (status apa pun) + produk + statistik penjual. */
    public function myShop(Request $request)
    {
        $shop = $request->user()->shop()->with(['products.photos', 'stats'])->first();

        if (!$shop) {
            return response()->json(['data' => null]);
        }

        return response()->json([
            'data' => $this->presentMyShop($shop),
        ]);
    }

    /** Daftar + label kategori utk pengisian formulir penjual. */
    public function categoryOptions()
    {
        return $this->categories();
    }

    /** Buat atau perbarui toko milik sendiri (multipart + 1 foto). Ditolak -> diajukan ulang. */
    public function saveShop(Request $request)
    {
        $user = $request->user();
        $shop = $user->shop()->first();

        if ($shop) {
            $this->authorize('update', $shop);

            return $this->updateShop($request, $shop);
        }

        $this->authorize('create', Shop::class);

        return $this->createShop($request, $user);
    }

    private function createShop(Request $request, $user): \Illuminate\Http\JsonResponse
    {
        $data = $this->validateShop($request);

        $shop = new Shop($data);
        $shop->village_id = $user->village_id;
        $shop->user_id = $user->id;

        if ($file = $request->file('photo')) {
            $this->storeShopPhoto($shop, $file);
        }

        $shop->save();

        AuditLogger::record('umkm.shop.created', $shop, 'Pelaku UMKM mendaftarkan toko.', ['category' => $shop->category]);

        return response()->json([
            'message' => 'Toko terdaftar. Menunggu verifikasi petugas desa.',
            'data' => $this->presentMyShop($shop->fresh(['products', 'stats'])),
        ], 201);
    }

    private function updateShop(Request $request, Shop $shop): \Illuminate\Http\JsonResponse
    {
        $data = $this->validateShop($request);

        $shop->fill($data);

        if ($file = $request->file('photo')) {
            $this->storeShopPhoto($shop, $file);
        }

        $shop->save();

        // Toko yang ditolak diperbaiki & diajukan ulang otomatis ke status pending.
        if ($shop->status === UmkmStatus::SHOP_REJECTED) {
            $this->umkm->resubmit($shop->refresh());
            $message = 'Perubahan tersimpan. Toko diajukan ulang untuk verifikasi.';
        } else {
            $message = 'Perubahan toko tersimpan.';
        }

        AuditLogger::record('umkm.shop.updated', $shop, 'Pemilik memperbarui tokonya.');

        return response()->json([
            'message' => $message,
            'data' => $this->presentMyShop($shop->fresh(['products', 'stats'])),
        ]);
    }

    /** Tambah produk ke toko sendiri (multipart, maks 5 foto). */
    public function storeProduct(Request $request)
    {
        $user = $request->user();
        $shop = $user->shop()->first();

        abort_unless($shop, 422, 'Daftarkan toko Anda terlebih dahulu.');
        $this->authorize('create', Product::class);

        $data = $this->validateProduct($request);

        $product = new Product($data);
        $product->village_id = $shop->village_id;
        $product->shop_id = $shop->id;
        $product->save();

        $this->storeProductPhotos($product, $request->file('photos', []));

        AuditLogger::record('umkm.product.created', $product, 'Penambahan produk ke katalog toko.', ['shop_id' => $shop->id]);

        return response()->json([
            'message' => 'Produk ditambahkan.',
            'data' => $this->presentProduct($product->fresh(['photos', 'shop'])),
        ], 201);
    }

    /** Ubah produk (replace-all foto bila dikirim). Hanya pemilik toko. */
    public function updateProduct(Request $request, Product $product)
    {
        $this->authorize('update', $product);

        $data = $this->validateProduct($request, updating: true);
        $product->fill($data)->save();

        if ($request->hasFile('photos')) {
            $this->clearProductPhotos($product);
            $this->storeProductPhotos($product, $request->file('photos', []));
        }

        AuditLogger::record('umkm.product.updated', $product, 'Pemilik memperbarui produknya.');

        return response()->json([
            'message' => 'Produk diperbarui.',
            'data' => $this->presentProduct($product->fresh(['photos', 'shop'])),
        ]);
    }

    /** Hapus produk beserta fotonya. Hanya pemilik toko. */
    public function destroyProduct(Request $request, Product $product)
    {
        $this->authorize('delete', $product);

        $this->clearProductPhotos($product);
        $product->delete();

        AuditLogger::record('umkm.product.deleted', $product, 'Pemilik menghapus produknya.');

        return response()->json(['message' => 'Produk dihapus.']);
    }

    // ---- validasi & penyimpanan ----

    private function validateShop(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category' => ['required', 'string', Rule::in(array_keys(config('desa.umkm.categories')))],
            'address' => ['nullable', 'string', 'max:255'],
            'rt' => ['nullable', 'string', 'max:5'],
            'rw' => ['nullable', 'string', 'max:5'],
            'opening_hours' => ['nullable', 'string', 'max:120'],
            'wa_number' => ['required', 'string', 'max:20'],
            'photo' => [
                'nullable', 'image',
                'mimetypes:' . implode(',', config('desa.umkm.allowed_mime')),
                'max:' . config('desa.umkm.max_photo_kb'),
            ],
        ], [
            'name.required' => 'Nama toko tidak boleh kosong.',
            'category.required' => 'Pilih kategori usaha.',
            'category.in' => 'Kategori usaha tidak dikenali.',
            'wa_number.required' => 'Isi nomor WhatsApp untuk dihubungi pembeli.',
            'photo.mimetypes' => 'Foto toko harus berupa gambar (JPG/PNG/WebP).',
            'photo.max' => 'Ukuran foto toko terlalu besar (maks ~3 MB).',
        ]);
    }

    private function validateProduct(Request $request, bool $updating = false): array
    {
        $max = (int) config('desa.umkm.max_product_photos');

        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0'],
            'is_available' => ['boolean'],
            'photos' => [$updating ? 'sometimes' : 'nullable', 'array', 'max:' . $max],
            'photos.*' => [
                'file',
                'mimetypes:' . implode(',', config('desa.umkm.allowed_mime')),
                'max:' . config('desa.umkm.max_photo_kb'),
            ],
        ], [
            'name.required' => 'Nama produk tidak boleh kosong.',
            'price.required' => 'Isi harga produk.',
            'photos.max' => "Foto produk maksimal {$max}.",
            'photos.*.mimetypes' => 'Foto produk harus berupa gambar (JPG/PNG/WebP).',
            'photos.*.max' => 'Ukuran foto produk terlalu besar (maks ~3 MB).',
        ]);
    }

    private function storeReport(Request $request, int $villageId, ?Product $product, ?Shop $shop): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        $this->authorize('create', ProductReport::class);
        abort_unless($user->village_id === $villageId, 403, 'Lapor hanya untuk toko/produk di desa Anda.');

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ], [
            'reason.required' => 'Jelaskan alasan laporan Anda.',
        ]);

        $report = ProductReport::create([
            'village_id' => $villageId,
            'product_id' => $product?->id,
            'shop_id' => $shop?->id,
            'reporter_id' => $user->id,
            'reason' => $data['reason'],
            'status' => UmkmStatus::REPORT_NEW,
        ]);

        AuditLogger::record('umkm.report.submitted', $report, 'Warga melaporkan produk/toko UMKM untuk moderasi.');

        return response()->json(['message' => 'Laporan terkirim. Terima kasih telah menjaga marketplace desa.',], 201);
    }

    private function storeShopPhoto(Shop $shop, $file): void
    {
        $path = sprintf('umkm/shops/%s/%s.%s', $shop->id ?: 'new', (string) Str::uuid(), $file->getClientOriginalExtension() ?: 'jpg');
        Storage::disk('local')->put($path, file_get_contents($file->getRealPath()));
        $shop->forceFill(['disk' => 'local', 'photo_path' => $path, 'photo_name' => $file->getClientOriginalName()]);
    }

    private function storeProductPhotos(Product $product, array $files): void
    {
        foreach ($files as $file) {
            if (!$file) {
                continue;
            }
            $path = sprintf('umkm/products/%d/%s.%s', $product->id, (string) Str::uuid(), $file->getClientOriginalExtension() ?: 'jpg');
            Storage::disk('local')->put($path, file_get_contents($file->getRealPath()));

            ProductPhoto::create([
                'product_id' => $product->id,
                'disk' => 'local',
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime' => $file->getClientMimeType(),
                'size' => $file->getSize(),
            ]);
        }
    }

    private function clearProductPhotos(Product $product): void
    {
        foreach ($product->photos as $photo) {
            Storage::disk($photo->disk ?: 'local')->delete($photo->path);
            $photo->delete();
        }
    }

    // ---- presenter ----

    private function presentShopCard(Shop $shop): array
    {
        return [
            'id' => $shop->id,
            'name' => $shop->name,
            'category' => $shop->category,
            'category_label' => $shop->categoryLabel(),
            'description' => $shop->description,
            'rt' => $shop->rt,
            'rw' => $shop->rw,
            'opening_hours' => $shop->opening_hours,
            'products_count' => (int) ($shop->products_count ?? 0),
            'photo_url' => $shop->photo_path ? "/umkm/shops/{$shop->id}/photo" : null,
        ];
    }

    private function presentShopDetail(Shop $shop): array
    {
        return array_merge($this->presentShopCard($shop), [
            'products_count' => $shop->products->count(),
            'address' => $shop->address,
            'status' => $shop->status,
            'status_label' => $shop->statusLabel(),
            'wa_url' => $this->umkm->waLink($shop, null, $shop->village?->name),
            'products' => $shop->products->map(fn (Product $p) => $this->presentProduct($p->setRelation('shop', $shop)))->values(),
        ]);
    }

    private function presentMyShop(Shop $shop): array
    {
        return array_merge($this->presentShopDetail($shop), [
            'is_hidden' => (bool) $shop->is_hidden,
            'review_note' => $shop->review_note,
            'stats' => [
                'views_count' => (int) ($shop->stats?->views_count ?? 0),
                'wa_clicks_count' => (int) ($shop->stats?->wa_clicks_count ?? 0),
            ],
            'products' => $shop->products->map(fn (Product $p) => $this->presentProduct($p->setRelation('shop', $shop), detail: true))->values(),
        ]);
    }

    private function presentProduct(Product $product, bool $detail = false): array
    {
        $row = [
            'id' => $product->id,
            'name' => $product->name,
            'description' => $product->description,
            'price' => (float) $product->price,
            'price_label' => 'Rp ' . number_format((float) $product->price, 0, ',', '.'),
            'is_available' => (bool) $product->is_available,
            'shop_id' => $product->shop_id,
            'shop_name' => $product->shop?->name,
            'photo_url' => $product->photos?->first()?->id ? "/umkm/products/{$product->id}/photos/{$product->photos->first()->id}" : null,
        ];

        if ($detail) {
            $row['photos'] = $product->photos?->map(fn (ProductPhoto $p) => [
                'id' => $p->id,
                'name' => $p->original_name,
                'download_url' => "/umkm/products/{$product->id}/photos/{$p->id}",
            ])->values();

            if ($product->shop) {
                $row['wa_url'] = $this->umkm->waLink($product->shop, $product->name, $product->shop->village?->name);
            }
        }

        return $row;
    }
}
