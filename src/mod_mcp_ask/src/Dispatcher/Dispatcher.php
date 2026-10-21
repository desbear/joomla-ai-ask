<?php

/**
 * @package     AI Ask for Joomla
 * @subpackage  mod_mcp_ask
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Module\McpAsk\Site\Dispatcher;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/**
 * Search box that turns into a conversation with the site's content.
 */
class Dispatcher extends AbstractModuleDispatcher
{
    /** @var boolean  The page-change script only has to be added once per page */
    private static bool $revealAdded = false;

    private static bool $themeAdded = false;

    /**
     * Without the answer service there is nothing to ask, so nothing is shown (demo mode aside).
     */
    public function dispatch()
    {
        $params = new Registry($this->module->params);

        // Another site's AI Ask: this site's answer service is not needed, only that site's address and key
        if ($params->get('mode') === 'remote') {
            if ($this->remote($params) !== null) {
                parent::dispatch();
            }

            return;
        }

        if ($params->get('demo', 0) || $this->serviceReady()) {
            parent::dispatch();
        }
    }

    /**
     * Another site's AI Ask, embedded (0.20.2): the address of its embed script, the key, the
     * page's language, and for a signed-in visitor a token signed with the key's secret (HS256,
     * valid an hour) that says who they are. Null when the settings are not complete.
     *
     * @return  array{src: string, logout: string, key: string, lang: string, user: string, login: string, why: string, size: int, title: string}|null
     */
    private function remote(Registry $params): ?array
    {
        $site = rtrim(trim((string) $params->get('remote_site', '')), '/');
        $key  = trim((string) $params->get('remote_key', ''));
        $host = strtolower((string) parse_url($site, PHP_URL_HOST));

        // https only, but for addresses of this computer while trying it out
        $local = \in_array($host, ['localhost', '127.0.0.1', '[::1]'], true) || str_ends_with($host, '.test') || str_ends_with($host, '.localhost');

        if (!preg_match('#^https://#i', $site) && !($local && preg_match('#^http://#i', $site)) || $host === '' || !preg_match('/^pk_[A-Za-z0-9_-]{6,}$/', $key)) {
            return null;
        }

        $user   = $this->getApplication()->getIdentity();
        $secret = trim((string) $params->get('remote_secret', ''));
        $token  = '';
        // Why a signed-in visitor is not passed on, for whoever reads the page's source
        $why    = '';

        if ($user && !$user->guest) {
            $why = $secret === '' ? 'no secret is saved in the module' : (filter_var((string) $user->email, FILTER_VALIDATE_EMAIL) ? '' : 'the account has no valid e-mail address');
        }

        if ($user && !$user->guest && $why === '') {
            $now   = time();
            $part  = static fn (array $data) => rtrim(strtr(base64_encode((string) json_encode($data, JSON_UNESCAPED_UNICODE)), '+/', '-_'), '=');
            $head  = $part(['alg' => 'HS256', 'typ' => 'JWT']);
            $body  = $part(['aud' => $key, 'email' => (string) $user->email, 'name' => (string) $user->name, 'iat' => $now, 'exp' => $now + 3600]);
            $token = $head . '.' . $body . '.' . rtrim(strtr(base64_encode(hash_hmac('sha256', $head . '.' . $body, $secret, true)), '+/', '-_'), '=');
        }

        // Where a visitor who is not signed in signs in: this site's own sign-in (its single sign-on),
        // coming back to this page; the frame's sign-in button leads there (0.20.3)
        $login = '';

        if (!$user || $user->guest) {
            $here  = Uri::getInstance()->toString();
            $login = trim((string) $params->get('remote_login', ''));
            $login = $login !== ''
                ? str_replace(['{return}', '{return64}'], [rawurlencode($here), rawurlencode(base64_encode($here))], $login)
                : Route::_('index.php?option=com_users&view=login&return=' . base64_encode($here), false, Route::TLS_IGNORE, true);
        }

        // Where a signed-in visitor signs out: this site's own sign-out, back to this page; signing out
        // in the conversation goes there, or the next page would sign them in there again
        $logout = '';

        if ($token !== '') {
            $here   = Uri::getInstance()->toString();
            $logout = trim((string) $params->get('remote_logout', ''));
            $logout = $logout !== ''
                ? str_replace(['{return}', '{return64}'], [rawurlencode($here), rawurlencode(base64_encode($here))], $logout)
                : Route::_('index.php?option=com_users&task=user.logout&' . Session::getFormToken() . '=1&return=' . base64_encode($here), false, Route::TLS_IGNORE, true);
        }

        return [
            'src'    => $site . '/media/mod_mcp_ask/js/embed.js',
            'logout' => $logout,
            'key'    => $key,
            'lang'   => $this->getApplication()->getLanguage()->getTag(),
            'user'   => $token,
            'login'  => $login,
            'why'    => $why,
            'size'   => max(0, min(240, (int) $params->get('remote_size', 0))),
            'title'  => trim((string) $params->get('title')) ?: Text::_('MOD_MCP_ASK_TITLE'),
        ];
    }

