# Steps 14 and 15 — Reports and the Dashboard

*Reports: Scope v2 Module C, agreed as recommended (`docs/SCOPE_REPORTS.md`).*
*Dashboard: built exactly as the agreed demo (`docs/dashboard-demo.html`).*

Both come in one package and install together.

---

## Step 14 · Reports

**Reports** in the menu is a real page now (no longer "Soon").

**1. The six filters of the Scope, all together.** An empty filter means
"everyone".

| Filter | How |
|---|---|
| **Occupation** | Any standard (ENOC, ISCO-08, ESCO), any level. Several = a person in *any* of them |
| **Industry** | Sector or sub-sector of the **current job, or the last one** |
| **Gender** | Both · Women · Men |
| **Age** | From … to … years (people with no date of birth are left out) |
| **Experience** | From … to … years |
| **Expected salary** | From … to … EGP a month |
| *Added* | Governorate · Education level · Journey stage (Registered … Placed) |

**2. Show:** the **number of people**, or the **average** age, experience or
expected salary. Every average says how many people it is **based on**;
people who did not give that value are not counted in it. Nothing is guessed.

**3. Split by** one thing, or two (a cross-table): occupation (at the level
you choose: major group … 4-digit group … ESCO job), industry (sector or
sub-sector), gender, age band, experience band, salary band, governorate,
education, journey stage, month or quarter of registration.
When the average expected salary is split by 4-digit occupation, the
**Egypt market wage** is shown beside it.

**4. The date range:** this month, 3 months, this year, last year, all
time, or two dates. It is the date the person was **registered**. With the
stage filter **Placed**, it is the date they were **hired or completed**.

**5. The result:** the question in words, a chart (bars, or a line for
months and quarters; the biggest 15, the rest as "Other"), and the table
with totals.

**6. Excel and PDF**, in the language on the screen. Excel: the table, then
a second sheet with the filters in words, who made it and when; Arabic sheets
are right-to-left. PDF: A4, with the chart and the table; Arabic letters are
joined and written right-to-left. **Every download is recorded.**

**7. Save** a report under a name. The whole team sees it; only the person
who saved it (or a Company Admin) can rename or delete it. Only the question
is saved: the numbers are worked out again each time.

**8. Open these people:** click any number to open exactly those people in
the **CV Bank** (a blue note says which report they came from). From there
you can check them against a job or training, as usual.

**9. Super Admin:** *Reports* in the platform menu. Every partner together,
**counts only**, no names; any number from 1 to 4 shows as **"fewer than
5"**. The public pool's own profiles are never counted.

**10. Permissions** (every partner user holds both during the build):
*View reports* (`reports.view`) and *Export reports* (`reports.export`).

---

## Step 15 · The Dashboard

The partner's **Dashboard** is now exactly the demo, with real numbers.
The buttons at the top: **30 days · 3 months · 12 months · All time**, and
**Download PDF** (your browser opens its print window: choose *Save as PDF*).

| Part | What it shows |
|---|---|
| **Five numbers** | Beneficiaries (+ new in the period) · CV Bank (% read automatically) · open jobs & training (seats) · placed (hired · completed) · placement rate (and days on average from referral to placement). Small monthly lines under three of them |
| **The placement journey** | Of the people registered in the period: how many were assessed, eligible, matched and placed, with the % from each stage to the next |
| **Needs your attention** | Matches with no change for 14 days · results to look at again · CVs waiting for review · deadlines passed · jobs with all seats taken. Each opens its list |
| **Your people** | New people by month, **by how they joined** (Guided intake · CV upload · Public Talent Pool · by hand) · placements by month · gender · education · age · governorates · industry. The two monthly charts can switch to a table |
| **Your people and Egypt's labour market** | The top 10 occupations of your people, in the standard of the top-bar switch, next to the **real Egypt market wage and outlook to 2030** · expected salary vs market wage · the ESCO skills most lacking among Eligible people (ideas for training) |
| **Jobs & Training** | Open jobs and trainings (eligible, referred, seats taken, deadline) · matches that need a follow-up · team activity in the period |

Point at any bar, dot, month or stage to see its numbers. Parts a person is
not allowed to see are simply not shown.

The **Super Admin's dashboard** is the demo's second tab: partners, team
members, beneficiaries, CVs and placements across partners (**counts
only**), new people by month, the occupation backbone, the Public Talent Pool,
rule requests waiting, and one line per partner.

**Two new facts are kept on every profile** (filled in automatically for
the people you already have):
- **how the person joined**. For people registered before this step it is
  worked out from what is known: added from the pool → *Public Talent Pool*;
  a CV uploaded on *Upload CVs* → *CV upload*; a CV given in Guided Intake →
  *Guided intake*; otherwise → *by hand*. (Old Guided Intake registrations
  without a CV cannot be told apart from the form, so they show as *by hand*.)
