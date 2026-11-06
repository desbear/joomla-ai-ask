<?php

/**
 * @package     AI Ask for Joomla
 * @subpackage  com_mcpask
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Mcpask\Administrator\Ask;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

\defined('_JEXEC') or die;

/**
 * The network addresses of ordinary questions, sent as a CSV file to the addresses the options
 * name before they are forgotten (0.22.6): run by Retention, so by the scheduled task, never while
 * a visitor waits. Only what was sent is forgotten here; what a mail could not take is tried again
 * the next day, and forgotten by UsageLog::prune() a week later in any case.
 *
 * @since  0.22.6
 */
final class AddressMail
{
    /** Days more an address is kept while its mail keeps failing */
    public const GRACE = 7;

    /** Questions in one mail; more are sent the next day */
    private const BATCH = 5000;

    public function __construct(private DatabaseInterface $db, private Settings $settings)
    {
    }

    /**
     * @return  array{sent: int, error: string}  Questions whose address was sent and forgotten, and why a mail failed
     */
    public function send(): array
    {
        $to = $this->settings->ipMail();

        if ($this->settings->ipDays() === 0 || !$to) {
            return ['sent' => 0, 'error' => ''];
        }

        $db     = $this->db;
        $before = Factory::getDate('-' . $this->settings->ipDays() . ' days')->toSql();
        $rows   = $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName(['l.id', 'l.created', 'l.ip', 'l.status', 'l.question', 'u.username']))
                ->from($db->quoteName('#__mcpask_log', 'l'))
                ->join('LEFT', $db->quoteName('#__users', 'u'), 'u.id = l.user_id')
                ->where('l.evidence = 0')
                ->where('l.ip <> ' . $db->quote(''))
                ->where('l.created < :before')
                ->bind(':before', $before)
                ->order('l.id'),
            0,
            self::BATCH
        )->loadObjectList();

        if (!$rows) {
            return ['sent' => 0, 'error' => ''];
        }

        try {
            $app = Factory::getApplication();
            $app->getLanguage()->load('com_mcpask', JPATH_ADMINISTRATOR);

            $zone  = new \DateTimeZone((string) $app->get('offset', 'UTC'));
            $local = static fn ($sql) => Factory::getDate((string) $sql, 'UTC')->setTimezone($zone)->format('Y-m-d H:i:s', true);
            $first = $local($rows[0]->created);
            $last  = $local($rows[\count($rows) - 1]->created);

            $mailer = Factory::getMailer();

            foreach ($to as $email) {
                $mailer->addRecipient($email);
            }

            $mailer->setSubject(Text::sprintf('COM_MCPASK_IP_MAIL_SUBJECT', $app->get('sitename'), substr($first, 0, 10), substr($last, 0, 10)));
            $mailer->isHtml(false);
            $mailer->setBody(str_replace('\\n', "\n", Text::sprintf('COM_MCPASK_IP_MAIL_BODY', \count($rows), $first, $last, $this->settings->ipDays())));
            $mailer->addStringAttachment($this->csv($rows, $local), 'ai-ask-addresses-' . str_replace('-', '', substr($last, 0, 10)) . '.csv', 'base64', 'text/csv');

            if (!$mailer->Send()) {
                return ['sent' => 0, 'error' => 'The mail was not sent.'];
            }
        } catch (\Throwable $e) {
            return ['sent' => 0, 'error' => mb_substr($e->getMessage(), 0, 200)];
        }

        // Sent: forgotten here
        $ids = array_map(static fn ($row) => (int) $row->id, $rows);
        $db->setQuery($db->getQuery(true)->update($db->quoteName('#__mcpask_log'))->set('ip = ' . $db->quote(''))
            ->whereIn('id', $ids, ParameterType::INTEGER))->execute();

        return ['sent' => \count($ids), 'error' => ''];
    }

    /**
     * The questions as a CSV file Excel opens as UTF-8. A cell a visitor wrote that would read as a
     * formula is kept as text.
     */
    private function csv(array $rows, callable $local): string
    {
        $out  = fopen('php://temp', 'r+');
        $cell = static fn ($value) => preg_match('/^[=+\-@\t\r]/', (string) $value) ? "'" . $value : (string) $value;

        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['time', 'address', 'account', 'status', 'question'], ',', '"', '');

        foreach ($rows as $row) {
            fputcsv($out, array_map($cell, [$local($row->created), $row->ip, (string) $row->username, $row->status, (string) $row->question]), ',', '"', '');
        }

        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        return $csv;
    }
}
