# Massar — Reports: Scope of Work

*Version 2 · 27 September 2026 · Module C (Insights)*
*Agreed as recommended. Decisions: section 12. Built in Step 14 (`docs/STEP_14_REPORTS.md`).*

This document expands **Reports** in the Massar Scope of Work v2. The
scope's own words are:

> Combined filters: occupation (any standard), industry, gender, age,
> experience, salary. Counts or averages, any date range, Excel / PDF export.

It builds on everything before it: the profiles and their occupations in
the three standards (Steps 3–4), work histories with their sectors
(Step 10.5), eligibility (Step 11), Jobs and Training (Step 12) and Matches
(Step 13).

---

## 1. The idea in one paragraph

A report answers one question about the workspace's **people**, for
example *"How many women aged 22–30 with 2+ years in Banking are Eligible
for a job, by governorate?"* or *"What is the average expected salary of
accountants, by years of experience?"*. The case worker **chooses the
filters** (all of them together), **chooses what to show** (a count or an
average), **chooses how to split it** (by occupation, by governorate …),
and gets a **table and a chart** at once. The same report downloads as
**Excel** or **PDF**, and can be **saved** to be opened again next month
with new numbers.

```
Filters (all together)  →  Show (count / average)  →  Split by (1 or 2)  →  Table + chart  →  Excel · PDF · Save
```

---

## 2. The screen

One page, **Reports** in the menu (no longer "Soon"), in three parts:

| Part | What is in it |
|---|---|
| **Filters** (left, or top on a phone) | Every filter of section 3, each can be left empty |
| **Show and split** (top) | Count or average (section 4), split by one or two things (section 5), the date range (section 6) |
| **Result** | The chart, the table under it with totals, and the buttons **Excel**, **PDF**, **Save**, **Open these people** |

The result updates when a choice changes. A line at the top says in words
what is shown, for example: *"People registered 1 Jan – 30 Sep 2026 ·
Women · Age 22–30 · Banking · counted by governorate: 312 people."*

---

## 3. The filters (combined: every filter applies together)

| Filter | How it is chosen | Notes |
|---|---|---|
| **Occupation** | One or more, **any standard** (ENOC, ISCO-08, ESCO) and **any level** (major group down to an ESCO job) | A person matches if they are in **any** of the chosen ones. Uses the same codes at every level as the CV Bank. |
| **Industry** | Sector and sub-sector (the list from Step 10.5) | From the person's work history (see section 12, point 2) |
| **Gender** | Men, women, or both | |
| **Age** | From … to … (years) | People with no date of birth are left out when an age is chosen, and counted as *"not given"* when splitting by age |
| **Experience** | From … to … years | From the work history |
| **Expected salary** | From … to … EGP a month | People who gave none are left out when a range is chosen |
| Governorate | One or more of the 27 | Added: most questions need it |
| Education level | One or more | Added |
| Journey stage | Registered · Assessed · Eligible · Matched · Placed | Added: turns every report into a placement report |

Empty filters mean *"everyone"*. The filters can be cleared in one click.

---

## 4. Show: a count or an average

| Choice | What each cell shows |
|---|---|
| **Count of people** (default) | How many people, and their **share** of the total (%) |
| **Average age** | In years, one decimal |
| **Average experience** | In years, one decimal |
| **Average expected salary** | In EGP a month, with the **Egypt market average wage** next to it when the split is by occupation (the same published figures as the profile) |

Every average also says **how many people it is based on** (for example
*"6,850 EGP · 42 people"*), and people who did not give that value are not
counted in it. Nothing is guessed.

---

## 5. Split by (one or two)

The table's rows, and optionally its columns (a cross-table):

- **Occupation**, at the level chosen: major group (1 digit), sub-major (2),
  minor (3), 4-digit group (= ENOC occupation), or ESCO job. In the
  standard of the top-bar switch.
- **Industry**: sector, or sub-sector.
- **Gender** · **Age band** (under 20, 20–24, 25–29, 30–34, 35–44, 45+)
- **Experience band** (none, under 1 year, 1–2, 3–5, 6–10, over 10)
- **Salary band** (under 4,000, 4,000–5,999, 6,000–7,999, 8,000–9,999, 10,000–14,999, 15,000+)
- **Governorate** · **Education level** · **Journey stage**
- **Month** or **quarter** of registration (a line through time)

