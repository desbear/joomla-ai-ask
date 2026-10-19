<?php

/**
 * @package     AI Ask for Joomla
 * @subpackage  com_mcpask
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Mcpask\Administrator\Ask\Source;

\defined('_JEXEC') or die;

/**
 * A source whose pages are files (PDF, Word, PowerPoint, Excel, Markdown): an answer that cites
 * one shows it as a file to open or download, at the page the passage came from.
 *
 * @since  0.19.0
 */
interface FileSourceInterface extends SourceInterface
{
    /**
     * The file behind a stored page.
     *
     * @return  array{type: string, size: int, open: string, download: string}|null
     *          Its extension ("pdf"), size in bytes, an address that shows it in the browser (empty
     *          when it cannot be shown there) and one that downloads it; null when it is gone
     */
    public function file(string $link): ?array;

    /**
     * The name of the source as the administrator knows it ("Dropfiles").
     */
    public function label(): string;

    /**
     * Every file of the source, also those that are not read, each with why not: for the
     * administrator to see what is answered from.
     *
     * @return  array<int, array{id: int, title: string, folder: string, type: string, size: int, modified: string, reason: string}>
     *          Newest first. reason: "" (read), "private" (a guest may not download it), "remote" (not
     *          on this server), "type" (a kind that is not read), "size" (larger than allowed)
     */
    public function catalog(int $limit = 500): array;
}
