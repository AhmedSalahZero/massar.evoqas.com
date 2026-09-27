<!--
  Massar — Step 3: the Occupations page for partners
  Location: docs/STEP_03_OCCUPATIONS.md
  How to install this step and what to check. Written for a
  non-programmer: copy each command exactly.
-->

# Step 3 · Occupations for partners

## What this step adds

- **Occupations** in the partner menu (under Insights) now opens a real
  page instead of "Coming soon". Every partner reads the same data
  (Scope v2 §4 "Shared Backbone Access"); nothing can be changed there.
- **Search and browse** in the standard chosen in the top bar:
  ENOC (426 Egyptian occupations), ISCO-08 (the four-level tree) or
  ESCO (about 3,000 detailed jobs). Search works in Arabic and
  English, including alternative job titles.
- **The occupation page**: the three standards side by side, the
  classification path, the Arabic ENOC description, every ESCO job in
  the group, and the **Egypt labour market panel**.
- **Figures that cannot be right are never shown to partners.** They
  are removed on the server before anything reaches the partner's
  browser, and the panel says **"Under review"** in their place (never
  "No data": the figure exists, it is being checked). This covers the
  flagged hours, sector shares and public/private shares, and the six
  regions outside Greater Cairo while the edition says they are
  identical everywhere. The Super Admin still sees everything as
  published, with the warnings.
- Behind the scenes, the Super Admin backbone pages and the partner
  pages now use the same code, so they always show the same thing.

## Install (about 3 minutes)

1. Extract the zip into `D:\My Projects\massar` and choose
   **Replace the files**.
2. In the terminal, inside the `massar` folder, run one at a time:

```
composer dump-autoload
npm run build
php artisan test
```

There is no `migrate` in this step: no database change.

| Command | Expected result |
|---|---|
| `composer dump-autoload` | ends with "Generated optimized autoload files" |
| `npm run build` | ends with "✓ built in …" |
| `php artisan test` | **41 passed** (the 37 from before + 4 new Occupations tests) |

## Check it yourself (5 minutes)

Sign in as a **partner** user (a Company Admin or employee), not as
the Super Admin.

1. Open **Occupations** in the menu. You should see the list of
   occupations in the standard shown in the top bar.
2. Switch the top bar between **ENOC**, **ISCO-08** and **ESCO**. The
   list changes each time (ISCO-08 shows a tree you can open).
3. Search for **محاسب**, then for **accountant**. Accountants
   (2411) should come first.
4. Open **2411**: three cards (ENOC · ISCO-08 · ESCO), the ESCO jobs,
   and the labour market panel with wages and workers. Under regions,
   only Greater Cairo is shown, with a short note about the others.
5. Open **3334** (real estate agents): the sectors say
   "These figures are being checked with the source". There is no
   red warning and no 408 anywhere.
6. Open any ESCO job in the list: it shows the figures of its
   Egyptian occupation, labelled as such.
7. Switch to **عربي** and check the pages read well.

Then sign in as the **Super Admin** and open 3334 in the Occupation
backbone: you still see the published figures with their warnings.

## If something goes wrong

| What you see | What to do |
|---|---|
| "Occupations are not available yet" | The backbone is not loaded. As Super Admin run `php artisan backbone:import` and `php artisan market:import`. |
| The menu item still says "Coming soon" | Run `npm run build` again, then refresh the page with Ctrl+F5. |
| "403 · Forbidden" for a partner user | That user's permissions do not include "Browse occupations". |
