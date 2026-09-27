# Massar — Eligibility Assessment: Scope of Work

*Version 2 · 27 September 2026 · Module B (Beneficiary & Services Management)*
*Open questions answered: see section 11.*

> **Update, Step 12 (27 September 2026):** eligibility is no longer created
> on its own. The "eligibility programs" below became the **Eligibility
> section inside each Job and Training Program**, and the Assessments menu
> item was removed. The rules, results, decisions and "Check everyone" work
> as described here. See `docs/SCOPE_JOBS_AND_TRAINING.md`.

This document expands the **Eligibility Assessment Engine** in the Massar
Scope of Work v2, section 3. The scope's own words are:

> Rule-based auto-scoring (education level, age, skills, location)
> combined with the case worker's notes and override.
> *Outcome:* Fast, consistent first-pass filtering, keeping human judgment.

Everything below builds on what Massar already has: beneficiary profiles,
the three occupation standards, the CV Bank search, the profile history,
workspace isolation and the permission backbone.

---

## 1. The idea in one paragraph

Whether someone is eligible always depends on **what for**. So partners
don't give people one general score. They create **eligibility programs**,
for example *"Youth Technician Training 2026"*, each with its own rules.
Massar checks a beneficiary against a program, shows the result and every
reason, and the case worker makes the final decision. Later, every Job and
Training posting uses the same rules, so the engine is built once.

---

## 2. Eligibility programs

| | |
|---|---|
| **What it is** | A named set of rules, for example *"Call-centre jobs, Cairo & Giza"*. |
| **Who owns it** | The partner workspace that created it. Other partners never see it. |
| **Who creates it** | Users with the *Manage eligibility programs* permission. |
| **What it holds** | Name (Arabic and English), a short description, status (Active / Closed), the rules, and the program's two result levels (see section 4). |
| **Later** | Job and Training postings (next modules) will carry their own rules in the same form. |

A program can be copied, so a partner can start a new round from last
year's rules. A closed program keeps its results for reporting.

---

## 3. The rules

Every rule uses information **the profile already holds**. Nothing new is
collected from beneficiaries.

| Rule | Example | Read from |
|---|---|---|
| Age | 18 to 29 | Date of birth |
| Governorate | Cairo, Giza, Qalyubia | Governorate |
| Minimum education level | Secondary technical or higher | Education level (the 9 Egyptian levels) |
| Field of study | Commerce, Accounting | Education (field) |
| Required skills | Excel, customer service | Skills |
| Occupation | ISCO-08 *24*, ENOC *4222*, or an ESCO job | Occupation, in any standard and at any level |
| Minimum experience | 2 years | Experience (worked out from the work history) |
| Languages | English, at least "good" | Languages and level |
| Military status (men) | Completed or exempted | Military status |
| Gender | Only when the program truly requires it | Gender |
| Expected salary | Not above 8,000 EGP | Expected salary |

### Two kinds of rule

- **Must have.** If the person fails it, they are **not eligible**,
  whatever their score. *Example: age 18 to 29.*
- **Counts.** It adds points. *Example: English "good" or better = 20 points.*

The points of the "Counts" rules add up to **100**. The score is always
out of 100.

---

## 4. The result

Each check gives a score out of 100 and one of three results.

### The partner sets the levels, per program

When creating a program, the partner chooses **two levels**:

| Setting | Example |
|---|---|
| **Eligible from** | 70 |
| **Check from** | 50 |

Then, for that program:

| Score | Result |
|---|---|
| 70 to 100 | **Eligible** |
| 50 to 69 | **Check** |
| 0 to 49 | **Not eligible** |

- Each program has its own levels. A strict program can use *Eligible
  from 85*, and an open one *Eligible from 50*.
- "Check from" can be set equal to "Eligible from". Then the program has
  no Check band: people are either Eligible or Not eligible.
- "Check from" can never be higher than "Eligible from".

### Two cases that override the score

