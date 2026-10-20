<?php

/**
 * @package     AI Ask Desk for Joomla
 * @subpackage  com_askdesk
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Askdesk\Administrator\Desk;

use Joomla\CMS\Http\HttpFactory;

\defined('_JEXEC') or die;

/**
 * A quick machine translation (0.1.0), for a message the AI is slow to translate: Google Cloud
 * Translation, in well under a second. Shown for the moment, marked as such, until the AI's comes.
 *
 * @since  0.1.0
 */
final class QuickTranslate
{
    private const URL = 'https://translation.googleapis.com/language/translate/v2';

    public function __construct(private Settings $settings)
    {
    }

    /**
     * @return  string  The translation; empty when it cannot be had
     */
    public function translate(string $text, string $source, string $target): string
    {
        $key = $this->settings->googleKey();

        if ($this->settings->quickTranslate() !== 'google' || $key === '' || trim($text) === '' || $target === '') {
            return '';
        }

        $query = ['q' => $text, 'target' => self::code($target), 'format' => 'text'];

        if (self::code($source) !== '' && self::code($source) !== $query['target']) {
            $query['source'] = self::code($source);
        }

        try {
            $response = HttpFactory::getHttp()->post(
                self::URL . '?key=' . rawurlencode($key),
                json_encode($query, JSON_UNESCAPED_UNICODE),
                ['Content-Type' => 'application/json; charset=utf-8'],
                4
            );
        } catch (\Throwable) {
            return '';
        }

        if ($response->code !== 200) {
            return '';
        }

        $data = json_decode((string) $response->body, true);

        return trim((string) ($data['data']['translations'][0]['translatedText'] ?? ''));
    }

    /** A language as Google names it: Chinese by its script, the rest by the language alone. */
    private static function code(string $tag): string
    {
        $lower = strtolower(trim($tag));

        return match (true) {
            $lower === ''                                                  => '',
            \in_array($lower, ['zh-tw', 'zh-hk', 'zh-mo', 'zh-hant'], true) => 'zh-TW',
            str_starts_with($lower, 'zh')                                  => 'zh-CN',
            default                                                        => explode('-', $lower)[0],
        };
    }
}
