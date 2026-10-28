package com.desa.digital.feature.complaint.ui

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.automirrored.filled.Send
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import com.desa.digital.core.network.dto.ComplaintDetailDto
import com.desa.digital.feature.complaint.ComplaintViewModel

/**
 * Lacak satu pengaduan (PEMBANGUNAN.md 4.5): status berjalan, isi laporan & foto bukti,
 * kronologi (perubahan status + balasan petugas), dan kolom balasan selama laporan
 * masih terbuka (belum selesai/ditolak).
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ComplaintDetailScreen(
    complaintId: Long,
    onBack: () -> Unit,
    viewModel: ComplaintViewModel = hiltViewModel(),
) {
    val detail by viewModel.detail.collectAsState()
    val busy by viewModel.busy.collectAsState()
    val message by viewModel.message.collectAsState()
    val snackbar = remember { SnackbarHostState() }
    var reply by remember { mutableStateOf("") }

    LaunchedEffect(complaintId) { viewModel.loadDetail(complaintId) }
    LaunchedEffect(message) {
        message?.let { snackbar.showSnackbar(it); viewModel.consumeMessage() }
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Status Laporan") },
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
                if (busy) CircularProgressIndicator() else Text("Memuat laporan…")
            } else {
                StatusBanner(d)
                Spacer(Modifier.height(16.dp))

                Card(Modifier.fillMaxWidth()) {
                    Column(Modifier.padding(14.dp)) {
                        Text(d.title, style = MaterialTheme.typography.titleMedium)
                        Spacer(Modifier.height(6.dp))
                        Text("${d.categoryLabel ?: d.category}", style = MaterialTheme.typography.labelLarge)
                        Spacer(Modifier.height(8.dp))
                        Text(d.description, style = MaterialTheme.typography.bodyMedium)
                        if (!d.location.isNullOrBlank()) {
                            Spacer(Modifier.height(8.dp))
                            Text("Lokasi: ${d.location}", style = MaterialTheme.typography.bodyMedium)
                        }
                        if (d.isAnonymous) {
                            Spacer(Modifier.height(6.dp))
                            Text("Dikirim anonim (nama Anda tak tampil di papan publik).", style = MaterialTheme.typography.bodySmall)
                        }
                        if (d.photos.isNotEmpty()) {
                            Spacer(Modifier.height(10.dp))
                            Text("Foto bukti (${d.photos.size})", style = MaterialTheme.typography.labelLarge)
                            d.photos.forEach { Text(it.name ?: "-", style = MaterialTheme.typography.bodySmall) }
                        }
                    }
                }

                Spacer(Modifier.height(16.dp))
                Card(Modifier.fillMaxWidth()) {
                    Column(Modifier.padding(14.dp)) {
                        Text("Kronologi", style = MaterialTheme.typography.titleMedium)
                        d.timeline.forEach { t ->
                            Spacer(Modifier.height(10.dp))
                            Column {
                                val judul = t.toLabel?.let { "Status: $it" } ?: (t.author ?: "Petugas")
                                Text(judul, style = MaterialTheme.typography.bodyLarge)
                                val waktu = (t.at?.substringBefore('.') ?: "-").replace('T', ' ')
                                Text(waktu, style = MaterialTheme.typography.bodySmall)
                                if (!t.body.isNullOrBlank()) {
                                    Text(t.body, style = MaterialTheme.typography.bodyMedium)
                                }
                            }
                        }
                    }
                }

                Spacer(Modifier.height(16.dp))
                if (!d.closed) {
                    OutlinedTextField(
                        value = reply,
                        onValueChange = { reply = it },
                        label = { Text("Tulis balasan / tambahan…") },
                        minLines = 2,
                        modifier = Modifier.fillMaxWidth(),
                    )
                    Spacer(Modifier.height(8.dp))
                    Button(
                        onClick = { viewModel.reply(d.id, reply); reply = "" },
                        enabled = !busy && reply.isNotBlank(),
                        modifier = Modifier.fillMaxWidth().height(52.dp),
                    ) {
                        Icon(Icons.AutoMirrored.Filled.Send, contentDescription = null)
                        Spacer(Modifier.width(8.dp))
                        Text("Kirim Balasan")
                    }
                } else {
                    Text(
                        "Laporan sudah ditutup. Tidak ada balasan baru.",
                        style = MaterialTheme.typography.bodyMedium,
                        modifier = Modifier.align(Alignment.CenterHorizontally),
                    )
                }
            }
        }
    }
}

@Composable
private fun StatusBanner(d: ComplaintDetailDto) {
    val container = when (d.status) {
        "selesai" -> MaterialTheme.colorScheme.secondaryContainer
        "ditolak" -> MaterialTheme.colorScheme.errorContainer
        "diproses" -> MaterialTheme.colorScheme.tertiaryContainer
        else -> MaterialTheme.colorScheme.surfaceVariant
    }
    Surface(color = container, shape = MaterialTheme.shapes.medium, modifier = Modifier.fillMaxWidth()) {
        Column(Modifier.padding(14.dp)) {
            Text(d.statusLabel ?: d.status, style = MaterialTheme.typography.titleMedium)
            val tgl = d.submittedAt?.substringBefore('T')?.replace('-', '.') ?: "-"
            Text("Diajukan $tgl", style = MaterialTheme.typography.bodyMedium)
            val pen = d.reporter?.name
            if (!pen.isNullOrBlank() && !d.isAnonymous) {
                Text("Pelapor: $pen", style = MaterialTheme.typography.bodyMedium)
            }
        }
    }
}
