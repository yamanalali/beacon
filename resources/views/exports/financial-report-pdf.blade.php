<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>{{ $report->title }}</title>
    <style>
        body {
            font-family: xbriyaz, dejavusans, sans-serif;
            font-size: 11pt;
            color: #1a1a1a;
            direction: rtl;
            text-align: right;
        }
        h1 {
            font-size: 18pt;
            margin: 0 0 4pt;
            color: #0f3d3e;
        }
        .period {
            font-size: 10pt;
            color: #555;
            margin-bottom: 16pt;
        }
        h2 {
            font-size: 13pt;
            margin: 18pt 0 8pt;
            color: #0f3d3e;
            border-bottom: 1px solid #c5d4d4;
            padding-bottom: 4pt;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8pt;
        }
        th, td {
            border: 1px solid #c5d4d4;
            padding: 5pt 6pt;
            text-align: right;
            vertical-align: top;
        }
        th {
            background: #e8f0f0;
            font-weight: bold;
        }
        .empty {
            color: #777;
            font-size: 10pt;
            margin: 4pt 0 12pt;
        }
        .notes {
            margin-top: 18pt;
            font-size: 9pt;
            color: #555;
        }
        .notes p {
            margin: 0 0 4pt;
        }
        .disclaimer {
            margin-top: 10pt;
            font-size: 9pt;
            color: #666;
        }
    </style>
</head>
<body>
    <h1>{{ $report->title }}</h1>
    <div class="period">{{ $report->periodLabel }}</div>

    @foreach ($report->sections as $section)
        <h2>{{ $section['title'] }}</h2>
        @if (count($section['rows']) === 0)
            <p class="empty">لا توجد بيانات لهذه الفترة</p>
        @else
            <table>
                <thead>
                    <tr>
                        @foreach ($section['columns'] as $column)
                            <th>{{ $column }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($section['rows'] as $row)
                        <tr>
                            @foreach ($row as $cell)
                                <td>{{ $cell }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endforeach

    @if (count($report->notes) > 0)
        <div class="notes">
            @foreach ($report->notes as $note)
                <p>{{ $note }}</p>
            @endforeach
        </div>
    @endif

    <p class="disclaimer">هذا التقرير محسوب من السجلات المحفوظة وليس تقييماً أو موافقة على تمويل.</p>
</body>
</html>
