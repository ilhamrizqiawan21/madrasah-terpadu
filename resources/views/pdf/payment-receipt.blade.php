<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Kuitansi {{ $receiptNumber }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a201c; margin: 18px; }
        .header { border-bottom: 2px solid #135b42; padding-bottom: 8px; margin-bottom: 12px; }
        .school { font-size: 15px; font-weight: bold; color: #135b42; }
        .meta { font-size: 10px; color: #555; margin-top: 2px; }
        .title-row { width: 100%; margin-bottom: 10px; }
        .title-row td { vertical-align: top; }
        .doc-title { font-size: 13px; font-weight: bold; letter-spacing: 0.4px; }
        .receipt-no { font-size: 12px; font-weight: bold; text-align: right; }
        .info { width: 100%; margin-bottom: 10px; }
        .info td { padding: 2px 0; vertical-align: top; }
        .label { width: 110px; color: #555; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.items th, table.items td { border: 1px solid #d4d4d0; padding: 6px 8px; }
        table.items th { background: #f3f2ee; text-align: left; font-size: 10px; }
        .right { text-align: right; }
        .total-row td { font-weight: bold; background: #f9f8f5; }
        .footer { margin-top: 14px; width: 100%; font-size: 10px; color: #444; }
        .reversed { margin-top: 8px; padding: 6px 8px; border: 1px solid #b91c1c; color: #b91c1c; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <div class="school">{{ $foundationName }}</div>
        @if($appName && $appName !== $foundationName)
            <div class="meta">{{ $appName }}</div>
        @endif
        @if($address || $phone)
            <div class="meta">{{ trim($address.' · '.$phone, ' ·') }}</div>
        @endif
    </div>

    <table class="title-row">
        <tr>
            <td class="doc-title">KUITANSI PEMBAYARAN</td>
            <td class="receipt-no">{{ $receiptNumber }}</td>
        </tr>
    </table>

    <table class="info">
        <tr>
            <td class="label">Siswa</td>
            <td>: <strong>{{ $studentName }}</strong> (NIS {{ $studentNis }})</td>
            <td class="label">Tanggal</td>
            <td>: {{ $paidAt }}</td>
        </tr>
        <tr>
            <td class="label">Unit</td>
            <td>: {{ $unitName }}</td>
            <td class="label">Metode</td>
            <td>: {{ $methodLabel }}@if($reference) ({{ $reference }})@endif</td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th>Rincian Tagihan</th>
                <th>Jenis</th>
                <th class="right">Nominal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
                <tr>
                    <td>{{ $row['description'] }}</td>
                    <td>{{ $row['fee_type'] }}</td>
                    <td class="right">{{ $row['amount'] }}</td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="2" class="right">Total Dibayar</td>
                <td class="right">{{ $totalFormatted }}</td>
            </tr>
        </tbody>
    </table>

    @if($isReversed)
        <div class="reversed">
            PEMBAYARAN DIBALIK: {{ $reversalReason }}
        </div>
    @endif

    <table class="footer">
        <tr>
            <td>Diterima oleh: <strong>{{ $cashierLabel }}</strong></td>
            <td class="right">Dokumen sah sistem {{ $appName ?: $foundationName }}</td>
        </tr>
    </table>
</body>
</html>
