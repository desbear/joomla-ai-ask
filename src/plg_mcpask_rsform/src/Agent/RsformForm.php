<?php

/**
 * @package     AI Ask for Joomla
 * @subpackage  plg_mcpask_rsform
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Plugin\Mcpask\Rsform\Agent;

use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

/**
 * An RSForm! Pro form as a conversation sees it: pages of fields (cut at page breaks), what each
 * field accepts, checking what was filled in, and sending it in as RSForm does.
 *
 * @since  1.0.0
 */
final class RsformForm
{
    /** RSForm fields and what they become in the conversation. */
    private const KINDS = [
        'textBox'       => 'text',
        'textArea'      => 'textarea',
        'selectList'    => 'select',
        'radioGroup'    => 'radio',
        'checkboxGroup' => 'checkbox',
        'calendar'      => 'date',
        'jQueryCalendar' => 'date',
        'birthDay'      => 'date',
        'freeText'      => 'info',
        'hidden'        => 'hidden',
        'pageBreak'     => 'page',
    ];

    /** RSForm checks that need more than the value: other fields, earlier submissions. */
    private const OTHER_RULES = ['sameas', 'uniquefield', 'uniquefielduser', 'multiplerules', 'password'];

    public string $title = '';

    /** @var string  The thank-you message, with RSForm's placeholders */
    public string $thankYou = '';

    /** @var array<int, array<int, array>>  Pages of fields: name, label, kind, required, options, help, format, default */
    public array $pages = [];

    public function __construct(private DatabaseInterface $db, public readonly int $id)
    {
    }

    public static function available(): bool
    {
        return is_file(JPATH_ADMINISTRATOR . '/components/com_rsform/helpers/rsform.php')
            && \Joomla\CMS\Component\ComponentHelper::isEnabled('com_rsform');
    }

    /**
     * @return  array<int, string>  Published forms, by ID
     */
    public static function forms(DatabaseInterface $db): array
    {
        return $db->setQuery($db->getQuery(true)->select('FormId, FormTitle')->from($db->quoteName('#__rsform_forms'))->where('Published = 1')->order('FormTitle'))->loadAssocList('FormId', 'FormTitle');
    }

