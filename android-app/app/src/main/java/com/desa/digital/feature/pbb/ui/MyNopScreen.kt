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
import com.desa.digital.core.network.dto.PbbMyObjectDto
import com.desa.digital.feature.pbb.PbbViewModel

/**
 * "NOP saya" (PEMBANGUNAN.md 4.3): daftar objek pajak yang klaim kepemilikannya sudah disetujui
 * petugas, lengkap dengan tunggakan per tahun. Kartu dapat dibuka untuk rinci tagihan.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun MyNopScreen(
    onBack: () -> Unit,
    onNewClaim: () -> Unit,
    viewModel: PbbViewModel = hiltViewModel(),
) {
    val objects by viewModel.myObjects.collectAsState()
    val busy by viewModel.busy.collectAsState()
    val message by viewModel.message.collectAsState()
    val snackbar = remember { SnackbarHostState() }

    LaunchedEffect(Unit) { viewModel.loadMyObjects() }
    LaunchedEffect(message) { message?.let { snackbar.showSnackbar(it); viewModel.consumeMessage() } }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("NOP Saya") },
                navigationIcon = { IconButton(onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, "Kembali") } },
            )
        },
        snackbarHost = { SnackbarHost(snackbar) },
        floatingActionButton = {
            FloatingActionButton(onClick = onNewClaim) { Icon(Icons.Filled.Add, "Ajukan klaim") }
        },
    ) { padding ->
        Column(Modifier.padding(padding).padding(16.dp)) {
            if (busy && objects.isEmpty()) {
                CircularProgressIndicator(modifier = Modifier.align(Alignment.CenterHorizontally))
            } else if (objects.isEmpty()) {
                Text(
                    "Belum ada NOP atas nama Anda.\nTekan tombol + untuk mengajukan klaim kepemilikan.",
                    style = MaterialTheme.typography.bodyLarge,
                )
            } else {
                LazyColumn(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    items(objects, key = { it.id }) { o -> ObjectCard(o) }
                }
            }
        }
    }
}

@Composable
private fun ObjectCard(o: PbbMyObjectDto) {
    var expanded by remember { mutableStateOf(false) }
    Card(onClick = { expanded = !expanded }, modifier = Modifier.fillMaxWidth()) {
        Column(Modifier.padding(14.dp)) {
            Text("NOP ${o.nop}", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)
            Text(o.wpName ?: "-", style = MaterialTheme.typography.bodyMedium)
            Text("${o.address ?: "-"} · RT ${o.rt ?: "-"}/${o.rw ?: "-"}", style = MaterialTheme.typography.bodySmall)
            Spacer(Modifier.height(8.dp))
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Text("Tunggakan", style = MaterialTheme.typography.labelLarge)
                Text(rupiah(o.arrearsTotal), style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)
            }
            if (expanded) {
                Spacer(Modifier.height(8.dp))
                HorizontalDivider()
                o.bills.forEach { b -> BillRow(b) }
            } else {
                Text("Tap untuk lihat tagihan per tahun", style = MaterialTheme.typography.bodySmall)
            }
        }
    }
}
