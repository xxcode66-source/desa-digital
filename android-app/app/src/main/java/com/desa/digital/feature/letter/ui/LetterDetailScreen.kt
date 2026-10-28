package com.desa.digital.feature.letter.ui

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import com.desa.digital.core.network.dto.LetterDetailDto
import com.desa.digital.feature.letter.LetterViewModel

/**
 * Lacak satu surat (PEMBANGUNAN.md 4.2): status berjalan, nomor & tanggal,
 * isi formulir, riwayat lengkap dengan catatan petugas, dan tombol perbaikan
 * ketika status perlu_perbaikan.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun LetterDetailScreen(
    letterId: Long,
    onBack: () -> Unit,
    onRepair: (Long) -> Unit,
    viewModel: LetterViewModel = hiltViewModel(),
) {
    val detail by viewModel.detail.collectAsState()
    val busy by viewModel.busy.collectAsState()
    val message by viewModel.message.collectAsState()
    val snackbar = remember { SnackbarHostState() }

    LaunchedEffect(letterId) { viewModel.loadDetail(letterId) }
    LaunchedEffect(message) {
        message?.let { snackbar.showSnackbar(it); viewModel.consumeMessage() }
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Status Surat") },
                navigationIcon = { IconButton(onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, "Kembali") } },
            )
        },
        snackbarHost = { SnackbarHost(snackbar) },
    ) { padding ->
        val d = detail
        Column(
            Modifier
                .padding(padding)
                .padding(20.dp)
                .fillMaxSize()
                .verticalScroll(rememberScrollState()),
        ) {
            if (d == null) {
                if (busy) CircularProgressIndicator() else Text("Memuat surat…")
            } else {
                StatusBanner(d)
                Spacer(Modifier.height(16.dp))

                if (d.editable) {
                    Button(
                        onClick = { onRepair(d.id) },
                        modifier = Modifier.fillMaxWidth().height(52.dp),
                    ) { Text("Perbaiki Surat") }
                    Spacer(Modifier.height(16.dp))
                }

                if (d.pdfUrl != null) {
                    OutlinedButton(
                        onClick = { viewModel.downloadAndOpenPdf(d.id) },
                        enabled = !busy,
                        modifier = Modifier.fillMaxWidth().height(52.dp),
                    ) { Text("Unduh / Lihat PDF Surat") }
                    Spacer(Modifier.height(16.dp))
                }

                Card(Modifier.fillMaxWidth()) {
                    Column(Modifier.padding(14.dp)) {
                        Text("Isi Surat", style = MaterialTheme.typography.titleMedium)
                        d.data.forEach { (key, value) ->
                            Spacer(Modifier.height(6.dp))
                            Text(
                                key.replace('_', ' ').replaceFirstChar { it.uppercase() },
                                style = MaterialTheme.typography.labelLarge,
                            )
                            Text(value.ifBlank { "-" }, style = MaterialTheme.typography.bodyMedium)
                        }
                        if (d.attachments.isNotEmpty()) {
                            Spacer(Modifier.height(10.dp))
                            Text("Lampiran (${d.attachments.size})", style = MaterialTheme.typography.labelLarge)
                            d.attachments.forEach { Text(it.name ?: "-", style = MaterialTheme.typography.bodySmall) }
                        }
                    }
                }

                Spacer(Modifier.height(16.dp))
                Card(Modifier.fillMaxWidth()) {
                    Column(Modifier.padding(14.dp)) {
                        Text("Riwayat", style = MaterialTheme.typography.titleMedium)
                        d.history.forEach { h ->
                            Spacer(Modifier.height(10.dp))
                            Column {
                                Text(h.toLabel ?: h.to ?: "-", style = MaterialTheme.typography.bodyLarge)
                                val waktu = (h.at?.substringBefore('.') ?: "-").replace('T', ' ')
                                Text(waktu, style = MaterialTheme.typography.bodySmall)
                                if (!h.note.isNullOrBlank()) {
                                    Text("Catatan petugas: ${h.note}", style = MaterialTheme.typography.bodySmall)
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun StatusBanner(d: LetterDetailDto) {
    val container = when (d.status) {
        "selesai" -> MaterialTheme.colorScheme.secondaryContainer
        "ditolak", "perlu_perbaikan" -> MaterialTheme.colorScheme.errorContainer
        else -> MaterialTheme.colorScheme.tertiaryContainer
    }
    Surface(color = container, shape = MaterialTheme.shapes.medium, modifier = Modifier.fillMaxWidth()) {
        Column(Modifier.padding(14.dp)) {
            Text(d.statusLabel ?: d.status, style = MaterialTheme.typography.titleMedium)
            if (d.number != null) Text("Nomor: ${d.number}", style = MaterialTheme.typography.bodyMedium)
            if (!d.note.isNullOrBlank() && (d.status == "perlu_perbaikan" || d.status == "ditolak")) {
                Text("Petugas: ${d.note}", style = MaterialTheme.typography.bodyMedium)
            }
        }
    }
}
