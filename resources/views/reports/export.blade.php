<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><style>
    @page { margin: 48pt; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; line-height: 1.45; }
    h1 { font-size: 22pt; } h2 { font-size: 16pt; page-break-after: avoid; } h3 { font-size: 12pt; page-break-after: avoid; }
    table { border-collapse: collapse; width: 100%; } th, td { border: 1px solid #bbb; padding: 5pt; vertical-align: top; }
    a { color: #50376b; } pre { white-space: pre-wrap; }
</style></head>
<body><h1>{{ $title }}</h1><p>{{ $label }} · {{ __('Revision :number', ['number' => $revision]) }}</p>{!! $html !!}</body>
</html>
