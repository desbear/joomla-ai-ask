<?php

/**
 * @package     AI Ask Desk for Joomla
 * @subpackage  com_askdesk
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Askdesk\Administrator\View\Mail;

use Gss\Component\Askdesk\Administrator\Controller\MailsController;
use Gss\Component\Askdesk\Administrator\Desk\Mails;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Uri\Uri;

\defined('_JEXEC') or die;

/**
 * One e-mail of the desk designed, in one language (0.6.3): dragged together in the editor (GrapesJS, its
 * newsletter blocks; all of it from this site), its placeholders put in from the list beside it.
 *
 * @since  0.6.3
 */
class HtmlView extends BaseHtmlView
{
    public string $kind = '';

    public string $language = '';

    public string $subject = '';

    public bool $published = true;

    /** @var array<string, mixed> */
    public array $data = [];

    public function display($tpl = null): void
    {
        $app  = Factory::getApplication();
        $user = $app->getIdentity();

        if (!$user->authorise('core.admin', 'com_askdesk') && !$user->authorise('core.options', 'com_askdesk')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $this->kind     = $app->getInput()->getCmd('kind', '');
        $this->language = $app->getInput()->getString('language', '');

        if (!Mails::valid($this->kind, $this->language)) {
            throw new \RuntimeException(Text::_('JERROR_PAGE_NOT_FOUND'), 404);
        }

        $row             = Mails::make()->get($this->kind, $this->language);
        $starter         = Mails::starter($this->kind, Mails::words($this->language));
        $this->subject   = $row ? (string) $row->subject : $starter['subject'];
        $this->published = $row ? (bool) $row->published : true;
        $design          = $row ? json_decode((string) $row->design, true) : null;
        $media           = Uri::root() . 'media/com_askdesk/';
        $token           = Session::getFormToken();
        $task            = static fn (string $name) => 'index.php?option=com_askdesk&task=mails.' . $name . '&format=json&kind=' . rawurlencode(Factory::getApplication()->getInput()->getCmd('kind')) . '&language=' . rawurlencode(Factory::getApplication()->getInput()->getString('language'));
        $vars            = [];

        foreach (Mails::KINDS[$this->kind][1] as $var) {
            $vars[] = ['token' => '{' . $var . '}', 'label' => Text::_('COM_ASKDESK_MAILS_VAR_' . strtoupper($var))];
        }

        $this->data = [
            'design'      => \is_array($design) ? $design : null,
            'html'        => $row ? (string) $row->html : '',
            'starter'     => $starter,
            'vars'        => $vars,
            'token'       => $token,
            'locale'      => strtolower($app->getLanguage()->getTag()),
            'urls'        => ['test' => $task('test'), 'upload' => $task('upload'), 'icons' => $media . 'vendor/grapesjs/font-awesome.min.css'],
            'placeholder' => $media . 'images/mail-placeholder.png',
            'pictures'    => MailsController::pictures(),
            'strings'     => $this->strings(),
        ];

        $assets = $this->getDocument()->getWebAssetManager();
        $dir    = JPATH_ROOT . '/media/com_askdesk/';
        $stamp  = static fn (string $file) => is_file($dir . $file) ? (string) filemtime($dir . $file) : '';
        $vendor = Uri::root(true) . '/media/com_askdesk/vendor/grapesjs/';

        $assets->registerAndUseStyle('com_askdesk.grapesjs', $vendor . 'grapes.min.css', ['version' => '0.23.6']);
        $assets->registerAndUseStyle('com_askdesk.mail-editor', 'com_askdesk/mail-editor.css', ['version' => $stamp('css/mail-editor.css')], [], ['com_askdesk.grapesjs']);
        $assets->registerAndUseScript('com_askdesk.grapesjs', $vendor . 'grapes.min.js', ['version' => '0.23.6'], ['defer' => true]);
        $assets->registerAndUseScript('com_askdesk.grapesjs-newsletter', $vendor . 'grapesjs-preset-newsletter.min.js', ['version' => '1.0.2'], ['defer' => true], ['com_askdesk.grapesjs']);
        $assets->registerAndUseScript('com_askdesk.mail-editor', 'com_askdesk/mail-editor.js', ['version' => $stamp('js/mail-editor.js')], ['defer' => true], ['com_askdesk.grapesjs-newsletter', 'core']);

        $app->getInput()->set('hidemainmenu', true);
        ToolbarHelper::title(Text::sprintf('COM_ASKDESK_MAIL_TITLE', Text::_('COM_ASKDESK_MAILS_KIND_' . strtoupper($this->kind)), Mails::languages()[$this->language]), 'envelope');
        ToolbarHelper::apply('mails.apply');
        ToolbarHelper::save('mails.save');
        ToolbarHelper::cancel('mails.cancel', 'JTOOLBAR_CLOSE');

        parent::display($tpl);
    }

    /** @return array<string, string> */
    private function strings(): array
    {
        $strings = [];

        foreach ([
            'testSent' => 'COM_ASKDESK_MAILS_TEST_SENDING', 'testFailed' => 'COM_ASKDESK_MAILS_TEST_FAILED', 'restoreConfirm' => 'COM_ASKDESK_MAILS_RESTORE_CONFIRM',
            'varHint' => 'COM_ASKDESK_MAILS_VAR_COPIED', 'empty' => 'COM_ASKDESK_MAILS_EMPTY', 'leave' => 'COM_ASKDESK_MAILS_LEAVE',
            'blockSect100' => 'COM_ASKDESK_MAILS_BLOCK_SECT100', 'blockSect50' => 'COM_ASKDESK_MAILS_BLOCK_SECT50', 'blockSect30' => 'COM_ASKDESK_MAILS_BLOCK_SECT30',
            'blockSect37' => 'COM_ASKDESK_MAILS_BLOCK_SECT37', 'blockButton' => 'COM_ASKDESK_MAILS_BLOCK_BUTTON', 'blockDivider' => 'COM_ASKDESK_MAILS_BLOCK_DIVIDER',
            'blockText' => 'COM_ASKDESK_MAILS_BLOCK_TEXT', 'blockTextSect' => 'COM_ASKDESK_MAILS_BLOCK_TEXT_SECT', 'blockImage' => 'COM_ASKDESK_MAILS_BLOCK_IMAGE',
            'blockQuote' => 'COM_ASKDESK_MAILS_BLOCK_QUOTE', 'blockLink' => 'COM_ASKDESK_MAILS_BLOCK_LINK', 'blockLinkBlock' => 'COM_ASKDESK_MAILS_BLOCK_LINK_BLOCK',
            'blockGridItems' => 'COM_ASKDESK_MAILS_BLOCK_GRID_ITEMS', 'blockListItems' => 'COM_ASKDESK_MAILS_BLOCK_LIST_ITEMS',
            'importTitle' => 'COM_ASKDESK_MAILS_IMPORT_TITLE', 'importButton' => 'COM_ASKDESK_MAILS_IMPORT_BUTTON', 'exportTitle' => 'COM_ASKDESK_MAILS_EXPORT_TITLE',
            'clearConfirm' => 'COM_ASKDESK_MAILS_CLEAR_CONFIRM',
        ] as $key => $string) {
            $strings[$key] = Text::_($string);
        }

        return $strings;
    }
}
