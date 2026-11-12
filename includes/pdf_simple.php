<?php
/**
 * Generatore PDF minimo (solo testo, linee e rettangoli), senza librerie.
 * Coordinate in punti, origine in alto a sinistra. Font Helvetica (WinAnsi).
 */
class SimplePdf
{
    public float $w = 595.28;
    public float $h = 841.89;
    private array $pages = [];
    private int $cur = -1;

    public function addPage(): void
    {
        $this->pages[] = '';
        $this->cur = count($this->pages) - 1;
    }

    public function pageCount(): int
    {
        return count($this->pages);
    }

    private function enc(string $s): string
    {
        $c = @iconv('UTF-8', 'CP1252//TRANSLIT//IGNORE', $s);
        if ($c === false) {
            $c = preg_replace('/[^\x20-\x7E]/', '?', $s);
        }
        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', ' ', ' '], $c);
    }

    /** Accorcia il testo perche stia in $maxWidth punti (stima Helvetica). */
    public function fit(string $s, float $maxWidth, float $size): string
    {
        $max = max(1, (int)floor($maxWidth / ($size * 0.52)));
        return mb_strlen($s) > $max ? mb_substr($s, 0, $max - 1) . '.' : $s;
    }

    public function text(float $x, float $y, string $s, float $size = 10, bool $bold = false, ?int $page = null): void
    {
        $p = $page ?? $this->cur;
        $this->pages[$p] .= sprintf("BT /%s %.2F Tf %.2F %.2F Td (%s) Tj ET\n", $bold ? 'F2' : 'F1', $size, $x, $this->h - $y, $this->enc($s));
    }

    public function line(float $x1, float $y1, float $x2, float $y2, float $width = 0.5): void
    {
        $this->pages[$this->cur] .= sprintf("%.2F w %.2F %.2F m %.2F %.2F l S\n", $width, $x1, $this->h - $y1, $x2, $this->h - $y2);
    }

    public function rect(float $x, float $y, float $w, float $h, float $width = 0.7): void
    {
        $this->pages[$this->cur] .= sprintf("%.2F w %.2F %.2F %.2F %.2F re S\n", $width, $x, $this->h - $y - $h, $w, $h);
    }

    public function fillRect(float $x, float $y, float $w, float $h, float $gray = 0.9): void
    {
        $this->pages[$this->cur] .= sprintf("%.2F g %.2F %.2F %.2F %.2F re f 0 g\n", $gray, $x, $this->h - $y - $h, $w, $h);
    }

    public function output(): string
    {
        $n = count($this->pages);
        $kids = [];
        for ($i = 0; $i < $n; $i++) {
            $kids[] = (6 + 2 * $i) . ' 0 R';
        }
        $objs = [];
        $objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objs[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $n . ' >>';
        $objs[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objs[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        foreach ($this->pages as $i => $content) {
            $objs[5 + 2 * $i] = '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . 'endstream';
            $objs[6 + 2 * $i] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>',
                $this->w, $this->h, 5 + 2 * $i
            );
        }
        $out = "%PDF-1.4\n";
        $off = [];
        foreach ($objs as $id => $body) {
            $off[$id] = strlen($out);
            $out .= "$id 0 obj\n$body\nendobj\n";
        }
        $xref = strlen($out);
        $out .= "xref\n0 " . (count($objs) + 1) . "\n0000000000 65535 f \n";
        foreach ($objs as $id => $_) {
            $out .= sprintf("%010d 00000 n \n", $off[$id]);
        }
        $out .= "trailer\n<< /Size " . (count($objs) + 1) . " /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
        return $out;
    }
}
