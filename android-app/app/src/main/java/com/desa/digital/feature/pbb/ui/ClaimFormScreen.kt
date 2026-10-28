package com.desa.digital.feature.pbb.ui

import android.net.Uri
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.PickVisualMediaRequest
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import coil.compose.AsyncImage
import com.desa.digital.feature.pbb.PbbViewModel

/**
 * Ajukan klaim kepemilikan NOP (PEMBANGUNAN.md 4.3). Warga memasukkan NOP, catatan singkat,
 * dan WAJIB melampirkan satu foto bukti (SPPT/surat tanah). Petugas memverifikasi di panel;
 * NOP baru tampil di "NOP saya" setelah klaim disetujui.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ClaimFormScreen(
    onBack: () -> Unit,
    onDone: () -> Unit,
    viewModel: PbbViewModel = hiltViewModel(),
) {
    val busy by viewModel.busy.collectAsState()
    val message by viewModel.message.collectAsState()
    val lastClaim by viewModel.lastClaim.collectAsState()
    val snackbar = remember { SnackbarHostState() }

    var nop by remember { mutableStateOf("") }
    var note by remember { mutableStateOf("") }
    var evidence by remember { mutableStateOf<Uri?>(null) }

    LaunchedEffect(message) { message?.let { snackbar.showSnackbar(it); viewModel.consumeMessage() } }
    LaunchedEffect(lastClaim) {
        if (lastClaim != null) { viewModel.consumeClaim(); onDone() }
    }

    val picker = rememberLauncherForActivityResult(ActivityResultContracts.PickVisualMedia()) { uri ->
        uri?.let { evidence = it }
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Klaim Kepemilikan NOP") },
                navigationIcon = { IconButton(onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, "Kembali") } },
            )
        },
        snackbarHost = { SnackbarHost(snackbar) },
    ) { padding ->
        Column(
            Modifier.padding(padding).padding(20.dp).fillMaxSize().verticalScroll(rememberScrollState()),
        ) {
            Text(
                "Petugas desa akan meninjau klaim ini. Pastikan NOP benar dan foto bukti terbaca jelas.",
                style = MaterialTheme.typography.bodyMedium,
            )
            Spacer(Modifier.height(16.dp))

            OutlinedTextField(
                value = nop,
                onValueChange = { nop = it },
                label = { Text("NOP yang diklaim *") },
                supportingText = { Text("18 digit Nomor Objek Pajak.") },
                singleLine = true,
                modifier = Modifier.fillMaxWidth(),
            )
            Spacer(Modifier.height(12.dp))

            OutlinedTextField(
                value = note,
                onValueChange = { note = it },
                label = { Text("Catatan (opsional)") },
                minLines = 3,
                modifier = Modifier.fillMaxWidth(),
            )
            Spacer(Modifier.height(16.dp))

            Text("Foto bukti kepemilikan *", style = MaterialTheme.typography.titleSmall)
            Spacer(Modifier.height(8.dp))
            OutlinedButton(
                onClick = { picker.launch(PickVisualMediaRequest(ActivityResultContracts.PickVisualMedia.ImageOnly)) },
                modifier = Modifier.fillMaxWidth(),
            ) { Text(if (evidence == null) "Pilih foto (SPPT / surat tanah)" else "Ganti foto") }
            Spacer(Modifier.height(8.dp))
            evidence?.let {
                AsyncImage(model = it, contentDescription = "Foto bukti", modifier = Modifier.size(140.dp))
            }
            Spacer(Modifier.height(20.dp))

            Button(
                onClick = { viewModel.submitClaim(nop, note.ifBlank { null }, evidence) },
                enabled = !busy,
                modifier = Modifier.fillMaxWidth().height(52.dp),
            ) {
                if (busy) {
                    CircularProgressIndicator(Modifier.size(22.dp), color = MaterialTheme.colorScheme.onPrimary)
                } else {
                    Text("Kirim Klaim")
                }
            }
        }
    }
}
