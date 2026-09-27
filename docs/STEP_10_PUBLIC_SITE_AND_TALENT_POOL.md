# Step 10: Public site for job seekers + Public Talent Pool

## What is new

**The public website** (your home address, for example `https://massar.example/`):

- A home page explaining Massar, with two buttons: "Upload my CV" and "I don't have a CV".
- Registration uses the same questions as the staff Guided Intake:
  - with a CV, the CV is read and only the missing answers are asked;
  - it then asks for preferences (salary, job type, how soon they can start), consent, and a password.
- The job seeker confirms their email with a 6-digit code.
- **"My profile"** lets them:
  - edit their details;
  - replace their CV (they see what changed and tick what to keep);
  - see which organisations added them;
  - leave the Talent Pool;
  - delete everything.
- Job seekers sign in at `/sign-in`. Their accounts are **completely separate from staff accounts**.
- **Staff still sign in at `/login`.** A small "Staff sign-in" link sits at the bottom of the public site. Staff who are already signed in still go straight to their workspace.

**For partners, the menu item "Public Talent Pool"** (it is no longer "soon"):

- It uses the same search as the CV Bank.
- It lists only job seekers who confirmed their email and chose to be seen.
- **"Add to my workspace"** gives your organisation its own copy of the profile and the CV. The history says "Added from the Public Talent Pool".
- Many organisations can add the same person. The person is not notified; they see the organisations in "My profile".
- Opening a profile, downloading its CV and adding it are all logged.
- The permissions already existed: *View the public talent pool* and *Add from the public pool*.

## Privacy

- The profiles live in one hidden "Talent Pool" workspace. It never appears in the Super Admin's list of partners or in their counts.
- CVs are stored encrypted, as usual.
- The nightly task `pool:prune` deletes:
  - CVs uploaded but never registered (after 1 day);
  - accounts whose email was never confirmed (after 7 days).
- If a job seeker deletes their account, the copies that partners already added stay with those partners.

## Installing

```
php artisan migrate
npm run build
```

The migration creates the new tables and the Talent Pool workspace. The nightly task runs with your existing scheduler (`php artisan schedule:run`).

## Checks

`php artisan test` runs 10 new checks in `tests/Feature/TalentPoolTest.php`:

- registering with and without a CV;
- the email code;
- job seekers and staff never able to use each other's sign-in;
- "My profile";
- replacing a CV;
- partners adding a job seeker;
- duplicates and permissions;
- the Talent Pool never appearing as a partner;
- the nightly clean-up.
