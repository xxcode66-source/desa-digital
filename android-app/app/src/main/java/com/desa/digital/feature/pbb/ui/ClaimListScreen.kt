package com.desa.digital.feature.pbb.ui

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.Add
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import com.desa.digital.core.network.dto.PbbClaimDto
import com.desa.digital.feature.pbb.PbbViewModel

/** Riwayat klaim kepemilikan NOP milik warga + status verifikasi petugas (PEMBANGUNAN.md 4.3). */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ClaimListScreen(
    onBack: () -> Unit,
    onNewClaim: () -> Unit,
    viewModel: PbbViewModel = hiltViewModel(),
) {
    val claims by viewModel.myClaims.collectAsState()
    val busy by viewModel.busy.collectAsState()
    val message by viewModel.message.collectAsState()
    val snackbar = remember { SnackbarHostState() }

    LaunchedEffect(Unit) { viewModel.loadMyClaims() }
    LaunchedEffect(message) { message?.let { snackbar.showSnackbar(it); viewModel.consumeMessage() } }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Riwayat Klaim") },
                navigationIcon = { IconButton(onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, "Kembali") } },
            )
        },
        snackbarHost = { SnackbarHost(snackbar) },
        floatingActionButton = {
            FloatingActionButton(onClick = onNewClaim) { Icon(Icons.Filled.Add, "Ajukan klaim") }
        },
    ) { padding ->
        Column(Modifier.padding(padding).padding(16.dp)) {
            if (busy && claims.isEmpty()) {
                CircularProgressIndicator(modifier = Modifier.align(Alignment.CenterHorizontally))
            } else if (claims.isEmpty()) {
                Text("Belum ada klaim yang Anda kirim.", style = MaterialTheme.typography.bodyLarge)
            } else {
                LazyColumn(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    items(claims, key = { it.id }) { c -> ClaimCard(c) }
                }
            }
        }
    }
}

@Composable
private fun ClaimCard(c: PbbClaimDto) {
    Card(Modifier.fillMaxWidth()) {
        Column(Modifier.padding(14.dp)) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Text("NOP ${c.nop ?: "-"}", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)
                PbbStatusChip(c.statusLabel, c.status)
            }
            Text(c.wpName ?: "-", style = MaterialTheme.typography.bodyMedium)
            Text(c.address ?: "-", style = MaterialTheme.typography.bodySmall)
            if (!c.reviewNote.isNullOrBlank()) {
                Spacer(Modifier.height(6.dp))
                Text("Catatan petugas: ${c.reviewNote}", style = MaterialTheme.typography.bodyMedium)
            }
            if (c.status == "approved") {
                Spacer(Modifier.height(6.dp))
                Text("Sudah atas nama Anda. Lihat detail di menu NOP Saya.", style = MaterialTheme.typography.bodySmall)
            }
        }
    }
}
