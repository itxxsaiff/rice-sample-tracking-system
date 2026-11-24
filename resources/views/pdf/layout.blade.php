<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $title }}</title>
<style>
    @page { margin: 26pt 32pt 44pt; }
    * { box-sizing: border-box; }
    body { font-family: Helvetica, Arial, sans-serif; font-size: 8.4pt; color: #1f2933; line-height: 1.38; margin: 0; }
    table { border-collapse: collapse; width: 100%; }

    /* Letterhead */
    .letterhead td { vertical-align: middle; padding: 0; }
    .letterhead .logo { height: 54px; width: auto; }
    .letterhead .company { font-size: 12pt; font-weight: bold; color: #0f2a4a; }
    .letterhead .company-sub { font-size: 7pt; color: #7a828d; letter-spacing: 1.4px; text-transform: uppercase; margin-top: 2px; }
    .letterhead .doc-title { font-size: 14pt; font-weight: bold; color: #0f2a4a; text-align: right; }
    .letterhead .doc-meta { font-size: 7.4pt; color: #7a828d; text-align: right; margin-top: 3px; }
    .rule { border-top: 2px solid #0f2a4a; margin-top: 12px; }
    .rule-accent { border-top: 0.8px solid #b8894c; margin-top: 2px; margin-bottom: 14px; }

    /* Who / what the document covers */
    .criteria { font-size: 7.8pt; color: #4b5563; margin-bottom: 12px; }
    .criteria .sep { color: #c3c8cf; padding: 0 6px; }
    .criteria b { color: #1f2933; }

    .party { margin-bottom: 14px; }
    .party { table-layout: fixed; }
    .party td { padding: 2.5px 8px 2.5px 0; vertical-align: top; font-size: 8.2pt; }
    .party .label { width: 13%; color: #7a828d; }
    .party .name { font-size: 12pt; font-weight: bold; color: #0f2a4a; padding-bottom: 6px; }

    /* Figures */
    .summary { table-layout: fixed; margin-bottom: 18px; border-top: 0.8px solid #d5dae1; border-bottom: 0.8px solid #d5dae1; }
    .summary td { padding: 8px 10px 9px; border-left: 0.8px solid #e3e7ec; vertical-align: top; }
    .summary td:first-child { border-left: none; padding-left: 0; }
    .summary .k { font-size: 6.8pt; color: #7a828d; text-transform: uppercase; letter-spacing: 0.8px; }
    .summary .v { font-size: 12pt; font-weight: bold; color: #0f2a4a; margin-top: 2px; }

    h2 { font-size: 8.6pt; color: #0f2a4a; text-transform: uppercase; letter-spacing: 1.1px; margin: 4px 0 6px; }
    h2 .count { color: #7a828d; font-weight: normal; letter-spacing: 0; text-transform: none; }

    /* Register tables */
    .grid { margin-bottom: 18px; }
    .grid thead { display: table-header-group; }
    .grid th { font-size: 6.6pt; font-weight: bold; color: #4b5563; text-transform: uppercase; letter-spacing: 0.7px;
               text-align: left; padding: 6px 6px 5px; border-bottom: 1.2px solid #0f2a4a; }
    .grid td { padding: 6px 6px; border-bottom: 0.6px solid #e3e7ec; vertical-align: top; }
    .grid tbody tr:nth-child(even) td { background: #f7f9fb; }
    .grid tr { page-break-inside: avoid; }
    .grid .no { width: 18px; color: #9aa1ab; font-size: 7pt; }
    .grid .r { text-align: right; }
    .grid .nowrap { white-space: nowrap; }
    .grid tfoot td { border-top: 1.2px solid #0f2a4a; border-bottom: none; font-weight: bold; color: #0f2a4a; padding-top: 7px; }
    .strong { font-weight: bold; color: #111827; }
    .sub { font-size: 7.2pt; color: #7a828d; margin-top: 1px; }

    .st { font-weight: bold; font-size: 7.4pt; }
    .st-pending { color: #b45309; }
    .st-confirmed { color: #0f766e; }
    .st-in_progress { color: #1d4ed8; }
    .st-completed { color: #15803d; }
    .st-rejected { color: #b91c1c; }

    .muted { color: #9aa1ab; }
    .note { font-size: 7.4pt; color: #7a828d; margin-top: -8px; margin-bottom: 14px; }
    .empty { padding: 22px 0; text-align: center; color: #7a828d; border-top: 0.6px solid #e3e7ec; border-bottom: 0.6px solid #e3e7ec; margin-bottom: 18px; }
    .truck-photos { margin-bottom: 12px; page-break-inside: avoid; }
    .truck-photos-head { font-size: 8pt; color: #4b5563; padding: 4px 0; border-bottom: 0.6px solid #e3e7ec; margin-bottom: 6px; }
    .truck-photos-head b { color: #0f2a4a; }
    .photo-grid { table-layout: fixed; }
    .photo-grid td { width: 25%; padding: 0 6px 6px 0; vertical-align: top; }
    .photo-grid img { width: 100%; height: auto; border: 0.6px solid #e3e7ec; }
    .end { text-align: center; font-size: 7pt; color: #9aa1ab; letter-spacing: 1px; margin-top: 6px; }
</style>
</head>
<body>
    <table class="letterhead">
        <tr>
            <td style="width: 88px; padding-right: 10px"><img src="{{ $logo }}" class="logo" alt=""></td>
            <td>
                <div class="company">{{ $company }}</div>
                <div class="company-sub">Rice Samples · Tracking &amp; Dispatch</div>
            </td>
            <td>
                <div class="doc-title">{{ $title }}</div>
                <div class="doc-meta">Printed {{ $generatedAt->format('d M Y, g:i A') }}@if ($generatedBy) · {{ $generatedBy }}@endif</div>
            </td>
        </tr>
    </table>
    <div class="rule"></div>
    <div class="rule-accent"></div>

    @yield('content')

    <div class="end">— END OF REPORT —</div>
</body>
</html>