    /**
     * @param   string  $language  The language of the page (zh-TW): a form written in another
     *                             language is read with its RSForm translation into this one
     */
    public function load(string $language = ''): bool
    {
        $db   = $this->db;
        $form = $db->setQuery($db->getQuery(true)->select('FormId, FormTitle, Thankyou, Lang')->from($db->quoteName('#__rsform_forms'))->where('FormId = ' . $this->id)->where('Published = 1'))->loadObject();

        if (!$form) {
            return false;
        }

        $words          = $this->translations($language, (string) $form->Lang);
        $this->title    = (string) ($words['forms']['FormTitle'] ?? $form->FormTitle);
        $this->thankYou = (string) ($words['forms']['Thankyou'] ?? $form->Thankyou);

        $components = $db->setQuery(
            $db->getQuery(true)
                ->select('c.ComponentId, t.ComponentTypeName AS type')
                ->from($db->quoteName('#__rsform_components', 'c'))
                ->join('INNER', $db->quoteName('#__rsform_component_types', 't'), 't.ComponentTypeId = c.ComponentTypeId')
                ->where('c.FormId = ' . $this->id)
                ->where('c.Published = 1')
                ->order('c.Order')
        )->loadObjectList();

        $page = [];

        foreach ($components as $component) {
            $kind = self::KINDS[$component->type] ?? null;

            if ($kind === null) {
                // Captchas, buttons and anything else a conversation does without
                continue;
            }

            if ($kind === 'page') {
                $this->pages[] = $page;
                $page          = [];
                continue;
            }

            $p = $db->setQuery(
                $db->getQuery(true)->select('PropertyName, PropertyValue')->from($db->quoteName('#__rsform_properties'))->where('ComponentId = ' . (int) $component->ComponentId)
            )->loadAssocList('PropertyName', 'PropertyValue');

            // The words of the page's language, where the form has them
            foreach ($words['properties'][(int) $component->ComponentId] ?? [] as $name => $value) {
                $p[$name] = $value;
            }

            $readonly = strtoupper((string) ($p['READONLY'] ?? 'NO')) === 'YES' || preg_match('/\breadonly\b/i', (string) ($p['ADDITIONALATTRIBUTES'] ?? ''));

            // A box the visitor only reads (terms to agree to, say): shown as text, sent as it is
            if ($readonly && \in_array($kind, ['text', 'textarea'], true)) {
                $kind = 'info';
            }

            $field = [
                'cid'      => (int) $component->ComponentId,
                'name'     => (string) ($p['NAME'] ?? ''),
                'label'    => self::plain((string) ($p['CAPTION'] ?? '')),
                'kind'     => $kind,
                'required' => strtoupper((string) ($p['REQUIRED'] ?? 'NO')) === 'YES',
                'help'     => self::plain((string) ($p['DESCRIPTION'] ?? '')),
                'message'  => self::plain((string) ($p['VALIDATIONMESSAGE'] ?? '')),
                'format'   => (string) ($p['DATEFORMAT'] ?? 'Y-m-d'),
                'default'  => '',
                'options'  => [],
                'placeholder' => self::plain((string) ($p['PLACEHOLDER'] ?? '')),
                'max'      => max(0, (int) ($p['MAXSIZE'] ?? 0)),
                'rule'     => strtolower((string) ($p['VALIDATIONRULE'] ?? '')),
                'extra'    => (string) ($p['VALIDATIONEXTRA'] ?? ''),
                'fixed'    => null,
            ];

            if ($kind === 'info' && $readonly) {
                // The box's caption over its text, and its text sent in with the form
                $field['fixed'] = (string) ($p['DEFAULTVALUE'] ?? '');
                $field['help']  = self::plain($field['fixed']);
            } elseif ($kind === 'info') {
                $field['label'] = self::plain((string) ($p['TEXT'] ?? ''));
                $field['name']  = $field['name'] ?: 'info' . $component->ComponentId;
            }

            if ($kind === 'text' && preg_match('/email/i', (string) ($p['VALIDATIONRULE'] ?? ''))) {
                $field['kind'] = 'email';
            }

            // What the field starts with, as on the form's page: its code run, and RSForm's global
            // placeholders ({global:fullname}, {global:useremail} and the like) for this visitor
            if (\in_array($field['kind'], ['hidden', 'text', 'email', 'textarea', 'date'], true)) {
                $field['default'] = self::startsWith((string) ($p['DEFAULTVALUE'] ?? ''));
            }

            if (\in_array($kind, ['select', 'radio', 'checkbox'], true)) {
                [$field['options'], $field['default']] = self::options((string) ($p['ITEMS'] ?? ''), $kind);
            }

            if ($field['name'] !== '' && ($kind !== 'info' || $field['label'] !== '')) {
                $page[] = $field;
            }
        }

        $this->pages[] = $page;
        $this->pages   = array_values(array_filter($this->pages, static fn ($fields) => array_filter($fields, static fn ($field) => !\in_array($field['kind'], ['info', 'hidden'], true))));

        return $this->pages !== [];
    }

    /**
     * Leave fields out of the conversation by name: they are neither shown nor sent.
     *
     * @param  string[]  $names
     */
    public function leaveOut(array $names): void
    {
        if (!$names) {
            return;
        }

        foreach ($this->pages as &$page) {
            $page = array_values(array_filter($page, static fn ($field) => !\in_array($field['name'], $names, true)));
        }

        unset($page);

        $this->pages = array_values(array_filter($this->pages, static fn ($fields) => array_filter($fields, static fn ($field) => !\in_array($field['kind'], ['info', 'hidden'], true))));
    }

