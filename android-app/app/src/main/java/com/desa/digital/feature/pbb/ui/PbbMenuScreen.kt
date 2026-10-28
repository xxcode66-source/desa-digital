package com.desa.digital.feature.pbb.ui

import androidx.compose.foundation.layout.*
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import com.desa.digital.feature.pbb.PbbViewModel

/**
 * Hub modul PBB Fase 4 (PEMBANGUNAN.md 4.3 & 6). Menu besar: cek NOP, NOP saya, ajukan klaim,
 * riwayat klaim — plus menu setoran yang hanya muncul untuk RT/kolektor. Tidak ada pembayaran
 * di aplikasi; semua status bersifat verifikasi internal desa.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun PbbMenuScreen(
    onBack: () -> Unit,
    onCekNop: () -> Unit,
    onMyNop: () -> Unit,
    onNewClaim: () -> Unit,
    onMyClaims: () -> Unit,
    onDeposit: () -> Unit,
    viewModel: PbbViewModel = hiltViewModel(),
) {
    val isCollector by viewModel.isCollector.collectAsState()

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("PBB Desa") },
                navigationIcon = { IconButton(onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, "Kembali") } },
            )
        },
    ) { padding ->
        Column(Modifier.padding(padding).padding(20.dp)) {
            Text(
                "Cek tagihan PBB, ajukan klaim kepemilikan, dan pantau NOP milik Anda. " +
                    "Pembayaran tetap dilakukan di kanal resmi desa/kantor pajak.",
                style = MaterialTheme.typography.bodyMedium,
            )
            Spacer(Modifier.height(20.dp))

            MenuButton(Icons.Filled.Search, "Cek NOP", "Cari tagihan pakai Nomor Objek Pajak", onClick = onCekNop)
            MenuButton(Icons.Filled.Home, "NOP Saya", "Objek pajak atas nama Anda & tunggakannya", onClick = onMyNop)
            MenuButton(Icons.Filled.Person, "Ajukan Klaim Kepemilikan", "Laporkan NOP adalah milik Anda + foto bukti", onClick = onNewClaim)
            MenuButton(Icons.Filled.List, "Riwayat Klaim", "Status verifikasi klaim yang Anda kirim", onClick = onMyClaims)

            if (isCollector) {
                MenuButton(Icons.Filled.Info, "Setoran PBB (RT/Kolektor)", "Pilih tagihan & setor ke bendahara", onClick = onDeposit)
            }
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
