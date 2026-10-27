// File build root — Fase 0. AGP 9 memakai built-in Kotlin, jadi tidak ada
// org.jetbrains.kotlin.android. Versi diambil dari catalog (gradle/libs.versions.toml).
plugins {
    alias(libs.plugins.android.application) apply false
    alias(libs.plugins.kotlin.compose) apply false
    alias(libs.plugins.kotlin.serialization) apply false
    alias(libs.plugins.ksp) apply false
    alias(libs.plugins.hilt) apply false
}
