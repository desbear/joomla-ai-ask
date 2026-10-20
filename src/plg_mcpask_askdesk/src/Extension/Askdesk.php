<?php

/**
 * @package     AI Ask Desk for Joomla
 * @subpackage  plg_mcpask_askdesk
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Plugin\Mcpask\Askdesk\Extension;

use Gss\Plugin\Mcpask\Askdesk\Agent\AskdeskAgent;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;

\defined('_JEXEC') or die;

/**
 * Offers AI Ask an agent that passes visitors on to people of AI Ask Desk, in its own window.
 *
 * @since  0.1.0
 */
final class Askdesk extends CMSPlugin implements SubscriberInterface
{
    protected $autoloadLanguage = true;

    public static function getSubscribedEvents(): array
    {
        return ['onMcpaskAgentTypes' => 'agentTypes'];
    }

    public function agentTypes(Event $event): void
    {
        // AI Ask's own classes are needed; without it there is nothing to offer
        if (!interface_exists('Gss\\Component\\Mcpask\\Administrator\\Agent\\AgentInterface')) {
            return;
        }

        $this->loadLanguage();
        $event->setArgument('types', [...(array) $event->getArgument('types', []), new AskdeskAgent()]);
    }
}
