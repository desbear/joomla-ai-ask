<?php

/**
 * @package     AI Ask for Joomla
 * @subpackage  com_mcpask
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Mcpask\Administrator\Ask;

use Gss\Component\Mcpask\Administrator\Agent\MarkdownLite;

\defined('_JEXEC') or die;

/**
 * The text of a document as light HTML, for the index: a PDF page by page, a presentation slide
 * by slide (with its notes), a spreadsheet sheet by sheet as tables, a Word document with its
 * headings and tables, Markdown and plain text. Each page, slide or sheet is a section of its own,
 * so a passage can say where in the document it is ("p. 12", "Slide 3").
 *
 * PDF files are read with smalot/pdfparser (LGPL), kept with the component; Office files are ZIP
 * archives of XML and need nothing but PHP's zip extension. Pictures are not read: a scanned PDF,
 * or a slide that is only a picture, has no text here.
 *
 * @since  0.19.0
 */
final class DocumentText
{
    /** Kinds of documents read, by extension. */
    public const EXTENSIONS = ['pdf', 'docx', 'pptx', 'xlsx', 'md', 'markdown', 'txt'];

    /** Rows of a sheet read at most: a long table is mostly more of the same. */
    private const MAX_ROWS = 300;

    /** Characters of text kept at most from one document. */
    private const MAX_CHARS = 400000;

    /**
     * @return  array{html: string, parts: int}  The text, and how many pages, slides or sheets it has
     *
     * @throws  \RuntimeException  When the document cannot be read
     */
    public static function read(string $path, string $extension = ''): array
    {
        $extension = strtolower($extension ?: pathinfo($path, PATHINFO_EXTENSION));

        if (!is_file($path) || !is_readable($path)) {
            throw new \RuntimeException('The file is not there.');
        }

        [$html, $parts] = match ($extension) {
            'pdf'              => self::pdf($path),
            'docx'             => self::docx($path),
            'pptx'             => self::pptx($path),
            'xlsx'             => self::xlsx($path),
            'md', 'markdown'   => [MarkdownLite::html(self::cut((string) file_get_contents($path))), 1],
            'txt'              => ['<p>' . nl2br(htmlspecialchars(self::cut((string) file_get_contents($path)), ENT_QUOTES, 'UTF-8')) . '</p>', 1],
            default            => throw new \RuntimeException('Documents of this kind are not read: ' . $extension),
        };

        return ['html' => $html, 'parts' => $parts];
    }

    public static function supports(string $extension): bool
    {
        return \in_array(strtolower($extension), self::EXTENSIONS, true);
    }

    /**
     * A PDF, page by page. Pictures are left aside, so a document full of them does not take more
     * memory than its text needs.
     */
    private static function pdf(string $path): array
    {
        self::loadPdfParser();

        $config = new \Smalot\PdfParser\Config();
        $config->setRetainImageContent(false);
        $config->setDecodeMemoryLimit(16 * 1048576);

        try {
            $document = (new \Smalot\PdfParser\Parser([], $config))->parseFile($path);
        } catch (\Throwable $e) {
            throw new \RuntimeException('The PDF could not be read: ' . $e->getMessage());
        }

        $html  = '';
        $count = 0;
        $kept  = 0;

        foreach ($document->getPages() as $number => $page) {
            $count++;

            try {
                $text = trim((string) $page->getText());
            } catch (\Throwable) {
                continue;
            }

            if ($text === '' || $kept > self::MAX_CHARS) {
                continue;
            }

            $kept += mb_strlen($text);
            $html .= '<h2>p. ' . ($number + 1) . "</h2>\n" . self::paragraphs($text);
        }

        return [$html, $count];
    }

    /**
     * A Word document: its paragraphs, headings as headings, tables as tables.
     */
    private static function docx(string $path): array
    {
        $xml  = self::part($path, 'word/document.xml');
        $html = '';

        if ($xml === null) {
            throw new \RuntimeException('The Word document could not be read.');
        }

        $xml->registerXPathNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        foreach ($xml->xpath('//w:body/*') ?: [] as $block) {
            $name = $block->getName();
            $block->registerXPathNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

            if ($name === 'p') {
                $text  = self::runs($block, './/w:t');
                $style = (string) ($block->xpath('w:pPr/w:pStyle/@w:val')[0] ?? '');

                if ($text === '') {
                    continue;
                }

                // Headings by their style ("Heading1", "Heading 2", "標題 1" and the like)
                $html .= preg_match('/^(heading|標題|title)\s*([1-3])?/iu', $style, $match)
                    ? '<h' . (min(3, (int) ($match[2] ?? 1)) + 1) . '>' . self::e($text) . '</h' . (min(3, (int) ($match[2] ?? 1)) + 1) . ">\n"
                    : '<p>' . self::e($text) . "</p>\n";
            } elseif ($name === 'tbl') {
                $rows = [];

                foreach ($block->xpath('w:tr') ?: [] as $row) {
                    $row->registerXPathNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
                    $rows[] = array_map(static function ($cell) {
                        $cell->registerXPathNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

                        return self::runs($cell, './/w:t');
                    }, $row->xpath('w:tc') ?: []);
                }

                $html .= self::table($rows);
            }

            if (mb_strlen($html) > self::MAX_CHARS) {
                break;
            }
        }

        return [$html, 1];
    }

