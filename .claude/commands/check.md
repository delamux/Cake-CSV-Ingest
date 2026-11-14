---
description: Run every quality gate (PHPUnit, PHPStan, PHPCS, Vitest, tsc, build) and report failures
---

Run, in this order, and stop at the first failing group to report it with the relevant output:

1. `vendor/bin/phpunit`
2. `vendor/bin/phpstan analyse --memory-limit=1G --no-progress`
3. `vendor/bin/phpcs -q`
4. `npx vitest run`
5. `npx tsc --noEmit`
6. `npm run build` — then `git status --short webroot/js` must show the bundle is committed/up to date.

Never "fix" a failure by weakening a test, adding `@phpstan-ignore`, or running `phpcbf` on `templates/`.
Summarize: which gates passed, which failed, and the root cause of each failure.
