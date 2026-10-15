<?php

namespace Tests\Support;

trait MakesPdfs
{
    /**
     * Builds a minimal but valid single-page PDF with a real text layer (correct xref offsets),
     * so tests exercise the actual PDF parser instead of a mock.
     *
     * @param  list<string>  $lines
     */
    protected function makePdf(array $lines): string
    {
        $escape = fn (string $s) => str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);
        $stream = "BT /F1 11 Tf 14 TL 50 780 Td\n";
        foreach ($lines as $line) {
            $stream .= '('.$escape($line).") Tj T*\n";
        }
        $stream .= 'ET';

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $i => $body) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n{$body}\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= 'xref
0 '.(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf.'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }
}