    /**
     * The fields of a page a visitor fills in.
     *
     * @return  array[]
     */
    public function inputs(int $page): array
    {
        return array_values(array_filter($this->pages[$page] ?? [], static fn ($field) => !\in_array($field['kind'], ['info', 'hidden'], true)));
    }

    /**
     * A value as a field takes it, or null when it does not: an option's value (its label is taken
     * too), several for checkboxes, a date as Y-m-d.
     */
    public function accept(array $field, mixed $value): mixed
    {
        if (\in_array($field['kind'], ['select', 'radio', 'checkbox'], true)) {
            $values = array_map(static fn ($item) => trim((string) $item), (array) $value);
            $kept   = [];

            foreach ($values as $item) {
                foreach ($field['options'] as $option) {
                    if ($item !== '' && (strcasecmp($item, $option['value']) === 0 || strcasecmp($item, $option['label']) === 0)) {
                        $kept[] = $option['value'];
                    }
                }
            }

            $kept = array_values(array_unique($kept));

            return $field['kind'] === 'checkbox' ? $kept : ($kept[0] ?? null);
        }

        $value = trim(\is_array($value) ? implode(' ', $value) : (string) $value);

        if ($value === '') {
            return '';
        }

        if (($field['max'] ?? 0) > 0 && mb_strlen($value) > $field['max']) {
            return null;
        }

        if (!$this->valid($field, $value)) {
            return null;
        }

        return match ($field['kind']) {
            'email'  => filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : null,
            'date'   => ($time = strtotime($value)) ? date('Y-m-d', $time) : null,
            default  => mb_substr($value, 0, $field['kind'] === 'textarea' ? 5000 : 500),
        };
    }

    /**
     * What is wrong with a page as filled in, by field name.
     *
     * @param   array<string, mixed>  $values
     *
     * @return  array<string, string>
     */
    public function errors(int $page, array $values, bool $chinese): array
    {
        $errors = [];

        foreach ($this->inputs($page) as $field) {
            $value = $values[$field['name']] ?? '';
            $empty = $value === '' || $value === [] || $value === null;

            if ($empty && $field['required']) {
                $errors[$field['name']] = $field['message'] !== '' ? $field['message'] : ($chinese ? '請填寫這一欄。' : 'Please fill this in.');
            } elseif (!$empty && $this->accept($field, $value) === null) {
                $errors[$field['name']] = $field['message'] !== '' ? $field['message'] : ($chinese ? '這一欄的格式不正確。' : 'This is not in the right form.');
            }
        }

        return $errors;
    }

    /**
     * A value as people read it: option labels rather than values.
     */
    public function display(array $field, mixed $value): string
    {
        if (\in_array($field['kind'], ['select', 'radio', 'checkbox'], true)) {
            $labels = [];

            foreach ((array) $value as $item) {
                foreach ($field['options'] as $option) {
                    if ($option['value'] === $item) {
                        $labels[] = $option['label'];
                    }
                }
            }

            return implode('、', $labels);
        }

        return (string) $value;
    }

