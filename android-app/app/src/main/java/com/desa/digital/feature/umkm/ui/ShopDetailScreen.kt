package com.desa.digital.feature.umkm.ui

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.Phone
import androidx.compose.material.icons.filled.Warning
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import com.desa.digital.core.network.dto.UmkmProductDto
import com.desa.digital.feature.umkm.UmkmViewModel

/**
 * Detail satu toko UMKM (PEMBANGUNAN.md 4.4): profil (kategori, alamat RT/RW, jam buka),
 * katalog produk, tombol "Chat WhatsApp" (tautan wa.me dibangun server + pesan otomatis),
 * dan aksi lapor untuk moderasi petugas. Transaksi tetap offline lewat WhatsApp.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ShopDetailScreen(
    shopId: Long,
    onBack: () -> Unit,
    onOpenProduct: (Long) -> Unit,
    viewModel: UmkmViewModel = hiltViewModel(),
) {
    val detail by viewModel.shopDetail.collectAsState()
    val busy by viewModel.busy.collectAsState()
    val message by viewModel.message.collectAsState()
    val waUrl by viewModel.waUrl.collectAsState()
    val snackbar = remember { SnackbarHostState() }
    val openUrl = rememberOpenUrl()
    var reportOpen by remember { mutableStateOf(false) }

    LaunchedEffect(shopId) { viewModel.loadShop(shopId) }
    LaunchedEffect(message) {
        message?.let { snackbar.showSnackbar(it); viewModel.consumeMessage() }
    }
    LaunchedEffect(waUrl) {
        waUrl?.let {
            if (!openUrl(it)) snackbar.showSnackbar("Tidak ada aplikasi untuk membuka WhatsApp.")
            viewModel.consumeWa()
        }
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Detail Toko") },
                navigationIcon = { IconButton(onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, "Kembali") } },
                actions = { IconButton(onClick = { reportOpen = true }) { Icon(Icons.Filled.Warning, "Lapor toko") } },
            )
        },
        snackbarHost = { SnackbarHost(snackbar) },
    ) { padding ->
        val shop = detail
        Column(
            Modifier.padding(padding).padding(20.dp).fillMaxSize()
                .verticalScroll(rememberScrollState()),
        ) {
            if (shop == null) {
                if (busy) CircularProgressIndicator() else Text("Memuat toko…")
            } else {
                Text(shop.name, style = MaterialTheme.typography.headlineSmall)
                Spacer(Modifier.height(4.dp))
                Text(shop.categoryLabel ?: shop.category, style = MaterialTheme.typography.labelLarge)
                if (!shop.description.isNullOrBlank()) {
                    Spacer(Modifier.height(10.dp))
                    Text(shop.description, style = MaterialTheme.typography.bodyMedium)
                }
                Spacer(Modifier.height(10.dp))
                DetailRow("Alamat", shop.address ?: "-")
                val rt = listOfNotNull(
                    shop.rt?.takeIf { it.isNotBlank() }?.let { "RT $it" },
                    shop.rw?.takeIf { it.isNotBlank() }?.let { "RW $it" },
                ).joinToString(" ").ifBlank { "-" }
                DetailRow("Lokasi", rt)
                DetailRow("Jam buka", shop.openingHours ?: "-")

                Spacer(Modifier.height(16.dp))
                Button(
                    onClick = { viewModel.requestWa(shop.id) },
                    enabled = !busy,
                    modifier = Modifier.fillMaxWidth().height(52.dp),
                ) {
                    Icon(Icons.Filled.Phone, contentDescription = null)
                    Spacer(Modifier.width(8.dp))
                    Text("Chat WhatsApp")
                }

                Spacer(Modifier.height(20.dp))
                Text("Produk (${shop.products.size})", style = MaterialTheme.typography.titleMedium)
                Spacer(Modifier.height(8.dp))
                if (shop.products.isEmpty()) {
                    Text("Belum ada produk terdaftar.", style = MaterialTheme.typography.bodyMedium)
                } else {
                    shop.products.forEach { p ->
                        ProductRow(p) { onOpenProduct(p.id) }
                        Spacer(Modifier.height(8.dp))
                    }
                }

                Spacer(Modifier.height(12.dp))
                OutlinedButton(
                    onClick = { reportOpen = true },
                    modifier = Modifier.fillMaxWidth(),
                ) { Text("Laporkan toko ini") }
            }
        }
    }

    ReportDialog(
        open = reportOpen,
        title = "Laporkan toko",
        onDismiss = { reportOpen = false },
        onSubmit = { reason ->
            viewModel.reportShop(shopId, reason)
            reportOpen = false
        },
    )
}

@Composable
private fun DetailRow(label: String, value: String) {
    Row(Modifier.fillMaxWidth().padding(vertical = 2.dp)) {
        Text(label, style = MaterialTheme.typography.bodyMedium, modifier = Modifier.width(90.dp))
        Text(value, style = MaterialTheme.typography.bodyMedium, modifier = Modifier.weight(1f))
    }
}

@Composable
private fun ProductRow(product: UmkmProductDto, onClick: () -> Unit) {
    Card(onClick = onClick, modifier = Modifier.fillMaxWidth()) {
        Column(Modifier.padding(12.dp)) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Text(product.name, style = MaterialTheme.typography.titleMedium, modifier = Modifier.weight(1f))
                if (!product.isAvailable) {
                    SuggestionChip(onClick = {}, label = { Text("Habis") })
                }
            }
            Spacer(Modifier.height(4.dp))
            Text(product.priceLabel ?: "Rp ${product.price}", style = MaterialTheme.typography.bodyLarge)
        }
    }
}

@Composable
fun ReportDialog(
    open: Boolean,
    title: String,
    onDismiss: () -> Unit,
    onSubmit: (String) -> Unit,
) {
    var reason by remember { mutableStateOf("") }
    if (open) {
        AlertDialog(
            onDismissRequest = onDismiss,
            title = { Text(title) },
            text = {
                OutlinedTextField(
                    value = reason,
                    onValueChange = { reason = it },
                    label = { Text("Alasan laporan") },
                    minLines = 3,
                    modifier = Modifier.fillMaxWidth(),
                )
            },
            confirmButton = {
                TextButton(onClick = { onSubmit(reason.trim()) }, enabled = reason.isNotBlank()) { Text("Kirim") }
            },
            dismissButton = { TextButton(onClick = onDismiss) { Text("Batal") } },
        )
    }
}
