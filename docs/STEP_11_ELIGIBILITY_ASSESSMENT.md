# Step 11 — Eligibility Assessment

*Scope v2 Module B §3 (Eligibility Assessment Engine). The full agreed scope
is in `docs/SCOPE_ELIGIBILITY_ASSESSMENT.md`.*

## What you get

**1. Eligibility programs.** The menu item **Assessments** (no longer
"Soon") lists your workspace's programs, open and closed. Each program has:

- a name (Arabic, English or both) and a short description;
- **two result levels**, on a score out of 100: **Eligible from** and
  **Check from** (for example 70 and 50). The form shows the three bands
  in colour. The same number in both means no Check band;
- **its rules**. Each rule is either:
  - **Must have**: fail it and the person is Not eligible, whatever the score;
  - **Counts**: meet it and its points are added to the score. The points
    of all the "Counts" rules must add up to exactly 100. The form shows
    the total as you type.

Programs belong to **your workspace only**. Another partner never sees them.

**2. The rules** read only what the profile already holds:

| Rule | Example | Read from |
|---|---|---|
| Age | 18 to 29 | Date of birth |
| Governorate | Cairo, Giza | Governorate |
| Education level | Above intermediate and higher | Education level |
| Field of study | accounting, محاسبة | What the person studied |
| Skills | Excel (all / at least one) | Skills |
| Occupation | ISCO-08 24, ENOC 4222, an ESCO job | Occupation, in any standard and at any level |
| Years of experience | at least 2 | Work history |
| Language | English, at least Good | Languages |
| Military status (men) | Completed, Exempted | Military status. For women it does not apply and counts as met |
| Gender | Female | Gender, marked "use only when truly required" |
| Expected salary | up to 8,000 EGP | Expected salary |

**3. The result**, with every reason:

- a failed **Must have** rule → **Not eligible**, whatever the score;
- otherwise, **missing information** (for example no date of birth) →
  **Check**, whatever the score. **Nothing is guessed**;
- otherwise, the program's two levels decide: **Eligible**, **Check** or
  **Not eligible**.

Each rule is shown with **✓** (met), **✗** (not met), **?** (not recorded
in the profile) or **–** (does not apply), and what the profile holds,
for example *"✗ Lives in: Cairo, Giza — Alexandria"*.

**4. The case worker's decision.** On the profile, **Change the result**:

- the choices are **Eligible**, **Not eligible** or **On hold**, or go
  back to the automatic result;
- **a reason is required** every time;
- it is written into the **profile history** (who, when, from what to
  what, and the reason);
- a new check never changes a decision. If the profile changes, or the
  program's rules change, the decision stays and is marked **"profile
  changed since the decision"** or **"rules changed since the decision"**;
- **Notes** can be added to any result.

**5. On the profile.** A new **Eligibility** panel lists every program the
person was checked against, with the score, the result, the reasons, the
decision and the notes. Choose an open program and press **Check**.
After a Guided Intake, the new profile opens with this panel ready.

**6. Check many people at once.** On a program's page:

- **Check all N people** checks your whole workspace;
- from the **CV Bank**: search and filter, then press **Check these people
  against a program**. Every person the search found is checked, not only
  the page you see;
- after you change the rules, the page says how many results were made
  with the older rules, with a **Check them again** button.

It runs 200 people at a time and shows a progress bar. If you leave the
page, it continues when you come back.

**The results list** puts Eligible first, then Check, On hold and Not
eligible, each sorted by score. You can filter by result, by "Needs a
look" (decisions to look at again, and results made with older rules),
or by name, mobile or number.

**7. It completes things we left waiting.**

- **CV Bank:** the **Journey stage** filter left out in Step 8 is here:
  *Not assessed yet*, *Assessed*, *Eligible in a program*.
- **Dashboard:** the **Placement pipeline**. Registered and Assessed
  (with how many are eligible) show numbers now. Matched and Placed come
  with the Matching module.
- **Super Admin dashboard:** one line of **counts only**: open programs,
  people assessed, people eligible, across partners. Never a person or a
  result.

