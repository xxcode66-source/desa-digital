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
 * Formulir daftar/ubah toko UMKM (PEMBANGUNAN.md 4.4): nama, kategori (config desa), deskripsi,
 * alamat + RT/RW, jam buka, nomor WhatsApp, dan satu foto. Mengisi otomatis saat mengubah toko
 * yang sudah ada. Toko baru/diajukan ulang menunggu verifikasi petugas desa.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ShopFormScreen(
    onBack: () -> Unit,
    viewModel: UmkmViewModel = hiltViewModel(),
) {
    val shop by viewModel.myShop.collectAsState()
    val categories by viewModel.categories.collectAsState()
    val busy by viewModel.busy.collectAsState()
    val message by viewModel.message.collectAsState()
    val snackbar = remember { SnackbarHostState() }

    var name by remember { mutableStateOf("") }
    var description by remember { mutableStateOf("") }
    var category by remember { mutableStateOf<String?>(null) }
    var address by remember { mutableStateOf("") }
    var rt by remember { mutableStateOf("") }
    var rw by remember { mutableStateOf("") }
    var openingHours by remember { mutableStateOf("") }
    var waNumber by remember { mutableStateOf("") }
    var photoUri by remember { mutableStateOf<Uri?>(null) }
    var prefilled by remember { mutableStateOf(false) }

    LaunchedEffect(Unit) {
        if (categories.isEmpty()) viewModel.loadCategories()
        viewModel.loadMyShop()
    }
    // Sekali saja, isi form dari toko yang ada (mode ubah).
    LaunchedEffect(shop) {
        if (!prefilled && shop != null) {
            val s = shop!!
            name = s.name
            description = s.description ?: ""
            category = s.category
            address = s.address ?: ""
            rt = s.rt ?: ""
            rw = s.rw ?: ""
            openingHours = s.openingHours ?: ""
            waNumber = "" // nomor WA tidak dikembalikan ke klien untuk privasi; pemilik mengisi ulang
            prefilled = true
        }
    }
    LaunchedEffect(message) {
        message?.let {
            snackbar.showSnackbar(it)
            viewModel.consumeMessage()
            if (it.contains("tersimpan", ignoreCase = true)) onBack()
        }
    }

    val picker = rememberLauncherForActivityResult(ActivityResultContracts.PickVisualMedia()) { uri ->
        uri?.let { photoUri = it }
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text(if (shop != null) "Ubah Toko" else "Daftarkan Toko") },
                navigationIcon = { IconButton(onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, "Kembali") } },
            )
        },
        snackbarHost = { SnackbarHost(snackbar) },
    ) { padding ->
        Column(
            Modifier.padding(padding).padding(20.dp).fillMaxSize()
                .verticalScroll(rememberScrollState()),
        ) {
            OutlinedTextField(name, { name = it }, label = { Text("Nama toko *") }, modifier = Modifier.fillMaxWidth())
            Spacer(Modifier.height(12.dp))

            Text("Kategori usaha *", style = MaterialTheme.typography.titleSmall)
            Spacer(Modifier.height(4.dp))
            if (categories.isEmpty() && !busy) Text("Memuat kategori…", style = MaterialTheme.typography.bodyMedium)
            categories.forEach { c ->
                Row(verticalAlignment = Alignment.CenterVertically) {
                    RadioButton(selected = category == c.code, onClick = { category = c.code })
                    Text(c.label)
                }
            }
            Spacer(Modifier.height(12.dp))

            OutlinedTextField(description, { description = it }, label = { Text("Deskripsi usaha") }, minLines = 3, modifier = Modifier.fillMaxWidth())
            Spacer(Modifier.height(12.dp))

            OutlinedTextField(address, { address = it }, label = { Text("Alamat") }, modifier = Modifier.fillMaxWidth())
            Spacer(Modifier.height(12.dp))

            Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                OutlinedTextField(rt, { rt = it }, label = { Text("RT") }, modifier = Modifier.weight(1f))
                OutlinedTextField(rw, { rw = it }, label = { Text("RW") }, modifier = Modifier.weight(1f))
            }
            Spacer(Modifier.height(12.dp))

            OutlinedTextField(openingHours, { openingHours = it }, label = { Text("Jam buka") }, supportingText = { Text("Contoh: 08.00–17.00 WIB") }, modifier = Modifier.fillMaxWidth())
            Spacer(Modifier.height(12.dp))

            OutlinedTextField(waNumber, { waNumber = it }, label = { Text("Nomor WhatsApp *") }, supportingText = { Text("Untuk dihubungi pembeli, mis. 0812xxxx atau 628xxxx.") }, modifier = Modifier.fillMaxWidth())
            Spacer(Modifier.height(16.dp))

            Text("Foto toko (opsional)", style = MaterialTheme.typography.titleSmall)
            Spacer(Modifier.height(8.dp))
            OutlinedButton(
                onClick = { picker.launch(PickVisualMediaRequest(ActivityResultContracts.PickVisualMedia.ImageOnly)) },
                modifier = Modifier.fillMaxWidth(),
            ) { Text("Pilih foto") }
            photoUri?.let {
                Spacer(Modifier.height(8.dp))
                AsyncImage(model = it, contentDescription = "Pratinjau foto toko", modifier = Modifier.size(120.dp))
            }
            Spacer(Modifier.height(20.dp))

            Button(
                onClick = {
                    viewModel.saveShop(
                        name = name,
                        description = description.ifBlank { null },
                        category = category ?: "",
                        address = address.ifBlank { null },
                        rt = rt.ifBlank { null },
                        rw = rw.ifBlank { null },
                        openingHours = openingHours.ifBlank { null },
                        waNumber = waNumber,
                        photoUri = photoUri,
                    )
                },
                enabled = !busy && name.isNotBlank() && category != null && waNumber.isNotBlank(),
                modifier = Modifier.fillMaxWidth().height(52.dp),
            ) {
                if (busy) CircularProgressIndicator(Modifier.size(22.dp), color = MaterialTheme.colorScheme.onPrimary)
                else Text(if (shop != null) "Simpan Perubahan" else "Daftarkan Toko")
            }
        }
    }
}
