# JobHunt

A personal job application tracker that runs only on this computer. Claude Code finds jobs, tailors your resume, records applications and reads your job email, and you follow it all here.

## Install (first time)

**You need:**
- PHP 8.2 or newer, with the `pdo_sqlite` extension
- [Composer](https://getcomposer.org)
- Node.js 20 or newer
- [Claude Code](https://claude.com/claude-code), plus the [Claude in Chrome](https://claude.com/chrome) extension in a Chrome-based browser (Chrome, Brave or Edge)
- **Optional:** a LaTeX install for building resume PDFs (MiKTeX on Windows, MacTeX on macOS, TeX Live on Linux), and Python with `pypdf`

**Then, in the project folder:**

```
composer run setup
```

This one command:
- installs everything
- creates `.env` with fresh secrets
- creates the database and your login
- copies a blank skill template to `.claude/skills/job-hunt-assistant/SKILL.md`
- builds the website

It prints your login at the end. It's safe to run again: it only fills in what's missing.

### Set up your skill

The skill at `.claude/skills/job-hunt-assistant/SKILL.md` is personal to you and is never committed.

1. **Fill in your details.** Fill in **Profile** and **Base resume (LaTeX)**. The easiest way is to open Claude Code in this folder, attach your resume, and ask: *"Fill in the Profile and Base resume sections of my job-hunt-assistant skill from this resume."*
2. **Choose a secret key** for the rules in **Access control**, then print its SHA-256:

   ```
   python -c "import hashlib; print(hashlib.sha256(input('Key: ').strip().lower().encode()).hexdigest())"
   ```

   Paste the result in place of `REPLACE_WITH_THE_SHA256_OF_YOUR_KEY`. Keep the key itself to yourself.
3. **Check the rules.** Apply days and the locked window are set in the skill and in `config/jobhunt.php`. Change both if yours differ.

**Gmail (optional):** see [docs/GMAIL_SETUP.md](docs/GMAIL_SETUP.md).

**On Windows:** the skill's checks use `TZ=Asia/Kolkata date` and `python3` in Git Bash. If the time shows UTC or `python3` is missing, see "Windows notes" in [CLAUDE.md](CLAUDE.md).

## Every day

Open PowerShell in `D:\Job-hunt` and run:

```
composer run dev
```

Then open **http://127.0.0.1:8000** and log in. Use `127.0.0.1`, not `localhost`.

This one command starts the website and the hourly Gmail sync together. Leave the window open while you use the app, and press **Ctrl+C** to stop.

If `composer` isn't recognised (for example in Git Bash), `npm run start` does exactly the same thing.

## Logging in

- **Email:** `me@jobhunt.test`
- **Password:** the value of `JOBHUNT_USER_PASSWORD` in `.env`

You can change both on the Profile page, which is the person icon.

## Applying from the website

1. In Claude Code, run `/apply-worker` and leave it running. Each job page then shows **Claude worker is online**.
2. Open a job (click its title on **Jobs**) and click **Apply with Claude**.
3. Watch the steps light up as Claude fills the form in your browser. If the form asks something only you can answer, type it on the page.
4. Check what will be sent, then click **Submit**. Nothing is submitted until you do.

It follows your skill's rules: applying works only on Saturday and Sunday (IST), up to 10 a day.

Add your current and expected salary, notice period and relocation preference under **Settings → Your details for applications**. Claude uses them when a form asks, and **What to ask for** on the dashboard suggests a salary for every company you've queued or applied to.

## Using it with Claude Code

Open Claude Code in this folder and use these commands:

| Command | What it does |
|---|---|
| `/find-jobs Laravel developer, Coimbatore or remote, 0-2 years` | Searches job sites, scores each job against your profile, and saves the shortlist to **Jobs** |
| `/tailor 65` | Tailors your resume to job 65 and builds a one-page PDF. **Resumes** then shows what changed against the base resume |
| `/apply-worker` | Lets the **Apply with Claude** button on job pages work. Leave it running |
| `/apply 65` | The same as the button, but run from Claude Code |
| `/prep Zoho` | Saves interview rounds and questions reported by candidates, with sources and suggested answers, to **Interview prep** |
| `/sync-mail` | Fetches job email, sorts it, and updates application statuses |
| `/weekly-report` | Summarises this week |

The app must be running (`composer run dev`) for these commands to work.

## Pages

| Page | What it shows |
|---|---|
| Dashboard | This week, response rate, stage reached, next steps and top matches |
| Jobs | Every job found, with filters and match score |
| Applications | A board where you drag a card to change its status, with the full history and resume on each application |
| Resumes | Every version, where it was used, and what changed |
| Interview prep | Rounds and questions per company, plus practice mode |
| Settings | Gmail accounts, the agent token, and the apply-day rules |

## Good to know

- **Gmail asks to reconnect about once a week.** This is Google's rule for personal apps in Testing mode. Go to Settings and click **Reconnect**.
- **After updating the app's code,** run `npm run build` once, then `composer run dev` as usual.
- **"Port 8000 is already in use":** an old copy is still running. Close its window, or in PowerShell run:

  ```
  Get-NetTCPConnection -LocalPort 8000 -State Listen | ForEach-Object { Stop-Process -Id $_.OwningProcess }
  ```

- **Running the tests:** `php artisan test`.

## More detail

- [docs/AGENT_API.md](docs/AGENT_API.md) covers the API Claude Code uses, with a curl example for each endpoint.
- [docs/GMAIL_SETUP.md](docs/GMAIL_SETUP.md) explains how Gmail was connected and how to add another account.
- [CLAUDE.md](CLAUDE.md) explains how the app is built.
