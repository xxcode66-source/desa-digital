package com.desa.digital.feature.auth.ui

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import com.desa.digital.feature.auth.RegisterUiState
import com.desa.digital.feature.auth.RegisterViewModel

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun RegisterScreen(
    onRegisterSuccess: () -> Unit,
    onBackToLogin: () -> Unit,
    viewModel: RegisterViewModel = hiltViewModel(),
) {
    val state by viewModel.state.collectAsState()

    var villageId by remember { mutableStateOf("1") }
    var nik by remember { mutableStateOf("") }
    var name by remember { mutableStateOf("") }
    var phone by remember { mutableStateOf("") }
    var password by remember { mutableStateOf("") }
    var confirm by remember { mutableStateOf("") }
    var rt by remember { mutableStateOf("") }
    var rw by remember { mutableStateOf("") }
    var consent by remember { mutableStateOf(false) }
    var otp by remember { mutableStateOf("") }

    val showError = state is RegisterUiState.Error
    val loading = state is RegisterUiState.Loading

    LaunchedEffect(state) {
        if (state is RegisterUiState.Success) onRegisterSuccess()
    }

    Scaffold(topBar = { Text("Pendaftaran Warga", style = MaterialTheme.typography.headlineMedium) }) { padding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding)
                .padding(24.dp)
                .verticalScroll(rememberScrollState()),
        ) {
            if (state is RegisterUiState.SentOtp) {
                // FASE OTP — konfirmasi nomor dengan kode 6 digit.
                val sent = state as RegisterUiState.SentOtp
                Text("Kode verifikasi telah dikirim ke ${sent.phone}.", style = MaterialTheme.typography.bodyLarge)
                if (sent.debugOtp != null) {
                    Spacer(Modifier.height(8.dp))
                    AssistChip(onClick = { otp = sent.debugOtp }, label = { Text("Dev: isi ${sent.debugOtp}") })
                }
                Spacer(Modifier.height(16.dp))
                OutlinedTextField(
                    value = otp,
                    onValueChange = { if (it.length <= 6) otp = it },
                    label = { Text("Kode verifikasi (6 digit)") },
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.NumberPassword),
                    singleLine = true,
                    modifier = Modifier.fillMaxWidth(),
                )
                if (showError) {
                    Spacer(Modifier.height(12.dp))
                    Text((state as RegisterUiState.Error).message, color = MaterialTheme.colorScheme.error)
                }
                Spacer(Modifier.height(24.dp))
                Button(
                    onClick = { viewModel.submitRegister(otp) },
                    enabled = !loading,
                    modifier = Modifier.fillMaxWidth().height(56.dp),
                ) { if (loading) Loader() else Text("Selesaikan Pendaftaran") }
                Spacer(Modifier.height(8.dp))
                TextButton(onClick = { viewModel.backToForm() }) { Text("Ganti nomor / kembali") }
            } else {
                // FASE FORM — data diri.
                FormField("Kode Desa (village_id)", villageId, KeyboardType.Number) { villageId = it }
                FormField("NIK (16 digit)", nik, KeyboardType.Number) { if (it.length <= 16) nik = it }
                FormField("Nama lengkap", name) { name = it }
                FormField("Nomor HP", phone, KeyboardType.Phone) { phone = it }
                FormField("Password (min 8)", password, KeyboardType.Password, password = true) { password = it }
                FormField("Ulangi password", confirm, KeyboardType.Password, password = true) { confirm = it }
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                    Box(Modifier.weight(1f)) { FormField("RT", rt, KeyboardType.Number) { rt = it } }
                    Box(Modifier.weight(1f)) { FormField("RW", rw, KeyboardType.Number) { rw = it } }
                }
                Spacer(Modifier.height(8.dp))
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Checkbox(checked = consent, onCheckedChange = { consent = it })
                    Text("Saya menyetujui penggunaan data sesuai ketentuan desa.")
                }
                if (showError) {
                    Spacer(Modifier.height(12.dp))
                    Text((state as RegisterUiState.Error).message, color = MaterialTheme.colorScheme.error)
                }
                Spacer(Modifier.height(24.dp))
                Button(
                    onClick = { viewModel.sendOtp(villageId, nik, name, phone, password, confirm, rt, rw, consent) },
                    enabled = !loading,
                    modifier = Modifier.fillMaxWidth().height(56.dp),
                ) { if (loading) Loader() else Text("Kirim Kode Verifikasi") }
                Spacer(Modifier.height(8.dp))
                TextButton(onClick = onBackToLogin) { Text("Sudah punya akun? Masuk") }
            }
        }
    }
}

@Composable
private fun FormField(
    label: String,
    value: String,
    keyboardType: KeyboardType = KeyboardType.Text,
    password: Boolean = false,
    onValueChange: (String) -> Unit,
) {
    OutlinedTextField(
        value = value,
        onValueChange = onValueChange,
        label = { Text(label) },
        singleLine = true,
        keyboardOptions = KeyboardOptions(keyboardType = keyboardType),
        visualTransformation = if (password) PasswordVisualTransformation() else androidx.compose.ui.text.input.VisualTransformation.None,
        modifier = Modifier.fillMaxWidth().padding(bottom = 12.dp),
    )
}

@Composable
private fun Loader() = CircularProgressIndicator(modifier = Modifier.size(24.dp), color = MaterialTheme.colorScheme.onPrimary)
