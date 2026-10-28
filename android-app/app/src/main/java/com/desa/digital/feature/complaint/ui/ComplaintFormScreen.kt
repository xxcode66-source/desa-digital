package com.desa.digital.feature.complaint.ui

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
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import coil.compose.AsyncImage
import com.desa.digital.feature.complaint.ComplaintViewModel

/**
 * Formulir pengaduan (PEMBANGUNAN.md 4.5): pilih kategori (dari config desa), judul &
 * penjelasan, lokasi opsional, foto bukti (maks 3, hemat kuota), dan opsi kirim anonim.
 * Anonim hanya menyamarkan nama di papan publik — pelapor tetap tercatat untuk petugas.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ComplaintFormScreen(
    onBack: () -> Unit,
    onOpenDetail: (Long) -> Unit,
    viewModel: ComplaintViewModel = hiltViewModel(),
) {
    val categories by viewModel.categories.collectAsState()
    val busy by viewModel.busy.collectAsState()
    val message by viewModel.message.collectAsState()
    val submittedId by viewModel.submittedComplaintId.collectAsState()
    val snackbar = remember { SnackbarHostState() }

    var category by remember { mutableStateOf<String?>(null) }
    var title by remember { mutableStateOf("") }
    var description by remember { mutableStateOf("") }
    var location by remember { mutableStateOf("") }
    var anonymous by remember { mutableStateOf(false) }
    var uris by remember { mutableStateOf<List<Uri>>(emptyList()) }

    LaunchedEffect(Unit) { if (categories.isEmpty()) viewModel.loadCategories() }
    LaunchedEffect(submittedId) {
        submittedId?.let { viewModel.consumeSubmitted(); onOpenDetail(it) }
    }
    LaunchedEffect(message) {
        message?.let { snackbar.showSnackbar(it); viewModel.consumeMessage() }
    }

    val picker = rememberLauncherForActivityResult(ActivityResultContracts.PickMultipleVisualMedia(3)) { picked ->
        uris = (uris + picked).distinct().take(3)
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Buat Laporan") },
                navigationIcon = { IconButton(onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, "Kembali") } },
            )
        },
        snackbarHost = { SnackbarHost(snackbar) },
    ) { padding ->
        Column(
            modifier = Modifier.padding(padding).padding(20.dp).fillMaxSize()
                .verticalScroll(rememberScrollState()),
        ) {
            Text("Kategori laporan", style = MaterialTheme.typography.titleSmall)
            Spacer(Modifier.height(4.dp))
            if (categories.isEmpty() && !busy) Text("Memuat kategori…", style = MaterialTheme.typography.bodyMedium)
            categories.forEach { c ->
                Row(verticalAlignment = Alignment.CenterVertically, modifier = Modifier.fillMaxWidth()) {
                    RadioButton(selected = category == c.code, onClick = { category = c.code })
                    Text(c.label)
                }
            }
            Spacer(Modifier.height(16.dp))

            OutlinedTextField(
                value = title,
                onValueChange = { title = it },
                label = { Text("Judul laporan *") },
                modifier = Modifier.fillMaxWidth(),
            )
            Spacer(Modifier.height(12.dp))

            OutlinedTextField(
                value = description,
                onValueChange = { description = it },
                label = { Text("Jelaskan laporannya *") },
                minLines = 4,
                modifier = Modifier.fillMaxWidth(),
            )
            Spacer(Modifier.height(12.dp))

            OutlinedTextField(
                value = location,
                onValueChange = { location = it },
                label = { Text("Lokasi (opsional)") },
                supportingText = { Text("Contoh: Jl. Melati RT 3, depan pasar.") },
                modifier = Modifier.fillMaxWidth(),
            )
            Spacer(Modifier.height(16.dp))

            Text("Foto bukti (opsional, maks 3)", style = MaterialTheme.typography.titleSmall)
            Spacer(Modifier.height(8.dp))
            OutlinedButton(
                onClick = { picker.launch(PickVisualMediaRequest(ActivityResultContracts.PickVisualMedia.ImageOnly)) },
                modifier = Modifier.fillMaxWidth(),
            ) { Text("Pilih foto") }
            Spacer(Modifier.height(8.dp))
            if (uris.isNotEmpty()) {
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    uris.forEach { uri ->
                        AsyncImage(model = uri, contentDescription = "Foto bukti", modifier = Modifier.size(84.dp))
                    }
                }
                Spacer(Modifier.height(4.dp))
                Text("${uris.size} foto dipilih.", style = MaterialTheme.typography.bodySmall)
            }
            Spacer(Modifier.height(16.dp))

            Card(modifier = Modifier.fillMaxWidth()) {
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    modifier = Modifier.padding(horizontal = 8.dp),
                ) {
                    Column(Modifier.weight(1f)) {
                        Text("Kirim secara anonim", style = MaterialTheme.typography.titleSmall)
                        Text(
                            "Nama Anda disembunyikan di papan publik. Petugas desa tetap bisa menghubungi bila perlu.",
                            style = MaterialTheme.typography.bodySmall,
                        )
                    }
                    Switch(checked = anonymous, onCheckedChange = { anonymous = it })
                }
            }
            Spacer(Modifier.height(20.dp))

            Button(
                onClick = {
                    viewModel.submit(
                        category = category ?: "",
                        title = title,
                        description = description,
                        location = location.ifBlank { null },
                        latitude = null,
                        longitude = null,
                        isAnonymous = anonymous,
                        photoUris = uris,
                    )
                },
                enabled = !busy,
                modifier = Modifier.fillMaxWidth().height(52.dp),
            ) {
                if (busy) {
                    CircularProgressIndicator(Modifier.size(22.dp), color = MaterialTheme.colorScheme.onPrimary)
                } else {
                    Text("Kirim Laporan")
                }
            }
        }
    }
}
