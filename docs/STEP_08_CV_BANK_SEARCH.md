# Step 8 — Searchable CV Bank

*Scope v2 Module B §3 (Searchable CV Bank)*

## What you get

A new page, **Beneficiaries → CV Bank**, to find people in your workspace.

**1. Every word counts.**
- The search looks inside **the original CVs attached to each profile**, and inside the profile itself: names, city, email and mobile, job titles, employers, **job responsibilities**, education and skills.
- *"general accounting"* finds a person even when those words are only in their CV summary.
- Every word you type must be found. Capital letters don't matter.

**2. Arabic spelling differences don't matter.**
- أ / إ / آ / ا, ة / ه, ى / ي, "ال" and Arabic digits are all treated as the same.
- For example, *"جامعه عين شمس"* finds *"جامعة عين شمس"*, and *"اخصائيه"* finds *"أخصائية"*.

**3. Occupation synonyms.**
- The words are also looked up in the occupation names: ENOC, ESCO titles and alternative titles, and ISCO-08.
- For example, *"bookkeeper"* finds the people registered as **accountants**, even if their CV never says "bookkeeper".
- Word matches are listed first. People found only through their occupation are marked *"Found through the occupation"*.

**4. Filters:**

| Filter | How it works |
|---|---|
| **Occupation** | Type a name or a code. Choose any standard and any level: an ISCO-08 major, sub-major, minor or unit group (e.g. *2*, *24*, *241*, *2411*), the matching ENOC occupation, or an ESCO occupation. An ESCO occupation also finds the narrower jobs under it. |
| **Governorate** | As on the beneficiaries list. |
| **Minimum experience** | 1, 2, 3, 5 or 10 years. |
| **CV language** | Arabic or English. A CV mixing both counts for both. |

**5. Snippets.** Each result shows up to **3 lines from the CV**, or from the profile, with the searched words **highlighted**. Click a result to open the profile.

**6. Always up to date.** The search index follows every change by itself: a new profile, an edit, a CV added through the Review Queue, or "Update from this CV".

**Only your own workspace is ever searched**, never another partner's.

**Not in this step: the "journey stage" filter.** The app does not record stages yet (registered, trained, referred, employed…). That comes with the later modules (Assessments, Matches), and the filter will be added then.

## Install (about 5 minutes)

**1.** Unzip the package. Copy **what is inside** its `massar` folder into your own `massar` folder, choosing **Replace**.

**2.** Run these one at a time in `D:\My Projects\massar`:

```
composer dump-autoload
php artisan migrate
php artisan cvbank:index
php artisan config:clear
npm run build
php artisan test
```

- `php artisan migrate` adds **one new table**, the search index.
- `php artisan cvbank:index` fills the index for the people **already registered**. It says, for example, *"Done: 120 profiles can now be found in the CV Bank."* You only need it once; it is safe to run again.
- You should see **101 passed** (95 before, 6 new). If any line says **FAILED**, copy the lines under it and send them to me.

## What to check on screen

1. Open **Beneficiaries → CV Bank**.
2. Search a word that appears **only inside a CV**, for example a university or a company name. The person appears, with the line highlighted.
3. Search an Arabic word written differently, for example with **ه** instead of **ة**. It is still found.
4. Search an occupation synonym, for example **bookkeeper**, and see the accountants appear.
5. Filter by an occupation (type *24*, or *محاسب*, and pick one), then add a governorate and a minimum experience.
6. Switch to **عربي** and repeat one search.

## If something goes wrong

| What you see | What to do |
|---|---|
| A yellow box: "The CV Bank is not ready yet" | Run `php artisan migrate`, then `php artisan cvbank:index`. |
| Older people are not found | Run `php artisan cvbank:index` (once). |
| The CV Bank is not in the menu | Run `npm run build`, then refresh with Ctrl+F5. |
| A test fails | Copy the lines under **FAILED** and send them to me. |

## A note for later

The search reads the index word by word. That is quick for thousands of CVs. If a partner one day holds **hundreds of thousands**, a database "full-text index" can be added. It is a small change, and the screen stays the same.
