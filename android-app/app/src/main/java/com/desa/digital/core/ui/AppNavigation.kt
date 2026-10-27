package com.desa.digital.core.ui

import androidx.compose.runtime.Composable
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.rememberNavController
import com.desa.digital.feature.auth.ui.LoginScreen
import com.desa.digital.feature.home.ui.HomeScreen

/** Rute navigasi dasar (Fase 0). Rute fitur surat/PBB/UMKM/pengaduan menyusul. */
object Routes {
    const val LOGIN = "login"
    const val HOME = "home"
}

@Composable
fun AppNavigation() {
    val navController = rememberNavController()
    NavHost(navController = navController, startDestination = Routes.LOGIN) {
        composable(Routes.LOGIN) {
            LoginScreen(
                onLoginSuccess = {
                    navController.navigate(Routes.HOME) {
                        popUpTo(Routes.LOGIN) { inclusive = true }
                    }
                },
            )
        }
        composable(Routes.HOME) {
            HomeScreen()
        }
    }
}
