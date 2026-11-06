---
description: Tailor the resume to one job (skill Mode 2) and save the version to JobHunt
argument-hint: "<JobHunt job id or posting URL>"
---

Use the `job-hunt-assistant` skill in `.claude/skills/job-hunt-assistant/SKILL.md`. Follow its Access control and Ground rules exactly. Tailoring counts as "tailor resume" in the Access control table.

Job: $ARGUMENTS

1. Check that JobHunt is running:

   ```bash
   BASE=http://127.0.0.1:8000/api/agent
   AGENT_TOKEN="$(grep '^AGENT_TOKEN=' backend/.env | cut -d= -f2)"
   curl -s -o /dev/null -w '%{http_code}\n' -H "Authorization: Bearer $AGENT_TOKEN" "$BASE/summary"
   ```

   Anything other than `200`: ask the user to run `npm run dev`, then stop. Never print the token. Send JSON bodies from a file or a heredoc with `--data-binary @file` or `--data-binary @-`, never inline with `-d`: on Windows, inline text turns ₹ and other non-English characters into ?.

2. Get the job:
   - **A number:** `GET $BASE/jobs/<id>`. Use its `jd_text`, `title` and `company.name`. If `jd_text` is empty, fetch the posting at `url`.
   - **A URL:** fetch the posting, then `POST $BASE/jobs` with `url`, `title`, `company` and `jd_text`, so the job has a JobHunt id.
   - **Nothing given:** ask which job.

3. Run Mode 2 (Tailor) from the skill on that job description. Work in `backend/storage/app/private/tailoring/job-<id>/`, which is kept out of git. `pdflatex` is MiKTeX at `"$LOCALAPPDATA/Programs/MiKTeX/miktex/bin/x64/pdflatex.exe"` if it is not on the PATH. Check the page count with `python` (pypdf); there is no `python3`. If no LaTeX build works, say so and stop before building the PDF. Do not hand over a broken PDF.

4. Save the version with `POST $BASE/resume-versions` as multipart:

   ```bash
   curl -s -X POST "$BASE/resume-versions" \
     -H "Authorization: Bearer $AGENT_TOKEN" \
     -F "job_id=<id>" \
     -F "label=Tailored for <Company> - <Role>" \
     -F "tex_source=<resume.tex" \
     -F "pdf=@<Your_Name>_<Company>_<Role>.pdf;type=application/pdf" \
     -F 'keywords_covered=["Laravel","REST APIs"]' \
     -F 'changes_summary={"added":[],"removed":[],"reordered":[]}'
   ```

   - `keywords_covered`: the job-description keywords the tailored resume now contains.
   - `changes_summary`: compared with the base resume in the skill, one short plain sentence per item:
     - `added`: for example a Summary line aimed at backend roles
     - `removed`: projects or bullets dropped
     - `reordered`: sections, bullets or skills moved

5. Report the match score, keywords covered, gaps (with which are worth learning), the PDF path, and the new version id. Point to http://127.0.0.1:5180/resumes/<version id>, which shows what changed against the base resume and the PDF.
