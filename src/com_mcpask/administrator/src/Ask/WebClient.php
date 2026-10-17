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
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

/**
 * Reads pages of a website the administrator has listed, as a polite and careful visitor: it
 * says who it is, keeps to robots.txt, waits between pages, stays on the site's own host, and
 * takes only so much.
 *
 * @since  0.3.0
 */
final class WebClient
{
    /** Largest document accepted: a page, a feed or a sitemap. */
    private const MAX_BYTES = 5242880;

    /** Microseconds between two requests to the same site. */
    private const PAUSE = 600000;

    private const REDIRECTS = 3;

    /** @var array<int, array{0: bool, 1: string}>  Disallow (false) and Allow (true) rules from robots.txt */
    private ?array $rules = null;

    /** @var string[]  Sitemaps that robots.txt names */
    private array $sitemaps = [];

    private float $last = 0.0;

    /** @var array<string, array{scheme: string, server: string}>  Hosts of this site reached through this machine, with how */
    private static array $local = [];

    /**
     * @param  string  $base  Address of the source; every address read must be on its host
     */
    /** Whether the administrator listed a site on the server's own network on purpose. */
    private bool $internal;

    /**
     * @param  bool  $internal  The site may be on the server's own network: this site's own pages,
     *                          or a site the administrator marked so (its name may lead there
     *                          from the server, through the company's own DNS)
     */
    public function __construct(private string $base, bool $internal = false)
    {
        $this->internal = $internal || self::isInternalHost((string) parse_url($base, PHP_URL_HOST));
    }

