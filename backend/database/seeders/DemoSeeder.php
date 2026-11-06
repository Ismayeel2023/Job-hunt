<?php

namespace Database\Seeders;

use App\Enums\PipelineStatus;
use App\Enums\StatusEventSource;
use App\Models\Application;
use App\Models\Company;
use App\Models\Email;
use App\Models\InterviewQuestion;
use App\Models\InterviewRound;
use App\Models\Job;
use App\Models\ResumeVersion;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Fictional sample data for trying the UI. Run it with
 * `php artisan db:seed --class=DemoSeeder` and remove it with `php artisan jobhunt:demo-clear`.
 */
class DemoSeeder extends Seeder
{
    private const COMPANIES = [
        'northwind' => ['Northwind Labs', 'Fintech', 'Bengaluru'],
        'quillbyte' => ['Quillbyte', 'SaaS', 'Remote'],
        'harbor' => ['Harbor & Pine', 'E-commerce', 'Chennai'],
        'tessera' => ['Tessera Systems', 'Consulting', 'Hyderabad'],
        'orbitline' => ['Orbitline', 'Logistics', 'Pune'],
        'monsoon' => ['Monsoon Data', 'Analytics', 'Bengaluru'],
        'kestrel' => ['Kestrel Health', 'Healthtech', 'Gurugram'],
    ];

    private const GMAIL_ACCOUNTS = ['demo.personal@example.com', 'demo.careers@example.com'];

    private const SAMPLE_MARKER = 'DEMO SAMPLE';

    private const SAMPLE_RESUME = <<<'TEX'
        % PLACEHOLDER: DEMO SAMPLE. Replace with the real base resume LaTeX.
        \documentclass[11pt]{article}
        \usepackage[margin=0.8in]{geometry}
        \begin{document}

        \begin{center}
          {\LARGE \textbf{Sample Candidate}} \\
          sample@example.com
        \end{center}

        \section*{Summary}
        Backend developer who builds and maintains web applications with PHP and SQL.

        \section*{Skills}
        PHP, Laravel, MySQL, REST APIs, Git, Linux

        \section*{Experience}
        \textbf{Software Developer}, Sample Company \hfill 2023--Present
        \begin{itemize}
          \item Built internal REST APIs used by three product teams.
          \item Reduced report generation time by tuning slow SQL queries.
          \item Maintained CI pipelines and deployment scripts.
        \end{itemize}

        \section*{Projects}
        \textbf{Job Tracker} \hfill Laravel, React
        \begin{itemize}
          \item Built a personal application tracker with a token-protected API.
        \end{itemize}

        \end{document}
        TEX;

    public function run(): void
    {
        if (Company::where('name', self::COMPANIES['northwind'][0])->exists()) {
            $this->command?->warn('Demo data already exists. Run php artisan jobhunt:demo-clear first to recreate it.');

            return;
        }

        $this->seedBaseResume();

        $companies = collect(self::COMPANIES)->map(fn (array $company, string $key) => Company::factory()->create([
            'name' => $company[0],
            'industry' => $company[1],
            'location' => $company[2],
            'website' => "https://{$key}.example",
            'logo_url' => null,
            'notes' => null,
        ]));

        foreach ($this->jobs() as $index => $spec) {
            $job = Job::factory()->for($companies[$spec['company']])->create([
                'title' => $spec['title'],
                'platform' => $spec['platform'],
                'url' => "https://jobs.example/demo/{$index}",
                'location' => $spec['location'],
                'experience_range' => $spec['experience'],
                'salary_text' => $spec['salary'],
                'jd_text' => $this->jobDescription($spec),
                'must_have_skills' => $spec['must'],
                'nice_to_have_skills' => $spec['nice'],
                'match_score' => $spec['score'],
                'matched_skills' => $spec['matched'],
                'gaps' => $spec['gaps'],
                'found_at' => now()->subDays(($index % 9) + 1),
                'status' => $spec['status'] ?? PipelineStatus::Found,
            ]);

            if (isset($spec['application'])) {
                $this->seedApplication($job, $spec['application'], $spec);
            }
        }

        Email::factory()->create([
            'gmail_account' => self::GMAIL_ACCOUNTS[0],
            'from' => 'careers@orbitline.example',
            'subject' => 'Thanks for your interest in Orbitline',
            'snippet' => 'We have received your profile and will get back to you if there is a fit.',
        ]);

        $this->seedInterviewPrep($companies);
    }

