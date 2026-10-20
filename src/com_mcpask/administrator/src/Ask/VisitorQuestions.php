<?php

/**
 * @package     AI Ask for Joomla
 * @subpackage  com_mcpask
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Mcpask\Administrator\Ask;

use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

\defined('_JEXEC') or die;

/**
 * What a signed-in visitor asked before, for the people answering them (AI Ask Desk): the questions
 * of their account in the log, as long as the log keeps them and records who asked. Guests have
 * none: nothing is looked up by address.
 *
 * @since  0.20.0
 */
final class VisitorQuestions
{
    public function __construct(private DatabaseInterface $db, private Settings $settings)
    {
    }

    /**
     * @return  array<int, array{id: int, time: string, question: string, answer: string, rating: int, answered: bool}>  Newest first
     */
    public function of(int $userId, int $limit = 20): array
    {
        if ($userId < 1 || !$this->settings->logQuestions() || !$this->settings->logWho()) {
            return [];
        }

        $db   = $this->db;
        $rows = $db->setQuery(
            $db->getQuery(true)
                ->select('id, created, question, answer, rating, status')
                ->from($db->quoteName('#__mcpask_log'))
                ->where('user_id = :user')
                ->where($db->quoteName('question') . ' IS NOT NULL')
                ->where($db->quoteName('status') . ' <> ' . $db->quote('blocked'))
                ->bind(':user', $userId, ParameterType::INTEGER)
                ->order('id DESC'),
            0,
            max(1, min(50, $limit))
        )->loadObjectList();

        return array_map(static fn ($row) => [
            'id'       => (int) $row->id,
            'time'     => gmdate('c', strtotime($row->created . ' UTC')),
            'question' => mb_substr(trim((string) $row->question), 0, 500),
            // The answer as read, without its references and pictures
            'answer'   => mb_substr(trim((string) preg_replace(['/!\[[^\]]*\]\([^)]*\)/u', '/\s?\[\d{1,3}\]/u', '/[#*_`>]+/u', '/\s+/u'], ['', '', '', ' '], (string) $row->answer)), 0, 400),
            'rating'   => (int) $row->rating,
            'answered' => $row->status === 'ok',
        ], $rows);
    }
}
