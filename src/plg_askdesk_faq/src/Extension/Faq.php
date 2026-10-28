<?php

/**
 * @package     AI Ask Desk for Joomla
 * @subpackage  plg_askdesk_faq
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Plugin\Askdesk\Faq\Extension;

use Gss\Plugin\Askdesk\Faq\Faq\FaqPanel;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;

\defined('_JEXEC') or die;

/**
 * The knowledge base for AI Ask Desk's staff (0.6.4): a chat turned into a question and its answer, drafted
 * by AI in a card only they see, kept as an article of the site once they confirm it. Switched off, nothing
 * of it shows in the console.
 *
 * @since  0.6.4
 */
final class Faq extends CMSPlugin implements SubscriberInterface
{
    protected $autoloadLanguage = true;

    public static function getSubscribedEvents(): array
    {
        return ['onAskdeskPanels' => 'panels'];
    }

    public function panels(Event $event): void
    {
        if (!interface_exists('Gss\\Component\\Askdesk\\Administrator\\Desk\\WorkInterface') || !method_exists('Gss\\Component\\Askdesk\\Administrator\\Desk\\Summary', 'fill')) {
            return;
        }

        $this->loadLanguage();

        $event->setArgument('panels', [...(array) $event->getArgument('panels', []), new FaqPanel($this->params)]);
    }
}
