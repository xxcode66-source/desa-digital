package com.desa.digital

import android.app.Application
import androidx.hilt.work.HiltWorkerFactory
import androidx.work.Configuration
import dagger.hilt.android.HiltAndroidApp
import javax.inject.Inject

/**
 * Application class Fase 0.
 * Mengaktifkan Hilt dan menyediakan konfigurasi WorkManager (antrean upload offline,
 * lihat PEMBANGUNAN.md bagian 2 & 4.3). Worker bisnis akan ditambahkan pada fasenya.
 */
@HiltAndroidApp
class DesaDigitalApplication : Application(), Configuration.Provider {

    @Inject lateinit var workerFactory: HiltWorkerFactory

    override val workManagerConfiguration: Configuration
        get() = Configuration.Builder()
            .setWorkerFactory(workerFactory)
            .build()
}
