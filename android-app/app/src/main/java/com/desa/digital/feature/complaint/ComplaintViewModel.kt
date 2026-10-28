package com.desa.digital.feature.complaint

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.desa.digital.core.network.dto.ComplaintCategoryDto
import com.desa.digital.core.network.dto.ComplaintDetailDto
import com.desa.digital.core.network.dto.ComplaintSummaryDto
import com.desa.digital.data.repository.ComplaintRepository
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import javax.inject.Inject

/**
 * State modul Pengaduan Fase 3 (PEMBANGUNAN.md 4.5). Satu ViewModel untuk daftar laporan
 * milik warga, formulir pengajuan (kategori + foto + lokasi + anonim), detail/kronologi,
 * dan balasan selama laporan masih terbuka.
 */
@HiltViewModel
class ComplaintViewModel @Inject constructor(
    private val repository: ComplaintRepository,
) : ViewModel() {

    private val _categories = MutableStateFlow<List<ComplaintCategoryDto>>(emptyList())
    val categories: StateFlow<List<ComplaintCategoryDto>> = _categories.asStateFlow()

    private val _complaints = MutableStateFlow<List<ComplaintSummaryDto>>(emptyList())
    val complaints: StateFlow<List<ComplaintSummaryDto>> = _complaints.asStateFlow()

    private val _detail = MutableStateFlow<ComplaintDetailDto?>(null)
    val detail: StateFlow<ComplaintDetailDto?> = _detail.asStateFlow()

    private val _busy = MutableStateFlow(false)
    val busy: StateFlow<Boolean> = _busy.asStateFlow()

    private val _message = MutableStateFlow<String?>(null)
    val message: StateFlow<String?> = _message.asStateFlow()

    private val _submittedComplaintId = MutableStateFlow<Long?>(null)
    val submittedComplaintId: StateFlow<Long?> = _submittedComplaintId.asStateFlow()

    fun loadCategories() = viewModelScope.launch {
        repository.categories().onSuccess { _categories.value = it }.onFailure { _message.value = it.message }
    }

    fun loadComplaints() = viewModelScope.launch {
        _busy.value = true
        repository.myComplaints().onSuccess { _complaints.value = it }.onFailure { _message.value = it.message }
        _busy.value = false
    }

    fun loadDetail(complaintId: Long) = viewModelScope.launch {
        _busy.value = true
        repository.detail(complaintId).onSuccess { _detail.value = it }.onFailure { _message.value = it.message }
        _busy.value = false
    }

    fun submit(
        category: String,
        title: String,
        description: String,
        location: String?,
        latitude: Double?,
        longitude: Double?,
        isAnonymous: Boolean,
        photoUris: List<android.net.Uri>,
    ) {
        if (category.isBlank()) { _message.value = "Pilih dulu kategori laporannya."; return }
        if (title.isBlank()) { _message.value = "Judul laporan tidak boleh kosong."; return }
        if (description.isBlank()) { _message.value = "Jelaskan laporannya sebentar."; return }
        runWork {
            repository.submit(category, title.trim(), description.trim(), location?.trim(), latitude, longitude, isAnonymous, photoUris)
        }
    }

    fun reply(complaintId: Long, body: String) {
        if (body.isBlank()) { _message.value = "Tulis dulu balasannya."; return }
        viewModelScope.launch {
            _busy.value = true
            repository.reply(complaintId, body.trim())
                .onSuccess { _detail.value = it; _message.value = "Balasan terkirim." }
                .onFailure { _message.value = it.message ?: "Balasan gagal. Coba lagi." }
            _busy.value = false
        }
    }

    private fun runWork(action: suspend () -> Result<ComplaintDetailDto>) = viewModelScope.launch {
        _busy.value = true
        action().onSuccess {
            _detail.value = it
            _submittedComplaintId.value = it.id
            _message.value = "Laporan terkirim. Pantau statusnya di halaman pengaduan."
        }.onFailure { _message.value = it.message ?: "Pengiriman gagal. Coba lagi." }
        _busy.value = false
    }

    fun consumeMessage() { _message.value = null }
    fun consumeSubmitted() { _submittedComplaintId.value = null }
}
