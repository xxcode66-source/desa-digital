package com.desa.digital.feature.auth.ui

import androidx.compose.foundation.layout.*
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp

/** Layar pembuka ramah warga (PEMBANGUNAN.md §6): jelas, kontras, bahasa sederhana. */
@Composable
fun OnboardingScreen(
    onLogin: () -> Unit,
    onRegister: () -> Unit,
) {
    Surface(modifier = Modifier.fillMaxSize()) {
        Column(
            modifier = Modifier.fillMaxSize().padding(28.dp),
            verticalArrangement = Arrangement.Center,
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            Text("Desa Digital", style = MaterialTheme.typography.headlineLarge)
            Spacer(Modifier.height(12.dp))
            Text(
                "Layanan desa dalam genggaman.\nAjukan surat, pantau PBB, dan pengaduan — semua di satu tempat.",
                style = MaterialTheme.typography.bodyLarge,
                textAlign = TextAlign.Center,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
            Spacer(Modifier.height(40.dp))
            Button(
                onClick = onRegister,
                modifier = Modifier.fillMaxWidth().height(56.dp),
            ) { Text("Daftar Akun Baru", style = MaterialTheme.typography.labelLarge) }
            Spacer(Modifier.height(12.dp))
            OutlinedButton(
                onClick = onLogin,
                modifier = Modifier.fillMaxWidth().height(56.dp),
            ) { Text("Sudah Punya Akun — Masuk") }
        }
    }
}
