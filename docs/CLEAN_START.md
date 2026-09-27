# Massar — Clean start from the Maliyat Docs project

What was done to turn the copied project into a clean Massar starting
point (25 Sep 2026).

## Removed
- All bookkeeping code and references (sales, expenses, inventory,
  depreciation, custody, reports, business types, currency).
- Public company self-registration (`RegisteredUserController`,
  `RegisterService`, `StoreRegisterRequest`) — partners are onboarded by
  the Super Admin (Scope v2 §4).
- Link-based email verification controllers (Massar uses the 6-digit code).
- `config/permission.php` (belonged to a package that was not installed),
  `tailwind.config.js`, the PWA setup, `app/Support/Reports/*` exporters
  and their PDF view (rebuilt for Massar reports later),
  `lang/*/documents.php`, `lang/*/notifications.php`.
- The 11 patched migrations → replaced by 7 clean ones.
- All old tests and every old Vue page, layout and component.
- Stale `bootstrap/cache/packages.php` and `services.php`.
- npm packages: tailwindcss, @tailwindcss/*, autoprefixer, postcss,
  vite-plugin-pwa, pinia, vue-i18n, marked, @tabler/icons-webfont.

## Kept (and documented)
Sign-in with rate limiting, email verification codes, password reset,
per-request access checks, tenant isolation trait, double-submit guard,
no-cache for signed-in pages, sign-out-other-devices, login activity
tracking, deletion log, bilingual emails.

## Added
- Partner organisations with type, seats and subscription.
- Permission backbone (`config/permissions.php`).
- Team management with seat limit; profile; preference switches
  (theme, language, occupation standard) saved per user.
- Massar shell with collapsible sidebar and the ENOC · ISCO-08 · ESCO switch.
- Coming Soon page for every planned module, so the full menu works.
- Subscription reminder email + daily command.
- Demo partner seeder, tests, README.

## If your local folder still has these, delete them
- `public/sw.js`, `public/manifest.webmanifest`, `public/workbox-*.js`,
  `public/offline.html`, `public/build/`
- `postcss.config.js` (if present)
- `node_modules/` and `vendor/` — then run `npm install` and `composer install`.

## Version 3 — the one final package (25 Sep 2026)

Built from massar_1 (the stronger base) and replaces massar_1 and massar_2.

- The name is corrected to **Massar** everywhere (screens, emails, code).
  The layout file `MasarShell.vue` is now `MassarShell.vue`.
- Automated tests added (sign-in, partner organisations, team and seat
  limits, workspace isolation), adapted from massar_2 to this code.
- The permission checker's memory is cleared before each test, so one
  test cannot affect another.
- Added the standard Laravel files that were missing: `artisan` and the
  `public` folder (`index.php`, `.htaccess`, `robots.txt`, `favicon.ico`),
  plus `.gitignore`, `.env.example` and `README.md`.
- The real `.env` (passwords and secret key) is no longer inside the zip.
