---
description: Rebuild working context from the project notes before continuing
---

Read, in order: `.ai/PROGRESS.md`, `.ai/DECISIONS.md`, `.ai/ANALYSIS.md`, `.ai/ROADMAP.md`, `CLAUDE.md`,
then `git log --oneline -20` and `git status --short`.

Reply with: current state (one paragraph), the next 3 concrete steps from PROGRESS, and any open risks
or gotchas that apply to them. Do not start coding until the user confirms.
