package com.desa.digital.data.repository

import android.content.Context
import android.net.Uri
import com.desa.digital.core.network.ApiService
import com.desa.digital.core.network.dto.UmkmCategoryDto
import com.desa.digital.core.network.dto.UmkmProductDto
import com.desa.digital.core.network.dto.UmkmReportRequest
import com.desa.digital.core.network.dto.UmkmShopDto
import dagger.hilt.android.qualifiers.ApplicationContext
import java.util.UUID
import javax.inject.Inject
import javax.inject.Singleton
import okhttp3.MediaType.Companion.toMediaTypeOrNull
import okhttp3.MultipartBody
import okhttp3.RequestBody
import okhttp3.RequestBody.Companion.toRequestBody
import retrofit2.HttpException

/**
 * Repository Marketplace UMKM Fase 5 (PEMBANGUNAN.md 4.4). Menangani jelajah toko/produk,
 * tautan WhatsApp, laporan moderasi, serta pengelolaan toko & katalog produk pelaku UMKM
 * (multipart foto). Seperti modul lain, error teknis diterjemahkan ke bahasa Indonesia
 * sederhana. TIDAK ada pembayaran di aplikasi — WA hanya jembatan menghubungi penjual.
 */
@Singleton
class UmkmRepository @Inject constructor(
    private val api: ApiService,
    @ApplicationContext private val context: Context,
) {
    suspend fun categories(): Result<List<UmkmCategoryDto>> = runCatching {
        api.umkmCategories().data
    }.recoverCatching { throw friendly(it) }

    suspend fun browse(q: String?, category: String?, sort: String?, page: Int? = null): Result<List<UmkmShopDto>> = runCatching {
        api.umkmBrowse(q?.trim()?.ifBlank { null }, category?.ifBlank { null }, sort, page).data
    }.recoverCatching { throw friendly(it) }

    suspend fun shopDetail(shopId: Long): Result<UmkmShopDto> = runCatching {
        api.umkmShopDetail(shopId).data ?: error("Toko tidak ditemukan.")
    }.recoverCatching { throw friendly(it) }

    /** Bangun tautan wa.me di server (menyekaligus mencatat klik). Return URL untuk dibuka. */
    suspend fun shopWa(shopId: Long, productId: Long? = null): Result<String> = runCatching {
        api.umkmShopWa(shopId, productId).data.waUrl
    }.recoverCatching { throw friendly(it) }

    suspend fun productDetail(productId: Long): Result<UmkmProductDto> = runCatching {
        api.umkmProductDetail(productId).data ?: error("Produk tidak ditemukan.")
    }.recoverCatching { throw friendly(it) }

    suspend fun reportShop(shopId: Long, reason: String): Result<Unit> = runCatching {
        api.umkmReportShop(shopId, UmkmReportRequest(reason.trim()))
        Unit
    }.recoverCatching { throw friendly(it) }

    suspend fun reportProduct(productId: Long, reason: String): Result<Unit> = runCatching {
        api.umkmReportProduct(productId, UmkmReportRequest(reason.trim()))
        Unit
    }.recoverCatching { throw friendly(it) }

    /** Toko milik pelaku yang login; null bila belum mendaftar. */
    suspend fun myShop(): Result<UmkmShopDto?> = runCatching {
        api.umkmMyShop().data
    }.recoverCatching { throw friendly(it) }

    /** Buat/perbarui toko sendiri (server memutuskan create vs update). */
    suspend fun saveShop(
        name: String,
        description: String?,
        category: String,
        address: String?,
        rt: String?,
        rw: String?,
        openingHours: String?,
        waNumber: String,
        photoUri: Uri?,
    ): Result<UmkmShopDto> = runCatching {
        val text = "text/plain".toMediaTypeOrNull()
        val parts = mutableMapOf<String, RequestBody>(
            "name" to name.trim().toRequestBody(text),
            "category" to category.toRequestBody(text),
            "wa_number" to waNumber.trim().toRequestBody(text),
        )
        description?.takeIf { it.isNotBlank() }?.let { parts["description"] = it.trim().toRequestBody(text) }
        address?.takeIf { it.isNotBlank() }?.let { parts["address"] = it.trim().toRequestBody(text) }
        rt?.takeIf { it.isNotBlank() }?.let { parts["rt"] = it.trim().toRequestBody(text) }
        rw?.takeIf { it.isNotBlank() }?.let { parts["rw"] = it.trim().toRequestBody(text) }
        openingHours?.takeIf { it.isNotBlank() }?.let { parts["opening_hours"] = it.trim().toRequestBody(text) }
        val photo = photoUri?.let { singleFilePart("photo", it) }
        api.umkmSaveShop(parts, photo).data ?: error("Gagal menyimpan toko.")
    }.recoverCatching { throw friendly(it) }

    suspend fun addProduct(
        name: String,
        description: String?,
        price: Double,
        isAvailable: Boolean,
        photoUris: List<Uri>,
    ): Result<UmkmProductDto> = runCatching {
        api.umkmStoreProduct(productParts(name, description, price, isAvailable), fileParts(photoUris)).data
            ?: error("Gagal menambahkan produk.")
    }.recoverCatching { throw friendly(it) }

    /** [replacePhotos] = true => kirim foto baru (ganti semua); false => pertahankan foto yang ada. */
    suspend fun updateProduct(
        productId: Long,
        name: String,
        description: String?,
        price: Double,
        isAvailable: Boolean,
        photoUris: List<Uri>,
        replacePhotos: Boolean,
    ): Result<UmkmProductDto> = runCatching {
        val files = if (replacePhotos) fileParts(photoUris) else emptyList()
        api.umkmUpdateProduct(productId, productParts(name, description, price, isAvailable), files).data
            ?: error("Gagal memperbarui produk.")
    }.recoverCatching { throw friendly(it) }

    suspend fun deleteProduct(productId: Long): Result<Unit> = runCatching {
        api.umkmDeleteProduct(productId)
        Unit
    }.recoverCatching { throw friendly(it) }

    private fun productParts(name: String, description: String?, price: Double, isAvailable: Boolean): Map<String, RequestBody> {
        val text = "text/plain".toMediaTypeOrNull()
        val map = mutableMapOf<String, RequestBody>(
            "name" to name.trim().toRequestBody(text),
            "price" to price.toString().toRequestBody(text),
            "is_available" to (if (isAvailable) "1" else "0").toRequestBody(text),
        )
        description?.takeIf { it.isNotBlank() }?.let { map["description"] = it.trim().toRequestBody(text) }
        return map
    }

    private fun fileParts(uris: List<Uri>): List<MultipartBody.Part> = uris.map { uri ->
        val mime = context.contentResolver.getType(uri) ?: "application/octet-stream"
        val ext = mime.substringAfter('/').substringBefore(';').ifBlank { "jpg" }
        val bytes = context.contentResolver.openInputStream(uri)?.use { it.readBytes() }
            ?: throw Exception("Foto tidak bisa dibaca. Pilih ulang fotonya.")
        MultipartBody.Part.createFormData(
            "photos[]",
            "${UUID.randomUUID()}.$ext",
            bytes.toRequestBody(mime.toMediaTypeOrNull()),
        )
    }

    private fun singleFilePart(field: String, uri: Uri): MultipartBody.Part {
        val mime = context.contentResolver.getType(uri) ?: "application/octet-stream"
        val ext = mime.substringAfter('/').substringBefore(';').ifBlank { "jpg" }
        val bytes = context.contentResolver.openInputStream(uri)?.use { it.readBytes() }
            ?: throw Exception("Foto tidak bisa dibaca. Pilih ulang fotonya.")
        return MultipartBody.Part.createFormData(
            field,
            "${UUID.randomUUID()}.$ext",
            bytes.toRequestBody(mime.toMediaTypeOrNull()),
        )
    }

    /** Pesan error manusiawi (§6); pesan 422 backend sudah berbahasa Indonesia. */
    private fun friendly(t: Throwable): Throwable = when {
        t is HttpException && t.code() == 404 -> Exception("Data tidak ditemukan. Mungkin sudah dihapus atau tidak lagi tampil.")
        t is HttpException && t.code() == 422 -> Exception(extractMessage(t) ?: "Periksa kembali isian Anda.")
        t is HttpException && t.code() == 403 -> Exception("Belum boleh mengakses fitur ini. Pastikan akun Anda terverifikasi / punya peran yang sesuai.")
        t is HttpException && t.code() == 429 -> Exception("Terlalu sering mencoba. Tunggu sebentar lalu coba lagi.")
        t is HttpException -> Exception("Permintaan gagal (${t.code()}). Coba lagi atau hubungi kantor desa.")
        else -> t
    }

    private fun extractMessage(e: HttpException): String? = try {
        e.response()?.errorBody()?.string()?.let { body ->
            Regex("\"message\"\\s*:\\s*\"([^\"]+)\"").find(body)?.groupValues?.get(1)
        }
    } catch (_: Exception) {
        null
    }
}
