<?php

/**
 * @package     AI Ask for Joomla
 * @subpackage  com_mcpask
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Mcpask\Administrator\Ask;

\defined('_JEXEC') or die;

/**
 * What visitors write, with what identifies a person or opens an account taken out before it is
 * kept (0.21.0): e-mail addresses, telephone numbers, national ID numbers, card numbers, and
 * passwords, keys and tokens written out. What the question is about stays readable.
 *
 * @since  0.21.0
 */
final class Masker
{
    public static function mask(string $text): string
    {
        if ($text === '') {
            return $text;
        }

        $rules = [
            // A password, key or token written out: what follows the word goes
            '/\b(password|passwd|pwd|passcode|secret|api[_\- ]?key|access[_\- ]?key|token|密碼|金鑰|驗證碼)(\s*[:=：]\s*)([^\s,;，；]{4,})/iu' => '$1$2[secret]',
            '/\b(bearer|basic)(\s+)([A-Za-z0-9._~+\/=\-]{12,})/i' => '$1$2[secret]',
            // Keys that look like keys whatever is written before them
            '/\b(sk|pk|rk)[-_][A-Za-z0-9_\-]{16,}\b/' => '[secret]',
            '/\beyJ[A-Za-z0-9_\-]{10,}\.[A-Za-z0-9_\-]{10,}\.[A-Za-z0-9_\-]{5,}\b/' => '[secret]',
            '/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/u' => '[email]',
            // National ID of Taiwan (and the resident certificate's), and of other places written alike
            '/\b[A-Z][12890]\d{8}\b/' => '[id]',
        ];

        foreach ($rules as $pattern => $replacement) {
            $text = (string) preg_replace($pattern, $replacement, $text);
        }

        // Card numbers: 13 to 19 digits, in groups or not, that pass the Luhn check
        $text = (string) preg_replace_callback('/\b(?:\d[ \-]?){12,18}\d\b/', static function (array $match): string {
            $digits = preg_replace('/\D/', '', $match[0]);

            return self::luhn((string) $digits) ? '[card]' : $match[0];
        }, $text);

        // Telephone numbers: Taiwan's mobile and fixed lines, and international numbers
        $text = (string) preg_replace([
            '/(?<![\d\-])(?:\+?886[\s\-]?|0)9\d{2}[\s\-]?\d{3}[\s\-]?\d{3}(?![\d\-])/',
            '/(?<![\d\-])(?:\+?886[\s\-]?\(?0?|\(?0)[2-8]\)?[\s\-]?\d{3,4}[\s\-]?\d{4}(?:\s*(?:#|ext\.?|分機)\s*\d{1,5})?(?![\d\-])/iu',
            '/(?<![\w\-])\+\d{1,3}[\s\-]?(?:\(?\d{1,4}\)?[\s\-]?){2,4}\d{2,4}(?![\d\-])/',
        ], '[phone]', $text);

        return $text;
    }

    private static function luhn(string $digits): bool
    {
        $length = \strlen($digits);

        if ($length < 13 || $length > 19) {
            return false;
        }

        $sum = 0;

        for ($i = 0; $i < $length; $i++) {
            $digit = (int) $digits[$length - 1 - $i];

            if ($i % 2 === 1) {
                $digit *= 2;
                $digit = $digit > 9 ? $digit - 9 : $digit;
            }

            $sum += $digit;
        }

        return $sum % 10 === 0;
    }
}
