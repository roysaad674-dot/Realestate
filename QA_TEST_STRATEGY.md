# QA Test Strategy

The strategy asked for under this name is in **[TESTING_STRATEGY.md](TESTING_STRATEGY.md)**.

Both documents were requested, and splitting the same material across two files would mean
one of them going stale. `TESTING_STRATEGY.md` holds all of it:

- why PHPUnit, Vitest, Playwright and ZAP are not used here (no Composer, no npm — by design)
- what is used instead, and why it catches more for an application of this shape
- the five suites and what each covers
- the conventions: self-cleaning suites, deterministic cleanup, assertions on facts rather
  than wording, negative tests, standing invariants
- how test-driven development was actually practised during this work, including where it
  was not
- why no coverage percentage is quoted
- how to run everything

The single command that runs the whole gate is `bash tests/run-all.sh`.