    /**
     * Send the form in as RSForm does on its own page: a submission with its values, RSForm's
     * events (so its plugins and integrations run) and its e-mails. What a form checks on its own
     * page (its captcha, its own scripts) is not run: the conversation has its own checks.
     *
     * @param   array<string, mixed>  $values  By field name, as accept() gives them
     *
     * @return  array{id: int, thanks: string}  The submission, and the thank-you message filled in
     */
    public function submit(array $values, string $ip, int $userId, string $username, string $language): array
    {
        require_once JPATH_ADMINISTRATOR . '/components/com_rsform/helpers/rsform.php';

        $db   = $this->db;
        $app  = Factory::getApplication();
        $post = [];

        foreach ($this->pages as $page) {
            foreach ($page as $field) {
                if ($field['kind'] === 'info') {
                    if ($field['fixed'] !== null) {
                        $post[$field['name']] = $field['fixed'];
                    }

                    continue;
                }

                $value = $field['kind'] === 'hidden' ? ($values[$field['name']] ?? $field['default']) : ($values[$field['name']] ?? '');

                // Dates as the form writes them
                if ($field['kind'] === 'date' && \is_string($value) && $value !== '' && ($time = strtotime($value))) {
                    $value = date(preg_replace('/[^a-zA-Z0-9.\-\/ :,]/', '', $field['format']) ?: 'Y-m-d', $time);
                }

                $post[$field['name']] = $value;
            }
        }

        $form = \RSFormProHelper::getForm($this->id);

        // The form's own words in the page's language, as RSForm reads them
        foreach ((array) \RSFormProHelper::getTranslations('forms', $this->id, $language) as $name => $text) {
            if (isset($form->{$name})) {
                $form->{$name} = $text;
            }
        }

        // What the form checks on its own when it is sent: its plugins, and its own script
        $invalid = [];
        $this->trigger('onRsformFrontendBeforeFormValidation', [['invalid' => &$invalid, 'formId' => $this->id, 'post' => &$post]]);

        $post    = $this->script((string) $form->ScriptProcess, $form, $post, $invalid);

        // Only fields of the conversation count: a captcha or a button the conversation does
        // without is never answered, and must not keep the form from being sent
        $shown   = [];

        foreach ($this->pages as $page) {
            foreach ($page as $field) {
                $shown[] = $field['cid'];
            }
        }

        $invalid = array_values(array_intersect(array_map('intval', $invalid), $shown));

        if ($invalid) {
            throw new \RuntimeException('The form turned the answers down (' . \count($invalid) . ' fields).');
        }

        $this->trigger('onRsformFrontendBeforeFormProcess', [['post' => &$post]]);

        $submission = (object) [
            'FormId'         => $this->id,
            'DateSubmitted'  => Factory::getDate()->toSql(),
            'UserIp'         => $form->KeepIP ? $ip : '0.0.0.0',
            'Username'       => $username,
            'UserId'         => $userId,
            'Lang'           => $language,
            'confirmed'      => 1,
            'ConfirmedIP'    => '',
            'ConfirmedDate'  => null,
            'SubmissionHash' => md5(random_bytes(32)),
        ];

        $db->insertObject('#__rsform_submissions', $submission, 'SubmissionId');
        $id = (int) $submission->SubmissionId;

        $this->trigger('onRsformFrontendBeforeStoreSubmissions', [['formId' => $this->id, 'post' => &$post, 'SubmissionId' => $id]]);

        foreach ($post as $name => $value) {
            $row = (object) [
                'SubmissionId' => $id,
                'FormId'       => $this->id,
                'FieldName'    => $name,
                'FieldValue'   => \RSFormProHelper::stripJava(\is_array($value) ? implode("\n", $value) : (string) $value),
            ];
            $db->insertObject('#__rsform_submission_values', $row);
        }

        $this->trigger('onRsformFrontendAfterStoreSubmissions', [['SubmissionId' => $id, 'formId' => $this->id]]);

        [$replace, $with] = \RSFormProHelper::sendSubmissionEmails($id);

        // A form that keeps no submissions has it removed once everything has been sent
        if (!$form->Keepdata) {
            require_once JPATH_ADMINISTRATOR . '/components/com_rsform/helpers/submissions.php';
            register_shutdown_function(['RSFormProSubmissionsHelper', 'deleteSubmissions'], $id);
        }

        // Then, as RSForm does after storing: the form's script, its mappings to other tables, and
        // its "Post to another location" (where other systems receive the submission)
        $this->script((string) $form->ScriptProcess2, $form, $post, $invalid, $id, (array) $replace, (array) $with);

        $mappings = \RSFormProHelper::getMappings($this->id);
        $this->trigger('onRsformBeforeMappings', ['SubmissionId' => $id, 'formId' => $this->id, 'mappings' => &$mappings]);
        $this->safely(static fn () => \RSFormProHelper::doMappings($mappings, ['replace' => $replace, 'with' => $with]));

        $silentPost = \RSFormProHelper::getSilentPost($this->id);
        $this->trigger('onRsformBeforeSilentPost', ['SubmissionId' => $id, 'formId' => $this->id, 'silentPost' => &$silentPost]);

        // Only a post the server makes itself: one that sends the visitor's browser elsewhere cannot be made from a conversation
        if ($silentPost && !empty($silentPost->enabled) && !empty($silentPost->silent)) {
            $this->safely(static fn () => \RSFormProHelper::doSilentPost($silentPost, ['replace' => $replace, 'with' => $with, 'post' => $post]));
        }

        $this->trigger('onRsformFrontendAfterFormProcess', [['SubmissionId' => $id, 'formId' => $this->id]]);

        return ['id' => $id, 'thanks' => self::plain(str_replace((array) $replace, (array) $with, $this->thankYou))];
    }