    /**
     * Remove everything run() created. The base resume goes back to the placeholder only if it is still the sample.
     */
    public static function clear(): void
    {
        $companies = Company::whereIn('name', array_column(self::COMPANIES, 0));

        ResumeVersion::whereIn('job_id', Job::whereIn('company_id', (clone $companies)->select('id'))->select('id'))->delete();
        Email::whereIn('gmail_account', self::GMAIL_ACCOUNTS)->delete();
        $companies->get()->each->delete();

        ResumeVersion::where('is_base', true)
            ->where('tex_source', 'like', '%'.self::SAMPLE_MARKER.'%')
            ->update(['tex_source' => DatabaseSeeder::BASE_PLACEHOLDER, 'pdf_path' => null]);

        $disk = Storage::disk('resumes');
        $disk->delete(array_filter($disk->files(), fn (string $file) => str_starts_with($file, 'demo-')));
    }

    /**
     * Only a resume that is still the placeholder is swapped for the sample, so a real one is never touched.
     */
    private function seedBaseResume(): void
    {
        $base = ResumeVersion::where('is_base', true)->latest('id')->first();

        if ($base && str_contains($base->tex_source, 'PLACEHOLDER')) {
            $base->update([
                'tex_source' => self::SAMPLE_RESUME,
                'pdf_path' => $this->writePdf('demo-base.pdf', ['Sample Candidate', 'Base resume (demo sample)']),
                'keywords_covered' => ['PHP', 'Laravel', 'MySQL', 'REST APIs', 'Git', 'Linux'],
            ]);
        }
    }

    private function seedApplication(Job $job, array $spec, array $jobSpec): void
    {
        $appliedAt = $spec['applied_at'];
        $path = $spec['path'];
        $status = end($path);

        $resume = isset($spec['tailor'])
            ? $this->seedTailoredResume($job, $spec['tailor'])
            : ResumeVersion::where('is_base', true)->latest('id')->first();

        $application = Application::factory()->for($job)->create([
            'resume_version_id' => $resume?->id,
            'applied_at' => $appliedAt,
            'applied_via' => $spec['via'],
            'cover_note' => $spec['cover_note'] ?? null,
            'screening_answers' => [
                ['question' => 'Notice period?', 'answer' => '30 days'],
                ['question' => 'Years of experience with '.$jobSpec['must'][0].'?', 'answer' => '3'],
            ],
            'status' => $status,
            'next_step' => $spec['next_step'][0] ?? null,
            'next_step_at' => isset($spec['next_step']) ? now()->addHours($spec['next_step'][1]) : null,
        ]);

        $job->update(['status' => $status]);

        $previous = null;

        foreach ($path as $step => $stepStatus) {
            $happenedAt = min($appliedAt->addDays($step * 3), now()->subHours(count($path) - $step)->toImmutable());
            $gmail = $spec['gmail'][$stepStatus->value] ?? null;

            $email = $gmail ? Email::factory()->create([
                'gmail_account' => self::GMAIL_ACCOUNTS[$step % 2],
                'from' => $gmail[0],
                'subject' => $gmail[1],
                'snippet' => $gmail[2],
                'received_at' => $happenedAt,
                'matched_application_id' => $application->id,
                'classification' => $stepStatus->value,
                'processed' => true,
            ]) : null;

            $application->statusEvents()->create([
                'from_status' => $previous,
                'to_status' => $stepStatus,
                'source' => $email ? StatusEventSource::Gmail : ($step === 0 ? StatusEventSource::Claude : StatusEventSource::Manual),
                'evidence' => $gmail ? 'Matched from an email: '.$gmail[1] : null,
                'email_id' => $email?->id,
                'happened_at' => $happenedAt,
            ]);

            $previous = $stepStatus;
        }
    }

