<?php

/**
 * @package     AI Ask for Joomla
 * @subpackage  com_mcpask
 *
 * @copyright   (C) 2026 GSS
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Gss\Component\Mcpask\Administrator\Agent;

use Joomla\CMS\Http\HttpFactory;

\defined('_JEXEC') or die;

/**
 * Public holidays of a country and year, for the days an agent's people do not answer: from each
 * country's own official source where there is one that can be read (Taiwan's Directorate-General
 * of Personnel Administration, China's State Council notices), from Google's public holiday
 * calendars for countries open data does not cover, and from Nager.Date for the others. Only days
 * off for the whole country; the administrator checks them before they are kept.
 *
 * @since  0.17.0
 */
final class Holidays
{
    /** Countries offered first, by ISO code, and where their holidays come from. */
    public const COUNTRIES = [
        'TW' => 'dgpa', 'US' => 'nager', 'JP' => 'nager', 'KR' => 'nager', 'CN' => 'holidaycn', 'HK' => 'nager',
        'SG' => 'nager', 'MY' => 'google:malaysia', 'TH' => 'google:th', 'VN' => 'nager', 'PH' => 'nager',
        'ID' => 'nager', 'GB' => 'nager', 'FR' => 'nager', 'DE' => 'nager', 'AU' => 'nager', 'CA' => 'nager',
    ];

    private const TIMEOUT = 20;

    /**
     * @return  array{days: array<int, array{date: string, name: string}>, workdays: array<int, array{date: string, name: string}>}
     *          Days off, and weekend days worked instead (Taiwan, China), by date
     *
     * @throws  \RuntimeException  When the source cannot be read
     */
    public function fetch(string $country, int $year): array
    {
        $country = strtoupper($country);

        if ($year < 2000 || $year > 2100) {
            throw new \RuntimeException('Year out of range');
        }

        $source = self::COUNTRIES[$country] ?? (preg_match('/^[A-Z]{2}$/', $country) ? 'nager' : '');
        $found  = match (true) {
            $source === 'dgpa'                 => $this->taiwan($year),
            $source === 'holidaycn'            => $this->china($year),
            str_starts_with($source, 'google:') => $this->google(substr($source, 7), $year),
            $source === 'nager'                => $this->nager($country, $year),
            default                            => throw new \RuntimeException('Unknown country'),
        };

        // Sources that know of weekend days worked instead give both
        [$days, $workdays] = isset($found['days']) ? [$found['days'], $found['workdays']] : [$found, []];

        return ['days' => self::merge($days, $year), 'workdays' => self::merge($workdays, $year)];
    }

