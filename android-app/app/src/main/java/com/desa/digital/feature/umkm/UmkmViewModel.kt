package com.desa.digital.feature.umkm

import android.net.Uri
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.desa.digital.core.network.dto.UmkmCategoryDto
import com.desa.digital.core.network.dto.UmkmProductDto
import com.desa.digital.core.network.dto.UmkmShopDto
import com.desa.digital.data.repository.UmkmRepository
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import javax.inject.Inject

/**
 * State modul Marketplace UMKM Fase 5 (PEMBANGUNAN.md 4.4). Satu ViewModel untuk sisi pembeli
 * (jelajah/cari/saring, detail toko & produk, tombol Chat WhatsApp, lapor moderasi) dan sisi
 * pelaku (daftar/ubah toko, kelola katalog produk, statistik penjual). Tautan WA dibangun server
 * lalu dilempar lewat [waUrl] untuk dibuka UI via Intent. TIDAK ada pembayaran di aplikasi.
 */
@HiltViewModel
class UmkmViewModel @Inject constructor(
    private val repository: UmkmRepository,
) : ViewModel() {

    private val _categories = MutableStateFlow<List<UmkmCategoryDto>>(emptyList())
    val categories: StateFlow<List<UmkmCategoryDto>> = _categories.asStateFlow()

    private val _shops = MutableStateFlow<List<UmkmShopDto>>(emptyList())
    val shops: StateFlow<List<UmkmShopDto>> = _shops.asStateFlow()

    private val _shopDetail = MutableStateFlow<UmkmShopDto?>(null)
    val shopDetail: StateFlow<UmkmShopDto?> = _shopDetail.asStateFlow()

    private val _productDetail = MutableStateFlow<UmkmProductDto?>(null)
    val productDetail: StateFlow<UmkmProductDto?> = _productDetail.asStateFlow()

    private val _myShop = MutableStateFlow<UmkmShopDto?>(null)
    val myShop: StateFlow<UmkmShopDto?> = _myShop.asStateFlow()

    private val _waUrl = MutableStateFlow<String?>(null)
    val waUrl: StateFlow<String?> = _waUrl.asStateFlow()

    private val _busy = MutableStateFlow(false)
    val busy: StateFlow<Boolean> = _busy.asStateFlow()

    private val _message = MutableStateFlow<String?>(null)
    val message: StateFlow<String?> = _message.asStateFlow()

    fun loadCategories() = viewModelScope.launch {
        repository.categories().onSuccess { _categories.value = it }.onFailure { _message.value = it.message }
    }

    fun browse(q: String? = null, category: String? = null, sort: String = DEFAULT_SORT) = viewModelScope.launch {
        _busy.value = true
        repository.browse(q, category, sort).onSuccess { _shops.value = it }.onFailure { _message.value = it.message }
        _busy.value = false
    }

    fun loadShop(shopId: Long) = viewModelScope.launch {
        _busy.value = true
        repository.shopDetail(shopId).onSuccess { _shopDetail.value = it }.onFailure { _message.value = it.message }
        _busy.value = false
    }

    fun loadProduct(productId: Long) = viewModelScope.launch {
        _busy.value = true
        repository.productDetail(productId).onSuccess { _productDetail.value = it }.onFailure { _message.value = it.message }
        _busy.value = false
    }

    /** Minta tautan WA ke server (mencatat klik), lalu UI membuka [waUrl]. [productId] => pesan menyebut produk. */
    fun requestWa(shopId: Long, productId: Long? = null) = viewModelScope.launch {
        _busy.value = true
        repository.shopWa(shopId, productId).onSuccess { _waUrl.value = it }.onFailure { _message.value = it.message }
        _busy.value = false
    }

    fun reportShop(shopId: Long, reason: String) {
        if (reason.isBlank()) { _message.value = "Tulis dulu alasan laporan."; return }
        viewModelScope.launch {
            _busy.value = true
            repository.reportShop(shopId, reason)
                .onSuccess { _message.value = "Laporan terkirim. Terima kasih telah menjaga marketplace desa." }
                .onFailure { _message.value = it.message ?: "Laporan gagal dikirim." }
            _busy.value = false
        }
    }

    fun reportProduct(productId: Long, reason: String) {
        if (reason.isBlank()) { _message.value = "Tulis dulu alasan laporan."; return }
        viewModelScope.launch {
            _busy.value = true
            repository.reportProduct(productId, reason)
                .onSuccess { _message.value = "Laporan terkirim. Terima kasih telah menjaga marketplace desa." }
                .onFailure { _message.value = it.message ?: "Laporan gagal dikirim." }
            _busy.value = false
        }
    }

    fun loadMyShop() = viewModelScope.launch {
        _busy.value = true
        repository.myShop().onSuccess { _myShop.value = it }.onFailure { _message.value = it.message }
        _busy.value = false
    }

    fun saveShop(
        name: String,
        description: String?,
        category: String,
        address: String?,
        rt: String?,
        rw: String?,
        openingHours: String?,
        waNumber: String,
        photoUri: Uri?,
    ) {
        if (name.isBlank()) { _message.value = "Nama toko tidak boleh kosong."; return }
        if (category.isBlank()) { _message.value = "Pilih kategori usaha."; return }
        if (waNumber.isBlank()) { _message.value = "Isi nomor WhatsApp untuk dihubungi pembeli."; return }
        viewModelScope.launch {
            _busy.value = true
            repository.saveShop(name, description, category, address, rt, rw, openingHours, waNumber, photoUri)
                .onSuccess {
                    _myShop.value = it
                    _message.value = "Toko tersimpan. Menunggu verifikasi petugas desa."
                }
                .onFailure { _message.value = it.message ?: "Gagal menyimpan toko." }
            _busy.value = false
        }
    }

    fun addProduct(name: String, description: String?, priceText: String, isAvailable: Boolean, photoUris: List<Uri>) {
        val price = parsePrice(priceText)
        if (name.isBlank()) { _message.value = "Nama produk tidak boleh kosong."; return }
        if (price == null) { _message.value = "Harga produk tidak valid. Isi angka."; return }
        viewModelScope.launch {
            _busy.value = true
            repository.addProduct(name, description, price, isAvailable, photoUris)
                .onSuccess {
                    _message.value = "Produk ditambahkan."
                    refreshMyShop()
                }
                .onFailure { _message.value = it.message ?: "Gagal menambahkan produk." }
            _busy.value = false
        }
    }

    fun updateProduct(
        productId: Long,
        name: String,
        description: String?,
        priceText: String,
        isAvailable: Boolean,
        photoUris: List<Uri>,
        replacePhotos: Boolean,
    ) {
        val price = parsePrice(priceText)
        if (name.isBlank()) { _message.value = "Nama produk tidak boleh kosong."; return }
        if (price == null) { _message.value = "Harga produk tidak valid. Isi angka."; return }
        viewModelScope.launch {
            _busy.value = true
            repository.updateProduct(productId, name, description, price, isAvailable, photoUris, replacePhotos)
                .onSuccess {
                    _message.value = "Produk diperbarui."
                    refreshMyShop()
                }
                .onFailure { _message.value = it.message ?: "Gagal memperbarui produk." }
            _busy.value = false
        }
    }

    fun deleteProduct(productId: Long) = viewModelScope.launch {
        _busy.value = true
        repository.deleteProduct(productId)
            .onSuccess {
                _message.value = "Produk dihapus."
                refreshMyShop()
            }
            .onFailure { _message.value = it.message ?: "Gagal menghapus produk." }
        _busy.value = false
    }

    /** Ambil ulang "toko saya" tanpa men_toggle busy (dipakai setelah CRUD produk). */
    private suspend fun refreshMyShop() {
        repository.myShop().onSuccess { _myShop.value = it }.onFailure { _message.value = it.message }
    }

    private fun parsePrice(raw: String): Double? =
        raw.filter { it.isDigit() }.toLongOrNull()?.toDouble()

    fun consumeMessage() { _message.value = null }
    fun consumeWa() { _waUrl.value = null }

    companion object {
        const val DEFAULT_SORT = "baru"
        const val SORT_POPULER = "populer"
        const val SORT_TERDEKAT = "terdekat"
    }
}
