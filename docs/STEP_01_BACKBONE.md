<!--
  Massar — Step 1: Occupation Backbone
  Location: docs/STEP_01_BACKBONE.md
  How to install this step and what to check. Written for a
  non-programmer: copy each command exactly.
-->

# Step 1 · Occupation Backbone (ENOC · ISCO-08 · ESCO)

## What this step adds

- The three occupation standards stored once and linked together:
  619 ISCO-08 groups, 426 ENOC occupations (9 Egyptian major groups)
  and 3,039 ESCO occupations, in Arabic and English.
- Search across about 41,000 job titles. Arabic works with or without
  "ال", with or without tashkeel, and in masculine or feminine form.
- The **Occupation Backbone** screens in the Super Admin menu.
- An import you can run again safely at any time. If something is
  wrong it changes nothing, and occupation IDs never change, so
  anything classified later stays valid.

Your official data files are already inside this package, in
`database/data/backbone`.

## Install (about 3 minutes)

1. Unzip `massar_step1_backbone.zip`.
2. Copy everything inside it into your `massar` folder. When Windows
   asks, choose **Replace the files in the destination**. This only
   adds and updates files; nothing of yours is deleted.
3. Open the terminal **inside the `massar` folder** and run these one
   at a time. Wait for each one to finish.

```
composer dump-autoload
php artisan migrate
php artisan backbone:import
npm run build
php artisan test
```

What you should see:

| Command | Expected result |
|---|---|
| `php artisan migrate` | one line ending in **DONE** (it adds the new tables; your users and partners are not touched) |
| `php artisan backbone:import` | a small table: 10 · 43 · 130 · 436 ISCO-08 groups, 9 ENOC major groups, 426 ENOC, 3039 ESCO, then **Done in … seconds** |
| `php artisan test` | **27 passed** in green |

Running `backbone:import` a second time says *"The files are the
same… Nothing was changed."* That is correct.

## What to check on screen

Sign in as the Super Admin and open **Occupation Backbone** in the menu.

1. The four boxes at the top show **426 · 619 · 3,039 · 40,855**.
2. Press **ENOC**, **ISCO-08** and **ESCO** (on the page or in the top
   bar). The list changes: ENOC is a list of Egyptian occupations,
   ISCO-08 is a tree you can open level by level, and ESCO is the
   detailed list.
3. Search **محاسبة**, then **المحاسب**, then **accountant**. All three
   should find the accountant occupations.
4. Open **2411 Accountants**. You should see the same group in all
   three standards, the Egyptian Arabic task description, and 13 ESCO
   occupations underneath.
5. Open **3221** from the orange list in *Data checks*. It should say
   the group has no ESCO occupation and is classified at group level
   only.
6. Switch to **عربي**. Everything should turn right-to-left and
   show Arabic titles first.

## Updating the data later

When a new edition arrives (for example a newer ESCO), replace the file
in `database/data/backbone`, change the edition name in
`config/backbone.php`, and run `php artisan backbone:import`. The
import history at the bottom of the Backbone screen records every run.

## If something goes wrong

| What you see | What to do |
|---|---|
| "The import stopped. Nothing was changed." | Read the **Reason** line under it and send it to me. |
| "File missing: …" | A data file is not in `database/data/backbone`. Unzip the package again. |
| "Class … not found" | Run `composer dump-autoload` again. |
| The menu item or screens do not appear | Run `npm run build` again, then refresh the browser. |
| Anything else | Copy the last lines of `storage/logs/laravel.log` and send them to me. |
