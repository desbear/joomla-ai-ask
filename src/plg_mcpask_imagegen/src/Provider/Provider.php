<?php

/**
 * @package     AI Ask for Joomla
 * @subpackage  plg_mcpask_imagegen
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Plugin\Mcpask\Imagegen\Provider;

use Gss\Component\Mcpask\Administrator\Agent\ImageProviderInterface;
use Joomla\CMS\Http\HttpFactory;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/**
 * Pictures from OpenAI's image API (gpt-image-1 by default) or from an Open WebUI server.
 *
 * @since  0.14.0
 */
final class Provider implements ImageProviderInterface
{
    public function __construct(private Registry $params)
    {
    }

    public function name(): string
    {
        return $this->provider();
    }

    public function ready(): bool
    {
        return $this->key() !== '' || ($this->provider() === 'openwebui' && $this->base() !== '');
    }

    public function draw(string $prompt): string
    {
        $http    = HttpFactory::getHttp();
        $headers = ['Content-Type' => 'application/json'] + ($this->key() !== '' ? ['Authorization' => 'Bearer ' . $this->key()] : []);
        $model   = trim((string) $this->params->get('model', ''));

        if ($this->provider() === 'openwebui') {
            $payload  = ['prompt' => $prompt, 'size' => '1536x1024', 'n' => 1] + ($model !== '' ? ['model' => $model] : []);
            $response = $http->post($this->base() . '/api/v1/images/generations', json_encode($payload), $headers, 180);
            $data     = json_decode((string) $response->getBody(), true);
            $url      = (string) ($data[0]['url'] ?? $data['data'][0]['url'] ?? '');

            if ($response->getStatusCode() !== 200 || $url === '') {
                throw new \RuntimeException('Open WebUI made no image.');
            }

            // Only from the server asked: a picture's address on another host is not fetched
            $url = str_starts_with($url, '/') ? $this->base() . $url : $url;

            if (parse_url($url, PHP_URL_HOST) !== parse_url($this->base(), PHP_URL_HOST)) {
                throw new \RuntimeException('The image is on another server.');
            }

            return (string) $http->get($url, $headers, 120)->getBody();
        }

        $quality  = \in_array($this->params->get('quality'), ['low', 'medium', 'high'], true) ? $this->params->get('quality') : 'medium';
        $payload  = ['model' => $model ?: 'gpt-image-1', 'prompt' => $prompt, 'size' => '1536x1024', 'quality' => $quality, 'n' => 1];
        $response = $http->post($this->base() . '/images/generations', json_encode($payload), $headers, 180);
        $data     = json_decode((string) $response->getBody(), true);
        $binary   = base64_decode((string) ($data['data'][0]['b64_json'] ?? ''), true);

        if ($response->getStatusCode() !== 200 || !$binary) {
            throw new \RuntimeException('OpenAI made no image.');
        }

        return $binary;
    }

    private function provider(): string
    {
        return $this->params->get('provider', 'openai') === 'openwebui' ? 'openwebui' : 'openai';
    }

    private function base(): string
    {
        return rtrim(trim((string) $this->params->get('base', '')) ?: ($this->provider() === 'openai' ? 'https://api.openai.com/v1' : ''), '/');
    }

    private function key(): string
    {
        return trim((string) $this->params->get('key', ''));
    }
}
