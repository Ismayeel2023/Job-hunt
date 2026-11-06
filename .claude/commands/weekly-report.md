---
description: Summarise this week's job hunt from JobHunt
---

Read-only: do not change anything in JobHunt.

1. Load the data:

   ```bash
   BASE=http://127.0.0.1:8000/api/agent
   AGENT_TOKEN="$(grep '^AGENT_TOKEN=' backend/.env | cut -d= -f2)"
   curl -s -H "Authorization: Bearer $AGENT_TOKEN" "$BASE/summary"
   curl -s -H "Authorization: Bearer $AGENT_TOKEN" "$BASE/applications?per_page=200"
   curl -s -H "Authorization: Bearer $AGENT_TOKEN" "$BASE/jobs?status=queued&per_page=200"
   ```

   If any call fails, ask the user to run `npm run dev`, then stop. Never print the token.

2. The week runs Monday to Sunday in IST (`this_week.from` and `to` are given in UTC). Write a short report:
   - **This week:** applications sent, each with company, role, status now and date, and how that compares with last week.
   - **Pipeline:** jobs and applications by status, from `jobs_by_status` and `applications_by_status`.
   - **Stage reached:** how many applications got to at least online test, interview, HR and offer.
   - **Replies:** share of applications that got any reply, including rejections. Name it "Response rate". Never call it a success or selection rate.
   - **Coming up:** next steps with dates in the next 7 days, and any that are overdue.
   - **Going quiet:** applications still at `applied` with no change in 14 days or more. Suggest a follow-up, or marking them `ghosted` (do not change them).
   - **Next weekend:** queued jobs waiting for Saturday and Sunday.

3. Never present a "guaranteed selection" or success-probability figure. Only report Match score and Stage reached.