    /**
     * A presentation, slide by slide in their order, each with its notes.
     */
    private static function pptx(string $path): array
    {
        $zip    = self::zip($path);
        $slides = [];

        // The slides in the order the presentation shows them (not the order of their file names)
        $deck  = self::parse($zip->getFromName('ppt/presentation.xml'));
        $links = self::targets(self::parse($zip->getFromName('ppt/_rels/presentation.xml.rels')), 'ppt/');

        foreach ($deck !== null ? ($deck->xpath('//*[local-name()="sldId"]') ?: []) : [] as $slide) {
            $id = (string) ($slide->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'] ?? '');

            if (isset($links[$id])) {
                $slides[\count($slides) + 1] = $links[$id];
            }
        }

        // A presentation without that list: the slides by the numbers of their files
        if (!$slides) {
            for ($index = 0; $index < $zip->numFiles; $index++) {
                if (preg_match('#^ppt/slides/slide(\d+)\.xml$#', (string) $zip->getNameIndex($index), $match)) {
                    $slides[(int) $match[1]] = $match[0];
                }
            }

            ksort($slides);
        }

        $html = '';

        foreach ($slides as $number => $name) {
            $xml   = self::parse($zip->getFromName($name));
            // Its notes, wherever the slide says they are
            $own   = self::targets(self::parse($zip->getFromName(\dirname($name) . '/_rels/' . basename($name) . '.rels')), \dirname($name) . '/');
            $place = array_values(array_filter($own, static fn ($target) => str_contains($target, 'notesSlide')))[0] ?? '';
            $notes = $place !== '' ? self::parse($zip->getFromName($place)) : null;
            $lines = $xml !== null ? self::paragraphLines($xml) : [];
            $said  = $notes !== null ? array_filter(self::paragraphLines($notes), static fn ($line) => !preg_match('/^\d+$/', $line)) : [];

            if (!$lines && !$said) {
                continue;
            }

            $html .= '<h2>Slide ' . $number . ($lines ? ' — ' . self::e(mb_substr($lines[0], 0, 80)) : '') . "</h2>\n";
            $html .= implode('', array_map(static fn ($line) => '<p>' . self::e($line) . "</p>\n", \array_slice($lines, 1)));

            if ($said) {
                $html .= '<p>' . self::e(implode(' ', $said)) . "</p>\n";
            }

            if (mb_strlen($html) > self::MAX_CHARS) {
                break;
            }
        }

        $zip->close();

        return [$html, \count($slides)];
    }

    /**
     * A spreadsheet, sheet by sheet, each as a table of the values its cells show.
     */
    private static function xlsx(string $path): array
    {
        $zip     = self::zip($path);
        $strings = [];
        $shared  = self::parse($zip->getFromName('xl/sharedStrings.xml'));

        foreach ($shared !== null ? $shared->children() : [] as $item) {
            $strings[] = self::runs($item, './/*[local-name()="t"]');
        }

        // The sheets by name, in the workbook's order, with the file each is kept in
        $book  = self::parse($zip->getFromName('xl/workbook.xml'));
        $links = self::parse($zip->getFromName('xl/_rels/workbook.xml.rels'));
        $files = [];

        foreach ($links !== null ? $links->children() : [] as $link) {
            $files[(string) $link['Id']] = 'xl/' . ltrim(str_replace('/xl/', '', (string) $link['Target']), '/');
        }

        $html  = '';
        $count = 0;

        foreach ($book !== null ? ($book->xpath('//*[local-name()="sheet"]') ?: []) : [] as $sheet) {
            $count++;
            $id    = (string) ($sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'] ?? '');
            $xml   = isset($files[$id]) ? self::parse($zip->getFromName($files[$id])) : null;
            $rows  = [];

            foreach ($xml !== null ? ($xml->xpath('//*[local-name()="sheetData"]/*[local-name()="row"]') ?: []) : [] as $row) {
                $cells = [];

                foreach ($row->children() as $cell) {
                    $value = (string) ($cell->children()->v ?? '');
                    $type  = (string) $cell['t'];

                    $cells[] = match ($type) {
                        's'         => $strings[(int) $value] ?? '',
                        'inlineStr' => self::runs($cell, './/*[local-name()="t"]'),
                        default     => $value,
                    };
                }

                if (array_filter($cells, static fn ($cell) => trim($cell) !== '')) {
                    $rows[] = $cells;
                }

                if (\count($rows) >= self::MAX_ROWS) {
                    break;
                }
            }

            if ($rows) {
                $html .= '<h2>' . self::e((string) $sheet['name']) . "</h2>\n" . self::table($rows);
            }

            if (mb_strlen($html) > self::MAX_CHARS) {
                break;
            }
        }

        $zip->close();

        return [$html, $count];
    }

    /**
     * The parts a relationships file points to, by relationship ID, as paths in the archive.
     *
     * @return  array<string, string>
     */
    private static function targets(?\SimpleXMLElement $relationships, string $base): array
    {
        $targets = [];

        foreach ($relationships !== null ? $relationships->children() : [] as $relationship) {
            $target = (string) $relationship['Target'];
            $path   = str_starts_with($target, '/') ? ltrim($target, '/') : $base . $target;

            // "../notesSlides/notesSlide1.xml" from ppt/slides/
            while (preg_match('#[^/]+/\.\./#', $path)) {
                $path = (string) preg_replace('#[^/]+/\.\./#', '', $path, 1);
            }

            $targets[(string) $relationship['Id']] = $path;
        }

        return $targets;
    }

    private static function loadPdfParser(): void
    {
        if (!class_exists(\Smalot\PdfParser\Parser::class, false)) {
            \JLoader::registerNamespace('Smalot\\PdfParser', \dirname(__DIR__, 2) . '/vendor/pdfparser/Smalot/PdfParser');
        }
    }

    private static function zip(string $path): \ZipArchive
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('PHP\'s zip extension is needed to read Office documents.');
        }

        $zip = new \ZipArchive();

        if ($zip->open($path, \ZipArchive::RDONLY) !== true) {
            throw new \RuntimeException('The document could not be opened.');
        }

        return $zip;
    }

