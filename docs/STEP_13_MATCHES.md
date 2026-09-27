# Step 13 — Matches

*Scope v2 Module B §3 (Beneficiary–Opportunity Matching). The full agreed
scope is in `docs/SCOPE_MATCHES.md`.*

**In one sentence:** the case worker **refers** an Eligible person to a Job
or Training, and Massar follows that person step by step until they are
**hired** or have **completed** the training (or it stops, with a reason).
Every step records who, when and why.

## What you get

**1. Matches is a real menu item** under **Opportunities** (no longer "Soon").

**2. On the Job or Training page**

- In the **Shortlist**, each Eligible person has a **Refer** button, and a
  tick box to **refer several at once** ("Refer the 3 chosen").
- People who are **Check**, **On hold** or **Not eligible** cannot be
  referred. The line says so and offers **Change the result** (it opens the
  profile, where the case worker decides, with a reason).
- A new **Fit** column: the **occupation fit** (*Same occupation*, *Same
  group 2411*, *Same minor group 241* … *Not related*) and the **skills fit**
  (*"1 of 2 essential skills"*; press it to see what was found and what is
  lacking). A new sort: **Best fit first**. The fit only sorts and informs:
  it never blocks a referral and never changes a result.
- If the person is already **Hired** (or **In training**) elsewhere, the
  line says *"Already hired at …"*, and referring shows a reminder.
- A new **Matches** part: everyone referred, **grouped by stage**, the
  counts, and **"7 of 10 seats taken"**. Each line has **→ next stage**,
  **Stop**, **Back one stage** and **Timeline**. Stopped matches are hidden
  under "Show the stopped", each with **Restart**.
- When **all seats are taken**: an orange warning with **Close as Filled**.
  Nothing is blocked (an employer may take more than planned).

**3. The stages** (the words change with the kind)

| Stage | Job | Training |
|---|---|---|
| 1 | Referred to the employer | Referred to the provider |
| 2 | Accepted by the employer | Accepted into the training |
| 3 | Interviewing | In training |
| 4 | **Hired** | **Completed** |

- Each move asks for **the date it happened** (today, or earlier) and an
  optional **note**.
- **Skipping** is allowed (for example, Hired straight after referral). The
  skipped stages are shown as skipped in the timeline.
- **Back one stage** corrects a mistake: a **reason is required**.
- **Hired / Completed** are final: only "Back one stage" can undo them.
- **Stop** at any stage before the last, with a reason from the list:
  Job: *Not accepted by the employer · Not hired after interviews · The
  person withdrew · Other (write it)*.
  Training: *Not accepted by the provider · Did not complete (dropped out) ·
  The person withdrew · Other (write it)*.
- **Restart** a stopped match with a reason: it continues at the stage where
  it stopped. Like a referral, only while the job or training is **open**
  and the person is **Eligible**.
- A seat is taken from **Accepted** onwards (and not stopped).

**4. On the profile**

- A new **Matches** panel: every job and training the person was referred
  to, the stage, since when, and the **full timeline** (stage, date, who,
  note). The same buttons as on the job page.
- **Suggested for this person**: the open jobs and trainings whose
  occupations fit the person's occupation (the same, or the same 4-digit
  group), with the eligibility result where it exists and **Refer**,
  **Check and refer** (not checked yet: checked first, referred only if
  Eligible) or **Check again**.
- The profile **History** shows every referral and stage change.

**5. The Matches page**

- Counts per stage: **Referred · Accepted · In progress · Hired / Completed
  · Stopped**, and **Needs follow-up** (click one to filter).
- Filters: Job or Training, a specific job or training, the person's
  governorate, the person's occupation (any standard, any level), and a
  search by name, mobile or number.
- **Needs follow-up**: active matches with **no change recorded for 14
  days**, so nobody is forgotten.

**6. Elsewhere**

- **Dashboard pipeline**: *Matched* (people referred at least once) and
  *Placed* (people hired or who completed a training) show real numbers,
  with a link to the matches that need a follow-up.
- **CV Bank → journey stage** gains **Matched** and **Placed**.
- **Jobs and Training lists**: each card shows the seats taken.
- **Super Admin dashboard**: counts only (matches, people matched, people
  placed).
- A closed job or training takes **nobody new**, but its matches continue.
- When the person's result changes after the referral (profile, rules, or
  the case worker's decision), the match **stays** and is marked **"No
  longer eligible"** so someone looks again. It is never stopped by itself.
- A deleted profile takes its matches with it.

**7. Permissions** (every partner user holds both during the build)

