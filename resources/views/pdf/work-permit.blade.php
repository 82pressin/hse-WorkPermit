@php
    $c = fn ($d) => \Carbon\Carbon::parse($d)->locale('id');
    $start = $c($permit->start_date);
    $end = $c($permit->end_date);

    if ($start->isSameDay($end)) {
        $range = $start->translatedFormat('j F Y');
    } elseif ($start->year === $end->year) {
        $range = $start->translatedFormat('j F') . ' - ' . $end->translatedFormat('j F Y');
    } else {
        $range = $start->translatedFormat('j F Y') . ' - ' . $end->translatedFormat('j F Y');
    }

    $time = \Carbon\Carbon::parse($permit->start_time)->format('H.i') . ' - '
          . \Carbon\Carbon::parse($permit->end_time)->format('H.i') . ' WIB';

    $issued = $permit->issued_at ? $c($permit->issued_at)->translatedFormat('j F Y') : null;

    $types = [
        'is_hot_work' => 'Panas',
        'is_work_at_height' => 'Di Ketinggian (> 1,8 meter)',
        'is_confined_space' => 'Ruang terbatas',
        'is_excavation' => 'Penggalian',
        'is_chemical' => 'Pekerjaan Kimia',
        'is_electrical' => 'Elektrikal',
        'is_mechanical' => 'Mekanikal',
    ];

    $requirements = [
        'Sebelum melakukan pekerjaan, terlebih dahulu buat JSEA ( Job Safety Environment Analysis )',
        'Harus mematuhi aturan-aturan yang berlaku di PT.KRM',
        'Semua karyawan harus memakai APD ( Alat Pelindung Diri ) sesuai dengan jenis pekerjaan',
        'Memiliki Petugas Safety ( Safety Officer )',
        'Jika ada pekerjaan pengelasan, maka harus menyediakan APAR ( Alat Pemadam Api Ringan )',
        'Semua karyawan harus terdaftar di Pos Keamanan ( lampiran Tanda Pengenal )',
        'Jika terjadi kerusakan lingkungan, maka kontraktor harus bertanggung jawab.',
        'Dilarang melakukan pembersihan alat-alat kerja menggunakan bahan mudah terbakar.',
        'Lakukan pemeriksaan akhir 1/2 sampai 1 jam setelah pekerjaan menggunakan api selesai',
    ];
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<style>
    @page { margin: 28px 36px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #111; }
    table { border-collapse: collapse; width: 100%; }
    .head td { border: 1px solid #000; padding: 5px 7px; vertical-align: middle; }
    .center { text-align: center; }
    .big { font-size: 15px; font-weight: bold; }
    .field td { padding: 6px 0; vertical-align: top; }
    .label { width: 150px; }
    .line { border-bottom: 1px solid #000; font-weight: bold; }
    .req td { padding: 5px 0; vertical-align: top; }
    .draft { background: #fde8e8; color: #b00020; border: 1px solid #b00020;
             text-align: center; padding: 5px; font-weight: bold; margin-bottom: 10px; }
</style>
</head>
<body>

@if ($isDraft)
    <div class="draft">DRAFT &mdash; belum ditandatangani GM, bukan dokumen resmi</div>
@endif

<table class="head">
    <tr>
        <td rowspan="2" style="width: 34%;">
            <strong style="font-size: 12px;">PT. KRAMA YUDHA RATU MOTOR</strong><br>
            <span style="font-size: 8.5px;">
                Jl.Raya Bekasi km.21-22<br>
                Rawa Terate, Cakung, Jakarta 13920<br>
                Telp.021-4602905, Fax.021-4602904<br>
                P.O.Box-1321 Jakarta 13013
            </span>
        </td>
        <td class="center big" style="width: 38%;">FORMULIR IJIN KERJA</td>
        <td style="font-size: 9px;">
            No Dokumen : FRM-HSE-007<br>
            Tgl Berlaku : 27 Maret 2018<br>
            No. Revisi : 01 (3-12-2018)
        </td>
    </tr>
    <tr>
        <td class="center big">WORK PERMIT</td>
        <td></td>
    </tr>
</table>

<table class="field" style="margin-top: 14px;">
    <tr>
        <td class="label">No. Ijin :</td>
        <td class="line">{{ $permit->permit_number ?? '(nomor dibuat saat diajukan ke GM)' }}</td>
        <td style="width: 95px; text-align: right;">Tanggal terbit :</td>
        <td class="line" style="width: 110px;">{{ $issued ?? '-' }}</td>
    </tr>
</table>

<table class="field">
    <tr><td class="label">Nama Perusahaan :</td><td class="line">{{ $permit->vendor_name }}</td></tr>
    <tr><td class="label">Sesuai No.Surat Permohonan :</td><td class="line">&nbsp;</td></tr>
    <tr><td class="label">Uraian Pekerjaan :</td><td class="line">{{ $permit->job_description }}</td></tr>
    <tr><td class="label">Lokasi :</td><td class="line">{{ $permit->location }}</td></tr>
</table>

<table class="field">
    <tr>
        <td class="label">Jenis Pekerjaan :</td>
        <td>
            <table>
                @foreach (array_chunk($types, 4, true) as $row)
                    <tr>
                        @foreach ($row as $field => $label)
                            <td style="padding: 3px 0;">{!! $permit->{$field} ? '&#9745;' : '&#9744;' !!} {{ $label }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </table>
        </td>
    </tr>
</table>

<table class="field">
    <tr><td class="label">Tanggal pelaksanaan :</td><td><strong>{{ $range }}</strong></td></tr>
    <tr><td class="label">Jam :</td><td><strong>{{ $time }}</strong></td></tr>
    <tr><td class="label">Jumlah Personil :</td><td class="line">{{ $permit->personnel_count }} Personil</td></tr>
</table>

<p style="margin-top: 14px;"><u><strong>Ijin Kerja yang Aman</strong></u></p>
<p>Ijin Kerja ini hanya untuk pekerjaan yang tertera di atas.</p>
<p>Pekerjaan boleh dimulai setelah persyaratan dipenuhi, yaitu :</p>

<table class="req">
    @foreach ($requirements as $i => $text)
        <tr>
            <td style="width: 20px;">{{ $i + 1 }}</td>
            <td>{{ $text }}</td>
            <td style="width: 24px; text-align: center;">&#9745;</td>
        </tr>
    @endforeach
</table>

<p style="margin-top: 10px;">
    <strong>Demikianlah hal ini kami sampaikan untuk dilaksanakan<br>
    Atas kerjasamanya kami ucapkan terima kasih.</strong>
</p>

<table style="margin-top: 6px;">
    <tr>
        <td style="width: 52%;"></td>
        <td>
            Jakarta, {{ $issued ?? '..............' }}<br>
            PT. Krama Yudha Ratu Motor<br>
            <strong>Departemen General Affair</strong>
            <div style="height: 72px; padding-top: 10px;">
                @if ($approval)
                    <span style="color: #1a7f37;">&#10003; Disetujui secara elektronik</span><br>
                    <span style="font-size: 9px;">{{ $approval->approved_at->format('d/m/Y H:i') }} WIB</span>
                @endif
            </div>
            <u><strong>{{ $approval?->approver?->name ?? '..........................' }}</strong></u><br>
            General Manager HR &amp; GA
        </td>
    </tr>
</table>

<p style="font-size: 9px; margin-top: 18px;">Catatan : Pengajuan Ijin Kerja, paling lambat 3 hari sebelum bekerja</p>

</body>
</html>