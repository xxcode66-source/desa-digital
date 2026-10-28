package com.desa.digital.data.repository

import android.content.Context
import android.net.Uri
import com.desa.digital.core.network.ApiService
import com.desa.digital.core.network.dto.PbbBillDto
import com.desa.digital.core.network.dto.PbbClaimDto
import com.desa.digital.core.network.dto.PbbDepositBatchDto
import com.desa.digital.core.network.dto.PbbMyObjectDto
import com.desa.digital.core.network.dto.PbbNopLookupDto
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
 * Repository PBB Fase 4 (PEMBANGUNAN.md 4.3). Menangani cek NOP, klaim kepemilikan (dengan
 * foto bukti via multipart), daftar "NOP saya"/klaim, serta alur kolektor melihat tagihan
 * & mengajukan slip setoran. Seperti modul surat/pengaduan, error teknis diterjemahkan ke
 * bahasa Indonesia sederhana. TIDAK ada transaksi uang di aplikasi.
 */
@Singleton
class PbbRepository @Inject constructor(
    private val api: ApiService,
    @ApplicationContext private val context: Context,
) {
    suspend fun cekNop(nop: String, villageId: Long): Result<PbbNopLookupDto> = runCatching {
        api.cekNop(nop.trim(), villageId).data
    }.recoverCatching { throw friendly(it) }

    suspend fun myObjects(): Result<List<PbbMyObjectDto>> = runCatching {
        api.myPbbObjects().data
    }.recoverCatching { throw friendly(it) }

    suspend fun myClaims(): Result<List<PbbClaimDto>> = runCatching {
        api.myPbbClaims().data
    }.recoverCatching { throw friendly(it) }

    suspend fun submitClaim(nop: String, note: String?, evidenceUri: Uri): Result<PbbClaimDto> = runCatching {
        val text = "text/plain".toMediaTypeOrNull()
        val parts = mutableMapOf<String, RequestBody>("nop" to nop.trim().toRequestBody(text))
        note?.takeIf { it.isNotBlank() }?.let { parts["note"] = it.trim().toRequestBody(text) }
        api.submitClaim(parts, filePart("evidence", evidenceUri)).data
    }.recoverCatching { throw friendly(it) }

    suspend fun collectorBills(year: Int? = null): Result<List<PbbBillDto>> = runCatching {
        api.collectorBills(year).data
    }.recoverCatching { throw friendly(it) }

    /**
     * Kolektor mengajukan setoran. [items] = pasangan (id tagihan, nominal disetor). Nominal
     * nol berarti "sesuai total tagihan" (backend mengisi default).
     */
    suspend fun submitDeposit(
        title: String,
        note: String?,
        items: List<Pair<Long, Double>>,
        evidenceUri: Uri?,
    ): Result<PbbDepositBatchDto> = runCatching {
        val text = "text/plain".toMediaTypeOrNull()
        val parts = mutableMapOf<String, RequestBody>("title" to title.trim().toRequestBody(text))
        note?.takeIf { it.isNotBlank() }?.let { parts["note"] = it.trim().toRequestBody(text) }
        items.forEachIndexed { i, (billId, amount) ->
            parts["bills[$i][pbb_bill_id]"] = billId.toString().toRequestBody(text)
            if (amount > 0.0) parts["bills[$i][amount_claimed]"] = amount.toString().toRequestBody(text)
        }
        val file = evidenceUri?.let { filePart("evidence", it) }
        api.submitDeposit(parts, file).data
    }.recoverCatching { throw friendly(it) }

    suspend fun myDeposits(): Result<List<PbbDepositBatchDto>> = runCatching {
        api.myPbbDeposits().data
    }.recoverCatching { throw friendly(it) }

    /** Bangun satu part file dari Uri galeri (nama file acak, mime mengikuti kontennya). */
    private fun filePart(field: String, uri: Uri): MultipartBody.Part {
        val mime = context.contentResolver.getType(uri) ?: "application/octet-stream"
        val ext = mime.substringAfter('/').substringBefore(';').ifBlank { "jpg" }
        val bytes = context.contentResolver.openInputStream(uri)?.use { it.readBytes() }
            ?: throw Exception("Foto bukti tidak bisa dibaca. Pilih ulang fotonya.")
        return MultipartBody.Part.createFormData(
            field,
            "${UUID.randomUUID()}.$ext",
            bytes.toRequestBody(mime.toMediaTypeOrNull()),
        )
    }

    /** Pesan error manusiawi (§6); pesan 422 backend sudah berbahasa Indonesia. */
    private fun friendly(t: Throwable): Throwable = when {
        t is HttpException && t.code() == 404 -> Exception("NOP tidak ditemukan. Pastikan 18 digit NOP benar.")
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
