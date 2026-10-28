package com.desa.digital.core.network

import com.desa.digital.core.network.dto.ComplaintCategoryListResponse
import com.desa.digital.core.network.dto.ComplaintDataResponse
import com.desa.digital.core.network.dto.ComplaintListResponse
import com.desa.digital.core.network.dto.ComplaintReplyRequest
import com.desa.digital.core.network.dto.LetterDataResponse
import com.desa.digital.core.network.dto.LetterListResponse
import com.desa.digital.core.network.dto.LetterTypeListResponse
import com.desa.digital.core.network.dto.LoginRequest
import com.desa.digital.core.network.dto.LoginResponse
import com.desa.digital.core.network.dto.RegisterRequest
import com.desa.digital.core.network.dto.RegisterResponse
import com.desa.digital.core.network.dto.UserDto
import okhttp3.MultipartBody
import okhttp3.RequestBody
import retrofit2.http.Body
import retrofit2.http.GET
import retrofit2.http.Header
import retrofit2.http.Multipart
import retrofit2.http.POST
import retrofit2.http.PUT
import retrofit2.http.Part
import retrofit2.http.PartMap
import retrofit2.http.Path

/**
 * Kontrak API backend Laravel (lihat docs/openapi.yaml).
 * Fase 0: health/login/me/logout. Fase 1: registrasi NIK + consent + logout-all
 * (tanpa OTP/SMS pihak ketiga). Fase 2: surat — jenis dinamis, ajukan (multipart),
 * lacak, perbaiki. Authorization otomatis via interceptor [com.desa.digital.core.di.NetworkModule].
 */
interface ApiService {
    @GET("api/health")
    suspend fun health(): Map<String, String>

    @POST("api/login")
    suspend fun login(@Body body: LoginRequest): LoginResponse

    @POST("api/register")
    suspend fun register(@Body body: RegisterRequest): RegisterResponse

    @GET("api/me")
    suspend fun me(@Header("Authorization") authorization: String): UserDto

    @POST("api/logout")
    suspend fun logout(@Header("Authorization") authorization: String): Map<String, String>

    @POST("api/logout-all")
    suspend fun logoutAll(@Header("Authorization") authorization: String): Map<String, String>

    // ---- Fase 2: Surat (warga) ----

    @GET("api/letters/types")
    suspend fun letterTypes(): LetterTypeListResponse

    /** Ajukan surat. parts: "letter_type_id" + "data[key]"; files: attachments[]. */
    @Multipart
    @POST("api/letters")
    suspend fun submitLetter(
        @PartMap parts: Map<String, RequestBody>,
        @Part files: List<MultipartBody.Part>,
    ): LetterDataResponse

    @GET("api/letters")
    suspend fun letters(): LetterListResponse

    @GET("api/letters/{id}")
    suspend fun letterDetail(@Path("id") letterId: Long): LetterDataResponse

    /** Perbaiki surat berstatus perlu_perbaikan (skema sama dengan submitLetter). */
    @Multipart
    @PUT("api/letters/{id}")
    suspend fun repairLetter(
        @Path("id") letterId: Long,
        @PartMap parts: Map<String, RequestBody>,
        @Part files: List<MultipartBody.Part>,
    ): LetterDataResponse

    // ---- Fase 3: Pengaduan (warga) ----

    /** Kategori baku pengaduan untuk mengisi pilihan pada formulir. */
    @GET("api/complaints/categories")
    suspend fun complaintCategories(): ComplaintCategoryListResponse

    /** Ajukan pengaduan. parts: category/title/description/location/latitude/longitude/is_anonymous; files: photos[]. */
    @Multipart
    @POST("api/complaints")
    suspend fun submitComplaint(
        @PartMap parts: Map<String, RequestBody>,
        @Part files: List<MultipartBody.Part>,
    ): ComplaintDataResponse

    @GET("api/my-complaints")
    suspend fun myComplaints(): ComplaintListResponse

    @GET("api/complaints/{id}")
    suspend fun complaintDetail(@Path("id") complaintId: Long): ComplaintDataResponse

    /** Balasan warga pada pengaduan yang masih terbuka. */
    @POST("api/complaints/{id}/replies")
    suspend fun replyComplaint(
        @Path("id") complaintId: Long,
        @Body body: ComplaintReplyRequest,
    ): ComplaintDataResponse
}
