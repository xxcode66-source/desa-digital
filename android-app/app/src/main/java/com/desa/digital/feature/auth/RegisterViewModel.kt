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
    data class Success(val name: String) : RegisterUiState
    data class Error(val message: String) : RegisterUiState
}

/**
 * Registrasi warga (PEMBANGUNAN.md §4.1): isi formulir -> submit -> akun dibuat
 * (status pending). Tidak ada OTP/SMS pihak ketiga; verifikasi identitas dilakukan
 * operator di panel. Setelah submit berhasil warga langsung memegang token.
 */
@HiltViewModel
class RegisterViewModel @Inject constructor(
    private val repository: AuthRepository,
) : ViewModel() {

    private val _state = MutableStateFlow<RegisterUiState>(RegisterUiState.Idle)
    val state: StateFlow<RegisterUiState> = _state.asStateFlow()

    fun submit(
        villageId: String, nik: String, name: String, phone: String,
        password: String, confirm: String, rt: String, rw: String, consent: Boolean,
    ) {
        val errors = mutableListOf<String>()
        val vid = villageId.toLongOrNull() ?: 0L
        val nikT = nik.trim(); val nameT = name.trim(); val phoneT = phone.trim()

        if (vid <= 0L) errors += "Kode desa tidak valid."
        if (nikT.length != 16 || nikT.any { !it.isDigit() }) errors += "NIK harus 16 digit angka."
        if (nameT.isBlank()) errors += "Nama wajib diisi."
        if (!phoneT.matches(Regex("^(\\+?62|0)8[1-9][0-9]{6,11}\$"))) errors += "Format nomor HP tidak valid."
        if (password.length < 8) errors += "Password minimal 8 karakter."
        if (password != confirm) errors += "Konfirmasi password tidak sama."
        if (!consent) errors += "Setujui persetujuan penggunaan data."

        if (errors.isNotEmpty()) {
            _state.value = RegisterUiState.Error(errors.joinToString(" "))
            return
        }

        _state.value = RegisterUiState.Loading
        viewModelScope.launch {
            _state.value = repository.register(
                villageId = vid, nik = nikT, name = nameT, phone = phoneT,
                password = password, rt = rt.trim().ifBlank { null }, rw = rw.trim().ifBlank { null },
            ).fold(
                onSuccess = { RegisterUiState.Success(it.name) },
                onFailure = { RegisterUiState.Error(it.message ?: "Pendaftaran gagal. Periksa data & koneksi.") },
            )
        }
    }
}
