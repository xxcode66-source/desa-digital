package com.desa.digital.core.data

import android.content.Context
import androidx.core.content.edit
import dagger.hilt.android.qualifiers.ApplicationContext
import javax.inject.Inject
import javax.inject.Singleton

/**
 * Penyimpanan token Sanctum sederhana (Fase 0 fondasi).
 * CATATAN KEAMANAN: untuk produksi pindahkan ke EncryptedSharedPreferences / Keystore
 * (PEMBANGUNAN.md bagian 5). Disengaja sederhana agar fokus Fase 0 tetap ke struktur.
 */
@Singleton
class TokenStore @Inject constructor(
    @ApplicationContext private val context: Context,
) {
    private val prefs = context.getSharedPreferences("desa_session", Context.MODE_PRIVATE)

    var token: String?
        get() = prefs.getString(KEY_TOKEN, null)
        set(value) = prefs.edit {
            if (value == null) remove(KEY_TOKEN) else putString(KEY_TOKEN, value)
        }

    val authorizationHeader: String? get() = token?.let { "Bearer $it" }

    fun clear() = prefs.edit { clear() }

    private companion object { const val KEY_TOKEN = "auth_token" }
}
