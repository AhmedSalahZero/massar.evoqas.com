<!--
  Massar — Setup Guide
  Location: docs/SETUP.md
  Step-by-step instructions for installing this version on your
  computer. Written for a non-programmer: copy each command exactly.
-->

# Massar — Setting up (version 3, the one final package)

This zip is a **complete project**. It replaces both earlier packages
(massar_1 and massar_2). Do not unzip it on top of an old folder,
because old files would stay behind. Follow these steps in order.

## Step 1 · Put the new project in place

1. Rename your current project folder from `massar` to `massar_old`.
   Keep it as a backup for now.
2. Unzip this file. You get a folder called `massar`. Put it where the
   old one was, with the same name, so `http://massar.test` keeps working.
3. Copy your **`.env`** file from `massar_old` into the new `massar`
   folder. It holds your database password, which is why it is **not**
   in the zip. (Tip: files starting with a dot can be hidden. On a Mac
   press Cmd + Shift + . in Finder to show them; on Windows, in File
   Explorer choose View → Show → Hidden items.)

## Step 2 · Check your `.env`

Open `massar/.env` in a text editor. Make sure these lines are there
(compare with `.env.example`, which lists every setting):

```
APP_NAME="Massar"
SUPER_ADMIN_EMAIL=your.email@example.com
SUPER_ADMIN_NAME="Massar Admin"
DEFAULT_PASSWORD=Choose#Strong1
AUTH_EMAIL_VERIFICATION_ENABLED=false
```

- **DEFAULT_PASSWORD** needs at least 8 characters, with a capital
  letter, a number and a symbol. It is used to create your Super Admin
  account and the demo accounts.
- **AUTH_EMAIL_VERIFICATION_ENABLED**: `false` skips the 6-digit email
  code while you test on your computer. Set it to `true` to try the code.

## Step 3 · Install and build

Open the terminal **inside the `massar` folder** and run these one at a
time. Wait for each to finish before starting the next.

```
composer install
npm install
npm run build
```

## Step 4 · Set up the database

```
php artisan migrate:fresh --seed
```

⚠ This empties the database and builds it again. That is fine now,
because there is no real data yet, and it is needed because massar_1
and massar_2 created different tables.

You should see **"Super admin created"** and **"Demo partner ready"**.

## Step 5 · Check that everything works

```
php artisan test
```

You should see **16 passed** in green. These checks cover sign-in,
partner organisations and the team page. They use a temporary database,
so your data is never touched.

Then open `http://massar.test` and sign in:

| Who | Email | Password |
|---|---|---|
| You (Super Admin) | the SUPER_ADMIN_EMAIL from `.env` | your DEFAULT_PASSWORD |
| Demo partner admin | admin@alamal.test | your DEFAULT_PASSWORD |
| Demo case worker | caseworker@alamal.test | your DEFAULT_PASSWORD |

## Step 6 · When everything works

You can delete `massar_old`, `massar_1.zip` and `massar_2.rar`.

## If something goes wrong

| What you see | What to do |
|---|---|
| "Could not open input file: artisan" | You are not inside the `massar` folder in the terminal. |
| "No application encryption key" | Run `php artisan key:generate` once. |
| "DEFAULT_PASSWORD is not set" | Fix the lines from Step 2, then run Step 4 again. |
| "Vite manifest not found" | Run `npm run build` again. |
| Anything else | Copy the last lines of `storage/logs/laravel.log` and send them to me. |
