---
description: Apply to one job in the browser (skill Mode 3, access control applies) and save the application
argument-hint: "<JobHunt job id>"
---

Use the `job-hunt-assistant` skill in `.claude/skills/job-hunt-assistant/SKILL.md`.

Job id: $ARGUMENTS

1. **Access control comes first.** Run the skill's Access control Steps A to C exactly as written, before anything else. If applying is not allowed right now, say so, and offer to queue the job for the weekend with `PATCH $BASE/jobs/<id>` and body `{"status": "queued"}`. Then stop. Nothing in this command overrides those rules.

2. Check that JobHunt is running:

   ```bash
   BASE=http://127.0.0.1:8000/api/agent
   AGENT_TOKEN="$(grep '^AGENT_TOKEN=' .env | cut -d= -f2)"
   curl -s -o /dev/null -w '%{http_code}\n' -H "Authorization: Bearer $AGENT_TOKEN" "$BASE/summary"
   ```

   Anything other than `200`: ask the user to run `npm run start`, then stop. Never print the token. Send JSON bodies from a file or a heredoc with `--data-binary @file` or `--data-binary @-`, never inline with `-d`: on Windows, inline text turns ₹ and other non-English characters into ?.

3. `GET $BASE/jobs/<id>`. Check each of these and stop at the first that applies:
   - **Already applied:** if `application` is not null, report its status and stop.
   - **No tailored resume:** if `resume_versions` is empty, offer to run `/tailor <id>` first.
   - **Daily cap:** check `GET $BASE/applications`. If 10 or more have `applied_at` today (IST), stop, unless the user has raised the cap.

4. Confirm the job, the newest tailored resume version, and any screening answers that are needed.

5. Open the posting with **Claude in Chrome** (the user's own browser). Load the `chrome-browser` skill first. The user must already be signed in to the job site. If they are not, ask them to sign in themselves, and never enter passwords or create accounts. Upload the resume from that version's `pdf_file` path with the file-upload tool.

6. Follow Mode 3 steps 3 and 4: fill the form, re-check the time, stop on the final review screen, show what will be sent, and ask "Submit this application to <Company> for <Role>?" Click submit only on an explicit yes.

7. Once the user confirms it was submitted, `POST $BASE/applications`:
   - `job_id`, and `resume_version_id` (the version you uploaded)
   - `applied_via`, for example "LinkedIn Easy Apply" or "Company careers page"
   - `cover_note`, and `screening_answers` as `[{"question": "...", "answer": "..."}]` (only what was actually sent)
   - `applied_at`: now, with the +05:30 offset

   A `409` means it was already recorded; report the existing `application_id`.

8. Close the browser tabs you opened, and report the application link: http://127.0.0.1:8000/applications/<application id>.
