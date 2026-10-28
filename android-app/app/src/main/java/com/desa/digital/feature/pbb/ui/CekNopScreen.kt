package com.desa.digital.feature.pbb.ui

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.Search
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import com.desa.digital.core.network.dto.PbbBillDto
import com.desa.digital.feature.pbb.PbbViewModel

/**
 * Cek NOP publik (PEMBANGUNAN.md 4.3). Warga mengetik 18 digit NOP; hasil menampilkan nama WP
 * yang SUDAH disamarkan server, alamat, serta daftar tagihan per tahun + total tunggakan.
 * Selalu tampilkan disclaimer bahwa ini info sementara, bukan bukti pelunasan resmi.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun CekNopScreen(
    onBack: () -> Unit,
    viewModel: PbbViewModel = hiltViewModel(),
) {
    val lookup by viewModel.lookup.collectAsState()
    val busy by viewModel.busy.collectAsState()
    val message by viewModel.message.collectAsState()
    val snackbar = remember { SnackbarHostState() }
    var nop by remember { mutableStateOf("") }

    LaunchedEffect(message) {
        message?.let { snackbar.showSnackbar(it); viewModel.consumeMessage() }
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Cek Tagihan PBB") },
                navigationIcon = { IconButton(onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, "Kembali") } },
            )
        },
        snackbarHost = { SnackbarHost(snackbar) },
    ) { padding ->
        Column(
            Modifier.padding(padding).padding(20.dp).fillMaxSize().verticalScroll(rememberScrollState()),
        ) {
            OutlinedTextField(
                value = nop,
                onValueChange = { nop = it },
                label = { Text("Nomor Objek Pajak (NOP)") },
                supportingText = { Text("18 digit. Boleh memakai titik/garis, angka saja yang dipakai.") },
                singleLine = true,
                modifier = Modifier.fillMaxWidth(),
            )
            Spacer(Modifier.height(12.dp))
            Button(
                onClick = { viewModel.checkNop(nop) },
                enabled = !busy,
                modifier = Modifier.fillMaxWidth().height(52.dp),
            ) {
                if (busy) {
                    CircularProgressIndicator(Modifier.size(22.dp), color = MaterialTheme.colorScheme.onPrimary)
                } else {
                    Icon(Icons.Filled.Search, contentDescription = null)
                    Spacer(Modifier.width(8.dp))
                    Text("Cari")
                }
            }
            Spacer(Modifier.height(16.dp))

            val result = lookup
            if (result != null) {
                Card(Modifier.fillMaxWidth()) {
                    Column(Modifier.padding(16.dp)) {
                        Text("NOP: ${result.nop}", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)
                        Spacer(Modifier.height(4.dp))
                        Text("Wajib Pajak: ${result.wpNameMasked ?: "-"}", style = MaterialTheme.typography.bodyLarge)
                        Text(result.address ?: "-", style = MaterialTheme.typography.bodyMedium)
                        Text("RT ${result.rt ?: "-"} / RW ${result.rw ?: "-"}", style = MaterialTheme.typography.bodyMedium)
                        Spacer(Modifier.height(10.dp))
                        HorizontalDivider()
                        Spacer(Modifier.height(10.dp))
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically,
                        ) {
                            Text("Total tunggakan", style = MaterialTheme.typography.titleSmall)
                            Text(
                                "${result.arrearsCount} tahun · ${rupiah(result.arrearsTotal)}",
                                style = MaterialTheme.typography.titleMedium,
                                fontWeight = FontWeight.Bold,
                            )
                        }
                        Spacer(Modifier.height(10.dp))
                        result.bills.forEach { b -> BillRow(b) }
                    }
                }
                Spacer(Modifier.height(14.dp))
                PbbDisclaimer(result.disclaimer ?: "Info sementara. Pelunasan resmi mengikuti kantor pajak.")
            } else if (!busy) {
                Text(
                    "Masukkan NOP untuk melihat perkiraan tagihan PBB. Nama pemilik disamarkan demi keamanan data.",
                    style = MaterialTheme.typography.bodyMedium,
                )
            }
        }
    }
}

@Composable
internal fun BillRow(b: PbbBillDto) {
    Row(
        modifier = Modifier.fillMaxWidth().padding(vertical = 6.dp),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Column {
            Text("Tahun ${b.year}", style = MaterialTheme.typography.bodyLarge)
            Text(rupiah(b.amount) + if (b.penalty > 0) " + denda ${rupiah(b.penalty)}" else "", style = MaterialTheme.typography.bodySmall)
        }
        PbbStatusChip(b.statusLabel, b.status)
    }
}
