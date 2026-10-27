package com.desa.digital.core.database

import androidx.room.Dao
import androidx.room.Entity
import androidx.room.PrimaryKey
import androidx.room.Query
import androidx.room.Upsert
import kotlinx.coroutines.flow.Flow

/**
 * Cache offline generik (Fase 0). Membuktikan perpipaan Room + KSP berjalan.
 * Entity domain (surat, PBB, pengumuman) ditambahkan pada fase fitur masing-masing.
 */
@Entity(tableName = "cache_entries")
data class CacheEntry(
    @PrimaryKey val key: String,
    val value: String,
    val updatedAt: Long = System.currentTimeMillis(),
)

@Dao
interface CacheDao {
    @Upsert
    suspend fun put(entry: CacheEntry)

    @Query("SELECT value FROM cache_entries WHERE key = :key")
    suspend fun get(key: String): String?

    @Query("SELECT * FROM cache_entries WHERE key = :key")
    fun observe(key: String): Flow<CacheEntry?>

    @Query("DELETE FROM cache_entries")
    suspend fun clear()
}
