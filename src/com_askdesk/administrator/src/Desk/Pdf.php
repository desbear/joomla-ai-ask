<?php

/**
 * @package     AI Ask Desk for Joomla
 * @subpackage  com_askdesk
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Askdesk\Administrator\Desk;

\defined('_JEXEC') or die;

/**
 * A small PDF writer for chat transcripts: A4 pages of wrapped lines, nothing else. Latin text is
 * set in Helvetica; Chinese, Japanese and Korean in the Adobe CID fonts PDF readers bring with them
 * (MSung, STSong, Kozuka Mincho, HYSMyeongJo), so no font is carried in the file.
 *
 * @since  0.1.0
 */
final class Pdf
{
    private const WIDTH  = 595.28;
    private const HEIGHT = 841.89;
    private const MARGIN = 56;

    /** The CID fonts, by the language they are chosen for. */
    private const CJK = [
        'tw' => ['MSung-Light', 'UniCNS-UCS2-H', 'CNS1', 0],
        'cn' => ['STSong-Light', 'UniGB-UCS2-H', 'GB1', 2],
        'ja' => ['KozMinPro-Regular', 'UniJIS-UCS2-H', 'Japan1', 4],
        'ko' => ['HYSMyeongJo-Medium', 'UniKS-UCS2-H', 'Korea1', 1],
    ];

    /** Helvetica's widths of the characters 32 to 126, in thousandths of the size. */
    private const HELVETICA = [
        278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278,
        556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 278, 278, 584, 584, 584, 556,
        1015, 667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778,
        667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 278, 278, 278, 469, 556,
        333, 556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556, 556,
        556, 556, 333, 500, 278, 556, 500, 722, 500, 500, 500, 334, 260, 334, 584,
    ];

    /** @var string[]  Each page's drawing */
    private array $pages = [];

    private string $page = '';

    private float $y = 0;

    private string $cjk;

    /**
     * @param  string  $language  The language of the words, to choose the CID font
     */
    public function __construct(string $language, private string $title = '')
    {
        $tag = strtolower($language);

        $this->cjk = match (true) {
            str_starts_with($tag, 'ja')                                                                  => 'ja',
            str_starts_with($tag, 'ko')                                                                  => 'ko',
            \in_array($tag, ['zh-cn', 'zh-sg', 'zh-hans', 'zh'], true) || str_starts_with($tag, 'zh-hans') => 'cn',
            default                                                                                       => 'tw',
        };

        $this->newPage();
    }

    /**
     * Words wrapped to the page's width.
     *
     * @param  array{size?: float, grey?: bool, bold?: bool, indent?: float, after?: float, center?: bool}  $style
     */
    public function text(string $text, array $style = []): void
    {
        $size   = (float) ($style['size'] ?? 10.5);
        $indent = (float) ($style['indent'] ?? 0);
        $width  = self::WIDTH - 2 * self::MARGIN - $indent;
        $leading = $size * 1.55;

        foreach (preg_split('/\R/u', $text) ?: [''] as $paragraph) {
            foreach ($this->wrap($paragraph, $size, $width) as $line) {
                if ($this->y - $leading < self::MARGIN) {
                    $this->newPage();
                }

                $this->y -= $leading;
                $x = self::MARGIN + $indent + (!empty($style['center']) ? ($width - $this->measure($line, $size)) / 2 : 0);
                $this->line($line, $x, $this->y + ($leading - $size) / 2, $size, !empty($style['grey']), !empty($style['bold']));
            }
        }

        $this->y -= (float) ($style['after'] ?? 0);
    }

    /** A thin line across the page. */
    public function rule(float $after = 10): void
    {
        $this->y -= 6;
        $this->page .= sprintf("0.85 0.88 0.92 RG 0.5 w %.2F %.2F m %.2F %.2F l S\n", self::MARGIN, $this->y, self::WIDTH - self::MARGIN, $this->y);
        $this->y -= $after;
    }

    public function space(float $points): void
    {
        $this->y -= $points;
    }

    /**
     * The file.
     */
    public function output(): string
    {
        $this->pages[] = $this->page;
        $this->page    = '';

        [$name, $encoding, $ordering, $supplement] = self::CJK[$this->cjk];

        $objects = [];
        $pages   = \count($this->pages);
        // 1 catalog, 2 pages, 3 Helvetica, 4 CID font, 5 its descendant, 6 its descriptor, 7 info, then each page and its drawing
        $kids = [];

        for ($i = 0; $i < $pages; $i++) {
            $kids[] = (8 + 2 * $i) . ' 0 R';
        }

        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $pages . ' >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[4] = '<< /Type /Font /Subtype /Type0 /BaseFont /' . $name . '-' . $encoding . ' /Encoding /' . $encoding . ' /DescendantFonts [5 0 R] >>';
        $objects[5] = '<< /Type /Font /Subtype /CIDFontType0 /BaseFont /' . $name . ' /CIDSystemInfo << /Registry (Adobe) /Ordering (' . $ordering . ') /Supplement ' . $supplement . ' >> /FontDescriptor 6 0 R /DW 1000 >>';
        $objects[6] = '<< /Type /FontDescriptor /FontName /' . $name . ' /Flags 6 /FontBBox [-25 -254 1000 880] /ItalicAngle 0 /Ascent 880 /Descent -120 /CapHeight 880 /StemV 93 >>';
        $objects[7] = '<< /Producer (AI Ask Desk) /Title ' . self::utf16($this->title) . ' /CreationDate (D:' . gmdate('YmdHis') . "Z) >>";

        foreach ($this->pages as $i => $content) {
            $objects[8 + 2 * $i] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . self::WIDTH . ' ' . self::HEIGHT . '] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . (9 + 2 * $i) . ' 0 R >>';
            $stream = gzcompress($content);
            $objects[9 + 2 * $i] = '<< /Length ' . \strlen($stream) . " /Filter /FlateDecode >>\nstream\n" . $stream . "\nendstream";
        }

        $pdf     = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];

