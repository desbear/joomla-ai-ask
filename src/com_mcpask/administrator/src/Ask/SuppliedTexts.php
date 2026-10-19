<?php

/**
 * @package     AI Ask for Joomla
 * @subpackage  com_mcpask
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Mcpask\Administrator\Ask;

use Gss\Component\Mcpask\Administrator\Agent\MarkdownLite;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

/**
 * Text the administrator gives a page of the index, as plain text or Markdown: for a document
 * whose own text cannot be read (a scan, a file too large) or says too little. Added after what
 * is read of it, or used instead.
 *
 * @since  0.19.0
 */
final class SuppliedTexts
{
    public const MODES = ['add', 'replace'];

    /** Longest text kept, in characters. */
    public const MAX = 200000;

    public function __construct(private DatabaseInterface $db)
    {
    }

    /**
     * @return  object|null  text, mode, modified, modified_by
     */
    public function get(string $source, int $itemId): ?object
    {
        try {
            return $this->db->setQuery(
                $this->db->getQuery(true)
                    ->select('text, mode, modified, modified_by')
                    ->from($this->db->quoteName('#__mcpask_texts'))
                    ->where('source = :source')
                    ->where('item_id = ' . $itemId)
                    ->bind(':source', $source)
            )->loadObject() ?: null;
        } catch (\Throwable) {
            // Not installed yet: an update that has not run its SQL
            return null;
        }
    }

    /**
     * The items of a source that have text given.
     *
     * @return  int[]
     */
    public function ids(string $source): array
    {
        try {
            return array_map('intval', $this->db->setQuery(
                $this->db->getQuery(true)
                    ->select('item_id')
                    ->from($this->db->quoteName('#__mcpask_texts'))
                    ->where('source = :source')
                    ->bind(':source', $source)
            )->loadColumn());
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Keep the text of an item; empty text takes it away.
     */
    public function save(string $source, int $itemId, string $text, string $mode, int $userId): void
    {
        $text = mb_substr(trim(str_replace("\r\n", "\n", $text)), 0, self::MAX);
        $mode = \in_array($mode, self::MODES, true) ? $mode : 'add';

        $this->db->setQuery(
            $this->db->getQuery(true)
                ->delete($this->db->quoteName('#__mcpask_texts'))
                ->where('source = :source')
                ->where('item_id = ' . $itemId)
                ->bind(':source', $source)
        )->execute();

        if ($text === '') {
            return;
        }

        $row = (object) [
            'source'      => $source,
            'item_id'     => $itemId,
            'text'        => $text,
            'mode'        => $mode,
            'modified'    => Factory::getDate()->toSql(),
            'modified_by' => $userId,
        ];
        $this->db->insertObject('#__mcpask_texts', $row);
    }

    /**
     * The body of a page with the text given for it: after what was read, or instead of it.
     */
    public function apply(Document $document): string
    {
        $given = $this->get($document->source, $document->itemId);

        if ($given === null) {
            return $document->html;
        }

        $html = MarkdownLite::html((string) $given->text);

        return $given->mode === 'replace' ? $html : $document->html . "\n" . $html;
    }
}
