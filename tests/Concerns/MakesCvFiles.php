<?php

namespace Tests\Concerns;

use App\Services\Cv\TextExtractor;
use Dompdf\Dompdf;
use Illuminate\Http\UploadedFile;
use ZipArchive;

// ══════════════════════════════════════════════════════════════════
//  Massar — MakesCvFiles (test helper)
//  Location: tests/Concerns/MakesCvFiles.php
//
//  Builds real CV files for the tests, in a temporary folder, so the
//  tests never touch real people's CVs:
//    docx($lines)   a Word file; a line starting with '#' is a bold heading
//    pdf($lines)    a PDF with real text (made with dompdf)
//    scannedPdf()   a PDF with no text in it, like a scan
//    lockedPdf()    a PDF that opens only with a password
//    oldDoc()       an old Word .doc file
//  PDF tests need Poppler's pdftotext; without it they are skipped
//  (needsPdfReader) and say why.
// ══════════════════════════════════════════════════════════════════

trait MakesCvFiles
{
    protected function docx(array $lines, string $name = 'cv.docx'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'cv');
        $esc = fn ($t) => htmlspecialchars($t, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $body = '';
        foreach ($lines as $l) {
            $bold = str_starts_with($l, '#');
            $text = $bold ? trim(substr($l, 1)) : $l;
            $body .= '<w:p><w:r>'.($bold ? '<w:rPr><w:b/></w:rPr>' : '').'<w:t xml:space="preserve">'.$esc($text).'</w:t></w:r></w:p>';
        }
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'.$body.'</w:body></w:document>');
        $zip->close();

        return new UploadedFile($path, $name, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true);
    }

    protected function pdf(array $lines, string $name = 'cv.pdf'): UploadedFile
    {
        $html = '<html><body style="font-family: DejaVu Sans; font-size: 11px">'
            .implode('', array_map(fn ($l) => '<p style="margin:0 0 4px">'.htmlspecialchars(ltrim($l, '#')).'</p>', $lines)).'</body></html>';
        $dompdf = new Dompdf;
        $dompdf->loadHtml($html);
        $dompdf->render();
        $path = tempnam(sys_get_temp_dir(), 'cv');
        file_put_contents($path, $dompdf->output());

        return new UploadedFile($path, $name, 'application/pdf', null, true);
    }

    protected function scannedPdf(string $name = 'scan.pdf'): UploadedFile
    {
        // One empty page: what a scanned CV looks like to a text reader.
        $pdf = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
            ."3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 595 842]>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF";
        $path = tempnam(sys_get_temp_dir(), 'cv');
        file_put_contents($path, $pdf);

        return new UploadedFile($path, $name, 'application/pdf', null, true);
    }

    protected function lockedPdf(string $name = 'locked.pdf'): UploadedFile
    {
        $dompdf = new Dompdf;
        $dompdf->loadHtml('<p>Ahmed Hassan Mahmoud</p><p>Senior Accountant, Cairo</p>');
        $dompdf->render();
        $dompdf->getCanvas()->get_cpdf()->setEncryption('user-password', 'owner-password');
        $path = tempnam(sys_get_temp_dir(), 'cv');
        file_put_contents($path, $dompdf->output());

        return new UploadedFile($path, $name, 'application/pdf', null, true);
    }

    protected function oldDoc(string $name = 'old.doc'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'cv');
        file_put_contents($path, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1".str_repeat("\0", 500));

        return new UploadedFile($path, $name, 'application/msword', null, true);
    }

    protected function needsPdfReader(): void
    {
        if (! app(TextExtractor::class)->pdfReaderReady()) {
            $this->markTestSkipped('Poppler pdftotext is not installed or PDFTOTEXT_PATH in .env is wrong.');
        }
    }

    protected function englishCv(array $extra = []): array
    {
        return array_merge([
            'Ahmed Hassan Mahmoud',
            'Senior Accountant',
            'Nasr City, Cairo | +20 100 555 1234 | ahmed.hassan@example.com',
            '#PROFESSIONAL SUMMARY',
            'Accountant with 6 years of experience in general accounting.',
            '#WORK EXPERIENCE',
            'Accountant — Delta Trading | Mar 2021 – Present',
            '- Prepared monthly financial statements',
            'Junior Accountant, Nile Foods (01/2018 - 02/2021)',
            '#EDUCATION',
            'Bachelor of Commerce, Accounting — Cairo University, 2017',
            '#SKILLS',
            'Microsoft Excel, SAP, Budget planning',
            '#LANGUAGES',
            'Arabic: Native',
            'English: Fluent',
            '#PERSONAL INFORMATION',
            'Date of Birth: 23/05/1995',
            'Gender: Male',
            'Military Status: Completed',
        ], $extra);
    }

    protected function arabicCv(): array
    {
        return [
            'منى عبد الرحمن السيد',
            'محاسبة',
            'المعادي، القاهرة',
            'موبايل: ٠١٢٢٣٤٥٦٧٨٩',
            'البريد الإلكتروني: mona.sayed@example.com',
            '#البيانات الشخصية',
            'تاريخ الميلاد: 12/03/1998',
            'الحالة الاجتماعية: عزباء',
            '#الخبرات العملية',
            'محاسبة لدى شركة النيل للتجارة من 2020 حتى الآن',
            '#المؤهلات الدراسية',
            'بكالوريوس تجارة شعبة محاسبة - جامعة عين شمس 2019',
            '#اللغات',
            'العربية: اللغة الأم',
            'الإنجليزية: جيد جدا',
        ];
    }
}
