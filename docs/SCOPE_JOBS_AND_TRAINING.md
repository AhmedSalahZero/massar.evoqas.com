# Massar — Jobs & Training (with Eligibility inside): Scope of Work

*Version 2 · 27 September 2026 · Module B (Beneficiary & Services Management)*
*Agreed. Decisions: sections 2 and 13. Built in Step 12 (`docs/STEP_12_JOBS_AND_TRAINING.md`).*

This document expands **Job & Training Opportunity Postings** in the Massar
Scope of Work v2, section 3. The scope's own words are:

> Partners post job openings and training slots with requirements and
> capacity. Required occupations are chosen from the backbone in any standard.
> *Outcome:* Opportunities and beneficiaries share one occupation language,
> which makes matching reliable.

It also **reshapes Step 11 (Eligibility Assessment)**, as agreed on
27 September 2026: eligibility is no longer created on its own. It lives
**inside** each Job and each Training Program.

---

## 1. The idea in one paragraph

A partner posts an **opportunity**: a **Job** or a **Training Program**.
Each opportunity holds two things together: its **details** (what it is,
where, how many places) and its **eligibility rules** (who is suitable).
They are created in one form and saved once. Massar checks beneficiaries
against the opportunity, shows the shortlist with every reason, and the
case worker makes the final decision. The next module, Matching, refers
the eligible people to that same opportunity.

```
+ New Job / + New Training
        │
        ├── Details        (title, occupation, place, seats, dates …)
        └── Eligibility    (the rules and the two result levels)  ← required
        │
   Check people  →  Shortlist (Eligible · Check · Not eligible)
        │
   Matching (next step)  →  Referred → Accepted → In progress → Completed / Hired
```

---

## 2. Decisions already made (27 September 2026)

| # | Decision |
|---|---|
| 1 | Eligibility is **part of** each Job and Training Program, never created separately. |
| 2 | Jobs and Training are built **on top of** the existing eligibility engine. The engine is reused, not rebuilt. |
| 3 | The Eligibility section is **required**: an opportunity cannot be saved without at least one rule. |
| 4 | There is **no "general screening"** assessment outside a job or training. |

---

## 3. One build, two kinds

Jobs and Training share one design, one set of screens and one list of
rules. They differ only in a few details (section 4). The menu keeps two
items under **Opportunities**, so each one has its own list:

| Menu | Shows |
|---|---|
| **Jobs** | the workspace's job postings |
| **Training Programs** | the workspace's training postings |
| **Matches** | still *Soon* (next step) |

The **Assessments** menu item is **removed** (section 8).

---

## 4. The details

### Shared by both

| Field | Required | Notes |
|---|---|---|
| Title | Yes | **One field**, written in Arabic or English |
| Description | No | What the job or training is about |
| Occupation(s) | Yes | At least 1; **the partner decides how many** (a safety ceiling of 100). Chosen from the backbone in any standard (ENOC, ISCO-08, ESCO), at any level |
| Governorate(s) | Yes | Where it takes place. One or more of the 27 |
| City or area | No | For example *Nasr City* |
| Seats | Yes | How many people can be placed or trained |
| Application deadline | No | After this date a reminder shows; it does not close by itself |
| Start date / End date | No | |
| Contact person | No | The staff member responsible, chosen from the team |

### Job only

| Field | Required | Notes |
|---|---|---|
| Employer | No | The same employer selector as work history (Step 10.5). **Optional**: an NGO can post a job without naming the company. A known company fills in its sector |
| Sector / sub-sector | No | Filled by a known company; chosen by hand for a typed one (which the workspace then remembers) |
| Job type | Yes | Full time, Part time, Temporary (the same list as profiles) |
| Salary range | No | From / to, in EGP. Shown next to the Egypt market average for the occupation |

### Training only

| Field | Required | Notes |
|---|---|---|
| Training provider | Yes | Your own organisation by default, or type another name |
| Duration | No | A number and a unit: hours, days, weeks or months |
| Format | No | In person, Online, or Mixed |
| Cost to the trainee | No | Free, or Paid with an amount |
| Certificate | No | What the trainee receives, for example *ICDL certificate* |

---

## 5. The Eligibility section (inside the same form)

Everything already built in Step 11 moves here unchanged:

- the **rules**: Age, Governorate, Education level, Field of study,
  Skills, Occupation, Years of experience, Language, Military status,
  Gender, Expected salary;
- each rule is **Must have** or **Counts**, and the "Counts" points add up
  to 100 (an opportunity with only "Must have" rules scores 100 for
  everyone who passes them);
- the two levels: **Eligible from** and **Check from**;
- **Nothing is guessed**: missing information gives **?** and **Check**.

**Required:** the form cannot be saved until there is **at least one rule**.
The message says *"Add at least one eligibility rule."*

**Two helpers, never automatic.** The partner decides; Massar only offers:

- **"Use the occupation(s) above as a rule"**: one click adds an
  Occupation rule with the occupations chosen in the details.
- **"Use the governorate(s) above as a rule"**: the same for the place.

