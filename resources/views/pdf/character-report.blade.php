<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Penilaian karakter {{ $classroom }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1f2937; margin: 24px 28px; }
        .page { page-break-after: always; }
        .page:last-child { page-break-after: auto; }
        .header { border-bottom: 2px solid #0f5132; padding-bottom: 8px; margin-bottom: 14px; }
        .school { font-size: 13px; font-weight: bold; color: #0f5132; }
        .title { font-size: 15px; font-weight: bold; margin-top: 2px; }
        .meta { font-size: 9px; color: #4b5563; margin-top: 3px; }
        .draft { display: inline-block; margin-top: 4px; padding: 2px 6px; border: 1px solid #b45309; color: #b45309; font-size: 9px; font-weight: bold; }
        .identity td { border: none; padding: 1px 0; }
        .identity td.label { width: 90px; color: #4b5563; }
        table.result { width: 100%; border-collapse: collapse; margin-top: 12px; }
        table.result th, table.result td { border: 1px solid #d1d5db; padding: 6px 7px; text-align: left; vertical-align: top; }
        table.result th { background-color: #f3f4f6; font-weight: bold; }
        .level { font-weight: bold; white-space: nowrap; }
        .footer { margin-top: 24px; font-size: 9px; color: #4b5563; }
    </style>
</head>
<body>
@forelse ($students as $student)
    <div class="page">
        <div class="header">
            <div class="school">{{ $schoolName }}</div>
            <div class="title">Lampiran Rapor · Penilaian Karakter</div>
            <div class="meta">{{ $unit }} · Rombel {{ $classroom }} · Semester {{ $semester }}</div>
            @unless ($approved)
                <div class="draft">{{ strtoupper($statusLabel) }} — BELUM DISETUJUI KEPALA UNIT</div>
            @endunless
        </div>

        <table class="identity">
            <tr><td class="label">Nama</td><td>{{ $student['name'] }}</td></tr>
            <tr><td class="label">NIS</td><td>{{ $student['nis'] }}</td></tr>
        </table>

        <table class="result">
            <thead>
                <tr>
                    <th style="width: 28%">Dimensi</th>
                    <th style="width: 22%">Capaian</th>
                    <th>Deskripsi</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($student['dimensions'] as $dimension)
                    <tr>
                        <td>{{ $dimension['name'] }}</td>
                        <td class="level">{{ $dimension['level_label'] }}</td>
                        <td>{{ $dimension['description'] ?? '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="footer">
            @if ($approved && $approvedBy)
                Disetujui {{ $approvedBy }}@if ($approvedAt), {{ $approvedAt }}@endif.
            @endif
        </div>
    </div>
@empty
    <p>Belum ada penilaian.</p>
@endforelse
</body>
</html>
