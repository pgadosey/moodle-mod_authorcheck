# Authorship Check (mod_authorcheck)

A Moodle activity that generates personalised comprehension questions from a
student's own assignment submission, to help verify they understand the work
they submitted. Question generation runs on-premises against a local LLM — no
student work leaves the institution.

This is an **authorship-confidence check**, not an AI detector: it surfaces
whether a student can answer questions about their own submission, as a signal
for the teacher to review — not an automated verdict.

## How it works

1. Students submit their work to a normal Moodle **Assignment** (PDF or DOCX).
2. After the assignment's due date, a scheduled task reads each submission,
   extracts its text, and generates a mixed set of questions (multiple choice,
   true/false, fill-in, and open) using Moodle's AI subsystem.
3. The teacher **releases** the questions (on a schedule or manually).
4. Students answer; objective questions are auto-graded and a pass/fail
   **authorship flag** is raised when the score falls below a teacher-set
   threshold.
5. The teacher reviews flagged students, sees their answers, and can override
   the flag.

## Requirements

- **Moodle 4.5+ / 5.0+** with the core **AI subsystem** enabled and an
  AI provider configured (see below). Tested on Moodle 5.0.
- **poppler-utils** (provides `pdftotext`) — for PDF text extraction.
- **pandoc** — for DOCX text extraction.
- **Cron** running regularly (standard Moodle requirement) — the scheduled
  task that generates questions runs on cron.
- An **AI provider** reachable from the server. The plugin uses whatever
  `generate_text` provider the site has configured; a local **Ollama** instance
  is recommended for the on-premises, privacy-first deployment this plugin is
  designed for.

After installing, confirm the command-line tools are detected under
**Site administration → Reports → Status** (the "Authorship Check dependencies"
check).

## Installation

1. Place the plugin folder at `mod/authorcheck` in your Moodle codebase.
2. Install the system dependencies on the web/cron server(s):

   ```bash
   # Debian/Ubuntu
   sudo apt-get update && sudo apt-get install -y poppler-utils pandoc
   ```

3. Visit **Site administration → Notifications** to complete the database
   installation.
4. Configure the AI provider and cURL security (below).

### AI provider and cURL security

The plugin calls the core AI subsystem's `generate_text` action. Configure a
provider under **Site administration → AI → Provider settings** (for on-prem,
an **Ollama** provider pointing at your local model).

Moodle blocks outgoing HTTP requests to internal/private hosts by default
(SSRF protection). If your AI endpoint is on a private address, allow it under
**Site administration → General → HTTP security** by adjusting
`curlsecurityblockedhosts` / `curlsecurityallowedhost` so the provider endpoint
is reachable. Only open what you need.

## Configuration (per activity)

When adding the activity, a teacher sets:

- **Linked assignment** — the assignment whose submissions are checked.
- **Number of questions** (3–12).
- **Question release** — a scheduled release time and/or a manual "release now".
- **Attempts allowed** (1–3).
- **Flag threshold** (40–90%) — below this objective score, the attempt is
  flagged for review.

## Privacy

The plugin stores each student's attempt, generated questions, and answers, and
sends submission text to the AI subsystem to generate questions. It implements
the Moodle Privacy API (data export and deletion). See the plugin's entry in
**Site administration → Users → Privacy and policies → Plugin privacy registry**.

## Known limitations

- **DOCX and PDF only.** Legacy `.doc` (binary Word) is not supported —
  extraction requires a text layer, so scanned/image-only PDFs are flagged as
  "unreadable" for manual teacher review rather than processed.
- **Linked-assignment reference on cross-course restore.** Backup and restore
  carry the activity and its data correctly, but the *link* to the source
  assignment does not auto-remap when restoring into a different course. After
  such a restore, re-select the linked assignment in the activity settings.
- **Group assignments** are not yet supported (individual submissions only).

## Deployment notes

- Bake the system dependencies into your server image rather than installing by
  hand, so they survive rebuilds. Example addition to a Moodle container image:

  ```dockerfile
  RUN apt-get update \
      && apt-get install -y --no-install-recommends poppler-utils pandoc \
      && rm -rf /var/lib/apt/lists/*
  ```

- Ensure cron runs on the same host(s) that can reach the AI provider and the
  file store, since the generation task runs there.

## License

GNU GPL v3 or later.
