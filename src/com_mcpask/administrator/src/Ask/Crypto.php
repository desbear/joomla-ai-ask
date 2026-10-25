<?php

/**
 * @package     AI Ask for Joomla
 * @subpackage  com_mcpask
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Mcpask\Administrator\Ask;

use Joomla\CMS\Factory;

\defined('_JEXEC') or die;

/**
 * Secrets kept in the database encrypted (0.20.8): AI service keys, export and peer keys, embed
 * secrets, the secret of another site's AI Ask. AES-256-GCM with a key made from the site's own
 * secret (configuration.php), so that a copy of the database or a backup of it alone does not give
 * them away. It does not keep them from someone who controls the site: changing them is what
 * helps then.
 *
 * Stored as "enc:v1:" followed by base64 of IV, tag and ciphertext. Values without the prefix are
 * taken as they are (written before 0.20.8), until the upgrade or the next save seals them.
 *
 * @since  0.20.8
 */
final class Crypto
{
    public const PREFIX = 'enc:v1:';

    public static function isSealed(string $value): bool
    {
        return str_starts_with($value, self::PREFIX);
    }

    /**
     * The value encrypted; an empty value or one already sealed stays as it is.
     */
    public static function seal(string $value): string
    {
        $value = trim($value);

        if ($value === '' || self::isSealed($value)) {
            return $value;
        }

        $iv     = random_bytes(12);
        $tag    = '';
        $cipher = openssl_encrypt($value, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);

        if ($cipher === false) {
            throw new \RuntimeException('The secret could not be encrypted.');
        }

        return self::PREFIX . base64_encode($iv . $tag . $cipher);
    }

    /**
     * The value as it was entered: decrypted when sealed, as it is otherwise; empty when it cannot
     * be opened (the site's secret changed, say), so that nothing is sent with a wrong key.
     */
    public static function open(string $value): string
    {
        $value = trim($value);

        if (!self::isSealed($value)) {
            return $value;
        }

        $raw = base64_decode(substr($value, \strlen(self::PREFIX)), true);

        if ($raw === false || \strlen($raw) < 29) {
            return '';
        }

        $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));

        return $plain === false ? '' : $plain;
    }

    private static function key(): string
    {
        $secret = '';

        try {
            $secret = (string) Factory::getApplication()->get('secret');
        } catch (\Throwable) {
            // No application (a command line script): the configuration file itself
        }

        if ($secret === '' && class_exists('JConfig')) {
            $secret = (string) ((new \JConfig())->secret ?? '');
        }

        if ($secret === '') {
            throw new \RuntimeException('The site has no secret to encrypt with.');
        }

        return hash_hmac('sha256', 'mcpask-secret-v1', $secret, true);
    }
}
