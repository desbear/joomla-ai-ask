<?php

/**
 * @package     AI Ask for Joomla
 * @subpackage  com_mcpask
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Mcpask\Administrator\Embed;

\defined('_JEXEC') or die;

/**
 * The token a website signs for its visitor, for another site's AI Ask (Identity takes it there):
 * a JWT, HS256, with the embed key's secret: aud = the public key, email, name, an hour at most.
 *
 * @since  0.20.4
 */
final class ProductToken
{
    public const SECONDS = 3600;

    public static function sign(string $key, string $secret, string $email, string $name): string
    {
        $now  = time();
        $part = static fn (array $data) => rtrim(strtr(base64_encode((string) json_encode($data, JSON_UNESCAPED_UNICODE)), '+/', '-_'), '=');
        $head = $part(['alg' => 'HS256', 'typ' => 'JWT']);
        $body = $part(['aud' => $key, 'email' => $email, 'name' => $name, 'iat' => $now, 'exp' => $now + self::SECONDS]);

        return $head . '.' . $body . '.' . rtrim(strtr(base64_encode(hash_hmac('sha256', $head . '.' . $body, $secret, true)), '+/', '-_'), '=');
    }
}
