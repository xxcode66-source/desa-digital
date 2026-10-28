package com.desa.digital.data.repository

import android.content.Context
import android.net.Uri
import com.desa.digital.core.network.ApiService
import com.desa.digital.core.network.dto.LetterDetailDto
import com.desa.digital.core.network.dto.LetterSummaryDto
import com.desa.digital.core.network.dto.LetterTypeDto
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
 * Repository surat Fase 2 (PEMBANGUNAN.md 4.2). Membangun multipart dari Uri galeri
 * (hemat kuota: foto sudah dikompres di pemilih gambar) dan menerjemahkan error
 * teknis menjadi bahasa Indonesia sederhana.
 */
@Singleton
class LetterRepository @Inject constructor(
    private val api: ApiService,
    @ApplicationContext private val context: Context,
) {
    suspend fun letterTypes(): Result<List<LetterTypeDto>> = runCatching {
        api.letterTypes().data
    }.recoverCatching { throw friendly(it) }

    suspend fun myLetters(): Result<List<LetterSummaryDto>> = runCatching {
        api.letters().data
    }.recoverCatching { throw friendly(it) }

    suspend fun detail(letterId: Long): Result<LetterDetailDto> = runCatching {
        api.letterDetail(letterId).data
    }.recoverCatching { throw friendly(it) }

    suspend fun submit(
        letterTypeId: Long,
        data: Map<String, String>,
        attachmentUris: List<Uri>,
    ): Result<LetterDetailDto> = runCatching {
        api.submitLetter(parts(letterTypeId, data), files(attachmentUris)).data
    }.recoverCatching { throw friendly(it) }

    suspend fun repair(
        letterId: Long,
        data: Map<String, String>,
        attachmentUris: List<Uri>,
    ): Result<LetterDetailDto> = runCatching {
        api.repairLetter(letterId, parts(null, data), files(attachmentUris)).data
    }.recoverCatching { throw friendly(it) }

    private fun parts(letterTypeId: Long?, data: Map<String, String>): Map<String, RequestBody> {
        val text = "text/plain".toMediaTypeOrNull()
        val map = mutableMapOf<String, RequestBody>()
        letterTypeId?.let { map["letter_type_id"] = it.toString().toRequestBody(text) }
        data.forEach { (key, value) -> map["data[$key]"] = value.toRequestBody(text) }
        return map
    }

    private fun files(uris: List<Uri>): List<MultipartBody.Part> = uris.mapIndexed { _, uri ->
        val mime = context.contentResolver.getType(uri) ?: "application/octet-stream"
        val ext = mime.substringAfter('/').substringBefore(';')
        val bytes = context.contentResolver.openInputStream(uri)?.use { it.readBytes() }
            ?: throw Exception("Berkas tidak bisa dibaca. Pilih ulang fotonya.")
        MultipartBody.Part.createFormData(
            "attachments[]",
            "${UUID.randomUUID()}.$ext",
            bytes.toRequestBody(mime.toMediaTypeOrNull()),
        )
    }

    /** Pesan error manusiawi untuk warga (§6); pesan 422 backend sudah berbahasa Indonesia. */
    private fun friendly(t: Throwable): Throwable = when {
        t is HttpException && t.code() == 422 -> Exception(extractMessage(t) ?: "Periksa kembali isian surat.")
        t is HttpException && t.code() == 403 -> Exception("Belum boleh memakai layanan surat. Tunggu verifikasi akun Anda oleh operator desa.")
        t is HttpException && t.code() == 429 -> Exception("Terlalu sering mengirim. Tunggu sebentar lalu coba lagi.")
        t is HttpException -> Exception("Permintaan gagal (${t.code()}). Coba lagi atau hubungi kantor desa.")
        else -> t
    }

    /** Retrofit >= 2.11: errorBody diakses via Response, bukan HttpException langsung. */
    private fun extractMessage(e: HttpException): String? = try {
        e.response()?.errorBody()?.string()?.let { body ->
            Regex("\"message\"\\s*:\\s*\"([^\"]+)\"").find(body)?.groupValues?.get(1)
        }
    } catch (_: Exception) {
        null
    }
}
