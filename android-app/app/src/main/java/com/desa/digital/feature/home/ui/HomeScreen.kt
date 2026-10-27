package com.desa.digital.feature.home.ui

import androidx.compose.foundation.layout.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp

/**
 * Beranda Fase 0: kerangka dengan menu besar berikon (PEMBANGUNAN.md bagian 6).
 * Item Surat/PBB/UMKM/Pengaduan sengaja ditampilkan non-aktif — layarnya baru
 * dibangun pada Fase 2-5, sesuai aturan satu fase per sesi.
 */
@Composable
fun HomeScreen(userName: String = "Warga") {
    Surface(modifier = Modifier.fillMaxSize()) {
        Column(modifier = Modifier.padding(24.dp)) {
            Text("Halo, $userName", style = MaterialTheme.typography.headlineMedium)
            Spacer(Modifier.height(4.dp))
            Text("Selamat datang di Desa Digital.", style = MaterialTheme.typography.bodyLarge)
            Spacer(Modifier.height(24.dp))

            val menus = listOf(
                "Ajukan Surat" to false,
                "Cek & Bayar PBB" to false,
                "Pasar UMKM" to false,
                "Pengaduan" to false,
            )
            menus.forEach { (label, enabled) ->
                FilledTonalButton(
                    onClick = { /* navigasi layar fitur menyusul pada fasenya */ },
                    enabled = enabled,
                    modifier = Modifier.fillMaxWidth().height(64.dp),
                ) { Text(label, style = MaterialTheme.typography.labelLarge) }
                Spacer(Modifier.height(12.dp))
            }
        }
    }
}
