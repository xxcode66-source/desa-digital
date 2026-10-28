package com.desa.digital.core.security

import androidx.biometric.BiometricManager
import androidx.biometric.BiometricManager.Authenticators.BIOMETRIC_STRONG
import androidx.biometric.BiometricManager.Authenticators.DEVICE_CREDENTIAL
import androidx.biometric.BiometricPrompt
import androidx.core.content.ContextCompat
import androidx.fragment.app.FragmentActivity

/**
 * Pembantu biometrik (Fase 1). Memakai BIOMETRIC_STRONG | DEVICE_CREDENTIAL supaya
 * pengguna tetap bisa masuk dengan PIN kunci perangkat bila sidik jari/wajah gagal.
 */
object BiometricHelper {

    private const val AUTHENTICATORS = BIOMETRIC_STRONG or DEVICE_CREDENTIAL

    fun isAvailable(context: android.content.Context): Boolean =
        BiometricManager.from(context)
            .canAuthenticate(AUTHENTICATORS) == BiometricManager.BIOMETRIC_SUCCESS

    fun prompt(
        activity: FragmentActivity,
        title: String = "Buka kunci Desa Digital",
        subtitle: String = "Gunakan sidik jari atau PIN perangkat",
        onSuccess: () -> Unit,
        onError: (String) -> Unit = {},
    ) {
        val callback = object : BiometricPrompt.AuthenticationCallback() {
            override fun onAuthenticationSucceeded(result: BiometricPrompt.AuthenticationResult) {
                onSuccess()
            }

            override fun onAuthenticationError(errorCode: Int, errString: CharSequence) {
                // Error pengguna (dibatalkan/tidak tersedia) jangan dianggap gagal total.
                onError(errString.toString())
            }
        }
        val prompt = BiometricPrompt(activity, ContextCompat.getMainExecutor(activity), callback)
        val info = BiometricPrompt.PromptInfo.Builder()
            .setTitle(title)
            .setSubtitle(subtitle)
            .setAllowedAuthenticators(AUTHENTICATORS)
            .build()
        prompt.authenticate(info)
    }
}
