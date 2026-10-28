package com.desa.digital.feature.letter.ui

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
import com.desa.digital.feature.letter.LetterViewModel

/**
 * Daftar surat warga (PEMBANGUNAN.md 4.2): lacak status tiap pengajuan, terbaru dulu.
 * Kartu bisa dibuka untuk riwayat lengkap; FAB menuju wizard pengajuan.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun LetterListScreen(
    onBack: () -> Unit,
    onNewLetter: () -> Unit,
    onOpenDetail: (Long) -> Unit,
    viewModel: LetterViewModel = hiltViewModel(),
) {
    val letters by viewModel.letters.collectAsState()
    val busy by viewModel.busy.collectAsState()
    val message by viewModel.message.collectAsState()
    val snackbar = remember { SnackbarHostState() }

    LaunchedEffect(Unit) { viewModel.loadLetters() }
    LaunchedEffect(message) {
        message?.let { snackbar.showSnackbar(it); viewModel.consumeMessage() }
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Surat Saya") },
                navigationIcon = { IconButton(onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, "Kembali") } },
            )
        },
        snackbarHost = { SnackbarHost(snackbar) },
        floatingActionButton = {
            FloatingActionButton(onClick = onNewLetter) { Icon(Icons.Filled.Add, "Ajukan surat") }
        },
    ) { padding ->
        Column(Modifier.padding(padding).padding(16.dp)) {
            if (busy && letters.isEmpty()) {
                CircularProgressIndicator(modifier = Modifier.align(Alignment.CenterHorizontally))
            } else if (letters.isEmpty()) {
                Text(
                    "Belum ada surat yang Anda ajukan.\nTap tombol + untuk mulai membuat surat.",
                    style = MaterialTheme.typography.bodyLarge,
                )
            } else {
                LazyColumn(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    items(letters, key = { it.id }) { letter ->
                        Card(onClick = { onOpenDetail(letter.id) }, modifier = Modifier.fillMaxWidth()) {
                            Column(Modifier.padding(14.dp)) {
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment = Alignment.CenterVertically,
                                ) {
                                    Text(letter.typeName ?: "Surat", style = MaterialTheme.typography.titleMedium)
                                    SuggestionChip(onClick = {}, label = {
                                        Text(letter.statusLabel ?: letter.status)
                                    })
                                }
                                Spacer(Modifier.height(6.dp))
                                val tgl = letter.submittedAt?.substringBefore('T')?.replace('-', '.') ?: "-"
                                Text(
                                    if (letter.number != null) "Nomor: ${letter.number}" else "Diajukan $tgl",
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
