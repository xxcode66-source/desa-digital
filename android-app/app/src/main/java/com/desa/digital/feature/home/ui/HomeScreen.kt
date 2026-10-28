package com.desa.digital.feature.home.ui

import androidx.compose.foundation.layout.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import com.desa.digital.feature.profile.ProfileUiState
import com.desa.digital.feature.profile.ProfileViewModel

/**
 * Beranda Fase 1+2: sambutan + ringkas status verifikasi, menu besar berikon (PEMBANGUNAN.md §6).
 * Surat sudah aktif (Fase 2); PBB/UMKM/Pengaduan sengaja non-aktif — dibangun pada Fase 3-5
 * (aturan satu fase per sesi).
 */
@Composable
fun HomeScreen(
    onOpenProfile: () -> Unit,
    onOpenLetters: () -> Unit = {},
    viewModel: ProfileViewModel = hiltViewModel(),
) {
    val state by viewModel.state.collectAsState()
    val userName = (state as? ProfileUiState.Loaded)?.user?.name ?: "Warga"
    val status = (state as? ProfileUiState.Loaded)?.user?.status

    Surface(modifier = Modifier.fillMaxSize()) {
        Column(modifier = Modifier.padding(24.dp)) {
            Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                Column {
                    Text("Halo, $userName", style = MaterialTheme.typography.headlineMedium)
                    Spacer(Modifier.height(4.dp))
                    Text("Selamat datang di Desa Digital.", style = MaterialTheme.typography.bodyLarge)
                }
            }
            Spacer(Modifier.height(12.dp))

            val banner = when (status) {
                "verified" -> "Akun terverifikasi"
                "rejected" -> "Verifikasi ditolak — hubungi kantor desa"
                "pending" -> "Menunggu verifikasi operator"
                else -> null
            }
            if (banner != null) {
                val container = when (status) {
                    "verified" -> MaterialTheme.colorScheme.secondaryContainer
                    "rejected" -> MaterialTheme.colorScheme.errorContainer
                    else -> MaterialTheme.colorScheme.tertiaryContainer
                }
                Surface(color = container, shape = MaterialTheme.shapes.medium, modifier = Modifier.fillMaxWidth()) {
                    Text(banner, style = MaterialTheme.typography.labelLarge, modifier = Modifier.padding(12.dp))
                }
                Spacer(Modifier.height(20.dp))
            }

            val menus = listOf(
                "Ajukan Surat" to true,
                "Cek & Bayar PBB" to false,
                "Pasar UMKM" to false,
                "Pengaduan" to false,
            )
            menus.forEach { (label, enabled) ->
                FilledTonalButton(
                    onClick = { if (label == "Ajukan Surat") onOpenLetters() },
                    enabled = enabled,
                    modifier = Modifier.fillMaxWidth().height(64.dp),
                ) { Text(label, style = MaterialTheme.typography.labelLarge) }
                Spacer(Modifier.height(12.dp))
            }

            Spacer(Modifier.height(8.dp))
            OutlinedButton(onClick = onOpenProfile, modifier = Modifier.fillMaxWidth().height(56.dp)) {
                Text("Profil & Status Verifikasi")
            }
        }
    }
}
