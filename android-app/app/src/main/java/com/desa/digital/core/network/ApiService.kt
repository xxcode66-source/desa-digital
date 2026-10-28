package com.desa.digital.core.network

import com.desa.digital.core.network.dto.LoginRequest
import com.desa.digital.core.network.dto.LoginResponse
import com.desa.digital.core.network.dto.RegisterRequest
import com.desa.digital.core.network.dto.RegisterResponse
import com.desa.digital.core.network.dto.UserDto
import retrofit2.http.Body
import retrofit2.http.GET
import retrofit2.http.Header
import retrofit2.http.POST

/**
 * Kontrak API backend Laravel (lihat docs/openapi.yaml).
 * Fase 0: health/login/me/logout. Fase 1: registrasi NIK + consent + logout-all
 * (tanpa OTP/SMS pihak ketiga). Endpoint surat/PBB/UMKM/pengaduan menyusul pada fasenya.
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
}
