package com.desa.digital.core.di

import com.desa.digital.core.data.TokenStore
import com.desa.digital.core.network.ApiService
import dagger.Module
import dagger.Provides
import dagger.hilt.InstallIn
import dagger.hilt.components.SingletonComponent
import javax.inject.Singleton
import kotlinx.serialization.json.Json
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.Retrofit
import com.jakewharton.retrofit2.converter.kotlinx.serialization.asConverterFactory
import retrofit2.create

// TODO (Fase 1): pindahkan ke BuildConfig / env agar bisa dev-vs-prod tanpa ubah kode.
private const val BASE_URL = "https://api.desadigital.example/"

@Module
@InstallIn(SingletonComponent::class)
object NetworkModule {

    @Provides
    @Singleton
    fun provideJson(): Json = Json {
        ignoreUnknownKeys = true
        coerceInputValues = true
    }

    @Provides
    @Singleton
    fun provideOkHttp(tokenStore: TokenStore): OkHttpClient {
        val logging = HttpLoggingInterceptor().apply { level = HttpLoggingInterceptor.Level.BODY }
        return OkHttpClient.Builder()
            .addInterceptor { chain ->
                val req = chain.request()
                val auth = tokenStore.authorizationHeader
                if (auth != null && !req.headers.hasHeader("Authorization")) {
                    req.newBuilder().header("Authorization", auth).build().let(chain::proceed)
                } else {
                    chain.proceed(req)
                }
            }
            .addInterceptor(logging)
            .build()
    }

    @Provides
    @Singleton
    fun provideRetrofit(client: OkHttpClient, json: Json): Retrofit = Retrofit.Builder()
        .baseUrl(BASE_URL)
        .client(client)
        .addConverterFactory(json.asConverterFactory("application/json".toMediaType()))
        .build()

    @Provides
    @Singleton
    fun provideApiService(retrofit: Retrofit): ApiService = retrofit.create()
}

private fun okhttp3.Headers.hasHeader(name: String) = names().any { it.equals(name, true) }
