<?php

/**
 * @package     AI Ask Desk for Joomla
 * @subpackage  com_askdesk
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Askdesk\Site\Controller;

use Joomla\CMS\Uri\Uri;

\defined('_JEXEC') or die;

/**
 * What the visitor's and the console's requests share: a JSON body from this site's own pages,
 * and a JSON answer.
 *
 * @since  0.1.0
 */
trait Json
{
    private const MAX_BODY_BYTES = 16384;

    /**
     * @return  array<string, mixed>  The request's body, when it is a JSON POST from this site
     */
    private function body(): array
    {
        $input = $this->app->getInput();

        if (strtoupper($input->getMethod()) !== 'POST' || !$this->sameOrigin()) {
            $this->send(['error' => 'Bad request.'], 400);
        }

        $body = json_decode((string) file_get_contents('php://input', false, null, 0, self::MAX_BODY_BYTES), true);

        return \is_array($body) ? $body : [];
    }

    /**
     * The file sent with a form (multipart) from this site's own pages, if any.
     *
     * @return  array{name: string, tmp_name: string, size: int, error: int}|null
     */
    private function uploaded(): ?array
    {
        $input  = $this->app->getInput();
        $server = $input->server;
        $origin = $server->getString('HTTP_ORIGIN', '') ?: $server->getString('HTTP_REFERER', '');
        $host   = parse_url($origin, PHP_URL_HOST);

        if (strtoupper($input->getMethod()) !== 'POST' || !\is_string($host) || strcasecmp($host, \Joomla\CMS\Uri\Uri::getInstance()->getHost()) !== 0) {
            $this->send(['error' => 'Bad request.'], 400);
        }

        $file = $_FILES['file'] ?? null;

        return \is_array($file) && \is_string($file['name'] ?? null) ? ['name' => $file['name'], 'tmp_name' => (string) $file['tmp_name'], 'size' => (int) $file['size'], 'error' => (int) $file['error']] : null;
    }

    private function sameOrigin(): bool
    {
        $server = $this->app->getInput()->server;
        $origin = $server->getString('HTTP_ORIGIN', '') ?: $server->getString('HTTP_REFERER', '');
        $host   = parse_url($origin, PHP_URL_HOST);

        return \is_string($host)
            && strcasecmp($host, Uri::getInstance()->getHost()) === 0
            && str_starts_with(strtolower($server->getString('CONTENT_TYPE', '')), 'application/json');
    }

    private function send(array $data, int $status = 200): never
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        header('X-Robots-Tag: noindex');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

        $this->app->close();
    }
}
