package com.desa.digital.core.network.dto

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

/**
 * DTO modul Pengaduan Fase 3 (PEMBANGUNAN.md 4.5). Bentuk mengikuti respons API backend
 * (docs/openapi.yaml). Kategori dikirim sebagai kode (mis. "jalan_rusak"), label
 * berbahasa Indonesia disediakan server lewat kategori_label/status_label.
 */
@Serializable
data class ComplaintCategoryDto(
    val code: String = "",
    val label: String = "",
)

@Serializable
data class ComplaintCategoryListResponse(val data: List<ComplaintCategoryDto> = emptyList())

@Serializable
data class ComplaintSummaryDto(
    val id: Long,
    @SerialName("category_label") val categoryLabel: String? = null,
    val title: String = "",
    val status: String = "",
    @SerialName("status_label") val statusLabel: String? = null,
    val closed: Boolean = false,
    @SerialName("submitted_at") val submittedAt: String? = null,
)

@Serializable
data class ComplaintListResponse(val data: List<ComplaintSummaryDto> = emptyList())

@Serializable
data class ComplaintPhotoDto(
    val id: Long,
    val name: String? = null,
    @SerialName("download_url") val downloadUrl: String? = null,
)

@Serializable
data class ComplaintReporterDto(
    val id: Long? = null,
    val name: String? = null,
)

/** Satu baris kronologi: balasan (warga/petugas) atau perubahan status (to_status terisi). */
@Serializable
data class ComplaintTimelineDto(
    val id: Long,
    val body: String? = null,
    @SerialName("is_staff") val isStaff: Boolean = false,
    val author: String? = null,
    @SerialName("to_status") val toStatus: String? = null,
    @SerialName("to_label") val toLabel: String? = null,
    val at: String? = null,
)

@Serializable
data class ComplaintDetailDto(
    val id: Long,
    val category: String = "",
    @SerialName("category_label") val categoryLabel: String? = null,
    val title: String = "",
    val description: String = "",
    val location: String? = null,
    val latitude: String? = null,
    val longitude: String? = null,
    val status: String = "",
    @SerialName("status_label") val statusLabel: String? = null,
    @SerialName("is_anonymous") val isAnonymous: Boolean = false,
    val reporter: ComplaintReporterDto? = null,
    val closed: Boolean = false,
    @SerialName("submitted_at") val submittedAt: String? = null,
    @SerialName("resolved_at") val resolvedAt: String? = null,
    val photos: List<ComplaintPhotoDto> = emptyList(),
    val timeline: List<ComplaintTimelineDto> = emptyList(),
)

@Serializable
data class ComplaintDataResponse(
    val message: String = "",
    val data: ComplaintDetailDto,
)

/** Body balasan (JSON) untuk POST /api/complaints/{id}/replies. */
@Serializable
data class ComplaintReplyRequest(val body: String)
