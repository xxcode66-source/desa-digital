package com.desa.digital.core.di

import android.content.Context
import androidx.room.Room
import com.desa.digital.core.database.AppDatabase
import com.desa.digital.core.database.CacheDao
import dagger.Module
import dagger.Provides
import dagger.hilt.InstallIn
import dagger.hilt.android.qualifiers.ApplicationContext
import dagger.hilt.components.SingletonComponent
import javax.inject.Singleton

@Module
@InstallIn(SingletonComponent::class)
object DatabaseModule {

    @Provides
    @Singleton
    fun provideDatabase(@ApplicationContext context: Context): AppDatabase =
        Room.databaseBuilder(context, AppDatabase::class.java, "desa_digital.db").build()

    @Provides
    fun provideCacheDao(db: AppDatabase): CacheDao = db.cacheDao()
}
