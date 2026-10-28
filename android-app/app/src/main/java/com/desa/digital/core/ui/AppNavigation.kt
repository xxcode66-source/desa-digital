package com.desa.digital.core.ui

import androidx.compose.runtime.Composable
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.navigation.NavType
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.rememberNavController
import androidx.navigation.navArgument
import com.desa.digital.core.session.SessionViewModel
import com.desa.digital.feature.auth.ui.LoginScreen
import com.desa.digital.feature.auth.ui.OnboardingScreen
import com.desa.digital.feature.auth.ui.RegisterScreen
import com.desa.digital.feature.complaint.ui.ComplaintDetailScreen
import com.desa.digital.feature.complaint.ui.ComplaintFormScreen
import com.desa.digital.feature.complaint.ui.ComplaintListScreen
import com.desa.digital.feature.home.ui.HomeScreen
import com.desa.digital.feature.letter.ui.LetterDetailScreen
import com.desa.digital.feature.letter.ui.LetterListScreen
import com.desa.digital.feature.letter.ui.LetterWizardScreen
import com.desa.digital.feature.lock.ui.CreatePinScreen
import com.desa.digital.feature.lock.ui.LockScreen
import com.desa.digital.feature.profile.ui.ProfileScreen
import com.desa.digital.core.session.SessionViewModel.Companion.CREATE_PIN
import com.desa.digital.core.session.SessionViewModel.Companion.HOME
import com.desa.digital.core.session.SessionViewModel.Companion.LOCK
import com.desa.digital.core.session.SessionViewModel.Companion.LOGIN
import com.desa.digital.core.session.SessionViewModel.Companion.ONBOARDING
import com.desa.digital.core.session.SessionViewModel.Companion.PROFILE
import com.desa.digital.core.session.SessionViewModel.Companion.REGISTER

/** Rute modul Surat Fase 2. */
const val LETTERS = "letters"
const val LETTER_NEW = "letters/new"
const val LETTER_DETAIL = "letters/{letterId}"
const val LETTER_REPAIR = "letters/{letterId}/repair"

/** Rute modul Pengaduan Fase 3. */
const val COMPLAINTS = "complaints"
const val COMPLAINT_NEW = "complaints/new"
const val COMPLAINT_DETAIL = "complaints/{complaintId}"

private fun letterIdArg() = navArgument("letterId") { type = NavType.LongType }
private fun complaintIdArg() = navArgument("complaintId") { type = NavType.LongType }

/**
 * Grafik navigasi Fase 1+2: onboarding -> (login|register) -> buat PIN -> beranda -> profil,
 * ditambah modul surat (daftar -> wizard -> detail -> perbaikan). Rute awal ditentukan
 * [SessionViewModel]. PBB/UMKM/pengaduan menyusul pada Fase 3-5.
 */
@Composable
fun AppNavigation(sessionViewModel: SessionViewModel = hiltViewModel()) {
    val navController = rememberNavController()

    // Setelah login/registrasi sukses: wajib buat PIN dulu bila belum ada.
    fun goAuthenticated() {
        val target = if (sessionViewModel.hasPin()) HOME else CREATE_PIN
        navController.navigate(target) {
            popUpTo(ONBOARDING) { inclusive = true }
            popUpTo(LOGIN) { inclusive = true }
            popUpTo(REGISTER) { inclusive = true }
        }
    }

    NavHost(navController = navController, startDestination = sessionViewModel.initialRoute()) {
        composable(ONBOARDING) {
            OnboardingScreen(
                onLogin = { navController.navigate(LOGIN) },
                onRegister = { navController.navigate(REGISTER) },
            )
        }
        composable(LOGIN) {
            LoginScreen(onLoginSuccess = ::goAuthenticated)
        }
        composable(REGISTER) {
            RegisterScreen(
                onRegisterSuccess = ::goAuthenticated,
                onBackToLogin = { navController.popBackStack(LOGIN, inclusive = false) },
            )
        }
        composable(CREATE_PIN) {
            CreatePinScreen(onDone = {
                navController.navigate(HOME) { popUpTo(CREATE_PIN) { inclusive = true } }
            })
        }
        composable(LOCK) {
            LockScreen(onUnlocked = {
                navController.navigate(HOME) { popUpTo(LOCK) { inclusive = true } }
            })
        }
        composable(HOME) {
            HomeScreen(
                onOpenProfile = { navController.navigate(PROFILE) },
                onOpenLetters = { navController.navigate(LETTERS) },
                onOpenComplaints = { navController.navigate(COMPLAINTS) },
            )
        }
        composable(PROFILE) {
            ProfileScreen(onLoggedOut = {
                navController.navigate(ONBOARDING) {
                    popUpTo(navController.graph.id) { inclusive = true }
                }
            })
        }

        // ---- Fase 2: Surat ----
        composable(LETTERS) {
            LetterListScreen(
                onBack = { navController.popBackStack() },
                onNewLetter = { navController.navigate(LETTER_NEW) },
                onOpenDetail = { id -> navController.navigate("letters/$id") },
            )
        }
        composable(LETTER_NEW) {
            LetterWizardScreen(
                onBack = { navController.popBackStack() },
                onOpenDetail = { id ->
                    navController.navigate("letters/$id") {
                        popUpTo(LETTERS) { inclusive = false }
                    }
                },
            )
        }
        composable(LETTER_DETAIL, arguments = listOf(letterIdArg())) { entry ->
            LetterDetailScreen(
                letterId = entry.arguments?.getLong("letterId") ?: 0L,
                onBack = { navController.popBackStack() },
                onRepair = { id -> navController.navigate("letters/$id/repair") },
            )
        }
        composable(LETTER_REPAIR, arguments = listOf(letterIdArg())) { entry ->
            LetterWizardScreen(
                repairLetterId = entry.arguments?.getLong("letterId"),
                onBack = { navController.popBackStack() },
                onOpenDetail = { id -> navController.navigate("letters/$id") },
            )
        }

        // ---- Fase 3: Pengaduan ----
        composable(COMPLAINTS) {
            ComplaintListScreen(
                onBack = { navController.popBackStack() },
                onNewComplaint = { navController.navigate(COMPLAINT_NEW) },
                onOpenDetail = { id -> navController.navigate("complaints/$id") },
            )
        }
        composable(COMPLAINT_NEW) {
            ComplaintFormScreen(
                onBack = { navController.popBackStack() },
                onOpenDetail = { id ->
                    navController.navigate("complaints/$id") {
                        popUpTo(COMPLAINTS) { inclusive = false }
                    }
                },
            )
        }
        composable(COMPLAINT_DETAIL, arguments = listOf(complaintIdArg())) { entry ->
            ComplaintDetailScreen(
                complaintId = entry.arguments?.getLong("complaintId") ?: 0L,
                onBack = { navController.popBackStack() },
            )
        }
    }
}
