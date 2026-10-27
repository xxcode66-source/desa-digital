package com.desa.digital.core.theme

import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.darkColorScheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable

private val LightColors = lightColorScheme(
    primary = DesaGreen,
    onPrimary = androidx.compose.ui.graphics.Color.White,
    primaryContainer = DesaGreenLight,
    secondary = DesaAmber,
    surface = SurfaceTint,
)

private val DarkColors = darkColorScheme(
    primary = DesaGreenLight,
    onPrimary = androidx.compose.ui.graphics.Color.Black,
    secondary = DesaAmberDark,
    surface = androidx.compose.ui.graphics.Color(0xFF111B11),
    onSurface = OnSurfaceDark,
)

/**
 * Tema Material 3 aplikasi dengan dukungan dark mode (PEMBANGUNAN.md bagian 6).
 * Dynamic color sengaja tidak dipakai agar identitas warna desa tetap konsisten.
 */
@Composable
fun DesaDigitalTheme(
    darkTheme: Boolean = isSystemInDarkTheme(),
    content: @Composable () -> Unit,
) {
    MaterialTheme(
        colorScheme = if (darkTheme) DarkColors else LightColors,
        typography = Typography,
        content = content,
    )
}
