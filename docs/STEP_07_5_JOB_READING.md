# Step 7.5 — Reading each job by what its lines are

An update to the CV reading engine (still rule-based, no AI), after
Step 7 (Learned Rules) and Step 8 (Searchable CV Bank).

## The problem it fixes

The reader used to decide by **position**. For example, it assumed
"the line above the dates is the title". A CV written as *Company /
Title / Date*, or *Date / Title – Company, Country*, came out with the
title and the employer swapped, a country inside the employer, and
details such as *Industry: FMCG* read as a job title.

## What the reader does now

**1. Every line is labelled by what it is.**

| Kind of line | How the reader knows |
|---|---|
| A date | A job period: *Mar 2021 – Present*, *من 2019 حتى الآن*, *2016 – 2019* |
| A job title | It is one of the official titles already in the app (ENOC, ESCO titles and their other names, ISCO-08, in Arabic and English), or a title you taught (Learned Rules). Or it contains a title word: *Manager, Accountant, Engineer, Officer, محاسب, مدير…* |
| An employer | Company words: *Co., Ltd, Group, Bank, Foods, Hotel, Center, شركة, مجموعة…*; or it comes after *at / @ / لدى / بشركة*; or it ends with *Egypt / Misr* (*Vodafone Egypt*); or it is an employer you taught, or one already typed in a profile of your workspace |
| A place | Only a place: *Cairo, New Cairo, Riyadh, KSA, Doha, Qatar, Dubai…* |
| A duty | A bullet, a sentence, or a line that starts with a verb (*Managing…, Prepared…, إعداد…*) |
| A detail | *Industry:*, *Website:*, *Reporting to:*, *Department:* are skipped. *Company:*, *Position:* and *Location:* go to their own box |

**2. A line with several parts is cut first.** For example, *Project
Engineer | Hassan Allam Holding | Riyadh, KSA | 2019 – 2023* becomes a
title, an employer, a place and dates.

**3. Each part gets points.** The part with the most title points is
the title, and the one with the most employer points is the employer.
The main word counts extra: *Bank **Teller*** is a title, *Nile Foods
**Co.*** is an employer.

**4. Which lines go with which dates.** The reader takes the lines
right above or right below each date line.

- It stops once the job has a title and an employer, so the next job's
  lines are never taken.
- Some CVs have no duties between jobs. There, **the CV's own habit
  decides**. The reader learns from the clear jobs in the same CV
  whether this person writes the title above or below the dates, and
  whether the title or the company comes first.

So *Company / Title / Date*, *Title / Company / Date* and *Date /
Title – Company, Country* all come out the same.

**5. Never guessed silently.** A job is marked **Check** on the review
screen, with the reason, when:

- two lines both look like the job title, or
- nothing shows which line is the title, or
- the dates are years only, or missing.

**6. The rest of the fixes**

- **A second title under the same company, without dates** (for
  example *Financial Analyst* after the *Chief Accountant* duties)
  becomes its own job. It keeps the same employer and is marked Check
  for its dates.
- **Two titles with their own dates under one company line**
  (*Vodafone Egypt (2015 – Present)*, then *Team Leader (2018 –
  Present)* and *Agent (2015 – 2018)*) become two jobs at Vodafone
  Egypt.
- **A numbered line** (*2) Chief Accountant*) starts a new job.
- **"Majors:", "Grade:" and "License number:"** are details inside
  Education, not headings. *Majors:* fills the field of study.
- **Degrees and courses with years stay out of the work history**,
  even under a heading the app does not know (*ACADEMIC PATH*,
  *LEARNING JOURNEY*). Degrees go to Education. Courses and
  certificates are left out.
- **A heading split over two lines** (*LANGUAGE* / *SKILLS:*) is joined
  back together.
- **PDF lines broken in two** are joined again, including a title
  whose last word ended up on the next line (*… Organizational
  Development* / *Specialist*).

## On the review screen (and the profile form)

- **⇄** between *Job title* and *Employer*: swaps the two in one click.
- **Split into two jobs**: put the cursor on the responsibility line
  where the second job starts, then press it. That line becomes the new
  job's title, and the lines under it become its duties. The new job
  keeps the same employer.
- **Merge with the job above**: when a job is really part of the job
  above, its title goes back into the duties of the job above.
- **Location** box on every job, filled from the CV (*Riyadh, KSA*).
  It is shown on the profile and found by the CV Bank search.
- An orange **Check this job** line explains what to look at.

## Learned Rules: employers

- A new kind of rule, **Employer**, sits next to Heading, Job title and
  Skill word. Teach *Etisalat Misr* once, and the reader always knows
  that line is the employer.
- **Employers from your own profiles.** Every employer already typed in
  a profile of your workspace (the latest 3,000 profiles) is known to
  the reader automatically. So each CV you approve teaches the next
  ones.
- A job title or a place typed in the Employer box by mistake is not
  learned.

## The test set: proof that a change helps

`tests/Fixtures/cv-gold/` holds test CVs, each with a `.json` file that
says what a careful person would type into the form.

- **35 CVs** cover every layout above, plus the earlier checks.
- **15 more** were written afterwards and not tuned in advance. The
  first time, 9 of the 15 came out fully right. The 6 misses were
  fixed, and 1 more CV was added to check the official titles.

```
php artisan cv:score
```

This reads every CV in the set and reports, for example:
**Fully correct: 51 of 51 CVs · jobs right: 100 of 100.** Anything
different from the correct answers is listed with the reason.

Before this update, the old reader scored **12 of 35**.

**To add your own real CVs to the set:**

```
php artisan cv:score --add="C:\CVs\Some CV.pdf"
```

1. Run the command. The file is copied into the folder, and a `.json`
   is written with what the reader reads today.
2. Open the `.json` in Notepad.
3. Correct what is wrong, and save.

From then on, every change to the reader is checked against that CV.
⚠ These are real people's CVs, so keep that folder on your computer
and don't send it outside your organisation.

`php artisan test` also runs the whole set (tests/Feature/CvGoldSetTest).

## Files changed

- `app/Services/Cv/CvReader.php`: section 7 rewritten; two-line headings;
  headings the app does not know are decided by their content; *Majors:*
- `app/Services/Cv/CvDictionary.php`: new lists: title words, duty
  words, places outside Egypt, job labels, course words; more company
  words; postgraduate diplomas
- `app/Services/Cv/CvGoldSet.php`, `app/Console/Commands/ScoreCvReader.php`,
  `tests/Fixtures/cv-gold/`, `tests/Feature/CvGoldSetTest.php`: the
  test set
- `app/Services/Cv/LearnedRuleBook.php`, `app/Models/LearnedRule.php`,
  `app/Http/Requests/App/SaveLearnedRuleRequest.php`: employer rules and
  employers from profiles
- `app/Http/Requests/App/SaveBeneficiaryRequest.php`,
  `app/Services/Beneficiaries/BeneficiaryRecorder.php`, `CvIntake.php`,
  `CvProfileUpdate.php`, `CvBankIndex.php`, `CvBankSearch.php`: the job
  location; the Check notes are never saved
- Screens: `BeneficiaryForm.vue` (⇄, Split, Merge, Location, Check),
  `Show.vue`, `Update.vue`, the Learned Rules screens, `translations.js`
- No database change: nothing to migrate.
