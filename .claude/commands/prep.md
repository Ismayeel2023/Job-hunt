---
description: Research a company's interview rounds and reported questions and save them with suggested answers
argument-hint: "<company name>"
---

Use the `job-hunt-assistant` skill in `.claude/skills/job-hunt-assistant/SKILL.md`. Follow its Access control and Ground rules, treating this research like "Find jobs" in the Access control table. Its Profile section is the only source of facts about the candidate.

Company: $ARGUMENTS (if empty, ask which company)

1. Check that JobHunt is running:

   ```bash
   BASE=http://127.0.0.1:8000/api/agent
   AGENT_TOKEN="$(grep '^AGENT_TOKEN=' .env | cut -d= -f2)"
   curl -s -o /dev/null -w '%{http_code}\n' -H "Authorization: Bearer $AGENT_TOKEN" "$BASE/summary"
   ```

   Anything other than `200`: ask the user to run `npm run start`, then stop. Never print the token. Send JSON bodies from a file or a heredoc with `--data-binary @file` or `--data-binary @-`, never inline with `-d`: on Windows, inline text turns ₹ and other non-English characters into ?.

2. Find the company: `GET $BASE/companies?search=<name>`. If there is no exact match, `POST $BASE/companies` with `name` (and `website` if you know it). Note the id and the `interview_rounds` already saved.

3. Research with WebSearch and WebFetch, in public sources only:
   - GeeksforGeeks interview experiences
   - AmbitionBox interviews
   - LeetCode Discuss
   - Glassdoor
   - PrepInsta
   - the company's own careers or engineering blog

   Skip any page that needs a sign-in, and never try to get past a login wall or CAPTCHA. Prefer reports from the last 3 years, and for this candidate's level: fresher or 0-2 years, Software Engineer, Developer or Backend roles.

4. Save each distinct round with `POST $BASE/companies/<id>/rounds`:
   - `round_name`, and `round_type`: one of `aptitude`, `coding`, `technical`, `hr`, `managerial`, `group_discussion`
   - `description`: start with "Reported by candidates:" and summarise what the round involves
   - `typical_duration` if reported, and `source_url`

   A round already saved under the same name comes back unchanged.

5. Save the questions in one request with `POST $BASE/companies/<id>/questions`, as `{"questions": [...]}`. For each question:
   - `question`: paraphrase it in one or two sentences. Do not copy long passages from the source.
   - `round_type`, `topic` (for example SQL, OOP, DSA arrays, Laravel, Behavioural), and `difficulty` (`easy`, `medium` or `hard`) if the source gives one.
   - `year_reported`: the year on the report. If no year is shown, leave it out rather than guessing.
   - `source_url` (the exact page) and `source_name` (for example "GeeksforGeeks").
   - `suggested_answer`: start with "Reported by candidates." and then give the draft answer.
     - Use ONLY facts from the skill's Profile section: its experience, projects, skills, education and achievements.
     - For a technical question, explain the concept and tie it to a real profile project where one fits.
     - For a question that needs facts not in the profile (salary, notice period, reasons for leaving, relocation), give a structure with `[your answer]` placeholders. Never invent them.
   - `answer_based_on`: `{"projects": [...], "experience": [...]}` using project and job names exactly as they appear in the Profile.

   Never call anything "official" or "the company's process". The only exception is when it comes from the company's own blog, and then name that blog.

   If the research finds no reports for this company (common for small or new companies), save no rounds and no questions. Never fill the gap with generic or invented questions under the company's name. Say what was searched, and offer to run `/prep` for a similar company the user names instead.

6. Report:
   - the rounds found, and how many questions were saved and skipped (already there)
   - which sources were used, and any that were skipped because they needed a sign-in

   Point to http://127.0.0.1:8000/prep.
