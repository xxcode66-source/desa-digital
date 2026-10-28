package com.desa.digital.feature.pbb

import android.net.Uri
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.desa.digital.core.network.dto.PbbBillDto
import com.desa.digital.core.network.dto.PbbClaimDto
import com.desa.digital.core.network.dto.PbbDepositBatchDto
import com.desa.digital.core.network.dto.PbbMyObjectDto
import com.desa.digital.core.network.dto.PbbNopLookupDto
import com.desa.digital.data.repository.AuthRepository
import com.desa.digital.data.repository.PbbRepository
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import javax.inject.Inject

/**
 * State modul PBB Fase 4 (PEMBANGUNAN.md 4.3). Satu ViewModel untuk: cek NOP (publik),
 * klaim kepemilikan + riwayat klaim, "NOP saya", dan alur kolektor (pilih tagihan & setor).
 * village_id serta peran kolektor dibaca dari profil/login agar cek NOP & setoran memakai
 * konteks desa yang benar. TIDAK ada pembayaran di aplikasi.
 */
@HiltViewModel
class PbbViewModel @Inject constructor(
    private val repository: PbbRepository,
    private val authRepository: AuthRepository,
) : ViewModel() {

    private val _villageId = MutableStateFlow<Long?>(null)
    val villageId: StateFlow<Long?> = _villageId.asStateFlow()

    private val _isCollector = MutableStateFlow(false)
    val isCollector: StateFlow<Boolean> = _isCollector.asStateFlow()

    private val _lookup = MutableStateFlow<PbbNopLookupDto?>(null)
    val lookup: StateFlow<PbbNopLookupDto?> = _lookup.asStateFlow()

    private val _myObjects = MutableStateFlow<List<PbbMyObjectDto>>(emptyList())
    val myObjects: StateFlow<List<PbbMyObjectDto>> = _myObjects.asStateFlow()

    private val _myClaims = MutableStateFlow<List<PbbClaimDto>>(emptyList())
    val myClaims: StateFlow<List<PbbClaimDto>> = _myClaims.asStateFlow()

    private val _bills = MutableStateFlow<List<PbbBillDto>>(emptyList())
    val bills: StateFlow<List<PbbBillDto>> = _bills.asStateFlow()

    private val _deposits = MutableStateFlow<List<PbbDepositBatchDto>>(emptyList())
    val deposits: StateFlow<List<PbbDepositBatchDto>> = _deposits.asStateFlow()

    private val _busy = MutableStateFlow(false)
    val busy: StateFlow<Boolean> = _busy.asStateFlow()

    private val _message = MutableStateFlow<String?>(null)
    val message: StateFlow<String?> = _message.asStateFlow()

    private val _lastClaim = MutableStateFlow<PbbClaimDto?>(null)
    val lastClaim: StateFlow<PbbClaimDto?> = _lastClaim.asStateFlow()

    private val _lastDeposit = MutableStateFlow<PbbDepositBatchDto?>(null)
    val lastDeposit: StateFlow<PbbDepositBatchDto?> = _lastDeposit.asStateFlow()

    init { loadAccount() }

    /** village_id + status kolektor diperlukan utk cek NOP & menu setoran. */
    fun loadAccount() = viewModelScope.launch {
        authRepository.me().onSuccess { user ->
            _villageId.value = user.villageId
            _isCollector.value = user.roles.any { it.name == "rt" || it.name == "kolektor" }
        }.onFailure { _message.value = it.message ?: "Gagal memuat data akun." }
    }

    fun checkNop(rawNop: String) {
        val digits = rawNop.filter { it.isDigit() }
        if (digits.length < 8) { _message.value = "NOP minimal 18 digit. Masukkan Nomor Objek Pajak yang benar."; return }
        val village = _villageId.value
        if (village == null) { _message.value = "Desa Anda belum dikenali. Masuk ulang lalu coba lagi."; return }
        viewModelScope.launch {
            _busy.value = true
            _lookup.value = null
            repository.cekNop(digits, village)
                .onSuccess { _lookup.value = it }
                .onFailure { _message.value = it.message }
            _busy.value = false
        }
    }

    fun loadMyObjects() = runList({ repository.myObjects() }) { _myObjects.value = it }

    fun loadMyClaims() = runList({ repository.myClaims() }) { _myClaims.value = it }

    fun loadBills() = runList({ repository.collectorBills() }) { _bills.value = it }

    fun loadMyDeposits() = runList({ repository.myDeposits() }) { _deposits.value = it }

    fun submitClaim(nop: String, note: String?, evidenceUri: Uri?) {
        if (nop.isBlank()) { _message.value = "Isi NOP yang ingin diklaim."; return }
        if (evidenceUri == null) { _message.value = "Lampirkan foto bukti (SPPT / surat tanah)."; return }
        viewModelScope.launch {
            _busy.value = true
            repository.submitClaim(nop, note, evidenceUri)
                .onSuccess {
                    _lastClaim.value = it
                    _message.value = "Klaim terkirim, menunggu verifikasi desa."
                }
                .onFailure { _message.value = it.message ?: "Klaim gagal dikirim. Coba lagi." }
            _busy.value = false
        }
    }

    /** Kolektor menyetor tagihan terpilih. [selected] = map id tagihan -> nominal (0 = ikut total). */
    fun submitDeposit(title: String, note: String?, selected: Map<Long, Double>, evidenceUri: Uri?) {
        if (title.isBlank()) { _message.value = "Beri judul slip setoran (mis. Setoran RT 03)."; return }
        if (selected.isEmpty()) { _message.value = "Pilih minimal satu tagihan untuk disetor."; return }
        val items = selected.map { (id, amount) -> id to amount }
        viewModelScope.launch {
            _busy.value = true
            repository.submitDeposit(title, note, items, evidenceUri)
                .onSuccess {
                    _lastDeposit.value = it
                    _message.value = "Setoran tercatat, menunggu ACC bendahara."
                }
                .onFailure { _message.value = it.message ?: "Setoran gagal dikirim. Coba lagi." }
            _busy.value = false
        }
    }

    private fun <T> runList(
        block: suspend () -> Result<List<T>>,
        assign: (List<T>) -> Unit,
    ) = viewModelScope.launch {
        _busy.value = true
        block().onSuccess { assign(it) }.onFailure { _message.value = it.message }
        _busy.value = false
    }

    fun consumeMessage() { _message.value = null }
    fun consumeClaim() { _lastClaim.value = null }
    fun consumeDeposit() { _lastDeposit.value = null }
}
