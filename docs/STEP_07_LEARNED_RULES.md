# Step 7 — Learned Rules + "Update from this CV"

*Scope v2 Module B §3 (Learned Rules, two layers) · CV Bank*

## What you get

### 1. Learned Rules: teach the app

The app can be taught **three kinds of rule**:

| Kind | Example | What it does |
|---|---|---|
| **Heading** | *Where I Worked* → Work experience | The section is read into the form. A line can also be taught as *Not a heading* (it stays text). |
| **Job title** | *مندوب مبيعات* → Sales representative | The occupation is chosen automatically next time. |
| **Skill word** | *Peachtree* | Wherever it appears in a CV, it is added to the skills. |

**How reviewers teach**, on the review screen:
- **Teach**, next to each red (unknown) heading.
- **Teach these words**: select words on the CV text first, then press the button.
- **"Remember: '…' means the occupation I choose"**: this box appears when the app could not settle the occupation. It is ticked by default, and the rule is saved when you press **Approve**.

**What happens right after teaching:**
- The rule works **at once, for your workspace only**.
- The current CV, **and the other waiting CVs containing those words**, are read again straight away.
- Changes already typed in the form are not kept after a re-read (the Teach window says so).

**Job title rules are "step 2"** of occupation matching, exactly as the Scope says:
1. Exact match
2. **Learned rule**
3. Close matches
4. Manual search

Because a person made that decision, a CV settled by a rule can be **added automatically**. The profile then shows the occupation as *"chosen by a Learned Rule"*.

**Order of priority:** your own rule, then a Massar rule, then the built-in word list.

**Settings → Learned Rules** (the page):
- **Our rules:** add, change, delete, propose to Massar, take back a proposal. It also shows how many CVs each rule was used for, and the Super Admin's answer.
- **Massar rules:** the rules every partner uses (read-only).
- Teaching the same words again changes the existing rule instead of adding a second one.

### 2. Sharing with all partners (layer 2)

1. A partner presses **Propose to Massar**.
2. The Super Admin opens **Rule Requests**, with three tabs: *Waiting*, *Decided* and *Massar rules*.
3. The Super Admin chooses:
   - **Promote**: the rule works for every partner. If Massar already has those words, the screen first shows their current meaning.
   - **Decline**: with an optional note, which the partner sees.
4. The Super Admin can also remove a Massar rule.

**Only the words and their meaning are shared**, never a CV, a name or any personal data.

If a partner is deleted, its rules are deleted too. A Massar rule promoted from it stays.

### 3. "Update from this CV"

**Finding the person:** *Add to an existing profile* now has a **search by name, mobile, email or profile number** instead of the number box. It only finds people in your own workspace.

After choosing the person, you have two options:
- **Compare and update**
- **Attach without changes** (the same as before)

**The comparison screen** lists what the CV says that the profile does not:

| Change | Ticked by default? |
|---|---|
| New job (with its responsibilities), new skill, new language, new qualification | ✅ ticked |
| A field the profile has empty (e.g. no email yet) | ✅ ticked |
| Responsibilities for a job that has none | ✅ ticked |
| Anything that **replaces** what the profile says (new email, different level, other occupation) | ☐ not ticked |
| A mobile or email that **another profile** already uses | 🔒 cannot be ticked |

**Nothing changes until you press *Update the profile*.** Then:
- Only the ticked changes are made, and checked with the registration form's own rules.
- The CV is attached to the profile.
- Years of experience are counted again.
- The profile history shows *"Updated from the CV 'file name'"*, with who did it and when.

## Install (about 10 minutes)

**1.** Unzip the package. Inside is a folder called `massar`. Copy **what is inside it** into your own `massar` folder, choosing **Replace**.

**2.** Open the terminal in `D:\My Projects\massar` and run these, one at a time:

```
composer dump-autoload
php artisan migrate
php artisan config:clear
npm run build
php artisan test
```

- `php artisan migrate` adds **one new table** (`learned_rules`). Nothing existing is changed.
- You should see **95 passed** (84 before, 11 new). If any line says **FAILED**, copy the lines under it and send them to me.

## What to check on screen

1. **Teach a heading.** Upload a CV with an unusual heading. In the Review Queue, press **Teach** next to the red heading and choose *Work experience*. The CV is read again: the heading turns yellow and the jobs are filled.
2. **Teach an occupation.** Open a CV whose occupation was not settled. Choose the occupation, keep **Remember…** ticked, and press **Approve**. Upload another CV with the same title: it gets the occupation automatically.
3. **See your rules.** Open **Settings → Learned Rules**: your rules are listed. Press **Propose to Massar** on one.
4. **Promote it.** Sign in as the **Super Admin**, open **Rule Requests** and press **Promote**. Sign in as another partner: the rule is under *Massar rules*.
5. **Update a profile.** Upload a newer CV of someone already registered. Press **Add to an existing profile**, search the name, press **Compare and update**, tick what to keep, and save. Open the profile: the changes are there, and the history says *Updated from the CV …*.
6. Switch to **عربي** and look at the same screens.

## Decisions still open

| When | Decision |
|---|---|
| Before going live | **Who** in a partner may teach rules (`rules.manage`) and propose them (`rules.propose`). During the build, everyone has both. |
| Later | Whether a Massar rule should be able to *override* a partner's own rule. Today the partner's own rule always wins. |

## If something goes wrong

| What you see | What to do |
|---|---|
| "Table learned_rules doesn't exist" | Run `php artisan migrate`. |
| The new pages look unchanged or old | Run `npm run build` again, then refresh with Ctrl+F5. |
| A test fails | Copy the lines under **FAILED** and send them to me. |
