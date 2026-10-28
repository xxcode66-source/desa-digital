package com.desa.digital.core.ui

import androidx.compose.runtime.Composable
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.rememberNavController
import com.desa.digital.core.session.SessionViewModel
import com.desa.digital.feature.auth.ui.LoginScreen
import com.desa.digital.feature.auth.ui.OnboardingScreen
import com.desa.digital.feature.auth.ui.RegisterScreen
import com.desa.digital.feature.home.ui.HomeScreen
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

/**
 * Grafik navigasi Fase 1: onboarding -> (login|register) -> buat PIN -> beranda -> profil.
 * Rute awal ditentukan [SessionViewModel] (belum login / terkunci / belum ada PIN).
 * Rute fitur surat/PBB/UMKM/pengaduan menyusul pada Fase 2-5.
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
            HomeScreen(onOpenProfile = { navController.navigate(PROFILE) })
        }
        composable(PROFILE) {
            ProfileScreen(onLoggedOut = {
                navController.navigate(ONBOARDING) {
                    popUpTo(navController.graph.id) { inclusive = true }
                }
            })
        }
    }
}
