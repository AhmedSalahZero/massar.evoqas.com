<!--
  Massar — Step 2: Egypt labour market data
  Location: docs/STEP_02_LABOUR_MARKET.md
  How to install this step and what to check. Written for a
  non-programmer: copy each command exactly.
-->

# Step 2 · Egypt labour market data

## What this step adds

- The Egypt Occupational Outlook figures for each ENOC occupation:
  workers and growth, share of women, formal / public / private work,
  economic sectors, regions, wages (overall, men, women, public,
  private), weekly hours, required education, top 5 knowledge areas,
  abilities and skills, 10 skill groups, the outlook to 2030 and the
  link to the green transition.
- **Editions.** Each file is loaded as a dated edition and old editions
  are kept. One edition is "in use" and shown everywhere.
- An **Egypt labour market** panel on every occupation page in the
  Occupation Backbone. ESCO jobs show the figures of their Egyptian
  occupation, labelled as such.
- Missing figures say **"No data"** — never 0, never an estimate.
- Wages always carry a note that they date from the survey period.

## Findings about the official file (shown on screen)

1. **Regions:** for all 190 occupations with regional data, the six
   regions outside Greater Cairo have exactly the same value. This is
   almost certainly an error in the file. They are shown with a
   warning on your admin screens and will be hidden from partners.
2. **Sector shares that cannot be right** for 6 occupations (for
   example 976% instead of 97.6%, or shares adding up to 128%):
   3334, 3352, 9313, 9331, 9332, 9611. Shown exactly as published,
   with a warning; they will be hidden from partners.
3. **Wages:** the file lists the wages twice. Both copies agree for
   every occupation.

Worth reporting points 1 and 2 to the Ministry of Planning when you
ask them for the reference year.

## Install (about 3 minutes)

1. Unzip `massar_step2_labour_market.zip`.
2. Copy everything inside it into your `massar` folder and choose
   **Replace the files in the destination**.
3. In the terminal, inside the `massar` folder, run one at a time:

```
composer dump-autoload
php artisan migrate
php artisan market:import
npm run build
php artisan test
```

What you should see:

| Command | Expected result |
|---|---|
| `php artisan migrate` | one line ending in **DONE** (two new tables) |
| `php artisan market:import` | a table (190 · 349 · 229 · 228 · 228 · 228 · 192 · 190 · 420), the three findings above, then **Done. Edition #1 is now the one shown everywhere.** |
| `php artisan test` | **35 passed** |

## What to check on screen

Sign in as Super Admin → **Occupation Backbone**.

1. In **Data checks**, a new part "Egypt labour market · edition #1"
   lists how many occupations have each kind of figure, the regions
   warning, and the 6 occupations with figures to check.
2. Below the browser, **Labour market editions** shows edition #1 as
   **In use**.
3. Open **2411 Accountants**: the new panel shows 569,316 workers, an
   average wage of 6,740 EGP with the survey note, 15.7% women,
   46 hours a week, the sectors, the regions and the top 5 lists.
   **Please compare these with row 2411 of your Excel file.**
4. Open **9611** from the orange list: its sector shares show 933%
   with a warning, and its wages say "No data".
5. Open any ESCO job under 2411 (for example *Financial auditor*): it
   shows the same figures, with a note that they belong to 2411.
6. Switch to **عربي** and check the panel reads well.

## Loading a newer file later

1. Put the new file in `database/data/backbone`.
2. In `config/backbone.php`, under `market`, set `file`, `edition`,
   `edition_ar` and `reference_period` (and `reference_is_estimate`).
3. Run `php artisan market:import`. The new edition is loaded **but
   not shown yet**; the command tells you how it compares with the one
   in use (occupations added or dropped, big changes).
4. Open **Occupation backbone** (as Super Admin) and scroll to
   **Labour market editions**. The new edition is listed as "Loaded,
   not in use", with its figures to check. When you are happy, press
   **Use this edition** and confirm. Massar records who switched and
   when. You can switch back to an older edition the same way.

   From the command line instead: `php artisan market:use <number>`
   (without a number it lists the editions). If you already trust the
   file, `php artisan market:import --make-current` loads and uses it
   in one step.

## If something goes wrong

| What you see | What to do |
|---|---|
| "The import stopped. Nothing was changed." | Send me the **Reason** line. |
| "…uses wording the app does not know yet" | A newer file uses a new category text. Send me the line; I add it. |
| "…has a different layout: column N…" | The file's columns changed. Send me the line. |
| "The occupation backbone is empty" | Run `php artisan backbone:import` first. |
