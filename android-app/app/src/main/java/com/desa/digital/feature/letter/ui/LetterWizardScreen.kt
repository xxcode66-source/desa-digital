package com.desa.digital.feature.letter.ui

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
import com.desa.digital.core.network.dto.LetterFieldDto
import com.desa.digital.core.network.dto.LetterTypeDto
import com.desa.digital.feature.letter.LetterViewModel
import kotlinx.coroutines.launch

/**
 * Wizard pengajuan surat dinamis (PEMBANGUNAN.md 4.2): jenis surat -> isi field
 * (dirender dari form_schema, tanpa hardcode per jenis) -> lampiran -> kirim.
 * Mode perbaikan (repairLetterId != null) memakai jalur yang sama dengan data terisi,
 * dan field yang ditandai operator diberi pesan merah.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun LetterWizardScreen(
    onBack: () -> Unit,
    onOpenDetail: (Long) -> Unit,
    repairLetterId: Long? = null,
    viewModel: LetterViewModel = hiltViewModel(),
) {
    val types by viewModel.types.collectAsState()
    val detail by viewModel.detail.collectAsState()
    val busy by viewModel.busy.collectAsState()
    val message by viewModel.message.collectAsState()
    val submittedId by viewModel.submittedLetterId.collectAsState()
    val snackbar = remember { SnackbarHostState() }
    val scope = rememberCoroutineScope()

    var step by remember { mutableIntStateOf(1) }
    var selectedType by remember { mutableStateOf<LetterTypeDto?>(null) }
    val values = remember { mutableStateMapOf<String, String>() }
    var uris by remember { mutableStateOf<List<Uri>>(emptyList()) }

    val isRepair = repairLetterId != null

    LaunchedEffect(Unit) {
        viewModel.loadTypes()
        if (isRepair) viewModel.loadDetail(repairLetterId!!)
    }

    // Mode perbaikan: jenis + isian awal datang dari detail surat.
    LaunchedEffect(detail, types) {
        if (isRepair && detail != null && selectedType == null) {
            selectedType = types.firstOrNull { it.id == detail?.type?.id }
            values.putAll(detail?.data ?: emptyMap())
            if (selectedType != null) step = 2
        }
    }

    LaunchedEffect(submittedId) {
        submittedId?.let {
            viewModel.consumeSubmitted()
            onOpenDetail(it) // langsung lacak status surat yang baru dikirim
        }
    }
    LaunchedEffect(message) {
        message?.let { snackbar.showSnackbar(it); viewModel.consumeMessage() }
    }

    val picker = rememberLauncherForActivityResult(ActivityResultContracts.PickMultipleVisualMedia(5)) { picked ->
        uris = (uris + picked).distinct().take(5)
    }

    val schema = selectedType?.formSchemaJson ?: emptyList()
    val flagged = detail?.flaggedFields ?: emptyList()

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text(if (isRepair) "Perbaiki Surat" else "Ajukan Surat") },
                navigationIcon = { IconButton(onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, "Kembali") } },
            )
        },
        snackbarHost = { SnackbarHost(snackbar) },
    ) { padding ->
        Column(
            modifier = Modifier.padding(padding).padding(20.dp).fillMaxSize()
                .verticalScroll(rememberScrollState()),
        ) {
            when (step) {
                1 -> {
                    Text("Pilih jenis surat", style = MaterialTheme.typography.titleLarge)
                    Spacer(Modifier.height(4.dp))
                    Text(
                        "Formulir menyesuaikan jenis surat yang dipilih — dibuat kelolaan desa.",
                        style = MaterialTheme.typography.bodyMedium,
                    )
                    Spacer(Modifier.height(12.dp))
                    if (types.isEmpty() && !busy) Text("Belum ada jenis surat tersedia.")
                    types.forEach { type ->
                        Card(
                            onClick = { selectedType = type; step = 2 },
                            modifier = Modifier.fillMaxWidth().padding(vertical = 5.dp),
                        ) {
                            Column(Modifier.padding(14.dp)) {
                                Text(type.name, style = MaterialTheme.typography.titleMedium)
                                if (type.needsRt) {
                                    Text("Butuh pengantar RT/RW", style = MaterialTheme.typography.bodySmall)
                                }
                            }
                        }
                    }
                }

                2 -> {
                    WizardHeading(step = 2, isRepair = isRepair, typeName = selectedType?.name)
                    schema.forEach { field ->
                        DynamicField(
                            field = field,
                            value = values[field.key] ?: "",
                            flagged = field.key in flagged,
                            onValue = { values[field.key] = it },
                        )
                        Spacer(Modifier.height(12.dp))
                    }
                    Spacer(Modifier.height(8.dp))
                    Button(
                        onClick = {
                            // Validasi client sederhana; server tetap penjaga terakhir.
                            val missing = schema.firstOrNull { it.required && values[it.key].isNullOrBlank() }
                            if (missing != null) {
                                scope.launch { snackbar.showSnackbar("Isi bagian: ${missing.label ?: missing.key}") }
                            } else {
                                step = 3
                            }
                        },
                        enabled = !busy,
                        modifier = Modifier.fillMaxWidth().height(52.dp),
                    ) { Text(if (isRepair) "Lanjut ke Lampiran" else "Lanjut") }
                    Spacer(Modifier.height(8.dp))
                    if (!isRepair) TextButton(onClick = { step = 1 }) { Text("Ganti jenis surat") }
                }

                3 -> {
                    WizardHeading(step = 3, isRepair = isRepair, typeName = selectedType?.name)
                    Text("Unggah minimal satu foto (KTP / surat pengantar RT), terbaca jelas.",
                        style = MaterialTheme.typography.bodyMedium)
                    Spacer(Modifier.height(12.dp))
                    OutlinedButton(
                        onClick = { picker.launch(PickVisualMediaRequest(ActivityResultContracts.PickVisualMedia.ImageOnly)) },
                        modifier = Modifier.fillMaxWidth(),
                    ) { Text("Pilih foto (maks 5)") }
                    Spacer(Modifier.height(12.dp))
                    if (uris.isNotEmpty()) {
                        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                            uris.forEach { uri ->
                                AsyncImage(
                                    model = uri,
                                    contentDescription = "Lampiran surat",
                                    modifier = Modifier.size(84.dp),
                                )
                            }
                        }
                        Spacer(Modifier.height(8.dp))
                        Text("${uris.size} foto dipilih.", style = MaterialTheme.typography.bodySmall)
                    }
                    Spacer(Modifier.height(16.dp))
                    Button(
                        onClick = {
                            val data = schema.associate { f -> f.key to (values[f.key]?.trim() ?: "") }
                            if (isRepair && repairLetterId != null) {
                                viewModel.repair(repairLetterId, data, uris)
                            } else {
                                selectedType?.let { viewModel.submit(it.id, data, uris) }
                            }
                        },
                        enabled = !busy,
                        modifier = Modifier.fillMaxWidth().height(52.dp),
                    ) {
                        if (busy) {
                            CircularProgressIndicator(Modifier.size(22.dp), color = MaterialTheme.colorScheme.onPrimary)
                        } else {
                            Text(if (isRepair) "Kirim Perbaikan" else "Kirim Pengajuan")
                        }
                    }
                    TextButton(onClick = { step = 2 }) { Text("Kembali ke isian") }
                }
            }
        }
    }
}

@Composable
private fun WizardHeading(step: Int, isRepair: Boolean, typeName: String?) {
    Column {
        Text(typeName ?: "Lengkapi surat", style = MaterialTheme.typography.titleLarge)
        Text(
            if (isRepair) "Perbaiki bagian bertanda merah, lalu kirim ulang."
            else "Langkah $step dari 3",
            style = MaterialTheme.typography.bodyMedium,
        )
        Spacer(Modifier.height(12.dp))
    }
}

/** Satu field dinamis: text / textarea / select — label & keharusan dari skema. */
@Composable
private fun DynamicField(
    field: LetterFieldDto,
    value: String,
    flagged: Boolean,
    onValue: (String) -> Unit,
) {
    val label = field.label ?: field.key
    when (field.type) {
        "select" -> Column {
            Text(if (field.required) "$label *" else label, style = MaterialTheme.typography.titleSmall)
            Spacer(Modifier.height(4.dp))
            field.options?.forEach { option ->
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    modifier = Modifier.fillMaxWidth(),
                ) {
                    RadioButton(selected = value == option, onClick = { onValue(option) })
                    Text(option, modifier = Modifier)
                }
            }
        }

        else -> OutlinedTextField(
            value = value,
            onValueChange = onValue,
            label = { Text(if (field.required) "$label *" else label) },
            minLines = if (field.type == "textarea") 3 else 1,
            isError = flagged,
            supportingText = if (flagged) {
                { Text("Ditandai petugas — mohon perbaiki bagian ini.", color = MaterialTheme.colorScheme.error) }
            } else null,
            modifier = Modifier.fillMaxWidth(),
        )
    }
}
