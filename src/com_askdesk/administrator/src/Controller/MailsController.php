<?php

/**
 * @package     AI Ask Desk for Joomla
 * @subpackage  com_askdesk
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Askdesk\Administrator\Controller;

use Gss\Component\Askdesk\Administrator\Desk\Mails;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;

\defined('_JEXEC') or die;

/**
 * The e-mails the desk sends, as designed (0.6.3): saved, switched on or off, given back to the desk's own words,
 * tried (sent to oneself, with words made up), and the pictures they show put on the site.
 *
 * @since  0.6.3
 */
class MailsController extends BaseController
{
    /** Where the pictures of the e-mails are kept, under the site's images */
    public const FOLDER = 'images/askdesk-mail';

    private const PICTURES = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'];

    private const PICTURE_MAX = 5 * 1024 * 1024;

    public function apply(): void
    {
        $this->store(false);
    }

    public function save(): void
    {
        $this->store(true);
    }

    public function cancel(): void
    {
        $this->back();
    }

    public function publish(): void
    {
        [$kind, $language] = $this->which();

        if (!$this->allowed() || $kind === '') {
            return;
        }

        Mails::make()->publish($kind, $language, $this->input->getBool('on'));
        $this->back();
    }

    public function delete(): void
    {
        [$kind, $language] = $this->which();

        if (!$this->allowed() || $kind === '') {
            return;
        }

        Mails::make()->delete($kind, $language);
        $this->back(Text::_('COM_ASKDESK_MAILS_DELETED'));
    }

    /**
     * Sent to oneself as it is in the editor, with words made up for its placeholders.
     */
    public function test(): void
    {
        [$kind, $language] = $this->which();
        $user              = $this->app->getIdentity();

        if (!$this->allowed(true) || $kind === '') {
            $this->json(['ok' => false, 'message' => Text::_('JERROR_ALERTNOAUTHOR')]);
        }

        $html    = Mails::body((string) $this->input->post->get('html', '', 'raw'));
        $subject = trim((string) $this->input->post->getString('subject', ''));

        if (!filter_var($user->email, FILTER_VALIDATE_EMAIL) || $subject === '' || trim(strip_tags($html, '<img>')) === '' || \strlen($html) > Mails::MAX) {
            $this->json(['ok' => false, 'message' => Text::_('COM_ASKDESK_MAILS_TEST_FAILED')]);
        }

        $mails = Mails::make();
        $vars  = Mails::sample($kind, Mails::words($language));
        $body  = $mails->fill($html, $vars);
        $title = trim(strtr($subject, array_combine(array_map(static fn ($key) => '{' . $key . '}', array_keys($vars)), array_map(static fn ($v) => str_contains($v, '<') ? '' : $v, $vars))));
        $sent  = false;

        try {
            $mailer = Factory::getMailer();
            $mailer->addRecipient($user->email);
            Mails::apply($mailer, ['subject' => '[' . Text::_('COM_ASKDESK_MAILS_TEST_MARK') . '] ' . $title, 'html' => Mails::document($body, $title, $language), 'text' => Mails::text($body)]);
            $sent = (bool) $mailer->Send();
        } catch (\Throwable) {
            $sent = false;
        }

        $this->json($sent
            ? ['ok' => true, 'message' => Text::sprintf('COM_ASKDESK_MAILS_TEST_SENT', $user->email)]
            : ['ok' => false, 'message' => Text::_('COM_ASKDESK_MAILS_TEST_FAILED')]);
    }

    /**
     * Pictures for the e-mails, put among the site's images (only pictures, as they are: a picture is checked to
     * be one), answered as the editor wants: where each now is.
     */
    public function upload(): void
    {
        if (!$this->allowed(true)) {
            $this->json(['data' => [], 'message' => Text::_('JERROR_ALERTNOAUTHOR')], 403);
        }

        $files = $this->input->files->get('files', [], 'raw');
        $files = isset($files['name']) ? [$files] : (array) $files;
        $dir   = JPATH_ROOT . '/' . self::FOLDER;
        $done  = [];

        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            $this->json(['data' => [], 'message' => Text::_('COM_ASKDESK_MAILS_UPLOAD_FAILED')], 500);
        }

