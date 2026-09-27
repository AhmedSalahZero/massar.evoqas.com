# Step 6.5 — CV headings and Job Responsibilities

An update to the CV Bank (Step 6), before Step 7 (Learned Rules).

## What you get

**1. The app knows every heading in your two reference files.**

- All **1,029 different headings** listed in the two files
  "CV Section Title Variations — Parser Reference" are in the app's
  word list, `app/Services/Cv/CvDictionary.php`.
  - They are marked *from your reference files*.
  - 29 more headings seen in your own CVs are marked *seen in the CVs
    you sent*.
- The two files are kept in `docs/cv-headings/`.
- An automated test reads them and checks that every heading is
  recognised. So if a heading is ever removed by mistake,
  `php artisan test` says which one.
- A heading is recognised however it is written:
  - capitals or not, singular or plural (*Skill / Skills*)
  - British or American spelling (*Honours / Honors*)
  - numbered (*1. Education*, *03 | Skills*)
  - decorated (*★ SKILLS ★*, *\*\* Objective \*\**)
  - with its letters spaced out by the PDF (*S ki ll s*)
  - bilingual (*Experience | الخبرات*)
  - combined (*Courses & Sessions*)
- New sections are recognised even though the form has no field for
  them: Internships, Military Service, Publications, Research,
  Memberships, Salary Expectations, Availability, Career Preferences,
  Additional Information. Knowing where they start stops their text from
  being read into the section above (for example, hobbies read as
  skills).
  - Internships are read as jobs.
  - The line under *Military Service* is read as the military status.
- A heading word used as a list item stays content. For example,
  *Communication Skills* inside the Skills list is kept as a skill.

**2. Job Responsibilities, under each job.**

- **On the form, the review screen and the profile:**
  - A *Job responsibilities* box under every job, one duty per line,
    up to 30 per job.
  - The profile page lists the duties under each job: the first 4,
    then *Show all*.
- **From a CV:** the lines under each job, up to the next job, are
  its duties.
  - When the job has a sub-heading (*Key Responsibilities:*,
    *Job description:*, *المهام الوظيفية* — 189 such headings from
    your files), only the lines under it are taken. A sentence about
    the company above it is not a duty.
  - Long lines that a PDF broke in two are joined again.
- **Never uncertain:** a CV without duties is **not** held back for
  review for that reason alone. Responsibilities are marked only
  *Found* or *Missing*, never *Check*.
- **Older profiles:** profiles made before this update simply have an
  empty box. Saving one unchanged does not add a false "work history
  changed" line to its history.

**3. Reading fixes found in your 21 CVs.**

- **Word files:** a tab in front of a heading was read as the two
  characters `\t`, so tabbed headings (*Education*, *Work Experience*)
  were never recognised. This was the biggest single cause of empty
  Word CVs.
- **Sub-headings inside a job** (*Key Responsibilities:*) used to be
  treated as unknown sections, which made every later job "uncertain".
- **Tables** (labels in one column, values in the next) are now
  paired: *Date of birth / Gender / … / September 20th, 1995 / Female*.
  Labels on their own line (*GENDER*, *Degree*, *POSITION:*) are no
  longer taken for unknown headings.
- **More ways of writing dates:**
  - special dashes (*15‐8‐2017*)
  - *June to September 2015*
  - *From 17-7 to 16-8-2017*
  - *Jan 2008 - Till April 2012*
  - *2015/2016*
  - *FEB / 2017*
  - *Since 26/2/2017*
  - *present time*
  - a single year at the start of a job (*2016 | Worked as …*), read
    but marked *Check*
- **Job titles:**
  - the title under the dates, or on a numbered line ending with ":"
    (*1- Attended an Internship at CI Capital:*)
  - *Worked as …* removed from the title
  - the employer found past bullets and a *POSITION:* label
- **Languages:** *Arabic | : Native* keeps its level, and *Basics* is
  a level word.
- **Month spelling mistakes:** *Augst, Abril, Seb*.

## Results on your 21 CVs

Measured with the same engine, before and after this update.
"Fields found" counts the 12 main fields marked *Found*: name, gender,
date of birth, military status, governorate, mobile, email, education,
education level, work history, skills and languages.

