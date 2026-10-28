<div
    class="flex flex-col gap-4 rounded-xl bg-gradient-to-br from-emerald-600 via-emerald-500 to-teal-500 p-6 text-white shadow-sm sm:flex-row sm:items-center sm:justify-between dark:from-emerald-700 dark:via-emerald-700 dark:to-teal-800"
>
    <div>
        <p class="text-xs font-semibold uppercase tracking-widest text-emerald-50/80">
            Panel Layanan Desa
        </p>
        <h2 class="mt-1 text-2xl font-bold leading-tight">{{ $this->villageName() }}</h2>
        <p class="mt-1 text-sm text-emerald-50/90">
            {{ now()->translatedFormat('l, j F Y', null, 'id') }}
        </p>
    </div>

    <div class="rounded-lg bg-white/15 px-4 py-3 text-right ring-1 ring-white/20 backdrop-blur">
        <p class="text-xs text-emerald-50/80">Masuk sebagai</p>
        <p class="text-base font-semibold">{{ $this->userName() }}</p>
        <p class="text-xs text-emerald-50/90">{{ $this->userRole() }}</p>
    </div>
</div>
