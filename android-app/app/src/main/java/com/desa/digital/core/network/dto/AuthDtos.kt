package com.desa.digital.core.network.dto

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

@Serializable
data class LoginRequest(
    val phone: String,
    val password: String,
)

@Serializable
data class LoginResponse(
    val token: String,
    val user: UserDto,
)

@Serializable
data class OtpRequest(
    val phone: String,
)

@Serializable
data class OtpSendResponse(
    val message: String = "",
    // Hanya terisi di environment local/testing backend; null di produksi.
    @SerialName("debug_otp") val debugOtp: String? = null,
)

@Serializable
data class RegisterRequest(
    @SerialName("village_id") val villageId: Long,
    val nik: String,
    val name: String,
    val phone: String,
    val password: String,
    @SerialName("password_confirmation") val passwordConfirmation: String,
    val rt: String? = null,
    val rw: String? = null,
    @SerialName("otp_code") val otpCode: String,
    val consent: Boolean = true,
)

@Serializable
data class RegisterResponse(
    val message: String = "",
    val token: String,
    val user: UserDto,
)

@Serializable
data class UserDto(
    val id: Long,
    val name: String,
    val phone: String,
    val status: String = "pending",
    val rt: String? = null,
    val rw: String? = null,
    @SerialName("village_id") val villageId: Long? = null,
    @SerialName("phone_verified_at") val phoneVerifiedAt: String? = null,
    val roles: List<RoleDto> = emptyList(),
)

@Serializable
data class RoleDto(
    val id: Long? = null,
    val name: String,
    val label: String? = null,
)
