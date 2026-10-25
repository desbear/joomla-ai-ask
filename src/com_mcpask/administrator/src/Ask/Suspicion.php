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
 * Whether what a visitor wrote reads as reconnaissance or an attack, even when it was answered
 * (0.21.0): attempts to get the instructions out, addresses inside the server's network, commands,
 * searches for passwords and keys, strings scanners replay. Such a question is kept as evidence
 * (with the address and its words as sent), not forgotten after a fortnight. Turning it away is
 * Guard's: this only decides what is kept.
 *
 * @since  0.21.0
 */
final class Suspicion
{
    /**
     * @var array<string, string[]>  Patterns by kind
     */
    private const PATTERNS = [
        'injection' => [
            '/\b(ignore|disregard|forget|override)\b.{0,20}\b(all|any|previous|prior|above|earlier|preceding|system)\b.{0,20}\b(instructions?|prompts?|messages?|rules|context)\b/iu',
            '/(忽略|無視|忘記|忘掉|不要理會).{0,10}(先前|之前|以上|前面|上述|所有|系統).{0,10}(指示|指令|規則|提示|設定|訊息)/u',
            '/\b(system|initial|hidden|developer|original)\s+(prompt|message|instructions?)\b/iu',
            '/(系統提示|初始提示|系統指令|開發者訊息|提示詞|工具清單|工具列表)/u',
            '/\b(verbatim|word\s+for\s+word|everything\s+(above|before)\s+this)\b/iu',
            '/(逐字|原封不動|一字不漏).{0,10}(輸出|列出|印出|顯示|貼出)/u',
            '/\b(you\s+are\s+now|act\s+as\s+(an?\s+)?(unrestricted|jailbroken)|jailbreak|DAN\s+mode)\b/iu',
            '/\b(list|print|reveal|show|dump|output)\b.{0,20}\b(your\s+)?(tools?|functions?|instructions|prompt)\b/iu',
        ],
        'internal' => [
            '/\b(127\.\d{1,3}\.\d{1,3}\.\d{1,3}|0\.0\.0\.0|169\.254\.\d{1,3}\.\d{1,3}|10\.\d{1,3}\.\d{1,3}\.\d{1,3}|192\.168\.\d{1,3}\.\d{1,3}|172\.(1[6-9]|2\d|3[01])\.\d{1,3}\.\d{1,3})\b/u',
            '/(https?:\/\/|\/\/)(localhost|metadata\.google\.internal|instance-data)\b|\blocalhost:\d+|\[::1?\]/iu',
            '/\b(file|gopher|dict|ftp|ldap|jar|php|expect|data):\/\//iu',
            '#/(etc/(passwd|shadow|hostname|hosts|issue|group)|proc/(self|\d+|version|environ)|var/log/|root/)#iu',
            '#(\.\./){2,}|\.\.\\\\\.\.\\\\#u',
            '/\b(latest\/meta-data|computeMetadata|169\.254\.169\.254)\b/iu',
        ],
        'command' => [
            '/\b(uname\s+-[a-z]+|whoami|ifconfig|ipconfig|netstat\s+-|cat\s+\/|curl\s+(-[a-z]+\s+)*https?:|wget\s+https?:|bash\s+-c|sh\s+-c|nc\s+-[a-z]|rm\s+-rf|chmod\s+[0-7]{3}|powershell|cmd\.exe)\b/iu',
            '/(執行|运行|run|execute|exec).{0,12}\b(id|uname|whoami|ls|cat|pwd|env|ps)\b/iu',
            '/(;|\||&&|`|\$\()\s*(id|uname|whoami|cat|ls|curl|wget)\b/iu',
        ],
        'secrets' => [
            '/(搜尋|搜索|查詢|查找|列出|找出|給我|顯示|輸出|search|list|find|show|dump|give\s+me|print|extract).{0,24}(password|passwd|api[_\s-]?keys?|secret|tokens?|credentials?|private\s+key|密碼|金鑰|憑證|私鑰)/iu',
            '/(password|passwd|api[_\s-]?key|secret|token|credential).{0,6}(\||,|、|和|or|and).{0,12}(password|passwd|api[_\s-]?key|secret|token|credential)/iu',
        ],
        'replay' => [
            '/\b(ARTEX|CANARY|PWNED|HACKED)_[A-Z0-9_]+/u',
            '/<\s*(script|iframe|svg|img)\b|javascript:|on(error|load)\s*=/iu',
            '/\b(union\s+(all\s+)?select|or\s+1\s*=\s*1|sleep\s*\(\s*\d+\s*\)|benchmark\s*\()|\'\s*or\s+\'/iu',
            '/\$\{(jndi|env|sys):|\{\{.{0,40}\}\}|<%.*%>/iu',
        ],
    ];

    /**
     * The kind of attack the text reads as, or null.
     */
    public static function of(string $text): ?string
    {
        if (trim($text) === '') {
            return null;
        }

        foreach (self::PATTERNS as $kind => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $text)) {
                    return $kind;
                }
            }
        }

        return null;
    }
}
