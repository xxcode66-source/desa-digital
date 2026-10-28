package com.desa.digital.feature.letter

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import android.content.ActivityNotFoundException
import android.content.Context
import android.content.Intent
import android.net.Uri
import com.desa.digital.core.network.dto.LetterDetailDto
import com.desa.digital.core.network.dto.LetterSummaryDto
import com.desa.digital.core.network.dto.LetterTypeDto
import com.desa.digital.data.repository.LetterRepository
import dagger.hilt.android.lifecycle.HiltViewModel
import dagger.hilt.android.qualifiers.ApplicationContext
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import javax.inject.Inject

/**
 * State modul Surat Fase 2 (PEMBANGUNAN.md 4.2). Satu ViewModel untuk daftar,
 * wizard dinamis (field dibangun dari form_schema jenis surat), detail/lacak,
 * dan perbaikan — sesuai instruksi operator per-field.
 */
@HiltViewModel
class LetterViewModel @Inject constructor(
    private val repository: LetterRepository,
    @ApplicationContext private val context: Context,
) : ViewModel() {

    private val _types = MutableStateFlow<List<LetterTypeDto>>(emptyList())
    val types: StateFlow<List<LetterTypeDto>> = _types.asStateFlow()

    private val _letters = MutableStateFlow<List<LetterSummaryDto>>(emptyList())
    val letters: StateFlow<List<LetterSummaryDto>> = _letters.asStateFlow()

    private val _detail = MutableStateFlow<LetterDetailDto?>(null)
    val detail: StateFlow<LetterDetailDto?> = _detail.asStateFlow()

    private val _busy = MutableStateFlow(false)
    val busy: StateFlow<Boolean> = _busy.asStateFlow()

    /** Pesan satu kali (error sukses/gagal); UI wajib memanggil [consumeMessage]. */
    private val _message = MutableStateFlow<String?>(null)
    val message: StateFlow<String?> = _message.asStateFlow()

    private val _submittedLetterId = MutableStateFlow<Long?>(null)
    val submittedLetterId: StateFlow<Long?> = _submittedLetterId.asStateFlow()

    fun loadTypes() = viewModelScope.launch {
        repository.letterTypes().onSuccess { _types.value = it }.onFailure { _message.value = it.message }
    }

    fun loadLetters() = viewModelScope.launch {
        _busy.value = true
        repository.myLetters().onSuccess { _letters.value = it }.onFailure { _message.value = it.message }
        _busy.value = false
    }

    fun loadDetail(letterId: Long) = viewModelScope.launch {
        _busy.value = true
        repository.detail(letterId).onSuccess { _detail.value = it }.onFailure { _message.value = it.message }
        _busy.value = false
    }

    fun submit(letterTypeId: Long, data: Map<String, String>, attachmentUris: List<android.net.Uri>) {
        if (attachmentUris.isEmpty()) {
            _message.value = "Unggah minimal satu lampiran (foto KTP/Surat pengantar)."
            return
        }
        runWork { repository.submit(letterTypeId, data, attachmentUris) }
    }

    fun repair(letterId: Long, data: Map<String, String>, attachmentUris: List<android.net.Uri>) {
        runWork { repository.repair(letterId, data, attachmentUris) }
    }

    /** Unduh PDF surat selesai ke cache lalu buka di aplikasi pembaca PDF (§4.2). */
    fun downloadAndOpenPdf(letterId: Long) = viewModelScope.launch {
        _busy.value = true
        repository.downloadPdf(letterId)
            .onSuccess { uri -> openPdf(uri) }
            .onFailure { _message.value = it.message ?: "Gagal mengunduh PDF surat." }
        _busy.value = false
    }

    private suspend fun openPdf(uri: Uri) = withContext(Dispatchers.Main) {
        val intent = Intent(Intent.ACTION_VIEW).apply {
            setDataAndType(uri, "application/pdf")
            addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION or Intent.FLAG_ACTIVITY_NEW_TASK)
        }
        try {
            context.startActivity(intent)
        } catch (_: ActivityNotFoundException) {
            _message.value = "Belum ada aplikasi pembaca PDF di perangkat ini."
        }
    }

    private fun runWork(action: suspend () -> Result<LetterDetailDto>) = viewModelScope.launch {
        _busy.value = true
        action().onSuccess {
            _detail.value = it
            _submittedLetterId.value = it.id
            _message.value = "Surat terkirim. Pantau statusnya di halaman surat."
        }.onFailure { _message.value = it.message ?: "Pengiriman gagal. Coba lagi." }
        _busy.value = false
    }

    fun consumeMessage() { _message.value = null }
    fun consumeSubmitted() { _submittedLetterId.value = null }
}
