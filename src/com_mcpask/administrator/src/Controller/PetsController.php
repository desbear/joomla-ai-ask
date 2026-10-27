<?php

/**
 * @package     AI Ask for Joomla
 * @subpackage  com_mcpask
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Mcpask\Administrator\Controller;

use Gss\Component\Mcpask\Administrator\Access\Areas;
use Gss\Component\Mcpask\Administrator\Pet\Pets;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

/**
 * Saving a pet with the pictures uploaded for it, and deleting pets. For those who may change the
 * component's options.
 *
 * @since  0.12.0
 */
class PetsController extends BaseController
{
    public function __construct($config = [], $factory = null, $app = null, $input = null)
    {
        parent::__construct($config, $factory, $app, $input);

        // "Save" stays on the page; "Save & Close" goes back to the list
        $this->registerTask('apply', 'save');
    }

    public function cancel(): void
    {
        $this->setRedirect(Route::_('index.php?option=com_mcpask&view=pets', false));
    }

    public function save(): void
    {
        $this->guard();

        $pets  = new Pets(Factory::getContainer()->get(DatabaseInterface::class));
        $input = $this->app->getInput();
        $id    = $input->getInt('id');
        $form  = Form::getInstance('com_mcpask.pet', JPATH_ADMINISTRATOR . '/components/com_mcpask/forms/pet.xml', ['control' => 'jform']);
        $data  = $form->filter($input->post->get('jform', [], 'array'));

        if ($form->validate($data) !== true) {
            foreach ($form->getErrors() as $error) {
                $this->app->enqueueMessage($error instanceof \Throwable ? $error->getMessage() : (string) $error, 'warning');
            }

            $this->back($id);
        }

        // What it had, with this form's settings over it; what it is for stays what it was made for
        $before = $id ? $pets->get($id) : null;
        $params = array_merge($before ? $before->params->toArray() : [], (array) ($data['params'] ?? []));
        $params['kind'] = $before ? Pets::kindOf($before) : ($input->getCmd('kind') === 'cleaner' ? 'cleaner' : 'agent');
        $id     = $pets->save(['id' => $id, 'title' => $data['title'] ?? '', 'params' => $params]);

        // How long the moments that play once last
        foreach ((array) $input->post->get('seconds', [], 'array') as $name => $value) {
            if (isset(Pets::ACTIONS[$name]) && Pets::ACTIONS[$name] > 0) {
                $params[$name . '_seconds'] = round(max(0.3, min(10.0, (float) $value)), 2);
            }
        }

        // Pictures taken away, then those uploaded
        foreach ((array) $input->post->get('remove', [], 'array') as $field => $on) {
            if ($on && isset(Pets::pictures()[$field])) {
                $params[Pets::pictures()[$field]] = '';
            }
        }

        foreach ((array) $input->files->get('pictures', [], 'raw') as $field => $file) {
            if (!\is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            try {
                if (($file['error'] ?? 0) !== UPLOAD_ERR_OK) {
                    throw new \RuntimeException(Text::sprintf('COM_MCPASK_PET_ERROR_SIZE', (string) ($file['name'] ?? '')));
                }

                if (!isset(Pets::pictures()[$field])) {
                    continue;
                }

                $params[Pets::pictures()[$field]] = Pets::store($id, (string) $field, (string) $file['tmp_name'], (string) $file['name']);

                // A moment that plays once lasts as long as its new picture plays, when the picture says
                if ((Pets::ACTIONS[$field] ?? 0) > 0 && ($seconds = Pets::duration(JPATH_ROOT . '/' . $params[Pets::pictures()[$field]])) !== null) {
                    $params[$field . '_seconds'] = round(max(0.3, min(10.0, $seconds)), 2);
                    $this->app->enqueueMessage(Text::sprintf('COM_MCPASK_PET_SECONDS_READ', Text::_('COM_MCPASK_PET_ACTION_' . strtoupper((string) $field)), $params[$field . '_seconds']), 'info');
                }
            } catch (\RuntimeException $e) {
                $this->app->enqueueMessage($e->getMessage(), 'warning');
            }
        }

        // The button's pets: their library, and the moments its pictures were put in (0.22.0)
        if ($params['kind'] === 'agent') {
            $params = $this->arrange($id, $params, $before);
        }

        $pets->save(['id' => $id, 'title' => $data['title'] ?? '', 'params' => $params]);

        if ($params['kind'] === 'cleaner' ? !$pets->cleaner((string) $id) : !$pets->display((string) $id)) {
            $this->app->enqueueMessage(Text::_($params['kind'] === 'cleaner' ? 'COM_MCPASK_CLEANER_NEEDS_PICTURE' : 'COM_MCPASK_PET_NEEDS_IDLE'), 'warning');
        }

        if ($this->getTask() !== 'apply') {
            $this->setRedirect(Route::_('index.php?option=com_mcpask&view=pets' . ($params['kind'] === 'cleaner' ? '&kind=cleaner' : ''), false), Text::_('COM_MCPASK_PET_SAVED'));

            return;
        }

        $this->back($id, Text::_('COM_MCPASK_PET_SAVED'));
    }

    public function delete(): void
    {
        $this->guard();

        $pets    = new Pets(Factory::getContainer()->get(DatabaseInterface::class));
        $cleaner = false;

        foreach ((array) $this->app->getInput()->get('cid', [], 'int') as $id) {
            // Back to the tab they were on
            $pet      = $pets->get((int) $id);
            $cleaner  = $cleaner || ($pet && Pets::kindOf($pet) === 'cleaner');
            $pets->delete((int) $id);
        }

        $this->setRedirect(Route::_('index.php?option=com_mcpask&view=pets' . ($cleaner ? '&kind=cleaner' : ''), false), Text::_('COM_MCPASK_PET_DELETED'));
    }

    /**
     * Move a pet one place earlier or later (the arrows on its card).
     */
    public function move(): void
    {
        $this->guard();

        [$id, $step] = array_map('intval', explode(':', $this->app->getInput()->post->getString('move', '0:0'), 2)) + [1 => 0];
        $pets        = new Pets(Factory::getContainer()->get(DatabaseInterface::class));
        $pet         = $pets->get($id);

        try {
            $pets->move($id, $step);
        } catch (\RuntimeException $e) {
            $this->app->enqueueMessage($e->getMessage(), 'error');
        }

        $this->setRedirect(Route::_('index.php?option=com_mcpask&view=pets' . ($pet && Pets::kindOf($pet) === 'cleaner' ? '&kind=cleaner' : ''), false));
    }

    /**
     * Keep the order the pets were dragged into, as JSON.
     */
    public function reorder(): void
    {
        $this->guard();

        try {
            (new Pets(Factory::getContainer()->get(DatabaseInterface::class)))->reorder((array) $this->app->getInput()->post->get('ids', [], 'array'));
            $response = new \Joomla\CMS\Response\JsonResponse(['saved' => true]);
        } catch (\RuntimeException $e) {
            $response = new \Joomla\CMS\Response\JsonResponse($e);
        }

        header('Content-Type: application/json; charset=utf-8');
        echo $response;
        $this->app->close();
    }

    /**
     * A pet's library as the form left it: names and lengths, pictures replaced, taken away and
     * added (to the library, or straight into a moment), and the pictures of each moment. Each
     * moment's first picture is kept where pets kept it before, for what reads it there.
     */
    private function arrange(int $id, array $params, ?object $before): array
    {
        $input            = $this->app->getInput();
        [$items, $blocks] = Pets::library($before ? $before->params : new \Joomla\Registry\Registry());
        $posted           = (array) $input->post->get('items', [], 'array');
        $files            = $input->files;
        $gone             = [];

        foreach ($items as $key => &$item) {
            $form = (array) ($posted[$key] ?? []);

            if (!empty($form['remove'])) {
                $gone[] = $item;
                unset($items[$key]);

                continue;
            }

            if (\array_key_exists('name', $form)) {
                $item['name']    = mb_substr(trim((string) $form['name']), 0, 60);
                $item['seconds'] = trim((string) ($form['seconds'] ?? '')) === '' ? 0 : round(max(0.3, min(10.0, (float) $form['seconds'])), 2);
            }

            if (!empty($form['thumb_remove'])) {
                $gone[]        = ['image' => '', 'thumb' => $item['thumb']];
                $item['thumb'] = '';
            }
        }

        unset($item);

        // A file of the form: what it is, null when none was chosen
        $upload = function (array $file): ?array {
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE || ($file['name'] ?? '') === '') {
                return null;
            }

            if (($file['error'] ?? 0) !== UPLOAD_ERR_OK) {
                throw new \RuntimeException(Text::sprintf('COM_MCPASK_PET_ERROR_SIZE', (string) ($file['name'] ?? '')));
            }

            return $file;
        };

        // Pictures replaced, and thumbnails given
        foreach (['item_image' => false, 'item_thumb' => true] as $field => $thumb) {
            foreach ((array) $files->get($field, [], 'raw') as $key => $file) {
                try {
                    if (!isset($items[$key]) || !\is_array($file) || !($file = $upload($file))) {
                        continue;
                    }

                    $path   = Pets::storeItem($id, (string) $key, $thumb, (string) $file['tmp_name'], (string) $file['name']);
                    $gone[] = ['image' => '', 'thumb' => $items[$key][$thumb ? 'thumb' : 'image']];

                    $items[$key][$thumb ? 'thumb' : 'image'] = $path;

                    if (!$thumb && ($seconds = Pets::duration(JPATH_ROOT . '/' . $path)) !== null) {
                        $items[$key]['seconds'] = round(max(0.3, min(10.0, $seconds)), 2);
                    }
                } catch (\RuntimeException $e) {
                    $this->app->enqueueMessage($e->getMessage(), 'warning');
                }
            }
        }

        // The pictures of each moment, as they were dragged (unless the form did not say)
        if ($input->post->getInt('blocks_posted')) {
            $blocks = [];

            foreach ((array) $input->post->get('blocks', [], 'array') as $name => $keys) {
                if (isset(Pets::ACTIONS[$name])) {
                    $blocks[$name] = array_values(array_unique(array_filter(explode(',', (string) $keys), static fn ($key) => isset($items[$key]))));
                }
            }
        }

        // New pictures: into the library, or into the moment they were uploaded at
        $new = ['' => (array) $files->get('new', [], 'raw')];

        foreach ((array) $files->get('new_block', [], 'raw') as $name => $list) {
            if (isset(Pets::ACTIONS[$name])) {
                $new[$name] = (array) $list;
            }
        }

        foreach ($new as $name => $list) {
            foreach (isset($list['name']) ? [$list] : $list as $file) {
                try {
                    if (!\is_array($file) || !($file = $upload($file))) {
                        continue;
                    }

                    $key  = bin2hex(random_bytes(4));
                    $path = Pets::storeItem($id, $key, false, (string) $file['tmp_name'], (string) $file['name']);

                    $items[$key] = [
                        'name'    => mb_substr(pathinfo((string) $file['name'], PATHINFO_FILENAME), 0, 60),
                        'image'   => $path,
                        'thumb'   => '',
                        'seconds' => (float) (Pets::duration(JPATH_ROOT . '/' . $path) ?? 0),
                    ];

                    // The first picture of a pet is what it does when nothing happens
                    $to = $name !== '' ? $name : (empty($blocks['idle']) ? 'idle' : '');

                    if ($to !== '') {
                        $blocks[$to][] = $key;
                    }
                } catch (\RuntimeException $e) {
                    $this->app->enqueueMessage($e->getMessage(), 'warning');
                }
            }
        }

        // What nothing uses any more goes
        $used = [(string) ($params['static'] ?? ''), (string) ($params['thumb'] ?? '')];

        foreach ($items as $item) {
            array_push($used, $item['image'], $item['thumb']);
        }

        foreach ($gone as $item) {
            Pets::discard($id, (string) $item['image'], $used);
            Pets::discard($id, (string) $item['thumb'], $used);
        }

        $params['library'] = [];

        foreach ($items as $key => $item) {
            $params['library'][] = ['key' => (string) $key] + $item;
        }

        $params['blocks'] = $blocks;

        foreach (Pets::ACTIONS as $name => $length) {
            $first = $items[$blocks[$name][0] ?? ''] ?? null;

            $params[$name . '_image'] = $first['image'] ?? '';
            $params[$name . '_thumb'] = $first['thumb'] ?? '';

            if ($length > 0) {
                $params[$name . '_seconds'] = $first && $first['seconds'] > 0 ? $first['seconds'] : $length;
            }
        }

        return $params;
    }

    private function guard(): void
    {
        $user = $this->app->getIdentity();

        if (!Session::checkToken() || !Areas::allows($user, 'pets')) {
            $this->setRedirect(Route::_('index.php?option=com_mcpask&view=pets', false), Text::_('JERROR_ALERTNOAUTHOR'), 'error');
            $this->redirect();
        }
    }

    private function back(int $id, string $message = ''): never
    {
        $this->setRedirect(Route::_('index.php?option=com_mcpask&view=pet' . ($id ? '&id=' . $id : ''), false), $message !== '' ? $message : null);
        $this->redirect();
    }
}
