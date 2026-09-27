# Step 10.5: Business sectors, companies and countries

## What is new

Every job in a work history now has three more pieces of information:

| | What it is | Example |
|---|---|---|
| **Country** | Where the job was. Egypt by default. | Saudi Arabia |
| **Sector** | Industrial, Trading or Service | Service |
| **Sub-sector** | One of 50 sub-sectors | Banking |

**The employer box searches the list of companies.**

- Type "vodaf" or "فودا" and "Vodafone Egypt · Telecommunications" appears.
  Pick it, and the sector and sub-sector are filled in for you.
- A company that is not in the list is never a problem. The last line
  always says **"Use '…' as written"**, and you then choose the sector
  yourself.
- For jobs outside Egypt, the Egyptian list is not searched.

**CVs.**

- The reader finds each job's country from its place. For example,
  "Riyadh, KSA" becomes Saudi Arabia, and "Dubai" becomes UAE.
- It then looks up the employer in the list. With one clear match, the
  sector is filled in.
- If two companies in the list have the same name, the job is marked
  **Check**.
- Short one-word names (Orange, Corona, WE) only count when they are the
  whole employer line. So "Corona Hospital" is not Corona.

**Massar learns.**

- When a caseworker saves a job at a company that is not in the list and
  chooses its sector, that workspace remembers the company. The next CV
  with that company is filled in automatically.
- Other partners do not see it.
- Nothing is learned from the public site.

**Filters.** The CV Bank and the Talent Pool have a new filter: "worked
in this sector or sub-sector".

**The sector is not required.** An empty sector shows an orange hint, but
it never blocks saving.

## Update: selectors (after your review)

- **Employer:** a selector with ▾. Click it to open the list (staff first
  see the companies their workspace added lately), and type to narrow it.
  The last line is always there:
  - staff: **"+ Add new company: '…'"** → added to *that workspace's* list
    when the profile is saved;
  - job seekers: **"+ My company is not in the list"** → kept on their
    profile only; the public site never changes any list.
- **⇄** still swaps Job title and Employer. After a swap, the name is
  looked up at once, and a known company gets its sector without opening
  the list. ⇄ is now also in the question-by-question journey (New Intake
  and public registration) when a CV was read.
- **Location follows the country:**
  - Egypt: **Governorate** (the 27) + City or area.
  - Abroad: **City**, with a ▾ list of the country's main cities (Riyadh,
    Jeddah, Dubai, Doha…), or type any city.
  - CVs fill it: "Nasr City, Cairo" → Cairo; "Riyadh, KSA" → Saudi Arabia ›
    Riyadh.
- **Sub-sector "+ Other (not in the list)":** type it. The Super Admin
  sees these under **Sectors** (most used first), and can:
  - **add it** to the official list (English + Arabic names);
  - say it is **an existing** sub-sector;
  - or **decline** it.

  Adding or merging moves every job that typed it to the official
  sub-sector.
- **Job seekers** also get **"I don't know"** for the sector.

## The list of companies: the Excel file

The file lives at:

```
database\data\employers\employers.xlsx
```

(It is the "Massar_Egypt_Employers_Starter_List.xlsx" file: 238 companies,
3 sectors, 50 sub-sectors.) To load it, run:

```
php artisan employers:import
```

**To add companies later:**

1. Open `employers.xlsx`.
2. Add the new companies:
   - in the **"Add companies"** sheet: choose the sub-sector from the
     drop-down list; or
   - as new rows in the **"Companies"** sheet: put the code in
     "Sub-sector code".
3. Save the file in the same place.
4. Run `php artisan employers:import` again.

Running it again is safe:

- a company is found by its English name (or by its Arabic name when it
  has no English one);
- new companies are **added**, and changed ones are **updated**;
- nothing is duplicated or deleted;
- if the file has a mistake, **nothing changes**, and the message says
  which row is wrong.

Only company names and sectors belong in this file: no people's names,
emails or phones.

## Installing

```
php artisan migrate
php artisan employers:import
npm run build
```

## Files

- `database/migrations/2026_09_30_000014_create_sectors_and_employers_tables.php`
- `database/data/employers/employers.xlsx`
- `config/countries.php`: the countries, and the place names that point
  to them
- `app/Models/Sector.php`, `app/Models/Employer.php`
- `app/Services/Employers/EmployerBook.php`: matching, suggestions,
  learning
- `app/Services/Employers/EmployerImporter.php` and
  `app/Console/Commands/ImportEmployers.php`
- `app/Http/Controllers/EmployerSearchController.php`: the suggestions
- Changed:
  - `SaveBeneficiaryRequest` (the new job fields and their rules)
  - `BeneficiaryRecorder` (learning, and the history of the new fields)
  - `CvIntake` (CV jobs are matched)
  - `CvBankSearch`, `CvBankController`, `PoolController` (the sector
    filter)
  - `BeneficiaryController` (the lists for the forms, and sector names on
    the profile)
- Screens:
  - `Components/Employers/EmployerInput.vue`, `JobSector.vue`
  - `BeneficiaryForm.vue` (staff form and CV review)
  - `IntakeJourney.vue` (New Intake and public registration)
  - the profile pages, and the CV Bank and Talent Pool filters
- Checks: `tests/Feature/EmployersTest.php` (3 new tests). Some expected
  results in older tests now include the job's country.