    private function seedTailoredResume(Job $job, array $tailor): ResumeVersion
    {
        $tex = str_replace(
            [
                'Backend developer who builds and maintains web applications with PHP and SQL.',
                'PHP, Laravel, MySQL, REST APIs, Git, Linux',
                '  \item Built internal REST APIs used by three product teams.',
                '  \item Maintained CI pipelines and deployment scripts.'."\n",
            ],
            [
                $tailor['summary'],
                $tailor['skills'],
                '  \item Built internal REST APIs used by three product teams.'."\n".'  \item '.$tailor['bullet'],
                '',
            ],
            self::SAMPLE_RESUME,
        );

        return ResumeVersion::factory()->for($job)->create([
            'label' => 'Tailored for '.$job->company->name,
            'tex_source' => $tex,
            'pdf_path' => $this->writePdf("demo-job-{$job->id}.pdf", [
                'Sample Candidate',
                'Tailored for '.$job->company->name.': '.$job->title,
                $tailor['summary'],
            ]),
            'keywords_covered' => $tailor['keywords'],
            'changes_summary' => [
                'added' => [$tailor['bullet'], 'Skills: '.implode(', ', $tailor['keywords'])],
                'removed' => ['CI pipelines bullet, which this role does not ask for'],
                'reordered' => ['Moved the skills this role lists first to the front'],
            ],
        ]);
    }

    private function jobDescription(array $spec): string
    {
        return implode("\n\n", [
            "We are looking for a {$spec['title']} to join our {$spec['location']} team. You will own services end to end, from design through to production support, and work closely with product and design.",
            "What you will do\n- Design, build and operate backend services\n- Review code and raise the bar for the team\n- Investigate production issues and fix the root cause",
            'Must have: '.implode(', ', $spec['must']).'.',
            'Nice to have: '.implode(', ', $spec['nice']).'.',
            'This is a fictional job description created as demo data.',
        ]);
    }

    /**
     * A minimal, valid one-page PDF, so the embedded viewer has something real to show.
     */
    private function writePdf(string $name, array $lines): string
    {
        $escape = fn (string $text) => str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
        $text = 'BT /F1 13 Tf 60 780 Td 18 TL ';

        foreach ($lines as $line) {
            $text .= '('.$escape($line).') Tj T* ';
        }

        $text .= '() Tj T* (Demo PDF created by DemoSeeder.) Tj ET';

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.strlen($text)." >>\nstream\n{$text}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n{$object}\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= 'xref'."\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        $pdf .= 'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";

        Storage::disk('resumes')->put($name, $pdf);

        return $name;
    }

    /**
     * An apply date on a Saturday or Sunday, whole weeks back.
     */
    private function weekend(int $weeksAgo, bool $sunday = false): CarbonImmutable
    {
        return CarbonImmutable::now(config('jobhunt.timezone'))
            ->startOfWeek(Carbon::MONDAY)
            ->subWeeks($weeksAgo)
            ->addDays($sunday ? 6 : 5)
            ->setTime(10, 30)
            ->setTimezone(config('app.timezone'));
    }

