<?php

/**
 * @package     AI Ask Desk for Joomla
 * @subpackage  com_askdesk
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Askdesk\Administrator\View\Files;

use Gss\Component\Askdesk\Administrator\Desk\Files;
use Gss\Component\Askdesk\Administrator\Desk\Library;
use Gss\Component\Askdesk\Administrator\Desk\Settings;
use Gss\Component\Askdesk\Administrator\Desk\Staff;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

/**
 * The file library: the files sent in chats (who sent each, in which chat, until when it is kept),
 * and the folders in Dropfiles the people answering keep files in to send again.
 *
 * @since  0.1.0
 */
class HtmlView extends BaseHtmlView
{
    /** @var object[] */
    public array $files = [];

    /** @var array<int, string>  Names of the people answering, by user ID */
    public array $names = [];

    public string $kind = '';

    public bool $library = false;

    /** @var array<int, array{id: int, title: string, files: int, user: int}> */
    public array $folders = [];

    public string $libraryError = '';

    public bool $canDelete = false;

    public function display($tpl = null): void
    {
        $app      = Factory::getApplication();
        $db       = Factory::getContainer()->get(DatabaseInterface::class);
        $settings = Settings::load();

        $this->kind      = $app->getInput()->getCmd('kind', '');
        $this->files     = (new Files($db))->listing($this->kind);
        $this->names     = (new Staff($db, $settings))->names(array_map(static fn ($file) => (int) $file->staff_id, $this->files));
        $this->canDelete = $app->getIdentity()->authorise('core.delete', 'com_askdesk');
        $this->library   = Library::available();

        if ($this->library) {
            try {
                $this->folders = (new Library($db, $settings))->folders();
            } catch (\Throwable $e) {
                $this->libraryError = $e->getMessage();
            }
        }

        ToolbarHelper::title(Text::_('COM_ASKDESK_FILES'), 'folder');
        ToolbarHelper::preferences('com_askdesk');

        parent::display($tpl);
    }
}
