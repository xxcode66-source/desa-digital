package com.desa.digital.feature.umkm.ui

import androidx.compose.foundation.layout.*
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp

/**
 * Hub modul Marketplace UMKM Fase 5 (PEMBANGUNAN.md 4.4 & 6). Dua pintu: warga menjelajahi
 * toko/produk desa (beli lewat WhatsApp), pelaku UMKM mengelola tokonya. Tidak ada pembayaran
 * di aplikasi — transaksi disepakati langsung dengan penjual.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun UmkmMenuScreen(
    onBack: () -> Unit,
    onBrowse: () -> Unit,
    onMyShop: () -> Unit,
) {
    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Pasar UMKM Desa") },
                navigationIcon = { IconButton(onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, "Kembali") } },
            )
        },
    ) { padding ->
        Column(Modifier.padding(padding).padding(20.dp)) {
            Text(
                "Temukan usaha warga desa — makanan, jasa, kerajinan, dan lainnya. " +
                    "Minat beli langsung dikomunikasikan ke penjual lewat WhatsApp. " +
                    "Pelaku UMKM bisa mendaftarkan toko dan mengelola katalog produknya.",
                style = MaterialTheme.typography.bodyMedium,
            )
            Spacer(Modifier.height(20.dp))

            MenuButton(Icons.Filled.Search, "Jelajahi Marketplace", "Cari toko & produk di desa Anda", onClick = onBrowse)
            MenuButton(Icons.Filled.Person, "Toko Saya", "Daftarkan/ubah toko & kelola produk", onClick = onMyShop)
        }
    }
}

@Composable
private fun MenuButton(icon: androidx.compose.ui.graphics.vector.ImageVector, title: String, subtitle: String, onClick: () -> Unit) {
    FilledTonalButton(
        onClick = onClick,
        modifier = Modifier.fillMaxWidth().height(72.dp),
        contentPadding = PaddingValues(horizontal = 16.dp),
    ) {
        Icon(icon, contentDescription = null)
        Spacer(Modifier.width(16.dp))
        Column(horizontalAlignment = Alignment.Start) {
            Text(title, style = MaterialTheme.typography.titleMedium)
            Text(subtitle, style = MaterialTheme.typography.bodySmall)
        }
    }
    Spacer(Modifier.height(12.dp))
}
