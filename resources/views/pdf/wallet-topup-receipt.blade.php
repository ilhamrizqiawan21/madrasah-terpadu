<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Kuitansi Isi Saldo Dompet</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1f2937; }
        .box { border: 1px solid #d1d5db; padding: 16px; max-width: 480px; }
        .title { font-size: 15px; font-weight: bold; margin-bottom: 4px; }
        .meta { font-size: 11px; color: #6b7280; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        td { padding: 4px 0; vertical-align: top; }
        .right { text-align: right; font-weight: bold; }
    </style>
</head>
<body>
    <div class="box">
        <div class="title">{{ $schoolName }}</div>
        <div class="meta">Kuitansi Isi Saldo Tunai Dompet Kantin · #{{ $entry->id }}</div>
        <table>
            <tr>
                <td>Siswa</td>
                <td class="right">{{ $entry->wallet->student->name }} ({{ $entry->wallet->student->nis }})</td>
            </tr>
            <tr>
                <td>Tanggal (WITA)</td>
                <td class="right">{{ $entry->created_at->timezone('Asia/Makassar')->format('d/m/Y H.i') }}</td>
            </tr>
            <tr>
                <td>Nominal Isi Saldo</td>
                <td class="right">{{ \App\Support\Money::format($entry->amount) }}</td>
            </tr>
            <tr>
                <td>Saldo Akhir</td>
                <td class="right">{{ \App\Support\Money::format($entry->balance_after) }}</td>
            </tr>
            <tr>
                <td>Petugas</td>
                <td class="right">{{ $entry->creator?->name ?? '-' }}</td>
            </tr>
        </table>
    </div>
</body>
</html>
