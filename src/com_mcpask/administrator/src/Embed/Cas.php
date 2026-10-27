<?php

/**
 * @package     AI Ask for Joomla
 * @subpackage  com_mcpask
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Mcpask\Administrator\Embed;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/**
 * Signing in at an account centre over CAS without an account here (0.22.1): the visitor is sent to the
 * account centre's login, comes back with a ticket, and the ticket is checked with the account centre by
 * this server (never trusted from the browser). What it says of them (an address, a name) is kept in their
 * session only (Visitor).
 *
 * @since  0.22.1
 */
final class Cas
{
    private const TIMEOUT = 10;

    private Registry $params;

    public function __construct(?Registry $params = null)
    {
        $this->params = $params ?? ComponentHelper::getParams('com_mcpask');
    }

    public function enabled(): bool
    {
        return (bool) $this->params->get('cas', 0) && $this->base() !== '';
    }

    /** The account centre's CAS address, as https://example.com/cas */
    public function base(): string
    {
        $base = rtrim(trim((string) $this->params->get('cas_url', '')), '/');

        return preg_match('~^https://[^\s/?#]+(/[^\s?#]*)?$~i', $base) ? $base : '';
    }

    /** What the sign-in button says, else the usual "Sign in" */
    public function label(): string
    {
        return trim((string) $this->params->get('cas_label', ''));
    }

    /**
     * Where a visitor starts signing in, to come back to a page of this site.
     */
    public function start(string $return): string
    {
        return rtrim(Uri::root(), '/') . '/index.php?option=com_mcpask&task=cas.login&format=raw&return=' . rawurlencode(base64_encode($return));
    }

    /**
     * Where a visitor signs out of it, back to a page of this site.
     */
    public function end(string $return, string $token): string
    {
        return rtrim(Uri::root(), '/') . '/index.php?option=com_mcpask&task=cas.logout&format=raw&' . $token . '=1&return=' . rawurlencode(base64_encode($return));
    }

    /**
     * The address the account centre sends the visitor back to: the same each time for a page, as the
     * account centre checks the ticket against it.
     */
    public function service(string $return): string
    {
        return rtrim(Uri::root(), '/') . '/index.php?option=com_mcpask&task=cas.back&format=raw&return=' . rawurlencode(base64_encode($return));
    }

    public function loginUrl(string $service): string
    {
        return $this->base() . '/login?service=' . rawurlencode($service);
    }

    /**
     * Who a ticket stands for, asked of the account centre (CAS 3, else 2): an address (an attribute, or the
     * user name when that is one) and a name. Null when the ticket is no good or no address comes with it.
     *
     * @return  array{user: string, email: string, name: string}|null
     */
    public function validate(string $ticket, string $service): ?array
    {
        if (!preg_match('/^[A-Za-z0-9._-]{4,256}$/', $ticket)) {
            return null;
        }

        $path = $this->params->get('cas_version', '3') === '2' ? '/serviceValidate' : '/p3/serviceValidate';
        $url  = $this->base() . $path . '?service=' . rawurlencode($service) . '&ticket=' . rawurlencode($ticket);

        try {
            $response = HttpFactory::getHttp()->get($url, ['User-Agent' => 'AI Ask for Joomla (CAS)'], self::TIMEOUT);
        } catch (\Throwable) {
            return null;
        }

        return (int) $response->code === 200 ? $this->read((string) $response->body) : null;
    }

    /**
     * @return  array{user: string, email: string, name: string}|null
     */
    public function read(string $xml): ?array
    {
        $previous = libxml_use_internal_errors(true);
        $doc      = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        libxml_use_internal_errors($previous);

        // (Not "!$doc": an element whose children are all in a namespace counts as empty)
        if ($doc === false) {
            return null;
        }

        $doc->registerXPathNamespace('cas', 'http://www.yale.edu/tp/cas');
        $user = trim((string) ($doc->xpath('//cas:authenticationSuccess/cas:user')[0] ?? ''));

        if ($user === '') {
            return null;
        }

        $attributes = [];

        foreach ($doc->xpath('//cas:authenticationSuccess/cas:attributes/*') ?: [] as $node) {
            $attributes[strtolower($node->getName())] ??= trim((string) $node);
        }

        $pick = function (string $setting, string $default) use ($attributes): string {
            foreach (array_filter(array_map('trim', explode(',', strtolower((string) $this->params->get($setting, $default))))) as $name) {
                if (($attributes[$name] ?? '') !== '') {
                    return $attributes[$name];
                }
            }

            return '';
        };

        $email = $pick('cas_email', 'mail,email');
        $email = filter_var($email, FILTER_VALIDATE_EMAIL) ?: (filter_var($user, FILTER_VALIDATE_EMAIL) ?: '');

        return $email === '' ? null : ['user' => mb_substr($user, 0, 255), 'email' => $email, 'name' => $pick('cas_name', 'name,displayname,cn')];
    }
}
