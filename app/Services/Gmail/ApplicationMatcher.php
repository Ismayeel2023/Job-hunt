<?php

namespace App\Services\Gmail;

use App\Models\Company;
use Illuminate\Support\Collection;

/**
 * Links an email to an application when there is exactly one sensible answer, and otherwise leaves it for Claude.
 */
class ApplicationMatcher
{
    /** @var Collection<int, array{domain: ?string, name: string, applications: Collection}> */
    private Collection $companies;

    public function __construct()
    {
        $this->companies = Company::has('applications')
            ->with('applications.job:id,title')
            ->get(['id', 'name', 'website'])
            ->map(fn (Company $company) => [
                'domain' => MailFilter::domainOf($company->website),
                'name' => $company->name,
                'applications' => $company->applications,
            ]);
    }

    public function match(string $fromAddress, string $fromName, string $subject, string $snippet): ?int
    {
        $domain = strtolower(substr(strrchr($fromAddress, '@') ?: '', 1));
        $text = "{$fromName} {$subject} {$snippet}";

        $byDomain = $this->companies->filter(fn (array $company) => $company['domain'] && $domain
            && ($domain === $company['domain'] || str_ends_with($domain, '.'.$company['domain'])));

        $byName = $this->companies->filter(fn (array $company) => mb_strlen($company['name']) >= 3
            && preg_match('/(?<![\p{L}\p{N}])'.preg_quote($company['name'], '/').'(?![\p{L}\p{N}])/iu', $text));

        $candidates = $byDomain->count() === 1 ? $byDomain : $byName;

        if ($candidates->count() !== 1) {
            return null;
        }

        $applications = $candidates->first()['applications'];

        if ($applications->count() === 1) {
            return $applications->first()->id;
        }

        // Several applications at one company: only link when the email names exactly one of the job titles.
        $byTitle = $applications->filter(fn ($application) => stripos("{$subject} {$snippet}", $application->job->title) !== false);

        return $byTitle->count() === 1 ? $byTitle->first()->id : null;
    }
}