The chart follows the split: bars for categories, a line for months and
quarters, grouped bars for a cross-table. Very long lists show the top 15
and put the rest in **"Other"** (the table always has every line).

---

## 6. The date range

- The range applies to **when the person was registered** (created in the
  workspace: by hand, Guided Intake, CV upload or the Public Talent Pool).
  See section 12, point 3.
- Quick choices: this month, last 3 months, this year, last year, all time,
  or two dates.

---

## 7. Excel and PDF

| | Excel (.xlsx) | PDF (A4) |
|---|---|---|
| **Contains** | Sheet 1: the table with totals. Sheet 2: the filters in words, the date range, who made it and when | The title, the filters in words, the chart, the table, the date and who made it, page numbers |
| **Language** | The language on screen; Arabic sheets are right-to-left | The language on screen; Arabic is written right-to-left with joined letters |
| **Workspace** | The workspace name at the top | The workspace name and logo colours at the top |

Each download is recorded (who, when, which report), like CV downloads.

---

## 8. Saved reports

- **Save** keeps the filters, the choice and the split under a name, for
  example *"Women in Banking, by governorate"*.
- Saved reports are shared with the **team of the workspace**. The numbers are
  always worked out again when opened: a saved report is a question, not a
  copy of old numbers.
- The person who saved it (or a Company Admin) can rename or delete it.

---

## 9. "Open these people"

From any number in the table, **Open these people** opens the **CV Bank**
with the same filters, so the case worker goes from a number to the names
(and can check them against a job, as today). Only in the partner's own
workspace.

---

## 10. Privacy and isolation

- A partner's reports count **only its own workspace**. Another partner's
  people are never counted and never seen.
- A person added from the Public Talent Pool counts once, as the partner's
  own copy.
- The Super Admin: see section 12, point 6.

---

## 11. Permissions

Both already exist in the permission backbone. During the build, every
partner user holds them.

| Permission | Allows |
|---|---|
| `reports.view` | The Reports page, the results, saved reports, "Open these people" (also needs `beneficiaries.view`) |
| `reports.export` | The Excel and PDF downloads |

---

## 12. Decisions (agreed 27 September 2026, as recommended)

| # | Question | Decision |
|---|---|---|
| 1 | Reports are about **people** only (with the journey stage as a filter and a split, which covers matches and placements)? | **Yes**. Jobs, trainings and matches have their own lists and the dashboard. |
| 2 | **Industry** means the sector of which job in the work history? | The **current or most recent** job. (Option: *any* job the person had.) |
| 3 | The date range applies to? | The **registration date**. When the split or filter is *Placed*, the date it happened (hired / completed) is used instead. |
| 4 | The averages offered | **Age, experience and expected salary** (salary with the market average beside it) |
| 5 | Saved reports shared with the whole team? | **Yes** |
| 6 | A **Super Admin** report across all partners? | **Yes, counts only**, with no names. Any number **under 5 is shown as "fewer than 5"** so no one can be recognised. |
| 7 | Split by **two** things at once (a cross-table)? | **Yes**, at most two |

---

## 13. Not in this step

- **Scheduled reports** sent by email every month (can come later).
- Reports on the **CV reading** quality (the CV reader has its own checks).
- The **Partner Dashboard**: its own step (Step 15), built exactly as the agreed demo (`docs/dashboard-demo.html`).

---

## 14. How it will be checked

As with every step:

- automated checks (`php artisan test`): every filter alone and together,
  occupations in the three standards and every level, industry, the bands,
  counts and averages (and "based on N people"), the date range, the
  cross-table, Excel and PDF (English and Arabic), saved reports, "Open these
  people", permissions, the Super Admin's "fewer than 5", and that one
  partner can never count another partner's people;
- every new screen opened in a simulated browser, in Arabic and English;
- the install rehearsed from your current project before you receive it;
- a step note (`docs/STEP_14_REPORTS.md`) with simple install steps.