- **the industry of the current or last job** (for Reports and the dashboard).

---

## Install (about 5 minutes)

Nothing is deleted in these steps. The package also carries **Step 13
(Matches)**: if you already installed it, those files are simply the same;
if not, it is installed together with these steps (see `docs/STEP_13_MATCHES.md`
for what to look at).

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

- `php artisan migrate` adds the two profile facts above (and fills them in
  for everyone already registered), the saved reports, and the download log.
- You should see **169 passed** (the 155 from before, 9 for Reports and 5
  for the Dashboard; 2 are skipped, as before). If any line says
  **FAILED**, copy the lines under it and send them to me.

## What to check on screen

1. **Dashboard:** press 30 days, 3 months, 12 months, All time. Point at the
   bars and the lines. Press *Table* on "New people by month".
2. Switch the top-bar standard to **ISCO-08** and **ESCO**: the occupations
   table follows it.
3. Click **Matches with no change for 14 days** in "Needs your attention".
4. **Reports:** choose an occupation (type "account"), Women, Age 20 to 35.
   Split by **Governorate** and by **Gender**. Click a number: the CV Bank
   opens with exactly those people.
5. Back in Reports: **Show → Average expected salary**, split by
   **Occupation**: the Egypt market wage appears beside it.
6. Press **Excel** and **PDF**. Switch to **عربي** and press them again.
7. **Save** the report; open it again from *Saved reports*.
8. Sign in as the **Super Admin**: the new Dashboard, and *Reports* in the
   platform menu (a small number shows as "fewer than 5").
9. Switch to **عربي** and dark mode and look at the same pages.

## If something goes wrong

| What you see | What to do |
|---|---|
| An error mentioning `saved_reports`, `industry` or `source` | `php artisan migrate` was not run. |
| Reports still says *Soon*, or the old dashboard shows | Run `npm run build` again, then refresh with Ctrl+F5. |
| A 403 page on Reports | `php artisan config:clear`. |
| The skills table on the dashboard stays empty | People must be checked against an **open** job or training, and the ESCO skills loaded (`php artisan skills:import`, Step 5). |
| The industry chart shows only "No work history yet" | Work histories need a sector (Step 10.5); the sector list comes from `php artisan employers:import`. |
| `php artisan test` shows failures | Copy the lines under **FAILED** and send them to me. |

## Files

**New**

- `database/migrations/2026_10_04_000019_create_reports_tables.php`
- `app/Models/SavedReport.php`, `app/Models/ReportDownload.php`
- `app/Services/Reports/ReportBuilder.php`: the filters, counts and averages, the split
- `app/Services/Reports/ReportExporter.php`: Excel and PDF
- `app/Services/Dashboard/PartnerDashboard.php`, `AdminDashboard.php`: every dashboard number
- `app/Support/ArabicShaper.php`: joined Arabic, right to left, in PDFs
- `app/Support/ScreenWords.php`: the screens' own names (governorates …) for Excel and PDF
- `app/Http/Controllers/App/ReportController.php`
- `lang/en/reports.php`, `lang/ar/reports.php`
- `resources/js/Pages/App/Reports/Index.vue`, `resources/js/Pages/Admin/Reports/Index.vue`
- `resources/js/Components/Reports/ReportPage.vue`
- `resources/js/Components/Dashboard/charts.js`, `DashTooltip.vue`, `dash.css`
- `tests/Feature/ReportsTest.php` (9 checks), `tests/Feature/DashboardTest.php` (5 checks)
- `docs/SCOPE_REPORTS.md` (agreed), `docs/dashboard-demo.html` (the agreed demo), this note

**Changed**

- `routes/web.php`: Reports (partner and Super Admin), instead of "coming soon"
- `app/Http/Controllers/App/DashboardController.php`, `Admin/DashboardController.php`: the new dashboards
- `app/Services/Beneficiaries/BeneficiaryRecorder.php`: how the person joined, the industry
- `app/Http/Controllers/App/IntakeController.php`, `ReviewQueueController.php`, `app/Services/Cv/CvIntake.php`, `app/Services/Pool/TalentPool.php`: say how the person joined
- `app/Services/Cv/CvBankSearch.php`, `app/Http/Controllers/App/CvBankController.php`, `EligibilityController.php`: "Open these people"
- `app/Http/Controllers/ComingSoonController.php`: Reports removed from "coming soon"
- Screens: `Pages/App/Dashboard.vue`, `Pages/Admin/Dashboard.vue`, `Pages/App/CvBank/Index.vue`, `Layouts/AppLayout.vue`, `Layouts/AdminLayout.vue`, `lang/translations.js`

**Removed**

- Nothing. No file to delete by hand.
