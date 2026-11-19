# EstateOS — Deployment and Rollback Runbook

The procedure for putting a change live and for taking it back out again.

Written to be followed under pressure, by somebody who did not write the change. Every
command is given in full. Nothing here says "simply" or "just".

---

## 0. The one thing to understand first

**Migrations are forward-only.** `database/migrate.php` applies any `.sql` file in
`database/migrations/` that is not already recorded in the `schema_migrations` table. There
are no `down` steps, and there is no `php database/migrate.php --rollback`.

The consequence, stated plainly: **a schema change is undone by restoring the database from
the backup you took before applying it.** If you did not take that backup, the schema change
cannot be undone, and the rollback becomes a data-recovery exercise instead of a procedure.

This is why §1 step 2 is not optional.

---

## 1. Deploying

Do this in a maintenance window if the release contains a migration.

**1. Know what you are shipping.**

```bash
git log --oneline <currently-deployed-sha>..HEAD
git diff --stat <currently-deployed-sha>..HEAD -- database/migrations/
```

The second command is the important one: if it is empty, this release has no schema change
and rollback is simple. If it is not, read every migration before continuing.

**2. Back up the database. Not optional.**

```bash
php database/backup.php
php database/backup.php --verify <the-file-it-just-named>
```

The first takes a compressed dump into `backups/` and keeps the most recent fourteen. It
reads the connection from `.env`, so it needs no credentials of its own, and it **fails
loudly** rather than leaving a zero-byte file behind: a dump with no `CREATE TABLE` in it
is treated as a failure, because that is what a backup looks like when the password was
wrong.

The second restores that dump into a scratch database, compares the table count against
the live one, checks the restored ledger balances, and drops the scratch copy. Run it — a
backup nobody has restored is a hope, not a backup.

Record the filename; you will need it in §2 and you will not want to be guessing which
one it was.

**3. Record the version you are coming from.**

```bash
git rev-parse HEAD > backups/deployed-sha-$(date +%Y%m%d-%H%M%S).txt
```

**4. Put the new code in place.**

```bash
git fetch --all
git checkout <tag-or-sha>
```

**5. Apply migrations.**

```bash
php database/migrate.php --status     # read this before running the next line
php database/migrate.php
php database/migrate.php --status     # everything should now show [x]
```

**6. Check the environment.** `.env` is not in the repository, so a new file does not arrive
with the code. After any release that adds a setting, compare:

```bash
diff <(sed 's/=.*//' .env | sort) <(sed 's/=.*//' .env.example | sort)
```

Any key present in `.env.example` and missing from `.env` needs a value.
`APP_ENV=production` and `APP_DEBUG=false` must both be set.

**7. Confirm the application is actually up.**

```bash
curl -s -o /dev/null -w '%{http_code}\n' https://<host>/login       # expect 200
curl -s -o /dev/null -w '%{http_code}\n' https://<host>/.env        # expect 404
```

The second is not paranoia. If it returns anything but 404, the document root is wrong and
your configuration is being served to the public internet: **stop and fix that before
anything else.**

**8. Check the books.** The one query that tells you the ledger survived the release:

```bash
php tests/q.php "SELECT ROUND(COALESCE(SUM(debit-credit),0),2) FROM journal_entry_lines WHERE status='posted'"
```

It must print `0.00`. Anything else means stop and roll back.

---

## 2. Rolling back

### Decide which case you are in

| | Release had no migration | Release had a migration |
|---|---|---|
| **What to do** | §2a — code only, seconds, no data loss | §2b — restore the database, minutes, **loses everything written since the backup** |

Check with:

```bash
git diff --stat <previous-sha>..<deployed-sha> -- database/migrations/
```

### 2a. Code-only rollback

```bash
git checkout <previous-sha>
curl -s -o /dev/null -w '%{http_code}\n' https://<host>/login    # expect 200
```

That is the whole procedure. No data is affected.

### 2b. Rollback across a migration