    protected function getLayoutData(): array
    {
        $data   = parent::getLayoutData();
        $params = $data['params'];

        // Another site's AI Ask: only its embed script is written
        if ($params->get('mode') === 'remote') {
            return $data + ['remote' => $this->remote($params)];
        }
        $demo   = (bool) $params->get('demo', 0);

        $title       = trim((string) $params->get('title')) ?: Text::_('MOD_MCP_ASK_TITLE');
        $placeholder = trim((string) $params->get('placeholder')) ?: Text::_('MOD_MCP_ASK_PLACEHOLDER');

        $data['mode']        = \in_array($params->get('mode'), ['box', 'float'], true) ? $params->get('mode') : 'icon';
        $data['start']       = $params->get('align', 'end') === 'start';
        $data['floatAt']     = \in_array($params->get('float_position'), ['top', 'middle'], true) ? $params->get('float_position') : 'bottom';
        // Embedded on another website (the component's embed page): its key, the website, the sign-in window
        $embed               = (array) $params->get('embed', []);
        $data['embed']       = !empty($embed['key']) ? array_intersect_key($embed, array_flip(['key', 'host', 'side', 'login'])) : null;
        $data['spot']        = $data['mode'] === 'float' && !$data['embed'] ? $this->spot() : null;
        $data['title']       = $title;
        $data['placeholder'] = $placeholder;
        // Routed, so the language of the page is the language the answer service works in
        $data['endpoint']    = $demo ? '' : Route::_('index.php?option=com_mcpask&task=ask.stream&format=raw', false);
        $data['feedback']    = $demo ? '' : Route::_('index.php?option=com_mcpask&task=ask.feedback&format=raw', false);
        $data['allowedHosts'] = $this->allowedHosts();
        $data['pageItem']    = $data['embed'] ? 0 : $this->pageItem();
        $data['sourcesShown'] = max(0, min(50, (int) $params->get('sources_shown', 3)));
        // On the days of a festive row, its pet and cleaning pet instead of the usual ones
        $festive             = $this->festive($params);
        $data['pet']         = $data['mode'] === 'float' && !$demo ? $this->pet((string) ($festive['pet'] ?? '') ?: (string) $params->get('pet', '')) : null;
        // How a new conversation clears the old one, and the pet that does it when one is chosen (0.18.0)
        $data['clear']       = \in_array($params->get('clear_effect', 'saucer'), ['saucer', 'wind', 'hole', 'fade', 'none'], true) ? (string) $params->get('clear_effect', 'saucer') : 'saucer';
        $data['cleaner']     = $data['clear'] === 'saucer' ? $this->cleaner((string) ($festive['cleaner'] ?? '') ?: (string) $params->get('cleaner', '')) : null;
        $data['demoSources'] = $demo ? $this->demoSources() : [];
        $data['accent']      = preg_match('/^#[0-9a-f]{3,8}$/i', (string) $params->get('accent')) ? $params->get('accent') : '';
        $data['strings']     = [
            'title'        => $title,
            'placeholder'  => $placeholder,
            'followup'     => Text::_('MOD_MCP_ASK_FOLLOWUP'),
            'empty'        => $placeholder,
            'send'         => Text::_('MOD_MCP_ASK_SEND'),
            'close'        => Text::_('MOD_MCP_ASK_CLOSE'),
            'newChat'      => Text::_('MOD_MCP_ASK_NEW'),
            'resume'       => Text::_('MOD_MCP_ASK_RESUME'),
            'sources'      => Text::_('MOD_MCP_ASK_SOURCES'),
            'filePage'     => Text::_('MOD_MCP_ASK_FILE_PAGE'),
            'fileDownload' => Text::_('MOD_MCP_ASK_FILE_DOWNLOAD'),
            'here'         => Text::_('MOD_MCP_ASK_HERE'),
            'more'         => Text::_('MOD_MCP_ASK_MORE'),
            'others'       => Text::_('MOD_MCP_ASK_MORE_OTHERS'),
            'less'         => Text::_('MOD_MCP_ASK_LESS'),
            'searching'    => Text::_('MOD_MCP_ASK_SEARCHING'),
            'note'         => Text::_('MOD_MCP_ASK_NOTE'),
            'noAnswer'     => Text::_('MOD_MCP_ASK_NO_ANSWER'),
            'retry'        => Text::_('MOD_MCP_ASK_RETRY'),
            'error'        => Text::_('MOD_MCP_ASK_ERROR'),
            'unconfigured' => Text::_('MOD_MCP_ASK_UNCONFIGURED'),
            'helpful'      => Text::_('MOD_MCP_ASK_HELPFUL'),
            'notHelpful'   => Text::_('MOD_MCP_ASK_NOT_HELPFUL'),
            'why'          => Text::_('MOD_MCP_ASK_WHY'),
            'thanks'       => Text::_('MOD_MCP_ASK_THANKS'),
            'previous'       => Text::_('MOD_MCP_ASK_PREVIOUS'),
            'next'           => Text::_('MOD_MCP_ASK_NEXT'),
            'calloutNote'    => Text::_('MOD_MCP_ASK_CALLOUT_NOTE'),
            'calloutTip'     => Text::_('MOD_MCP_ASK_CALLOUT_TIP'),
            'calloutWarning' => Text::_('MOD_MCP_ASK_CALLOUT_WARNING'),
            'agentWith'      => Text::_('MOD_MCP_ASK_AGENT_WITH'),
            'agentLeave'     => Text::_('MOD_MCP_ASK_AGENT_LEAVE'),
            'agentReply'     => Text::_('MOD_MCP_ASK_AGENT_REPLY'),
            'agentStart'     => Text::_('MOD_MCP_ASK_AGENT_START'),
            'agentTry'       => Text::_('MOD_MCP_ASK_AGENT_TRY'),
            'agentMore'      => Text::_('MOD_MCP_ASK_AGENT_MORE'),
            'useSuggestion'  => Text::_('MOD_MCP_ASK_USE_SUGGESTION'),
            'liveLeave'      => Text::_('MOD_MCP_ASK_LIVE_LEAVE'),
            'liveOriginal'   => Text::_('MOD_MCP_ASK_LIVE_ORIGINAL'),
            'liveTranslated' => Text::_('MOD_MCP_ASK_LIVE_TRANSLATED'),
            'liveFailed'     => Text::_('MOD_MCP_ASK_LIVE_FAILED'),
            'liveByAi'       => Text::_('MOD_MCP_ASK_LIVE_BY_AI'),
            'liveLeaveConfirm' => Text::_('MOD_MCP_ASK_LIVE_LEAVE_CONFIRM'),
            'liveAttach'        => Text::_('MOD_MCP_ASK_LIVE_ATTACH'),
            'liveUploading'     => Text::_('MOD_MCP_ASK_LIVE_UPLOADING'),
            'liveRemove'        => Text::_('MOD_MCP_ASK_LIVE_REMOVE'),
            'liveExpiresIn'     => Text::_('MOD_MCP_ASK_LIVE_EXPIRES_IN'),
            'liveExpired'       => Text::_('MOD_MCP_ASK_LIVE_EXPIRED'),
            'liveDownload'      => Text::_('MOD_MCP_ASK_LIVE_DOWNLOAD'),
            'liveTooLarge'      => Text::_('MOD_MCP_ASK_LIVE_TOO_LARGE'),
            'liveImageTooLarge' => Text::_('MOD_MCP_ASK_LIVE_IMAGE_TOO_LARGE'),
            'liveCapture' => Text::_('MOD_MCP_ASK_LIVE_CAPTURE'),
            'liveScreenshot' => Text::_('MOD_MCP_ASK_LIVE_SCREENSHOT'),
            'livePicture' => Text::_('MOD_MCP_ASK_LIVE_PICTURE'),
            'liveRecord' => Text::_('MOD_MCP_ASK_LIVE_RECORD'),
            'liveRecordNote' => Text::_('MOD_MCP_ASK_LIVE_RECORD_NOTE'),
            'liveRecording' => Text::_('MOD_MCP_ASK_LIVE_RECORDING'),
            'liveStop' => Text::_('MOD_MCP_ASK_LIVE_STOP'),
            'liveWatch' => Text::_('MOD_MCP_ASK_LIVE_WATCH'),
            'liveAgain' => Text::_('MOD_MCP_ASK_LIVE_AGAIN'),
            'liveCancel' => Text::_('MOD_MCP_ASK_LIVE_CANCEL'),
            'liveSend' => Text::_('MOD_MCP_ASK_LIVE_SEND'),
            'liveCut' => Text::_('MOD_MCP_ASK_LIVE_CUT'),
            'liveCaptureFailed' => Text::_('MOD_MCP_ASK_LIVE_CAPTURE_FAILED'),
            'liveMarkHint' => Text::_('MOD_MCP_ASK_LIVE_MARK_HINT'),
            'liveDrop' => Text::_('MOD_MCP_ASK_LIVE_DROP'),
            'liveStickers' => Text::_('MOD_MCP_ASK_LIVE_STICKERS'),
            'liveShotNow' => Text::_('MOD_MCP_ASK_LIVE_SHOT_NOW'),
            'liveDraft' => Text::_('MOD_MCP_ASK_LIVE_DRAFT'),
            'liveQuick' => Text::_('MOD_MCP_ASK_LIVE_QUICK'),
            'liveShotHint' => Text::_('MOD_MCP_ASK_LIVE_SHOT_HINT'),
            'liveRecorderTitle' => Text::_('MOD_MCP_ASK_LIVE_RECORDER_TITLE'),
            'liveRecorderNote' => Text::_('MOD_MCP_ASK_LIVE_RECORDER_NOTE'),
            'liveRecorderStart' => Text::_('MOD_MCP_ASK_LIVE_RECORDER_START'),
            'liveRecorderBack' => Text::_('MOD_MCP_ASK_LIVE_RECORDER_BACK'),
            'liveRecorderSending' => Text::_('MOD_MCP_ASK_LIVE_RECORDER_SENDING'),
            'liveRecorderSent' => Text::_('MOD_MCP_ASK_LIVE_RECORDER_SENT'),
            'required'       => Text::_('MOD_MCP_ASK_REQUIRED'),
            'reportWriting'  => Text::_('MOD_MCP_ASK_REPORT_WRITING'),
            'reportOpen'     => Text::_('MOD_MCP_ASK_REPORT_OPEN'),
            'reportEmailed'  => Text::_('MOD_MCP_ASK_REPORT_EMAILED'),
            'reportSources'  => Text::_('MOD_MCP_ASK_REPORT_SOURCES'),
            'handoff'        => Text::_('MOD_MCP_ASK_HANDOFF'),
            'statusOnline'   => Text::_('MOD_MCP_ASK_STATUS_ONLINE'),
            'statusOffline'  => Text::_('MOD_MCP_ASK_STATUS_OFFLINE'),
            'statusClosed'   => Text::_('MOD_MCP_ASK_STATUS_CLOSED'),
            'handoffOffer'   => Text::_('MOD_MCP_ASK_HANDOFF_OFFER'),
            'signIn'         => Text::_('MOD_MCP_ASK_SIGN_IN'),
            'signOut'        => Text::_('MOD_MCP_ASK_SIGN_OUT'),
        ];

        // Helpers that take over the conversation for one task (the component's agents)
        $data['agentStart'] = $demo ? '' : Route::_('index.php?option=com_mcpask&task=agent.start&format=raw', false);
        $data['agentStep']  = $demo ? '' : Route::_('index.php?option=com_mcpask&task=agent.step&format=raw', false);
        $data['agentReport'] = $demo ? '' : Route::_('index.php?option=com_mcpask&task=agent.report&format=raw', false);
        $data['agentHandoff'] = $demo ? '' : Route::_('index.php?option=com_mcpask&task=agent.handoff&format=raw', false);
        $data['agentList']    = $demo ? '' : Route::_('index.php?option=com_mcpask&task=agent.list&format=raw', false);
        $data['agentLookup']  = $demo ? '' : Route::_('index.php?option=com_mcpask&task=agent.lookup&format=raw', false);
        [$data['agents'], $data['handoff'], $data['agentLooks']] = $demo ? [[], null, []] : $this->agents();

        $this->addAssets($demo, $params->get('theme', 'site') !== 'own');

        return $data;
    }