        foreach ($files as $file) {
            $name = (string) ($file['name'] ?? '');
            $tmp  = (string) ($file['tmp_name'] ?? '');
            $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $info = $tmp !== '' && is_uploaded_file($tmp) ? @getimagesize($tmp) : false;

            if ((int) ($file['error'] ?? 1) !== UPLOAD_ERR_OK || !isset(self::PICTURES[$ext]) || (int) ($file['size'] ?? 0) > self::PICTURE_MAX
                || !$info || ($info['mime'] ?? '') !== self::PICTURES[$ext]) {
                continue;
            }

            $base = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower(pathinfo($name, PATHINFO_FILENAME))), '-') ?: 'image';
            $file = mb_substr($base, 0, 40) . '-' . bin2hex(random_bytes(4)) . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);

            if (move_uploaded_file($tmp, $dir . '/' . $file)) {
                @chmod($dir . '/' . $file, 0644);
                $done[] = ['src' => self::url($file), 'type' => 'image', 'width' => (int) $info[0], 'height' => (int) $info[1]];
            }
        }

        $this->json($done ? ['data' => $done] : ['data' => [], 'message' => Text::_('COM_ASKDESK_MAILS_UPLOAD_FAILED')], $done ? 200 : 400);
    }

    /**
     * The pictures already put there, newest first.
     *
     * @return  array<int, array{src: string, type: string}>
     */
    public static function pictures(): array
    {
        $dir   = JPATH_ROOT . '/' . self::FOLDER;
        $found = [];

        foreach (is_dir($dir) ? (glob($dir . '/*.{jpg,png,gif,webp}', GLOB_BRACE) ?: []) : [] as $path) {
            $found[basename($path)] = (int) filemtime($path);
        }

        arsort($found);

        return array_map(static fn ($file) => ['src' => self::url((string) $file), 'type' => 'image'], array_keys(\array_slice($found, 0, 200, true)));
    }

    /** As a mail program reaches it: from anywhere, whole */
    private static function url(string $file): string
    {
        return rtrim(Uri::root(), '/') . '/' . self::FOLDER . '/' . rawurlencode($file);
    }

    private function store(bool $close): void
    {
        [$kind, $language] = $this->which();

        if (!$this->allowed() || $kind === '') {
            return;
        }

        $edit = 'index.php?option=com_askdesk&view=mail&kind=' . $kind . '&language=' . rawurlencode($language);

        try {
            Mails::make()->save(
                $kind,
                $language,
                $this->input->post->getString('subject', ''),
                (string) $this->input->post->get('html', '', 'raw'),
                (string) $this->input->post->get('design', '', 'raw'),
                $this->input->post->getBool('published'),
                (int) $this->app->getIdentity()->id
            );
        } catch (\RuntimeException) {
            $this->setRedirect(Route::_($edit, false), Text::_('COM_ASKDESK_MAILS_EMPTY'), 'warning');

            return;
        }

        if ($close) {
            $this->back(Text::_('COM_ASKDESK_MAILS_SAVED'));
        } else {
            $this->setRedirect(Route::_($edit, false), Text::_('COM_ASKDESK_MAILS_SAVED'));
        }
    }

    /**
     * @return  array{0: string, 1: string}  The kind and language asked for, or empty when they are not one
     */
    private function which(): array
    {
        $kind     = $this->input->getCmd('kind', '');
        $language = $this->input->getString('language', '');

        return Mails::valid($kind, $language) ? [$kind, $language] : ['', ''];
    }

    private function allowed(bool $quiet = false): bool
    {
        $user = $this->app->getIdentity();

        if ((Session::checkToken() || Session::checkToken('get')) && ($user->authorise('core.admin', 'com_askdesk') || $user->authorise('core.options', 'com_askdesk'))) {
            return true;
        }

        if (!$quiet) {
            $this->setRedirect(Route::_('index.php?option=com_askdesk&view=mails', false), Text::_('JERROR_ALERTNOAUTHOR'), 'error');
        }

        return false;
    }

    private function back(string $message = '', string $type = 'message'): void
    {
        $this->setRedirect(Route::_('index.php?option=com_askdesk&view=mails', false), $message ?: null, $type);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function json(array $data, int $status = 200): never
    {
        $this->app->setHeader('status', (string) $status, true);
        $this->app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
        $this->app->setHeader('Cache-Control', 'no-store', true);
        $this->app->sendHeaders();
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->app->close();
    }
}