**8. Permissions.** Three new ones. During the build every partner user
holds them, as with the others:

| Permission | Allows |
|---|---|
| See programs and results | the Assessments pages and the profile panel |
| Create and change programs; run checks | new, edit, copy, close, delete, Check, Check all |
| Change a result (with a reason) | the decision and the notes |

**Closing and deleting.** **Close** keeps all the results, but nobody can
be checked until you **Open again**. **Delete** is only offered while a
program has no results. **Copy** makes a new program with the same rules,
for example for next year's round.

## Install (about 5 minutes)

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

- `php artisan migrate` adds **three new tables**: the programs, the
  results, and the "Check everyone" runs.
- You should see **137 passed**: the 123 from before plus 14 new ones. If
  any line says **FAILED**, copy the lines under it and send them to me.

## What to check on screen

1. Open **Assessments** → **New program**. Add an Age rule (Must have,
   18–29), a Governorate rule (Must have, Cairo and Giza), and two
   "Counts" rules, for example Education 50 and English 50. Watch the
   points total reach 100, then **Create program**.
2. On the program page, press **Check all … people**. Watch the progress
   bar, then the results list.
3. Open a Not eligible person. In **Eligibility**, press **Show the …
   reasons**, then **Change the result**. Try to save without a reason (it
   is refused), then write one and save. Look at **Profile history** at
   the bottom.
4. Edit that person's governorate and save. The decision is marked
   "profile changed since the decision".
5. Change the program's rules. The program page offers **Check them again**.
6. In the **CV Bank**, choose **Journey stage → Assessed**, then try
   **Check these people against a program**.
7. Switch to **عربي** and dark mode and look at the same pages.

## If something goes wrong

| What you see | What to do |
|---|---|
| An error mentioning `eligibility_programs` | `php artisan migrate` was not run. |
| The menu item still says *Soon* | Run `npm run build` again, then refresh with Ctrl+F5. |
| A 403 page on Assessments | `php artisan config:clear` (the new permissions are read from the settings). |
| `php artisan test` shows failures | Copy the lines under **FAILED** and send them to me. |

## Files

**New**

- `database/migrations/2026_10_01_000016_create_eligibility_tables.php`
- `app/Models/EligibilityProgram.php`, `EligibilityAssessment.php`, `EligibilityRun.php`
- `app/Services/Eligibility/RuleBook.php`: what a rule can be, and its checks
- `app/Services/Eligibility/Evaluator.php`: checks one person, with no writing
- `app/Services/Eligibility/Assessor.php`: the only place results and decisions are written
- `app/Services/Eligibility/RunStepper.php`: "Check everyone"
- `app/Http/Controllers/App/EligibilityProgramController.php`, `EligibilityController.php`
- `app/Http/Requests/App/SaveEligibilityProgramRequest.php`
- `lang/en/assessments.php`, `lang/ar/assessments.php`
- `resources/js/Pages/App/Assessments/Index.vue`, `Form.vue`, `Show.vue`
- `resources/js/Components/Assessments/`: `EligibilityPanel.vue`, `RuleEditor.vue`,
  `OccupationChoice.vue`, `WordsInput.vue`, `elig.js`
- `tests/Feature/EligibilityTest.php` (14 checks)
- `docs/SCOPE_ELIGIBILITY_ASSESSMENT.md`, this note

**Changed**

- `routes/web.php`: the Assessments routes (instead of "coming soon")
- `config/permissions.php`: the three new permissions
- `app/Models/Beneficiary.php`: when a profile is saved, its results in open programs follow it
- `app/Services/Cv/CvBankSearch.php`, `CvBankController.php`: the Journey stage filter, and all the people found for "Check these people"
- `BeneficiaryController.php`: the Eligibility panel and the decision lines in the history
- `DashboardController.php` (partner and Super Admin): the pipeline and the counts
- `ComingSoonController.php`, `AppLayout.vue`: Assessments is no longer "Soon"
- Screens: `Beneficiaries/Show.vue`, `CvBank/Index.vue`, `Dashboard.vue`, `Admin/Dashboard.vue`, `translations.js`
