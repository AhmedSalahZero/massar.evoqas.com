<?php

namespace App\Services\Reports;

use App\Support\ArabicShaper;
use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// ══════════════════════════════════════════════════════════════════
//  Massar — ReportExporter (Step 14 · Reports)
//  Location: app/Services/Reports/ReportExporter.php
//
//  The same answer as on the screen, to keep or to send:
//
//    xlsx()  Sheet 1: the table with its totals (and "based on N people"
//            for an average). Sheet 2: the question in words, the date
//            range, the workspace, who made it and when. Arabic sheets
//            are right-to-left.
//    pdf()   A4: the workspace, the title, the question in words, a bar
//            chart of the rows (the biggest 15), the table, who made it,
//            the date and page numbers. Arabic is shaped and written
//            right-to-left (App\Support\ArabicShaper), in DejaVu Sans.
//
//  Both return the file's bytes; the controller names and sends it.
// ══════════════════════════════════════════════════════════════════

class ReportExporter
{
    /** @param array{title: string, workspace: string, by: string, locale: string, summary: list<string>} $meta */
    public function xlsx(array $result, array $meta): string
    {
        $ar = $meta['locale'] === 'ar';
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle(mb_substr(__('reports.title', [], $meta['locale']), 0, 31));
        $sheet->setRightToLeft($ar);

        $grid = $this->grid($result, $meta['locale']);
        $sheet->fromArray($grid['head'], null, 'A1');
        $sheet->fromArray($grid['body'], null, 'A2');
        $last = count($grid['body']) + 1;
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($grid['head']));
        $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$lastCol}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E8F3FA');
        $sheet->getStyle("A{$last}:{$lastCol}{$last}")->getFont()->setBold(true);
        $sheet->getStyle("B2:{$lastCol}{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle("B2:{$lastCol}{$last}")->getNumberFormat()->setFormatCode($result['measure'] === 'count' ? '#,##0' : '#,##0.0');
        foreach (range(1, count($grid['head'])) as $i) {
            $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        }
        $sheet->freezePane('B2');

        $about = $book->createSheet();
        $about->setTitle(mb_substr(__('reports.filters', [], $meta['locale']), 0, 31));
        $about->setRightToLeft($ar);
        $lines = [[$meta['title']], [__('reports.workspace', [], $meta['locale']).': '.$meta['workspace']], ['']];
        foreach ($meta['summary'] as $s) {
            $lines[] = [$s];
        }
        $lines[] = [''];
        $lines[] = [__('reports.made_by', ['name' => $meta['by'], 'date' => now()->format('Y-m-d H:i')], $meta['locale'])];
        $about->fromArray($lines, null, 'A1');
        $about->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $about->getColumnDimension('A')->setWidth(100);
        $book->setActiveSheetIndex(0);

        ob_start();
        (new Xlsx($book))->save('php://output');

        return (string) ob_get_clean();
    }

    public function pdf(array $result, array $meta): string
    {
        $ar = $meta['locale'] === 'ar';
        $s = fn ($t) => htmlspecialchars($ar ? ArabicShaper::line((string) $t) : (string) $t, ENT_QUOTES, 'UTF-8');
        $grid = $this->grid($result, $meta['locale']);
        $head = $grid['head'];
        $body = $grid['body'];
        if ($ar) {   // right-to-left: the first column on the right
            $head = array_reverse($head);
            $body = array_map('array_reverse', $body);
        }
        $align = $ar ? 'right' : 'left';

        // A bar chart of the rows (the biggest 15; the table has them all).
        $bars = '';
        $rows = array_slice(array_values(array_filter($result['rows'], fn ($r) => $r['key'] !== '_none')), 0, 15);
        $vals = array_map(fn ($r) => (float) ($result['row_totals'][$r['key']]['v'] ?? 0), $rows);
        $max = $vals ? max($vals) : 0;
        if ($max > 0) {
            foreach ($rows as $i => $r) {
                $w = max(1, round($vals[$i] / $max * 100));
                $label = '<td class="bl" style="text-align:'.($ar ? 'right' : 'left').'">'.$s($r['label']).'</td>';
                $bar = '<td class="bt"><div class="bar" style="width:'.$w.'%;'.($ar ? 'margin-left:auto;' : '').'"></div></td>';
                $num = '<td class="bv">'.$s($this->fmt($result['row_totals'][$r['key']] ?? null, $result['measure'])).'</td>';
                $bars .= '<tr>'.($ar ? $num.$bar.$label : $label.$bar.$num).'</tr>';
            }
        }

        $th = implode('', array_map(fn ($h, $i) => '<th style="text-align:'.(($ar ? $i === count($head) - 1 : $i === 0) ? $align : 'right').'">'.$s($h).'</th>', $head, array_keys($head)));
        $trs = '';
        foreach ($body as $ri => $row) {
            $cls = $ri === count($body) - 1 ? ' class="tot"' : '';
            $trs .= "<tr$cls>".implode('', array_map(fn ($c, $i) => '<td style="text-align:'.(($ar ? $i === count($row) - 1 : $i === 0) ? $align : 'right').'">'.$s(is_numeric($c) ? number_format((float) $c, $result['measure'] === 'count' ? 0 : 1) : $c).'</td>', $row, array_keys($row))).'</tr>';
        }
        $summary = implode('', array_map(fn ($l) => '<div>'.$s($l).'</div>', $meta['summary']));
        $made = $s(__('reports.made_by', ['name' => $meta['by'], 'date' => now()->format('Y-m-d H:i')], $meta['locale']));

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
            @page { margin: 26px 30px 40px; }
            body { font-family: "DejaVu Sans", sans-serif; font-size: 10px; color: #123055; }
            .top { border-bottom: 3px solid #1B4F8C; padding-bottom: 6px; margin-bottom: 10px; text-align: '.$align.'; }
            .ws { color: #16708A; font-weight: bold; font-size: 10px; text-transform: uppercase; }
            h1 { font-size: 17px; margin: 3px 0 0; color: #123055; }
            .sum { background: #EFF6FC; border: 1px solid #D7E6F2; padding: 7px 9px; margin: 8px 0 12px; text-align: '.$align.'; line-height: 1.5; }
            table.chart { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
            .bl { width: 34%; padding: 2px 6px; font-size: 9px; }
            .bt { width: 52%; padding: 2px 0; }
            .bv { width: 14%; text-align: right; font-weight: bold; padding: 2px 6px; font-size: 9px; }
            .bar { height: 9px; background: #2a78d6; }
            table.t { width: 100%; border-collapse: collapse; }
            table.t th { background: #E8F3FA; font-size: 8.5px; text-transform: uppercase; color: #5C7999; padding: 5px 6px; border-bottom: 1px solid #D7E6F2; }
            table.t td { padding: 4px 6px; border-bottom: 1px solid #E4EEF6; }
            tr.tot td { font-weight: bold; border-top: 2px solid #1B4F8C; }
            .foot { margin-top: 10px; color: #5C7999; font-size: 8.5px; text-align: '.$align.'; }
        </style></head><body>
            <div class="top"><div class="ws">'.$s($meta['workspace']).'</div><h1>'.$s($meta['title']).'</h1></div>
            <div class="sum">'.$summary.'</div>'
            .($bars ? '<table class="chart">'.$bars.'</table>' : '').
            '<table class="t"><thead><tr>'.$th.'</tr></thead><tbody>'.$trs.'</tbody></table>
            <div class="foot">'.$made.'</div>
        </body></html>';

        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $pdf = new Dompdf($options);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4', count($head) > 6 ? 'landscape' : 'portrait');
        $pdf->render();
        // Page numbers.
        $canvas = $pdf->getCanvas();
        $font = $pdf->getFontMetrics()->getFont('DejaVu Sans');
        // Arabic: the words are shaped one by one around the page placeholders (drawn right to left).
        $text = $ar
            ? '{PAGE_COUNT} '.ArabicShaper::line('من').' {PAGE_NUM} '.ArabicShaper::line('صفحة')
            : __('reports.page', ['n' => '{PAGE_NUM}', 'total' => '{PAGE_COUNT}'], 'en');
        $canvas->page_text($canvas->get_width() / 2 - 30, $canvas->get_height() - 26, $text, $font, 8, [0.36, 0.47, 0.6]);

        return (string) $pdf->output();
    }

    /** The table as plain rows: a header, one line per row, and the totals. */
    private function grid(array $result, string $locale): array
    {
        $measure = $result['measure'];
        $dimTitle = (string) ($result['rows_title'] ?? '');
        $cols = $result['cols'];
        $head = [$dimTitle];
        if ($cols) {
            foreach ($cols as $c) {
                $head[] = $c['label'];
            }
        } else {
            $head[] = __('reports.measure.'.$measure, [], $locale);
        }
        $head[] = __('reports.total', [], $locale);
        $withN = $measure !== 'count';
        if ($withN) {
            $head[] = __('reports.based_on', [], $locale);
        }
        if ($result['market']) {
            $head[] = __('reports.market_wage', [], $locale);
        }

        $body = [];
        foreach ($result['rows'] as $r) {
            $line = [$r['label']];
            if ($cols) {
                foreach ($cols as $c) {
                    $line[] = $this->raw($result['cells'][$r['key']][$c['key']] ?? null, $locale);
                }
            } else {
                $line[] = $this->raw($result['cells'][$r['key']]['_'] ?? null, $locale);
            }
            $line[] = $this->raw($result['row_totals'][$r['key']] ?? null, $locale);
            if ($withN) {
                $line[] = $result['row_totals'][$r['key']]['n'] ?? 0;
            }
            if ($result['market']) {
                $line[] = $result['market'][$r['key']] ?? '';
            }
            $body[] = $line;
        }
        $totals = [__('reports.total', [], $locale)];
        if ($cols) {
            foreach ($cols as $c) {
                $totals[] = $this->raw($result['col_totals'][$c['key']] ?? null, $locale);
            }
        } else {
            $totals[] = $this->raw($result['total'], $locale);
        }
        $totals[] = $this->raw($result['total'], $locale);
        if ($withN) {
            $totals[] = $result['total']['n'] ?? 0;
        }
        if ($result['market']) {
            $totals[] = '';
        }
        $body[] = $totals;

        return ['head' => $head, 'body' => $body];
    }

    private function raw(?array $cell, string $locale): mixed
    {
        if (! $cell) {
            return 0;
        }
        if (! empty($cell['small'])) {
            return __('reports.fewer_than_5', [], $locale);
        }

        return $cell['v'] ?? '';
    }

    private function fmt(?array $cell, string $measure): string
    {
        if (! $cell || $cell['v'] === null) {
            return ! empty($cell['small']) ? '<5' : '—';
        }

        return number_format((float) $cell['v'], $measure === 'count' ? 0 : 1);
    }
}
