package com.desa.digital.feature.auth

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.desa.digital.data.repository.AuthRepository
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import javax.inject.Inject

/** Fase tampilan registrasi dikendalikan langsung oleh [RegisterUiState]. */
sealed interface RegisterUiState {
    data object Idle : RegisterUiState
    data object Loading : RegisterUiState
    data class SentOtp(val phone: String, val debugOtp: String?) : RegisterUiState
    data class Success(val name: String) : RegisterUiState
    data class Error(val message: String) : RegisterUiState
}

/**
 * Registrasi warga (PEMBANGUNAN.md §4.1): isi formulir -> kirim OTP -> masukkan OTP ->
 * akun dibuat (status pending). Data formulir ditahan di VM agar kedua fase dalam
 * satu layar ini bisa berbagi tanpa mengirim ulang lewat argumen navigasi.
 */
@HiltViewModel
class RegisterViewModel @Inject constructor(
    private val repository: AuthRepository,
) : ViewModel() {

    private val _state = MutableStateFlow<RegisterUiState>(RegisterUiState.Idle)
    val state: StateFlow<RegisterUiState> = _state.asStateFlow()

    // Data formulir sementara (dipakai lagi saat submit register setelah OTP valid).
    private var villageId: Long = 1L
    private var nik: String = ""
    private var name: String = ""
    private var phone: String = ""
    private var password: String = ""
    private var rt: String? = null
    private var rw: String? = null

    fun sendOtp(
        villageId: String, nik: String, name: String, phone: String,
        password: String, confirm: String, rt: String, rw: String, consent: Boolean,
    ) {
        val errors = mutableListOf<String>()
        this.villageId = villageId.toLongOrNull() ?: 0L
        this.nik = nik.trim(); this.name = name.trim(); this.phone = phone.trim()
        this.password = password; this.rt = rt.trim().ifBlank { null }; this.rw = rw.trim().ifBlank { null }

        if (this.villageId <= 0L) errors += "Kode desa tidak valid."
        if (this.nik.length != 16 || this.nik.any { !it.isDigit() }) errors += "NIK harus 16 digit angka."
        if (this.name.isBlank()) errors += "Nama wajib diisi."
        if (!this.phone.matches(Regex("^(\\+?62|0)8[1-9][0-9]{6,11}\$"))) errors += "Format nomor HP tidak valid."
        if (this.password.length < 8) errors += "Password minimal 8 karakter."
        if (this.password != confirm) errors += "Konfirmasi password tidak sama."
        if (!consent) errors += "Setujui persetujuan penggunaan data."

        if (errors.isNotEmpty()) {
            _state.value = RegisterUiState.Error(errors.joinToString(" "))
            return
        }

        _state.value = RegisterUiState.Loading
        viewModelScope.launch {
            _state.value = repository.sendRegisterOtp(this@RegisterViewModel.phone)
                .fold(
                    onSuccess = { RegisterUiState.SentOtp(this@RegisterViewModel.phone, it) },
                    onFailure = { RegisterUiState.Error(it.message ?: "Gagal mengirim kode. Coba lagi.") },
                )
        }
    }

    fun submitRegister(otpCode: String) {
        if (otpCode.length != 6) {
            _state.value = RegisterUiState.Error("Kode verifikasi harus 6 digit.")
            return
        }
        _state.value = RegisterUiState.Loading
        viewModelScope.launch {
            _state.value = repository.register(
                villageId = villageId, nik = nik, name = name, phone = phone,
                password = password, rt = rt, rw = rw, otpCode = otpCode,
            ).fold(
                onSuccess = { RegisterUiState.Success(it.name) },
                onFailure = { RegisterUiState.Error(it.message ?: "Pendaftaran gagal. Periksa data & koneksi.") },
            )
        }
    }

    fun backToForm() {
        _state.value = RegisterUiState.Idle
    }
}