    private static function part(string $path, string $name): ?\SimpleXMLElement
    {
        $zip = self::zip($path);
        $xml = self::parse($zip->getFromName($name));
        $zip->close();

        return $xml;
    }

    private static function parse(string|false $xml): ?\SimpleXMLElement
    {
        if (!\is_string($xml) || $xml === '') {
            return null;
        }

        $previous = libxml_use_internal_errors(true);
        $parsed   = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT);
        libxml_use_internal_errors($previous);

        // An element whose children are all in a namespace counts as false: only failure is null
        return $parsed === false ? null : $parsed;
    }

    /**
     * The text of the runs within an element, joined.
     */
    private static function runs(\SimpleXMLElement $element, string $path): string
    {
        return trim(implode('', array_map('strval', $element->xpath($path) ?: [])));
    }

    /**
     * The lines of a slide or of its notes: one per paragraph (a:p), its runs joined.
     *
     * @return  string[]
     */
    private static function paragraphLines(\SimpleXMLElement $xml): array
    {
        $lines = [];

        foreach ($xml->xpath('//*[local-name()="p"]') ?: [] as $paragraph) {
            $line = trim(implode('', array_map('strval', $paragraph->xpath('.//*[local-name()="t"]') ?: [])));

            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    /**
     * Text with its line breaks as paragraphs: lines that run on are joined.
     */
    private static function paragraphs(string $text): string
    {
        $blocks = preg_split('/\n\s*\n/u', str_replace("\r", '', $text)) ?: [];
        $html   = '';

        foreach ($blocks as $block) {
            $block = trim((string) preg_replace('/[ \t]*\n[ \t]*/u', "\n", $block));

            if ($block !== '') {
                $html .= '<p>' . nl2br(self::e($block), false) . "</p>\n";
            }
        }

        return $html;
    }

    /**
     * @param  array<int, string[]>  $rows
     */
    private static function table(array $rows): string
    {
        if (!$rows) {
            return '';
        }

        $html = "<table>\n";

        foreach ($rows as $row) {
            $html .= '<tr>' . implode('', array_map(static fn ($cell) => '<td>' . self::e(trim($cell)) . '</td>', $row)) . "</tr>\n";
        }

        return $html . "</table>\n";
    }

    private static function cut(string $text): string
    {
        return mb_substr(preg_replace('/^\xEF\xBB\xBF/', '', $text) ?? $text, 0, self::MAX_CHARS);
    }

    private static function e(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}
