<?php

/**
 * @package     AI Ask Desk for Joomla
 * @subpackage  com_askdesk
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Askdesk\Administrator\Desk;

use Gss\Component\Mcpask\Administrator\Ask\Translator;

\defined('_JEXEC') or die;

/**
 * Chinese kept in Traditional characters (0.6.2): where the settings say so, what the AI writes in Chinese is
 * Traditional with the words used in Taiwan, whatever the visitor wrote in, unless the page they came from is
 * itself in Simplified Chinese. An AI asked for Traditional sometimes answers in Simplified all the same: its
 * words are turned into Traditional before anyone sees them.
 *
 * @since  0.6.2
 */
final class Chinese
{
    /** Words written otherwise in mainland China, as they read once in Traditional characters, and Taiwan's. */
    private const WORDS = [
        '軟件' => '軟體', '硬件' => '硬體', '視頻' => '影片', '默認' => '預設', '網絡' => '網路', '數據庫' => '資料庫',
        '服務器' => '伺服器', '文件夾' => '資料夾', '鼠標' => '滑鼠', '屏幕' => '螢幕', '登錄' => '登入', '賬號' => '帳號',
        '賬戶' => '帳戶', '賬單' => '帳單', '激活' => '啟用', '鏈接' => '連結', '點擊' => '點選', '打印' => '列印',
        '在線' => '線上', '菜單' => '選單', '短信' => '簡訊', '二維碼' => 'QR Code', '郵箱' => '信箱', '博客' => '部落格',
        '質量' => '品質', '信息' => '資訊', '搜索' => '搜尋', '刷新' => '重新整理', '卸載' => '解除安裝', '反饋' => '回饋',
        '回復' => '回覆', '帖子' => '貼文', '字段' => '欄位', '界面' => '介面', '接口' => '介面', '運營' => '營運',
        '套餐' => '方案', '充值' => '儲值', '硬盤' => '硬碟', '光盤' => '光碟', 'U盤' => '隨身碟', '優盤' => '隨身碟',
        '移動端' => '行動版', '截屏' => '截圖', '兼容' => '相容', '模塊' => '模組', '調用' => '呼叫',
        '緩存' => '快取', '彈窗' => '彈出視窗', '導出' => '匯出', '導入' => '匯入', '源代碼' => '原始碼',
    ];

    /** Characters Taiwan writes too, which a turn into Traditional would change by themselves (干涉, 皇后, 公里). */
    private const SHARED = '干后里台了系面只制准游周余松谷丑斗范卷志冲注';

    /**
     * The language to write to a visitor in: Traditional for any Chinese, unless their page is Simplified.
     */
    public static function target(string $language, string $page): string
    {
        if (!Settings::load()->traditional() || !self::chinese($language) || self::simplified($page)) {
            return $language;
        }

        return 'zh-TW';
    }

    /**
     * Words the AI wrote, kept in Traditional where the language they were wanted in is (as the settings ask).
     */
    public static function keep(string $text, string $language): string
    {
        if ($text === '' || !self::chinese($language) || self::simplified($language) || !Settings::load()->traditional()) {
            return $text;
        }

        return self::traditional($text);
    }

    /**
     * Words in Traditional characters and Taiwan's words. Text already Traditional is left as written: only
     * where Simplified characters are found is it turned, so "了解" written by someone stays as it is.
     */
    public static function traditional(string $text): string
    {
        $turn = class_exists(\Transliterator::class) ? \Transliterator::create('Hans-Hant') : null;

        if (self::simplifiedIn($text, $turn)) {
            // Without PHP's intl, the characters the desk knows apart itself
            $text = $turn ? (string) $turn->transliterate($text)
                : strtr($text, array_diff_key(array_combine(mb_str_split(LanguageGuess::SIMPLIFIED), mb_str_split(LanguageGuess::TRADITIONAL)), array_flip(mb_str_split(self::SHARED))));
        }

        return strtr($text, self::WORDS);
    }

    /**
     * Whether there are characters in it only Simplified Chinese writes.
     */
    private static function simplifiedIn(string $text, ?\Transliterator $turn): bool
    {
        foreach (array_unique(mb_str_split($text)) as $char) {
            if (!preg_match('/\p{Han}/u', $char) || mb_strpos(self::SHARED, $char) !== false) {
                continue;
            }

            if ($turn ? $turn->transliterate($char) !== $char : mb_strpos(LanguageGuess::SIMPLIFIED, $char) !== false) {
                return true;
            }
        }

        return false;
    }

    private static function chinese(string $language): bool
    {
        return str_starts_with(strtolower(trim($language)), 'zh');
    }

    private static function simplified(string $language): bool
    {
        return self::chinese($language) && Translator::tag($language) === 'zh-CN';
    }
}
