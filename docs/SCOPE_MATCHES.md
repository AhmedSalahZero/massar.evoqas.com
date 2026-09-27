# Massar — Matches: Scope of Work

*Version 2 · 27 September 2026 · Module B (Beneficiary & Services Management)*
*Agreed. Decisions: section 13. Built in Step 13 (`docs/STEP_13_MATCHES.md`).*

This document expands **Beneficiary–Opportunity Matching** in the Massar
Scope of Work v2, section 3. The scope's own words are:

> Links eligible beneficiaries to opportunities using occupation codes (at
> any level), skills and rules, and tracks status: referred → accepted →
> in progress → completed/hired.
> *Outcome:* A clear, auditable trail of every beneficiary's journey to placement.

It builds on Step 12: every Job and Training already has its eligibility
rules and a shortlist of people, sorted by score.

---

## 1. The idea in one paragraph

A **match** is one person sent to one Job or Training. The case worker
**refers** an eligible person from the shortlist. From then on, Massar
follows that person step by step until they are **hired** or have
**completed** the training, or until it stops (for example, the employer
said no). Every step records **who**, **when** and **why**, so the whole
journey can be read back later. The dashboard's pipeline then shows real
numbers for **Matched** and **Placed**.

```
Shortlist (Eligible)  →  Refer  →  Accepted  →  In progress  →  Hired / Completed
                                ↘            ↘               ↘
                                  Stopped (with a reason: not accepted, withdrew, …)
```

---

## 2. What a match is

| | |
|---|---|
| **What it is** | One person + one Job or Training, with its current stage and its full timeline. |
| **Who owns it** | The partner workspace. Other partners never see it. |
| **One per pair** | A person can be referred to the same job or training only once. A stopped match can be **restarted** (with a reason) instead of creating a second one. |
| **Many per person** | A person can be referred to several jobs and trainings at the same time. |

---

## 3. The stages

The same four stages as the Scope, with words that fit each kind:

| Stage | For a **Job** | For a **Training** |
|---|---|---|
| 1. Referred | **Referred** to the employer | **Referred** to the provider |
| 2. Accepted | **Accepted by the employer** (for interview) | **Accepted** into the training |
| 3. In progress | **Interviewing** | **In training** |
| 4. Done | **Hired** | **Completed** |

**When it stops.** At any stage, the case worker can stop a match. A
**reason is required**, chosen from a short list, plus an optional note:

| For a Job | For a Training |
|---|---|
| Not accepted by the employer | Not accepted by the provider |
| Not hired after interviews | Did not complete (dropped out) |
| The person withdrew | The person withdrew |
| Other (write it) | Other (write it) |

**How a stage changes.**

- Normally one stage forward. Skipping a stage is allowed (for example,
  hired straight after referral), and the skipped stages are shown as skipped.
- Each change has a **date it happened** (today by default, or an earlier
  date if it happened before it was recorded) and an optional **note**.
- A mistake can be corrected by moving **back** one stage, with a
  **reason required**.
- **Hired** and **Completed** are final. They can only be undone as a
  correction, with a reason.

---

## 4. Who can be referred

**Only people whose result is Eligible** for that job or training, as the
Scope requires. That includes people the case worker **decided** are
Eligible (the override, with its reason, from Step 11).

- People who are **Check**, **On hold** or **Not eligible** cannot be
  referred. The button says why, and offers **Change the result** for the
  case worker to decide first.
- A person **not yet checked** is checked first, then referred if Eligible.

**The fit: occupation and skills (shown, never deciding).** To help the
case worker choose among the Eligible people, each one shows:

- **Occupation fit**: the person's occupation is *the same* as one the job
  asks for, *in the same group*, or *not related*. This uses the codes at
  every level (ESCO job → ISCO-08 / ENOC group → sub-major → major).
- **Skills fit**: how many of the **essential ESCO skills** of the job's
  occupations appear in the person's skills, for example *"4 of 12
  essential skills"*, with the list of what was found and what is lacking.

The fit only **sorts and informs**. It never blocks a referral and never
changes the eligibility result. Where the profile has no skills, or the
job's occupations have no ESCO skills, it says so, and nothing is guessed.

---

## 5. Seats

A Job or Training has a number of seats (Step 12). A seat is **taken** from
**Accepted** onwards: Accepted, In progress, Hired or Completed. Referred
people and stopped matches do not take a seat.

- The page shows **"7 of 10 seats taken"**.
- When all seats are taken, Massar **warns** and offers **Close as Filled**.
  It does not block, because an employer may take more people than planned
  (agreed, section 13).

---

## 6. Where it is used

### On the Job or Training page

- In the **Shortlist**, each Eligible person has a **Refer** button, and
  a tick box to **refer several at once**.
- A new **Matches** part: every person referred, grouped by stage, with
  counts per stage, the seats taken, and a button on each line to
  **move to the next stage** or **stop**.

