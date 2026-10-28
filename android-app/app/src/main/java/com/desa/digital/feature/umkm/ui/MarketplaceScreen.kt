package com.desa.digital.feature.umkm.ui

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.horizontalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.Search
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import com.desa.digital.core.network.dto.UmkmShopDto
import com.desa.digital.feature.umkm.UmkmViewModel

/**
 * Jelajah marketplace UMKM (PEMBANGUNAN.md 4.4): kotak cari, filter kategori (config desa),
 * dan urutan (Terbaru / Terpopuler / Terdekat dari RT saya). Menampilkan toko aktif yang
 * tidak disembunyikan di desa warga. Kartu toko membuka detail + daftar produk.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun MarketplaceScreen(
    onBack: () -> Unit,
    onOpenShop: (Long) -> Unit,
    viewModel: UmkmViewModel = hiltViewModel(),
) {
    val shops by viewModel.shops.collectAsState()
    val categories by viewModel.categories.collectAsState()
    val busy by viewModel.busy.collectAsState()
    val message by viewModel.message.collectAsState()
    val snackbar = remember { SnackbarHostState() }

    var query by remember { mutableStateOf("") }
    var category by remember { mutableStateOf<String?>(null) }
    var sort by remember { mutableStateOf(UmkmViewModel.DEFAULT_SORT) }

    LaunchedEffect(Unit) {
        if (categories.isEmpty()) viewModel.loadCategories()
        viewModel.browse(query, category, sort)
    }
    LaunchedEffect(message) {
        message?.let { snackbar.showSnackbar(it); viewModel.consumeMessage() }
    }

    fun refresh() = viewModel.browse(query, category, sort)

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Marketplace UMKM") },
                navigationIcon = { IconButton(onBack) { Icon(Icons.AutoMirrored.Filled.ArrowBack, "Kembali") } },
            )
        },
        snackbarHost = { SnackbarHost(snackbar) },
    ) { padding ->
        Column(Modifier.padding(padding).padding(16.dp)) {
            OutlinedTextField(
                value = query,
                onValueChange = { query = it },
                placeholder = { Text("Cari nama toko atau usaha…") },
                leadingIcon = { Icon(Icons.Filled.Search, contentDescription = null) },
                singleLine = true,
                modifier = Modifier.fillMaxWidth(),
            )
            Spacer(Modifier.height(8.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
                Button(onClick = ::refresh, modifier = Modifier.height(44.dp)) { Text("Cari") }
                Spacer(Modifier.width(8.dp))
                OutlinedButton(onClick = {
                    query = ""; category = null; sort = UmkmViewModel.DEFAULT_SORT; refresh()
                }, modifier = Modifier.height(44.dp)) { Text("Reset") }
            }
            Spacer(Modifier.height(12.dp))

            // Urutan: Terbaru / Terpopuler / Terdekat (berdasarkan RT warga).
            Row(Modifier.horizontalScroll(rememberScrollState())) {
                SortChip("Terbaru", UmkmViewModel.DEFAULT_SORT, sort) { sort = it; refresh() }
                SortChip("Terpopuler", UmkmViewModel.SORT_POPULER, sort) { sort = it; refresh() }
                SortChip("Terdekat", UmkmViewModel.SORT_TERDEKAT, sort) { sort = it; refresh() }
            }
            Spacer(Modifier.height(8.dp))

            CategoryDropdown(categories, category) { category = it; refresh() }
            Spacer(Modifier.height(12.dp))

            if (busy && shops.isEmpty()) {
                CircularProgressIndicator(modifier = Modifier.align(Alignment.CenterHorizontally))
            } else if (shops.isEmpty()) {
                Text(
                    "Belum ada toko yang tampil. Coba ubah kata kunci, kategori, atau urutan.",
                    style = MaterialTheme.typography.bodyLarge,
                )
            } else {
                LazyColumn(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    items(shops, key = { it.id }) { shop ->
                        ShopCard(shop) { onOpenShop(shop.id) }
                    }
                }
            }
        }
    }
}

@Composable
private fun SortChip(label: String, value: String, current: String, onSelect: (String) -> Unit) {
    FilterChip(selected = current == value, onClick = { onSelect(value) }, label = { Text(label) })
    Spacer(Modifier.width(6.dp))
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun CategoryDropdown(
    categories: List<com.desa.digital.core.network.dto.UmkmCategoryDto>,
    selected: String?,
    onSelect: (String?) -> Unit,
) {
    var expanded by remember { mutableStateOf(false) }
    val label = categories.firstOrNull { it.code == selected }?.label ?: "Semua kategori"
    ExposedDropdownMenuBox(expanded = expanded, onExpandedChange = { expanded = it }) {
        OutlinedTextField(
            value = label,
            onValueChange = {},
            readOnly = true,
            label = { Text("Kategori") },
            modifier = Modifier.fillMaxWidth().menuAnchor(),
        )
        ExposedDropdownMenu(expanded = expanded, onDismissRequest = { expanded = false }) {
            DropdownMenuItem(text = { Text("Semua kategori") }, onClick = { expanded = false; onSelect(null) })
            categories.forEach { c ->
                DropdownMenuItem(text = { Text(c.label) }, onClick = { expanded = false; onSelect(c.code) })
            }
        }
    }
}

@Composable
private fun ShopCard(shop: UmkmShopDto, onClick: () -> Unit) {
    Card(onClick = onClick, modifier = Modifier.fillMaxWidth()) {
        Column(Modifier.padding(14.dp)) {
            Text(shop.name, style = MaterialTheme.typography.titleMedium)
            Spacer(Modifier.height(4.dp))
            Text(shop.categoryLabel ?: shop.category, style = MaterialTheme.typography.labelLarge)
            if (!shop.description.isNullOrBlank()) {
                Spacer(Modifier.height(6.dp))
                Text(
                    shop.description,
                    style = MaterialTheme.typography.bodyMedium,
                    maxLines = 2,
                    overflow = androidx.compose.ui.text.style.TextOverflow.Ellipsis,
                )
            }
            Spacer(Modifier.height(8.dp))
            val lokasi = listOfNotNull(
                shop.rt?.takeIf { it.isNotBlank() }?.let { "RT $it" },
                shop.rw?.takeIf { it.isNotBlank() }?.let { "RW $it" },
            ).joinToString(" ")
            Text(
                "${shop.productsCount} produk" + (lokasi.takeIf { it.isNotBlank() }?.let { " · $it" } ?: ""),
                style = MaterialTheme.typography.bodySmall,
            )
        }
    }
}
