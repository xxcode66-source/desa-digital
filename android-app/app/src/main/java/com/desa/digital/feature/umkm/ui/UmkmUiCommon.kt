package com.desa.digital.feature.umkm.ui

import android.content.ActivityNotFoundException
import android.content.Intent
import android.net.Uri
import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.ui.platform.LocalContext

/**
 * Buka tautan eksternal (mis. hasil "Chat WhatsApp" wa.me dari server) memakai aplikasi/
 * peramban yang terpasang. Ditangkap rapi bila tak ada aplikasi yang mampu membukanya.
 */
@Composable
fun rememberOpenUrl(): (String) -> Boolean {
    val context = LocalContext.current
    return remember(context) {
        { url ->
            try {
                context.startActivity(
                    Intent(Intent.ACTION_VIEW, Uri.parse(url)).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK),
                )
                true
            } catch (_: ActivityNotFoundException) {
                false
            }
        }
    }
}