| Permission | Allows |
|---|---|
| See matches (`matches.view`) | the Matches page, the Matches part of a job or training, the profile's Matches panel and the fit |
| Refer people, move stages, stop, restart and correct matches (`matches.manage`) | Refer, next stage, back, stop, restart |

## Install (about 5 minutes)

Nothing is deleted in this step, and no existing data changes.

**1.** Unzip the package. Copy **what is inside** its `massar` folder into
your own `massar` folder, choosing **Replace**.

**2.** Run these one at a time in `D:\My Projects\massar`:

```
composer dump-autoload
php artisan migrate
php artisan config:clear
npm run build
php artisan test
```

- `php artisan migrate` adds two tables: **opportunity_matches** (the
  matches) and **opportunity_match_events** (their timelines).
- You should see **155 passed** (the 144 from before and 11 new; 2 are skipped, as before). If any line says **FAILED**, copy the
  lines under it and send them to me.

## What to check on screen

1. Open a **job** that has people checked (or press **Check all … people**).
2. In the **Shortlist**, look at the **Fit** column. Press *"… essential
   skills"* on one line to see what was found and what is lacking. Choose
   **Best fit first** in the sort box.
3. Tick two **Eligible** people and press **Refer the 2 chosen**. Keep
   today's date and write a note. The **Matches** part now shows them under
   *Referred to the employer*, and "0 of … seats taken".
4. On one of them press **→ Accepted**, then **→ Hired**. The seats count
   goes up. On the other press **Stop**, choose *Other* and try to save
   without words (it is refused), then write a reason.
5. Press **Timeline** on each. Then **Show the stopped** → **Restart**
   (a reason is required).
6. Try **Refer** on someone **Not eligible**: the line says why and offers
   **Change the result**.
7. Open one of the people: the **Matches** panel, the timeline, **Suggested
   for this person**, and the **History** at the bottom.
8. **Matches** in the menu: the counts, click **Hired / Completed**, filter by
   the job, search by a mobile number.
9. The **Dashboard**: Matched and Placed have numbers now.
10. Switch to **عربي** and dark mode and look at the same pages.

## If something goes wrong

| What you see | What to do |
|---|---|
| An error mentioning `opportunity_matches` | `php artisan migrate` was not run. |
| Matches still says *Soon* in the menu | Run `npm run build` again, then refresh with Ctrl+F5. |
| A 403 page on Matches | `php artisan config:clear` (the permissions are read from the settings). |
| The Fit column always says "No ESCO skills for these occupations" | The ESCO skills are not loaded: `php artisan skills:import` (Step 5). |
| `php artisan test` shows failures | Copy the lines under **FAILED** and send them to me. |

## Files

**New**

- `database/migrations/2026_10_03_000018_create_matches_tables.php`
- `app/Models/OpportunityMatch.php`, `app/Models/OpportunityMatchEvent.php`
- `app/Services/Matches/Matcher.php`: the only place matches are written (refer, move, back, stop, restart, seats)
- `app/Services/Matches/MatchBook.php`: what the screens show (lists, filters, counts, suggestions)
- `app/Services/Matches/MatchFit.php`: the occupation and skills fit
- `app/Http/Controllers/App/MatchController.php`
- `lang/en/matches.php`, `lang/ar/matches.php`
- `resources/js/Pages/App/Matches/Index.vue`
- `resources/js/Components/Matches/MatchActions.vue`, `MatchTimeline.vue`, `MatchesPanel.vue`, `match.js`
- `tests/Feature/MatchesTest.php` (11 checks)
- `docs/SCOPE_MATCHES.md` (agreed), this note

**Changed**

- `routes/web.php`: the Matches routes (instead of "coming soon")
- `config/permissions.php`: `matches.view` added
- `app/Models/Opportunity.php`: its matches
- `app/Http/Controllers/App/OpportunityController.php`: the fit, Refer, the Matches part, the seats taken
- `app/Http/Controllers/App/BeneficiaryController.php`: the Matches panel, the suggestions, the history lines
- `app/Http/Controllers/App/DashboardController.php`, `Admin/DashboardController.php`: Matched and Placed
- `app/Http/Controllers/ComingSoonController.php`: Matches removed from "coming soon"
- `app/Services/Cv/CvBankSearch.php`: the Matched and Placed stages
- Screens: `Opportunities/Show.vue`, `Opportunities/Index.vue`, `Beneficiaries/Show.vue`, `Dashboard.vue`, `Admin/Dashboard.vue`, `CvBank/Index.vue`, `Layouts/AppLayout.vue`, `lang/translations.js`
- `tests/Feature/OpportunitiesTest.php`, `tests/Feature/EligibilityTest.php`: Matches is a real page now; the pipeline has Matched and Placed

**Removed**

- Nothing. No file to delete by hand.
