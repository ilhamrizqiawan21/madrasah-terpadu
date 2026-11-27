<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Kartu Siswa</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 8mm 10mm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            margin: 0;
            padding: 0;
            font-family: DejaVu Sans, sans-serif;
            color: #1c1917;
            font-size: 9px;
        }
        .page {
            page-break-after: always;
        }
        .page:last-child {
            page-break-after: auto;
        }
        table.grid {
            width: 100%;
            border-collapse: separate;
            border-spacing: 6mm 3mm;
        }
        td.cell {
            width: 85.6mm;
            height: 54mm;
            vertical-align: top;
            padding: 0;
        }
        .card {
            width: 85.6mm;
            height: 54mm;
            border: 0.4mm solid #d6d3d1;
            border-radius: 2.5mm;
            overflow: hidden;
            background: #ffffff;
        }
        .card-header {
            background-color: {{ $primaryColor }};
            color: #ffffff;
            padding: 2.2mm 3.2mm;
        }
        .card-school {
            font-size: 8.5px;
            font-weight: bold;
            letter-spacing: 0.2px;
        }
        .card-subtitle {
            font-size: 6.5px;
            opacity: 0.9;
        }
        .card-body {
            padding: 2.8mm 3.2mm;
        }
        table.layout {
            width: 100%;
            border-collapse: collapse;
        }
        td.photo-col {
            width: 17mm;
            vertical-align: top;
        }
        td.info-col {
            padding: 0 2.5mm;
            vertical-align: top;
        }
        td.qr-col {
            width: 22mm;
            text-align: right;
            vertical-align: top;
        }
        .photo-box {
            width: 16mm;
            height: 20mm;
            border: 0.3mm solid #e7e5e4;
            border-radius: 1.5mm;
            overflow: hidden;
            background: #f5f5f4;
            text-align: center;
            line-height: 20mm;
            font-weight: bold;
            font-size: 11px;
            color: #57534e;
        }
        .photo-box img {
            width: 16mm;
            height: 20mm;
            object-fit: cover;
        }
        .student-name {
            font-size: 9.5px;
            font-weight: bold;
            line-height: 1.25;
            margin-bottom: 1.5mm;
            color: #0c0a09;
        }
        .meta-label {
            font-size: 6.5px;
            color: #78716c;
            text-transform: uppercase;
        }
        .meta-value {
            font-size: 8px;
            font-weight: bold;
            margin-bottom: 1.2mm;
            color: #292524;
        }
        .qr-img {
            width: 21mm;
            height: 21mm;
            border: 0.25mm solid #e7e5e4;
            padding: 0.8mm;
            background: #ffffff;
        }
        .unit-badge {
            display: inline-block;
            padding: 0.5mm 1.5mm;
            border-radius: 1mm;
            background: #f5f5f4;
            border: 0.25mm solid #d6d3d1;
            font-size: 7px;
            font-weight: bold;
        }
    </style>
</head>
<body>
@foreach ($pages as $page)
    <div class="page">
        <table class="grid">
            @foreach (array_chunk($page, 2) as $row)
                <tr>
                    @foreach ($row as $card)
                        <td class="cell">
                            <div class="card">
                                <div class="card-header">
                                    <div class="card-school">{{ $schoolName }}</div>
                                    <div class="card-subtitle">KARTU PELAJAR &amp; PRESENSI</div>
                                </div>
                                <div class="card-body">
                                    <table class="layout">
                                        <tr>
                                            <td class="photo-col">
                                                <div class="photo-box">
                                                    @if ($card['photo_data_uri'])
                                                        <img src="{{ $card['photo_data_uri'] }}" alt="">
                                                    @else
                                                        {{ $card['initials'] }}
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="info-col">
                                                <div class="student-name">{{ $card['name'] }}</div>
                                                <div class="meta-label">NIS</div>
                                                <div class="meta-value">{{ $card['nis'] }}</div>
                                                <div class="meta-label">Rombel / Unit</div>
                                                <div class="meta-value">{{ $card['classroom'] }} · <span class="unit-badge">{{ $card['unit'] }}</span></div>
                                            </td>
                                            <td class="qr-col">
                                                <img class="qr-img" src="{{ $card['qr_data_uri'] }}" alt="QR">
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </td>
                    @endforeach
                    @if (count($row) === 1)
                        <td class="cell"></td>
                    @endif
                </tr>
            @endforeach
        </table>
    </div>
@endforeach
</body>
</html>
