package com.desa.digital.feature.lock.ui

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.unit.dp
import androidx.fragment.app.FragmentActivity
import androidx.hilt.navigation.compose.hiltViewModel
import com.desa.digital.core.security.BiometricHelper
import com.desa.digital.core.session.SessionViewModel

/**
 * Buat PIN 6 digit setelah login/registrasi (Fase 1). Opsional mengaktifkan biometrik
 * bila perangkat mendukung. PIN disimpan ter-hash via [com.desa.digital.core.security.PinManager].
 */
@Composable
fun CreatePinScreen(
    onDone: () -> Unit,
    viewModel: SessionViewModel = hiltViewModel(),
) {
    var pin by remember { mutableStateOf("") }
    var confirm by remember { mutableStateOf("") }
    var error by remember { mutableStateOf<String?>(null) }
    var stage by remember { mutableStateOf(Stage.ENTER) }
    val biometricAvailable = remember { viewModel.biometricAvailable() }
    var biometricOn by remember { mutableStateOf(false) }

    fun submit() {
        error = when {
            pin.length != 6 || pin.any { !it.isDigit() } -> "PIN harus 6 digit angka."
            stage == Stage.ENTER -> { stage = Stage.CONFIRM; pin = ""; null }
            pin != confirm -> { stage = Stage.ENTER; pin = ""; confirm = ""; "PIN tidak cocok, ulangi." }
            viewModel.setPin(pin) -> { error = null; if (biometricOn) viewModel.setBiometric(true); onDone(); null }
            else -> "Gagal menyimpan PIN."
        }
    }

    PinScaffold(
        title = if (stage == Stage.ENTER) "Buat PIN (6 digit)" else "Ulangi PIN kamu",
        value = pin,
        onValueChange = { if (it.length <= 6) pin = it },
        error = error,
        submitLabel = if (stage == Stage.ENTER) "Lanjut" else "Simpan PIN",
        onSubmit = ::submit,
        extra = {
            if (stage == Stage.CONFIRM && biometricAvailable) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Switch(checked = biometricOn, onCheckedChange = { biometricOn = it })
                    Spacer(Modifier.width(8.dp))
                    Text("Aktifkan buka kunci sidik jari")
                }
                Spacer(Modifier.height(16.dp))
            }
        },
    )
}

/** Gerbang buka kunci saat aplikasi dibuka ulang bila PIN sudah ada. */
@Composable
fun LockScreen(
    onUnlocked: () -> Unit,
    viewModel: SessionViewModel = hiltViewModel(),
) {
    var pin by remember { mutableStateOf("") }
    var error by remember { mutableStateOf<String?>(null) }
    val context = LocalContext.current
    val activity = context as? FragmentActivity
    val biometricEnabled = remember { viewModel.isBiometricEnabled() }

    fun unlock() {
        if (viewModel.verifyPin(pin)) onUnlocked()
        else { error = "PIN salah."; pin = "" }
    }

    // Tawarkan biometrik otomatis saat masuk layar (kalau aktif & tersedia).
    LaunchedEffect(Unit) {
        if (biometricEnabled && activity != null) {
            BiometricHelper.prompt(activity, onSuccess = onUnlocked, onError = { })
        }
    }

    PinScaffold(
        title = "Desa Digital terkunci",
        value = pin,
        onValueChange = { if (it.length <= 6) pin = it },
        error = error,
        submitLabel = "Buka",
        onSubmit = ::unlock,
        extra = {
            if (biometricEnabled && activity != null) {
                Spacer(Modifier.height(12.dp))
                OutlinedButton(
                    onClick = { BiometricHelper.prompt(activity, onSuccess = onUnlocked, onError = { error = it }) },
                    modifier = Modifier.fillMaxWidth().height(52.dp),
                ) { Text("Pakai sidik jari") }
            }
        },
    )
}

private enum class Stage { ENTER, CONFIRM }

@Composable
private fun PinScaffold(
    title: String,
    value: String,
    onValueChange: (String) -> Unit,
    error: String?,
    submitLabel: String,
    onSubmit: () -> Unit,
    extra: @Composable () -> Unit = {},
) {
    Surface(modifier = Modifier.fillMaxSize()) {
        Column(
            modifier = Modifier.fillMaxSize().padding(28.dp),
            verticalArrangement = Arrangement.Center,
        ) {
            Text(title, style = MaterialTheme.typography.headlineMedium)
            Spacer(Modifier.height(20.dp))
            OutlinedTextField(
                value = value,
                onValueChange = onValueChange,
                label = { Text("PIN") },
                singleLine = true,
                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.NumberPassword),
                visualTransformation = PasswordVisualTransformation(),
                modifier = Modifier.fillMaxWidth(),
            )
            if (error != null) {
                Spacer(Modifier.height(12.dp))
                Text(error, color = MaterialTheme.colorScheme.error)
            }
            Spacer(Modifier.height(24.dp))
            Button(onClick = onSubmit, modifier = Modifier.fillMaxWidth().height(56.dp)) {
                Text(submitLabel, style = MaterialTheme.typography.labelLarge)
            }
            Spacer(Modifier.height(16.dp))
            extra()
        }
    }
}
