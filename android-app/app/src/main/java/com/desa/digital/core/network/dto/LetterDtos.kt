package com.desa.digital.core.network.dto

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

/**
 * DTO modul Surat Fase 2 (PEMBANGUNAN.md 4.2). Bentuk mengikuti respons API backend
 * (docs/openapi.yaml). Formulir bersifat dinamis: field dikirim sebagai
 * form_schema_json dan dirender sesuai tipe/opsinya.
 */
@Serializable
data class LetterFieldDto(
    val key: String,
    val label: String? = null,
    val type: String? = "text",
    val required: Boolean = false,
    val options: List<String>? = null,
)

@Serializable
data class LetterTypeDto(
    val id: Long,
    val code: String = "",
    val name: String = "",
    @SerialName("needs_rt") val needsRt: Boolean = false,
    @SerialName("form_schema_json") val formSchemaJson: List<LetterFieldDto> = emptyList(),
    @SerialName("required_attachments_json") val requiredAttachmentsJson: List<String>? = null,
)

@Serializable
data class LetterTypeListResponse(val data: List<LetterTypeDto> = emptyList())

@Serializable
data class LetterSummaryDto(
    val id: Long,
    @SerialName("type_name") val typeName: String? = null,
    val status: String = "",
    @SerialName("status_label") val statusLabel: String? = null,
    val number: String? = null,
    @SerialName("submitted_at") val submittedAt: String? = null,
)

@Serializable
data class LetterListResponse(val data: List<LetterSummaryDto> = emptyList())

@Serializable
data class LetterAttachmentDto(
    val id: Long,
    val name: String? = null,
    @SerialName("download_url") val downloadUrl: String? = null,
)

@Serializable
data class LetterHistoryDto(
    val from: String? = null,
    val to: String? = null,
    @SerialName("to_label") val toLabel: String? = null,
    val note: String? = null,
    val flagged: List<String>? = null,
    val at: String? = null,
)

@Serializable
data class LetterRefDto(
    val id: Long? = null,
    val name: String? = null,
    val code: String? = null,
    @SerialName("needs_rt") val needsRt: Boolean = false,
)

@Serializable
data class LetterDetailDto(
    val id: Long,
    val type: LetterRefDto? = null,
    val status: String = "",
    @SerialName("status_label") val statusLabel: String? = null,
    val number: String? = null,
    val data: Map<String, String> = emptyMap(),
    val note: String? = null,
    @SerialName("flagged_fields") val flaggedFields: List<String> = emptyList(),
    val editable: Boolean = false,
    @SerialName("submitted_at") val submittedAt: String? = null,
    @SerialName("completed_at") val completedAt: String? = null,
    val attachments: List<LetterAttachmentDto> = emptyList(),
    val history: List<LetterHistoryDto> = emptyList(),
)

@Serializable
data class LetterDataResponse(
    val message: String = "",
    val data: LetterDetailDto,
)
