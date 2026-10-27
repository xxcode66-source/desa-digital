package com.desa.digital.core.work

import android.content.Context
import androidx.hilt.work.HiltWorker
import androidx.work.CoroutineWorker
import androidx.work.WorkerParameters
import dagger.assisted.Assisted
import dagger.assisted.AssistedInject

/**
 * Kerangka antrean upload offline (PEMBANGUNAN.md bagian 2 & 6): aksi penting seperti
 * upload foto lampiran/SPPT masuk antrean WorkManager saat sinyal jelek.
 * Fase 0 hanya menyediakan struktur; logika upload nyata menyusul di fasenya.
 */
@HiltWorker
class UploadWorker @AssistedInject constructor(
    @Assisted appContext: Context,
    @Assisted params: WorkerParameters,
) : CoroutineWorker(appContext, params) {

    override suspend fun doWork(): Result {
        // no-op fondasi — implementasi upload per fitur dilakukan pada fasenya.
        return Result.success()
    }

    companion object { const val UNIQUE_NAME = "desa_upload_queue" }
}