        foreach ($objects as $number => $object) {
            $offsets[$number] = \strlen($pdf);
            $pdf .= $number . " 0 obj\n" . $object . "\nendobj\n";
        }

        $xref = \strlen($pdf);
        $pdf .= 'xref' . "\n0 " . (\count($objects) + 1) . "\n0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf . 'trailer' . "\n<< /Size " . (\count($objects) + 1) . " /Root 1 0 R /Info 7 0 R >>\nstartxref\n" . $xref . "\n%%EOF\n";
    }

    private function newPage(): void
    {
        if ($this->page !== '' || $this->pages) {
            $this->pages[] = $this->page;
        }

        $this->page = '';
        $this->y    = self::HEIGHT - self::MARGIN;

        if ($this->title !== '' && $this->pages) {
            // Pages after the first say whose they are
            $this->line($this->title . '  ·  ' . (\count($this->pages) + 1), self::MARGIN, self::HEIGHT - self::MARGIN + 16, 8, true, false);
        }
    }

    /**
     * One line, in runs of Helvetica and of the CID font.
     */
    private function line(string $line, float $x, float $y, float $size, bool $grey, bool $bold): void
    {
        $out = 'BT ' . ($grey ? '0.42 0.45 0.5 rg 0.42 0.45 0.5 RG' : '0.07 0.09 0.13 rg 0.07 0.09 0.13 RG') . ($bold ? ' 2 Tr ' . round($size / 30, 2) . ' w' : ' 0 Tr') . sprintf(' %.2F %.2F Td ', $x, $y);

        foreach ($this->runs($line) as [$latin, $run]) {
            if ($latin) {
                $out .= '/F1 ' . $size . ' Tf (' . strtr(self::winAnsi($run), ['\\' => '\\\\', '(' => '\\(', ')' => '\\)']) . ') Tj ';
            } else {
                $out .= '/F2 ' . $size . ' Tf <' . strtoupper(bin2hex(mb_convert_encoding($run, 'UTF-16BE', 'UTF-8'))) . '> Tj ';
            }
        }

        $this->page .= $out . "ET\n";
    }

    /**
     * @return  array<int, array{0: bool, 1: string}>  Runs of text, whether each is Latin
     */
    private function runs(string $line): array
    {
        $runs = [];

        foreach (mb_str_split($line) as $char) {
            // Beyond the first plane no font here has it; the wide tilde some fonts draw as another
            // character is a plain one
            $char  = mb_ord($char) > 0xFFFF ? '?' : ($char === '～' ? '~' : $char);
            $latin = self::isLatin($char);

            if ($runs && $runs[\count($runs) - 1][0] === $latin) {
                $runs[\count($runs) - 1][1] .= $char;
            } else {
                $runs[] = [$latin, $char];
            }
        }

        return $runs;
    }

    private static function isLatin(string $char): bool
    {
        $code = mb_ord($char);

        return $code < 0x2000 && @mb_convert_encoding($char, 'Windows-1252', 'UTF-8') !== '?' || \in_array($char, ['?'], true);
    }

    private static function winAnsi(string $text): string
    {
        return (string) mb_convert_encoding($text, 'Windows-1252', 'UTF-8');
    }

    private function measure(string $text, float $size): float
    {
        $width = 0;

        foreach (mb_str_split($text) as $char) {
            $code   = mb_ord($char);
            $width += $code >= 32 && $code <= 126 ? self::HELVETICA[$code - 32] : (self::isLatin($char) ? 556 : 1000);
        }

        return $width * $size / 1000;
    }

    /**
     * Lines that fit the width: broken between words, or anywhere in Chinese, Japanese and Korean.
     *
     * @return  string[]
     */
    private function wrap(string $text, float $size, float $width): array
    {
        $text = rtrim(preg_replace('/[\x00-\x08\x0B-\x1F\x7F]/u', '', $text) ?? '');

        if ($text === '') {
            return [''];
        }

        // Pieces that stay together: a word with what follows it, or one character of the rest
        preg_match_all('/[\x{0021}-\x{1FFF}]+\s*|\s+|./u', $text, $matches);

        $lines = [];
        $line  = '';

        foreach ($matches[0] as $piece) {
            if ($line !== '' && $this->measure(rtrim($line . $piece), $size) > $width) {
                $lines[] = rtrim($line);
                $line    = ltrim($piece);
            } else {
                $line .= $piece;
            }

            // A word longer than the line is cut
            while ($this->measure($line, $size) > $width && mb_strlen($line) > 1) {
                $cut = mb_strlen($line) - 1;

                while ($cut > 1 && $this->measure(mb_substr($line, 0, $cut), $size) > $width) {
                    $cut--;
                }

                $lines[] = mb_substr($line, 0, $cut);
                $line    = mb_substr($line, $cut);
            }
        }

        $lines[] = rtrim($line);

        return $lines;
    }

    private static function utf16(string $text): string
    {
        return '<FEFF' . strtoupper(bin2hex(mb_convert_encoding($text, 'UTF-16BE', 'UTF-8'))) . '>';
    }
}