    /**
     * Agents listed when the conversation opens (their ID and button text), and the one that passes
     * visitors on to a person, if any, listed or not.
     *
     * @return  array{0: array<int, array{id: int, label: string}>, 1: array{id: int, label: string}|null, 2: array<int, array>}
     */
    private function agents(): array
    {
        $class = 'Gss\\Component\\Mcpask\\Administrator\\Agent\\Agents';
        $app   = $this->getApplication();
        $user  = $app->getIdentity();

        try {
            if (!class_exists($class) || !$user || !method_exists($class, 'offer')) {
                return [[], null, []];
            }

            return (new $class(\Joomla\CMS\Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class)))
                ->offer($app->getLanguage()->getTag(), $user);
        } catch (\Throwable) {
            return [[], null, []];
        }
    }

    /**
     * The first festive row (0.19.0) whose days today is one of, in the site's time zone: its pet
     * and cleaning pet; null on other days.
     *
     * @return  array{pet: string, cleaner: string}|null
     */
    private function festive(Registry $params): ?array
    {
        $today = \Joomla\CMS\Factory::getDate('now', (string) $this->getApplication()->get('offset', 'UTC'));
        $date  = $today->format('Y-m-d', true);
        $day   = substr($date, 5);

        foreach ((array) $params->get('festive', []) as $row) {
            $row  = (array) $row;
            $from = substr(trim((string) ($row['from'] ?? '')), 0, 10);
            $to   = substr(trim((string) ($row['to'] ?? '')), 0, 10) ?: $from;

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
                continue;
            }

            if (!empty($row['yearly'])) {
                // Month and day only; a period may run over the new year (12-28 to 01-03)
                [$start, $end] = [substr($from, 5), substr($to, 5)];
                $hit           = $start <= $end ? $day >= $start && $day <= $end : $day >= $start || $day <= $end;
            } else {
                $hit = $date >= $from && $date <= $to;
            }

            if ($hit) {
                return ['pet' => (string) ($row['pet'] ?? ''), 'cleaner' => (string) ($row['cleaner'] ?? '')];
            }
        }

        return null;
    }

    /**
     * The pet the floating button is (the component's pet library), if one is chosen and there.
     */
    private function pet(string $choice): ?array
    {
        $pets = 'Gss\\Component\\Mcpask\\Administrator\\Pet\\Pets';

        if ($choice === '' || !class_exists($pets)) {
            return null;
        }

        try {
            $pet = (new $pets(\Joomla\CMS\Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class)))->display($choice);
        } catch (\Throwable) {
            return null;
        }

        // Whom it greets: a signed-in visitor by the name they gave
        $user = $this->getApplication()->getIdentity();

        if ($pet && $user && !$user->guest) {
            $pet['name'] = mb_substr((string) $user->name, 0, 40);
        }

        return $pet;
    }

    /**
     * A pet that clears the conversation (0.18.0), or null for the saucer.
     *
     * @return  array{clean: string, point: string, size: int}|null
     */
    private function cleaner(string $choice): ?array
    {
        $pets = 'Gss\\Component\\Mcpask\\Administrator\\Pet\\Pets';

        if ($choice === '' || !class_exists($pets) || !method_exists($pets, 'cleaner')) {
            return null;
        }

        try {
            return (new $pets(\Joomla\CMS\Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class)))->cleaner($choice);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * The article or FLEXIcontent item this page shows, so a question asked here can be understood
     * with it in mind. 0 on any other page.
     */
    private function pageItem(): int
    {
        $input = $this->getApplication()->getInput();
        $shown = [$input->getCmd('option'), $input->getCmd('view')];

        return \in_array($shown, [['com_content', 'article'], ['com_flexicontent', 'item']], true) ? max(0, $input->getInt('id')) : 0;
    }

    /**
     * Where the visitor last moved the floating button to, so the page arrives with it there
     * (ask.js writes the cookie, and applies it again if a cached page was sent without it).
     *
     * @return  array{side: string, y: float}|null
     */
    private function spot(): ?array
    {
        $value = (string) $this->getApplication()->getInput()->cookie->getString('mcp_ask_float', '');

        if (!preg_match('/^(left|right):([01](?:\.\d{1,6})?)$/', $value, $match)) {
            return null;
        }

        return ['side' => $match[1], 'y' => min(1.0, (float) $match[2])];
    }

    /**
     * Sites links in answers may lead to, as the component allows them.
     *
     * @return  string[]
     */
    private function allowedHosts(): array
    {
        $settings = 'Gss\\Component\\Mcpask\\Administrator\\Ask\\Settings';

        try {
            return class_exists($settings) ? $settings::load()->allowedHosts() : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * The colours chosen on the component's Appearance page, as CSS; empty when none were.
     */
    private function themeCss(): string
    {
        $settings = 'Gss\\Component\\Mcpask\\Administrator\\Ask\\Settings';
        $theme    = 'Gss\\Component\\Mcpask\\Administrator\\Ask\\Theme';

        try {
            return class_exists($settings) && class_exists($theme) ? $theme::css($settings::load()->theme()) : '';
        } catch (\Throwable) {
            return '';
        }
    }

    private function serviceReady(): bool
    {
        if (!ComponentHelper::isEnabled('com_mcpask')) {
            return false;
        }

        $service = ComponentHelper::getParams('com_mcpask');

        if (!$service->get('enabled', 1) || trim((string) $service->get('api_key', '')) === '') {
            return false;
        }

        // Not shown to a visitor the component's permissions do not let ask
        $settings = 'Gss\\Component\\Mcpask\\Administrator\\Ask\\Settings';
        $user     = $this->getApplication()->getIdentity();

        return !class_exists($settings) || !$user || $settings::load()->mayAsk($user);
    }

    private function addAssets(bool $demo, bool $siteTheme): void
    {
        $wa = $this->getApplication()->getDocument()->getWebAssetManager();

        $wa->getRegistry()->addExtensionRegistryFile('mod_mcp_ask');
        $wa->useStyle('mod_mcp_ask.ask')->useScript('mod_mcp_ask.ask');

        if ($demo) {
            $wa->useScript('mod_mcp_ask.demo');
        }

        // The site's colours (Appearance page), after the module's own styles, once a page
        if ($siteTheme && !self::$themeAdded) {
            self::$themeAdded = true;
            $css              = $this->themeCss();

            if ($css !== '') {
                $wa->addInlineStyle($css, [], [], ['mod_mcp_ask.ask']);
            }
        }

        // Inline, because it has to run before the first paint of a page reached from a source link
        $reveal = JPATH_ROOT . '/media/mod_mcp_ask/js/ask-reveal.js';

        if (!self::$revealAdded && is_file($reveal)) {
            $wa->addInlineScript(file_get_contents($reveal));
            self::$revealAdded = true;
        }
    }

    /**
     * Real pages of this site for the canned answer to cite, so the page change can be tried.
     *
     * @return  array<int, array{title: string, url: string}>
     */
    private function demoSources(): array
    {
        $menu    = $this->getApplication()->getMenu();
        $default = $menu->getDefault();
        $tag     = $this->getApplication()->getLanguage()->getTag();
        $items   = $default ? $menu->getItems(['menutype', 'language'], [$default->menutype, [$tag, '*']]) : [];
        $sources = [];

        foreach ($items as $item) {
            if ($item->type !== 'component' || \count($sources) === 3) {
                continue;
            }

            $sources[] = [
                'title' => $item->title,
                'url'   => Route::_('index.php?Itemid=' . $item->id, false, Route::TLS_IGNORE, true),
            ];
        }

        return $sources ?: [['title' => $this->getApplication()->get('sitename'), 'url' => Uri::root()]];
    }
}
