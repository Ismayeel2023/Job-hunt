---
description: Sync Gmail, classify new job emails, and update application statuses with the email as evidence
---

Use the `job-hunt-assistant` skill in `.claude/skills/job-hunt-assistant/SKILL.md` for its Ground rules. Work only from what JobHunt stored (sender, subject, snippet, date). Never open Gmail or the full message.

1. Fetch new mail, then check the API:

   ```bash
   php artisan jobhunt:sync-mail
   BASE=http://127.0.0.1:8000/api/agent
   AGENT_TOKEN="$(grep '^AGENT_TOKEN=' .env | cut -d= -f2)"
   curl -s -o /dev/null -w '%{http_code}\n' -H "Authorization: Bearer $AGENT_TOKEN" "$BASE/summary"
   ```

   If the sync reports an account needs reconnecting, tell the user to click Reconnect on the Settings page, and carry on with what is already stored. If the API check is not `200`, ask the user to run `npm run start`, then stop. Never print the token. Send JSON bodies from a file or a heredoc with `--data-binary @file` or `--data-binary @-`, never inline with `-d`: on Windows, inline text turns ₹ and other non-English characters into ?.

2. Load the work:
   - `GET $BASE/emails?processed=0`, following `next_page_url` until done.
   - `GET $BASE/applications`, for matching and current statuses.

3. Classify each email as exactly one of these. Each maps to a status:

   | Classification | New status |
   |---|---|
   | `applied_confirmation` | `applied` |
   | `assessment_invite` | `online_test` |
   | `interview_invite` | `interview` (use `hr` when it is clearly an HR or salary discussion) |
   | `rejection` | `rejected` |
   | `offer` | `offer` |
   | `other` | none (alerts, newsletters, job recommendations, marketing) |

4. Find the application:
   - Use `matched_application_id` if it is set.
   - Otherwise match the company or role in the sender, subject and snippet against the applications list.
   - If exactly one application clearly fits, `PATCH $BASE/emails/<id>` with `matched_application_id`.
   - If none fits, or it is unclear, leave it unmatched.

5. Update the status, only for a matched email whose classification maps to a status:
   - **Already there or further along:** if the application's current status is the same, or is a later stage, make no status change. For example, an `applied_confirmation` for an application that is already at `interview`.
   - **Ambiguous offer or rejection:** before setting `offer` or `rejected` from an email that is ambiguous, ask the user and wait. Examples: "we will keep your profile on file", "position on hold", or an offer of a different role.
   - **Otherwise:** `POST $BASE/applications/<application id>/status` with:
     - `to_status`, and `source: "gmail"`
     - `email_id`: the email's id
     - `happened_at`: the email's `received_at`
     - `evidence`: one short sentence naming the sender and subject and what it says
     - `next_step` and `next_step_at` if the email gives a date, for example a test deadline or interview slot

6. Mark processed, only after any status change succeeded: `PATCH $BASE/emails/<id>` with `{"classification": "...", "processed": true}`.
   - Exception: a non-`other` email with no matching application. Set only `classification` and leave `processed` false, so a later sync can link it once the application exists.

7. Report a table:
   - email date, sender, subject, classification, application (company and role), and the status change made or why none was made
   - then list the emails still waiting on the user (ambiguous ones, unmatched ones) and ask about them
