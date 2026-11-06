# JobHunt Agent API

REST API that lets Claude Code write data into JobHunt. All routes live under `/api/agent`.

## Authentication

Every request needs both of these:

- **Source address:** the connection must come from `127.0.0.1`, otherwise the response is `403`. Use `127.0.0.1` in URLs, not `localhost`, which can resolve to `::1` and be rejected. Forwarded-for headers are ignored.
- **Token:** send `Authorization: Bearer <AGENT_TOKEN>`, using the value from `.env`. A missing or wrong token gets `401`. If `AGENT_TOKEN` is empty, every request gets `503`.

Set up your shell before running the examples (from the project root, with `php artisan serve` running):

```bash
export BASE=http://127.0.0.1:8000/api/agent
export AGENT_TOKEN="$(grep '^AGENT_TOKEN=' .env | cut -d= -f2)"
```

To rotate the token, change `AGENT_TOKEN` in `.env`. If you have run `php artisan config:cache`, run it again.

## Conventions

- Responses and errors are always JSON.
- On Windows, send JSON from a file or stdin (`--data-binary @body.json` or `--data-binary @-`). Text passed inline with `-d` loses ₹ and other non-English characters, which arrive as `?`.
- Validation errors return `422` with `{"message": "...", "errors": {"field": ["..."]}}`. Missing records return `404`.
- Dates accept any parseable format. Include an offset such as `2026-11-03T10:00:00+05:30`; a date without one is treated as UTC. Responses are always in UTC.
- List endpoints are paginated. Results are in `data`, with `total`, `current_page` and `last_page` alongside. Use `?page=` and `?per_page=` (1-200, default 50).

Allowed values:

| Field | Values |
|---|---|
| `platform` | `linkedin` `naukri` `indeed` `instahyre` `wellfound` `foundit` `career_page` `other` |
| job and application status | `found` `queued` `applied` `online_test` `interview` `hr` `offer` `rejected` `withdrawn` `ghosted` |
| status event `source` | `manual` `gmail` `claude` |
| `round_type` | `aptitude` `coding` `technical` `hr` `managerial` `group_discussion` |

## Jobs

### POST /jobs

Creates a job, or updates the existing one with the same `url`. Returns `201` if created and `200` if updated.

Required: `url`, `title`, and either `company_id` or `company` (an object with at least `name`). Fields you leave out keep their current values, so re-posting a job does not reset its `status` or `found_at`.

A company is matched by name, ignoring case, and created if there is no match. An existing company's details are not changed. The URL must match exactly, so a link with different tracking parameters counts as a new job.

Optional: `platform`, `location`, `experience_range`, `salary_text`, `jd_text`, `must_have_skills`, `nice_to_have_skills`, `match_score` (0-100), `matched_skills`, `gaps`, `found_at`, `status`.

```bash
curl -X POST "$BASE/jobs" \
  -H "Authorization: Bearer $AGENT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "url": "https://www.linkedin.com/jobs/view/123456",
    "title": "Backend Developer",
    "company": {"name": "Acme", "website": "https://acme.example", "location": "Bengaluru"},
    "platform": "linkedin",
    "location": "Bengaluru",
    "experience_range": "2-4 yrs",
    "salary_text": "12-18 LPA",
    "jd_text": "We are hiring a backend developer...",
    "must_have_skills": ["PHP", "Laravel"],
    "nice_to_have_skills": ["React"],
    "match_score": 82,
    "matched_skills": ["PHP", "Laravel"],
    "gaps": ["Kubernetes"]
  }'
```

### GET /jobs/{id}

Returns one job in full, including `jd_text`, `company`, its `resume_versions` (newest first, without the LaTeX, each with `pdf_file`, the absolute path of the PDF for uploading), and its `application` (or `null`).

```bash
curl "$BASE/jobs/1" \
  -H "Authorization: Bearer $AGENT_TOKEN"
```

### PATCH /jobs/{id}

Updates only the fields you send. Accepts the fields above, plus `company_id` but not a `company` object.

```bash
curl -X PATCH "$BASE/jobs/1" \
  -H "Authorization: Bearer $AGENT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"status": "queued", "match_score": 90}'
```

Once a job has an application, change its status with `POST /applications/{id}/status` instead. Patching the job directly does not update the application or record a status event.

### GET /jobs

Optional filters: `status`, `min_score` (jobs with no score are excluded). Sorted by highest score, then newest.

```bash
curl "$BASE/jobs?status=found&min_score=70" \
  -H "Authorization: Bearer $AGENT_TOKEN"
```

## Resume versions