    private function jobs(): array
    {
        $tailor = fn (string $summary, string $skills, string $bullet, array $keywords) => compact('summary', 'skills', 'bullet', 'keywords');

        return [
            [
                'company' => 'northwind', 'title' => 'Senior Backend Engineer', 'platform' => 'linkedin', 'location' => 'Bengaluru',
                'experience' => '3-6 yrs', 'salary' => '24-32 LPA', 'score' => 91,
                'must' => ['PHP', 'Laravel', 'MySQL'], 'nice' => ['Redis', 'AWS'], 'matched' => ['PHP', 'Laravel', 'MySQL', 'Redis'], 'gaps' => ['AWS'],
                'application' => [
                    'applied_at' => $this->weekend(4), 'via' => 'Easy Apply',
                    'path' => [PipelineStatus::Applied, PipelineStatus::OnlineTest, PipelineStatus::Interview, PipelineStatus::Hr, PipelineStatus::Offer],
                    'next_step' => ['Reply to the offer letter', 50],
                    'cover_note' => 'I have built and operated Laravel services for three product teams, and your payments work is close to what I do today.',
                    'tailor' => $tailor('Backend engineer building reliable Laravel and MySQL services for fintech-style workloads.', 'Laravel, PHP, MySQL, Redis, REST APIs, Git, Linux', 'Added Redis caching that cut API response times on the busiest endpoints.', ['Laravel', 'MySQL', 'Redis', 'REST APIs']),
                    'gmail' => [
                        'online_test' => ['talent@northwind.example', 'Your Northwind Labs online assessment', 'Please complete the 90-minute assessment within five days using the link below.'],
                        'interview' => ['talent@northwind.example', 'Technical interview invitation', 'Congratulations on clearing the assessment. We would like to schedule a technical round.'],
                        'hr' => ['people@northwind.example', 'Next step: conversation with HR', 'The panel enjoyed speaking with you. Our HR partner will reach out about next steps.'],
                        'offer' => ['people@northwind.example', 'Offer letter from Northwind Labs', 'We are delighted to share your offer letter. Please review and respond by the date mentioned.'],
                    ],
                ],
            ],
            [
                'company' => 'quillbyte', 'title' => 'Full Stack Developer', 'platform' => 'wellfound', 'location' => 'Remote',
                'experience' => '2-5 yrs', 'salary' => '18-26 LPA', 'score' => 84,
                'must' => ['React', 'TypeScript', 'Laravel'], 'nice' => ['Tailwind', 'Docker'], 'matched' => ['React', 'Laravel', 'Tailwind'], 'gaps' => ['Docker'],
                'application' => [
                    'applied_at' => $this->weekend(2, true), 'via' => 'Company portal',
                    'path' => [PipelineStatus::Applied, PipelineStatus::OnlineTest, PipelineStatus::Interview],
                    'next_step' => ['Technical round with the platform team', 30],
                    'tailor' => $tailor('Full stack developer shipping Laravel APIs with React and TypeScript frontends.', 'React, TypeScript, Laravel, PHP, Tailwind, Git', 'Built a React and TypeScript admin panel on top of existing Laravel APIs.', ['React', 'TypeScript', 'Laravel', 'Tailwind']),
                    'gmail' => [
                        'interview' => ['hiring@quillbyte.example', 'Interview slot confirmed', 'Your technical round with the platform team is confirmed. Meeting details are attached.'],
                    ],
                ],
            ],
            [
                'company' => 'harbor', 'title' => 'Software Engineer II', 'platform' => 'naukri', 'location' => 'Chennai',
                'experience' => '2-4 yrs', 'salary' => '14-20 LPA', 'score' => 76,
                'must' => ['PHP', 'MySQL', 'REST APIs'], 'nice' => ['Elasticsearch'], 'matched' => ['PHP', 'MySQL', 'REST APIs'], 'gaps' => ['Elasticsearch'],
                'application' => [
                    'applied_at' => $this->weekend(1), 'via' => 'Naukri apply',
                    'path' => [PipelineStatus::Applied, PipelineStatus::OnlineTest],
                    'next_step' => ['Finish the HackerRank test', 20],
                    'tailor' => $tailor('Software engineer building REST APIs and e-commerce backends with PHP and MySQL.', 'PHP, MySQL, REST APIs, Laravel, Git, Linux', 'Designed REST endpoints for order and inventory data.', ['PHP', 'MySQL', 'REST APIs']),
                    'gmail' => [
                        'online_test' => ['no-reply@harbor.example', 'Complete your coding test', 'Your coding test link is ready. It takes about 75 minutes to complete.'],
                    ],
                ],
            ],
            [
                'company' => 'tessera', 'title' => 'Backend Developer', 'platform' => 'instahyre', 'location' => 'Hyderabad',
                'experience' => '3-5 yrs', 'salary' => '16-22 LPA', 'score' => 71,
                'must' => ['Laravel', 'PostgreSQL'], 'nice' => ['Kafka'], 'matched' => ['Laravel'], 'gaps' => ['PostgreSQL', 'Kafka'],
                'application' => [
                    'applied_at' => $this->weekend(3, true), 'via' => 'Instahyre',
                    'path' => [PipelineStatus::Applied, PipelineStatus::Interview, PipelineStatus::Hr],
                    'next_step' => ['Share salary expectations with HR', -6],
                ],
            ],
            [
                'company' => 'orbitline', 'title' => 'PHP Developer', 'platform' => 'indeed', 'location' => 'Pune',
                'experience' => '1-3 yrs', 'salary' => null, 'score' => 68,
                'must' => ['PHP', 'MySQL'], 'nice' => ['Laravel'], 'matched' => ['PHP', 'MySQL', 'Laravel'], 'gaps' => [],
                'application' => [
                    'applied_at' => now()->subHours(20)->toImmutable(), 'via' => 'Indeed apply',
                    'path' => [PipelineStatus::Applied],
                ],
            ],
            [
                'company' => 'monsoon', 'title' => 'Data Platform Engineer', 'platform' => 'foundit', 'location' => 'Bengaluru',
                'experience' => '3-6 yrs', 'salary' => '20-28 LPA', 'score' => 58,
                'must' => ['Python', 'SQL', 'Airflow'], 'nice' => ['Spark'], 'matched' => ['SQL'], 'gaps' => ['Python', 'Airflow', 'Spark'],
                'application' => [
                    'applied_at' => $this->weekend(1, true), 'via' => 'Foundit',
                    'path' => [PipelineStatus::Applied],
                    'next_step' => ['Follow up with the recruiter', 96],
                ],
            ],
            [
                'company' => 'kestrel', 'title' => 'Software Engineer', 'platform' => 'career_page', 'location' => 'Gurugram',
                'experience' => '2-4 yrs', 'salary' => '15-19 LPA', 'score' => 63,
                'must' => ['Laravel', 'Vue'], 'nice' => ['HL7'], 'matched' => ['Laravel'], 'gaps' => ['Vue', 'HL7'],
                'application' => [
                    'applied_at' => $this->weekend(4, true), 'via' => 'Company portal',
                    'path' => [PipelineStatus::Applied, PipelineStatus::OnlineTest, PipelineStatus::Interview, PipelineStatus::Rejected],
                    'gmail' => [
                        'rejected' => ['careers@kestrel.example', 'Update on your application', 'After careful consideration we have decided to move ahead with other candidates.'],
                    ],
                ],
            ],
            [
                'company' => 'monsoon', 'title' => 'Analytics Engineer', 'platform' => 'linkedin', 'location' => 'Bengaluru',
                'experience' => '2-4 yrs', 'salary' => null, 'score' => 44,
                'must' => ['dbt', 'SQL'], 'nice' => ['Looker'], 'matched' => ['SQL'], 'gaps' => ['dbt', 'Looker'],
                'application' => [
                    'applied_at' => $this->weekend(3), 'via' => 'Easy Apply',
                    'path' => [PipelineStatus::Applied, PipelineStatus::Rejected],
                ],
            ],
            [
                'company' => 'orbitline', 'title' => 'Backend Engineer, Routing', 'platform' => 'naukri', 'location' => 'Pune',
                'experience' => '3-5 yrs', 'salary' => '18-24 LPA', 'score' => 66,
                'must' => ['PHP', 'Redis'], 'nice' => ['Go'], 'matched' => ['PHP', 'Redis'], 'gaps' => ['Go'],
                'application' => [
                    'applied_at' => $this->weekend(5), 'via' => 'Naukri apply',
                    'path' => [PipelineStatus::Applied, PipelineStatus::Ghosted],
                ],
            ],
            [
                'company' => 'northwind', 'title' => 'Staff Platform Engineer', 'platform' => 'linkedin', 'location' => 'Bengaluru',
                'experience' => '4-7 yrs', 'salary' => '30-40 LPA', 'score' => 88, 'status' => PipelineStatus::Queued,
                'must' => ['Laravel', 'MySQL', 'AWS'], 'nice' => ['Terraform'], 'matched' => ['Laravel', 'MySQL'], 'gaps' => ['AWS', 'Terraform'],
            ],
            [
                'company' => 'quillbyte', 'title' => 'Senior Laravel Developer', 'platform' => 'wellfound', 'location' => 'Remote',
                'experience' => '3-6 yrs', 'salary' => '20-30 LPA', 'score' => 86,
                'must' => ['Laravel', 'PHP'], 'nice' => ['Inertia'], 'matched' => ['Laravel', 'PHP', 'Inertia'], 'gaps' => [],
            ],
            [
                'company' => 'harbor', 'title' => 'API Engineer', 'platform' => 'indeed', 'location' => 'Chennai',
                'experience' => '2-5 yrs', 'salary' => '16-22 LPA', 'score' => 79, 'status' => PipelineStatus::Queued,
                'must' => ['REST APIs', 'PHP'], 'nice' => ['GraphQL'], 'matched' => ['REST APIs', 'PHP'], 'gaps' => ['GraphQL'],
            ],
            [
                'company' => 'tessera', 'title' => 'Full Stack Engineer', 'platform' => 'instahyre', 'location' => 'Hyderabad',
                'experience' => '2-4 yrs', 'salary' => '14-18 LPA', 'score' => 73,
                'must' => ['React', 'Laravel'], 'nice' => ['Docker'], 'matched' => ['React', 'Laravel'], 'gaps' => ['Docker'],
            ],
            [
                'company' => 'kestrel', 'title' => 'Backend Engineer, Records', 'platform' => 'career_page', 'location' => 'Gurugram',
                'experience' => '3-5 yrs', 'salary' => null, 'score' => 61,
                'must' => ['PHP', 'PostgreSQL'], 'nice' => ['FHIR'], 'matched' => ['PHP'], 'gaps' => ['PostgreSQL', 'FHIR'],
            ],
            [
                'company' => 'monsoon', 'title' => 'Machine Learning Engineer', 'platform' => 'foundit', 'location' => 'Bengaluru',
                'experience' => '3-6 yrs', 'salary' => '25-35 LPA', 'score' => 22,
                'must' => ['Python', 'PyTorch'], 'nice' => ['Kubernetes'], 'matched' => [], 'gaps' => ['Python', 'PyTorch', 'Kubernetes'],
            ],
            [
                'company' => 'orbitline', 'title' => 'Developer, Internal Tools', 'platform' => 'other', 'location' => 'Pune',
                'experience' => '1-3 yrs', 'salary' => null, 'score' => null,
                'must' => ['PHP'], 'nice' => [], 'matched' => [], 'gaps' => [],
            ],
        ];
    }

