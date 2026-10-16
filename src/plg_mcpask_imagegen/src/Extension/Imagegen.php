<?php

/**
 * @package     AI Ask for Joomla
 * @subpackage  plg_mcpask_imagegen
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Plugin\Mcpask\Imagegen\Extension;

use Gss\Plugin\Mcpask\Imagegen\Provider\Provider;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;

\defined('_JEXEC') or die;

/**
 * Offers AI Ask an image service for the pictures of reports: OpenAI's image API, or an Open WebUI
 * server. With it enabled and set up, the back end offers pictures made by AI.
 *
 * @since  0.14.0
 */
final class Imagegen extends CMSPlugin implements SubscriberInterface
{
    protected $autoloadLanguage = true;

    public static function getSubscribedEvents(): array
    {
        return ['onMcpaskImageProviders' => 'providers'];
    }

    public function providers(Event $event): void
    {
        // AI Ask's own classes are needed; without it there is nothing to offer
        if (!interface_exists('Gss\\Component\\Mcpask\\Administrator\\Agent\\ImageProviderInterface')) {
            return;
        }

        $event->setArgument('providers', [...(array) $event->getArgument('providers', []), new Provider($this->params)]);
    }
}