| CV | Fields found | Unknown headings | Jobs | Duties read | Newly found |
|---|---|---|---|---|---|
| Ahmad Diaa (PDF) | 11 → 11 | 2 → 0 | 1 → 2 | 22 | duties for both jobs |
| Asmaa Atef (Word) | 4 → 10 | 13 → 0 | 7 wrong → 2 right | 0 | date of birth, education, level, work history, skills, languages |
| Doaa Mahmoud (PDF) | 8 → 9 | 1 → 0 | 0 → 3 | 0 | date of birth, 3 jobs (single years, marked Check) |
| Dalia Oraby (Word, ×2) | 9 → 10 | 4 → 0 | 0 | 0 | languages |
| Engy Medhat (PDF) | 6 → 6 | 0 | 1 → 3 | 8 | 2 more jobs with dates, duties |
| Haidy Riad (PDF) | 5 → 5 | 1 → 0 | 2 | 3 | duties |
| Mahmoud Abdelkhalik (Word) | 5 → 7 | 5 → 0 | 1 → 2 | 0 | work history (2 internships), skills |
| Maram Ali (PDF) | 7 → 7 | 2 → 0 | 0 | 0 | — (her CV gives no date ranges) |
| Nada Salah (PDF) | 6 → 8 | 2 → 0 | 4 uncertain → 4 sure | 11 | work history, skills |
| Rania Abdel Wahab (Word) | 5 → 8 | 11 → 0 | 3 → 5 | 46 | education, level, work history |
| Rania Abdel Wahab (PDF) | 5 → 9 | 8 → 0 | 3 → 5 | 47 | name, education, level, work history |
| Reham Abdel Fatah (PDF) | 5 → 7 | 2 → 0 | 2 uncertain → 2 sure | 5 | work history, skills |
| Nourhan Gamal (Word) | 6 → 7 | 4 → 0 | 0 | 0 | skills |
| Salwa Ahmed (Word) | 9 → 10 | 2 → 0 | 0 | 0 | languages |
| Tarek El Gammal (PDF) | 3 → 9 | 17 → 0 | 3 | 7 | gender, date of birth, military, education, level, work history |
| Walaa Fakar (Word) | 8 → 9 | 2 → 0 | 0 | 0 | date of birth |
| Christine Magdy (Word) | 5 → 9 | 7 → 0 | 0 | 0 | gender, date of birth, education, level |
| Enas Mohamed (PDF) | 3 → 7 | 0 | 0 | 0 | date of birth, education, level, skills |
| Passant Magdy (PDF) | 5 → 6 | 1 → 0 | 2 | 1 | governorate and city |
| **Total (20 readable files)** | **124 → 165** | **88 → 0** | | **150** | |

*Andrew new CV.doc* is the old Word format and still says *Could not
be read*. Opening it in Word and choosing *Save As → .docx* fixes it.

**What still needs a person, and why (this is correct):**

- **Gender** when the CV has no gender line, no military status, no
  Arabic marital word and no national ID. Gender is never guessed from
  a name.
- **Years without months** ("2018 – 2020"), single years, and jobs
  with no dates at all.
- **Occupation:** job titles not in ENOC / ESCO / ISCO-08. This is what
  Step 7 (Learned Rules) solves.
- **Two-column PDFs** where the text of both columns is mixed (Haidy,
  Maram, Passant). Some fields are still read, but less reliably.

## Install (about 5 minutes)

**1. Copy the files.** Unzip the package and copy everything into your
`massar` folder, choosing **Replace**.

**2. Run these one at a time** in `D:\My Projects\massar`:

```
composer dump-autoload
php artisan config:clear
npm run build
php artisan test
```

- There is no `php artisan migrate` this time: the database does not
  change.
- You should see **84 passed** (71 before, 13 new). If any line says
  **FAILED**, copy the lines under it and send them to me.

## What to check on screen

1. Upload 3–5 of the CVs that used to come out mostly empty, for
   example Rania's, Christine's and Tarek's.
2. Open one in the **Review Queue**:
   - Headings should be **yellow**, with almost no **red**.
   - Each job should have its duties in the *Job responsibilities*
     box.
3. Change a duty, press **Approve**, and open the profile: the duties
   are listed under the job.
4. Open an **older profile**, press *Edit*, then *Save* without
   changing anything. Its history should not show a new change.
5. Switch to **عربي** and look at the same screens.

## If something goes wrong

| What you see | What to do |
|---|---|
| The *Job responsibilities* box does not appear | Run `npm run build` again, then refresh with Ctrl+F5. |
| `php artisan test` shows failures | Copy the lines under **FAILED** and send them to me. |
| A CV is still read badly | Send it to me (with personal details removed if you prefer). |