### POST /resume-versions

Send as `multipart/form-data`. Returns `201`, without `tex_source` in the response.

| Field | Required | Notes |
|---|---|---|
| `job_id` | yes | The job this version was tailored for |
| `tex_source` | yes | LaTeX source. `tex_source=<file.tex` in curl reads it from a file |
| `pdf` | no | PDF up to 10 MB. Saved to `storage/app/resumes`; `pdf_path` is the filename inside that folder |
| `label` | no | Defaults to `Tailored for <job title>` |
| `keywords_covered` | no | JSON array of strings |
| `changes_summary` | no | JSON object with `added`, `removed` and `reordered`, each an array of strings |

```bash
curl -X POST "$BASE/resume-versions" \
  -H "Authorization: Bearer $AGENT_TOKEN" \
  -F "job_id=1" \
  -F "tex_source=<resume.tex" \
  -F "pdf=@resume.pdf;type=application/pdf" \
  -F 'keywords_covered=["Laravel","SQL"]' \
  -F 'changes_summary={"added":["Laravel"],"removed":["Java"],"reordered":["Skills"]}'
```

## Applications

### GET /applications

Lists applications, newest first, each with its `job` and `job.company`, `stage_reached` and `last_event_at`. Optional filter: `status`.

```bash
curl "$BASE/applications?status=interview" \
  -H "Authorization: Bearer $AGENT_TOKEN"
```

### POST /applications

Required: `job_id`. Optional: `resume_version_id` (must belong to this job or be the base resume), `applied_at` (defaults to now), `applied_via`, `cover_note`, `screening_answers`, `status` (defaults to `applied`), `next_step`, `next_step_at`.

Returns `201`, and also records the first status event and sets the job's status to match. Each job can have one application. A second attempt returns `409` with the existing `application_id`.

```bash
curl -X POST "$BASE/applications" \
  -H "Authorization: Bearer $AGENT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "job_id": 1,
    "resume_version_id": 2,
    "applied_at": "2026-11-03T10:00:00+05:30",
    "applied_via": "Easy Apply",
    "cover_note": "Short note sent with the application.",
    "screening_answers": [{"question": "Notice period?", "answer": "30 days"}],
    "next_step": "Wait for recruiter",
    "next_step_at": "2026-11-10T10:00:00+05:30"
  }'
```

### POST /applications/{id}/status

Records a status event, then updates the status on both the application and its job. Returns `201` with the event and its application.

Required: `to_status`. Sending the current status returns `422`. Optional: `source` (defaults to `claude`), `evidence`, `email_id`, `happened_at` (defaults to now), `next_step`, `next_step_at`.

```bash
curl -X POST "$BASE/applications/1/status" \
  -H "Authorization: Bearer $AGENT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "to_status": "interview",
    "source": "gmail",
    "evidence": "Recruiter invited me to a technical round.",
    "email_id": 1,
    "next_step": "Technical round",
    "next_step_at": "2026-11-07T15:00:00+05:30"
  }'
```

## Company interview data

### GET /companies

Optional `search` matches part of the name, ignoring case. Each company includes its `interview_rounds` (name and type) and counts of jobs, applications and questions. Returns at most 50.

```bash
curl "$BASE/companies?search=zoho" \
  -H "Authorization: Bearer $AGENT_TOKEN"
```

### POST /companies

Finds a company by name, ignoring case, or creates it. Returns `200` with the existing company, whose details are left unchanged, or `201` with a new one. Required: `name`. Optional: `website`, `logo_url`, `location`, `industry`, `notes`.

```bash
curl -X POST "$BASE/companies" \
  -H "Authorization: Bearer $AGENT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name": "Zoho", "website": "https://www.zoho.com", "location": "Chennai"}'
```

### POST /companies/{id}/rounds

Creates one round. If the company already has a round with the same name (ignoring case), that round is returned with `200` instead. Required: `round_name`, `round_type`. Optional: `description`, `typical_duration`, `source_url`.

```bash
curl -X POST "$BASE/companies/1/rounds" \
  -H "Authorization: Bearer $AGENT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "round_name": "Online assessment",
    "round_type": "aptitude",
    "description": "Aptitude and two coding problems.",
    "typical_duration": "90 min",
    "source_url": "https://example.com/acme-interview"
  }'
```

### POST /companies/{id}/questions

Creates up to 500 questions in one request. If any question is invalid, none are saved. Each needs `question`. Optional: `round_type`, `topic`, `difficulty`, `year_reported`, `source_url`, `source_name`, `suggested_answer`, `answer_based_on`.

