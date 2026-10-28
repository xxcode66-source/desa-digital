package com.desa.digital.core.network.dto

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

/**
 * DTO modul PBB Fase 4 (PEMBANGUNAN.md 4.3). Bentuk mengikuti respons API backend
 * (docs/openapi.yaml & App\Http\Controllers\Api\PbbController). Nama wajib pajak pada
 * hasil cek NOP sudah disamarkan server (wp_name_masked) — aplikasi tidak menerima nama asli.
 * Uang TIDAK diproses di aplikasi; status "lunas" adalah verifikasi internal desa.
 */

/** Satu tagihan NOP per tahun. nop/wp_name/rt hanya terisi pada mode non-cek-NOP (kolektor). */
@Serializable
data class PbbBillDto(
    val id: Long = 0,
    val year: Int = 0,
    val amount: Double = 0.0,
    val penalty: Double = 0.0,
    val total: Double = 0.0,
    val status: String = "",
    @SerialName("status_label") val statusLabel: String? = null,
    @SerialName("is_arrears") val isArrears: Boolean = false,
    @SerialName("due_date") val dueDate: String? = null,
    val nop: String? = null,
    @SerialName("wp_name") val wpName: String? = null,
    val rt: String? = null,
)

/** Hasil cek NOP publik: nama WP disamarkan + disclaimer. */
@Serializable
data class PbbNopLookupDto(
    val nop: String = "",
    @SerialName("wp_name_masked") val wpNameMasked: String? = null,
    val address: String? = null,
    val rt: String? = null,
    val rw: String? = null,
    @SerialName("land_area") val landArea: String? = null,
    @SerialName("building_area") val buildingArea: String? = null,
    @SerialName("arrears_count") val arrearsCount: Int = 0,
    @SerialName("arrears_total") val arrearsTotal: Double = 0.0,
    val bills: List<PbbBillDto> = emptyList(),
    val disclaimer: String? = null,
)

@Serializable
data class PbbNopLookupResponse(val data: PbbNopLookupDto)

/** Objek pajak milik warga ("NOP saya") — nama WP lengkap krn sudah diverifikasi pemiliknya. */
@Serializable
data class PbbMyObjectDto(
    val id: Long = 0,
    val nop: String = "",
    @SerialName("wp_name") val wpName: String? = null,
    val address: String? = null,
    val rt: String? = null,
    val rw: String? = null,
    @SerialName("arrears_count") val arrearsCount: Int = 0,
    @SerialName("arrears_total") val arrearsTotal: Double = 0.0,
    val bills: List<PbbBillDto> = emptyList(),
)

@Serializable
data class PbbMyObjectListResponse(val data: List<PbbMyObjectDto> = emptyList())

/** Klaim kepemilikan NOP milik warga (riwayat + status verifikasi). */
@Serializable
data class PbbClaimDto(
    val id: Long = 0,
    val nop: String? = null,
    @SerialName("wp_name") val wpName: String? = null,
    val address: String? = null,
    val status: String = "",
    @SerialName("status_label") val statusLabel: String? = null,
    val note: String? = null,
    @SerialName("review_note") val reviewNote: String? = null,
    @SerialName("has_evidence") val hasEvidence: Boolean = false,
    @SerialName("evidence_url") val evidenceUrl: String? = null,
    @SerialName("reviewed_at") val reviewedAt: String? = null,
    @SerialName("created_at") val createdAt: String? = null,
)

@Serializable
data class PbbClaimListResponse(val data: List<PbbClaimDto> = emptyList())

@Serializable
data class PbbClaimDataResponse(
    val message: String = "",
    val data: PbbClaimDto,
)

/** Baris tagihan untuk dipilih kolektor (lingkup RT-nya). */
@Serializable
data class PbbBillListResponse(val data: List<PbbBillDto> = emptyList())

/** Item dalam slip setoran. */
@Serializable
data class PbbDepositItemDto(
    val id: Long = 0,
    val nop: String? = null,
    val year: Int = 0,
    @SerialName("amount_claimed") val amountClaimed: Double = 0.0,
    @SerialName("bill_total") val billTotal: Double = 0.0,
    val status: String = "",
    @SerialName("status_label") val statusLabel: String? = null,
    @SerialName("review_note") val reviewNote: String? = null,
)

/** Slip setoran (batch) milik kolektor. */
@Serializable
data class PbbDepositBatchDto(
    val id: Long = 0,
    val title: String = "",
    @SerialName("total_amount") val totalAmount: Double = 0.0,
    @SerialName("item_count") val itemCount: Int = 0,
    val status: String = "",
    @SerialName("status_label") val statusLabel: String? = null,
    val note: String? = null,
    @SerialName("reviewed_at") val reviewedAt: String? = null,
    @SerialName("created_at") val createdAt: String? = null,
    val items: List<PbbDepositItemDto> = emptyList(),
)

@Serializable
data class PbbDepositBatchListResponse(val data: List<PbbDepositBatchDto> = emptyList())

@Serializable
data class PbbDepositBatchDataResponse(
    val message: String = "",
    val data: PbbDepositBatchDto,
)
