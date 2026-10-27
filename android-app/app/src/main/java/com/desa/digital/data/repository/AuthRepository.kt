package com.desa.digital.data.repository

import com.desa.digital.core.data.TokenStore
import com.desa.digital.core.network.ApiService
import com.desa.digital.core.network.dto.LoginRequest
import com.desa.digital.core.network.dto.UserDto
import javax.inject.Inject
import javax.inject.Singleton

/**
 * Repository fondasi (Fase 0). Menyimpan hasil login ke TokenStore.
 * Verifikasi akun + OTP + keluarga baru dibangun penuh pada Fase 1.
 */
@Singleton
class AuthRepository @Inject constructor(
    private val api: ApiService,
    private val tokenStore: TokenStore,
) {
    suspend fun login(phone: String, password: String): Result<UserDto> = runCatching {
        val resp = api.login(LoginRequest(phone = phone, password = password))
        tokenStore.token = resp.token
        resp.user
    }

    suspend fun me(): Result<UserDto> = runCatching {
        val header = tokenStore.authorizationHeader
            ?: error("Belum login.")
        api.me(header)
    }

    suspend fun logout(): Result<Unit> = runCatching {
        tokenStore.authorizationHeader?.let { api.logout(it) }
        tokenStore.clear()
    }
}
