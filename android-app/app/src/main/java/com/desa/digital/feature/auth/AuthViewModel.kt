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

sealed interface AuthUiState {
    data object Idle : AuthUiState
    data object Loading : AuthUiState
    data class Success(val name: String) : AuthUiState
    data class Error(val message: String) : AuthUiState
}

/**
 * ViewModel login fondasi (Fase 0). Onboarding, OTP, PIN/biometrik, dan verifikasi
 * akun dibangun penuh pada Fase 1 sesuai PEMBANGUNAN.md bagian 4.1.
 */
@HiltViewModel
class AuthViewModel @Inject constructor(
    private val repository: AuthRepository,
) : ViewModel() {

    private val _state = MutableStateFlow<AuthUiState>(AuthUiState.Idle)
    val state: StateFlow<AuthUiState> = _state.asStateFlow()

    fun login(phone: String, password: String) {
        if (phone.isBlank() || password.isBlank()) {
            _state.value = AuthUiState.Error("Nomor HP dan password wajib diisi.")
            return
        }
        _state.value = AuthUiState.Loading
        viewModelScope.launch {
            _state.value = repository.login(phone, password)
                .fold(
                    onSuccess = { AuthUiState.Success(it.name) },
                    onFailure = { AuthUiState.Error("Gagal masuk. Periksa koneksi & data kamu.") },
                )
        }
    }
}
