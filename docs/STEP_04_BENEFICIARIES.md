<!--
  Massar — Step 4: beneficiary profiles
  Location: docs/STEP_04_BENEFICIARIES.md
  How to install this step and what to check. Written for a
  non-programmer: copy each command exactly.
-->

# Step 4 · Beneficiary profiles

## What this step adds

- **All Beneficiaries** in the partner menu now opens a real page: the
  partner's own list, with search (name in Arabic or English, mobile,
  email or number) and filters (governorate, gender, education,
  occupation group, minimum experience).
- **Register a beneficiary**: name in Arabic and/or English, gender,
  date of birth, military service (men only), governorate and area,
  mobile and email, education, work history, skills, languages,
  expected salary and preferred job type. Only a name, gender,
  governorate and a mobile or email are needed; the rest can be
  completed later.
- **Occupation** is chosen with the same search as the Occupations
  page. The detailed ESCO job is saved; it is shown in ENOC, ISCO-08
  or ESCO, following the switch in the top bar. The 10 groups ESCO
  does not detail are saved as "group level only".
- **The profile page**: details, the occupation in three standards
  (with who chose it and when), the **expected-salary check** against
  the market average and the private-sector average, the **Egypt
  labour market panel**, work history, education, skills, languages
  and the **profile history**.
- **Partner separation**: every partner sees only its own
  beneficiaries. Another partner's profile simply does not exist for
  them ("not found"). Each partner numbers its own people: No. 1, 2, 3…
  The Super Admin cannot open beneficiary records.
- **Who did what**: every registration and every change is recorded
  with the person's name, the time, and what changed
  (e.g. "Governorate: Cairo → Giza"). Nobody can edit this history.
- **Same mobile or email** inside the same workspace: the case worker
  is warned and must confirm it is a different person. This check never
  looks at other partners.
- The partner dashboard shows the number of beneficiaries.

## Install (about 3 minutes)

1. Extract the zip into your `massar` folder and choose **Replace the
   files**. This only adds and updates files.
2. In the terminal, inside the `massar` folder, run one at a time:

```
composer dump-autoload
php artisan migrate
npm run build
php artisan test
```

| Command | Expected result |
|---|---|
| `composer dump-autoload` | ends with "Generated optimized autoload files" |
| `php artisan migrate` | one line: `create_beneficiaries_tables … DONE` (nothing else is touched) |
| `npm run build` | ends with "✓ built in …" |
| `php artisan test` | **51 passed** (the 41 from before + 10 new beneficiary tests) |

## Check it yourself (10 minutes)

Sign in as the **demo case worker** (caseworker@alamal.test).

1. Open **All Beneficiaries**. It says there are none yet.
2. Press **Register a beneficiary**. Fill a name, choose Female,
   Cairo, and type the mobile as `+20 100 123 4567`.
3. In **Occupation**, type **محاسب**. Choose "Accountant". Switch the
   top bar between ENOC, ISCO-08 and ESCO: the choice follows it.
4. Add a job with a start month and tick **Current job**. Add two
   skills (press Enter after each). Type an expected salary of
   **9,000**. Press **Register**.
5. The profile opens. Check: the mobile shows as `01001234567`; the
   occupation cards; the **Expected salary check** says "Above it"
   with the note about the survey period; the labour market panel;
   the history says "Registered by Karim Adel".
6. Press **Edit profile**, change the governorate, save. The history
   now shows who changed it and "Cairo → Giza".
7. Register another person with the **same mobile**. A warning names
   the first person; tick "This is a different person" to continue.
8. Back on the list: search **سارة** (and try it without the hamza,
   e.g. احمد for أحمد), and try the filters.
9. Switch to **عربي** and check the pages read well.

**Separation check:** sign out and sign in as another partner (create
one as Super Admin if needed). Their list is empty, and opening
`/app/beneficiaries/1` says "Not found".

## If something goes wrong

| What you see | What to do |
|---|---|
| The menu item still says "Coming soon" | Run `npm run build` again, then refresh with Ctrl+F5. |
| "The occupation backbone is not loaded yet" on the form | As Super Admin run `php artisan backbone:import` and `php artisan market:import`. |
| "403 · Forbidden" for a user | That user's permissions do not include the beneficiary permissions. |
| An error mentioning `beneficiaries` table | `php artisan migrate` was not run. |
