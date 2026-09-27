# Step 12 — Jobs & Training (with Eligibility inside)

*Scope v2 Module B §3 (Job & Training Opportunity Postings). The full agreed
scope is in `docs/SCOPE_JOBS_AND_TRAINING.md`.*

**The big change:** eligibility is no longer created on its own. It lives
**inside** each Job and each Training Program. The **Assessments** menu item
is gone: you make the rules in the Job or Training form.

## What you get

**1. Two real menu items** under **Opportunities** (no longer "Soon"):
**Job Postings** and **Training Programs**. *Matches* is still "Soon" (the
next step).

**2. The list.** Open and Closed tabs. Each card shows the title, the
employer or provider, the occupations (in the standard you chose in the top
bar), where, the seats, the deadline, and how many people are Eligible,
Check, Not eligible or On hold. You can:

- search by title (and employer or provider);
- filter by **occupation at any level**: a job for the ESCO job 2411.1 is
  found under ISCO-08 24, under 2411 and under ENOC 2411;
- filter by governorate.

**3. One form, saved once: the details and the eligibility.**

| For both | For a job | For a training |
|---|---|---|
| Title (one field, any language) | Employer (**optional**: an NGO can post without naming the company) | Training provider (your organisation by default) |
| Description | Sector and sub-sector (a company from the list fills them) | Duration (hours, days, weeks, months) |
| Occupation(s): **as many as you need**, any standard and level | Job type (full time, part time, temporary) | Format (in person, online, mixed) |
| Governorate(s), city or area | Salary from / to (EGP a month) | Cost: free, or paid with an amount |
| Seats, deadline, start and end dates, contact person | | Certificate |

A company typed by hand, with a sector chosen, is remembered by your
workspace (as in work histories).

**4. The Eligibility section is required.** The form cannot be saved
without **at least one rule**. The rules, "Must have" / "Counts", the
points adding up to 100, and the two result levels work exactly as in
Step 11. Two helper buttons:

- **Use the occupation(s) above as a rule**;
- **Use the governorate(s) above as a rule**.

They act only when you press them. If you later change the occupations or
governorates, the rule is **not** changed silently: an orange note says the
two no longer agree.

**5. The Job or Training page.**

- The **details**. For a job, the salary is shown next to the **Egypt
  market wages** of its occupations (with the survey period).
- The **eligibility rules** and the two levels.
- **Check everyone** (the whole workspace, or people found in the CV Bank),
  and **Check them again** after the rules change.
- The **shortlist**: Eligible first, sorted by score, with the same filters
  as before.
- A short **history**: created, details changed, rules changed, closed
  (with the reason), opened again, copied.
- Buttons: **Edit**, **Copy** (same details and rules, for the next round),
  **Close** (you choose why: **Filled**, **Cancelled** or **Finished**),
  **Open again**, and **Delete** (only while nobody was checked).
- After the deadline, an orange **Deadline passed** reminder shows. It
  never closes anything by itself.

**6. On the profile and in the CV Bank.** The **Eligibility** panel lists
the jobs and trainings the person was checked against, each marked Job or
Training. "Check against…" offers the **open** jobs and trainings, grouped.
In the CV Bank: **Check these people against a job or training**.
Decisions, reasons, notes and the profile history work as before.

**7. Dashboards.** Your dashboard's pipeline links to Jobs and Training.
The Super Admin sees **counts only**: open jobs, open trainings, seats,
people assessed, people eligible.

**8. Permissions** (every partner user holds them during the build):

| Permission | Allows |
|---|---|
| See jobs, trainings, their rules and shortlists | the lists, the pages, the profile panel |
| Post and manage jobs and trainings, with their rules | new, edit, copy, close, open again, delete |
| Check people against a job or training | Check, Check everyone, from the CV Bank |
| Change an eligibility result (with a reason) | the decision and the notes |

Checking is separate from managing, so a case worker can check people
without being allowed to change a job's rules.

## Install (about 5 minutes)

**Important:** this step replaces the Step 11 tables. Any **test programs
and results** you created in Assessments are removed (they were test data
only). Real profiles, CVs and everything else are untouched.

**1.** Unzip the package. Copy **what is inside** its `massar` folder into
your own `massar` folder, choosing **Replace**.

**2.** Run these one at a time in `D:\My Projects\massar`:

```
php step12_cleanup.php
composer dump-autoload
php artisan migrate
php artisan config:clear
npm run build
php artisan test
```

