package com.desa.digital.data.repository

import android.content.Context
import android.net.Uri
import com.desa.digital.core.network.ApiService
import com.desa.digital.core.network.dto.ComplaintCategoryDto
import com.desa.digital.core.network.dto.ComplaintDetailDto
import com.desa.digital.core.network.dto.ComplaintReplyRequest
import com.desa.digital.core.network.dto.ComplaintSummaryDto
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
 * Repository pengaduan Fase 3 (PEMBANGUNAN.md 4.5). Membangun multipart dari Uri galeri
 * (foto bukti dikompres di pemilih gambar, hemat kuota) dan menerjemahkan error teknis
 * menjadi bahasa Indonesia sederhana, sama seperti modul surat.
 */
@Singleton
class ComplaintRepository @Inject constructor(
    private val api: ApiService,
    @ApplicationContext private val context: Context,
) {
    suspend fun categories(): Result<List<ComplaintCategoryDto>> = runCatching {
        api.complaintCategories().data
    }.recoverCatching { throw friendly(it) }

    suspend fun myComplaints(): Result<List<ComplaintSummaryDto>> = runCatching {
        api.myComplaints().data
    }.recoverCatching { throw friendly(it) }

    suspend fun detail(complaintId: Long): Result<ComplaintDetailDto> = runCatching {
        api.complaintDetail(complaintId).data
    }.recoverCatching { throw friendly(it) }

    suspend fun submit(
        category: String,
        title: String,
        description: String,
        location: String?,
        latitude: Double?,
        longitude: Double?,
        isAnonymous: Boolean,
        photoUris: List<Uri>,
    ): Result<ComplaintDetailDto> = runCatching {
        api.submitComplaint(parts(category, title, description, location, latitude, longitude, isAnonymous), files(photoUris)).data
    }.recoverCatching { throw friendly(it) }

    suspend fun reply(complaintId: Long, body: String): Result<ComplaintDetailDto> = runCatching {
        api.replyComplaint(complaintId, ComplaintReplyRequest(body)).data
    }.recoverCatching { throw friendly(it) }

    private fun parts(
        category: String,
        title: String,
        description: String,
        location: String?,
        latitude: Double?,
        longitude: Double?,
        isAnonymous: Boolean,
    ): Map<String, RequestBody> {
        val text = "text/plain".toMediaTypeOrNull()
        val map = mutableMapOf<String, RequestBody>()
        map["category"] = category.toRequestBody(text)
        map["title"] = title.toRequestBody(text)
        map["description"] = description.toRequestBody(text)
        map["is_anonymous"] = (if (isAnonymous) "1" else "0").toRequestBody(text)
        location?.takeIf { it.isNotBlank() }?.let { map["location"] = it.toRequestBody(text) }
        latitude?.let { map["latitude"] = it.toString().toRequestBody(text) }
        longitude?.let { map["longitude"] = it.toString().toRequestBody(text) }
        return map
    }

    private fun files(uris: List<Uri>): List<MultipartBody.Part> = uris.map { uri ->
        val mime = context.contentResolver.getType(uri) ?: "application/octet-stream"
        val ext = mime.substringAfter('/').substringBefore(';')
        val bytes = context.contentResolver.openInputStream(uri)?.use { it.readBytes() }
            ?: throw Exception("Foto tidak bisa dibaca. Pilih ulang fotonya.")
        MultipartBody.Part.createFormData(
            "photos[]",
            "${UUID.randomUUID()}.$ext",
            bytes.toRequestBody(mime.toMediaTypeOrNull()),
        )
    }

    /** Pesan error manusiawi untuk warga (§6); pesan 422 backend sudah berbahasa Indonesia. */
    private fun friendly(t: Throwable): Throwable = when {
        t is HttpException && t.code() == 422 -> Exception(extractMessage(t) ?: "Periksa kembali isian laporan.")
        t is HttpException && t.code() == 403 -> Exception("Belum boleh mengirim pengaduan. Tunggu verifikasi akun Anda oleh operator desa.")
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
