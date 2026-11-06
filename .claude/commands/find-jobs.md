---
description: Find matching jobs (skill Mode 1) and save the shortlist to JobHunt
argument-hint: "[optional preference overrides, e.g. remote only, Bengaluru]"
---

Use the `job-hunt-assistant` skill in `.claude/skills/job-hunt-assistant/SKILL.md`. Follow its Access control and Ground rules exactly. Finding jobs counts as "Find jobs" in the Access control table.

Preference overrides from the user, if any: $ARGUMENTS

1. Check that JobHunt is running:

   ```bash
   BASE=http://127.0.0.1:8000/api/agent
   AGENT_TOKEN="$(grep '^AGENT_TOKEN=' backend/.env | cut -d= -f2)"
   curl -s -o /dev/null -w '%{http_code}\n' -H "Authorization: Bearer $AGENT_TOKEN" "$BASE/summary"
   ```

   Anything other than `200` means the app is not reachable. Ask the user to run `npm run dev`, then stop. Never print the token. Send JSON bodies from a file or a heredoc with `--data-binary @file` or `--data-binary @-`, never inline with `-d`: on Windows, inline text turns ₹ and other non-English characters into ?.

2. Run Mode 1 (Find) from the skill. JobHunt is the tracker, so skip the Sheets or CSV tracker step unless the user asks for one.

3. For every job on the final shortlist, `POST $BASE/jobs` as JSON (see `docs/AGENT_API.md`):
   - `url`: the posting link (the company's own page if there is one), `title`, and `company` as an object with `name`, plus `website` and `location` if known.
   - `platform`: one of `linkedin`, `naukri`, `indeed`, `instahyre`, `wellfound`, `foundit`, `career_page`, `other`.
   - `location`, `experience_range`, `salary_text` (as written in the posting), and `jd_text` (the full job description you fetched).
   - `must_have_skills`, `nice_to_have_skills`, `matched_skills`, `gaps` (arrays of strings), and `match_score` (the 0-100 score from Mode 1).
   - Do not send `status` or `found_at`. New jobs start as `found`, and re-posting an existing URL keeps its status.

   Build the JSON with a heredoc or a temp file so quotes in the job description cannot break the command. A `422` means a field was rejected; fix that field and retry once.

4. Report a table sorted by score: JobHunt id, company, role, location, score, top matched skills, gaps, and whether it was new (`201`) or already saved (`200`). Finish with the link http://127.0.0.1:5180/jobs.
