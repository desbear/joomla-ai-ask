<?php

/**
 * @package     AI Ask for Joomla
 * @subpackage  plg_mcpask_dropfiles
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Plugin\Mcpask\Dropfiles\Extension;

use Gss\Plugin\Mcpask\Dropfiles\Source\DropfilesSource;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;

\defined('_JEXEC') or die;

/**
 * Adds the public files of Dropfiles to what AI Ask answers from: read, indexed and searched
 * together with the site's pages, and cited as files to open or download.
 *
 * @since  0.19.0
 */
final class Dropfiles extends CMSPlugin implements SubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return ['onMcpaskSources' => 'sources'];
    }

    public function sources(Event $event): void
    {
        // AI Ask's document reader is needed, and Dropfiles' tables
        if (!interface_exists('Gss\\Component\\Mcpask\\Administrator\\Ask\\Source\\FileSourceInterface') || !DropfilesSource::isAvailable($event->getArgument('db'))) {
            return;
        }

        $event->setArgument('sources', [...(array) $event->getArgument('sources', []), new DropfilesSource($event->getArgument('db'), $this->params)]);
    }
}
