package com.desa.digital

import android.os.Bundle
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.fragment.app.FragmentActivity
import com.desa.digital.core.ui.AppNavigation
import com.desa.digital.core.theme.DesaDigitalTheme
import dagger.hilt.android.AndroidEntryPoint

/**
 * FragmentActivity (bukan ComponentActivity) agar BiometricPrompt (Fase 1) bisa dipakai;
 * FragmentActivity tetap mendukung setContent & enableEdgeToEdge karena subclass ComponentActivity.
 */
@AndroidEntryPoint
class MainActivity : FragmentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        enableEdgeToEdge()
        super.onCreate(savedInstanceState)
        setContent {
            DesaDigitalTheme {
                AppNavigation()
            }
        }
    }
}
