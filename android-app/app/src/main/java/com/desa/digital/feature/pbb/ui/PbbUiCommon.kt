package com.desa.digital.feature.pbb.ui

import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.unit.dp
import androidx.compose.foundation.layout.padding

/** Format nominal rupiah sederhana (pemisah ribuan titik, tanpa desimal). */
fun rupiah(value: Double): String {
    val negative = value < 0
    val bulat = kotlin.math.abs(value).toLong().toString()
    val grouped = bulat.reversed().chunked(3).joinToString(".").reversed()
    return (if (negative) "-" else "") + "Rp " + grouped
}

/** Tanggal "2026-10-28" -> "28.10.2026"; nilai mentah lain ditampilkan apa adanya. */
fun tanggalIso(raw: String?): String {
    val d = raw?.substringBefore('T') ?: return "-"
    val parts = d.split("-")
    return if (parts.size == 3) "${parts[2]}.${parts[1]}.${parts[0]}" else d
}

/** Penanda status berwarna untuk tagihan/klaim/setoran (label sudah disiapkan server). */
@Composable
fun PbbStatusChip(label: String?, status: String) {
    val container = when (status) {
        "lunas", "approved" -> MaterialTheme.colorScheme.secondaryContainer
        "ditolak", "rejected" -> MaterialTheme.colorScheme.errorContainer
        "disetor_pending", "pending" -> MaterialTheme.colorScheme.tertiaryContainer
        else -> MaterialTheme.colorScheme.surfaceVariant
    }
    Surface(color = container, shape = MaterialTheme.shapes.small) {
        Text(
            label ?: status,
            style = MaterialTheme.typography.labelLarge,
            modifier = Modifier.padding(horizontal = 10.dp, vertical = 4.dp),
            color = Color.Unspecified,
        )
    }
}

/** Disclaimer wajib: info sementara & pelunasan resmi mengikuti kantor pajak (§4.3). */
@Composable
fun PbbDisclaimer(text: String) {
    Surface(
        color = MaterialTheme.colorScheme.surfaceVariant,
        shape = MaterialTheme.shapes.medium,
        modifier = Modifier.padding(0.dp),
    ) {
        Text(text, style = MaterialTheme.typography.bodySmall, modifier = Modifier.padding(12.dp))
    }
}
