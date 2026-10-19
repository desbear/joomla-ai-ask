<?php

/**
 * @package     AI Ask for Joomla
 * @subpackage  plg_mcpask_dropfiles
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Plugin\Mcpask\Dropfiles\Source;

use Gss\Component\Mcpask\Administrator\Ask\Document;
use Gss\Component\Mcpask\Administrator\Ask\DocumentText;
use Gss\Component\Mcpask\Administrator\Ask\Source\FileSourceInterface;
use Gss\Component\Mcpask\Administrator\Ask\SuppliedTexts;
use Joomla\CMS\Access\Access;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\QueryInterface;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/**
 * The files of Dropfiles a guest may download: published, in published categories open to the
 * public, within their publishing dates, kept on this server (not in a cloud drive), of a kind
 * that can be read and not too large (or with text the administrator gave instead); only those of
 * the chosen categories and the categories under them, when categories are chosen.
 *
 * @since  0.19.0
 */
final class DropfilesSource implements FileSourceInterface
{
    /** Where Dropfiles keeps the files of a category: media/com_dropfiles/{category}/{file}. */
    private const FOLDER = '/media/com_dropfiles/';

    /** @var array<int, object>  Files looked up, by ID */
    private array $files = [];

    /** @var int[]|null  Files the administrator gave text for */
    private ?array $given = null;

    public function __construct(private DatabaseInterface $db, private Registry $params)
    {
    }

    public static function isAvailable(mixed $db): bool
    {
        if (!$db instanceof DatabaseInterface || !ComponentHelper::isEnabled('com_dropfiles')) {
            return false;
        }

        try {
            return \in_array($db->replacePrefix('#__dropfiles_files'), $db->getTableList(), true);
        } catch (\Throwable) {
            return false;
        }
    }

    public function name(): string
    {
        return 'dropfiles';
    }

    public function label(): string
    {
        return 'Dropfiles';
    }

    public function ids(int $afterId = 0, int $limit = 0): array
    {
        $query = $this->eligible()->select('f.id')->where('f.id > ' . $afterId)->order('f.id');

        if ($limit > 0) {
            $query->setLimit($limit);
        }

        return array_map('intval', $this->db->setQuery($query)->loadColumn());
    }

    public function visible(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        if (!$ids) {
            return [];
        }

        return array_map('intval', $this->db->setQuery($this->eligible()->select('f.id')->whereIn('f.id', $ids))->loadColumn());
    }

    public function modified(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        if (!$ids) {
            return [];
        }

        // A file replaced keeps its row: the time it was changed, else when it was published
        return array_map('strval', $this->db->setQuery(
            $this->db->getQuery(true)
                ->select('id')
                ->select('CASE WHEN modified_time IS NULL OR modified_time < ' . $this->db->quote('1971-01-01') . ' THEN publish ELSE modified_time END AS changed')
                ->from($this->db->quoteName('#__dropfiles_files'))
                ->whereIn('id', $ids)
        )->loadAssocList('id', 'changed'));
    }

    public function load(int $id): ?Document
    {
        $file = $this->db->setQuery(
            $this->eligible()
                ->select('f.id, f.catid, f.file, f.title, f.description, f.ext, f.size, f.language, c.language AS category_language, c.title AS category')
                ->where('f.id = ' . $id)
        )->loadObject();

        if (!$file) {
            return null;
        }

        $path = $this->path($file);
        $html = '';

        // What the site says of the file first, then the file
        $description = trim(strip_tags((string) $file->description));

        if ($description !== '') {
            $html .= '<p>' . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '</p>';
        }

        try {
            // A file too large or of a kind not read is known by the text given for it
            if ($this->readable((string) $file->ext, (int) $file->size)) {
                $html .= DocumentText::read($path, (string) $file->ext)['html'];
            }
        } catch (\RuntimeException) {
            // A file that cannot be read (a scan without text, a damaged file) is known by its description only
        }

        $language = trim((string) $file->language);
        $language = $language !== '' && $language !== '*' ? $language : ((string) $file->category_language ?: '*');

        return new Document($this->name(), (int) $file->id, (string) $file->title, self::link((int) $file->id, (int) $file->catid), $language, 1, $html);
    }