    private function seedInterviewPrep($companies): void
    {
        $rounds = [
            'northwind' => [
                ['Online assessment', 'aptitude', 'Aptitude section followed by two coding problems on a hosted platform.', '90 min'],
                ['DSA and backend', 'coding', 'Live coding with a focus on hash maps, SQL and API design.', '60 min'],
                ['System design', 'technical', 'Design a small payments or ledger service and discuss the trade-offs.', '60 min'],
                ['Hiring manager', 'managerial', 'Past projects, ownership and how you handle disagreement.', '45 min'],
                ['HR discussion', 'hr', 'Compensation, notice period and joining date.', '30 min'],
            ],
            'quillbyte' => [
                ['Take-home', 'coding', 'A small Laravel and React feature, with a walkthrough afterwards.', '4 hours'],
                ['Technical deep dive', 'technical', 'Review of the take-home plus framework internals.', '60 min'],
                ['Culture chat', 'hr', 'How you work on a remote team.', '30 min'],
            ],
        ];

        foreach ($rounds as $company => $companyRounds) {
            foreach ($companyRounds as $round) {
                InterviewRound::factory()->for($companies[$company])->create([
                    'round_name' => $round[0],
                    'round_type' => $round[1],
                    'description' => $round[2],
                    'typical_duration' => $round[3],
                    'source_url' => 'https://example.com/interview-notes/'.$company,
                ]);
            }
        }

        $questions = [
            'northwind' => [
                ['coding', 'SQL', 'Find the second highest salary in each department.', 'medium', 2026, 'Use a window function: DENSE_RANK() partitioned by department, ordered by salary descending, then keep rank 2. Mention that DENSE_RANK handles ties, and how you would do it without window functions on an older MySQL.', 'Tuned slow report queries'],
                ['coding', 'SQL', 'What is the difference between WHERE and HAVING?', 'easy', 2025, 'WHERE filters rows before grouping and HAVING filters groups after aggregation. Prefer WHERE where possible because it reduces the rows being grouped.', null],
                ['coding', 'Data structures', 'Return the first non-repeating character in a string.', 'easy', 2026, 'Count characters in a hash map in one pass, then scan again for the first with a count of one. O(n) time, and O(1) extra space for a fixed alphabet.', null],
                ['technical', 'System design', 'How would you design an idempotent payment API?', 'hard', 2026, 'Require a client-generated idempotency key, store it with a unique constraint alongside the request hash and response, and return the stored response on retry. Wrap the charge and the record in a transaction, and explain what happens if the provider call times out.', 'Internal REST APIs'],
                ['technical', 'Laravel', 'What happens to a request between index.php and your controller?', 'medium', 2025, 'The kernel boots service providers, runs global middleware, the router matches the route, runs route middleware, resolves model bindings and dependencies, then calls the controller. The response goes back through middleware in reverse.', 'Job Tracker project'],
                ['technical', 'Laravel', 'How do you find and fix N+1 queries?', 'medium', 2026, 'Detect them with Model::preventLazyLoading in development or a query log, fix with eager loading through with(), and use withCount when only a count is needed.', 'Tuned slow report queries'],
                ['managerial', 'Behavioural', 'Tell me about a time you disagreed with a technical decision.', 'medium', 2025, 'Use situation, action, result. Describe bringing data rather than opinion, agreeing on a small experiment, and committing to the outcome either way.', null],
                ['hr', 'Behavioural', 'Why do you want to leave your current role?', 'easy', 2026, 'Keep it about what you are moving toward: larger systems and more ownership. Say nothing negative about your current employer.', null],
            ],
            'quillbyte' => [
                ['coding', 'React', 'When would you reach for useMemo, and when would you avoid it?', 'medium', 2026, 'Use it for genuinely expensive calculations or stable references passed to memoized children. Avoid it by default, since it adds complexity and only helps once you have measured a problem.', 'Job Tracker project'],
                ['technical', 'Laravel', 'How does Inertia differ from a separate SPA and API?', 'medium', 2026, 'Inertia keeps server-side routing and controllers but renders React pages from props, so you skip building a client router and a public API. The trade-off is tighter coupling to the backend.', 'Job Tracker project'],
                ['hr', 'Remote work', 'How do you stay visible on a remote team?', 'easy', 2025, 'Write things down, post short progress notes, and raise blockers early rather than waiting for a meeting.', null],
            ],
        ];

        foreach ($questions as $company => $companyQuestions) {
            foreach ($companyQuestions as $index => $question) {
                InterviewQuestion::factory()->for($companies[$company])->create([
                    'round_type' => $question[0],
                    'topic' => $question[1],
                    'question' => $question[2],
                    'difficulty' => $question[3],
                    'year_reported' => $question[4],
                    'source_url' => "https://example.com/interview-notes/{$company}/{$index}",
                    'source_name' => 'Sample forum',
                    'suggested_answer' => $question[5],
                    'answer_based_on' => $question[6] ? ['projects' => [$question[6]], 'experience' => []] : null,
                    'practiced' => $index < 2,
                    'my_notes' => $index === 0 ? 'Practise writing this without looking up the syntax.' : null,
                ]);
            }
        }
    }
}