- `php step12_cleanup.php` deletes the six old Assessment files (listed on
  screen) and then deletes itself. **Run it before `npm run build`**, or the
  build stops on the old screens.
- `php artisan migrate` replaces the three eligibility tables with the new
  ones (**opportunities**, plus the results and the "Check everyone" runs).
- You should see **144 passed** (the 137 from before, the 14 eligibility
  checks rewritten for Jobs and Training, and 7 new ones). If any line says
  **FAILED**, copy the lines under it and send them to me.

## What to check on screen

1. **Job Postings → New job.** Leave everything empty and press **Create
   job**: the title, occupations, governorates, seats, job type and the
   eligibility rule are all asked for.
2. Fill it in **without an employer**. Choose two occupations and two
   governorates. Press **Use the occupation(s) above as a rule** and **Use the
   governorate(s) above as a rule**. Add an Age rule. **Create job**.
3. On the job page, look at the market wages next to the salary, then press
   **Check all … people** and look at the shortlist.
4. Open a person from the shortlist. In **Eligibility**, the job is marked
   "Job". Change the result (a reason is required).
5. Back on the job, **Close** it: choose **Filled**. Look at the History.
   On the profile, the job is no longer offered in "Check against…".
6. **Training Programs → New training program.** The provider is already
   your organisation's name. Choose **Paid** and leave the amount empty:
   it is refused.
7. In **Job Postings**, filter by an occupation (for example ISCO-08 24)
   and by a governorate.
8. Switch to **عربي** and dark mode and look at the same pages.

## If something goes wrong

| What you see | What to do |
|---|---|
| `npm run build` stops on `Pages/App/Assessments` | `php step12_cleanup.php` was not run. Run it, then `npm run build` again. |
| An error mentioning `opportunities` | `php artisan migrate` was not run. |
| The menu still shows *Assessments*, or Jobs still say *Soon* | Run `npm run build` again, then refresh with Ctrl+F5. |
| A 403 page on Jobs or Training | `php artisan config:clear` (the permissions are read from the settings). |
| `php artisan test` shows failures | Copy the lines under **FAILED** and send them to me. |

## Files

**New**

- `database/migrations/2026_10_02_000017_create_opportunities_tables.php`
- `app/Models/Opportunity.php`
- `app/Services/Opportunities/OpportunityBook.php`: occupations at any level, market wages, what the pages show
- `app/Http/Controllers/App/OpportunityController.php`
- `app/Http/Requests/App/SaveOpportunityRequest.php`
- `lang/en/opportunities.php`, `lang/ar/opportunities.php`
- `resources/js/Pages/App/Opportunities/Index.vue`, `Form.vue`, `Show.vue`
- `resources/js/Components/Opportunities/opp.js`
- `tests/Feature/OpportunitiesTest.php` (7 checks)
- `step12_cleanup.php` (deletes itself after use)
- `docs/SCOPE_JOBS_AND_TRAINING.md`, this note

**Changed**

- `routes/web.php`: Jobs and Training (instead of "coming soon" and Assessments)
- `config/permissions.php`: the four permissions
- `app/Models/EligibilityAssessment.php`, `EligibilityRun.php`: they belong to a job or training
- `app/Services/Eligibility/Evaluator.php`, `Assessor.php`, `RunStepper.php`, `RuleBook.php`: the same engine, for jobs and trainings
- `app/Http/Controllers/App/EligibilityController.php`, `BeneficiaryController.php`, `CvBankController.php`
- `app/Http/Controllers/Admin/DashboardController.php`, `ComingSoonController.php`
- `app/Models/Beneficiary.php`, `app/Services/Cv/CvBankSearch.php` (wording only)
- `lang/en/assessments.php`, `lang/ar/assessments.php`
- Screens: `Components/Assessments/EligibilityPanel.vue`, `elig.js`, `RuleEditor.vue`, `Beneficiaries/Show.vue`, `CvBank/Index.vue`, `Dashboard.vue`, `Admin/Dashboard.vue`, `Layouts/AppLayout.vue`, `translations.js`
- `tests/Feature/EligibilityTest.php`: the 14 checks, now through Training
- `docs/SCOPE_ELIGIBILITY_ASSESSMENT.md`: a note at the top

**Removed** (by `step12_cleanup.php`)

- `app/Models/EligibilityProgram.php`
- `app/Http/Controllers/App/EligibilityProgramController.php`
- `app/Http/Requests/App/SaveEligibilityProgramRequest.php`
- `resources/js/Pages/App/Assessments/` (Index, Form, Show)
