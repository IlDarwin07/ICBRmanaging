<?php
/**
 * XlsxReader — lettore leggero per file .xlsx, .xls, .csv
 * Non richiede librerie esterne. Supporta:
 *  - .csv  → fgetcsv nativo PHP
 *  - .xlsx → parsing ZIP/XML nativo (Office Open XML)
 *  - .xls  → fallback CSV (converte a CSV con LibreOffice se disponibile,
 *             altrimenti richiede che il file sia già un CSV rinominato)
 */
class XlsxReader
{
    private array $rows = [];  // tutte le righe compresa la 0 (intestazioni)

    public function __construct(string $file, string $ext)
    {
        match (strtolower($ext)) {
            'csv'  => $this->loadCsv($file),
            'xlsx' => $this->loadXlsx($file),
            'xls'  => $this->loadXls($file),
            default => throw new RuntimeException('Formato non supportato: ' . $ext),
        };
    }

    // ------------------------------------------------------------------
    // Pubblici
    // ------------------------------------------------------------------

    /** Restituisce la riga 0 come array di stringhe (intestazioni). */
    public function getHeaders(): array
    {
        return array_map('strval', $this->rows[0] ?? []);
    }

    /** Restituisce le prime $n righe dati (salta intestazioni). */
    public function getSampleRows(int $n = 3): array
    {
        return array_slice($this->rows, 1, $n);
    }

    /** Restituisce tutte le righe dati (salta intestazioni). */
    public function getAllRows(): array
    {
        return array_slice($this->rows, 1);
    }

    // ------------------------------------------------------------------
    // CSV
    // ------------------------------------------------------------------

    private function loadCsv(string $file): void
    {
        $handle = fopen($file, 'r');
        if (!$handle) throw new RuntimeException('Impossibile aprire il file CSV.');

        // BOM UTF-8
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") rewind($handle);

        // Rileva delimitatore (virgola, punto e virgola, tab)
        $first = fgets($handle);
        rewind($handle);
        if ($bom !== "\xEF\xBB\xBF") fread($handle, 3); // skip BOM
        else rewind($handle);

        $scores = [];
        foreach ([',', ';', "\t"] as $d) {
            $scores[$d] = substr_count($first, $d);
        }
        arsort($scores);
        $delim = array_key_first($scores);

        rewind($handle);
        // Skip BOM again if present
        $bom2 = fread($handle, 3);
        if ($bom2 !== "\xEF\xBB\xBF") rewind($handle);

        $this->rows = [];
        while (($row = fgetcsv($handle, 0, $delim)) !== false) {
            $this->rows[] = $row;
        }
        fclose($handle);
    }

    // ------------------------------------------------------------------
    // XLSX (Office Open XML — ZIP + XML, nativo PHP)
    // ------------------------------------------------------------------

    private function loadXlsx(string $file): void
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('Estensione PHP ZipArchive non disponibile.');
        }

        $zip = new ZipArchive();
        if ($zip->open($file) !== true) {
            throw new RuntimeException('Impossibile aprire il file XLSX (ZIP non valido).');
        }

        // 1. Leggi shared strings
        $sharedStrings = [];
        $ssXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($ssXml !== false) {
            $ss = new SimpleXMLElement($ssXml);
            foreach ($ss->si as $si) {
                // Unisci tutti i tag <t> (testo con formattazione inline)
                $text = '';
                foreach ($si->r as $r) {
                    $text .= (string)$r->t;
                }
                if (empty($text)) {
                    $text = (string)($si->t ?? '');
                }
                $sharedStrings[] = $text;
            }
        }

        // 2. Trova primo sheet
        $wbXml = $zip->getFromName('xl/workbook.xml');
        $sheetFile = 'xl/worksheets/sheet1.xml'; // default
        if ($wbXml !== false) {
            $wb = new SimpleXMLElement($wbXml);
            // Prendi il primo sheet
            foreach ($wb->sheets->sheet as $sheet) {
                $rId = (string)$sheet->attributes('r', true)->id;
                // Cerca la relazione
                $relXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
                if ($relXml !== false) {
                    $rel = new SimpleXMLElement($relXml);
                    foreach ($rel->Relationship as $r) {
                        if ((string)$r['Id'] === $rId) {
                            $target = (string)$r['Target'];
                            if (!str_starts_with($target, 'xl/')) {
                                $target = 'xl/' . $target;
                            }
                            $sheetFile = $target;
                            break 2;
                        }
                    }
                }
                break;
            }
        }

        $wsXml = $zip->getFromName($sheetFile);
        $zip->close();

        if ($wsXml === false) {
            throw new RuntimeException('Sheet non trovato nel file XLSX.');
        }

        $ws = new SimpleXMLElement($wsXml);
        $ns = $ws->getNamespaces(true);
        $defaultNs = $ns[''] ?? '';

        // Indice colonna da riferimento cella (A=0, B=1, ...)
        $colIndex = function(string $cellRef): int {
            preg_match('/^([A-Z]+)/', $cellRef, $m);
            $col = $m[1] ?? 'A';
            $n = 0;
            foreach (str_split($col) as $c) {
                $n = $n * 26 + (ord($c) - ord('A') + 1);
            }
            return $n - 1;
        };

        $this->rows = [];
        foreach ($ws->sheetData->row as $row) {
            $maxCol = 0;
            $cells = [];
            foreach ($row->c as $c) {
                $ref  = (string)$c['r'];
                $type = (string)$c['t'];
                $ci   = $colIndex($ref);
                if ($ci > $maxCol) $maxCol = $ci;

                $val = (string)($c->v ?? '');
                if ($type === 's') {
                    $val = $sharedStrings[(int)$val] ?? '';
                } elseif ($type === 'inlineStr') {
                    $val = (string)($c->is->t ?? '');
                } elseif ($type === 'b') {
                    $val = $val === '1' ? '1' : '0';
                }
                $cells[$ci] = $val;
            }
            // Riempi celle mancanti
            $fullRow = [];
            for ($i = 0; $i <= $maxCol; $i++) {
                $fullRow[] = $cells[$i] ?? '';
            }
            $this->rows[] = $fullRow;
        }
    }

    // ------------------------------------------------------------------
    // XLS legacy — prova LibreOffice, altrimenti fallback CSV
    // ------------------------------------------------------------------

    private function loadXls(string $file): void
    {
        // Tenta conversione con LibreOffice headless
        $tmpDir = sys_get_temp_dir();
        $cmd = sprintf(
            'libreoffice --headless --convert-to csv --outdir %s %s 2>/dev/null',
            escapeshellarg($tmpDir),
            escapeshellarg($file)
        );
        exec($cmd, $out, $ret);
        $csvFile = $tmpDir . '/' . pathinfo($file, PATHINFO_FILENAME) . '.csv';
        if ($ret === 0 && file_exists($csvFile)) {
            $this->loadCsv($csvFile);
            @unlink($csvFile);
            return;
        }
        // Fallback: tratta come CSV (utile se rinominato)
        $this->loadCsv($file);
    }
}
