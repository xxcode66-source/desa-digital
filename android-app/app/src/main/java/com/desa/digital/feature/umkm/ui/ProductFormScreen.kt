package com.desa.digital.feature.umkm.ui

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
import com.desa.digital.feature.umkm.UmkmViewModel

/**
 * Formulir tambah/ubah produk UMKM (PEMBANGUNAN.md 4.4): nama, harga, deskripsi, ketersediaan
 * stok, dan hingga 5 foto. Saat mengubah, produk dicari dari katalog "toko saya" (tanpa
 * melewati halaman detail publik agar statistik tidak terpengaruh). Mengirim foto baru akan
 * menggantikan seluruh foto produk.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ProductFormScreen(
    productId: Long?,
    onBack: () -> Unit,
    viewModel: UmkmViewModel = hiltViewModel(),
) {
    val shop by viewModel.myShop.collectAsState()
    val busy by viewModel.busy.collectAsState()
    val message by viewModel.message.collectAsState()
    val snackbar = remember { SnackbarHostState() }

    val existing = remember(shop, productId) {
        if (productId == null) null else shop?.products?.firstOrNull { it.id == productId }
    }

    var name by remember { mutableStateOf("") }
    var description by remember { mutableStateOf("") }
    var price by remember { mutableStateOf("") }
    var available by remember { mutableStateOf(true) }
    var uris by remember { mutableStateOf<List<Uri>>(emptyList()) }
    var prefilled by remember { mutableStateOf(false) }

    LaunchedEffect(Unit) { viewModel.loadMyShop() }
    LaunchedEffect(existing) {
        if (!prefilled && existing != null) {
            name = existing.name
            description = existing.description ?: ""
            price = existing.price.toLong().toString()
            available = existing.isAvailable
            prefilled = true
        }
    }
    LaunchedEffect(message) {
        message?.let {
            snackbar.showSnackbar(it)
            viewModel.consumeMessage()
            if (it.contains("produk", ignoreCase = true) &&
                (it.contains("ditambahkan", ignoreCase = true) || it.contains("diperbarui", ignoreCase = true))
            ) onBack()
        }
    }

    val picker = rememberLauncherForActivityResult(ActivityResultContracts.PickMultipleVisualMedia(5)) { picked ->
        // Foto baru menggantikan seluruh foto lama saat disimpan.
        uris = (uris + picked).distinct().take(5)
    }

    val isEdit = productId != null
    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text(if (isEdit) "Ubah Produk" else "Tambah Produk") },
                navigationIcon = { IconButton(onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, "Kembali") } },
            )
        },
        snackbarHost = { SnackbarHost(snackbar) },
    ) { padding ->
        Column(
            Modifier.padding(padding).padding(20.dp).fillMaxSize()
                .verticalScroll(rememberScrollState()),
        ) {
            OutlinedTextField(name, { name = it }, label = { Text("Nama produk *") }, modifier = Modifier.fillMaxWidth())
            Spacer(Modifier.height(12.dp))

            OutlinedTextField(price, { price = it }, label = { Text("Harga (Rp) *") }, supportingText = { Text("Hanya angka, mis. 15000.") }, modifier = Modifier.fillMaxWidth())
            Spacer(Modifier.height(12.dp))

            OutlinedTextField(description, { description = it }, label = { Text("Deskripsi") }, minLines = 3, modifier = Modifier.fillMaxWidth())
            Spacer(Modifier.height(12.dp))

            Card(Modifier.fillMaxWidth()) {
                Row(verticalAlignment = Alignment.CenterVertically, modifier = Modifier.padding(horizontal = 8.dp)) {
                    Text("Produk tersedia", style = MaterialTheme.typography.titleSmall, modifier = Modifier.weight(1f))
                    Switch(checked = available, onCheckedChange = { available = it })
                }
            }
            Spacer(Modifier.height(16.dp))

            Text("Foto produk (opsional, maks 5)", style = MaterialTheme.typography.titleSmall)
            if (isEdit && existing?.photos?.isNotEmpty() == true && uris.isEmpty()) {
                Spacer(Modifier.height(4.dp))
                Text(
                    "Sudah ada ${existing.photos.size} foto. Pilih foto baru untuk menggantinya.",
                    style = MaterialTheme.typography.bodySmall,
                )
            }
            Spacer(Modifier.height(8.dp))
            OutlinedButton(
                onClick = { picker.launch(PickVisualMediaRequest(ActivityResultContracts.PickVisualMedia.ImageOnly)) },
                modifier = Modifier.fillMaxWidth(),
            ) { Text("Pilih foto") }
            if (uris.isNotEmpty()) {
                Spacer(Modifier.height(8.dp))
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    uris.forEach { uri ->
                        AsyncImage(model = uri, contentDescription = "Foto produk", modifier = Modifier.size(84.dp))
                    }
                }
                Spacer(Modifier.height(4.dp))
                Text("${uris.size} foto dipilih.", style = MaterialTheme.typography.bodySmall)
            }
            Spacer(Modifier.height(20.dp))

            Button(
                onClick = {
                    if (isEdit && productId != null) {
                        viewModel.updateProduct(
                            productId = productId,
                            name = name,
                            description = description.ifBlank { null },
                            priceText = price,
                            isAvailable = available,
                            photoUris = uris,
                            replacePhotos = uris.isNotEmpty(),
                        )
                    } else {
                        viewModel.addProduct(name, description.ifBlank { null }, price, available, uris)
                    }
                },
                enabled = !busy && name.isNotBlank() && price.isNotBlank(),
                modifier = Modifier.fillMaxWidth().height(52.dp),
            ) {
                if (busy) CircularProgressIndicator(Modifier.size(22.dp), color = MaterialTheme.colorScheme.onPrimary)
                else Text(if (isEdit) "Simpan Perubahan" else "Tambah Produk")
            }
        }
    }
}
