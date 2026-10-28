package com.desa.digital.core.session

import androidx.lifecycle.ViewModel
import com.desa.digital.core.data.TokenStore
import com.desa.digital.core.security.BiometricHelper
import com.desa.digital.core.security.PinManager
import dagger.hilt.android.lifecycle.HiltViewModel
import dagger.hilt.android.qualifiers.ApplicationContext
import android.content.Context
import javax.inject.Inject

/**
 * Menentukan rute awal aplikasi & mengelola gerbang kunci lokal (Fase 1).
 * - Belum login            -> ONBOARDING
 * - Login, belum ada PIN    -> CREATE_PIN (buat PIN dulu)
 * - Login, PIN sudah ada    -> LOCK (buka kunci)
 */
@HiltViewModel
class SessionViewModel @Inject constructor(
    @ApplicationContext private val context: Context,
    private val tokenStore: TokenStore,
    private val pinManager: PinManager,
) : ViewModel() {

    fun initialRoute(): String = when {
        tokenStore.token == null -> ONBOARDING
        !pinManager.hasPin() -> CREATE_PIN
        else -> LOCK
    }

    fun hasPin(): Boolean = pinManager.hasPin()

    fun isBiometricEnabled(): Boolean =
        pinManager.isBiometricEnabled() && BiometricHelper.isAvailable(context)

    fun biometricAvailable(): Boolean = BiometricHelper.isAvailable(context)

    fun setPin(pin: String): Boolean = runCatching { pinManager.setPin(pin); true }
        .getOrElse { false }

    fun verifyPin(pin: String): Boolean = pinManager.verifyPin(pin)

    fun setBiometric(enabled: Boolean) = pinManager.setBiometricEnabled(enabled)

    companion object {
        const val ONBOARDING = "onboarding"
        const val LOGIN = "login"
        const val REGISTER = "register"
        const val CREATE_PIN = "create_pin"
        const val LOCK = "lock"
        const val HOME = "home"
        const val PROFILE = "profile"
    }
}