`practiced` and `my_notes` belong to you and are ignored here. A question already saved for the company, ignoring case and spacing, is skipped, so research can be re-run. The response gives `count` (created) and `skipped`.

```bash
curl -X POST "$BASE/companies/1/questions" \
  -H "Authorization: Bearer $AGENT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "questions": [
      {
        "question": "Explain database indexing.",
        "round_type": "technical",
        "topic": "SQL",
        "difficulty": "medium",
        "year_reported": 2026,
        "source_name": "Glassdoor",
        "source_url": "https://example.com/q/1",
        "suggested_answer": "An index trades write cost for faster reads...",
        "answer_based_on": {"projects": ["JobHunt"], "experience": []}
      },
      {"question": "Tell me about yourself.", "round_type": "hr"}
    ]
  }'
```

## Emails

### GET /emails

Optional filter: `processed` (`0` or `1`). Sorted newest first.

There is no endpoint for adding emails yet, so this list stays empty until something else fills the `emails` table.

```bash
curl "$BASE/emails?processed=0" \
  -H "Authorization: Bearer $AGENT_TOKEN"
```

### PATCH /emails/{id}

Updates `matched_application_id`, `classification` and `processed`. Other fields are ignored.

```bash
curl -X PATCH "$BASE/emails/1" \
  -H "Authorization: Bearer $AGENT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"matched_application_id": 1, "classification": "interview_invite", "processed": true}'
```

## Summary

### GET /summary

Returns job and application counts for every status, including zeros, plus the applications from the current week. Weeks run Monday to Sunday in IST, set by `timezone` in `config/jobhunt.php`, and `from` and `to` are returned in UTC.

```bash
curl "$BASE/summary" \
  -H "Authorization: Bearer $AGENT_TOKEN"
```

```json
{
  "jobs_by_status": {"found": 12, "queued": 3, "applied": 5, "...": 0},
  "applications_by_status": {"found": 0, "queued": 0, "applied": 5, "...": 0},
  "this_week": {
    "from": "2026-11-01T18:30:00.000000Z",
    "to": "2026-11-08T18:29:59.999999Z",
    "count": 1,
    "applications": [
      {"id": 1, "job_id": 1, "title": "Backend Developer", "company": "Acme", "status": "applied", "applied_at": "2026-11-03T04:30:00.000000Z"}
    ]
  }
}
```

## Apply runs

Apply runs connect the **Apply with Claude** button on a job page to the `/apply-worker` command. The user clicks Apply, the worker claims the run with `php artisan jobhunt:apply-next`, and it reports progress here. The job page shows each report live.

### GET /apply-runs/{id}

Returns the run: `status`, `step`, `log`, `questions` (with any answers), `summary`, `confirmed_at` and `cancelled_at`.

```bash
curl "$BASE/apply-runs/1" \
  -H "Authorization: Bearer $AGENT_TOKEN"
```

### PATCH /apply-runs/{id}

Reports progress. All fields are optional.

| Field | Meaning |
|---|---|
| `step` | `check_rules`, `open_posting`, `fill_form`, `upload_resume`, `screening`, `review`, `submit` or `record` |
| `note` | One short line shown live on the job page |
| `questions` | Array of questions only the user can answer. This sets the status to `needs_answer` |
| `summary` | `[{"label": "...", "value": "..."}]`, everything that will be submitted. Required for `awaiting_confirmation` |
| `status` | `running`, `awaiting_confirmation`, `submitting`, `submitted`, `failed` or `refused` |
| `error` | The reason, for `failed` or `refused` |
| `application_id` | The application recorded after submitting |

**Rules the API enforces:**
- **Approval:** the agent can't approve a submit. `submitting` and `submitted` return `422` unless the user clicked Submit on the job page.
- **Finished runs:** a run that is cancelled or finished returns `409` to any update.

```bash
curl -X PATCH "$BASE/apply-runs/1" \
  -H "Authorization: Bearer $AGENT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"step": "fill_form", "note": "Filling in Notice period"}'
```

### Waiting for the user

These run on the same machine, not over HTTP:

- **`php artisan jobhunt:apply-next --wait=540`:** waits for an Apply click, claims the run, and prints the job, the resume's `pdf_file`, the user's details from Settings and saved answers as JSON. It prints `{"run_id": null}` if nobody clicked.
- **`php artisan jobhunt:apply-wait <run id> --wait=540`:** waits until the user answers the questions, approves, or cancels. It prints `approved`, `cancelled`, `questions` and `still_waiting`.

Both also mark the worker as online on the job page.
