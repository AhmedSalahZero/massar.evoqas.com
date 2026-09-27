<!--
  Massar — Step 5: ESCO skills and knowledge
  Location: docs/STEP_05_SKILLS.md
  How to install this step and what to check. Written for a
  non-programmer: copy each command exactly.
-->

# Step 5 · ESCO skills and knowledge

## What this step adds

- **13,939 skills and knowledge items** from ESCO v1.2.1, in Arabic and
  English, stored once in the backbone.
- **Which skills each ESCO job needs**: 126,051 links, marked
  **essential** (normally needed for the job) or **optional** (needed
  in some workplaces). All 3,039 ESCO jobs have their skills.
- **The ESCO skills tree**: 640 groups in 4 pillars (skills, knowledge,
  language, transversal).
- **About 115,000 searchable skill names**, with the same Arabic rules
  as job titles (with or without "ال", hamza, tashkeel).
- **On every ESCO job page** (Super Admin and partners): a *Skills and
  knowledge* panel with Essential and Optional tabs.
- **On every ENOC / ISCO-08 occupation page**: the skills most needed by
  the ESCO jobs in that group, with how many jobs need each one.
- **A page for each skill**: what it means (Arabic and English), where
  it sits in the skills tree, related skills, and every job that needs
  it.
- **In Occupation Backbone** (Super Admin): a new *ESCO skills and
  knowledge* part with the counts, the data checks, a **Find a skill**
  search, and the skills import history.
- A new command, `php artisan skills:import`, with the same safety as
  the occupation import. It checks everything first, changes nothing if
  something is wrong, and keeps skill IDs stable so later links never
  break.

The 7 ESCO skills files are already inside this package, in
`database/data/backbone/esco`.

## Findings about the ESCO files (shown on screen)

1. **220 knowledge groups have no Arabic name in ESCO itself**. These are
   the UNESCO fields of education, for example "education science".
   They are shown in English, marked "no Arabic name in ESCO".
2. **80 skills have an "Arabic" name in Latin letters**. These are
   product names such as "Apache Maven". This is normal.
3. **Only 15 skills have Arabic alternative names.** Local Arabic
   wording will come later from Learned Rules, the same as for job
   titles.
4. **21 skills are listed twice**, identical except for the date. The
   newest copy is kept.
5. **464 skills are not needed by any job.** They can still be found.
6. **One skill is listed as related to itself.** That line is skipped.

## Install (about 3 minutes)

1. Unzip `massar_step5_skills.zip`.
2. Copy everything inside it into your `massar` folder and choose
   **Replace the files in the destination**. This only adds and updates
   files; nothing of yours is deleted.
3. In the terminal, inside the `massar` folder, run these one at a
   time. Wait for each one to finish.

```
composer dump-autoload
php artisan migrate
php artisan skills:import
npm run build
php artisan test
```

What you should see:

| Command | Expected result |
|---|---|
| `composer dump-autoload` | ends with "Generated optimized autoload files" |
| `php artisan migrate` | one line: `create_esco_skills_tables … DONE` (nothing else is touched) |
| `php artisan skills:import` | a table: **13,939** skills and knowledge (10,715 · 3,219), **640** skill groups, **126,051** links (67,600 · 58,451), **3,039** ESCO occupations with skills, 5,817 skill ↔ skill links, 114,655 searchable names. Then the notes on the data and **Done in … seconds** |
| `npm run build` | ends with "✓ built in …" |
| `php artisan test` | **58 passed** (the 51 from before + 7 new skills tests) |

The skills import can take up to two minutes on your computer. Running
`skills:import` a second time says *"The files are the same… Nothing was
changed."* That is correct.

## Check it yourself (5 minutes)

Sign in as the **Super Admin** and open **Occupation Backbone**.

1. Under the data checks there is a new part, **ESCO skills and
   knowledge**. The four boxes show **13,939 · 640 · 126,051 · 114,655**.
2. The **Skills data checks** list the findings above.
3. In **Find a skill**, search **محاسبه** (without "ال" and with ه).
   "Accounting / المحاسبة" should come first. Then try **excel**: "Use
   spreadsheets software" should come first. Then try **لحام**.
4. Open **2411 Accountants**. A new panel, *Skills most needed in this
   occupation*, shows skills such as "Interpret financial statements · 8"
   (8 of the 13 ESCO jobs need it).
5. Open the ESCO job **Accountant**. The *Skills and knowledge* panel
   shows **26 essential** and **31 optional**. Switch the tabs.
6. Press any skill, for example **Calculate tax**. Its page shows the
   descriptions in both languages, where it sits in the skills tree, and
   the jobs that need it (6 essential, 6 optional).
7. Open **3221** (no ESCO jobs). There is no skills panel, because this
   group is classified at group level only.
8. Switch to **عربي** and check the pages read well.

Then sign in as a **partner** user (for example
caseworker@alamal.test) and open **Occupations** → 2411 → an ESCO job →
a skill. The partner sees the same skills. There is no admin search or
import history.

## Updating the skills later

When a newer ESCO arrives, replace **both** the two occupation files and
the 7 skills files in `database/data/backbone/esco` (they must be from
the same edition). Change `editions.esco` in `config/backbone.php`, then
run:

```
php artisan backbone:import
php artisan skills:import
```

If the skills files are from a different edition than the occupations,
the import stops and says so, without changing anything.

## If something goes wrong

| What you see | What to do |
|---|---|
| "The import stopped. Nothing was changed." | Send me the **Reason** line under it. |
| "The occupation backbone is empty…" | Run `php artisan backbone:import` first, then `php artisan skills:import`. |
| "File missing: esco/…" | A skills file is not in `database/data/backbone/esco`. Unzip the package again. |
| "…Allowed memory size…exhausted" | Run it with more memory: `php -d memory_limit=1G artisan skills:import` |
| An error mentioning `esco_skills` or `skill_imports` | `php artisan migrate` was not run. |
| The new panels do not appear | Run `npm run build` again, then refresh with Ctrl+F5. |
| `php artisan test` shows failures | Copy the lines under **FAILED** and send them to me. |
