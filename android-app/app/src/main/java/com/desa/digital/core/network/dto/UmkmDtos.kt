package com.desa.digital.core.network.dto

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

/**
 * DTO modul Marketplace UMKM Fase 5 (PEMBANGUNAN.md 4.4). Bentuk mengikuti presenter
 * App\Http\Controllers\Api\UmkmController (lihat docs/openapi.yaml). Satu bentuk [UmkmShopDto]
 * dipakai untuk kartu marketplace, detail toko, maupun "toko saya" — field lanjutan hanya terisi
 * pada respons yang relevan (server menyaring mana yang dikirim). Transaksi TIDAK terjadi di
 * aplikasi; [waUrl] hanyalah jembatan untuk menghubungi penjual secara offline.
 */

/** Kategori usaha baku (config desa) untuk filter marketplace & pilihan pada formulir toko. */
@Serializable
data class UmkmCategoryDto(
    val code: String = "",
    val label: String = "",
)

@Serializable
data class UmkmCategoryListResponse(val data: List<UmkmCategoryDto> = emptyList())

/** Satu foto produk (disk privat). Unduh lewat [downloadUrl] dengan header login. */
@Serializable
data class UmkmProductPhotoDto(
    val id: Long = 0,
    val name: String? = null,
    @SerialName("download_url") val downloadUrl: String? = null,
)

/** Produk UMKM. [photos] & [waUrl] hanya terisi pada respons detail (bukan kartu dalam toko). */
@Serializable
data class UmkmProductDto(
    val id: Long = 0,
    val name: String = "",
    val description: String? = null,
    val price: Double = 0.0,
    @SerialName("price_label") val priceLabel: String? = null,
    @SerialName("is_available") val isAvailable: Boolean = true,
    @SerialName("shop_id") val shopId: Long? = null,
    @SerialName("shop_name") val shopName: String? = null,
    @SerialName("photo_url") val photoUrl: String? = null,
    val photos: List<UmkmProductPhotoDto> = emptyList(),
    @SerialName("wa_url") val waUrl: String? = null,
)

/** Statistik penjual (hanya pada "toko saya"): berapa kali dilihat & klik WhatsApp. */
@Serializable
data class UmkmShopStatsDto(
    @SerialName("views_count") val viewsCount: Int = 0,
    @SerialName("wa_clicks_count") val waClicksCount: Int = 0,
)

/**
 * Toko UMKM. Pada daftar marketplace hanya field dasar + [productsCount] terisi; detail toko
 * menambah [products]/[waUrl]/[status]; "toko saya" menambah [isHidden]/[reviewNote]/[stats].
 */
@Serializable
data class UmkmShopDto(
    val id: Long = 0,
    val name: String = "",
    val category: String = "",
    @SerialName("category_label") val categoryLabel: String? = null,
    val description: String? = null,
    val rt: String? = null,
    val rw: String? = null,
    @SerialName("opening_hours") val openingHours: String? = null,
    @SerialName("products_count") val productsCount: Int = 0,
    @SerialName("photo_url") val photoUrl: String? = null,
    val address: String? = null,
    val status: String? = null,
    @SerialName("status_label") val statusLabel: String? = null,
    @SerialName("wa_url") val waUrl: String? = null,
    val products: List<UmkmProductDto> = emptyList(),
    @SerialName("is_hidden") val isHidden: Boolean = false,
    @SerialName("review_note") val reviewNote: String? = null,
    val stats: UmkmShopStatsDto? = null,
)

@Serializable
data class UmkmMetaDto(
    val total: Int = 0,
    val page: Int = 1,
)

@Serializable
data class UmkmShopListResponse(
    val data: List<UmkmShopDto> = emptyList(),
    val meta: UmkmMetaDto = UmkmMetaDto(),
)

/** Detail satu toko (data terisi) — dipakai shopDetail. Untuk myShop, [data] bisa null. */
@Serializable
data class UmkmShopDetailResponse(val data: UmkmShopDto? = null)

/** Simpan/perbarui toko (selalu menyertakan pesan + toko terbaru). */
@Serializable
data class UmkmShopDataResponse(
    val message: String = "",
    val data: UmkmShopDto? = null,
)

@Serializable
data class UmkmProductDetailResponse(val data: UmkmProductDto? = null)

@Serializable
data class UmkmProductDataResponse(
    val message: String = "",
    val data: UmkmProductDto? = null,
)

/** Respons tautan WhatsApp dari GET /umkm/shops/{id}/wa. */
@Serializable
data class UmkmWaDto(@SerialName("wa_url") val waUrl: String = "")

@Serializable
data class UmkmWaResponse(val data: UmkmWaDto = UmkmWaDto())

/** Body laporan moderasi (JSON) untuk POST .../reports. */
@Serializable
data class UmkmReportRequest(val reason: String)

/** Respons berisi pesan saja (hapus produk & kirim laporan). */
@Serializable
data class UmkmMessageResponse(val message: String = "")
