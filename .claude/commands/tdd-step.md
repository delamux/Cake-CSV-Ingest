---
description: Implement one small behaviour with strict TDD and a single Conventional Commit
argument-hint: <behaviour to implement>
---

Implement: $ARGUMENTS

1. Read `.ai/PROGRESS.md` and `.ai/DECISIONS.md` first.
2. Write or extend the test (PHPUnit in `tests/TestCase/`, Vitest next to the TS module).
3. Run it and show it failing for the right reason (red).
4. Implement the minimum to make it pass (green). Keep PHP 8.2 compatibility and the clean-room naming rules.
5. Refactor if useful; run `/check`.
6. Commit only the files of this step with a Conventional Commit message (`feat(scope): ...`, `fix(scope): ...`).
   Unrelated fixes discovered on the way get their own commit.
7. Update `.ai/PROGRESS.md` (done/next/gotchas) and add a `.ai/DECISIONS.md` entry if a design choice was made.
