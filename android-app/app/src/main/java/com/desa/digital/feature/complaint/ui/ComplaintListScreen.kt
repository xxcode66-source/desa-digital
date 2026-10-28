package com.desa.digital.feature.complaint.ui

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
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import com.desa.digital.feature.complaint.ComplaintViewModel

/**
 * Daftar pengaduan warga (PEMBANGUNAN.md 4.5): lacak status tiap laporan, terbaru dulu.
 * Kartu dibuka untuk kronologi & balasan; FAB menuju formulir pengajuan.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ComplaintListScreen(
    onBack: () -> Unit,
    onNewComplaint: () -> Unit,
    onOpenDetail: (Long) -> Unit,
    viewModel: ComplaintViewModel = hiltViewModel(),
) {
    val complaints by viewModel.complaints.collectAsState()
    val busy by viewModel.busy.collectAsState()
    val message by viewModel.message.collectAsState()
    val snackbar = remember { SnackbarHostState() }

    LaunchedEffect(Unit) { viewModel.loadComplaints() }
    LaunchedEffect(message) {
        message?.let { snackbar.showSnackbar(it); viewModel.consumeMessage() }
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Pengaduan Saya") },
                navigationIcon = { IconButton(onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, "Kembali") } },
            )
        },
        snackbarHost = { SnackbarHost(snackbar) },
        floatingActionButton = {
            FloatingActionButton(onClick = onNewComplaint) { Icon(Icons.Filled.Add, "Buat laporan") }
        },
    ) { padding ->
        Column(Modifier.padding(padding).padding(16.dp)) {
            if (busy && complaints.isEmpty()) {
                CircularProgressIndicator(modifier = Modifier.align(Alignment.CenterHorizontally))
            } else if (complaints.isEmpty()) {
                Text(
                    "Belum ada laporan yang Anda kirim.\nTap tombol + untuk melaporkan masalah di desa.",
                    style = MaterialTheme.typography.bodyLarge,
                )
            } else {
                LazyColumn(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    items(complaints, key = { it.id }) { c ->
                        Card(onClick = { onOpenDetail(c.id) }, modifier = Modifier.fillMaxWidth()) {
                            Column(Modifier.padding(14.dp)) {
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment = Alignment.CenterVertically,
                                ) {
                                    Text(c.title, style = MaterialTheme.typography.titleMedium)
                                    SuggestionChip(onClick = {}, label = {
                                        Text(c.statusLabel ?: c.status)
                                    })
                                }
                                Spacer(Modifier.height(6.dp))
                                val tgl = c.submittedAt?.substringBefore('T')?.replace('-', '.') ?: "-"
                                Text(
                                    "${c.categoryLabel ?: "Laporan"} · Diajukan $tgl",
                                    style = MaterialTheme.typography.bodyMedium,
                                )
                            }
                        }
                    }
                }
            }
        }
    }
}