| Case | Result, whatever the score |
|---|---|
| A **"Must have"** rule fails | **Not eligible** |
| Information needed by a rule is **missing** (for example, no date of birth) | **Check** |

### Every result shows its reasons

Each rule is shown with **✓**, **✗** or **?**:

> ✓ Age 24: between 18 and 29
> ✗ Governorate Alexandria: not in Cairo, Giza, Qalyubia
> ? Military status: not recorded

**Nothing is guessed.** Missing information always gives **?** and a
**Check** result, never a pass or a fail. The case worker completes the
profile, and the check runs again.

---

## 5. The case worker's decision (override)

- The case worker can change any result: *Eligible*, *Not eligible*, or
  *On hold*.
- A **short reason is required**, for example *"Moving to Giza next month"*.
- The decision is written into the **profile history**, which already
  records who changed what and when.
- The case worker can add **notes** to the assessment at any time.
- If the profile changes afterwards, the automatic score is worked out
  again, but **the case worker's decision stays** and is marked
  *"The profile changed since this decision"*, so someone looks again.

---

## 6. Where it is used

**On the profile.** A new **Eligibility** panel lists every program the
person was checked against, with the result, score, reasons and decision.
The **Check against a program** button runs a new check.

**Check many people at once.** In a program, **Check everyone** runs the
rules on:
- the whole workspace, or
- the results of a CV Bank search (for example, everyone found for
  "accountant" in Cairo).

The result is a **shortlist sorted by score**, filtered by Eligible /
Check / Not eligible. This runs in the background, like bulk CV upload,
so thousands of profiles never block the screen.

**The Assessments page** (today "Coming soon") becomes the list of the
workspace's programs, with counts per result.

---

## 7. What it completes

- **Placement pipeline.** Beneficiaries move to the **Assessed** stage
  (Registered → **Assessed** → Matched → Placed).
- **CV Bank.** The **journey stage** filter left out in Step 8 is added.
- **Guided Intake.** After saving a new profile, the case worker can check
  it against a program at once.
- **Matching (next module)** links only *eligible* beneficiaries, as the
  scope requires.

---

## 8. Permissions

Three new permissions are added to the permission backbone. During the
build, Company Admins hold them all, as with every other permission.

| Permission | Allows |
|---|---|
| `assessments.view` | See programs and results |
| `assessments.manage` | Create, edit, copy and close programs; run checks |
| `assessments.decide` | Change a result (override) and write the reason |

---

## 9. Privacy and isolation

- Programs, results and decisions belong to one partner workspace and are
  never visible to another partner.
- A person added from the Public Talent Pool is checked only as the
  partner's own copy of the profile.
- The Super Admin sees **counts only** (how many assessed, how many
  eligible), never individual results.
- Gender and military-status rules are shown with a reminder that they
  should only be used when the program truly requires them.

---

## 10. Not in this step

- Job and Training postings, and Matching (the next modules; they will
  reuse these rules).
- Reports on assessments (with the Analytics module).
- Shared program templates from the Super Admin (decided: not needed; see section 11).

---

## 11. Decisions (answered 27 September 2026)

| # | Question | Decision |
|---|---|---|
| 1 | Who owns programs? | **Each partner only.** No shared templates from the Super Admin. |
| 2 | Is a reason needed for every override? | **Yes.** A written reason is required every time a case worker changes a result. |
| 3 | Is anything missing from the rules table? | **No. The table is complete for now.** Disability status is not added. |
| 4 | How is the Check band set? | **The score is always out of 100, and the partner sets the levels for each program:** *Eligible from* and *Check from* (section 4). |

---

## 12. How it will be checked

As with every step:
- automated checks (`php artisan test`) for the rules, the results,
  overrides, missing information, the history, permissions, and that one
  partner can never see another partner's programs or results;
- the screens checked in Arabic and English, dark and light;
- a step note (`docs/STEP_11_ELIGIBILITY_ASSESSMENT.md`) with simple
  install steps.
