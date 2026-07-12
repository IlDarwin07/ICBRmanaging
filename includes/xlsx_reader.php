<?php
/**
 * XlsxReader — lettore leggero per file .xlsx, .xls, .csv
 * Non richiede librerie esterne. Supporta:
 *  - .csv  → fgetcsv nativo PHP
 *  - .xlsx → parsing ZIP/XML nativo (Office Open XML)
 *  - .xls  → rileva automaticamente:
 *              1. HTML table rinominata .xls (export portali web / Inter Club)
 *              2. BIFF OLE2 vero → conversione LibreOffice headless se disponibile
 *              3. Fallback CSV
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
        if ($bom !== "\xEF\xBB\xBF") fread($handle, 3);
        else rewind($handle);

        $scores = [];
        foreach ([',', ';', "\t"] as $d) {
            $scores[$d] = substr_count($first, $d);
        }
        arsort($scores);
        $delim = array_key_first($scores);

        rewind($handle);
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
        $sheetFile = 'xl/worksheets/sheet1.xml';
        if ($wbXml !== false) {
            $wb = new SimpleXMLElement($wbXml);
            foreach ($wb->sheets->sheet as $sheet) {
                $rId = (string)$sheet->attributes('r', true)->id;
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
            $fullRow = [];
            for ($i = 0; $i <= $maxCol; $i++) {
                $fullRow[] = $cells[$i] ?? '';
            }
            $this->rows[] = $fullRow;
        }
    }

    // ------------------------------------------------------------------
    // XLS — rileva il formato reale e sceglie il parser corretto
    // ------------------------------------------------------------------

    private function loadXls(string $file): void
    {
        // Leggi i primi 8 byte per identificare il formato
        $handle = fopen($file, 'rb');
        if (!$handle) throw new RuntimeException('Impossibile aprire il file XLS.');
        $magic = fread($handle, 8);
        fclose($handle);

        // Caso 1: File XLS in realtà è un HTML (export da portali web come Inter Club)
        // Signature HTML: inizia con <html, <!DOCTYPE, <table, <?xml, oppure BOM + tag
        $start = strtolower(ltrim(substr($magic, 0, 8)));
        $rawStart = file_get_contents($file, false, null, 0, 512);
        $rawLower = strtolower($rawStart);

        if (
            str_starts_with($start, '<') ||
            str_contains($rawLower, '<html') ||
            str_contains($rawLower, '<table') ||
            str_contains($rawLower, '<!doctype') ||
            str_contains($rawLower, '<?xml')
        ) {
            $this->loadXlsHtml($file);
            return;
        }

        // Caso 2: OLE2/BIFF vero (D0 CF 11 E0) — prova LibreOffice headless
        if (substr($magic, 0, 4) === "\xD0\xCF\x11\xE0") {
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
            throw new RuntimeException(
                'Il file è un Excel binario (BIFF) ma LibreOffice non è disponibile sul server. ' .
                'Salva il file come .xlsx o .csv ed effettua di nuovo il caricamento.'
            );
        }

        // Caso 3: forse è un CSV rinominato
        $this->loadCsv($file);
    }

    // ------------------------------------------------------------------
    // Parser HTML-as-XLS (export Inter Club e altri portali web)
    // ------------------------------------------------------------------

    private function loadXlsHtml(string $file): void
    {
        $content = file_get_contents($file);
        if ($content === false) {
            throw new RuntimeException('Impossibile leggere il file XLS/HTML.');
        }

        // Normalizza encoding: alcuni export usano Windows-1252
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        // Sopprime gli avvisi di HTML malformato
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $content);
        libxml_clear_errors();

        $tables = $dom->getElementsByTagName('table');
        if ($tables->length === 0) {
            throw new RuntimeException(
                'Il file XLS non contiene tabelle HTML leggibili. ' .
                'Prova a salvarlo come .xlsx o .csv.'
            );
        }

        // Usa la prima tabella con più di 1 colonna (salta eventuali tabelle di layout)
        $targetTable = null;
        foreach ($tables as $table) {
            $rows = $table->getElementsByTagName('tr');
            if ($rows->length > 0) {
                $firstRow = $rows->item(0);
                $cols = $firstRow->getElementsByTagName('td');
                $ths  = $firstRow->getElementsByTagName('th');
                if ($cols->length > 1 || $ths->length > 1) {
                    $targetTable = $table;
                    break;
                }
            }
        }

        if ($targetTable === null) {
            $targetTable = $tables->item(0);
        }

        $this->rows = [];
        $trList = $targetTable->getElementsByTagName('tr');
        foreach ($trList as $tr) {
            $row = [];
            // Supporta sia <td> che <th>
            foreach ($tr->childNodes as $node) {
                if ($node->nodeType !== XML_ELEMENT_NODE) continue;
                $tag = strtolower($node->nodeName);
                if ($tag !== 'td' && $tag !== 'th') continue;
                $row[] = trim($node->textContent);
            }
            if (!empty($row)) {
                $this->rows[] = $row;
            }
        }

        if (empty($this->rows)) {
            throw new RuntimeException('Nessuna riga trovata nella tabella HTML del file XLS.');
        }
    }
}