    /**
     * Whether a value passes the field's RSForm check, by RSForm's own checks (a site's custom
     * ones included). Checks that compare with other fields or earlier submissions are left to
     * RSForm's page.
     */
    private function valid(array $field, string $value): bool
    {
        $rule = $field['rule'] ?? '';

        if ($rule === '' || $rule === 'none' || \in_array($rule, self::OTHER_RULES, true) || !self::available()) {
            return true;
        }

        try {
            require_once JPATH_ADMINISTRATOR . '/components/com_rsform/helpers/rsform.php';

            $class = \RSFormProHelper::getValidationClass();

            if (!\is_callable([$class, $rule])) {
                return true;
            }

            return (bool) \call_user_func([$class, $rule], $value, $field['extra'] ?? '', ['NAME' => $field['name'], 'VALIDATIONEXTRA' => $field['extra'] ?? '']);
        } catch (\Throwable) {
            return true;
        }
    }

    /**
     * A default value as RSForm's page shows it: its code run, the global placeholders put in. A
     * placeholder of other fields ({name:value}) has no value yet, and is left empty.
     */
    private static function startsWith(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (str_contains($value, '<code>') && self::available()) {
            require_once JPATH_ADMINISTRATOR . '/components/com_rsform/helpers/rsform.php';

            $value = (string) \RSFormProHelper::isCode($value);
        }

        if (str_contains($value, '{')) {
            $app   = Factory::getApplication();
            $user  = $app->getIdentity();
            $value = strtr($value, [
                '{global:username}'  => (string) ($user->username ?? ''),
                '{global:userid}'    => (string) (int) ($user->id ?? 0),
                '{global:useremail}' => (string) ($user->email ?? ''),
                '{global:fullname}'  => (string) ($user->name ?? ''),
                '{global:userip}'    => (string) $app->getInput()->server->getString('REMOTE_ADDR', ''),
                '{global:sitename}'  => (string) $app->get('sitename'),
                '{global:siteurl}'   => \Joomla\CMS\Uri\Uri::root(),
                '{global:mailfrom}'  => (string) $app->get('mailfrom'),
                '{global:fromname}'  => (string) $app->get('fromname'),
            ]);
            $value = (string) preg_replace('/\{[a-z0-9_]+:[a-z0-9_]+\}/i', '', $value);
        }

        return trim($value);
    }

