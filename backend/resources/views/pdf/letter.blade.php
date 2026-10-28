@php
    /** @var \App\Models\Letter $letter */
    $village   = $letter->village;
    $settings  = $village?->settings ?? [];
    $kop       = $settings['kop'] ?? [];
    $applicant = $letter->user;

    // Baris kop: baris besar (nama desa), baris kecil di atasnya (kabupaten/kecamatan), alamat.
    $kopTop    = array_filter((array) ($kop['top'] ?? []));
    $kopName   = $kop['name'] ?? ($village?->name ?? 'Pemerintah Desa');
    $kopBottom = array_filter([
        $kop['address'] ?? null,
        isset($kop['phone']) ? 'Telp. ' . $kop['phone'] : null,
        $kop['email'] ?? null,
    ]);

    // Samarkan NIK untuk cetak (data pribadi — PEMBANGUNAN.md 5): 3301…****…1234
    $nik       = (string) ($applicant?->nik ?? '');
    $nikMask   = strlen($nik) >= 8
        ? substr($nik, 0, 4) . '******' . substr($nik, -4)
        : ($nik !== '' ? $nik : '-');

    // Isi surat: tampilkan field sesuai skema jenis surat (label + nilai dari data_json).
    $schema    = $letter->type?->form_schema_json ?? [];
    $data      = $letter->data_json ?? [];
    $rows      = collect($schema)
        ->map(function (array $f) use ($data) {
            $key   = $f['key'] ?? null;
            $value = $key ? ($data[$key] ?? null) : null;
            if (is_array($value)) {
                $value = implode(', ', $value);
            }

            return $key ? ['label' => $f['label'] ?? $key, 'value' => $value] : null;
        })
        ->filter()
        ->values();

    $tangga    = $letter->completed_at ?? now();
    $kades     = $settings['kades'] ?? null;
    $kadesNip  = $settings['kades_nip'] ?? null;
    $daerah    = $kop['area'] ?? ($village?->name ?? '');
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Surat {{ $letter->number }}</title>
    <style>
        @page { margin: 22mm 18mm; }
        * { font-family: "DejaVu Sans", sans-serif; }
        body { font-size: 12pt; color: #111; line-height: 1.5; }
        .kop { text-align: center; border-bottom: 3px double #111; padding-bottom: 8px; margin-bottom: 18px; }
        .kop .top { font-size: 11pt; letter-spacing: .5px; }
        .kop .name { font-size: 16pt; font-weight: bold; text-transform: uppercase; }
        .kop .bottom { font-size: 9.5pt; }
        .title { text-align: center; margin: 6px 0 16px; }
        .title h2 { margin: 0; text-transform: uppercase; letter-spacing: 1px; }
        .title .nomor { font-size: 11pt; margin-top: 4px; }
        table.meta { width: 100%; border-collapse: collapse; margin: 8px 0 16px; }
        table.meta td { padding: 4px 6px; vertical-align: top; font-size: 11.5pt; }
        table.meta td.l { width: 34%; }
        table.meta td.v { }
        .dot { }
        .ttd { margin-top: 28px; width: 100%; }
        .ttd td { vertical-align: top; font-size: 11.5pt; }
        .ttd .box { width: 50%; text-align: center; }
        .space { height: 62px; }
        .qr { text-align: center; margin-top: 20px; border-top: 1px solid #bbb; padding-top: 10px; }
        .qr img { width: 84px; height: 84px; }
        .qr .url { font-size: 8.5pt; color: #444; word-break: break-all; }
        .qr .note { font-size: 8.5pt; color: #666; margin-top: 2px; }
        .disc { font-size: 8.5pt; color: #666; margin-top: 18px; }
    </style>
</head>
<body>
    <div class="kop">
        @foreach ($kopTop as $line)
            <div class="top">{{ $line }}</div>
        @endforeach
        <div class="name">{{ $kopName }}</div>
        @foreach ($kopBottom as $line)
            <div class="bottom">{{ $line }}</div>
        @endforeach
    </div>

    <div class="title">
        <h2>{{ $letter->type?->name ?? 'Surat Keterangan' }}</h2>
        <div class="nomor">Nomor: {{ $letter->number ?: '-' }}</div>
    </div>

    <p style="margin-bottom:10px;">Yang bertanda tangan di bawah ini Kepala {{ $kopName }}, menerangkan bahwa:</p>

    <table class="meta">
        <tr><td class="l">Nama</td><td class="v">: {{ $applicant?->name ?? '-' }}</td></tr>
        <tr><td class="l">NIK</td><td class="v">: {{ $nikMask }}</td></tr>
        <tr><td class="l">RT / RW</td><td class="v">: {{ $applicant?->rt ?? '-' }} / {{ $applicant?->rw ?? '-' }}</td></tr>
        @foreach ($rows as $row)
            <tr>
                <td class="l">{{ $row['label'] }}</td>
                <td class="v">: {{ $row['value'] !== null && $row['value'] !== '' ? $row['value'] : '-' }}</td>
            </tr>
        @endforeach
    </table>

    <p>Demikian surat keterangan ini dibuat dengan sebenarnya untuk dapat dipergunakan sebagaimana mestinya.</p>

    <table class="ttd">
        <tr>
            <td class="box">
                {{ $daerah ? $daerah . ', ' : '' }}{{ $tangga->translatedFormat('d F Y') }}
                <div>Kepala {{ $kopName }}</div>
                <div class="space"></div>
                <div style="font-weight:bold;text-decoration:underline;">{{ $kades ?: '(_______________________)' }}</div>
                @if ($kadesNip)<div style="font-size:10pt;">NIP. {{ $kadesNip }}</div>@endif
            </td>
            <td class="box">
                <div style="height:24px;"></div>
                <div>Mengetahui, Ketua RT {{ $applicant?->rt ?? '____' }}</div>
                <div class="space"></div>
                <div style="font-weight:bold;text-decoration:underline;">(_______________________)</div>
            </td>
        </tr>
    </table>

    <div class="qr">
        <img src="{{ $qrUri }}" alt="QR Verifikasi">
        <div class="url">{{ $verifyUrl }}</div>
        <div class="note">Pindai kode QR atau buka tautan di atas untuk memverifikasi keaslian surat ini.</div>
    </div>

    <p class="disc">
        Dokumen sah setelah dibubuhi tanda tangan basah dan cap/stempel desa. Nomor registrasi:
        {{ $letter->public_token }}. Diterbitkan melalui Aplikasi Desa Digital.
    </p>
</body>
</html>
