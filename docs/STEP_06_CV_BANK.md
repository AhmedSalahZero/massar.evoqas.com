# Step 6 — CV Bank: upload CVs, read them without AI, review

Scope v2 §3: Bulk CV Upload · CV Reading Engine · Occupation
Auto-Classification · Review Queue & Review Screen · Duplicate Detection.
Scope v2 §7: Secure CV File Storage · access logs for CV downloads.

## What you get

- **Upload CVs** (menu → *Upload CVs*): drop up to **50** PDF or Word
  (.docx) files, up to **5 MB** each. Each file is read at once and gets
  one result:
  - **Added**: everything needed was found with certainty, so the
    profile is created automatically.
  - **Needs review**: something is missing or uncertain.
  - **Possible duplicate**: the same mobile, email or file is already in
    your workspace.
  - **Could not be read**: a scanned picture, a locked PDF, or an old .doc.
- **Review Queue** (menu → *Review Queue*): the CVs waiting for a person.
- **Review screen**:
  - The CV text is on the left, coloured: contact details blue, known
    headings yellow, known skills green, unknown headings red.
  - The registration form is on the right, filled in, with every field
    marked **Found / Check / Missing**.
  - The occupation the engine found is shown, or its suggestions with a
    match %.
  - Your decisions: **Approve and create profile**, **Add to existing
    profile**, or **Reject** (the file and its text are deleted). The
    next CV then opens.
- **Profile page**: a new *CV files* panel. It also shows how the
  occupation was chosen (by hand / exact match on the CV / while
  reviewing).

**How the reading works (no AI):** every word the engine knows
(headings, labels, governorates, degrees, languages…) is written out in
`app/Services/Cv/CvDictionary.php`, in English and Arabic.

**Gender** is never guessed from a name. It comes only from one of:

- a "Gender" line
- a military status line (men only)
- Arabic marital words (أعزب / عزباء)
- the national ID number

**Occupations:** a title matching several occupations equally (e.g.
"Sales Executive") always goes to a person.

**Years without months** ("2018 – 2020") are marked *Check*: the months
would be a guess.

**Files:** CV files are kept **encrypted** in
`storage/app/private/cvs/`, one folder per partner, and can never be
opened from the web. Download needs the *Download original CV files*
permission, and every download is recorded.

> ⚠ The files are encrypted with your `APP_KEY` (in `.env`). Keep a safe
> copy of it. If it is lost or changed, stored CV files cannot be opened.

## Install (about 5 minutes)

**1. Tell Massar where Poppler is.** Open your `.env` file and add this
line at the end. Use forward slashes `/`, exactly as shown:

```
PDFTOTEXT_PATH=C:/poppler/Library/bin/pdftotext.exe
```

On Linux (and on the server), install Poppler first
(Ubuntu/Debian: `sudo apt install poppler-utils`; AlmaLinux/RHEL:
`sudo dnf install poppler-utils`) and use this line instead:

```
PDFTOTEXT_PATH=/usr/bin/pdftotext
```

**2. Copy the files.** Unzip the package and copy everything into your
`massar` folder, choosing **Replace**.

**3. Run these one at a time** in `D:\My Projects\massar`:

```
composer dump-autoload
php artisan config:clear
php artisan migrate
npm run build
php artisan test
```

You should see **71 passed**. If a few lines say *skipped: Poppler
pdftotext is not installed…*, step 1 is not right yet. Word CVs still
work, but PDFs will not be read.

**To check PDF reading on its own**, run:

```
php artisan cv:check
```

It should end with **"PDF CVs can be read on this computer."** If it
does not, it shows Poppler's own message: send those lines to me.

**4. Check PHP's upload limit** (once). Run:

```
php -i | findstr upload_max_filesize
```

If it says less than `5M` (for example `2M`), CVs bigger than that will
say *"The file did not arrive"*. To fix it:

1. Open your `php.ini` (in Laragon: *Menu → PHP → php.ini*).
2. Set `upload_max_filesize = 10M` and `post_max_size = 12M`.
3. Save, then restart Laragon (or your web server).

## What to check on screen

1. Sign in as the caseworker (e.g. `caseworker@alamal.test`) and open
   **Upload CVs**. There should be **no orange warning** about the PDF
   reader.
2. Drop 3–5 real CVs (English and Arabic, PDF and Word). Each row turns
   into a coloured result within seconds.
3. Open a result marked **Added** → the profile, with the CV in the
   *CV files* panel. Compare it with the CV: you are the best judge.
4. Open the **Review Queue** and a CV in it.
   - Check the coloured text and the marks.
   - Change what is wrong and press **Approve**.
   - The next CV opens by itself.
5. Try **Reject** on one, and **Add to existing profile** on a
   duplicate.
6. Switch to **عربي** and look at an Arabic CV's review screen.

Please send me any CV that is read badly (with personal details
removed if you prefer). Each one helps me add the missing words.

## What comes next (not in this step)

- **Learned Rules**: teach the app unknown headings ("Career Path") and
  Egyptian job titles. Each is remembered for your workspace, and can
  be proposed to Massar for all partners.
- **Scanned CVs (OCR)**: they are kept and marked *Could not be read*
  for now. You can still register them by hand from the review screen.
- **Search inside CVs** and the **Public Talent Pool**.

## If something goes wrong

| What you see | What to do |
|---|---|
| Orange warning *"PDF files cannot be read on this computer yet"* | Check the `PDFTOTEXT_PATH` line (step 1), then run `php artisan config:clear` and refresh. |
| *"The file did not arrive"* | PHP's upload limit is too small: step 4. |
| Every PDF says *Could not be read* | Run `php artisan cv:check` and send me what it prints. |
| PDFs uploaded before step 1 was done say *Could not be read* | Open each one in the **Review Queue** and press **Read again**. |
| An Arabic PDF name is marked *Check* | Normal for some PDFs: they write "لا" as "ال". Compare the name with the CV. |
| An error mentioning `cv_documents` or `cv_batches` | `php artisan migrate` was not run. |
| The new menu items still say *Soon* | Run `npm run build` again, then refresh with Ctrl+F5. |
| `php artisan test` shows failures | Copy the lines under **FAILED** and send them to me. |
