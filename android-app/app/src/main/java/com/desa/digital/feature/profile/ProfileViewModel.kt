package com.desa.digital.feature.profile

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.desa.digital.core.network.dto.UserDto
import com.desa.digital.core.security.PinManager
import com.desa.digital.data.repository.AuthRepository
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import javax.inject.Inject

sealed interface ProfileUiState {
    data object Loading : ProfileUiState
    data class Loaded(val user: UserDto) : ProfileUiState
    data class Error(val message: String) : ProfileUiState
}

/** Profil & status verifikasi warga + pengaturan kunci lokal (PIN/biometrik). */
@HiltViewModel
class ProfileViewModel @Inject constructor(
    private val repository: AuthRepository,
    val pinManager: PinManager,
) : ViewModel() {

    private val _state = MutableStateFlow<ProfileUiState>(ProfileUiState.Loading)
    val state: StateFlow<ProfileUiState> = _state.asStateFlow()

    private val _loggedOut = MutableStateFlow(false)
    val loggedOut: StateFlow<Boolean> = _loggedOut.asStateFlow()

    init { refresh() }

    fun refresh() {
        _state.value = ProfileUiState.Loading
        viewModelScope.launch {
            _state.value = repository.me().fold(
                onSuccess = { ProfileUiState.Loaded(it) },
                onFailure = { ProfileUiState.Error(it.message ?: "Gagal memuat profil.") },
            )
        }
    }

    fun logout(all: Boolean) {
        viewModelScope.launch {
            repository.logout(all)
            pinManager.clear()
            _loggedOut.value = true
        }
    }
}
