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
import com.desa.digital.core.network.dto.PbbBillListResponse
import com.desa.digital.core.network.dto.PbbClaimDataResponse
import com.desa.digital.core.network.dto.PbbClaimListResponse
import com.desa.digital.core.network.dto.PbbDepositBatchDataResponse
import com.desa.digital.core.network.dto.PbbDepositBatchListResponse
import com.desa.digital.core.network.dto.PbbMyObjectListResponse
import com.desa.digital.core.network.dto.PbbNopLookupResponse
import com.desa.digital.core.network.dto.RegisterRequest
import com.desa.digital.core.network.dto.RegisterResponse
import com.desa.digital.core.network.dto.UmkmCategoryListResponse
import com.desa.digital.core.network.dto.UmkmMessageResponse
import com.desa.digital.core.network.dto.UmkmProductDataResponse
import com.desa.digital.core.network.dto.UmkmProductDetailResponse
import com.desa.digital.core.network.dto.UmkmReportRequest
import com.desa.digital.core.network.dto.UmkmShopDataResponse
import com.desa.digital.core.network.dto.UmkmShopDetailResponse
import com.desa.digital.core.network.dto.UmkmShopListResponse
import com.desa.digital.core.network.dto.UmkmWaResponse
import com.desa.digital.core.network.dto.UserDto
import okhttp3.MultipartBody
import okhttp3.RequestBody
import retrofit2.http.Body
import retrofit2.http.DELETE
import retrofit2.http.GET
import retrofit2.http.Header
import retrofit2.http.Multipart
import retrofit2.http.POST
import retrofit2.http.PUT
import retrofit2.http.Part
import retrofit2.http.PartMap
import retrofit2.http.Path
import retrofit2.http.Query

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

    // ---- Fase 4: PBB (warga & kolektor) ----

    /** Cek NOP publik (nama WP disamarkan server). village_id diambil dari profil pengguna. */
    @GET("api/pbb/cek-nop")
    suspend fun cekNop(
        @Query("nop") nop: String,
        @Query("village_id") villageId: Long,
    ): PbbNopLookupResponse

    /** Ajukan klaim kepemilikan. parts: nop/note; file: bukti foto (SPPT/surat tanah). */
    @Multipart
    @POST("api/pbb/claims")
    suspend fun submitClaim(
        @PartMap parts: Map<String, RequestBody>,
        @Part evidence: MultipartBody.Part,
    ): PbbClaimDataResponse

    @GET("api/my-pbb-objects")
    suspend fun myPbbObjects(): PbbMyObjectListResponse

    @GET("api/my-pbb-claims")
    suspend fun myPbbClaims(): PbbClaimListResponse

    /** Tagihan dalam lingkup RT kolektor untuk dipilih saat menyetor. */
    @GET("api/pbb/bills")
    suspend fun collectorBills(@Query("year") year: Int? = null): PbbBillListResponse

    /** Kolektor mengajukan slip setoran. parts: title/note + bills[i][pbb_bill_id]/bills[i][amount_claimed]; file bukti opsional. */
    @Multipart
    @POST("api/pbb/deposits")
    suspend fun submitDeposit(
        @PartMap parts: Map<String, RequestBody>,
        @Part evidence: MultipartBody.Part? = null,
    ): PbbDepositBatchDataResponse

    @GET("api/my-pbb-deposits")
    suspend fun myPbbDeposits(): PbbDepositBatchListResponse

    // ---- Fase 5: Marketplace UMKM (warga/pembeli) ----

    /** Kategori usaha baku untuk filter marketplace & pengisian formulir toko. */
    @GET("api/umkm/categories")
    suspend fun umkmCategories(): UmkmCategoryListResponse

    /** Jelajahi toko aktif di desa: cari [q], saring [category], urutkan [sort] (baru/populer/terdekat). */
    @GET("api/umkm/shops")
    suspend fun umkmBrowse(
        @Query("q") q: String? = null,
        @Query("category") category: String? = null,
        @Query("sort") sort: String? = null,
        @Query("page") page: Int? = null,
    ): UmkmShopListResponse

    @GET("api/umkm/shops/{id}")
    suspend fun umkmShopDetail(@Path("id") shopId: Long): UmkmShopDetailResponse

    /** Klik "Chat WhatsApp" pada toko (opsional menyertakan produk) — mencatat statistik. */
    @GET("api/umkm/shops/{id}/wa")
    suspend fun umkmShopWa(
        @Path("id") shopId: Long,
        @Query("product") productId: Long? = null,
    ): UmkmWaResponse

    @GET("api/umkm/products/{id}")
    suspend fun umkmProductDetail(@Path("id") productId: Long): UmkmProductDetailResponse

    /** Lapor toko/produk untuk moderasi petugas desa. */
    @POST("api/umkm/shops/{id}/reports")
    suspend fun umkmReportShop(@Path("id") shopId: Long, @Body body: UmkmReportRequest): UmkmMessageResponse

    @POST("api/umkm/products/{id}/reports")
    suspend fun umkmReportProduct(@Path("id") productId: Long, @Body body: UmkmReportRequest): UmkmMessageResponse

    // ---- Fase 5: UMKM (pelaku — kelola toko & katalog) ----

    /** Toko milik pelaku yang login (status apa pun) + produk + statistik; null bila belum daftar. */
    @GET("api/umkm/my-shop")
    suspend fun umkmMyShop(): UmkmShopDetailResponse

    /** Buat/perbarui toko sendiri (multipart + 1 foto opsional pada field "photo"). */
    @Multipart
    @POST("api/umkm/my-shop")
    suspend fun umkmSaveShop(
        @PartMap parts: Map<String, RequestBody>,
        @Part photo: MultipartBody.Part? = null,
    ): UmkmShopDataResponse

    /** Tambah produk ke katalog (multipart, field "photos[]" maks 5). */
    @Multipart
    @POST("api/umkm/products")
    suspend fun umkmStoreProduct(
        @PartMap parts: Map<String, RequestBody>,
        @Part photos: List<MultipartBody.Part>,
    ): UmkmProductDataResponse

    /** Ubah produk; kirim "photos[]" untuk mengganti seluruh foto, kosongkan untuk pertahankan. */
    @Multipart
    @PUT("api/umkm/products/{id}")
    suspend fun umkmUpdateProduct(
        @Path("id") productId: Long,
        @PartMap parts: Map<String, RequestBody>,
        @Part photos: List<MultipartBody.Part>,
    ): UmkmProductDataResponse

    @DELETE("api/umkm/products/{id}")
    suspend fun umkmDeleteProduct(@Path("id") productId: Long): UmkmMessageResponse
}