### On the profile

- A new **Matches** panel: every job and training the person was referred
  to, the current stage, and the full timeline (stage, date, who, note).
- **Suggested for this person**: the open jobs and trainings whose
  occupations fit the person's occupation, with the eligibility result
  where it exists, and **Check** or **Refer** buttons.

### The Matches page (menu: *Matches*, no longer "Soon")

All the workspace's matches in one list, with:

- counts per stage (Referred · Accepted · In progress · Hired / Completed · Stopped);
- filters: stage, Job or Training, a specific job or training, governorate,
  occupation (any level), and search by name, mobile or number;
- **Needs follow-up**: matches with **no change for 14 days** (agreed,
  section 13), so nobody is forgotten.

### Dashboard

The **Placement pipeline** is complete:

| Stage | Counts |
|---|---|
| Registered | profiles |
| Assessed | people checked at least once (as today) |
| **Matched** | people referred at least once |
| **Placed** | people **hired** or who **completed** a training |

The Super Admin sees **counts only**: matches, people matched, people placed.

---

## 7. When things change

| What happens | What Massar does |
|---|---|
| The job or training is **closed** | No new referrals. Existing matches **continue**, since people may still be interviewing or in training. |
| The person's eligibility changes after referral (profile or rules changed, or the case worker changed the result) | The match **stays**. It is marked *"no longer eligible"* so someone looks again. It is never stopped automatically. |
| The person is already **Hired** or **In training** elsewhere | Referring again is allowed, with a reminder: *"Already hired at …"*. |
| A profile is deleted | Its matches are deleted with it, as with its other records. |

---

## 8. The trail (auditable)

- Every referral and stage change is kept in the **match timeline**:
  stage, the date it happened, the date it was recorded, who, and the note
  or reason.
- Each change is also written in the person's **profile history**.
- Nothing is ever silently changed. Corrections are new lines, not edits.

---

## 9. Permissions

The permission backbone already had *Refer and track matches*
(`matches.manage`). `matches.view` was added (section 13). During the
build, every partner user holds them all.

| Permission | Allows |
|---|---|
| `matches.view` | See the Matches page, the Matches part of a job or training, and the profile's Matches panel |
| `matches.manage` | Refer people, move stages, stop, restart and correct matches |

---

## 10. Privacy and isolation

- Matches belong to one partner workspace and are never visible to another.
- A person added from the Public Talent Pool is matched only as the
  partner's own copy of the profile.
- The Super Admin sees counts only.

---

## 11. Not in this step

- **Telling job seekers or employers.** Massar does not send emails or
  messages to people or employers in this step. Employers and providers
  do not sign in; the partner's case worker records what they said.
- **A referral letter** to print or send to the employer (can come later).
- **Reports** on matches and placements (the Reports step).
- The full **Partner Dashboard** (its own step).

---

## 12. What it completes

- **Placement pipeline**: Matched and Placed show real numbers.
- **CV Bank journey stage** filter gains *Matched* and *Placed*.
- **Jobs and Training lists**: each card shows the seats taken.

---

## 13. Decisions (agreed 27 September 2026)

| # | Question | Decision |
|---|---|---|
| 1 | Only **Eligible** people can be referred (as the Scope says)? | **Yes**, including those the case worker decided are Eligible |
| 2 | The stage words in section 3, and the stop reasons | **As listed** |
| 3 | When all seats are taken: warn or block? | **Warn** and offer "Close as Filled" |
| 4 | Show the **occupation and skills fit** (information only, never deciding)? | **Yes** |
| 5 | "Needs follow-up" after how many days without a change? | **14 days**, the same for everyone |
| 6 | Add `matches.view`, so some staff can see matches without changing them? | **Yes** |

---|---|---|
| 1 | Only **Eligible** people can be referred (as the Scope says)? | **Yes**, including those the case worker decided are Eligible |
| 2 | The stage words in section 3, and the stop reasons | As listed |
| 3 | When all seats are taken: **warn** or **block**? | **Warn** and offer "Close as Filled" |
| 4 | Show the **occupation and skills fit** (information only, never deciding)? | **Yes** |
| 5 | "Needs follow-up" after how many days without a change? | **14 days**, the same for everyone |
| 6 | Add `matches.view`, so some staff can see matches without changing them? | **Yes** |

---

## 14. How it will be checked

As with every step:

- automated checks (`php artisan test`): referring only Eligible people,
  one match per person and job, every stage and stop with its reason,
  skipped stages, corrections, seats, closed jobs, "no longer eligible",
  the fit, the timeline and profile history, the pipeline, permissions,
  and that one partner can never see another partner's matches;
- every new screen opened in a simulated browser, in Arabic and English;
- the install rehearsed from your current project before you receive it;
- a step note (`docs/STEP_13_MATCHES.md`) with simple install steps, and,
  if anything must be deleted, the exact list of files to delete by hand.
