# JobHunt

A local-only job application tracker for one person. Claude Code finds jobs, tailors resumes, applies and reads mail, and writes the results into this app through a token-protected API. The app runs only on `127.0.0.1`.

## Stack

- Laravel 12 (PHP 8.2, XAMPP) with Inertia, React 18, TypeScript and Tailwind 3. Breeze provides auth, with registration removed.
- SQLite at `database/database.sqlite`.
- Composer is not on the Git Bash PATH. Use `php C:/ProgramData/Composer/bin/composer.phar`.

## Running

| Task | Command |
|---|---|
| Start the app | `composer run dev`, or `npm run start` (web server on http://127.0.0.1:8000, plus the scheduler for the hourly Gmail sync) |
| Rebuild the UI after frontend changes | `npm run build` (runs `tsc` first) |
| Run the tests | `php artisan test` |
| Sync Gmail by hand | `php artisan jobhunt:sync-mail [--account=address]` |
| Remove demo data | `php artisan jobhunt:demo-clear` (and `php artisan db:seed --class=DemoSeeder` to add it back) |

The login is `JOBHUNT_USER_EMAIL` / `JOBHUNT_USER_PASSWORD` in `.env`, used the first time the database is seeded. Open the app at `127.0.0.1`, not `localhost`: Gmail sign-in and the agent API both depend on it.

## Architecture

- **Models** (`app/Models`):
  - `Company` has many `Job`s (table `jobs`).
  - `Job` has `ResumeVersion`s and one `Application` (`applications.job_id` is unique).
  - `Application` has `StatusEvent`s.
  - `Email` may link to an `Application` through `matched_application_id`.
  - `InterviewRound` and `InterviewQuestion` belong to a `Company`.
  - `GmailAccount` stores OAuth tokens, encrypted with the `encrypted` cast.
  - Laravel's own queue table is renamed to `queue_jobs` to free up `jobs`.
- **Statuses** are PHP backed enums in `app/Enums`, stored as strings:
  - `PipelineStatus` is shared by jobs, applications and events, and `stageRank()` orders the stages.
  - `JobPlatform`, `StatusEventSource` and `RoundType` cover the other fixed lists.
- **Status changes** always go through `Application::transitionTo()`. It records a `status_events` row and updates the application and its job together. Don't write `status` directly.
- **Stage reached** (`Application::stageReached()`) is the furthest stage ever reached. The UI shows Match score and Stage reached, and must never show a "guaranteed selection" or success-probability metric. A test enforces this for the UI code.
- **Time:** dates are stored in UTC. Weeks, apply days and display use IST, through `config/jobhunt.php` and `App\Support\ApplyCalendar`. The agent API converts dates that carry an offset to UTC (`AgentRequest::payload()`).
- **Apply-day rules:** in `config/jobhunt.php` and read-only in the UI. The Monday locked window is display-only in the app. Enforcement lives in the skill's Access control.

### Agent API (`/api/agent`)

- **Docs:** `docs/AGENT_API.md`, with curl examples for every endpoint.
- **Access:** `AuthenticateAgent` requires a bearer token equal to `AGENT_TOKEN` and the connection's real address to be `127.0.0.1`. It runs before route-model binding. Every request also has a Form Request in `app/Http/Requests/Agent`.
- **Re-runs are safe:**
  - Jobs upsert by `url`.
  - Companies are found or created by name.
  - Rounds and questions skip duplicates.
  - A second application for the same job returns `409`.

### Gmail sync (`app/Services/Gmail`)

- **Access:** OAuth with `gmail.readonly` only, plus PKCE. The callback refuses and revokes the connection if Google grants any other scope.
- **What's fetched:** `MailFilter` builds the Gmail search for the last 30 days (job sites, the domains of companies with applications, and subject words) and re-checks each message locally. Messages are fetched with `format=metadata` (From and Subject headers plus the snippet), and only sender, subject, snippet and date are saved. Never fetch bodies or attachments, and never widen the scope or the filter without the user asking.
- **Linking:** `ApplicationMatcher` links by sender domain, then company name. It only links when there is exactly one answer and leaves anything unclear for Claude.
- **Setup and connection:** setup steps are in `docs/GMAIL_SETUP.md`. While the Google app is in Testing, Google expires the connection after 7 days. Settings then shows Reconnect.

### UI

- Inertia pages are in `resources/js/Pages` (Dashboard, Jobs, Applications Board and Show, Resumes, Prep, Settings).
- Shared pieces are in `resources/js/Components/ui.tsx`. Colours are CSS-variable tokens defined in `resources/css/app.css` and `tailwind.config.js`, and dark mode uses the `dark` class.
- The job page (`/jobs/{id}`) shows the job description, match score, salary suggestion, tailored resumes and the Apply panel. The Apply panel polls every 2 seconds and animates the run's steps.
- Apply runs (`ApplyRun`, table `apply_runs`): the Apply button creates one, `/apply-worker` claims it with `jobhunt:apply-next` and reports progress. Only a logged-in click on the job page can approve a submit (`confirmed_at`); the agent API cannot.
- Your details (Settings): current and expected CTC, notice period and relocation, stored in `settings` through `App\Support\CandidateDetails`, plus answers remembered from earlier runs.
- Salary suggestions (`App\Support\Salary`) use only the posting's range and the user's own CTC (raise percentages in `config/jobhunt.php`), never outside salary data.
- Saves that must survive navigation (board moves, prep notes and ticks) use `window.axios`, because an Inertia visit to another URL cancels one already in flight.

## Claude Code workflow

The `job-hunt-assistant` skill (`.claude/skills/job-hunt-assistant/SKILL.md`, personal and git-ignored; `stubs/job-hunt-assistant.SKILL.md` is the blank template) holds the candidate's Profile, the base resume and the rules. Its **Access control** section is set by the user. Never edit it, never work around it, and never reveal or guess the key. Its **App sync** section says what to save to JobHunt.

| Command | What it does |
|---|---|
| `/find-jobs [prefs]` | Mode 1: search and score jobs, then upsert the shortlist to `/api/agent/jobs` |
| `/tailor <job id or url>` | Mode 2: tailor the LaTeX resume and build the PDF, then save to `/api/agent/resume-versions` |
| `/apply-worker` | Waits for **Apply with Claude** clicks on job pages, then runs Mode 3 and reports each step live through `/api/agent/apply-runs`. Submits only after the user clicks Submit on the job page |
| `/apply <job id>` | Mode 3 in the browser, after Access control: stops before submit, and records the application once the user confirms |
| `/prep <company>` | Researches public interview reports and saves rounds and questions with source and year. Answers use only Profile facts, and say "reported by candidates" |
| `/sync-mail` | Syncs Gmail, classifies unprocessed emails, updates application statuses with the email as evidence, and marks them processed. Asks before an ambiguous offer or rejection |
| `/weekly-report` | Read-only summary of this IST week from `/api/agent/summary` and `/api/agent/applications` |

- **Browser:** Claude in Chrome, in the user's own Chrome-based browser (Chrome, Brave or Edge). The user signs in to job sites themselves. Never enter passwords, create accounts or bypass CAPTCHAs.
- **Tailoring workspace:** `storage/app/private/tailoring/job-<id>/`, which is git-ignored. Uploaded PDFs live in `storage/app/resumes`, and `GET /api/agent/jobs/{id}` returns each version's absolute `pdf_file` path.

## Windows notes

These fixes are needed once per Windows machine. macOS and Linux don't need them.

- **`python3`:** Windows installs Python as `python`. A small `~/bin/python3` script that runs `python` lets the skill's key check (Step C) work in Git Bash.
- **IST time check:** Git Bash has no time-zone files, so `TZ=Asia/Kolkata date` (the skill's Step A) silently prints UTC. Fix it once from an administrator PowerShell by copying Python's `tzdata` files into Git: `Copy-Item -Recurse "$(python -c "import tzdata,os;print(os.path.dirname(tzdata.__file__))")\zoneinfo" "C:\Program Files\Git\usr\share\zoneinfo"` (run `python -m pip install tzdata` first if needed).
- **curl and ₹:** Git Bash passes inline `curl -d` text through the Windows code page, so ₹ arrives as `?`. Send JSON with `--data-binary @file` or `@-`.
- **PDF tools:** with a per-user MiKTeX install, `pdflatex` may not be on the Git Bash PATH. Call it as `"$LOCALAPPDATA/Programs/MiKTeX/miktex/bin/x64/pdflatex.exe"`. Install `pypdf` with `python -m pip install pypdf`. MiKTeX installs missing LaTeX packages on first use.