    public function textFields(): array
    {
        return [];
    }

    public function url(string $link): string
    {
        return rtrim(Uri::root(), '/') . '/' . ltrim($link, '/');
    }

    public function file(string $link): ?array
    {
        parse_str((string) parse_url($link, PHP_URL_QUERY), $query);

        $id   = (int) ($query['id'] ?? 0);
        $file = $this->files[$id] ??= $this->db->setQuery(
            $this->db->getQuery(true)
                ->select('id, catid, file, ext, size')
                ->from($this->db->quoteName('#__dropfiles_files'))
                ->where('id = ' . $id)
        )->loadObject() ?: null;

        if (!$file) {
            return null;
        }

        $download = $this->url(self::link((int) $file->id, (int) $file->catid));
        $type     = strtolower((string) $file->ext);

        // Dropfiles shows a PDF in the browser only when it is set to
        $inline = $type === 'pdf' && (int) ComponentHelper::getParams('com_dropfiles')->get('open_pdf_in', 0) === 1;

        return [
            'type'     => $type,
            'size'     => (int) $file->size,
            'open'     => $inline ? $download . '&preview=1' : '',
            'download' => $download,
        ];
    }

    public function catalog(int $limit = 500): array
    {
        $db = $this->db;

        // Those a guest may download, and those of the chosen categories that are kept from guests
        $public = array_flip(array_map('intval', $db->setQuery($this->public()->select('f.id'))->loadColumn()));
        $query  = $db->getQuery(true)
            ->select('f.id, f.title, f.ext, f.size, f.remoteurl, f.modified_time, f.publish, c.title AS folder')
            ->from($db->quoteName('#__dropfiles_files', 'f'))
            ->join('LEFT', $db->quoteName('#__categories', 'c'), 'c.id = f.catid')
            ->order('f.id DESC')
            ->setLimit($limit);
        $ranges = $this->ranges();

        if ($ranges !== null) {
            $query->where($ranges ? '(' . implode(' OR ', array_map(static fn ($range) => '(c.lft >= ' . $range[0] . ' AND c.rgt <= ' . $range[1] . ')', $ranges)) . ')' : '0 = 1');
        }

        $given = array_flip($this->given());

        return array_map(fn ($row) => [
            'id'       => (int) $row->id,
            'title'    => (string) $row->title,
            'folder'   => (string) $row->folder,
            'type'     => strtolower((string) $row->ext),
            'size'     => (int) $row->size,
            'modified' => (string) ($row->modified_time ?: $row->publish),
            'reason'   => match (true) {
                trim((string) $row->remoteurl) !== ''                                    => 'remote',
                !isset($public[(int) $row->id])                                          => 'private',
                isset($given[(int) $row->id])                                            => '',
                !\in_array(strtolower((string) $row->ext), $this->extensions(), true)  => 'type',
                (int) $row->size > $this->maxBytes()                                     => 'size',
                default                                                                  => '',
            },
        ], $db->setQuery($query)->loadObjectList());
    }

    /**
     * Where the file is on this server.
     */
    private function path(object $file): string
    {
        return JPATH_ROOT . self::FOLDER . (int) $file->catid . '/' . basename((string) $file->file);
    }

    private static function link(int $id, int $category): string
    {
        return 'index.php?option=com_dropfiles&task=frontfile.download&id=' . $id . '&catid=' . $category;
    }

    /**
     * The files that may be answered from, as a query on #__dropfiles_files (f) and its category (c).
     */
    private function eligible(): QueryInterface
    {
        $query = $this->public();
        $given = $this->given();

        // Of a kind read and not too large, or with text given instead
        $query->where('(' . $this->readableCondition() . ($given ? ' OR f.id IN (' . implode(',', $given) . ')' : '') . ')');

        return $query;
    }

