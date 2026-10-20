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
 * The language of a message as its own letters tell it, on the server, without asking anyone
 * (0.1.0): kana is Japanese, Hangul Korean, Thai Thai; Chinese is told Traditional or Simplified
 * by the characters only one of them writes ("zh" when it has none of them: the same in both); Latin letters are English when its common words are
 * there, Vietnamese with its marks. Empty when it cannot be told (the AI then does it): a message
 * that is surely in the language of the one reading it is not translated at all.
 *
 * @since  0.1.0
 */
final class LanguageGuess
{
    /** Characters only Traditional Chinese writes, and their Simplified counterparts. */
    private const TRADITIONAL = '們這說個來時會對發經國過後學還沒現問開麼題電話樣關應進實動與長東車見門認識讀請謝讓給員錢區單眾類網錄態導機買賣處裡帳號碼標麗鐘將當麼歡態誰從風點萬華氣書記設計畫圖選擇儲備註冊專業務該頁訊統費級訂購嗎鍵議譯體';

    private const SIMPLIFIED = '们这说个来时会对发经国过后学还没现问开么题电话样关应进实动与长东车见门认识读请谢让给员钱区单众类网录态导机买卖处里账号码标丽钟将当么欢态谁从风点万华气书记设计画图选择储备注册专业务该页讯统费级订购吗键议译体';

    /** Common English words: a few of them, and Latin letters are English. */
    private const ENGLISH = ['the', 'is', 'are', 'i', 'you', 'to', 'and', 'of', 'a', 'it', 'my', 'can', 'how', 'what', 'do', 'not', 'please', 'thanks', 'thank', 'hello', 'hi', 'for', 'in', 'on', 'with', 'this', 'that', 'have', 'need', 'help', 'why', 'when', 'where', 'yes', 'no', 'ok', 'okay', 'we', 'your', 'be', 'will', 'there', 'login', 'password', 'account'];

    public static function detect(string $text): string
    {
        // Without addresses, codes and numbers: only words tell a language
        $text = (string) preg_replace(['#\bhttps?://\S+#u', '/\S+@\S+/u', '/[\d\p{P}\p{S}]+/u'], ' ', $text);

        if (preg_match('/[\p{Hiragana}\p{Katakana}]/u', $text)) {
            return 'ja';
        }

        if (preg_match('/\p{Hangul}/u', $text)) {
            return 'ko';
        }

        if (preg_match('/\p{Thai}/u', $text)) {
            return 'th';
        }

        $han = preg_match_all('/\p{Han}/u', $text);

        if ($han) {
            $traditional = 0;
            $simplified  = 0;

            foreach (mb_str_split($text) as $char) {
                $traditional += (int) (mb_strpos(self::TRADITIONAL, $char) !== false);
                $simplified  += (int) (mb_strpos(self::SIMPLIFIED, $char) !== false);
            }

            // Chinese (product names in Latin letters among it too): told by the characters only one
            // script writes; with none of them, it reads the same in both
            if ($traditional > $simplified) {
                return 'zh-TW';
            }

            if ($simplified > $traditional) {
                return 'zh-CN';
            }

            return 'zh';
        }

        if (preg_match('/[\x{0102}\x{0103}\x{0110}\x{0111}\x{01A0}\x{01A1}\x{01AF}\x{01B0}\x{1EA0}-\x{1EF9}]/u', $text)) {
            return 'vi';
        }

        // Latin letters alone: English when enough of its common words are there
        if (preg_match('/^[\sA-Za-z\']+$/u', $text) && preg_match('/[A-Za-z]/', $text)) {
            $words  = preg_split('/\s+/', strtolower(trim($text))) ?: [];
            $common = \count(array_intersect($words, self::ENGLISH));

            if ($common >= max(1, (int) ceil(\count($words) * 0.25))) {
                return 'en';
            }
        }

        return '';
    }

    /**
     * Whether a message is surely written in a language, so it needs no translating into it. Words
     * with nothing of a language in them (numbers, an address) need none either.
     */
    public static function already(string $text, string $language): bool
    {
        $words = (string) preg_replace(['#\bhttps?://\S+#u', '/\S+@\S+/u'], ' ', $text);

        if (!preg_match('/\p{L}{2,}/u', $words)) {
            return true;
        }

        $found = self::detect($text);

        // Chinese that reads the same in both scripts needs no translating into either
        if ($found === 'zh') {
            return str_starts_with(strtolower($language), 'zh');
        }

        return $found !== '' && \Gss\Component\Mcpask\Administrator\Ask\Translator::same($found, $language);
    }
}
