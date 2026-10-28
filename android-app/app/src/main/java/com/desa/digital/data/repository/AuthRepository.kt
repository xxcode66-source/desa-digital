package com.desa.digital.data.repository

import com.desa.digital.core.data.TokenStore
import com.desa.digital.core.network.ApiService
import com.desa.digital.core.network.dto.LoginRequest
import com.desa.digital.core.network.dto.OtpRequest
import com.desa.digital.core.network.dto.RegisterRequest
import com.desa.digital.core.network.dto.UserDto
import javax.inject.Inject
import javax.inject.Singleton
import retrofit2.HttpException

/**
 * Repository autentikasi (Fase 0 fondasi + Fase 1 registrasi/OTP).
 * Semua hasil dibungkus [Result]; pesan error diubah ke bahasa Indonesia sederhana.
 */
@Singleton
class AuthRepository @Inject constructor(
    private val api: ApiService,
    private val tokenStore: TokenStore,
) {
    val isLoggedIn: Boolean get() = tokenStore.token != null

    suspend fun login(phone: String, password: String): Result<UserDto> = runCatching {
        val resp = api.login(LoginRequest(phone = phone, password = password))
        tokenStore.token = resp.token
        resp.user
    }.recoverCatching { throw friendly(it) }

    /** Kirim OTP registrasi. Mengembalikan debug-otp (nullable) untuk dev/testing. */
    suspend fun sendRegisterOtp(phone: String): Result<String?> = runCatching {
        api.sendRegisterOtp(OtpRequest(phone = phone)).debugOtp
    }.recoverCatching { throw friendly(it) }

    suspend fun register(
        villageId: Long,
        nik: String,
        name: String,
        phone: String,
        password: String,
        rt: String?,
        rw: String?,
        otpCode: String,
    ): Result<UserDto> = runCatching {
        val resp = api.register(
            RegisterRequest(
                villageId = villageId,
                nik = nik,
                name = name,
                phone = phone,
                password = password,
                passwordConfirmation = password,
                rt = rt?.ifBlank { null },
                rw = rw?.ifBlank { null },
                otpCode = otpCode,
            ),
        )
        tokenStore.token = resp.token
        resp.user
    }.recoverCatching { throw friendly(it) }

    suspend fun me(): Result<UserDto> = runCatching {
        val header = tokenStore.authorizationHeader ?: error("Belum login.")
        api.me(header)
    }

    suspend fun logout(all: Boolean = false): Result<Unit> = runCatching {
        tokenStore.authorizationHeader?.let { if (all) api.logoutAll(it) else api.logout(it) }
        tokenStore.clear()
    }

    /** Terjemahkan error teknis ke pesan yang dimengerti warga (PEMBANGUNAN.md §6). */
    private fun friendly(t: Throwable): Throwable = when {
        t is HttpException && t.code() == 403 ->
            Exception("Nomor HP belum diverifikasi. Selesaikan verifikasi dulu.")
        t is HttpException && t.code() == 401 ->
            Exception("Nomor HP atau password salah.")
        t is HttpException && t.code() == 429 ->
            Exception("Terlalu sering mencoba. Tunggu sebentar lalu coba lagi.")
        else -> t
    }
}
