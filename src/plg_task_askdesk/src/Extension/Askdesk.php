<?php

/**
 * @package     AI Ask Desk for Joomla
 * @subpackage  plg_task_askdesk
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Plugin\Task\Askdesk\Extension;

use Gss\Component\Askdesk\Administrator\Desk\Archive;
use Gss\Component\Askdesk\Administrator\Desk\Settings;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Database\DatabaseInterface;
use Joomla\Event\SubscriberInterface;

\defined('_JEXEC') or die;

/**
 * The desk's daily task (0.6.2): closed chats past their days written out of the site whole, marked, and only
 * then deleted (Archive). The desk does the same from its own requests once a day when this has not run.
 *
 * @since  0.6.2
 */
final class Askdesk extends CMSPlugin implements SubscriberInterface
{
    use TaskPluginTrait;

    private const TASKS_MAP = [
        'askdesk.archive' => [
            'langConstPrefix' => 'PLG_TASK_ASKDESK_ARCHIVE',
            'method'          => 'archive',
        ],
    ];

    protected $autoloadLanguage = true;

    public static function getSubscribedEvents(): array
    {
        return [
            'onTaskOptionsList' => 'advertiseRoutines',
            'onExecuteTask'     => 'standardRoutineHandler',
        ];
    }

    private function archive(ExecuteTaskEvent $event): int
    {
        if (!ComponentHelper::isEnabled('com_askdesk')) {
            return Status::KNOCKOUT;
        }

        $result = (new Archive(Factory::getContainer()->get(DatabaseInterface::class), Settings::load()))->run(50);

        $this->logTask(\sprintf('Chats archived %d, deleted %d.%s', $result['exported'], $result['deleted'], $result['error'] !== '' ? ' ' . $result['error'] : ''), $result['error'] !== '' ? 'warning' : 'info');

        return $result['error'] !== '' ? Status::KNOCKOUT : Status::OK;
    }
}