    /**
     * One line a day, in order, the names of a day put together.
     *
     * @return  array<int, array{date: string, name: string}>
     */
    private static function merge(array $days, int $year): array
    {
        $merged = [];

        foreach ($days as $day) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day['date']) || !str_starts_with($day['date'], (string) $year)) {
                continue;
            }

            $name = trim(preg_replace('/\s+/u', ' ', strip_tags($day['name'])) ?? '');

            if (!isset($merged[$day['date']])) {
                $merged[$day['date']] = $name;
            } elseif ($name !== '' && !str_contains($merged[$day['date']], $name)) {
                $merged[$day['date']] = trim($merged[$day['date']] . '、' . $name, '、');
            }
        }

        ksort($merged);

        return array_map(static fn ($date, $name) => ['date' => $date, 'name' => mb_substr($name, 0, 120)], array_keys($merged), $merged);
    }

    /**
     * Taiwan: the office calendar the Directorate-General of Personnel Administration publishes on
     * data.gov.tw (dataset 14718), a CSV a year: date, weekday, day off (2) or not (0), note. Days
     * off from Monday to Friday (holidays, days in lieu), and days off with a note.
     */
    private function taiwan(int $year): array
    {
        $dataset = json_decode($this->get('https://data.gov.tw/api/v2/rest/dataset/14718'), true);
        $roc     = $year - 1911;
        $url     = '';

        // The year's table, not the one made for Google Calendar; a later corrected one wins
        foreach ((array) ($dataset['result']['distribution'] ?? []) as $resource) {
            $title = (string) ($resource['resourceDescription'] ?? '');

            if (str_starts_with($title, $roc . '年') && !str_contains($title, 'Google') && strtoupper((string) ($resource['resourceFormat'] ?? '')) === 'CSV') {
                $url = (string) ($resource['resourceDownloadUrl'] ?? '');
            }
        }

        if ($url === '' || !preg_match('#^https://(www\.)?dgpa\.gov\.tw/#i', $url)) {
            throw new \RuntimeException('The calendar of ' . $year . ' (' . $roc . ') has not been published yet.');
        }

        $csv      = $this->text($this->get($url));
        $days     = [];
        $workdays = [];

        foreach (preg_split('/\R/u', $csv) ?: [] as $line) {
            $cells = str_getcsv($line, ',', '"', '\\');

            if (\count($cells) < 3 || !preg_match('/^(\d{4})(\d{2})(\d{2})$/', trim($cells[0]), $match)) {
                continue;
            }

            $date    = $match[1] . '-' . $match[2] . '-' . $match[3];
            $note    = trim((string) ($cells[3] ?? ''));
            $weekend = \in_array(trim($cells[1]), ['六', '日'], true);

            if (trim($cells[2]) === '2' && (!$weekend || $note !== '')) {
                $days[] = ['date' => $date, 'name' => $note];
            } elseif (trim($cells[2]) === '0' && $weekend) {
                // A weekend day worked to make up for a day off
                $workdays[] = ['date' => $date, 'name' => $note !== '' ? $note : '補行上班'];
            }
        }

        return ['days' => $days, 'workdays' => $workdays];
    }

    /**
     * China: holiday-cn, the State Council's notices of each year as data.
     */
    private function china(int $year): array
    {
        $data = json_decode($this->get('https://cdn.jsdelivr.net/gh/NateScarlet/holiday-cn@master/' . $year . '.json'), true);
        $all  = (array) ($data['days'] ?? []);
        $line = static fn ($day, $note = '') => ['date' => (string) $day['date'], 'name' => trim((string) ($day['name'] ?? '') . $note)];

        // Days marked not off are the weekend days worked instead (调休)
        return [
            'days'     => array_map($line, array_values(array_filter($all, static fn ($day) => !empty($day['isOffDay'])))),
            'workdays' => array_map(static fn ($day) => $line($day, ' 调休上班'), array_values(array_filter($all, static fn ($day) => empty($day['isOffDay'])))),
        ];
    }

    /**
     * Google's public holiday calendar of a country (ICS): the days it marks "Public holiday" for
     * the whole country, not those of some states or observances.
     */
    private function google(string $calendar, int $year): array
    {
        $ics  = $this->get('https://calendar.google.com/calendar/ical/en.' . rawurlencode($calendar) . '%23holiday%40group.v.calendar.google.com/public/basic.ics');
        $ics  = (string) preg_replace("/\r?\n[ \t]/", '', $ics);
        $days = [];

        preg_match_all('/BEGIN:VEVENT(.*?)END:VEVENT/s', $ics, $events);

        foreach ($events[1] as $event) {
            if (!preg_match('/DTSTART;VALUE=DATE:(\d{4})(\d{2})(\d{2})/', $event, $date) || (int) $date[1] !== $year) {
                continue;
            }

            preg_match('/^DESCRIPTION:(.*)$/m', $event, $description);
            preg_match('/^SUMMARY:(.*)$/m', $event, $summary);

            if (trim(str_replace('\\n', ' ', $description[1] ?? '')) === 'Public holiday') {
                $days[] = ['date' => $date[1] . '-' . $date[2] . '-' . $date[3], 'name' => stripcslashes(trim($summary[1] ?? ''))];
            }
        }

        return $days;
    }

    /**
     * Nager.Date: public holidays of over a hundred countries, those for the whole country.
     */
    private function nager(string $country, int $year): array
    {
        $data = json_decode($this->get('https://date.nager.at/api/v3/PublicHolidays/' . $year . '/' . $country), true);

        if (!\is_array($data) || !$data) {
            throw new \RuntimeException('No public holidays are known for this country.');
        }

        $days = [];

        // Where a country's days differ by region, those of its main one: England for the United Kingdom
        $main = ['GB' => 'GB-ENG'][$country] ?? '';

        foreach ($data as $day) {
            $whole = ($day['global'] ?? true) !== false || ($main !== '' && \in_array($main, (array) ($day['counties'] ?? []), true));

            if (!$whole || !\in_array('Public', (array) ($day['types'] ?? ['Public']), true)) {
                continue;
            }

            $local  = trim((string) ($day['localName'] ?? ''));
            $name   = trim((string) ($day['name'] ?? ''));
            $days[] = ['date' => (string) $day['date'], 'name' => $local !== '' && $local !== $name ? $local . ' (' . $name . ')' : ($local ?: $name)];
        }

        return $days;
    }

    private function get(string $url): string
    {
        $response = HttpFactory::getHttp()->get($url, ['User-Agent' => 'AI Ask for Joomla (holidays)'], self::TIMEOUT);

        if ($response->code !== 200) {
            throw new \RuntimeException('HTTP ' . $response->code . ' from ' . parse_url($url, PHP_URL_HOST));
        }

        return (string) $response->body;
    }

    /**
     * Text as UTF-8: without its byte order mark, from Big5 when it is not UTF-8 (older tables).
     */
    private function text(string $body): string
    {
        $body = preg_replace('/^\xEF\xBB\xBF/', '', $body) ?? $body;

        if (!mb_check_encoding($body, 'UTF-8')) {
            $body = (string) mb_convert_encoding($body, 'UTF-8', 'BIG-5');
        }

        return $body;
    }
}