**Understand the cost before you start:** restoring the backup discards every row written
since it was taken — every booking, receipt, journal entry and payslip created after that
moment. On a system that takes customer payments, that is money received with no record.

**Prefer a fix-forward** if the fault is a bug rather than a broken schema: write the
correction, ship it through §1, and keep the data.

If you must restore:

**1. Stop the application** so nothing else is written. Put up a maintenance page or stop
the web server.

**2. Dump the current state anyway**, before destroying it. You may need to reconcile from
it later:

```bash
mysqldump --single-transaction -u <user> -p estateos | gzip > backups/pre-rollback-$(date +%Y%m%d-%H%M%S).sql.gz
```

**3. Restore.**

```bash
gunzip -c backups/estateos-<the-one-from-step-2-of-the-deploy>.sql.gz | mysql -u <user> -p estateos
```

To be certain the dump is good **before** destroying the current database, verify it
first — it restores into a scratch copy and touches nothing else:

```bash
php database/backup.php --verify estateos-<stamp>.sql.gz
```

**4. Put the code back.**

```bash
git checkout <previous-sha>
```

The code and the schema must match. Restoring the database without reverting the code leaves
new code running against an old schema, which fails in ways that are harder to diagnose than
either problem alone.

**5. Verify.**

```bash
php database/migrate.php --status                   # should match the old code's migrations
curl -s -o /dev/null -w '%{http_code}\n' https://<host>/login
php tests/q.php "SELECT ROUND(COALESCE(SUM(debit-credit),0),2) FROM journal_entry_lines WHERE status='posted'"
```

**6. Start the application.**

**7. Write down what was lost.** The window between the backup and the restore is a list of
transactions that no longer exist. Somebody has to reconcile it against receipts, bank
statements and the outbox (`mail_messages` records what was sent, which is the best evidence
of what a customer was told).

---

## 3. After any rollback

- Tell whoever is on the phone to customers what window was lost.
- Check `storage/logs/php-error.log` for what actually went wrong.
- Write the failing test **before** fixing the bug — see `TESTING_STRATEGY.md` §5.
- Run the gate before trying again:

```bash
bash tests/run-all.sh
```

---

## 4. This procedure was rehearsed

An untested backup is a hope, not a plan, so the dump and restore in §1 and §2b were run for
real on 13 November 2026 — into a scratch database, never against the live one.

```
1. dump the live database            331K gzipped
2. create a scratch database         estateos_rollback_test
3. restore into it                   no errors
4. compare                           live_tables 117 · restored_tables 117
5. row counts                        journal_entry_lines 83/83 · bookings 16/16
                                     customer_payments 67/67 · payslips 16/16 · units 127/127
   restored trial balance            0.00
6. drop the scratch database         only estateos remains
```

The commands in this document are the commands that were run, with the local
`--defaults-file` removed since a production host will not need it.

**What this rehearsal does not prove:** that a restore works on a database with years of
data, under time pressure, by somebody who has not done it before. Rehearse it again on a
copy of production before you need it, and have the person who would actually be on call do
it.

---

## 5. What is not covered here, and should be

Stated so nobody assumes otherwise:

- **The backup is a script, but nothing schedules it yet.** `database/backup.php` handles
  the dump, the retention and the verification; a production host still needs a cron entry
  or a scheduled task to call it, and somebody to notice when it stops reporting:

  ```cron
  # nightly at 02:15, keeping the most recent fourteen
  15 2 * * *  cd /path/to/estateos && php database/backup.php >> storage/logs/backup.log 2>&1
  ```

  Rehearse the restore against a copy of production rather than against demo data as in §4.
- **No blue/green or staged deployment.** This procedure has downtime during a migration.
- **No health-check endpoint.** §1 step 7 checks that `/login` renders, which proves the
  application boots but not that background work is healthy.
- **Uploaded files are not covered.** `storage/uploads` is not in the repository and not in
  the database dump. It needs its own backup, and restoring a database without the matching
  files leaves documents pointing at nothing.
