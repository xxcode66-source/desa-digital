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
data class UserDto(
    val id: Long,
    val name: String,
    val phone: String,
    val status: String = "pending",
    val rt: String? = null,
    val rw: String? = null,
    @SerialName("village_id") val villageId: Long? = null,
)
