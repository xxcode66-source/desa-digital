package com.desa.digital.feature.profile.ui

import androidx.compose.foundation.layout.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import com.desa.digital.core.network.dto.UserDto
import com.desa.digital.feature.profile.ProfileUiState
import com.desa.digital.feature.profile.ProfileViewModel

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ProfileScreen(
    onLoggedOut: () -> Unit,
    viewModel: ProfileViewModel = hiltViewModel(),
) {
    val state by viewModel.state.collectAsState()
    val loggedOut by viewModel.loggedOut.collectAsState()

    LaunchedEffect(loggedOut) { if (loggedOut) onLoggedOut() }

    Scaffold(topBar = { TopAppBar(title = { Text("Profil & Status") }) }) { padding ->
        Column(
            modifier = Modifier.fillMaxSize().padding(padding).padding(24.dp),
        ) {
            when (val s = state) {
                ProfileUiState.Loading -> CircularProgressIndicator()
                is ProfileUiState.Error -> Text(s.message, color = MaterialTheme.colorScheme.error)
                is ProfileUiState.Loaded -> ProfileContent(s.user)
            }

            Spacer(Modifier.height(32.dp))
            OutlinedButton(
                onClick = { viewModel.logout(all = false) },
                modifier = Modifier.fillMaxWidth().height(52.dp),
            ) { Text("Keluar dari perangkat ini") }
            Spacer(Modifier.height(8.dp))
            TextButton(onClick = { viewModel.logout(all = true) }) {
                Text("Keluar dari semua perangkat")
            }
        }
    }
}

@Composable
private fun ProfileContent(user: UserDto) {
    val (label, tone) = when (user.status) {
        "verified" -> "Akun terverifikasi" to ColorTone.Success
        "rejected" -> "Verifikasi ditolak — hubungi kantor desa" to ColorTone.Error
        else -> "Menunggu verifikasi operator" to ColorTone.Warning
    }
    val container = when (tone) {
        ColorTone.Success -> MaterialTheme.colorScheme.secondaryContainer
        ColorTone.Error -> MaterialTheme.colorScheme.errorContainer
        ColorTone.Warning -> MaterialTheme.colorScheme.tertiaryContainer
    }

    Surface(color = container, shape = MaterialTheme.shapes.medium, modifier = Modifier.fillMaxWidth()) {
        Text(label, style = MaterialTheme.typography.titleMedium, modifier = Modifier.padding(16.dp))
    }

    Spacer(Modifier.height(20.dp))
    Row { InfoLabel("Nama"); Text(user.name) }
    Spacer(Modifier.height(8.dp))
    Row { InfoLabel("Nomor HP"); Text(user.phone) }
    Spacer(Modifier.height(8.dp))
    Row { InfoLabel("RT/RW"); Text("${user.rt ?: "-"} / ${user.rw ?: "-"}") }
    Spacer(Modifier.height(8.dp))
    Row { InfoLabel("Role"); Text(user.roles.joinToString { it.label ?: it.name }.ifBlank { "warga" }) }

    Spacer(Modifier.height(16.dp))
    HorizontalDivider()
    Spacer(Modifier.height(16.dp))
    Text(
        "Catatan: status 'terverifikasi' berarti identitas kamu disetujui operator desa. " +
            "Pembayaran/PBB di aplikasi ini hanya pencatatan, bukan bukti pelunasan resmi.",
        style = MaterialTheme.typography.bodySmall,
        color = MaterialTheme.colorScheme.onSurfaceVariant,
    )
}

@Composable
private fun InfoLabel(text: String) {
    Text(text, modifier = Modifier.width(110.dp), color = MaterialTheme.colorScheme.onSurfaceVariant)
}

private enum class ColorTone { Success, Warning, Error }
