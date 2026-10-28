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
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import com.desa.digital.feature.umkm.UmkmViewModel

/**
 * Detail satu produk UMKM (PEMBANGUNAN.md 4.4): nama, harga, ketersediaan, foto, tokonya,
 * tombol "Chat WhatsApp" (pesan otomatis menyebut produk), dan aksi lapor moderasi.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ProductDetailScreen(
    productId: Long,
    onBack: () -> Unit,
    onOpenShop: (Long) -> Unit,
    viewModel: UmkmViewModel = hiltViewModel(),
) {
    val product by viewModel.productDetail.collectAsState()
    val busy by viewModel.busy.collectAsState()
    val message by viewModel.message.collectAsState()
    val waUrl by viewModel.waUrl.collectAsState()
    val snackbar = remember { SnackbarHostState() }
    val openUrl = rememberOpenUrl()
    var reportOpen by remember { mutableStateOf(false) }

    LaunchedEffect(productId) { viewModel.loadProduct(productId) }
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
                title = { Text("Detail Produk") },
                navigationIcon = { IconButton(onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, "Kembali") } },
                actions = { IconButton(onClick = { reportOpen = true }) { Icon(Icons.Filled.Warning, "Lapor produk") } },
            )
        },
        snackbarHost = { SnackbarHost(snackbar) },
    ) { padding ->
        val p = product
        Column(
            Modifier.padding(padding).padding(20.dp).fillMaxSize()
                .verticalScroll(rememberScrollState()),
        ) {
            if (p == null) {
                if (busy) CircularProgressIndicator() else Text("Memuat produk…")
            } else {
                Text(p.name, style = MaterialTheme.typography.headlineSmall)
                Spacer(Modifier.height(6.dp))
                Text(p.priceLabel ?: "Rp ${p.price}", style = MaterialTheme.typography.titleLarge)
                Spacer(Modifier.height(8.dp))
                AssistChip(
                    onClick = {},
                    label = { Text(if (p.isAvailable) "Tersedia" else "Stok habis / tidak tersedia") },
                )
                if (!p.description.isNullOrBlank()) {
                    Spacer(Modifier.height(12.dp))
                    Text(p.description, style = MaterialTheme.typography.bodyMedium)
                }

                if (p.photos.isNotEmpty()) {
                    Spacer(Modifier.height(16.dp))
                    Text("Foto produk (${p.photos.size})", style = MaterialTheme.typography.titleSmall)
                    Spacer(Modifier.height(4.dp))
                    p.photos.forEach { Text("• ${it.name ?: "foto"}", style = MaterialTheme.typography.bodySmall) }
                }

                if (!p.shopName.isNullOrBlank()) {
                    Spacer(Modifier.height(16.dp))
                    Card(onClick = { p.shopId?.let(onOpenShop) }, modifier = Modifier.fillMaxWidth()) {
                        Text("Dari toko: ${p.shopName}", style = MaterialTheme.typography.bodyLarge, modifier = Modifier.padding(14.dp))
                    }
                }

                Spacer(Modifier.height(20.dp))
                Button(
                    onClick = { viewModel.requestWa(p.shopId ?: 0L, p.id) },
                    enabled = !busy && p.shopId != null,
                    modifier = Modifier.fillMaxWidth().height(52.dp),
                ) {
                    Icon(Icons.Filled.Phone, contentDescription = null)
                    Spacer(Modifier.width(8.dp))
                    Text("Chat WhatsApp")
                }

                Spacer(Modifier.height(12.dp))
                OutlinedButton(onClick = { reportOpen = true }, modifier = Modifier.fillMaxWidth()) {
                    Text("Laporkan produk ini")
                }
            }
        }
    }

    ReportDialog(
        open = reportOpen,
        title = "Laporkan produk",
        onDismiss = { reportOpen = false },
        onSubmit = { reason ->
            viewModel.reportProduct(productId, reason)
            reportOpen = false
        },
    )
}
