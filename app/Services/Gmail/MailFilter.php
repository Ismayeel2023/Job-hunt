<?php

namespace App\Services\Gmail;

use App\Models\Company;

/**
 * Decides which mail counts as job-related, both as a Gmail search query and as a local re-check.
 */
class MailFilter
{
    /**
     * @param  list<string>  $domains
     * @param  list<string>  $words
     */
    public function __construct(
        public readonly array $domains,
        public readonly array $words,
        public readonly int $days,
    ) {
    }

    public static function current(): self
    {
        $config = config('jobhunt.mail');

        $companyDomains = Company::has('applications')->pluck('website')
            ->map(fn (?string $website) => self::domainOf($website))
            ->filter()
            ->reject(fn (string $domain) => in_array($domain, $config['ignored_domains'], true));

        return new self(
            collect($config['platform_domains'])->merge($companyDomains)->unique()->values()->all(),
            $config['subject_words'],
            $config['lookback_days'],
        );
    }

    /**
     * The host of a URL or bare domain, without "www.", or null if it is not a plain domain name.
     */
    public static function domainOf(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        $host = strtolower(parse_url(str_contains($value, '://') ? $value : "https://{$value}", PHP_URL_HOST) ?? '');
        $host = preg_replace('/^www\./', '', $host);

        return preg_match('/^[a-z0-9-]+(\.[a-z0-9-]+)+$/', $host) ? $host : null;
    }

    /**
     * Gmail search queries. Braces mean "any of these". Long sender lists are split so no query gets too long.
     *
     * @return list<string>
     */
    public function queries(): array
    {
        $subjects = array_map(fn (string $word) => "subject:{$word}", $this->words);
        $senders = array_map(fn (string $domain) => "from:{$domain}", $this->domains);
        $groups = array_chunk($senders, 20) ?: [[]];
        $groups[0] = [...$groups[0], ...$subjects];

        return array_map(fn (array $terms) => "newer_than:{$this->days}d {".implode(' ', $terms).'}', $groups);
    }

    /**
     * Re-checks a message Gmail returned, so nothing outside the rules is ever stored.
     */
    public function matches(string $fromAddress, string $subject): bool
    {
        $domain = strtolower(substr(strrchr($fromAddress, '@') ?: '', 1));

        foreach ($this->domains as $allowed) {
            if ($domain === $allowed || str_ends_with($domain, '.'.$allowed)) {
                return true;
            }
        }

        $words = implode('|', array_map(fn (string $word) => preg_quote($word, '/'), $this->words));

        return (bool) preg_match("/\\b({$words})s?\\b/i", $subject);
    }

    /**
     * @return array{0: string, 1: string} The address and display name from a From header.
     */
    public static function parseSender(string $header): array
    {
        if (preg_match('/^(.*)<([^>]+)>\s*$/', $header, $matches)) {
            return [strtolower(trim($matches[2])), trim($matches[1], " \t\"'")];
        }

        return [strtolower(trim($header)), ''];
    }
}
