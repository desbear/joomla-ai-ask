<?php

/**
 * @package     AI Ask Desk for Joomla
 * @subpackage  com_askdesk
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Askdesk\Administrator\View\Person;

use Gss\Component\Askdesk\Administrator\Desk\Settings;
use Gss\Component\Askdesk\Administrator\Desk\Staff;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\User\User;
use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

/**
 * How one person answering shows themselves to visitors: a name and a face.
 *
 * @since  0.1.0
 */
class HtmlView extends BaseHtmlView
{
    public ?Form $form = null;

    public ?User $person = null;

    /** @var array{name: string, face: array{src: string, still: string}|null} */
    public array $profile = ['name' => '', 'face' => null];

    public function display($tpl = null): void
    {
        $app  = Factory::getApplication();
        $user = $app->getIdentity();

        if (!$user->authorise('core.admin', 'com_askdesk') && !$user->authorise('core.options', 'com_askdesk')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $id           = $app->getInput()->getInt('id');
        $this->person = $id ? User::getInstance($id) : null;

        if (!$this->person || !$this->person->id) {
            throw new \RuntimeException(Text::_('JERROR_PAGE_NOT_FOUND'), 404);
        }

        $staff         = new Staff(Factory::getContainer()->get(DatabaseInterface::class), Settings::load());
        $db            = Factory::getContainer()->get(DatabaseInterface::class);
        $saved         = $db->setQuery($db->getQuery(true)->select('display_name, avatar, avatar_image, thanks_image')->from($db->quoteName('#__askdesk_staff'))->where('user_id = ' . (int) $id))->loadAssoc() ?: [];
        $this->profile = $staff->profiles([$id])[$id] ?? $this->profile;

        // The pet list is AI Ask's
        $app->getLanguage()->load('com_mcpask', JPATH_ADMINISTRATOR);

        $this->form = Form::getInstance('com_askdesk.person', JPATH_ADMINISTRATOR . '/components/com_askdesk/forms/person.xml', ['control' => 'jform']);
        $this->form->bind($saved);
        $this->form->setFieldAttribute('display_name', 'hint', $this->person->name);

        ToolbarHelper::title(Text::sprintf('COM_ASKDESK_PERSON_EDIT', $this->person->name), 'user');
        $app->getInput()->set('hidemainmenu', true);
        ToolbarHelper::apply('person.apply');
        ToolbarHelper::save('person.save');
        ToolbarHelper::cancel('person.cancel', 'JTOOLBAR_CLOSE');

        parent::display($tpl);
    }
}
