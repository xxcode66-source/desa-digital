package com.desa.digital.feature.pbb.ui

import android.net.Uri
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.PickVisualMediaRequest
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import coil.compose.AsyncImage
import com.desa.digital.core.network.dto.PbbBillDto
import com.desa.digital.core.network.dto.PbbDepositBatchDto
import com.desa.digital.feature.pbb.PbbViewModel

/**
 * Setoran PBB untuk RT/Kolektor (PEMBANGUNAN.md 4.3). Kolektor memilih tagihan belum-bayar
 * dalam lingkup RT-nya, memberi judul slip, (opsional) foto bukti setor, lalu mengirim.
 * ACC dilakukan bendahara per item di panel. TIDAK ada uang yang diproses di aplikasi.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun DepositScreen(
    onBack: () -> Unit,
    viewModel: PbbViewModel = hiltViewModel(),
) {
    val bills by viewModel.bills.collectAsState()
    val deposits by viewModel.deposits.collectAsState()
    val busy by viewModel.busy.collectAsState()
    val message by viewModel.message.collectAsState()
    val lastDeposit by viewModel.lastDeposit.collectAsState()
    val snackbar = remember { SnackbarHostState() }

    var title by remember { mutableStateOf("") }
    var note by remember { mutableStateOf("") }
    var evidence by remember { mutableStateOf<Uri?>(null) }
    val selected = remember { mutableStateListOf<Long>() }

    LaunchedEffect(Unit) { viewModel.loadBills(); viewModel.loadMyDeposits() }
    LaunchedEffect(message) { message?.let { snackbar.showSnackbar(it); viewModel.consumeMessage() } }
    LaunchedEffect(lastDeposit) {
        if (lastDeposit != null) {
            selected.clear(); title = ""; note = ""; evidence = null
            viewModel.consumeDeposit(); viewModel.loadBills(); viewModel.loadMyDeposits()
        }
    }

    val picker = rememberLauncherForActivityResult(ActivityResultContracts.PickVisualMedia()) { uri -> uri?.let { evidence = it } }

    val totalSelected = bills.filter { selected.contains(it.id) }.sumOf { it.total }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Setoran PBB") },
                navigationIcon = { IconButton(onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, "Kembali") } },
            )
        },
        snackbarHost = { SnackbarHost(snackbar) },
    ) { padding ->
        Column(Modifier.padding(padding).padding(16.dp)) {
            Text("Tagihan belum dibayar di lingkup RT Anda. Pilih yang akan disetor.", style = MaterialTheme.typography.bodyMedium)
            Spacer(Modifier.height(8.dp))

            OutlinedTextField(
                value = title,
                onValueChange = { title = it },
                label = { Text("Judul slip (mis. Setoran RT 03) *") },
                singleLine = true,
                modifier = Modifier.fillMaxWidth(),
            )
            Spacer(Modifier.height(8.dp))
            OutlinedTextField(
                value = note,
                onValueChange = { note = it },
                label = { Text("Catatan (opsional)") },
                singleLine = true,
                modifier = Modifier.fillMaxWidth(),
            )
            Spacer(Modifier.height(8.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
                OutlinedButton(onClick = { picker.launch(PickVisualMediaRequest(ActivityResultContracts.PickVisualMedia.ImageOnly)) }) {
                    Text(if (evidence == null) "Foto bukti setor" else "Ganti foto")
                }
                evidence?.let {
                    Spacer(Modifier.width(12.dp))
                    AsyncImage(model = it, contentDescription = "Bukti setor", modifier = Modifier.size(56.dp))
                }
            }
            Spacer(Modifier.height(12.dp))

            Button(
                onClick = {
                    val map = bills.filter { selected.contains(it.id) }.associate { it.id to it.total }
                    viewModel.submitDeposit(title, note.ifBlank { null }, map, evidence)
                },
                enabled = !busy && selected.isNotEmpty(),
                modifier = Modifier.fillMaxWidth().height(52.dp),
            ) {
                if (busy) {
                    CircularProgressIndicator(Modifier.size(22.dp), color = MaterialTheme.colorScheme.onPrimary)
                } else {
                    Text("Kirim Setoran (${selected.size}) · ${rupiah(totalSelected)}")
                }
            }
            Spacer(Modifier.height(12.dp))

            if (busy && bills.isEmpty()) {
                CircularProgressIndicator(modifier = Modifier.align(Alignment.CenterHorizontally))
            } else if (bills.isEmpty()) {
                Text("Tidak ada tagihan untuk disetor.", style = MaterialTheme.typography.bodyMedium)
            } else {
                Text("Pilih tagihan", style = MaterialTheme.typography.titleSmall)
                Spacer(Modifier.height(4.dp))
                LazyColumn(
                    modifier = Modifier.weight(1f),
                    verticalArrangement = Arrangement.spacedBy(8.dp),
                ) {
                    items(bills, key = { it.id }) { b ->
                        BillSelectRow(b, checked = selected.contains(b.id), onToggle = {
                            if (selected.contains(b.id)) selected.remove(b.id) else selected.add(b.id)
                        })
                    }
                    item { Spacer(Modifier.height(12.dp)); SectionTitle("Riwayat setoran saya") }
                    items(deposits, key = { "d-${it.id}" }) { d -> DepositCard(d) }
                }
            }
        }
    }
}

@Composable
private fun BillSelectRow(b: PbbBillDto, checked: Boolean, onToggle: () -> Unit) {
    OutlinedCard(onClick = { onToggle() }, modifier = Modifier.fillMaxWidth()) {
        Row(Modifier.padding(12.dp), verticalAlignment = Alignment.CenterVertically) {
            Checkbox(checked = checked, onCheckedChange = { onToggle() })
            Column(Modifier.weight(1f)) {
                Text("NOP ${b.nop ?: "-"} · ${b.year}", style = MaterialTheme.typography.bodyLarge)
                Text("${b.wpName ?: "-"} · RT ${b.rt ?: "-"}", style = MaterialTheme.typography.bodySmall)
                Text(rupiah(b.total), style = MaterialTheme.typography.titleSmall, fontWeight = FontWeight.Bold)
            }
            PbbStatusChip(b.statusLabel, b.status)
        }
    }
}

@Composable
private fun SectionTitle(text: String) {
    Text(text, style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)
}

@Composable
private fun DepositCard(d: PbbDepositBatchDto) {
    Card(Modifier.fillMaxWidth()) {
        Column(Modifier.padding(12.dp)) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Text(d.title, style = MaterialTheme.typography.titleMedium)
                PbbStatusChip(d.statusLabel, d.status)
            }
            Text("${d.itemCount} tagihan · ${rupiah(d.totalAmount)} · ${tanggalIso(d.createdAt)}", style = MaterialTheme.typography.bodySmall)
            if (d.status == "partially_approved") {
                Text("Sebagian item sudah di-ACC bendahara.", style = MaterialTheme.typography.bodySmall)
            }
        }
    }
}