    /**
     * Where a request may go, checked before every request: the addresses the name resolves to
     * now, all of them public (unless the administrator listed an internal site), on the usual
     * port. The answer is pinned for curl, so the name cannot resolve differently a moment later
     * (DNS rebinding).
     *
     * @return  string|null  A CURLOPT_RESOLVE entry ("host:port:address"), or null when the request may not be made
     */
    public static function target(string $url, bool $internal = false, string &$why = ''): ?string
    {
        $parts  = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host   = strtolower(trim((string) ($parts['host'] ?? ''), '[]'));

        if (!\in_array($scheme, ['http', 'https'], true) || $host === '' || isset($parts['user']) || isset($parts['pass'])) {
            $why = 'address';

            return null;
        }

        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));

        // Only the ports of the web, unless an internal site was listed with its own
        if (!$internal && !\in_array($port, [80, 443], true)) {
            $why = 'port';

            return null;
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : self::resolve($host);

        if (!$ips) {
            $why = 'dns';

            return null;
        }

        foreach ($ips as $ip) {
            if (!$internal && !self::isPublic($ip)) {
                $why = 'private:' . $ip;

                return null;
            }
        }

        $ip = $ips[0];

        return $host . ':' . $port . ':' . (str_contains($ip, ':') ? '[' . $ip . ']' : $ip);
    }

    /**
     * @return  string[]  IPv4 and IPv6 addresses
     */
    private static function resolve(string $host): array
    {
        $ips     = [];
        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];

        foreach ($records as $record) {
            $ips[] = (string) ($record['ip'] ?? $record['ipv6'] ?? '');
        }

        $ips = array_values(array_filter($ips));

        return $ips ?: (gethostbynamel($host) ?: []);
    }

    /**
     * Whether an address is on the public internet: not private, loopback, link-local, reserved,
     * shared (carrier-grade NAT), unique-local, or IPv4 dressed as IPv6.
     */
    private static function isPublic(string $ip): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }

        $packed = inet_pton($ip);

        if ($packed === false) {
            return false;
        }

        if (\strlen($packed) === 4) {
            $first = \ord($packed[0]);
            $second = \ord($packed[1]);

            // 0.0.0.0/8, 100.64.0.0/10, 198.18.0.0/15
            return $first !== 0 && !($first === 100 && $second >= 64 && $second <= 127) && !($first === 198 && ($second & 0xFE) === 18);
        }

        $hex = bin2hex($packed);

        return !str_starts_with($hex, '00000000000000000000ffff')  // ::ffff:a.b.c.d
            && !str_starts_with($hex, '0000000000000000')            // ::, ::1 and IPv4-compatible
            && !preg_match('/^f[cd]/', $hex)                         // fc00::/7
            && !preg_match('/^fe[89ab]/', $hex)                      // fe80::/10
            && !str_starts_with($hex, '0064ff9b');                   // 64:ff9b::/96 (NAT64 onto IPv4)
    }

    /**
     * Whether a host the administrator gave is on the server's own network: then they meant it.
     */
    private static function isInternalHost(string $host): bool
    {
        $host = strtolower(trim($host, '[]'));

        if ($host === 'localhost' || str_ends_with($host, '.localhost')) {
            return true;
        }

        return filter_var($host, FILTER_VALIDATE_IP) !== false && !self::isPublic($host);
    }

    /**
     * Where an address the administrator gave really leads: its redirects are followed (at most
     * three) even to another host, but never to the server's own network.
     */
    public static function landing(string $url, bool $internal = false): string
    {
        $current  = $url;
        $internal = $internal || self::isInternalHost((string) parse_url($url, PHP_URL_HOST));

        for ($hop = 0; $hop <= self::REDIRECTS; $hop++) {
            $pin = self::target($current, $internal);

            if ($pin === null) {
                // Not reachable, or somewhere it may not lead: the address as given stays, and is refused there
                return $url;
            }

            // The last hop is only checked, not followed further
            if ($hop === self::REDIRECTS) {
                return $current;
            }

            $curl = curl_init($current);

            curl_setopt_array($curl, [
                CURLOPT_RESOLVE        => [$pin],
                CURLOPT_NOBODY         => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS | CURLPROTO_HTTP,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; AskTheSite/0.4; +' . rtrim(Uri::root(), '/') . ')',
            ]);
            curl_exec($curl);

            $status   = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            $location = (string) curl_getinfo($curl, CURLINFO_REDIRECT_URL);

            curl_close($curl);

            if (!\in_array($status, [301, 302, 303, 307, 308], true) || !preg_match('#^https?://#i', $location)) {
                return $current;
            }

            $current = $location;
        }

        return $current;
    }

    /**
     * Whether an address belongs to the site: the same host (with or without "www."), over HTTPS
     * unless the source itself was given as HTTP.
     */
    public function owns(string $url): bool
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $mine   = strtolower((string) parse_url($this->base, PHP_URL_SCHEME));
        $bare   = static fn ($host) => preg_replace('/^www\./', '', strtolower((string) $host));

        return \in_array($scheme, $mine === 'http' ? ['http', 'https'] : ['https'], true)
            && $bare(parse_url($url, PHP_URL_HOST)) === $bare(parse_url($this->base, PHP_URL_HOST))
            && $bare(parse_url($url, PHP_URL_HOST)) !== '';
    }

    /**
     * Read a document of the site.
     *
     * @param   bool  $list  A list of pages (a feed, a sitemap) rather than a page: read even where
     *                       robots.txt keeps crawlers out, as robots.txt is about crawling pages and
     *                       a site often keeps its sitemap's address out of search results that way.
     *                       The pages it lists are each still checked.
     *
     * @return  array{status: int, type: string, charset: string, body: string, url: string}  The status 0 when it could not be read at all
     */
    public function get(string $url, bool $list = false): array
    {
        for ($hop = 0; $hop <= self::REDIRECTS; $hop++) {
            if (!$this->owns($url) || (!$list && !$this->allowed($url))) {
                return ['status' => 0, 'type' => '', 'charset' => '', 'body' => '', 'url' => $url, 'why' => $this->owns($url) ? 'robots' : 'elsewhere'];
            }

            $result = $this->request($url);

            if (!\in_array($result['status'], [301, 302, 303, 307, 308], true) || $result['location'] === '') {
                return $result + ['url' => $url];
            }

            // A redirect is followed only while it stays on the site (checked at the top of the loop)
            $url = $this->absolute($result['location'], $url);
        }

        return ['status' => 0, 'type' => '', 'charset' => '', 'body' => '', 'url' => $url, 'why' => 'redirects'];
    }

    /**
     * An address as it stands in a page, made absolute against the page's address.
     */
    public function absolute(string $href, string $page): string
    {
        $href = trim(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $href)) {
            return $href;
        }

        $parts = parse_url($page);
        $root  = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '') . (isset($parts['port']) ? ':' . $parts['port'] : '');

        if (str_starts_with($href, '//')) {
            return ($parts['scheme'] ?? 'https') . ':' . $href;
        }

        if (str_starts_with($href, '/')) {
            return $root . $href;
        }

        $directory = preg_replace('#/[^/]*$#', '/', $parts['path'] ?? '/');

        return $root . $directory . $href;
    }

    /**
     * Whether robots.txt lets this visitor read an address. The rules for every visitor ("*") and
     * for this one apply; the longest matching rule decides, as search engines do.
     */
    public function allowed(string $url): bool
    {
        if ($this->rules === null) {
            $this->rules = [];
            $robots      = $this->request($this->absolute('/robots.txt', $this->base));

            // As search engines do, only the first 500 KB of robots.txt counts
            if ($robots['status'] === 200) {
                $robots['body'] = substr($robots['body'], 0, 512000);
                $this->rules    = self::robots($robots['body']);

                preg_match_all('/^\s*sitemap\s*:\s*(\S+)/im', $robots['body'], $found);
                $this->sitemaps = array_values(array_unique(array_filter($found[1], fn ($url) => $this->owns($url))));
            }
        }

        $path  = (string) parse_url($url, PHP_URL_PATH) ?: '/';
        $path .= ($query = parse_url($url, PHP_URL_QUERY)) ? '?' . $query : '';
        $best  = [-1, true];

        foreach ($this->rules as [$allow, $rule]) {
            $pattern = '#^' . str_replace(['\*', '\$'], ['.*', '$'], preg_quote($rule, '#')) . '#';

            if (preg_match($pattern, $path) && \strlen($rule) > $best[0]) {
                $best = [\strlen($rule), $allow];
            }
        }

        return $best[1];
    }

    /**
     * The sitemaps robots.txt names that are on the site itself.
     *
     * @return  string[]
     */
    public function sitemaps(): array
    {
        $this->allowed($this->base);

        return $this->sitemaps;
    }

    /**
     * @return  array<int, array{0: bool, 1: string}>
     */
    private static function robots(string $text): array
    {
        $rules  = [];
        $groups = [];
        $agents = [];
        $inRules = false;

        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            $line = trim((string) preg_replace('/#.*/', '', $line));

            if (!preg_match('/^([a-z-]+)\s*:\s*(.*)$/i', $line, $match)) {
                continue;
            }

            $field = strtolower($match[1]);
            $value = trim($match[2]);

            if ($field === 'user-agent') {
                // A user-agent line after rules starts a new group
                if ($inRules) {
                    $agents  = [];
                    $inRules = false;
                }

                $agents[] = strtolower($value);
            } elseif (\in_array($field, ['allow', 'disallow'], true)) {
                $inRules = true;

                foreach ($agents as $agent) {
                    $groups[$agent][] = [$field === 'allow', $value];
                }
            }
        }

        // This visitor's own group replaces the general one
        foreach (\array_slice($groups['askthesite'] ?? $groups['*'] ?? [], 0, 1000) as [$allow, $value]) {
            if ($value !== '') {
                // Runs of wildcards match what one does, and cost the matcher dearly
                $rules[] = [$allow, (string) preg_replace('/\*+/', '*', mb_substr($value, 0, 500))];
            }
        }

        return $rules;
    }

    /**
     * Read one address, without following redirects. The site's own pages are read through the web
     * server on this machine when the site's public address cannot be reached from it, as is
     * common behind a firewall or NAT that does not let a server reach itself that way.
     *
     * @return  array{status: int, type: string, charset: string, body: string, location: string, why: string}
     */
    private function request(string $url): array
    {
        // Never two requests to the site in quick succession
        $wait = (int) (self::PAUSE - (microtime(true) - $this->last) * 1000000);

        if ($wait > 0) {
            usleep($wait);
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $own  = self::isOwnHost($host);

        // Already known to be reached only through this machine (in this request, or remembered)
        if ($own && !isset(self::$local[$host])) {
            $known = self::remembered('site:local');

            if (($known['host'] ?? '') === $host && isset($known['route']['server'], $known['route']['scheme'])) {
                self::$local[$host] = $known['route'];
            }
        }

        if ($own && isset(self::$local[$host])) {
            $result = $this->local($url, self::$local[$host]);

            if ($result['status'] !== 0) {
                $this->last = microtime(true);

                return $result;
            }

            // That way no longer works: the usual way, and the search for another, again
            unset(self::$local[$host]);
            self::remember('site:local', []);
        }

        $why = '';
        $pin = self::target($url, $this->internal, $why);

        // An address that leads into the server's own network (or nowhere) is not requested at all,
        // unless it is this site's own
        if ($pin === null && !($own && str_starts_with($why, 'private:'))) {
            return ['status' => 0, 'type' => '', 'charset' => '', 'body' => '', 'location' => '', 'why' => $why];
        }

        $result = $this->fetch($url, $pin !== null ? [CURLOPT_RESOLVE => [$pin]] : []);

        if ($own && $result['status'] === 0 && str_starts_with($result['why'], 'connect:')) {
            $tried = [];

            foreach (self::localRoutes($url) as $route) {
                $local = $this->local($url, $route);

                // A redirect to the same page over https only says that this way is not the way
                if ($local['status'] !== 0 && !$this->backToHttps($url, $local)) {
                    self::$local[$host] = $route;
                    $result             = $local;

                    self::remember('site:local', ['host' => $host, 'route' => $route]);

                    break;
                }

                $tried[] = $route['scheme'] . '://' . $route['server'] . ' → ' . ($local['status'] ? 'HTTP ' . $local['status'] : substr($local['why'], 8));
            }

            if ($result['status'] === 0 && $tried) {
                $result['why'] .= '; ' . implode('; ', $tried);
            }
        }

        $this->last = microtime(true);

        return $result;
    }

    /**
     * Read an address of this site through the web server on this machine: over https with the
     * site's own name and certificate, or over plain http to a server behind a proxy that ends
     * https (telling it the visitor came over https, as the proxy does).
     *
     * @param  array{scheme: string, server: string}  $route
     */
    private function local(string $url, array $route): array
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $port = (int) (parse_url($url, PHP_URL_PORT) ?: (strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https' ? 443 : 80));

        if ($route['scheme'] === 'http' && str_starts_with(strtolower($url), 'https:')) {
            $url  = 'http:' . substr($url, 6);
            $url  = preg_replace('#^(http://[^/:]+):443(?=/|$)#i', '$1', $url);
            $port = (int) (parse_url($url, PHP_URL_PORT) ?: 80);

            return $this->fetch($url, [
                CURLOPT_CONNECT_TO => [$host . ':' . $port . ':' . $route['server']],
                CURLOPT_HTTPHEADER => ['Accept: text/html, application/xhtml+xml, application/rss+xml, application/atom+xml, application/xml;q=0.9, */*;q=0.5', 'X-Forwarded-Proto: https', 'X-Forwarded-Port: 443'],
            ]);
        }

        return $this->fetch($url, [CURLOPT_CONNECT_TO => [$host . ':' . $port . ':' . $route['server']]]);
    }

    /**
     * Whether a response only sends the same page to https: the server wants https, and was asked over http.
     */
    private function backToHttps(string $url, array $response): bool
    {
        if (!\in_array($response['status'], [301, 302, 303, 307, 308], true) || $response['location'] === '') {
            return false;
        }

        $to   = $this->absolute($response['location'], $url);
        $same = static fn (string $address) => strtolower((string) parse_url($address, PHP_URL_HOST)) . (parse_url($address, PHP_URL_PATH) ?: '/') . '?' . parse_url($address, PHP_URL_QUERY);

        return str_starts_with(strtolower($to), 'https:') && $same($to) === $same($url);
    }

    /**
     * @param  array<int, mixed>  $route  How to reach the server: CURLOPT_RESOLVE or CURLOPT_CONNECT_TO
     *
     * @return  array{status: int, type: string, charset: string, body: string, location: string, why: string}
     */
    private function fetch(string $url, array $route): array
    {
        $body    = '';
        $headers = [];
        $curl    = curl_init($url);

        curl_setopt_array($curl, $route + [
            CURLOPT_HTTPGET        => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS | CURLPROTO_HTTP,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_ENCODING       => '',
            // The usual form for crawlers: says who reads, and where to find out more
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; AskTheSite/0.4; +' . rtrim(Uri::root(), '/') . ')',
            CURLOPT_HTTPHEADER     => ['Accept: text/html, application/xhtml+xml, application/rss+xml, application/atom+xml, application/xml;q=0.9, */*;q=0.5'],
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$headers): int {
                if (preg_match('/^([a-z-]+):\s*(.*)$/i', trim($line), $match)) {
                    $headers[strtolower($match[1])] = $match[2];
                }

                return \strlen($line);
            },
            CURLOPT_WRITEFUNCTION  => static function ($curl, string $data) use (&$body): int {
                $body .= $data;

                return \strlen($body) > self::MAX_BYTES ? 0 : \strlen($data);
            },
        ]);

        curl_exec($curl);

        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error  = $status ? '' : curl_error($curl);

        curl_close($curl);

        return [
            'status'   => \strlen($body) > self::MAX_BYTES ? 0 : $status,
            'type'     => strtolower(trim(explode(';', $headers['content-type'] ?? '')[0])),
            'charset'  => preg_match('/charset\s*=\s*["\']?([a-z0-9_-]+)/i', $headers['content-type'] ?? '', $match) ? $match[1] : '',
            'body'     => $body,
            'location' => $headers['location'] ?? '',
            'why'      => \strlen($body) > self::MAX_BYTES ? 'size' : ($status ? '' : 'connect:' . mb_substr($error, 0, 200)),
        ];
    }

    /**
     * Whether a host is this site's own: the host of this request, or of the last request a
     * scheduled task run from the command line remembers.
     */
    private static function isOwnHost(string $host): bool
    {
        if (!str_starts_with(PHP_SAPI, 'cli')) {
            try {
                return strcasecmp($host, (string) Uri::getInstance(Uri::root())->getHost()) === 0;
            } catch (\Throwable) {
                // Fall back on what was remembered
            }
        }

        try {
            $db  = Factory::getContainer()->get(DatabaseInterface::class);
            $url = (string) ((new State($db))->get('site:url')['url'] ?? '');
        } catch (\Throwable) {
            return false;
        }

        return $url !== '' && strcasecmp($host, (string) parse_url($url, PHP_URL_HOST)) === 0;
    }

    /**
     * Ways to the web server on this machine: the address the administrator gave, where this
     * request came in (remembered for scheduled tasks run from the command line), the loopback
     * address; each over https and, for a server behind a proxy that ends https, over plain http.
     *
     * @return  array<int, array{scheme: string, server: string}>
     */
    private static function localRoutes(string $url): array
    {
        $https  = str_starts_with(strtolower($url), 'https:');
        $routes = [];

        try {
            $db    = Factory::getContainer()->get(DatabaseInterface::class);
            $given = trim((string) \Joomla\CMS\Component\ComponentHelper::getParams('com_mcpask')->get('own_address', ''));

            // The administrator's word first: "http://10.0.0.5:8080" and the like
            if (preg_match('#^(https?)://([^/\s]+?)(?::(\d+))?/?$#i', $given, $match)) {
                $routes[] = ['scheme' => strtolower($match[1]), 'server' => self::bracket(trim($match[2], '[]')) . ':' . ($match[3] ?? (strtolower($match[1]) === 'https' ? 443 : 80))];
            }

            $state = new State($db);
            $known = $state->get('site:server');
            $here  = isset($_SERVER['SERVER_ADDR'], $_SERVER['SERVER_PORT']) && !str_starts_with(PHP_SAPI, 'cli')
                ? self::bracket((string) $_SERVER['SERVER_ADDR']) . ':' . (int) $_SERVER['SERVER_PORT'] : '';

            if ($here !== '' && ($known['server'] ?? '') !== $here) {
                $state->set('site:server', ['server' => $here]);
            }

            if ($server = $here ?: (string) ($known['server'] ?? '')) {
                // As the request came in: https when it came to the usual port of https
                $routes[] = ['scheme' => str_ends_with($server, ':443') ? 'https' : 'http', 'server' => $server];
            }
        } catch (\Throwable) {
            // No database: the loopback address only
        }

        foreach (['127.0.0.1', '[::1]'] as $loopback) {
            if ($https) {
                $routes[] = ['scheme' => 'https', 'server' => $loopback . ':443'];
            }

            $routes[] = ['scheme' => 'http', 'server' => $loopback . ':80'];
        }

        return array_values(array_unique($routes, SORT_REGULAR));
    }

    private static function remembered(string $name): array
    {
        try {
            return (new State(Factory::getContainer()->get(DatabaseInterface::class)))->get($name) ?? [];
        } catch (\Throwable) {
            return [];
        }
    }

    private static function remember(string $name, array $value): void
    {
        try {
            (new State(Factory::getContainer()->get(DatabaseInterface::class)))->set($name, $value);
        } catch (\Throwable) {
            // Found again next time
        }
    }

    private static function bracket(string $ip): string
    {
        return str_contains($ip, ':') && !str_starts_with($ip, '[') ? '[' . $ip . ']' : $ip;
    }
}