    /**
     * The form's RSForm translation into a language, when the form was written in another one.
     *
     * @return  array{forms: array<string, string>, properties: array<int, array<string, string>>}
     */
    private function translations(string $language, string $written): array
    {
        $words = ['forms' => [], 'properties' => []];

        if ($language === '' || strcasecmp($language, $written) === 0) {
            return $words;
        }

        $db = $this->db;

        try {
            $rows = $db->setQuery(
                $db->getQuery(true)
                    ->select('reference, reference_id, value')
                    ->from($db->quoteName('#__rsform_translations'))
                    ->where('form_id = ' . $this->id)
                    ->where('lang_code = ' . $db->quote($language))
            )->loadObjectList();
        } catch (\Throwable) {
            return $words;
        }

        foreach ($rows as $row) {
            // An empty translation is a word not translated, except where the original is empty too
            if ((string) $row->value === '') {
                continue;
            }

            if ($row->reference === 'forms') {
                $words['forms'][(string) $row->reference_id] = (string) $row->value;
            } elseif ($row->reference === 'properties' && preg_match('/^(\d+)\.([A-Z_]+)$/', (string) $row->reference_id, $match)) {
                $words['properties'][(int) $match[1]][$match[2]] = (string) $row->value;
            }
        }

        return $words;
    }

    /**
     * Run a script of the form, as RSForm runs it on its own page (with the same names in reach),
     * and return the values as it left them. A script that fails is noted, and does not stop the
     * submission, as on the form's page.
     *
     * @param  array<int, mixed>  $invalid  Fields the script turns down, as on the form's page
     */
    private function script(string $code, object $form, array $post, array &$invalid, int $SubmissionId = 0, array $replace = [], array $with = []): array
    {
        if (trim($code) === '') {
            return $post;
        }

        $formId    = $this->id;
        $mainframe = Factory::getApplication();
        $db        = $this->db;
        $isAjax    = false;
        $saved     = $_POST['form'] ?? null;

        $_POST['form'] = $post;

        try {
            eval($code);
        } catch (\Throwable $e) {
            Log::add('AI Ask: the script of RSForm form ' . $formId . ' failed: ' . $e->getMessage(), Log::WARNING, 'mcpask');
        }

        $post = \is_array($_POST['form'] ?? null) ? $_POST['form'] : $post;

        if ($saved === null) {
            unset($_POST['form']);
        } else {
            $_POST['form'] = $saved;
        }

        return $post;
    }

    /**
     * A step after the submission is stored: one that fails does not lose the submission.
     */
    private function safely(callable $step): void
    {
        try {
            $step();
        } catch (\Throwable $e) {
            Log::add('AI Ask: RSForm form ' . $this->id . ': ' . $e->getMessage(), Log::WARNING, 'mcpask');
        }
    }

    /**
     * An RSForm event, as RSForm raises it (its plugins take their arguments by reference).
     */
    private function trigger(string $name, array $arguments): void
    {
        try {
            Factory::getApplication()->triggerEvent($name, $arguments);
        } catch (\Throwable) {
            // A plugin of RSForm failing must not lose the submission
        }
    }

    /**
     * RSForm's items: one per line, "value|label" or "label", "[c]" marking what is chosen at first.
     * An item without a value ("|Please select") is a prompt, not a choice.
     *
     * @return  array{0: array<int, array{value: string, label: string}>, 1: string|array}
     */
    private static function options(string $items, string $kind): array
    {
        $options = [];
        $chosen  = [];

        foreach (preg_split('/\R/u', $items) ?: [] as $line) {
            $checked = str_contains($line, '[c]');
            $line    = trim(str_replace(['[c]', '[d]'], '', $line));

            if ($line === '' || preg_match('/^\[g\]|^\[\/g\]/', $line)) {
                continue;
            }

            [$value, $label] = str_contains($line, '|') ? array_map('trim', explode('|', $line, 2)) : [$line, $line];

            if ($value === '') {
                continue;
            }

            // Labels may carry markup (a link to the terms, say): the conversation shows their text
            $label     = self::plain($label);
            $options[] = ['value' => $value, 'label' => $label !== '' ? $label : self::plain($value)];

            if ($checked) {
                $chosen[] = $value;
            }
        }

        return [$options, $kind === 'checkbox' ? $chosen : ($chosen[0] ?? '')];
    }

    private static function plain(string $html): string
    {
        $text = preg_replace(['#<br\s*/?>#i', '#</p>\s*<p[^>]*>#i'], ["\n", "\n\n"], $html);

        return trim(html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