If the partner later changes the occupation or governorate in the details,
the rule is **not** changed silently. A note says the two no longer agree.

---

## 6. The opportunity's page

Three parts on one page:

| Part | Holds |
|---|---|
| **Details** | everything from section 4, the status and the seats |
| **Eligibility rules** | the rules and levels, read-only, with **Edit** |
| **Shortlist** | the results, exactly as in Step 11: Eligible first, then Check, On hold and Not eligible, each sorted by score; filters by result, "Needs a look", and name, mobile or number |

Buttons:

- **Check all N people** (the whole workspace), with the progress bar;
- **Check them again** after the rules change;
- **Edit**, **Copy**, **Close**, **Open again**, and **Delete** (only while
  it has no results).

**Copy** makes a new opportunity with the same details and rules, for
example for next year's round, and adds *"(copy)"* to the title.

---

## 7. Status

| Status | Meaning |
|---|---|
| **Open** | People can be checked (and, with Matching, referred) |
| **Closed** | Nobody can be checked. All results are kept for reports |

When closing, the partner picks a reason: **Filled**, **Cancelled** or
**Finished**. It is written in the opportunity's history.

---

## 8. What changes in Step 11 (already built)

| Today | After this step |
|---|---|
| **Assessments** menu item | **Removed** |
| **+ New Program** screen with Arabic and English names | **Removed**. Rules are made inside the Job or Training form |
| Program list and program page | **Replaced** by the Jobs and Training lists and pages |
| Profile **Eligibility** panel lists programs | Lists the **jobs and trainings** the person was checked against, each with its kind (Job or Training) |
| Profile: "Check against a program" | **"Check against a job or training"**: a selector of open opportunities |
| CV Bank: "Check these people against a program" | **"Check these people against a job or training"** |
| Test programs in the database | **Cleared** (test data only; they have no job or training to belong to) |

**Unchanged:** the scoring, the three results, the case worker's decision
with a required reason, notes, the profile history, "profile changed / rules
changed since the decision", checking 200 people at a time, the Journey
stage filter in the CV Bank, the pipeline on the dashboard, and privacy.

---

## 9. Where it is used

- **Jobs** and **Training Programs** lists: title, occupation (in the
  standard you chose in the top bar), governorate, seats, deadline, status,
  and the counts Eligible / Check / Not eligible. Search by title; filter
  by status, occupation and governorate.
- **The beneficiary profile**: the Eligibility panel (section 8).
- **The CV Bank**: check the people found against a job or training.
- **Guided Intake**: after saving a new profile, check it against an open
  job or training at once (as today).
- **Matching (next step)** takes the Eligible people from the shortlist.

---

## 10. Permissions

The permission backbone already has *View jobs and training* and *Post
jobs and training*. The three Step 11 permissions are folded in. During
the build every partner user holds them all, as with every other
permission.

| Permission | Allows |
|---|---|
| `opportunities.view` | See jobs, trainings, their rules and shortlists, and the profile panel |
| `opportunities.manage` | Create, edit, copy, close, open again and delete jobs and trainings, including their rules |
| `eligibility.check` | Check people against an opportunity (one person, everyone, or a CV Bank search) |
| `eligibility.decide` | Change a result (with a reason) and write notes |

Checking is separate from managing, so a case worker can check people
without being allowed to change a job's rules.

---

## 11. Privacy and isolation

- Jobs, trainings, rules, results and decisions belong to one partner
  workspace and are never visible to another partner.
- Jobs and trainings are **not** shown on the public site (the Scope lists
  postings as a Partner feature). This can be added later if you want it.
- The Super Admin sees **counts only**: open jobs, open trainings, total
  seats, people assessed and people eligible, across partners. Never an
  opportunity's content, a person or a result.
- Gender and military-status rules keep their reminder.

---

## 12. Not in this step

- **Matching** (Step 13): referring eligible people and tracking
  referred → accepted → in progress → completed / hired, and seats used.
- **Reports** (Step 14) and the **Partner Dashboard** (Step 15).
- Showing jobs and trainings to job seekers on the public site.

---

## 13. Decisions (answered 27 September 2026)

| # | Question | Decision |
|---|---|---|
| 1 | Title: one field or two? | **One field**, in any language. |
| 2 | Are the fields in section 4 right? | **Yes**, as listed. |
| 3 | How many occupations per opportunity? | **The partner decides**: no fixed limit (only a safety ceiling of 100). |
| 4 | Must a job name its employer? | **No.** An NGO can post a job without naming the company. |
| 5 | The four permissions in section 10 | **Yes**, as listed. |

---

## 14. How it will be checked

As with every step:

- automated checks (`php artisan test`): creating jobs and trainings with
  their rules, refusing one with no rule, checking people, the shortlist,
  copying, closing, the profile panel, the CV Bank link, permissions, and
  that one partner can never see another partner's jobs, trainings or
  results. The 14 Step 11 checks are updated to the new screens;
- the screens checked in Arabic and English, dark and light;
- a step note (`docs/STEP_12_JOBS_AND_TRAINING.md`) with simple install
  steps.
