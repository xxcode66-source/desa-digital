package com.desa.digital.core.security

import android.content.Context
import android.content.SharedPreferences
import androidx.core.content.edit
import androidx.security.crypto.EncryptedSharedPreferences
import androidx.security.crypto.MasterKey
import dagger.hilt.android.qualifiers.ApplicationContext
import java.security.SecureRandom
import javax.crypto.SecretKeyFactory
import javax.crypto.spec.PBEKeySpec
import javax.inject.Inject
import javax.inject.Singleton

/**
 * Kunci lokal (PEMBANGUNAN.md §5): PIN disimpan sebagai hash PBKDF2 + salt acak di
 * EncryptedSharedPreferences (terenkripsi via Android Keystore). PIN asli tidak pernah
 * disimpan. Biometrik hanyalah gerbang pembuka — statusnya juga dicatat di sini.
 */
@Singleton
class PinManager @Inject constructor(
    @ApplicationContext private val context: Context,
) {
    private val prefs: SharedPreferences by lazy {
        val masterKey = MasterKey.Builder(context)
            .setKeyScheme(MasterKey.KeyScheme.AES256_GCM)
            .build()
        EncryptedSharedPreferences.create(
            context,
            "desa_secure_prefs",
            masterKey,
            EncryptedSharedPreferences.PrefKeyEncryptionScheme.AES256_SIV,
            EncryptedSharedPreferences.PrefValueEncryptionScheme.AES256_GCM,
        )
    }

    fun hasPin(): Boolean = prefs.getString(KEY_HASH, null) != null

    fun isBiometricEnabled(): Boolean = prefs.getBoolean(KEY_BIOMETRIC, false)

    fun setBiometricEnabled(enabled: Boolean) = prefs.edit { putBoolean(KEY_BIOMETRIC, enabled) }

    fun setPin(pin: String) {
        require(pin.length == 6 && pin.all { it.isDigit() }) { "PIN harus 6 digit angka." }
        val salt = ByteArray(16).also { SecureRandom().nextBytes(it) }
        val hash = pbkdf2(pin, salt)
        prefs.edit {
            putString(KEY_SALT, android.util.Base64.encodeToString(salt, android.util.Base64.NO_WRAP))
            putString(KEY_HASH, android.util.Base64.encodeToString(hash, android.util.Base64.NO_WRAP))
        }
    }

    fun verifyPin(pin: String): Boolean {
        val saltB64 = prefs.getString(KEY_SALT, null) ?: return false
        val hashB64 = prefs.getString(KEY_HASH, null) ?: return false
        val salt = android.util.Base64.decode(saltB64, android.util.Base64.NO_WRAP)
        val expected = android.util.Base64.decode(hashB64, android.util.Base64.NO_WRAP)
        return pbkdf2(pin, salt).contentEquals(expected)
    }

    /** Ganti PIN lama (verifikasi dulu) — dipakai di layar pengaturan. */
    fun changePin(oldPin: String, newPin: String): Boolean {
        if (!verifyPin(oldPin)) return false
        setPin(newPin)
        return true
    }

    fun clear() = prefs.edit {
        remove(KEY_SALT); remove(KEY_HASH); remove(KEY_BIOMETRIC)
    }

    private fun pbkdf2(pin: String, salt: ByteArray): ByteArray {
        val spec = PBEKeySpec(pin.toCharArray(), salt, 120_000, 256)
        return SecretKeyFactory.getInstance("PBKDF2WithHmacSHA256")
            .generateSecret(spec).encoded
    }

    private companion object {
        const val KEY_SALT = "pin_salt"
        const val KEY_HASH = "pin_hash"
        const val KEY_BIOMETRIC = "biometric_enabled"
    }
}