    /**
     * Files a guest may download, kept on this server, of the chosen categories.
     */
    private function public(): QueryInterface
    {
        $db     = $this->db;
        $levels = array_values(array_unique(array_map('intval', Access::getAuthorisedViewLevels(0))));
        $now    = Factory::getDate()->toSql();
        $zero   = $db->quote('1971-01-01');
        $query  = $db->getQuery(true)
            ->from($db->quoteName('#__dropfiles_files', 'f'))
            ->join('INNER', $db->quoteName('#__categories', 'c'), 'c.id = f.catid')
            ->where('f.state = 1')
            ->where('c.published = 1')
            ->where($db->quoteName('c.extension') . ' = ' . $db->quote('com_dropfiles'))
            ->whereIn('c.access', $levels ?: [1])
            // Files restricted to some users are not for everyone
            ->where('(f.canview IS NULL OR f.canview = ' . $db->quote('') . ' OR f.canview = ' . $db->quote('0') . ')')
            // Kept on this server: files of cloud drives have an address instead
            ->where('(f.remoteurl IS NULL OR f.remoteurl = ' . $db->quote('') . ')')
            ->where('(f.publish IS NULL OR f.publish < ' . $zero . ' OR f.publish <= :now1)')
            ->where('(f.publish_down IS NULL OR f.publish_down < ' . $zero . ' OR f.publish_down > :now2)')
            ->bind(':now1', $now)
            ->bind(':now2', $now);

        // A category is open only when every category above it is too
        $query->where(
            'NOT EXISTS (SELECT 1 FROM ' . $db->quoteName('#__categories', 'up')
            . ' WHERE up.extension = ' . $db->quote('com_dropfiles') . ' AND up.lft < c.lft AND up.rgt > c.rgt AND up.level > 0'
            . ' AND (up.published <> 1 OR up.access NOT IN (' . implode(',', $levels ?: [1]) . ')))'
        );

        // The chosen categories and those under them
        $ranges = $this->ranges();

        if ($ranges !== null) {
            $query->where($ranges ? '(' . implode(' OR ', array_map(static fn ($range) => '(c.lft >= ' . $range[0] . ' AND c.rgt <= ' . $range[1] . ')', $ranges)) . ')' : '0 = 1');
        }

        return $query;
    }

    /**
     * Whether a file of this kind and size is read.
     */
    private function readable(string $extension, int $size): bool
    {
        return \in_array(strtolower($extension), $this->extensions(), true) && $size <= $this->maxBytes();
    }

    /**
     * The same, as a condition on f.
     */
    private function readableCondition(): string
    {
        $kinds = array_map([$this->db, 'quote'], $this->extensions() ?: ['']);

        return '(LOWER(f.ext) IN (' . implode(',', $kinds) . ') AND f.size <= ' . $this->maxBytes() . ')';
    }

    /**
     * @return  int[]
     */
    private function given(): array
    {
        return $this->given ??= (new SuppliedTexts($this->db))->ids($this->name());
    }

    /**
     * The kinds of files chosen that can be read.
     *
     * @return  string[]
     */
    private function extensions(): array
    {
        $chosen = $this->params->get('extensions', ['pdf', 'docx', 'pptx', 'xlsx', 'md', 'txt']);
        $chosen = \is_array($chosen) ? $chosen : explode(',', (string) $chosen);
        $chosen = array_map(static fn ($kind) => strtolower(trim((string) $kind)), $chosen);

        // A Markdown file may be named .markdown too
        if (\in_array('md', $chosen, true)) {
            $chosen[] = 'markdown';
        }

        return array_values(array_intersect(array_unique($chosen), DocumentText::EXTENSIONS));
    }

    private function maxBytes(): int
    {
        return max(1, min(200, (int) $this->params->get('max_size', 30))) * 1024 * 1024;
    }

    /**
     * The nested-set ranges of the chosen categories; null when none are chosen (all of them).
     *
     * @return  array<int, array{0: int, 1: int}>|null
     */
    private function ranges(): ?array
    {
        $chosen = array_values(array_filter(array_map('intval', (array) $this->params->get('categories', []))));

        if (!$chosen) {
            return null;
        }

        $rows = $this->db->setQuery(
            $this->db->getQuery(true)
                ->select('lft, rgt')
                ->from($this->db->quoteName('#__categories'))
                ->where($this->db->quoteName('extension') . ' = ' . $this->db->quote('com_dropfiles'))
                ->whereIn('id', $chosen)
        )->loadRowList();

        return array_map(static fn ($row) => [(int) $row[0], (int) $row[1]], $rows);
    }
}
