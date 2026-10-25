<?php

/**
 * @package     AI Ask Desk for Joomla
 * @subpackage  com_askdesk
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Askdesk\Administrator\View\Cases;

use Gss\Component\Askdesk\Administrator\Desk\Settings;
use Gss\Component\Askdesk\Administrator\Desk\Staff;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

/**
 * Cases left open, for housekeeping (0.6.2): messages left and chats that ended with the visitor's words
 * unanswered, not closed (結案) yet, untouched for so many days; picked and closed together.
 *
 * @since  0.6.2
 */
class HtmlView extends BaseHtmlView
{
    /** The ages that may be chosen, in days (0: all) */
    public const AGES = [7, 30, 90, 0];

    /** @var object[] */
    public array $cases = [];

    /** @var array<int, string> */
    public array $names = [];

    public int $age = 30;

    public int $total = 0;

    public function display($tpl = null): void
    {
        $app = Factory::getApplication();

        if (!$app->getIdentity()->authorise('core.manage', 'com_askdesk')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $db        = Factory::getContainer()->get(DatabaseInterface::class);
        $age       = $app->getInput()->getInt('age', 30);
        $this->age = \in_array($age, self::AGES, true) ? $age : 30;

        $query = $db->getQuery(true)
            ->from($db->quoteName('#__askdesk_rooms', 'r'))
            ->where('r.settled IS NULL')
            ->where('(r.status = ' . $db->quote('message') . ' OR r.status = ' . $db->quote('closed') . ')');

        if ($this->age > 0) {
            $query->where('r.modified < ' . $db->quote(Factory::getDate('-' . $this->age . ' days')->toSql()));
        }

        $this->total = (int) $db->setQuery((clone $query)->select('COUNT(*)'))->loadResult();
        $this->cases = $db->setQuery(
            $query->select('r.id, r.status, r.name, r.email, r.company, r.user_id, r.staff_id, r.created, r.modified, m.text AS last_text')
                ->join('LEFT', $db->quoteName('#__askdesk_messages', 'm'), 'm.id = r.last_message')
                ->order('r.modified ASC'),
            0,
            500
        )->loadObjectList();
        $this->names = (new Staff($db, Settings::load()))->names(array_map(static fn ($row) => (int) $row->staff_id, $this->cases));

        ToolbarHelper::title(Text::_('COM_ASKDESK_CASES_HOUSEKEEPING'), 'checkbox-checked');
        ToolbarHelper::preferences('com_askdesk');

        parent::display($tpl);
    }
}
