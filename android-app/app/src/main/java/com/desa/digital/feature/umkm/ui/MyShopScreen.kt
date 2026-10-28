package com.desa.digital.feature.umkm.ui

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.Delete
import androidx.compose.material.icons.filled.Edit
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import com.desa.digital.core.network.dto.UmkmProductDto
import com.desa.digital.feature.umkm.UmkmViewModel

/**
 * Dasbor "Toko Saya" untuk pelaku UMKM (PEMBANGUNAN.md 4.4): status verifikasi toko, catatan
 * penolakan (bila ada), statistik penjual (dilihat & klik WhatsApp), dan katalog produk yang
 * bisa ditambah/ubah/hapus. Bila belum punya toko, diarahkan ke formulir pendaftaran.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun MyShopScreen(
    onBack: () -> Unit,
    onEditShop: () -> Unit,
    onAddProduct: () -> Unit,
    onEditProduct: (Long) -> Unit,
    viewModel: UmkmViewModel = hiltViewModel(),
) {
    val shop by viewModel.myShop.collectAsState()
    val busy by viewModel.busy.collectAsState()
    val message by viewModel.message.collectAsState()
    val snackbar = remember { SnackbarHostState() }
    var deleteTarget by remember { mutableStateOf<UmkmProductDto?>(null) }

    LaunchedEffect(Unit) { viewModel.loadMyShop() }
    LaunchedEffect(message) {
        message?.let { snackbar.showSnackbar(it); viewModel.consumeMessage() }
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Toko Saya") },
                navigationIcon = { IconButton(onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, "Kembali") } },
            )
        },
        snackbarHost = { SnackbarHost(snackbar) },
    ) { padding ->
        Column(
            Modifier.padding(padding).padding(20.dp).fillMaxSize()
                .verticalScroll(rememberScrollState()),
        ) {
            val s = shop
            if (s == null) {
                if (busy) {
                    CircularProgressIndicator()
                } else {
                    Text(
                        "Anda belum punya toko UMKM. Daftarkan toko untuk mulai menjual produk " +
                            "ke warga desa. Setiap toko diverifikasi oleh petugas desa.",
                        style = MaterialTheme.typography.bodyLarge,
                    )
                    Spacer(Modifier.height(16.dp))
                    Button(onClick = onEditShop, modifier = Modifier.fillMaxWidth().height(52.dp)) {
                        Text("Daftarkan Toko")
                    }
                }
            } else {
                StatusBanner(s.statusLabel ?: s.status ?: "-", s.status)
                if (!s.reviewNote.isNullOrBlank()) {
                    Spacer(Modifier.height(8.dp))
                    Text("Catatan petugas: ${s.reviewNote}", style = MaterialTheme.typography.bodyMedium)
                }
                Spacer(Modifier.height(14.dp))

                Card(Modifier.fillMaxWidth()) {
                    Column(Modifier.padding(14.dp)) {
                        Text(s.name, style = MaterialTheme.typography.titleLarge)
                        Text(s.categoryLabel ?: s.category, style = MaterialTheme.typography.labelLarge)
                        val stats = s.stats
                        Spacer(Modifier.height(10.dp))
                        Row(horizontalArrangement = Arrangement.spacedBy(24.dp)) {
                            StatCell("Dilihat", stats?.viewsCount ?: 0)
                            StatCell("Klik WA", stats?.waClicksCount ?: 0)
                            StatCell("Produk", s.products.size)
                        }
                    }
                }
                if (s.isHidden) {
                    Spacer(Modifier.height(8.dp))
                    Text(
                        "Toko sedang disembunyikan dari marketplace oleh petugas.",
                        style = MaterialTheme.typography.bodyMedium,
                        color = MaterialTheme.colorScheme.error,
                    )
                }

                Spacer(Modifier.height(16.dp))
                OutlinedButton(onClick = onEditShop, modifier = Modifier.fillMaxWidth()) { Text("Ubah Profil Toko") }
                Spacer(Modifier.height(20.dp))

                Text("Katalog Produk", style = MaterialTheme.typography.titleMedium)
                Spacer(Modifier.height(8.dp))
                if (s.products.isEmpty()) {
                    Text("Belum ada produk. Tambahkan produk pertama Anda.", style = MaterialTheme.typography.bodyMedium)
                } else {
                    s.products.forEach { p ->
                        MyProductRow(
                            product = p,
                            onEdit = { onEditProduct(p.id) },
                            onDelete = { deleteTarget = p },
                        )
                        Spacer(Modifier.height(8.dp))
                    }
                }
                Spacer(Modifier.height(12.dp))
                Button(onClick = onAddProduct, modifier = Modifier.fillMaxWidth().height(52.dp)) {
                    Icon(Icons.Filled.Add, contentDescription = null)
                    Spacer(Modifier.width(8.dp))
                    Text("Tambah Produk")
                }
            }
        }
    }

    val target = deleteTarget
    if (target != null) {
        AlertDialog(
            onDismissRequest = { deleteTarget = null },
            title = { Text("Hapus produk?") },
            text = { Text("Produk \"${target.name}\" akan dihapus dari katalog toko Anda.") },
            confirmButton = {
                TextButton(onClick = {
                    viewModel.deleteProduct(target.id)
                    deleteTarget = null
                }) { Text("Hapus") }
            },
            dismissButton = { TextButton(onClick = { deleteTarget = null }) { Text("Batal") } },
        )
    }
}

@Composable
private fun StatusBanner(label: String, status: String?) {
    val container = when (status) {
        "aktif" -> MaterialTheme.colorScheme.secondaryContainer
        "ditolak" -> MaterialTheme.colorScheme.errorContainer
        "nonaktif" -> MaterialTheme.colorScheme.tertiaryContainer
        else -> MaterialTheme.colorScheme.surfaceVariant
    }
    Surface(color = container, shape = MaterialTheme.shapes.medium, modifier = Modifier.fillMaxWidth()) {
        Text("Status: $label", style = MaterialTheme.typography.titleMedium, modifier = Modifier.padding(12.dp))
    }
}

@Composable
private fun StatCell(label: String, value: Int) {
    Column {
        Text("$value", style = MaterialTheme.typography.headlineSmall)
        Text(label, style = MaterialTheme.typography.bodySmall)
    }
}

@Composable
private fun MyProductRow(product: UmkmProductDto, onEdit: () -> Unit, onDelete: () -> Unit) {
    Card(Modifier.fillMaxWidth()) {
        Column(Modifier.padding(12.dp)) {
            Text(product.name, style = MaterialTheme.typography.titleMedium)
            Text(
                (product.priceLabel ?: "Rp ${product.price}") +
                    (if (product.isAvailable) "" else " · Habis"),
                style = MaterialTheme.typography.bodyMedium,
            )
            if (product.photos.isNotEmpty()) {
                Text("${product.photos.size} foto", style = MaterialTheme.typography.bodySmall)
            }
            Spacer(Modifier.height(6.dp))
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                TextButton(onClick = onEdit) {
                    Icon(Icons.Filled.Edit, contentDescription = null)
                    Spacer(Modifier.width(6.dp))
                    Text("Ubah")
                }
                TextButton(onClick = onDelete) {
                    Icon(Icons.Filled.Delete, contentDescription = null)
                    Spacer(Modifier.width(6.dp))
                    Text("Hapus")
                }
            }
        }
    }
}
