<?php

namespace App\Http\Controllers;

use App\Support\DatasetExport;
use Symfony\Component\HttpFoundation\Response;

/**
 * Downloads the whole dataset, for visitors and admins alike: JSON, or a
 * SQLite database with a .sqlite or .db extension.
 */
class ExportController extends Controller
{
    public function __invoke(string $format, DatasetExport $export): Response
    {
        $filename = 'comparative-mythology-'.now()->format('Y-m-d');

        if ($format === 'json') {
            return response()->streamDownload(
                fn () => print ($export->toJson()),
                "{$filename}.json",
                ['Content-Type' => 'application/json; charset=utf-8'],
            );
        }

        return response()
            ->download($export->toSqliteFile(), "{$filename}.{$format}", ['Content-Type' => 'application/vnd.sqlite3'])
            ->deleteFileAfterSend();
    }
}
