<?php

/**
 * @package     AI Ask for Joomla
 * @subpackage  com_mcpask
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Mcpask\Administrator\Agent;

use Gss\Component\Mcpask\Administrator\Ask\State;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\Event\Event;

\defined('_JEXEC') or die;

/**
 * Pictures for reports, drawn by an image service a plugin offers (ImageProviderInterface). A
 * picture is kept by its prompt, so the same prompt costs once; a day's pictures are capped.
 * Files go to images/mcpask/reports.
 *
 * @since  0.9.0
 */
final class ImageMaker
{
    public const FOLDER = 'images/mcpask/reports';

    private const WIDTH = 1200;

    /** @var ImageProviderInterface[]|null */
    private static ?array $providers = null;

    public function __construct(private DatabaseInterface $db)
    {
    }

    /**
     * The image services plugins offer that are set up.
     *
     * @return  ImageProviderInterface[]
     */
    public static function providers(): array
    {
        if (self::$providers === null) {
            self::$providers = [];
            PluginHelper::importPlugin('mcpask');

            $event = new Event('onMcpaskImageProviders', ['providers' => []]);
            Factory::getApplication()->getDispatcher()->dispatch('onMcpaskImageProviders', $event);

            foreach ((array) $event->getArgument('providers', []) as $provider) {
                try {
                    if ($provider instanceof ImageProviderInterface && preg_match('/^[a-z0-9]{2,30}$/', $provider->name()) && $provider->ready()) {
                        self::$providers[] = $provider;
                    }
                } catch (\Throwable) {
                    // A provider that cannot say whether it is ready is not used
                }
            }
        }

        return self::$providers;
    }

    /**
     * Whether pictures can be made at all: the back end offers it only then.
     */
    public static function available(): bool
    {
        return (bool) self::providers();
    }

    /**
     * A picture for a prompt: the one made before for the same prompt, or a new one while the
     * day's cap allows. Null when none can be had (no service, the cap reached, the service failed).
     *
     * @return  array{url: string, path: string}|null
     */
    public function make(string $prompt, int $perDay): ?array
    {
        $prompt   = trim(mb_substr($prompt, 0, 2000));
        $provider = self::providers()[0] ?? null;

        if ($prompt === '' || !$provider) {
            return null;
        }

        $name = sha1($prompt . '|' . $provider->name());

        foreach (['webp', 'jpg', 'png'] as $ext) {
            $path = self::FOLDER . '/' . $name . '.' . $ext;

            if (is_file(JPATH_ROOT . '/' . $path)) {
                return ['url' => '/' . $path, 'path' => $path];
            }
        }

        if (!$this->count($perDay)) {
            return null;
        }

        try {
            $image = $this->encode($provider->draw($prompt));
        } catch (\Throwable) {
            return null;
        }

        if (!is_dir(JPATH_ROOT . '/' . self::FOLDER)) {
            mkdir(JPATH_ROOT . '/' . self::FOLDER, 0755, true);
            file_put_contents(JPATH_ROOT . '/' . self::FOLDER . '/index.html', '<!DOCTYPE html><title></title>');
        }

        $path = self::FOLDER . '/' . $name . '.' . $image['ext'];
        file_put_contents(JPATH_ROOT . '/' . $path, $image['data']);

        return ['url' => '/' . $path, 'path' => $path];
    }

    /**
     * As WebP (or JPEG where WebP cannot be written), no wider than WIDTH.
     *
     * @return  array{data: string, ext: string}
     */
    private function encode(string $binary): array
    {
        $source = @imagecreatefromstring($binary);

        if ($source === false) {
            throw new \RuntimeException('Not an image.');
        }

        $width  = imagesx($source);
        $height = imagesy($source);
        $out    = min($width, self::WIDTH);
        $target = imagecreatetruecolor($out, (int) round($height * $out / $width));

        imagecopyresampled($target, $source, 0, 0, 0, 0, imagesx($target), imagesy($target), $width, $height);
        ob_start();

        $webp = \function_exists('imagewebp') && imagewebp($target, null, 82);

        if (!$webp) {
            imagejpeg($target, null, 85);
        }

        return ['data' => (string) ob_get_clean(), 'ext' => $webp ? 'webp' : 'jpg'];
    }

    /**
     * One more picture today, when the cap allows it.
     */
    private function count(int $perDay): bool
    {
        if ($perDay < 1) {
            return false;
        }

        $state = new State($this->db);
        $day   = Factory::getDate()->format('Y-m-d');
        $made  = $state->get('report:images');
        $made  = ($made['day'] ?? '') === $day ? (int) ($made['count'] ?? 0) : 0;

        if ($made >= $perDay) {
            return false;
        }

        $state->set('report:images', ['day' => $day, 'count' => $made + 1]);

        return true;
    }
}
