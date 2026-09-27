# Step 9 — Guided Intake

**Scope v2 §3:** "Two doors into the same profile. Beneficiaries without
a CV answer short, simple questions a few at a time. Beneficiaries with a
CV upload it first, and the journey asks only what the CV did not answer."

## Where it is

In the menu, **New Intake** now opens the Guided Intake instead of the
"coming soon" page. It is available to anyone who can register
beneficiaries.

## Door 1 — "The person has a CV"

1. Upload one PDF or Word CV. It's read by the same CV Reading Engine
   (no AI).
2. The journey is **filled from the CV**, and only the questions the CV
   **did not answer**, or answered with doubt, are asked. For example, if
   the CV has the name, mobile and jobs but no governorate, only the
   governorate is asked.
3. **"Show all the questions of this step"** opens the rest of a step.
   On the last page, **"Change"** opens any step.
4. On saving, the **CV is attached to the new profile** (encrypted, as in
   the CV Bank). The profile shows how the occupation was chosen:
   - **from the CV**, if you kept the CV's occupation;
   - **while reviewing the CV**, if you changed it.
5. The CV is **never added automatically**, because a person is filling
   in the profile at that moment. If the journey is left unfinished, the
   CV is not lost: it waits in the **Review Queue**.

## Door 2 — "No CV"

Short, simple questions, a few at a time:

1. **About you:** name, gender, date of birth
2. **Contact:** mobile or email
3. **Where you live:** governorate, area, and military status for men
4. **Education:** highest level, and what they studied and where
5. **Work:** "Have you worked before?", then each job (title, employer,
   dates)
6. **Occupation:** what work they do or want to do. This can be left
   for later.
7. **Skills and languages**
8. **Preferences:** expected salary, preferred job type
9. **Check and save**

## The same profile, the same checks

- Both doors save **exactly the same profile** as the registration form,
  checked with **the same rules**:
  - a name, the gender, the governorate, and a mobile or email are
    needed;
  - dates can't be in the future;
  - and so on.
- If something is wrong when saving, the last page lists it with a
  **"Fix"** button that opens the right step.
- **The same mobile or email** already in the workspace brings the usual
  question: "Is this a different person?"

## Files

- `app/Http/Controllers/App/IntakeController.php`: the journey, reading
  the CV, saving
- `app/Http/Requests/App/IntakeRequest.php`: the registration form's
  rules, plus the CV
- `resources/js/Pages/App/Intake/Journey.vue`: the screen
- `app/Services/Cv/CvIntake.php`: a CV read for the intake is never
  added automatically
- `lang/en/intake.php`, `lang/ar/intake.php`, `resources/js/lang/translations.js`:
  the wording in English and Arabic
- `tests/Feature/IntakeTest.php`: **4 new automated checks**
  - with and without a CV
  - a CV can't be used twice
  - another partner's CV can't be used
  - permissions

No database change: nothing to migrate.

## Next: Step 10 — Public Self-Registration + Public Talent Pool

As agreed:

- **The public website:** job seekers register themselves in one of two
  ways:
  - *Upload my CV (faster)*, which uses the same journey;
  - *I don't have a CV*, which uses the same questions.
- **"My profile":** job seekers can update their details, replace their
  CV, leave the pool, or delete their profile.
- **Sign-in:** email and password.
- **The Public Talent Pool:** any partner can add the same person to its
  workspace. The job seeker isn't notified but can see which partners
  added them.
- **Career search and occupation pages** come later, as their own step.
