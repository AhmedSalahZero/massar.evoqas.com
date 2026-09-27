<?php

namespace App\Services\Cv;

use App\Support\EgyptPhone;
use App\Support\TextNormalizer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

// ══════════════════════════════════════════════════════════════════
//  Massar — CvReader (the CV Reading Engine — rule-based, NO AI)
//  Location: app/Services/Cv/CvReader.php
//  Scope v2 §3 CV Reading Engine
//
//  Turns the plain text of one CV (TextExtractor) into the fields of
//  a beneficiary profile, and says for EVERY field how sure it is:
//
//      found    read from a clear pattern ("Gender: Male", an email …)
//      check    something was found, but a person should look at it
//               (a year without a month, a language without a level …)
//      missing  not in the CV
//
//  How it reads, step by step (every word it knows is in CvDictionary):
//    1. Sections — a short line that is a known heading ("Work
//       Experience", "الخبرات العملية") starts a section. Short lines
//       that LOOK like headings but are not known are listed as
//       "unrecognised headings" for the reviewer.
//    2. "Label: value" lines anywhere — name, date of birth, gender,
//       military status, address, mobile, email, national ID, job title.
//    3. Patterns — email addresses, Egyptian mobile numbers (010 011
//       012 015, with +20 / 0020, Arabic digits), LinkedIn, dates.
//    4. The name — a "Name:" line, or the first line of the CV when it
//       looks like a name (2–6 words, letters only).
//    5. Gender — "Gender: …"; a military status (men only in Egypt);
//       Arabic marital words, which are written in the person's own
//       gender (أعزب / عزباء); or the Egyptian national ID (its 13th
//       digit). It is never guessed from the name.
//    6. Governorate & city — from the address line or the top of the
//       CV, using the 27 governorates and their well-known cities.
//    7. Work history — each line of the work sections is read for WHAT
//       it is, not WHERE it is: a date line, a job title (an official
//       ENOC / ESCO / ISCO-08 title, a taught title, or a title word),
//       an employer (company words, a taught employer, or one already in
//       a profile of this workspace), a place (Riyadh, KSA, Cairo), a
//       duty (bullet, sentence, verb) or a detail ("Industry: FMCG").
//       "Company / Title / Date", "Title / Company / Date" and "Date /
//       Title – Company, Country" all give the same job. When the words
//       do not settle it, the CV's own habit does; when nothing does, the
//       job is marked Check — never guessed. Full rules: section 7 below.
//       Each job's RESPONSIBILITIES are the lines under it, up to the
//       next job (only those under "Key Responsibilities:" when present).
//    8. Education — degree words set the level (بكالوريوس → university);
//       institution words find the place; the latest year is the year.
//    9. Skills — the items under the skills heading; the ones in the
//       ESCO skills dictionary are marked as known.
//   10. Languages — language names with their level words.
//   11. Job titles for the occupation — the "Job title:" line, the
//       line under the name, then the jobs in the work history — for
//       OccupationClassifier.
//
//  The result also says where things were found (line numbers), so
//  the review screen can highlight contact details, known headings,
//  known skills and unrecognised headings on the CV text.
// ══════════════════════════════════════════════════════════════════

class CvReader
{
    // A lower-case "o" counts as a bullet only before a space: without the space it cut the
    // first letter off "objective:" and "overview", which were then never recognised.
    private const BULLET = '/^(?:[\-\x{2022}\x{25CF}\x{25AA}\x{25A0}\x{25E6}\x{2023}\x{2043}\x{2219}\x{27A2}\x{27A3}\x{27A4}\x{2756}\x{2B9A}\x{2713}\x{2714}\x{25BA}\x{25B6}\x{25C6}\x{25C7}\x{F000}-\x{F0FF}\*•·>]+|o(?=\s))\s*/u';

    /** "1-", "2.", "3)" in front of a list item. */
    private const NUMBERED = '/^\(?\d{1,2}\s*[\-.)]\s*(?=\S)(?!\d)/u';

    /** Sections with no field on the form that are only recognised on a line of their own ("Expected Salary: 5000" is a detail line). */
    private const NO_INLINE = ['availability', 'salary', 'preferences', 'additional', 'military', 'publications', 'research', 'memberships', 'internships'];

    /** Sections whose lines are read for jobs. */
    private const JOB_SECTIONS = ['experience', 'internships', 'unknown'];

    private const YEAR = '(?:19[5-9]\d|20[0-4]\d)';

    /** Put by TextExtractor in front of a bold / heading-style line of a Word file. */
    public const BOLD = "\u{E000}";

    /** @var array<string, string> heading key (see headingKeys) → section */
    private array $headings = [];

    /** @var array<string, true> heading key → a sub-heading inside a job ("Key Responsibilities") */
    private array $jobHeadings = [];

    /** @var array<string, true> */
    private array $weak = [];

    /** @var array<string, list<string>> */
    private array $ambiguous = [];

    /** @var array<string, true> normalised field label ("degree", "graduation year") */
    private array $fieldLabels = [];

    /** @var array<string, string> */
    private array $spelling = [];

    /** @var array<string, array{section: string, id: int}> Learned Rules: heading key → meaning (see withRules) */
    private array $ruleHeadings = [];

    /** @var list<array{id: int, key: string, name: string, phrase: string}> Learned Rules: skill words */
    private array $ruleSkills = [];

    /** @var array<int, true> the rules used while reading the current CV */
    private array $used = [];

    /** @var array<string, string> normalised label → field */
    private array $labels = [];

    /** @var array<string, array<string, true>> word list → its normalised words (and whole phrases for 'place' and 'duty') */
    private array $words = [];

    /** @var array<string, list<string>> word list → its normalised phrases of 2+ words */
    private array $phrases = [];

    /** @var array<string, true> Learned Rules: job titles taught in this workspace (normalised) */
    private array $ruleTitles = [];

    /** @var array<string, int> employers this workspace knows (normalised) → the Learned Rule id (0: from a profile) */
    private array $knownEmployers = [];

    /** @var array<string, true> the parts of the current CV that are official job titles */
    private array $titleHits = [];

    private string $monthRx;

    private string $presentRx;

    /** @var array<string, int> */
    private array $months = [];

    public function __construct()
    {
        foreach (CvDictionary::SPELLING as $from => $to) {
            $this->spelling[TextNormalizer::normalize($from)] = TextNormalizer::normalize($to);
        }
        foreach (CvDictionary::HEADINGS as $section => $words) {
            foreach ($words as $w) {
                foreach ($this->headingKeys($w) as $k) {
                    $this->headings[$k] ??= $section;
                }
            }
        }
        foreach (CvDictionary::JOB_SUBHEADINGS as $w) {
            foreach ($this->headingKeys($w) as $k) {
                $this->jobHeadings[$k] = true;
            }
        }
        foreach (CvDictionary::WEAK_HEADINGS as $w) {
            foreach ($this->headingKeys($w) as $k) {
                $this->weak[$k] = true;
            }
        }
        foreach (CvDictionary::AMBIGUOUS_HEADINGS as $w => $sections) {
            foreach ($this->headingKeys($w) as $k) {
                $this->ambiguous[$k] = $sections;
            }
        }
        foreach (CvDictionary::FIELD_LABELS as $w) {
            foreach ($this->variants($w) as $v) {
                $this->fieldLabels[$v] = true;
                $this->fieldLabels[str_replace(' ', '', $v)] = true;
            }
        }
        foreach (CvDictionary::LABELS as $field => $words) {
            foreach ($words as $w) {
                foreach ($this->variants($w) as $v) {
                    $this->labels[$v] ??= $field;
                    // "Date of B i r th": some PDFs space the letters out.
                    $this->labels['#'.str_replace(' ', '', $v)] ??= $field;
                }
            }
        }
        // The word lists the work history is read with, normalised once.
        foreach (['title' => CvDictionary::TITLE_WORDS, 'employer' => [...CvDictionary::EMPLOYER_WORDS, ...CvDictionary::INSTITUTION_WORDS],
            'duty' => CvDictionary::DUTY_WORDS] as $list => $words) {
            foreach ($words as $w) {
                foreach ($this->variants($w) as $v) {
                    if (str_contains($v, ' ')) {
                        $this->phrases[$list][] = $v;
                    }
                    $this->words[$list][$v] = true;
                }
            }
        }
        // Well-known employers: the whole part is one ("Xerox"), or it contains one that cannot be an
        // ordinary word ("KPMG Hazem Hassan" — but not "we", "total", "orange", "oracle" inside a sentence).
        $ordinary = ['we', 'ge', 'gm', 'hp', 'lg', 'bp', 'ey', 'citi', 'orange', 'total', 'metro', 'shell', 'emirates', 'oracle', 'sap', 'moore',
            'pioneers', 'mansour', 'raya', 'naeem', 'crowe', 'rsm', 'eni', 'dell', 'merck', 'roche', 'bayer', 'abbott', 'saudia', 'valeo'];
        foreach (CvDictionary::WELL_KNOWN_EMPLOYERS as $w) {
            foreach ($this->variants($w) as $v) {
                $this->words['known_employer'][$v] = true;
                if (str_contains($v, ' ')) {
                    $this->phrases['known_employer_in'][] = $v;
                } elseif (! in_array($v, $ordinary, true)) {
                    $this->words['known_employer_in'][$v] = true;
                }
            }
        }
        foreach ([...array_merge(...array_values(CvDictionary::PLACES)), ...CvDictionary::WORLD_PLACES] as $w) {
            foreach ($this->variants($w) as $v) {
                $this->words['place'][$v] = true;
            }
        }

        $monthWords = [];
        foreach (CvDictionary::MONTHS as $n => $words) {
            foreach ($words as $w) {
                $this->months[TextNormalizer::normalize($w)] = $n;
                $monthWords[] = preg_quote($w, '/');
                $monthWords[] = preg_quote(TextNormalizer::normalize($w), '/');
            }
        }
        usort($monthWords, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));
        $this->monthRx = '(?:'.implode('|', array_unique($monthWords)).')';

        $present = [];
        foreach (CvDictionary::PRESENT as $w) {
            foreach (ArabicRepair::variants($w) as $v) {
                $present[] = preg_quote($v, '/');
            }
        }
        usort($present, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));
        $this->presentRx = '(?:'.implode('|', array_unique($present)).')';
    }

    /**
     * @return array{
     *   form: array, marks: array<string, string>, notes: array<string, string>,
     *   titles: list<string>, language: string, links: array,
     *   marks_on_text: array{headings: array, unknown: array, contacts: list<string>, skills: list<string>}
     * }
     */
    /**
     * The same reader, with a workspace's Learned Rules (LearnedRuleBook::forReading).
     * A taught heading comes before the built-in word list; "none" means the
     * line is not a heading; "responsibilities" is a sub-heading inside a job.
     */
    public function withRules(array $book): static
    {
        $copy = clone $this;
        $copy->ruleHeadings = [];
        foreach ($book['headings'] ?? [] as $r) {
            foreach ($this->headingKeys($r['phrase']) as $k) {
                $copy->ruleHeadings[$k] = ['section' => $r['section'], 'id' => $r['id']];
            }
        }
        $copy->ruleSkills = array_values(array_filter($book['skills'] ?? [], fn ($r) => $r['key'] !== ''));
        $copy->ruleTitles = array_fill_keys(array_keys($book['titles'] ?? []), true);
        // Employers: the ones taught as Learned Rules, and the ones already in this workspace's profiles.
        $copy->knownEmployers = [];
        foreach ($book['known_employers'] ?? [] as $name) {
            $norm = TextNormalizer::normalize($name);
            // A job title or a place typed in the employer box by mistake is not learned.
            if (mb_strlen($norm) >= 2 && ! $this->hasAny($norm, 'title') && ! isset($this->words['place'][$norm])) {
                $copy->knownEmployers[$norm] = 0;
            }
        }
        foreach ($book['employers'] ?? [] as $r) {
            $copy->knownEmployers[$r['key']] = $r['id'];
        }
        unset($copy->knownEmployers['']);

        return $copy;
    }

    /** How a heading rule is stored (the words of the heading, without decoration or a colon). */
    public function headingRuleKey(string $phrase): string
    {
        $clean = $this->withoutDecoration($phrase);
        $clean = preg_replace('/:.*$/u', '', $clean) ?? $clean;

        return $this->headingKey(trim($clean));
    }

    /**
     * @param  bool  $wrapped  the text comes from a PDF, where long lines are broken
     *                         in two (a Word paragraph is always one line)
     */
    public function read(string $text, bool $arabicLigatures = false, bool $wrapped = false): array
    {
        $this->used = [];
        if ($wrapped) {
            // A PDF broke a date in two: "… Company : (June" / "2014 to July 2014)".
            $text = preg_replace('/((?:\(|\bfrom\s+|\bمن\s+)'.$this->monthRx.')[ \t]*\n'.self::BOLD.'?[ \t]*(?='.self::YEAR.')/iu', '$1 ', $text) ?? $text;
        }
        [$text, $bold] = self::withoutBoldMarks(strtr($text, ['：' => ':', '﹕' => ':']));
        // Page footers ("Page 3 of 4", "صفحة 2 من 3", "PAGE 3 | Tarek … - CV") are emptied, not read:
        // the line stays (so line numbers still match the CV on screen) but has no words.
        $text = implode("\n", array_map(fn ($l) => $this->isPageFooter($l) ? '' : $l, explode("\n", $text)));
        $lines = explode("\n", $text);
        $parsed = $this->sections($lines, $bold, $wrapped);

        $form = [
            'name_ar' => null, 'name_en' => null, 'gender' => null, 'date_of_birth' => null,
            'military_status' => null, 'governorate' => null, 'city' => null, 'phone' => null, 'email' => null,
            'education_level' => null, 'education' => [], 'work_history' => [], 'skills' => [], 'languages' => [],
        ];
        $marks = [];
        $notes = [];
        $labels = $this->labelValues($lines, $parsed);

        // ── Contact ─────────────────────────────────────────────────
        [$phone, $phoneRaw, $otherPhones] = $this->phone($text, $labels['phone'] ?? []);
        $form['phone'] = $phone;
        $marks['phone'] = $phone ? 'found' : 'missing';
        if ($otherPhones) {
            $notes['phone'] = 'more_phones';
        }
        $email = $this->email($text);
        $form['email'] = $email;
        $marks['email'] = $email ? 'found' : 'missing';
        $linkedin = preg_match('~(?:https?://)?(?:[a-z]{2,3}\.)?linkedin\.com/(?:in|pub)/[A-Za-z0-9\-_%]+/?~i', $text, $m) ? $m[0] : null;

        // ── Name ────────────────────────────────────────────────────
        [$nameAr, $nameEn, $nameMark] = $this->name($lines, $parsed, $labels['name'] ?? []);
        $form['name_ar'] = $nameAr;
        $form['name_en'] = $nameEn;
        if ($nameAr && $arabicLigatures && $nameMark === 'found' && preg_match('/[اأإآ]ل|ل[اأإآ]/u', $nameAr)) {
            $nameMark = 'check';
            $notes['name'] = 'arabic_letters';
        }
        $marks['name'] = ($nameAr || $nameEn) ? $nameMark : 'missing';

        // ── Date of birth, gender, military ─────────────────────────
        $nid = $this->nationalId($text, $labels['national_id'] ?? []);
        $dob = null;
        foreach ($labels['dob'] ?? [] as $value) {
            $dob = $this->birthDate($value);
            if ($dob) {
                break;
            }
        }
        $dob ??= $nid['dob'] ?? null;
        $form['date_of_birth'] = $dob;
        $marks['date_of_birth'] = $dob ? 'found' : (($labels['dob'] ?? []) ? 'check' : 'missing');

        $military = null;
        foreach ($labels['military'] ?? [] as $value) {
            $military = $this->pick($value, CvDictionary::MILITARY);
            if ($military) {
                break;
            }
        }

        $gender = null;
        $genderFrom = null;
        foreach ($labels['gender'] ?? [] as $value) {
            if ($g = $this->exact($value, CvDictionary::GENDER)) {
                [$gender, $genderFrom] = [$g, 'label'];
                break;
            }
        }
        if (! $gender && $nid) {
            [$gender, $genderFrom] = [$nid['gender'], 'national_id'];
        }
        if (! $gender && $military) {
            [$gender, $genderFrom] = ['male', 'military'];
        }
        if (! $gender) {
            foreach ($labels['marital'] ?? [] as $value) {
                if ($g = $this->pick($value, CvDictionary::MARITAL_GENDER)) {
                    [$gender, $genderFrom] = [$g, 'marital'];
                    break;
                }
            }
        }
        $form['gender'] = $gender;
        $marks['gender'] = $gender ? 'found' : 'missing';
        if ($genderFrom && $genderFrom !== 'label') {
            $notes['gender'] = 'gender_from_'.$genderFrom;
        }

        if ($gender === 'male') {
            $form['military_status'] = $military;
            $marks['military_status'] = $military ? 'found' : (($labels['military'] ?? []) ? 'check' : 'missing');
        }

        // ── Governorate and city ────────────────────────────────────
        [$gov, $city, $govMark] = $this->place($labels['address'] ?? [], $lines, $parsed);
        $form['governorate'] = $gov;
        $form['city'] = $city;
        $marks['governorate'] = $gov ? $govMark : 'missing';
        if ($city) {
            $marks['city'] = 'found';
        }

        // ── Work, education, skills, languages ──────────────────────
        [$jobs, $workMark] = $this->work($lines, $parsed, $wrapped, array_filter([$nameAr, $nameEn]));
        $form['work_history'] = $jobs;
        $marks['work_history'] = $workMark;
        // Never "check": a CV without duties is not held back for that alone.
        if ($jobs) {
            $marks['responsibilities'] = array_filter(array_column($jobs, 'responsibilities')) ? 'found' : 'missing';
        }
        if ($workMark === 'check') {
            $notes['work_history'] = 'work_check';
        }

        [$education, $level, $eduMark, $levelMark] = $this->education($lines, $parsed);
        $form['education'] = $education;
        $form['education_level'] = $level;
        $marks['education'] = $eduMark;
        $marks['education_level'] = $levelMark;

        [$languages, $langMark] = $this->languages($lines, $parsed, $labels['languages'] ?? []);
        $form['languages'] = $languages;
        $marks['languages'] = $langMark;

        [$skills, $known, $skillMark] = $this->skills($lines, $parsed);
        // Learned Rules: a taught skill word counts wherever it is in the CV.
        if ($this->ruleSkills) {
            $padded = ' '.TextNormalizer::normalize($text).' ';
            $have = array_map('mb_strtolower', $skills);
            foreach ($this->ruleSkills as $r) {
                if (! str_contains($padded, ' '.$r['key'].' ')) {
                    continue;
                }
                $this->used[$r['id']] = true;
                if (! in_array(mb_strtolower($r['name']), $have, true) && count($skills) < (int) config('beneficiaries.max.skills', 40)) {
                    $skills[] = $r['name'];
                    $have[] = mb_strtolower($r['name']);
                }
                $known[] = $r['phrase'];
            }
            $skillMark = $skills ? 'found' : $skillMark;
        }
        $form['skills'] = $skills;
        $marks['skills'] = $skillMark;

        // ── Job titles for the occupation, best source first ────────
        $titles = [];
        foreach ($labels['position'] ?? [] as $v) {
            $titles[] = $v;
        }
        if ($parsed['headline'] !== null) {
            $titles[] = $lines[$parsed['headline']];
        }
        foreach ($jobs as $job) {
            $titles[] = $job['title'];
        }
        $titles = array_values(array_unique(array_filter(array_map(fn ($t) => $this->cleanTitle((string) $t), $titles))));

        return [
            'form'     => $form,
            'marks'    => $marks,
            'notes'    => $notes,
            'titles'   => array_slice($titles, 0, 6),
            'language' => $this->languageOf($text),
            'links'    => array_filter(['linkedin' => $linkedin]),
            'rules_used' => array_keys($this->used),
            'marks_on_text' => [
                'headings' => $parsed['known'],
                'unknown'  => $parsed['unknown'],
                'contacts' => array_values(array_unique(array_filter([$phoneRaw, $email, $linkedin, ...$otherPhones]))),
                'skills'   => $known,
            ],
        ];
    }

    /**
     * The section a heading starts, as the reader understands it on a line
     * of its own: 'experience', 'skills' …, 'responsibilities' for a
     * sub-heading inside a job, or null when it is not a known heading.
     * (Used by the tests that check every heading of the reference files.)
     */
    public function recognise(string $line): ?string
    {
        $h = $this->heading($line);
        if (! $h) {
            return null;
        }

        if ($h['none'] ?? false) {
            return 'none';
        }

        return $h['section'] ?? 'responsibilities';
    }

    /**
     * How well the text is laid out: known headings with content under
     * them score, a heading followed straight by another heading (two
     * columns mixed together) costs. Used by TextExtractor to choose
     * between Poppler's reading modes.
     */
    /**
     * The text without the bold marks, and which lines had one.
     *
     * @return array{0: string, 1: array<int, true>}
     */
    public static function withoutBoldMarks(string $text): array
    {
        $bold = [];
        $lines = explode("\n", $text);
        foreach ($lines as $i => $line) {
            if (str_contains($line, self::BOLD)) {
                if (str_starts_with($line, self::BOLD)) {
                    $bold[$i] = true;
                }
                $lines[$i] = str_replace(self::BOLD, '', $line);
            }
        }

        return [implode("\n", $lines), $bold];
    }

    public function structureScore(string $text): int
    {
        $lines = array_values(array_filter(array_map('trim', explode("\n", $text)), fn ($l) => $l !== ''));
        $score = 0;
        foreach ($lines as $i => $line) {
            if ($this->heading($line) === null) {
                continue;
            }
            $next = $lines[$i + 1] ?? null;
            $score += ($next === null || $this->heading($next) !== null) ? -2 : 1;
        }

        return $score;
    }

    // ══ 1 · Sections ════════════════════════════════════════════════

    /**
     * @return array{section: array<int, string>, known: array<int, string>, unknown: list<int>, headline: ?int, inline: array<int, string>, job_heads: array<int, true>}
     *   known      line => section, for every recognised heading (a sub-heading
     *              inside a job is 'responsibilities')
     *   job_heads  the lines that are sub-headings inside a job ("Key Responsibilities:")
     */
    private function sections(array $lines, array $bold = [], bool $wrapped = false): array
    {
        $section = [];
        $known = [];
        $unknown = [];
        $inline = [];         // line => the text after "Skills:" on a heading line
        $jobHeads = [];
        $current = 'header';
        $seen = 0;            // non-empty lines so far
        $jobSeen = false;     // a job date line in the current section

        $joined = [];         // line => the heading it finishes ("LANGUAGE" / "SKILLS:")
        foreach ($lines as $i => $raw) {
            $line = trim($raw);
            if ($line === '') {
                $section[$i] = $current;
                continue;
            }
            $seen++;
            if (isset($joined[$i])) {
                $known[$i] = $joined[$i];
                $section[$i] = $current;
                continue;
            }
            $h = $this->heading($line);
            // A heading split over two lines: "LANGUAGE" / "SKILLS:", "COMPUTER" / "SKILLS:".
            if (($two = $this->twoLineHeading($lines, $i, $bold)) !== null) {
                [$h, $next] = $two;
                $joined[$next] = true;
            }
            if ($h !== null && ($h['none'] ?? false)) {
                $this->used[$h['rule']] = true;       // taught: "this line is not a heading"
                $section[$i] = $current;
                continue;
            }
            if ($h !== null && ! isset($h['rule']) && $this->ranges($line) && ! $this->headingLook($raw, isset($bold[$i]))) {
                $h = null;       // "Military Service – Egyptian Armed Forces (2015 – 2016)" is a job line
            }
            if ($h !== null) {
                $looks = $this->headingLook($raw, isset($bold[$i])) || isset($h['rule']);
                $inJobs = in_array($current, self::JOB_SECTIONS, true);

                // A sub-heading inside a job: the section does not change.
                if ($h['job'] && ($h['section'] === null || ($inJobs && $jobSeen && $this->jobFollows($lines, $i)))) {
                    if (isset($h['rule'])) {
                        $this->used[$h['rule']] = true;
                    }
                    $known[$i] = 'responsibilities';
                    $jobHeads[$i] = true;
                    if ($h['rest'] !== '') {
                        $inline[$i] = $h['rest'];
                    }
                    $section[$i] = $current;
                    continue;
                }
                $accept = $h['section'] !== null
                    // "• Training" inside a job is a duty; elsewhere "*Computer Skills" is a heading with a star.
                    && ! ($this->isBulleted($raw) && ! $looks && ($inJobs || $h['section'] === $current))
                    && ! ($h['weak'] && ! $looks)                              // "Research" inside a list
                    && ! ($h['section'] === $current && ! $looks)              // "Computer Skills" under Skills
                    && ! ($h['rest'] !== '' && in_array($h['section'], self::NO_INLINE, true))
                    && ! ($this->lineLabel($line) !== null && $this->nextToLabel($lines, $i))   // "MILITARY SERVICE" in a column of labels
                    && ! $this->endOfSentence($line, $lines, $i, $wrapped, $looks);
                if ($accept) {
                    if (isset($h['rule'])) {
                        $this->used[$h['rule']] = true;
                    }
                    $name = $h['ambiguous'] ? $this->decideByContent($h['ambiguous'], $lines, $i) : $h['section'];
                    $current = $name;
                    $known[$i] = $name;
                    foreach ($joined as $n => $v) {
                        if ($v === true) {
                            $joined[$n] = $name;
                        }
                    }
                    $jobSeen = false;
                    if ($h['rest'] !== '') {
                        $inline[$i] = $h['rest'];
                    }
                    $section[$i] = $current;
                    continue;
                }
            } elseif ($seen > 2 && ($this->looksLikeHeading($line, $lines, $i) || (isset($bold[$i]) && $this->boldHeading($line, $current)))) {
                $current = 'unknown';
                $unknown[] = $i;
                $jobSeen = false;
            }
            $section[$i] = $current;
            if (! $jobSeen && in_array($current, self::JOB_SECTIONS, true) && $this->ranges($line)) {
                $jobSeen = true;
            }
        }

        $joined = array_filter($joined, fn ($v) => $v === true);     // a split heading that was not accepted after all
        foreach (array_keys($joined) as $n) {
            unset($known[$n]);
        }
        $section = $this->unknownByContent($lines, $section, $unknown);

        return ['section' => $section, 'known' => $known, 'unknown' => $unknown, 'inline' => $inline, 'job_heads' => $jobHeads,
            'headline' => $this->headline($lines, $section, $known), 'bold' => $bold];
    }

    /**
     * "LANGUAGE" on one line and "SKILLS:" on the next: one heading.
     * Only short lines that look like headings (capitals, bold or a colon),
     * and only when the two together are a known heading.
     *
     * @return array{0: array, 1: int}|null the heading, the second line
     */
    private function twoLineHeading(array $lines, int $i, array $bold): ?array
    {
        $a = $this->withoutDecoration($lines[$i]);
        $next = $i + 1 < count($lines) ? $i + 1 : null;
        if ($next === null || trim($lines[$next]) === '' || $a === '' || str_ends_with($a, ':')) {
            return null;
        }
        $b = $this->withoutDecoration($lines[$next]);
        $short = fn ($t) => $t !== '' && count(preg_split('/\s+/u', trim($t, ': '))) <= 3 && ! preg_match('/[\d@|,،]/u', $t);
        if (! $short($a) || ! $short($b)) {
            return null;
        }
        $looks = fn ($raw, $k) => $this->headingLook($raw, isset($bold[$k]));
        if (! $looks($lines[$i], $i) || ! $looks($lines[$next], $next)) {
            return null;
        }
        $h = $this->heading(trim($a).' '.trim($b));
        if ($h === null || $h['section'] === null) {
            return null;
        }

        return [$h, $next];
    }

    /**
     * Lines under a heading the app does not know are read as work
     * (so a person checks them) — unless they are clearly studies or
     * courses: degrees ("Bachelor of Arts …", "دبلوم …") make it
     * Education, courses and certificates make it Courses. The heading
     * is still shown to the reviewer, to be taught.
     *
     * @param  array<int, string>  $section
     * @param  list<int>  $unknown
     * @return array<int, string>
     */
    private function unknownByContent(array $lines, array $section, array $unknown): array
    {
        foreach ($unknown as $start) {
            $run = [$start];
            for ($j = $start + 1; $j < count($lines) && ($section[$j] ?? '') === 'unknown' && ! in_array($j, $unknown, true); $j++) {
                $run[] = $j;
            }
            $study = 0;
            $course = 0;
            $job = 0;
            foreach (array_slice($run, 1) as $j) {
                $norm = TextNormalizer::normalize($lines[$j]);
                if ($norm === '') {
                    continue;
                }
                $isStudy = $this->levelIn($norm) !== null;
                $isCourse = ! $isStudy && $this->hasWord($norm, CvDictionary::COURSE_WORDS);
                $study += $isStudy ? 1 : 0;
                $course += $isCourse ? 1 : 0;
                $job += ! $isStudy && ! $isCourse && $this->hasAny($norm, 'title') ? 1 : 0;
            }
            $to = $study > 0 && $study >= $job ? 'education' : ($course > 0 && $course >= $job ? 'courses' : null);
            if ($to !== null) {
                foreach ($run as $j) {
                    $section[$j] = $to;
                }
            }
        }

        return $section;
    }

    /**
     * "objectives." or "experience in banking" — the end of a sentence
     * the PDF broke onto a new line, not a heading.
     */
    private function endOfSentence(string $line, array $lines, int $i, bool $wrapped, bool $looks): bool
    {
        $bare = $this->withoutDecoration($line);
        $latin = preg_replace('/[^A-Za-z]/', '', $bare) ?? '';
        $caps = strlen($latin) >= 3 && strtoupper($latin) === $latin;
        if (! $caps && preg_match('/[a-z]\.$/u', $bare) && ! $this->isArabic($bare)) {
            return true;
        }
        if ($wrapped && ! $caps && ! str_ends_with(rtrim($bare), ':') && preg_match('/^[a-z]/u', $bare)) {
            $prev = $this->previousLine($lines, $i);
            if ($prev !== null && $prev === $i - 1 && mb_strlen(trim($lines[$prev])) >= 40 && ! preg_match('/[.:!?؛]$/u', trim($lines[$prev]))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Another job date comes after line $i, before the next section
     * heading: so "Achievements:" here belongs to a job, not to the CV.
     */
    private function jobFollows(array $lines, int $i): bool
    {
        for ($j = $i + 1, $n = min(count($lines), $i + 60); $j < $n; $j++) {
            $t = trim($lines[$j]);
            if ($t === '') {
                continue;
            }
            $h = $this->heading($t);
            if ($h && $h['section'] && ! $h['job']) {
                return false;
            }
            if ($this->ranges($t)) {
                return true;
            }
        }

        return false;
    }

    /** "Background", "Qualifications" …: the lines under the heading decide the section. */
    private function decideByContent(array $sections, array $lines, int $i): string
    {
        $dates = 0;
        $study = 0;
        for ($j = $i + 1, $read = 0; $j < count($lines) && $read < 12; $j++) {
            $t = trim($lines[$j]);
            if ($t === '') {
                continue;
            }
            if (($h = $this->heading($t)) && $h['section'] && $this->headingLook($lines[$j], false)) {
                break;
            }
            $read++;
            $norm = TextNormalizer::normalize($t);
            if ($this->ranges($t)) {
                $dates++;
            }
            if ($this->levelIn($norm) || $this->hasWord($norm, CvDictionary::INSTITUTION_WORDS)) {
                $study++;
            }
        }
        if (in_array('experience', $sections, true) && $dates > 0 && $study === 0) {
            return 'experience';
        }
        if (in_array('education', $sections, true) && $study > 0) {
            return 'education';
        }
        // "Qualifications" with no degree and no place of study under it: courses.
        if ($sections[0] === 'education' && in_array('courses', $sections, true)) {
            return 'courses';
        }

        return $sections[0];
    }

    /** The line before or after is also a label on its own (a column of labels in a table). */
    private function nextToLabel(array $lines, int $i): bool
    {
        foreach ([-1, 1] as $step) {
            for ($j = $i + $step; $j >= 0 && $j < count($lines); $j += $step) {
                $t = trim($lines[$j]);
                if ($t === '') {
                    continue;
                }
                if ($this->lineLabel($t) !== null && ! str_contains(trim($t, ': '), ':')) {
                    return true;
                }
                break;
            }
        }

        return false;
    }

    /**
     * The section a heading line starts.
     *
     * @return array{section: ?string, rest: string, job: bool, weak: bool, ambiguous: ?list<string>}|null
     *   section    the section it starts (null: only a sub-heading inside a job)
     *   rest       the text after a colon on the same line ("Skills: Excel, Word")
     *   job        it is also a sub-heading inside a job ("Key Responsibilities")
     *   weak       a one-word heading that must LOOK like a heading (CvDictionary::WEAK_HEADINGS)
     *   ambiguous  the sections it can mean; the text under it decides
     */
    private function heading(string $line): ?array
    {
        $clean = $this->withoutDecoration($line);
        // "Section 1: Profile", "01 - Experience" (numbering before a colon or dash)
        $clean = preg_replace('/^(?:section\s+)?[\d٠-٩]{1,2}\s*[:.\-–|)]\s*(?=\p{L})/iu', '', $clean) ?? $clean;
        if ($clean === '' || mb_strlen($clean) > 90) {
            return null;
        }
        $before = $clean;
        $rest = '';
        if (preg_match('/^([^:]{2,70}):\s*(.*)$/u', $clean, $m)) {
            [$before, $rest] = [$m[1], trim($m[2])];
            // "Education :‐" — the colon is followed only by a dash.
            if (preg_match('/^[\-‐–—_\s]*$/u', $rest)) {
                $rest = '';
            }
        }
        $before = trim(preg_replace('/\([^)]*\)|\[[^]]*\]/u', ' ', $before) ?? $before);

        // Learned Rules first: what a person taught for this workspace (or Massar).
        if ($this->ruleHeadings) {
            foreach ($this->headingKeys($before, true) as $k) {
                if ($rule = $this->ruleHeadings[$k] ?? null) {
                    $section = $rule['section'];

                    return ['section' => in_array($section, ['none', 'responsibilities'], true) ? null : $section,
                        'job' => $section === 'responsibilities', 'weak' => false, 'ambiguous' => null,
                        'none' => $section === 'none', 'rule' => $rule['id'], 'rest' => $rest];
                }
            }
        }

        $hit = $this->headingHit($before);
        if (! $hit) {
            // Bilingual or combined: "Experience | الخبرات", "Skills / المهارات",
            // "Courses & Sessions", "Professional Achievements and Awards".
            // Bilingual (| / –): any part may be the known one. Combined (& , and): the FIRST part must be.
            $bilingual = preg_split('/\s*[|\/\\\\]\s*|\s+[\-–—]\s+/u', $before, -1, PREG_SPLIT_NO_EMPTY);
            $combined = preg_split('/\s*&\s*|\s*[,،]\s*|\s+(?:and|و)\s+/u', $before, -1, PREG_SPLIT_NO_EMPTY);
            $short = count(preg_split('/\s+/u', $before)) <= 8 && mb_strlen($before) <= 70;
            $found = [];
            if ($short && count($bilingual) >= 2 && count($bilingual) <= 3) {
                foreach ($bilingual as $part) {
                    if ($h = $this->headingHit($part)) {
                        $found[] = $h;     // a section, or a sub-heading inside a job ("Job Description | الوصف الوظيفي")
                    }
                }
            }
            if (! $found && $short && count($combined) >= 2 && count($combined) <= 4
                && ($h = $this->headingHit($combined[0])) && $h['section'] && ! $h['weak']) {
                $found[] = $h;
                foreach (array_slice($combined, 1) as $part) {
                    if (($h2 = $this->headingHit($part)) && $h2['section']) {
                        $found[] = $h2;
                    }
                }
            }
            if ($found) {
                $sections = array_column($found, 'section');
                $hit = $found[0];
                if (in_array('skills', $sections, true) && in_array('languages', $sections, true)) {
                    $hit['section'] = 'skills_languages';
                }
                $hit['weak'] = false;
            }
        }
        if (! $hit) {
            return null;
        }

        return $hit + ['rest' => $rest];
    }

    /** @return array{section: ?string, job: bool, weak: bool, ambiguous: ?list<string>}|null */
    private function headingHit(string $text): ?array
    {
        $keys = $this->headingKeys($text, true);
        foreach ($keys as $k) {
            $section = $this->headings[$k] ?? null;
            $job = isset($this->jobHeadings[$k]);
            if ($section !== null || $job) {
                return ['section' => $section, 'job' => $job, 'weak' => isset($this->weak[$k]), 'ambiguous' => $this->ambiguous[$k] ?? null];
            }
        }

        return null;
    }

    /**
     * The forms a heading is looked up by: normalised, singular, and
     * without spaces (for PDFs that space letters out: "S ki ll s").
     * The same rules are used for the dictionary and for the CV, so
     * "Skill", "SKILLS:", "1. Skills", "★ Skills ★" all give one key.
     *
     * @return list<string>
     */
    private function headingKeys(string $text, bool $fromCv = false): array
    {
        $out = [];
        foreach ($fromCv ? [$text] : ArabicRepair::variants($text) as $variant) {
            $key = $this->headingKey($variant);
            if ($key === '') {
                continue;
            }
            $words = explode(' ', $key);
            // A heading has at most 7 words — but a PDF may have spaced its letters out.
            $spaced = count($words) >= 3 && mb_strlen(str_replace(' ', '', $key)) / count($words) <= 3.2;
            if (count($words) > 7 && ! $spaced) {
                continue;
            }
            $single = $this->singular($key);
            if (count($words) <= 7) {
                $out[] = $key;
                $out[] = $single;
            }
            if (! $fromCv || $spaced) {
                $glued = str_replace(' ', '', $key);
                if (mb_strlen($glued) >= 5) {
                    $out[] = '#'.$glued;
                    $out[] = '#'.$this->singular($glued);
                }
            }
        }

        return array_values(array_unique($out));
    }

    private function headingKey(string $text): string
    {
        // "01 - Experience", "03 | Skills", "Section 1: Profile", "I. Education", "A) Skills"
        $text = preg_replace('/^\s*(?:section\s+)?(?:[\d٠-٩]{1,2}|[ivx]{1,4}|[a-h])\s*[.)\-–:|]\s*(?=\S)/iu', '', $text) ?? $text;
        $norm = TextNormalizer::normalize(str_replace('&', ' and ', $text));
        $words = [];
        foreach (explode(' ', $norm) as $w) {
            if ($w === '' || $w === 'and' || $w === 'و') {
                continue;
            }
            // "والتدريب" → "تدريب" (TextNormalizer already removed a leading "ال")
            if (mb_substr($w, 0, 3) === 'وال' && mb_strlen($w) >= 5) {
                $w = mb_substr($w, 3);
            }
            $w = $this->spelling[$w] ?? $w;
            $words[] = $w;
        }
        // Leftover numbering at the start: "01 experience"
        while ($words && preg_match('/^\d{1,2}$/', $words[0]) && count($words) > 1) {
            array_shift($words);
        }

        return implode(' ', $words);
    }

    /** "skills" → "skill", "activities" → "activity" (English words only; the same rule on both sides). */
    private function singular(string $key): string
    {
        return implode(' ', array_map(function ($w) {
            if (! preg_match('/^[a-z]{4,}$/', $w)) {
                return $w;
            }
            if (str_ends_with($w, 'ies')) {
                return substr($w, 0, -3).'y';
            }

            return str_ends_with($w, 's') && ! str_ends_with($w, 'ss') ? substr($w, 0, -1) : $w;
        }, explode(' ', $key)));
    }

    /** The line without bullets, decorations and bold marks: "★ SKILLS ★", "** Objective **", "| EDUCATION |". */
    private function withoutDecoration(string $line): string
    {
        $t = trim(str_replace(self::BOLD, '', $line));
        $t = preg_replace(self::BULLET, '', $t) ?? $t;
        // Symbols (not letters, digits, brackets or a colon) at either end.
        $t = preg_replace('/^[^\p{L}\p{N}(\[]+|[^\p{L}\p{N}:)\]]+$/u', '', $t) ?? $t;

        return trim($t);
    }

    /** Does the line LOOK like a heading (and not like an item of a list)? */
    private function headingLook(string $raw, bool $bold): bool
    {
        $line = trim($raw);
        if ($bold) {
            return true;
        }
        $bare = $this->withoutDecoration($line);
        if ($bare === '') {
            return false;
        }
        if (preg_match('/:\s*[\-‐–—_]*\s*$/u', $bare)) {
            return true;                                    // "Education:" "Skills :-"
        }
        $latin = preg_replace('/[^A-Za-z]/', '', $bare) ?? '';
        if (strlen($latin) >= 3 && strtoupper($latin) === $latin) {
            return true;                                    // "WORK EXPERIENCE"
        }
        // Decorated: "** Objective **", "— EXPERIENCE —", "★ Skills", ">> Experience", "### Skills"
        if (preg_match('/^[\x{2605}\x{2606}\x{25C6}\x{25C7}\x{25A0}\x{25A1}\x{25BA}\x{25B6}\x{00BB}#=~\x{2756}\x{2726}>]/u', $line)
            || preg_match('/^[^\p{L}\p{N}\s]+\s*\p{L}.*\p{L}\s*[^\p{L}\p{N}\s.:]+$/u', $line)) {
            return true;
        }
        if (preg_match(self::BULLET, $line) || preg_match(self::NUMBERED, $line) && ! preg_match('/^\d{1,2}\s*[.)\-]\s*\p{Lu}/u', $line)) {
            return false;                                   // "• Research" is an item
        }
        // Arabic has no capital letters: a short Arabic line on its own is a heading.
        return $this->isArabic($bare);
    }

    private function isBulleted(string $raw): bool
    {
        $line = trim(str_replace(self::BOLD, '', $raw));
        // "** Objective **" is decoration, not a bullet.
        if (preg_match('/^([*•\-])\1*\s*\S.*\S\s*\1+$/u', $line)) {
            return false;
        }

        return (bool) preg_match(self::BULLET, $line);
    }

    /** @return list<string>|null the next block of exactly $count lines (blank line or end after it), within 30 lines */
    private function laterValueBlock(array $lines, array $parsed, int $from, int $count): ?array
    {
        $block = [];
        for ($j = $from, $n = min(count($lines), $from + 30); $j <= $n; $j++) {
            $t = trim($lines[$j] ?? '');
            if ($t === '' || $j === $n) {
                if (count($block) === $count) {
                    return $block;
                }
                $block = [];
                continue;
            }
            $block[] = $t;
        }

        return null;
    }

    /** At least two values read as what their label says (a real birth date, a gender word …), and none contradicts. */
    private function valuesFit(array $run, array $values): bool
    {
        $good = 0;
        foreach ($run as $k => $label) {
            $v = $values[$k];
            $ok = match ($this->lineLabel($label)) {
                'dob'      => $this->birthDate($v) !== null,
                'gender'   => $this->exact($v, CvDictionary::GENDER) !== null,
                'military' => $this->pick($v, CvDictionary::MILITARY) !== null,
                'email'    => str_contains($v, '@'),
                'phone'    => preg_match('/[\d٠-٩]{7}/u', preg_replace('/\D/u', '', $v) ?? '') === 1,
                default    => null,
            };
            if ($ok === false) {
                return false;
            }
            $good += $ok ? 1 : 0;
        }

        return $good >= 2;
    }

    /** The label a short line is ("Degree", "GENDER", "Date of B i r th :"), or null. */
    private function lineLabel(string $line): ?string
    {
        $bare = trim($this->withoutDecoration($line), ' :.-');
        if ($bare === '' || mb_strlen($bare) > 40) {
            return null;
        }
        $norm = TextNormalizer::normalize($bare);
        if (isset($this->labels[$norm])) {
            return $this->labels[$norm];
        }
        if (isset($this->fieldLabels[$norm])) {
            return '';
        }
        $glued = str_replace(' ', '', $norm);
        if (substr_count($norm, ' ') >= 2 && isset($this->labels['#'.$glued])) {
            return $this->labels['#'.$glued];
        }

        return null;
    }

    /** A short line that looks like a heading but is not a known one ("Career Path"). */
    private function looksLikeHeading(string $line, array $lines, int $i): bool
    {
        $clean = trim(preg_replace(self::BULLET, '', $line) ?? $line);
        if ($clean !== $line && ! preg_match('/:$/u', $clean)) {
            return false;   // a bullet point is content
        }
        if ($this->notAHeading($clean)) {
            return false;   // "GENDER", "Degree", "POSITION:", "ARABIC" — a label or a value, not a section
        }
        if ($this->isRuleSkill($clean)) {
            return false;   // taught as a skill word ("IFRS"): a skill, not a heading
        }
        $norm = TextNormalizer::normalize($clean);
        $noNoise = TextNormalizer::normalize(OccupationClassifier::withoutNoise(trim($clean, ': ')));
        if ($this->hasAny($norm, 'title') || $this->hasAny($norm, 'employer') || isset($this->knownEmployers[$norm]) || isset($this->words['place'][$norm])
            || isset($this->ruleTitles[$norm]) || isset($this->ruleTitles[$noNoise])) {
            return false;   // "GENERAL MANAGER", "HASSAN ALLAM HOLDING": a job title or an employer in capitals, not a heading
        }
        if (preg_match('/[\d@\/|,،;]/u', $clean) || mb_strlen($clean) > 40) {
            return false;
        }
        $words = preg_split('/\s+/u', trim($clean, ': '));
        if (count($words) > 4 || count($words) < 1) {
            return false;
        }
        $next = null;
        for ($j = $i + 1; $j < count($lines); $j++) {
            if (trim($lines[$j]) !== '') {
                $next = trim($lines[$j]);
                break;
            }
        }
        if ($next === null) {
            return false;
        }
        $bare = trim($clean, ': ');
        $latinCaps = preg_match('/^[A-Z][A-Z &\-]{3,}$/u', $bare) === 1;
        $endsColon = str_ends_with($clean, ':') && $bare !== '';
        // "Career Path": 1–3 capitalised words after a blank line, followed by a
        // full job line ("Sales Executive at Aramex (2022 – Present)") or a long line.
        $titleCase = count($words) <= 3 && preg_match('/^(?:[A-Z][a-z&]*\s?){1,3}$/u', $bare) === 1
            && $i > 0 && trim($lines[$i - 1]) === ''
            && (($this->ranges($next) && $this->withoutDates($next, $this->ranges($next)[0]['match']) !== '')
                || count(preg_split('/\s+/u', $next)) > 6);

        return $latinCaps || $endsColon || $titleCase;
    }

    /**
     * A bold short line in a Word CV that is not a known heading. Bold is
     * also used for job titles and degrees, so it only counts outside the
     * sections that list jobs and studies, and never for an occupation,
     * employer, place of study or place name.
     */
    private function boldHeading(string $line, string $current): bool
    {
        $bare = trim($line, ': ');
        if (preg_match('/[\d@\/|,،]/u', $bare) || count(preg_split('/\s+/u', $bare)) > 4) {
            return false;
        }
        // Bold is also used for job titles, degrees and sub-groups of skills ("Managerial & Supervisory").
        if (in_array($current, ['experience', 'internships', 'education', 'courses', 'projects', 'volunteering', 'skills', 'skills_languages', 'languages'], true)) {
            return false;
        }
        $norm = TextNormalizer::normalize($bare);
        if ($norm === '' || $this->notAHeading($bare) || $this->hasAny($norm, 'title') || isset($this->ruleTitles[$norm]) || $this->hasWord($norm, CvDictionary::EMPLOYER_WORDS) || $this->hasWord($norm, CvDictionary::INSTITUTION_WORDS) || $this->placeIn($norm)) {
            return false;
        }
        try {
            return ! DB::table('occupation_labels')->where('normalized', $norm)->exists();
        } catch (\Throwable) {
            return true;
        }
    }

    private function isRuleSkill(string $text): bool
    {
        $norm = TextNormalizer::normalize(rtrim($text, ' :'));
        foreach ($this->ruleSkills as $r) {
            if ($r['key'] === $norm) {
                return true;
            }
        }

        return false;
    }

    /** "Page 3 of 4", "Page 2", "2 / 3", "صفحة 1 من 2", "PAGE 3 | Name - CV" */
    private function isPageFooter(string $line): bool
    {
        $t = trim(str_replace(self::BOLD, '', $line));

        return $t !== '' && (bool) preg_match('/^(?:(?:page|p\.|صفحة|الصفحة)\s*[\d٠-٩]+(?:\s*(?:of|\/|من|-)\s*[\d٠-٩]+)?(?:\s*\|.*)?|[\d٠-٩]+\s*(?:of|\/|من)\s*[\d٠-٩]+)$/iu', $t);
    }

    /** A label on its own line, a language name or a level ("ARABIC", "Mother Tongue"). */
    private function notAHeading(string $text): bool
    {
        if ($this->lineLabel($text) !== null) {
            return true;
        }
        $norm = TextNormalizer::normalize($text);
        if ($norm === '') {
            return true;
        }
        if (count(explode(' ', $norm)) <= 3 && ($this->languageCode($norm) || $this->levelOf($norm))) {
            return true;
        }

        return false;
    }

    /** The line right under the name, when it looks like a job title. */
    private function headline(array $lines, array $section, array $known): ?int
    {
        $count = 0;
        foreach ($lines as $i => $raw) {
            if (($section[$i] ?? '') !== 'header' || isset($known[$i])) {
                return null;
            }
            $line = trim($raw);
            if ($line === '') {
                continue;
            }
            $count++;
            if ($count === 1) {
                continue;    // the name
            }
            if ($count > 3) {
                return null;
            }
            if ($this->isContactLine($line) || preg_match('/\d/u', $line) || $this->isNameLike($line) && $count === 2 && $this->otherScript($lines, $i)) {
                continue;
            }
            $words = preg_split('/\s+/u', $line);

            return count($words) <= 7 && ! str_contains($line, ':') ? $i : null;
        }

        return null;
    }

    // ══ 2 · Label: value ════════════════════════════════════════════

    /** @return array<string, list<string>> field → values found */
    private function labelValues(array $lines, array $parsed): array
    {
        $out = [];
        foreach ($lines as $i => $raw) {
            $line = trim(preg_replace(self::BULLET, '', trim($raw)) ?? '');
            if ($line === '') {
                continue;
            }
            // Contact bars: "Mobile: 010… | Email: a@b.c | Address: Cairo"
            foreach (preg_split('/\s+\|\s+|\s{3,}|\t/u', $line) as $part) {
                if (! preg_match('/^(.{1,40}?)\s*[:\-–]\s*(.+)$/u', $part, $m) && ! preg_match('/^(.{1,40}?)\s*[:]\s*(.+)$/u', $part, $m)) {
                    continue;
                }
                $label = TextNormalizer::normalize($m[1]);
                if (! isset($this->labels[$label])) {
                    continue;
                }
                $out[$this->labels[$label]][] = $this->strip($m[2]);
            }
            // No colon, or the colon moved to the end ("تاريخ الميلاد 12/03/1998 :"):
            // the first 1–4 words are a known label, the rest is the value.
            $words = preg_split('/\s+/u', $this->strip($line));
            if (count($words) >= 2 && count($words) <= 9 && ! preg_match('/^[^:]{1,40}:\s*\S/u', $line)) {
                for ($k = min(4, count($words) - 1); $k >= 1; $k--) {
                    $label = TextNormalizer::normalize(implode(' ', array_slice($words, 0, $k)));
                    if (isset($this->labels[$label])) {
                        $out[$this->labels[$label]][] = $this->strip(implode(' ', array_slice($words, $k)));
                        break;
                    }
                }
            }
            // Word tables come out as "Gender | Male".
            if (preg_match('/^(.{1,40}?)\s+\|\s+(.+)$/u', $line, $m)) {
                $label = TextNormalizer::normalize($m[1]);
                if (isset($this->labels[$label])) {
                    $out[$this->labels[$label]][] = trim(explode(' | ', $m[2])[0]);
                }
            }
        }
        foreach ($parsed['inline'] as $i => $rest) {
            if (($parsed['known'][$i] ?? '') === 'languages') {
                $out['languages'][] = $rest;
            }
        }
        // Under a "Military Service" heading, the line itself is the status.
        foreach ($lines as $i => $raw) {
            if (($parsed['section'][$i] ?? '') === 'military' && ! isset($parsed['known'][$i]) && trim($raw) !== '' && count($out['military'] ?? []) < 3) {
                $out['military'][] = $this->strip(preg_replace(self::BULLET, '', trim($raw)) ?? '');
            }
        }
        foreach ($this->labelColumns($lines, $parsed) as $field => $values) {
            foreach ($values as $v) {
                $out[$field][] = $v;
            }
        }

        return $out;
    }

    /**
     * Tables in Word and PDF CVs often come out as a column of labels
     * followed by a column of values:
     *
     *     Date of birth          September 20th, 1995
     *     Gender           →     Female
     *
     * becomes "Date of birth / Gender / September 20th, 1995 / Female".
     * The values are matched to the labels in order — only when there are
     * exactly as many values as labels (or one label with ": value").
     *
     * @return array<string, list<string>>
     */
    private function labelColumns(array $lines, array $parsed): array
    {
        $out = [];
        $n = count($lines);
        $isLabel = fn (string $t) => $t !== '' && $this->lineLabel($t) !== null && ! preg_match('/:\s*\S/u', trim($t, ' :'));
        $glyph = fn (string $t) => preg_match('/^[^\p{L}\p{N}]*$/u', $t) === 1;
        for ($i = 0; $i < $n; $i++) {
            if (! $isLabel(trim($lines[$i]))) {
                continue;
            }
            $run = [];
            $j = $i;
            for (; $j < $n; $j++) {
                $t = trim($lines[$j]);
                if ($t === '') {
                    continue;
                }
                if (! $isLabel($t)) {
                    break;
                }
                $run[] = $t;
            }
            $values = [];
            for (; $j < $n && count($values) < count($run); $j++) {
                $t = trim($lines[$j]);
                if ($t === '' || $glyph($t)) {
                    continue;
                }
                if (isset($parsed['known'][$j]) || $isLabel($t)) {
                    break;
                }
                $values[] = $t;
            }
            $colonValues = $values && str_starts_with($values[0], ':');
            $fits = count($values) === count($run) && (count($run) >= 2 || $colonValues);
            if (! $fits && count($run) >= 3) {
                // Two-column PDFs sometimes put the values further down (after a footer):
                // a later block of exactly as many lines is used when the values PROVE they fit.
                $later = $this->laterValueBlock($lines, $parsed, $j, count($run));
                if ($later && $this->valuesFit($run, $later)) {
                    [$values, $fits] = [$later, true];
                }
            }
            if ($fits) {
                foreach ($run as $k => $label) {
                    $field = $this->lineLabel($label);
                    $value = $this->strip(ltrim($values[$k], ': '));
                    if ($field && $value !== '') {
                        $out[$field][] = $value;
                    }
                }
                $i = $j - 1;
            }
        }

        return $out;
    }

    // ══ 3 · Patterns ════════════════════════════════════════════════

    /** @return array{0: ?string, 1: ?string, 2: list<string>} normalised mobile, as written, other mobiles as written */
    private function phone(string $text, array $labelled): array
    {
        $found = [];
        $rx = '/(?<![\d٠-٩])(?:(?:\+|00)\s*2\s*0[\s\-.]*|\(\s*\+?20\s*\)[\s\-.]*)?\(?[0٠]?\s*[1١]\s*[0125٠١٢٥]\)?(?:[\s\-.]*[\d٠-٩]){8}(?![\d٠-٩])/u';
        foreach ([...$labelled, $text] as $source) {
            if (preg_match_all($rx, $source, $m)) {
                foreach ($m[0] as $raw) {
                    $n = EgyptPhone::normalize($raw);
                    if (EgyptPhone::isValid($n) && ! isset($found[$n])) {
                        $found[$n] = trim($raw);
                    }
                }
            }
        }
        if (! $found) {
            return [null, null, []];
        }
        $first = array_key_first($found);
        $others = array_values(array_slice($found, 1));

        return [$first, $found[$first], $others];
    }

    private function email(string $text): ?string
    {
        return preg_match('/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/u', $text, $m)
            ? mb_strtolower(rtrim($m[0], '.'))
            : null;
    }

    /** @return array{dob: string, gender: string}|null  Egyptian national ID: C YYMMDD GG SSS G X */
    private function nationalId(string $text, array $labelled): ?array
    {
        foreach ([...$labelled, $text] as $source) {
            $digits = strtr($source, array_combine(['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'], range(0, 9)));
            if (! preg_match_all('/(?<!\d)([23])(\d{2})(\d{2})(\d{2})(\d{2})(\d{3})(\d)(\d)(?!\d)/', $digits, $all, PREG_SET_ORDER)) {
                continue;
            }
            foreach ($all as $m) {
                $year = ($m[1] === '2' ? 1900 : 2000) + (int) $m[2];
                if (! checkdate((int) $m[3], (int) $m[4], $year) || (int) $m[5] < 1 || (int) $m[5] > 88) {
                    continue;
                }
                $dob = sprintf('%04d-%02d-%02d', $year, $m[3], $m[4]);
                if (! $this->plausibleBirth($dob)) {
                    continue;
                }

                return ['dob' => $dob, 'gender' => ((int) $m[7]) % 2 === 1 ? 'male' : 'female'];
            }
        }

        return null;
    }

    // ══ 4 · Name ════════════════════════════════════════════════════

    /** @return array{0: ?string, 1: ?string, 2: string} Arabic name, English name, mark */
    private function name(array $lines, array $parsed, array $labelled): array
    {
        $ar = null;
        $en = null;
        foreach ($labelled as $v) {
            $v = $this->tidyName($v);
            if ($v && $this->isNameLike($v)) {
                $this->isArabic($v) ? $ar ??= $v : $en ??= $v;
            }
        }
        if ($ar || $en) {
            return [$ar, $en, 'found'];
        }

        // The first lines of the CV (skipping "Curriculum Vitae").
        $count = 0;
        foreach ($lines as $i => $raw) {
            $line = trim($raw);
            if ($line === '' || in_array(TextNormalizer::normalize($line), array_map([TextNormalizer::class, 'normalize'], CvDictionary::NOT_A_NAME), true)) {
                continue;
            }
            if (($parsed['section'][$i] ?? '') !== 'header' || ++$count > 3) {
                break;
            }
            $name = $this->tidyName($line);
            if (! $name || ! $this->isNameLike($name)) {
                if ($count === 1) {
                    break;   // the CV does not start with a name
                }
                continue;
            }
            if ($this->isArabic($name)) {
                $ar ??= $name;
            } else {
                $en ??= $name;
            }
            if ($ar && $en) {
                break;
            }
            if ($count >= 1 && ! $this->otherScript($lines, $i)) {
                break;
            }
        }
        $words = count(preg_split('/\s+/u', (string) ($ar ?? $en)));

        return [$ar, $en, $words >= 2 && $words <= 5 ? 'found' : 'check'];
    }

    private function tidyName(string $v): ?string
    {
        $v = $this->strip(preg_replace('/\s+/u', ' ', $v) ?? '');
        if ($v === '') {
            return null;
        }
        // "AHMED HASSAN" → "Ahmed Hassan"
        if (! $this->isArabic($v) && mb_strtoupper($v) === $v) {
            $v = mb_convert_case(mb_strtolower($v), MB_CASE_TITLE);
        }

        return mb_substr($v, 0, 150);
    }

    private function isNameLike(string $line): bool
    {
        if (preg_match('/[\d@:\/|()]/u', $line) || $this->isContactLine($line)) {
            return false;
        }
        if (! preg_match('/^[\p{L}\s.\'\-]+$/u', $line)) {
            return false;
        }
        $words = preg_split('/\s+/u', trim($line));
        if (count($words) < 2 || count($words) > 6) {
            return false;
        }
        $norm = TextNormalizer::normalize($line);

        return ! isset($this->headings[$norm]) && ! $this->placeIn($norm);
    }

    /** The next line is the same name in the other script (أحمد حسن / Ahmed Hassan). */
    private function otherScript(array $lines, int $i): bool
    {
        for ($j = $i + 1; $j < min(count($lines), $i + 3); $j++) {
            $next = trim($lines[$j]);
            if ($next !== '') {
                return $this->isNameLike($next) && $this->isArabic($next) !== $this->isArabic(trim($lines[$i]));
            }
        }

        return false;
    }

    // ══ 5 · Dates ═══════════════════════════════════════════════════

    private function birthDate(string $value): ?string
    {
        $v = strtr($value, array_combine(['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'], range(0, 9)));
        $date = null;
        if (preg_match('/\b(\d{1,2})\s*[\/.\-]\s*(\d{1,2})\s*[\/.\-]\s*('.self::YEAR.')\b/u', $v, $m)) {
            // Egypt writes day first: 23/05/1995.
            $date = checkdate((int) $m[2], (int) $m[1], (int) $m[3]) ? [$m[3], $m[2], $m[1]] : null;
        } elseif (preg_match('/\b('.self::YEAR.')\s*[\/.\-]\s*(\d{1,2})\s*[\/.\-]\s*(\d{1,2})\b/u', $v, $m)) {
            $date = checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? [$m[1], $m[2], $m[3]] : null;
        } else {
            $norm = TextNormalizer::normalize($v);
            // "20th of September 1995", "August 28 th, 1995", "December, 5, 1995"
            if (preg_match('/(\d{1,2})\s*(?:st|nd|rd|th)?\s+(?:of\s+)?('.$this->monthRx.')\s+('.self::YEAR.')/u', $norm, $m)) {
                $date = [$m[3], $this->months[TextNormalizer::normalize($m[2])] ?? null, $m[1]];
            } elseif (preg_match('/('.$this->monthRx.')\s+(\d{1,2})\s*(?:st|nd|rd|th)?\s+('.self::YEAR.')/u', $norm, $m)) {
                $date = [$m[3], $this->months[TextNormalizer::normalize($m[1])] ?? null, $m[2]];
            }
            if ($date && (! $date[1] || ! checkdate((int) $date[1], (int) $date[2], (int) $date[0]))) {
                $date = null;
            }
        }
        if (! $date) {
            return null;
        }
        $iso = sprintf('%04d-%02d-%02d', $date[0], $date[1], $date[2]);

        return $this->plausibleBirth($iso) ? $iso : null;
    }

    private function plausibleBirth(string $iso): bool
    {
        return $iso > '1940-01-01' && $iso <= now()->subYears(14)->format('Y-m-d');
    }

    /**
     * Every date range on a line.
     *
     * @return list<array{from: string, to: ?string, current: bool, exact: bool, match: string}>
     */
    private function ranges(string $line): array
    {
        $v = $this->plain($line);
        $low = mb_strtolower($v);
        $out = [];

        // "June to September 2015", "من يونيو إلى سبتمبر 2015": two months, one year.
        $rx2 = '/(?:(?:from|since|من|منذ)\s+)?('.$this->monthRx.')\.?\s*(?:-|–|—|to|until|till|through|إلى|الى|حتى|حتي)\s*('.$this->monthRx.')\.?\s*[,\/\-]?\s*('.self::YEAR.')(?![\d\/.\-]*\d)/u';
        if (preg_match_all($rx2, $low, $all, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach (array_reverse($all) as $m) {
                $a = $this->months[TextNormalizer::normalize($m[1][0])] ?? null;
                $b = $this->months[TextNormalizer::normalize($m[2][0])] ?? null;
                if (! $a || ! $b) {
                    continue;
                }
                $year = (int) $m[3][0];
                $thisMonth = now()->format('Y-m');
                $from = sprintf('%04d-%02d', $b < $a ? $year - 1 : $year, $a);
                $to = sprintf('%04d-%02d', $year, $b);
                $start = mb_strlen(substr($low, 0, $m[0][1]));
                $len = mb_strlen($m[0][0]);
                array_unshift($out, ['from' => min($from, $thisMonth), 'to' => min($to, $thisMonth), 'current' => false, 'exact' => true,
                    'match' => mb_substr($v, $start, $len)]);
                // Blank it out (same number of characters), so it is not read twice below.
                $low = mb_substr($low, 0, $start).str_repeat(' ', $len).mb_substr($low, $start + $len);
            }
        }

        // "From 17-7 to 16-8-2017": the first date has no year — it is the year of the second.
        $low = preg_replace_callback('/(?<![\d\/.\-])(\d{1,2})\s*([\/.\-])\s*(\d{1,2})(?![\d\/.\-])(\s*(?:to|till|until|-|–|إلى|الى|حتى)\s*\d{1,2}\s*[\/.\-]\s*\d{1,2}\s*[\/.\-]\s*('.self::YEAR.'))/u',
            fn ($m) => $m[1].$m[2].$m[3].$m[2].$m[5].$m[4], $low, -1, $count) ?? $low;
        $original = $v;
        if ($count) {
            $v = $low;   // the dates were written out in full; the match is taken from the same text
        }
        $date = '(?:\d{1,2}\s*[\/.\-]\s*\d{1,2}\s*[\/.\-]\s*'.self::YEAR.'|'.$this->monthRx.'\.?\s*[,\/\-]?\s*'.self::YEAR.'|\d{1,2}\s*[\/.\-]\s*'.self::YEAR.'|'.self::YEAR.'\s*[\/.\-]\s*\d{1,2}(?!\d)|'.self::YEAR.')';
        // "-", "to", "- till", "to -" …
        $sep = '(?:\s*[\-–—―\/\\\\]\s*(?:(?:to|until|till|through|إلى|الى|حتى|حتي)\s+)?|\s*(?:to|until|till|through|إلى|الى|حتى|حتي|لـ|ل)\s*(?:[\-–—]\s*)?)';
        $rx = '/(?:(?:from|since|من|منذ)\s+)?('.$date.')(?:'.$sep.'('.$date.'|'.$this->presentRx.'))?/u';

        if (! preg_match_all($rx, $low, $all, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            return $out;
        }
        foreach ($all as $m) {
            $whole = $m[0][0];
            $second = $m[2][0] ?? '';
            $since = preg_match('/^(since|منذ)\s/u', $whole) === 1;
            if ($second === '' && ! $since) {
                continue;     // a lone year is not a job period
            }
            [$from, $fromExact] = $this->month($m[1][0], true);
            $current = $since || ($second !== '' && preg_match('/^'.$this->presentRx.'$/u', trim($second)));
            [$to, $toExact] = $current ? [null, true] : $this->month($second, false);
            if (! $from) {
                continue;
            }
            // Arabic PDFs sometimes write "2020 - 2018" for 2018 – 2020.
            if ($to && $to < $from) {
                [$from, $fromExact] = $this->month($second, true);
                [$to, $toExact] = $this->month($m[1][0], false);
            }
            $out[] = ['from' => $from, 'to' => $to, 'current' => (bool) $current, 'exact' => $fromExact && $toExact,
                'match' => mb_substr($v, mb_strlen(substr($low, 0, $m[0][1])), mb_strlen($whole))];
        }
        // The match must be the text as written, so it can be taken out of the line.
        foreach ($out as $k => $r) {
            if ($count && mb_stripos($original, $r['match']) === false
                && preg_match('/(?:(?:from|since|من)\s+)?\d{1,2}\s*[\/.\-]\s*\d{1,2}\s*(?:to|till|until|-|–|إلى|الى|حتى)\s*\d{1,2}\s*[\/.\-]\s*\d{1,2}\s*[\/.\-]\s*'.self::YEAR.'/iu', $original, $om)) {
                $out[$k]['match'] = $om[0];
            }
        }

        return $out;
    }

    /**
     * One date at the START of a job line, with no end date:
     * "2016 | Worked as Digital Ambassador at CIB", "July 2016 – Trainee at QNB".
     * Read as a job, but always marked "check".
     *
     * @return array{from: string, to: string, current: bool, exact: bool, match: string}|null
     */
    private function singleDate(string $line): ?array
    {
        $v = $this->plain($line);
        $low = mb_strtolower($v);
        if (! preg_match('/^((?:'.$this->monthRx.')\.?\s*[,\/\-]?\s*'.self::YEAR.'|'.self::YEAR.')\s*[|:\-–—]\s*(?=\p{L})/u', $low, $m)) {
            return null;
        }
        [$from] = $this->month($m[1], true);
        [$to] = $this->month($m[1], false);
        if (! $from) {
            return null;
        }

        return ['from' => $from, 'to' => $to, 'current' => false, 'exact' => false, 'match' => mb_substr($v, 0, mb_strlen($m[1]))];
    }

    /** Arabic digits → 0-9, the many dash characters → "-". */
    private function plain(string $text): string
    {
        // "From: Jan 2019 To: Present" — a label colon inside a date range.
        $text = preg_replace('/(?<=\s)(to|till|until|إلى|الى|حتى)\s*:/iu', '$1 ', $text) ?? $text;
        // "April 2012 to date", "till date" → "to present"
        $text = preg_replace('/(?<=\d)(\s*(?:-|–|—)?\s*(?:to|till|until))\s+date\b/iu', '$1 present', $text) ?? $text;
        if (preg_match('/[\d]{2}/', $text)) {
            $mon = '(?<![\p{L}])('.$this->monthRx.')';
            // "Sep’ 2018", "Jan' 19" → "Sep 2018", "Jan 19"
            $text = preg_replace('/'.$mon.'\.?\s*[’\'`´]\s*(?=\d)/iu', '$1 ', $text) ?? $text;
            // Two-digit years in a range: "Sep 18 – May 19", "June 19 – PRESENT", "Aug 2004 – December 09".
            $yy = fn (string $y) => (int) $y <= ((int) date('y')) + 1 ? '20'.$y : '19'.$y;
            $text = preg_replace_callback(
                '/'.$mon.'(\.?\s*)(\d{2}|(?:19|20)\d{2})(?!\d)(\s*(?:-|–|—|to|till|until)\s*)(?:'.$mon.'(\.?\s*)(\d{2})(?![\d\/.,]|\s*,?\s*(?:19|20)\d{2})|('.$this->presentRx.'))/iu',
                function ($m) use ($yy) {
                    $from = strlen($m[3]) === 2 ? $yy($m[3]) : $m[3];
                    $to = ($m[6] ?? '') !== '' ? $m[5].$m[6].$yy($m[7]) : $m[8];
                    if (strlen($m[3]) === 4 && ($m[6] ?? '') === '') {
                        return $m[0];          // nothing two-digit in it
                    }

                    return $m[1].$m[2].$from.$m[4].$to;
                },
                $text,
            ) ?? $text;
        }

        return strtr($text, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            "\u{2010}" => '-', "\u{2011}" => '-', "\u{2012}" => '-', "\u{2212}" => '-', "\u{FE63}" => '-', "\u{FF0D}" => '-',
        ]);
    }

    /** @return array{0: ?string, 1: bool} 'YYYY-MM', whether the month was written */
    private function month(string $v, bool $start): array
    {
        $v = $this->strip($v);
        $thisMonth = now()->format('Y-m');
        // A full date, day first as in Egypt: "15-8-2017", "26/2/2017".
        if (preg_match('/^(\d{1,2})\s*[\/.\-]\s*(\d{1,2})\s*[\/.\-]\s*('.self::YEAR.')$/', $v, $m)) {
            $month = (int) $m[2] >= 1 && (int) $m[2] <= 12 ? (int) $m[2] : ((int) $m[1] <= 12 ? (int) $m[1] : 0);

            return $month ? [min(sprintf('%04d-%02d', $m[3], $month), $thisMonth), true] : [null, false];
        }
        $norm = TextNormalizer::normalize($v);
        if (preg_match('/^('.$this->monthRx.')\s*('.self::YEAR.')$/u', $norm, $m) && isset($this->months[TextNormalizer::normalize($m[1])])) {
            $out = sprintf('%04d-%02d', $m[2], $this->months[TextNormalizer::normalize($m[1])]);

            return [min($out, $thisMonth), true];
        }
        if (preg_match('/^(\d{1,2})\s*[\/.\-]\s*('.self::YEAR.')$/', $v, $m) && (int) $m[1] >= 1 && (int) $m[1] <= 12) {
            return [min(sprintf('%04d-%02d', $m[2], $m[1]), $thisMonth), true];
        }
        if (preg_match('/^('.self::YEAR.')\s*[\/.\-]\s*(\d{1,2})$/', $v, $m) && (int) $m[2] >= 1 && (int) $m[2] <= 12) {
            return [min(sprintf('%04d-%02d', $m[1], $m[2]), $thisMonth), true];
        }
        if (preg_match('/^('.self::YEAR.')$/', $v, $m)) {
            // A year only: January for a start, December for an end — marked "check".
            return [min(sprintf('%04d-%02d', $m[1], $start ? 1 : 12), $thisMonth), false];
        }

        return [null, false];
    }

    // ══ 6 · Governorate and city ════════════════════════════════════

    /** @return array{0: ?string, 1: ?string, 2: string} governorate code, city, mark */
    private function place(array $address, array $lines, array $parsed): array
    {
        $sources = $address;
        foreach ($lines as $i => $raw) {
            if (in_array($parsed['section'][$i] ?? '', ['header', 'personal', 'contact'], true) && trim($raw) !== '') {
                $sources[] = trim($raw);
            }
        }
        foreach ($sources as $source) {
            $best = null;
            // Read piece by piece: "Nasr City, Cairo | 010…"
            foreach (preg_split('/\s*[,،|\-–]\s*|\s{2,}/u', $source) as $piece) {
                $norm = TextNormalizer::normalize($piece);
                if ($norm === '' || $this->hasWord($norm, CvDictionary::NOT_AN_ADDRESS)) {
                    continue;
                }
                $hit = $this->placeIn($norm);
                if (! $hit) {
                    continue;
                }
                [$gov, $isGovName] = $hit;
                if ($best && $best['gov'] !== $gov) {
                    // Two different governorates on the address line: a person decides.
                    return [$best['gov'], $best['city'], 'check'];
                }
                $best ??= ['gov' => $gov, 'city' => null];
                if (! $isGovName) {
                    $best['city'] ??= $this->titleCase(trim(preg_replace('/^(?:[^:]{1,30}:)\s*/u', '', $piece)));
                }
            }
            if ($best) {
                // "Nasr City, Cairo": the piece before the governorate is the city.
                if (! $best['city']) {
                    $pieces = array_values(array_filter(array_map('trim', preg_split('/\s*[,،]\s*/u', $source))));
                    foreach ($pieces as $k => $piece) {
                        $hit = $this->placeIn(TextNormalizer::normalize($piece));
                        if ($hit && $hit[1] && $k > 0 && ! $this->isContactLine($pieces[$k - 1])
                            && ! $this->placeIn(TextNormalizer::normalize($pieces[$k - 1])) && mb_strlen($pieces[$k - 1]) <= 40
                            && ! preg_match('/[@\d]/u', $pieces[$k - 1])) {
                            $best['city'] = $this->titleCase(trim(preg_replace('/^(?:[^:]{1,30}:)\s*/u', '', $pieces[$k - 1])));
                        }
                    }
                }

                return [$best['gov'], $best['city'] ? mb_substr($best['city'], 0, 100) : null, 'found'];
            }
        }

        return [null, null, 'missing'];
    }

    /** @return array{0: string, 1: bool}|null governorate code, whether it is the governorate's own name */
    private function placeIn(string $norm): ?array
    {
        static $index = null;
        if ($index === null) {
            $index = [];
            foreach (CvDictionary::PLACES as $gov => $names) {
                foreach ($names as $k => $name) {
                    foreach ($this->variants($name) as $v) {
                        $index[$v] ??= [$gov, $k <= 1 || in_array($name, ['alex', 'اسكندرية', 'إسكندرية'], true)];
                    }
                }
            }
            uksort($index, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));
        }
        $padded = ' '.$norm.' ';
        foreach ($index as $name => $hit) {
            if (str_contains($padded, ' '.$name.' ')) {
                return $hit;
            }
        }

        return null;
    }

    // ══ 7 · Work history — each line is read for WHAT it is ═════════
    //
    //  1. Every line of the work sections becomes a "unit":
    //       date      a job period ("Mar 2021 – Present", "من 2019 حتى الآن")
    //       header    a short line: a job title, an employer, a place
    //       duty      a bullet, a sentence, a line starting with a verb
    //                 ("Managing …", "Prepared …", "إعداد …")
    //       label     "Company: …" → employer, "Position: …" → title,
    //                 "Location: …" → place; "Industry: …", "Website: …"
    //                 are details the form has no box for (skipped)
    //       jobhead   a sub-heading inside a job ("Key Responsibilities:")
    //  2. A header or date line is cut into parts at "|", " – ", ",",
    //     " at ", "لدى" … ("Accountant | Nile Foods | Riyadh, KSA") and
    //     each part gets points (see titlePoints / employerPoints):
    //       title     official title (ENOC, ESCO, ISCO-08) or a taught
    //                 title +6 · a title word (Manager, محاسب) +3 · the
    //                 title word is the main word ("Bank TELLER") +1
    //       employer  a taught employer, or one already in a profile of
    //                 this workspace +6 · a company word (Co., Bank,
    //                 شركة) +3 · the main word +1 · after "at" / "لدى" +3
    //                 · ends with "Egypt" / "Misr" ("Vodafone Egypt") +2
    //       place     the part is only a place (Cairo, Riyadh, KSA)
    //  3. Which lines go with which dates: the header lines right above
    //     or right below each date line. Lines that sit between two dates
    //     with no duties in between go where this CV usually puts them
    //     (does this person write the title above or below the dates?),
    //     learned from the jobs of the same CV where it is clear.
    //  4. The part with the most title points is the title, the one with
    //     the most employer points is the employer, places go to the
    //     location. When two parts are both clearly titles, or nothing
    //     shows which is which, the job is marked Check — never guessed.
    //  5. A title line after a job's duties, followed by more duties
    //     ("Financial Analyst" under the same company), is its own job:
    //     the same employer, no dates, marked Check.
    //  6. A numbered line ("2) Chief Accountant") starts a new job.

    /**
     * @param  list<string>  $names  the person's name(s), to skip page footers ("Page 1 | Tarek …")
     * @return array{0: list<array>, 1: string} jobs (newest first), mark
     */
    private function work(array $lines, array $parsed, bool $wrapped = false, array $names = []): array
    {
        $max = (int) config('beneficiaries.max.work_history', 20);
        $inSection = in_array('experience', $parsed['section'], true);
        $units = $this->jobUnits($lines, $parsed, $wrapped, $names);
        if (! $units) {
            return [[], $inSection ? 'check' : 'missing'];
        }

        $anchors = array_keys(array_filter($units, fn ($u) => $u['role'] === 'date'));
        $owner = $this->headerOwners($units, $anchors);

        // ── Jobs with dates ─────────────────────────────────────────
        $jobs = [];
        $unread = false;
        foreach ($anchors as $a) {
            $mine = array_keys(array_filter($owner, fn ($o) => $o === $a));
            $job = $this->jobFrom($units, $a, $mine);
            if ($job !== null) {
                $jobs[$a] = $job;
            } else {
                $unread = true;         // dates with no title and no employer next to them
            }
        }

        // ── Jobs without dates: a title line followed by duties ─────
        $claimed = $owner + array_fill_keys($anchors, -1);
        $count = count($units);
        for ($k = 0; $k < $count; $k++) {
            $u = $units[$k];
            if (isset($claimed[$k]) || $u['role'] !== 'header' || ! $this->titled($u) && ! ($u['numbered'] && $u['t'] > 0)) {
                continue;
            }
            $run = [$k];
            $n = $k + 1;
            for (; $n < $count && count($run) < 3; $n++) {
                $w = $units[$n];
                if ($w['role'] === 'skip') {
                    continue;
                }
                if ($w['role'] !== 'header' || isset($claimed[$n]) || $w['sec'] !== $u['sec'] || $this->titled($w) || $w['numbered']) {
                    break;
                }
                $run[] = $n;
            }
            while ($n < $count && $units[$n]['role'] === 'skip') {
                $n++;
            }
            if ($n >= $count || ! in_array($units[$n]['role'], ['duty', 'jobhead'], true) || $units[$n]['sec'] !== $u['sec']) {
                continue;
            }
            $job = $this->jobFrom($units, null, $run);
            if ($job === null) {
                continue;
            }
            // Under the same company: a job above it, with duties in between.
            if ($job['employer'] === null && ($parent = $this->enclosingJob($units, $jobs, $k)) !== null) {
                $job['employer'] = $parent['employer'];
            }
            $job['check'][] = 'dates';
            $jobs[$k] = $job;
            foreach ($run as $r) {
                $claimed[$r] = $k;
            }
        }

        // ── The CV's own order (title line above / below the employer line)
        //    settles the jobs where the words did not.
        $order = $this->usualOrder($jobs);
        foreach ($jobs as $k => $job) {
            if ($job['guess'] && $order !== null) {
                $jobs[$k] = $this->jobFrom($units, $job['anchor'], $job['units'], $order) ?? $job;
                if ($job['anchor'] === null) {
                    $jobs[$k]['employer'] ??= $job['employer'];
                    $jobs[$k]['check'] = array_values(array_unique([...$jobs[$k]['check'], 'dates']));
                }
            }
        }

        ksort($jobs);
        $jobs = array_values($jobs);

        // ── The responsibilities: the lines under each job, up to the next job ──
        $used = [];
        foreach ($units as $k => $u) {
            if (isset($claimed[$k]) || $u['role'] === 'skip') {
                for ($l = $u['i']; $l <= $u['j']; $l++) {
                    $used[$l] = true;
                }
            }
        }
        usort($jobs, fn ($a, $b) => $a['first'] <=> $b['first']);
        foreach ($jobs as $k => $job) {
            $end = isset($jobs[$k + 1]) ? $jobs[$k + 1]['first'] : count($lines);
            $jobs[$k]['responsibilities'] = $this->responsibilities($lines, $parsed, $job['last'] + 1, $end, $used, $wrapped, $names);
        }
        $jobs = array_slice($this->mergeJobs($jobs, $units), 0, $max);

        $sure = true;
        foreach ($jobs as $job) {
            if ($job['check'] || $job['unknown_section']) {
                $sure = false;
            }
        }
        if ($unread) {
            $sure = false;
        }

        $jobs = array_map(fn ($j) => [
            'title'    => $j['title'],
            'employer' => $j['employer'],
            'location' => $j['location'],
            'from'     => $j['from'],
            'to'       => $j['to'],
            'current'  => $j['current'],
            'responsibilities' => $j['responsibilities'],
            'check'    => array_values(array_unique($j['check'])),
        ], $jobs);
        // Newest first, like the form (jobs with no dates last).
        usort($jobs, fn ($a, $b) => [$b['current'], $b['from'] ?? ''] <=> [$a['current'], $a['from'] ?? '']);
        $mark = $jobs ? ($sure ? 'found' : 'check') : ($inSection || $anchors ? 'check' : 'missing');

        return [$jobs, $mark];
    }

    /**
     * The lines of the work sections, one unit each (see the header above).
     *
     * @return list<array>
     */
    private function jobUnits(array $lines, array $parsed, bool $wrapped, array $names = []): array
    {
        $units = [];
        foreach ($this->jobLines($lines, $parsed, $wrapped, $names) as $line) {
            [$i, $j, $sec, $trim, $kind] = [$line['i'], $line['j'], $line['sec'], $line['text'], $line['kind']];
            $internLine = $line['intern'];
            $base = ['i' => $i, 'j' => $j, 'sec' => $sec, 'bold' => isset($parsed['bold'][$i]),
                'bullet' => false, 'numbered' => false, 'text' => $trim, 'date' => null, 'label' => null, 'pieces' => [], 't' => 0.0, 'e' => 0.0];
            if ($kind !== null) {
                $units[] = ['role' => $kind] + $base;      // jobhead, boundary, skip
                continue;
            }

            $bullet = $this->isBulleted($trim);
            $numbered = ! $bullet && preg_match(self::NUMBERED, $trim) === 1;
            $text = trim(preg_replace(self::NUMBERED, '', preg_replace(self::BULLET, '', $trim) ?? $trim) ?? $trim);
            $text = trim(preg_replace(self::BULLET, '', $text) ?? $text);
            // Web addresses are not part of a title or an employer.
            $text = trim(preg_replace('~\(?(?:https?://|www\.)\S+\)?~iu', ' ', $text) ?? $text);
            $text = preg_replace('/^[\s|,\-–]+|[\s|,\-–]+$/u', '', preg_replace('/\s{2,}/u', ' ', $text) ?? $text) ?? $text;
            if ($text === '') {
                $units[] = ['role' => 'skip'] + $base;
                continue;
            }
            $base = ['bullet' => $bullet, 'numbered' => $numbered, 'text' => $text] + $base;

            // "Industry: FMCG", "Company: Nile Foods", "Period: Feb 2019 – Present"
            $label = $line['label'];
            $value = $label !== null ? trim(preg_replace('/^[^:]{1,40}:\s*/u', '', $text) ?? $text) : $text;
            if ($label === null && preg_match('/^([^:]{2,30}):\s*(.*)$/u', $text, $m) && ($kind = $this->jobLabel($m[1])) !== null) {
                [$label, $value] = [$kind, trim($m[2])];
            }

            $r = $this->ranges($value);
            $single = false;
            if (! $r && $sec === 'experience' && $label === null && ($d = $this->singleDate($value))) {
                $r = [$d];
                $single = true;
            }
            if ($internLine && ! $r) {
                continue;
            }
            if ($r) {
                $rest = $this->withoutDates($value, $r[0]['match']);
                $rest = trim(preg_replace('/^(?:from|since|من|منذ)\s+/iu', '', $rest) ?? $rest);
                $rest = trim(preg_replace('/\s+(?:from|since|من|منذ)$/iu', '', $rest) ?? $rest);
                $rest = trim(preg_replace('/^(?:period|duration|dates?|الفترة|المدة)\s*:\s*/iu', '', $rest) ?? $rest);
                $rest = trim(preg_replace('/[\[\]]+/u', ' ', $rest) ?? $rest);
                $restNorm = TextNormalizer::normalize($rest);
                // A degree or a course with years is not a job.
                $study = $restNorm !== '' && ($this->levelIn($restNorm) || $this->hasWord($restNorm, CvDictionary::COURSE_WORDS))
                    && ! $this->hasWord($restNorm, CvDictionary::TITLE_WORDS);
                $unit = ['role' => $study ? 'other' : 'date', 'date' => $r[0] + ['single' => $single], 'label' => $label === 'dates' ? null : $label, 'rest' => $rest] + $base;
                $unit['pieces'] = $label && $label !== 'dates' ? $this->forcedPiece($rest, $label, $i) : $this->pieces($rest, $i, $unit['bold']);
                $units[] = $unit;
                continue;
            }
            if ($label === 'skip' || $label === 'dates') {
                $units[] = ['role' => 'skip', 'label' => $label] + $base;
                continue;
            }
            if ($label !== null) {
                $units[] = ['role' => $value === '' ? 'skip' : 'header', 'label' => $label, 'pieces' => $this->forcedPiece($value, $label, $i)] + $base;
                continue;
            }
            $units[] = ['role' => '?', 'pieces' => $this->pieces($text, $i, $base['bold'])] + $base;
        }

        $this->scorePieces($units);

        foreach ($units as $k => $u) {
            $units[$k]['t'] = $u['pieces'] ? max(array_column($u['pieces'], 't')) : 0.0;
            $units[$k]['e'] = $u['pieces'] ? max(array_column($u['pieces'], 'e')) : 0.0;
            if ($u['role'] === '?') {
                $units[$k]['role'] = $this->lineRole($units[$k]);
            }
            // "Challenged the stamp duty tax inspection … (2011-2013)": a duty that mentions years.
            if ($u['role'] === 'date' && $u['label'] === null) {
                $rest = (string) ($u['rest'] ?? '');
                $words = $rest === '' ? 0 : count(preg_split('/\s+/u', $rest) ?: []);
                if (($this->startsWithVerb($rest) && ! $this->titled($units[$k]) && $words > 4) || $words > 25) {
                    $units[$k]['role'] = 'duty';
                }
            }
        }

        return $units;
    }

    /**
     * The lines of the work sections, ready to be read: page footers and
     * bullet-only lines dropped; the person's own name (a page header) and
     * web addresses skipped; lines a PDF broke in two joined again;
     * "08-2018" / "Present" on two lines joined into one period; and
     * table-style labels ("Employer" / ":" / "AUR Leasing Co.") put back
     * together as "Employer: AUR Leasing Co.".
     *
     * @return list<array{i: int, j: int, sec: string, text: string, kind: ?string, label: ?string, intern: bool}>
     */
    private function jobLines(array $lines, array $parsed, bool $wrapped, array $names): array
    {
        $nameNorms = array_values(array_filter(array_map(fn ($n) => TextNormalizer::normalize($n), $names)));
        $isName = function (string $t) use ($nameNorms): bool {
            $n = TextNormalizer::normalize($t);
            if ($n === '' || preg_match('/\d/', $n) || substr_count($n, ' ') > 5) {
                return false;
            }
            foreach ($nameNorms as $name) {
                $w = explode(' ', $name);
                $have = explode(' ', $n);
                if ($n === $name || (count($w) >= 2 && in_array($w[0], $have, true) && in_array($w[count($w) - 1], $have, true))) {
                    return true;
                }
            }

            return false;
        };
        // How wide the PDF's lines are: a line near this width that stops mid-sentence continues on the next line.
        $lengths = array_map(fn ($l) => mb_strlen(trim($l)), array_filter($lines, fn ($l) => trim($l) !== ''));
        sort($lengths);
        $width = $lengths ? $lengths[(int) floor(count($lengths) * 0.9) - (count($lengths) >= 10 ? 1 : 0)] ?? end($lengths) : 0;

        // 1. The raw lines of the work sections.
        $raw = [];
        foreach ($lines as $i => $l) {
            $sec = $parsed['section'][$i] ?? '';
            $intern = $sec === 'volunteering' && preg_match('/\b(?:intern|internship|trainee)\b|متدرب|تدريب صيفي/iu', $l);
            if (! in_array($sec, self::JOB_SECTIONS, true) && ! $intern) {
                continue;
            }
            $t = trim(str_replace(self::BOLD, '', $l));
            if ($t === '') {
                $raw[] = null;         // a blank line
                continue;
            }
            $kind = isset($parsed['job_heads'][$i]) ? 'jobhead' : ((isset($parsed['known'][$i]) || in_array($i, $parsed['unknown'], true)) ? 'boundary' : null);
            if ($kind === null && ($this->isPageFooter($t) || preg_match('/^[^\p{L}\p{N}]+$/u', $t) || preg_match('/^\d{1,2}$/', $t))) {
                continue;              // "▪", "1" (a page number)
            }
            if ($kind === null && ($isName($t) || $this->isNoise($t))) {
                $kind = 'skip';
            }
            $raw[] = ['i' => $i, 'j' => $i, 'sec' => $intern ? 'experience' : $sec, 'text' => $t, 'kind' => $kind, 'label' => null, 'intern' => (bool) $intern];
        }

        // 2. Lines a PDF broke in two.
        $out = [];
        $n = count($raw);
        for ($k = 0; $k < $n; $k++) {
            $cur = $raw[$k];
            if ($cur === null) {
                $out[] = null;
                continue;
            }
            // (In a Word file only a line starting in small letters continues the line above: a line break inside a paragraph.)
            while ($cur['kind'] === null && $k + 1 < $n && ($nx = $raw[$k + 1]) !== null && $nx['kind'] === null
                && $nx['i'] === $cur['j'] + 1 && $nx['sec'] === $cur['sec']
                && ($wrapped ? $this->continues($cur['text'], $nx['text'], $raw[$k + 2] ?? null, $width)
                    : (preg_match('/^[a-z]/u', $nx['text']) && ! preg_match('/[.:;!?؛]$/u', $cur['text']) && ! $this->isBulleted($nx['text']) && ! $this->loneDate($nx['text'], true)))) {
                $cur['text'] .= ' '.$nx['text'];
                $cur['j'] = $nx['j'];
                $k++;
            }
            $out[] = $cur;
        }

        // 3. A period on two lines ("08-2018" / "Present", "Feb. 2011" / "Currently").
        $clean = array_values(array_filter($out));
        $result = [];
        $count = count($clean);
        for ($k = 0; $k < $count; $k++) {
            $cur = $clean[$k];
            if ($cur['kind'] === null && $k + 1 < $count && $clean[$k + 1]['kind'] === null && $clean[$k + 1]['sec'] === $cur['sec']
                && $this->loneDate($cur['text'], false) && $this->loneDate($clean[$k + 1]['text'], true)) {
                $cur['text'] .= ' – '.$clean[$k + 1]['text'];
                $cur['j'] = $clean[$k + 1]['j'];
                $k++;
            }
            $result[] = $cur;
        }

        // 4. Table-style labels: "Employer" / ":" / "AUR Leasing Co.", "Reporting To" / "Chief Executive Officer".
        $final = [];
        $count = count($result);
        for ($k = 0; $k < $count; $k++) {
            $cur = $result[$k];
            if ($cur['kind'] !== null || ($label = $this->aloneLabel($cur['text'])) === null) {
                $final[] = $cur;
                continue;
            }
            $v = $k + 1;
            while ($v < $count && $result[$v]['kind'] === null && preg_match('/^[\s:：\-–]*$/u', $result[$v]['text'])) {
                $v++;              // the ":" on a line of its own
            }
            $cur['kind'] = 'skip';
            $final[] = $cur;
            for ($q = $k + 1; $q < $v; $q++) {
                $final[] = ['kind' => 'skip'] + $result[$q];
            }
            if ($v >= $count || $result[$v]['kind'] !== null || $result[$v]['sec'] !== $cur['sec']) {
                $k = $v - 1;
                continue;
            }
            if ($label === 'skip') {
                // A detail with a paragraph under it ("Employer Profile", "Position Purpose"): skipped up to the next label, date or sub-heading.
                for (; $v < $count; $v++) {
                    $t = $result[$v]['text'];
                    if ($result[$v]['kind'] !== null || $this->aloneLabel($t) !== null || $this->ranges($t) || $this->isBulleted($t)
                        || ($v > $k + 1 && preg_match('/^[^:]{2,30}:/u', $t))) {
                        break;
                    }
                    $final[] = ['kind' => 'skip'] + $result[$v];
                }
                $k = $v - 1;
                continue;
            }
            $final[] = ['label' => $label, 'text' => $cur['text'].': '.$result[$v]['text']] + $result[$v];
            $k = $v;
        }

        return $final;
    }

    /** Does the next PDF line continue this one? */
    private function continues(string $cur, string $next, ?array $after, int $width): bool
    {
        if ($this->isBulleted($next) || preg_match('/^\(?\d{1,2}\s*[\-.)]\s/u', $next) || preg_match('/[.:;!?؛]$/u', $cur) || $this->isBulleted($cur) && mb_strlen($cur) < 25) {
            return false;
        }
        $nextWords = count(preg_split('/\s+/u', $next));
        // "… Transaction Advisory Services" / "and supporting the practice in KSA and Egypt"
        if (preg_match('/^[a-z(]/u', $next) && ! $this->loneDate($next, true)) {
            return true;
        }
        // "(September 2014 -" / "July 2015)", "… Universal for Metal and" / "Supporting Industries"
        if (preg_match('/(?:[,&\-–\/]|\b(?:and|of|for|the|in|at|&))$/iu', $cur) && $nextWords <= 8) {
            return true;
        }
        // "Al Ahli Kuwait Egypt Leasing Co. (Previously - Piraeus Egypt" / "Leasing Company)"
        if (substr_count($cur, '(') > substr_count($cur, ')') && str_contains($next, ')') && $nextWords <= 8) {
            return true;
        }
        if ($this->ranges($cur) || $this->singleDate($cur)) {
            // A date line near the full width, and a short end on the next line ("Service Group").
            return $width > 0 && mb_strlen($cur) >= 0.8 * $width && $nextWords <= 5 && ! $this->ranges($next) && ! $this->aloneLabel($next)
                || ($nextWords <= 4 && $after === null && ! $this->ranges($next));
        }
        // "… Organizational Development" / "Specialist": the title's main word on the next line.
        $isTitleWord = fn ($w) => isset($this->words['title'][$w]);
        $nw = explode(' ', TextNormalizer::normalize($next));
        $cw = explode(' ', TextNormalizer::normalize($cur));

        return count($nw) <= 2 && count($cw) >= 3 && count(array_filter($nw, $isTitleWord)) === count($nw) && ! $isTitleWord(end($cw));
    }

    /** The whole line is one date ("08-2018", "Feb. 2011", "2016") — or, for the end of a period, "Present". */
    private function loneDate(string $t, bool $orPresent): bool
    {
        $t = trim($this->plain($t), " \t.,|()[]");
        if ($t === '') {
            return false;
        }
        if ($orPresent && preg_match('/^'.$this->presentRx.'$/iu', $t)) {
            return true;
        }
        $low = mb_strtolower($t);

        return (bool) preg_match('/^(?:'.$this->monthRx.'\.?\s*[,\/\-]?\s*'.self::YEAR.'|\d{1,2}\s*[\/.\-]\s*'.self::YEAR.'|'.self::YEAR.'\s*[\/.\-]\s*\d{1,2}|\d{1,2}\s*[\/.\-]\s*\d{1,2}\s*[\/.\-]\s*'.self::YEAR.'|'.self::YEAR.')$/u', $low);
    }

    /** A job label on a line of its own ("Employer", "Position:", "Reporting To"): its kind. */
    private function aloneLabel(string $t): ?string
    {
        $t = preg_replace('/^[\s:：\-–]+|[\s:：\-–]+$/u', '', $t) ?? $t;
        if ($t === '' || mb_strlen($t) > 30 || str_contains($t, ':')) {
            return null;
        }

        return $this->jobLabel($t);
    }

    /** A line that is not part of the CV's content: a CV template's credit line, a web address alone. */
    private function isNoise(string $t): bool
    {
        if (preg_match('/\b(?:cv|resume)\s+template\b/iu', $t)) {
            return true;
        }
        $bare = trim(preg_replace('~(?:https?://|www\.)\S+|\b[\w.-]+\.(?:com|net|org|co\.uk|com\.eg|eg)\b\S*~iu', '', $t) ?? $t);

        return $bare !== $t && ! preg_match('/\p{L}{2,}.*\p{L}{2,}/u', $bare);
    }

    /** What a line inside a job is when it has no dates and no label: header, duty or skip. */
    private function lineRole(array $u): string
    {
        $text = $u['text'];
        $words = count(preg_split('/\s+/u', $text));
        if ($this->isContactLine($text) && ! $u['t'] && ! $u['e']) {
            return 'skip';
        }
        // "Current Job:", "Historical Experience:" — a small label with nothing after it.
        if (preg_match('/:\s*$/u', $text) && $words <= 3 && $u['t'] < 3 && $u['e'] < 3) {
            return 'skip';
        }
        $pieces = array_filter($u['pieces'], fn ($p) => $p['forced'] === null);
        // A line that names a job AND an employer ("Financial Reporting Manager – Easy Group").
        $jobLine = array_filter($pieces, fn ($p) => $p['t'] >= 3 && $p['t'] > $p['e'])
            && array_filter($pieces, fn ($p) => $p['e'] >= 3 && $p['e'] > $p['t'])
            && ! preg_match('/[.;؛]$/u', $text);
        if ($u['bullet']) {
            return $words <= 12 && $jobLine ? 'header' : 'duty';     // "▪ Financial Reporting Manager – Easy Group"
        }
        if ($words > ($jobLine ? 20 : 14) || mb_strlen($text) > 160) {
            return 'duty';
        }
        if ($u['numbered'] && ($u['t'] >= 3 || $u['e'] >= 3) && $words <= 10) {
            return 'header';
        }
        // A sentence: "Opening accounts and selling banking products." ("Nile Foods Co." is not one.)
        if (preg_match('/[.;؛]$/u', $text) && ($words > 12 || ($u['t'] < 3 && $u['e'] < 3 && $words >= 3) || ($this->startsWithVerb($text) && $words >= 3))
            && ! preg_match('/(?:^|\s)(?:co|ltd|inc|corp|est|s\.?a\.?e|l\.?l\.?c|plc|jsc|w\.?l\.?l|k\.?s\.?c\.?c?)\.$/iu', $text)) {
            return 'duty';
        }
        // "Reporting to CFO with unlimited signing authority", "Responsible for …": a duty, whatever it names.
        $second = explode(' ', TextNormalizer::normalize($text))[1] ?? '';
        if ($this->startsWithVerb($text) && $words >= 4 && in_array($second, ['to', 'for', 'the', 'a', 'an', 'all', 'with', 'on', 'in', 'of', 'our', 'its', 'their', 'new', 'and'], true)
            && ! ($jobLine && $second === 'and')) {
            return 'duty';
        }
        if (! $jobLine && $this->startsWithVerb($text) && ($u['t'] < 3 || $words > 5) && $words >= 2) {
            return 'duty';
        }
        if ($u['numbered']) {
            return 'duty';      // "1. Preparing …" — a numbered duty
        }

        return 'header';
    }

    private function startsWithVerb(string $text): bool
    {
        $first = explode(' ', TextNormalizer::normalize($text))[0] ?? '';
        if ($first === '') {
            return false;
        }
        if (preg_match('/^[a-z]{3,}(?:ing|ed)$/', $first)) {
            return true;
        }
        if (isset($this->words['duty'][$first])) {
            return true;
        }
        // "responsible for", "مسؤول عن"
        $two = implode(' ', array_slice(explode(' ', TextNormalizer::normalize($text)), 0, 2));

        return isset($this->words['duty'][$two]);
    }

    /** "Industry" → skip, "Company" → employer, "Location" → place, "Position" → title, "Period" → dates. */
    private function jobLabel(string $label): ?string
    {
        $norm = TextNormalizer::normalize($label);
        foreach (CvDictionary::JOB_LABELS as $kind => $words) {
            foreach ($words as $w) {
                if (TextNormalizer::normalize($w) === $norm) {
                    return $kind;
                }
            }
        }

        return null;
    }

    /** The value of "Company: …" / "Position: …" / "Location: …" — one part with a known meaning. */
    private function forcedPiece(string $value, string $label, int $line): array
    {
        $value = $this->strip($value);
        if ($value === '') {
            return [];
        }

        return [['text' => $value, 'start' => 0, 'end' => strlen($value), 'src' => $value, 'line' => $line, 'marker' => false,
            'bold' => false, 'forced' => $label, 't' => 0.0, 'e' => 0.0, 'p' => 0.0]];
    }

    /**
     * A header line cut into parts: "Project Engineer | Hassan Allam Holding | Riyadh, KSA",
     * "Accountant at Delta", "مدير مبيعات - شركة جهينة - القاهرة", "محاسب بشركة النيل".
     *
     * @return list<array{text: string, start: int, end: int, src: string, line: int, marker: bool}>
     */
    private function pieces(string $text, int $line, bool $bold = false): array
    {
        $src = $text;
        $rx = '/(?<=\p{Ll}{3}|\p{Lu}{2})\.\s+(?=\p{Lu})|\s+(at|@|with|in|لدى|لدي|في)\s+|\s+(ب)(?=شركة|مجموعة|بنك|مصنع|فندق|مستشفى)|\s*\|\s*|\s+[—–\-]\s+|\s*[—–]\s*|\s*[,،]\s+|\s*\(\s*|\s*\)\s*/u';
        preg_match_all($rx, $src, $seps, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        $out = [];
        $at = 0;
        $marker = null;
        $add = function (int $from, int $to, ?string $marker) use (&$out, $src, $line, $bold) {
            $piece = substr($src, $from, $to - $from);
            $clean = $this->strip($piece);
            if ($clean === '' || ! preg_match('/\p{L}/u', $clean)) {
                return;
            }
            $lead = strpos($piece, $clean);
            $from += $lead === false ? 0 : $lead;
            $out[] = ['text' => $clean, 'start' => $from, 'end' => $from + strlen($clean), 'src' => $src, 'line' => $line,
                'marker' => $marker, 'bold' => $bold, 'forced' => null, 't' => 0.0, 'e' => 0.0, 'p' => 0.0];
        };
        foreach ($seps as $s) {
            $add($at, $s[0][1], $marker);
            $marker = ($s[1][0] ?? '') !== '' && ($s[1][1] ?? -1) >= 0 ? $s[1][0] : ((($s[2][0] ?? '') !== '' && ($s[2][1] ?? -1) >= 0) ? $s[2][0] : null);
            $at = $s[0][1] + strlen($s[0][0]);
        }
        $add($at, strlen($src), $marker);

        return $out;
    }

    /** Points for every part (one look-up of all the titles in the backbone for the whole CV). */
    private function scorePieces(array &$units): void
    {
        $forms = [];
        foreach ($units as $u) {
            foreach ($u['pieces'] as $p) {
                foreach ($this->titleForms($p['text']) as $f) {
                    $forms[$f] = true;
                }
            }
        }
        $this->titleHits = $this->knownTitles(array_keys($forms));

        foreach ($units as $k => $u) {
            $pieces = $u['pieces'];
            foreach ($pieces as $n => $p) {
                $pieces[$n] = $this->scored($p);
            }
            // "… Future Rise Language School Group Financial Director": the company and the title with nothing
            // between them — cut after the last company word when what follows is clearly a title.
            for ($n = count($pieces) - 1; $n >= 0; $n--) {
                if (($cut = $this->titleTail($pieces[$n])) !== null) {
                    array_splice($pieces, $n, 1, [$this->scored($cut[0]), $this->scored($cut[1])]);
                }
            }
            // "in" / "with" / "في" only split when an employer or a place follows ("Teacher in charge" stays whole).
            for ($n = count($pieces) - 1; $n > 0; $n--) {
                $p = $pieces[$n];
                if (in_array($p['marker'], ['in', 'with', 'في'], true) && $p['e'] < 3 && $p['p'] < 6) {
                    $prev = $pieces[$n - 1];
                    $prev['text'] = $this->strip(substr($prev['src'], $prev['start'], $p['end'] - $prev['start']));
                    $prev['end'] = $p['end'];
                    $pieces[$n - 1] = $this->scored($prev);
                    array_splice($pieces, $n, 1);
                }
            }
            $units[$k]['pieces'] = array_values($pieces);
        }
    }

    /** @return array{0: array, 1: array}|null the part cut into company + title */
    private function titleTail(array $p): ?array
    {
        if ($p['forced'] !== null || $p['e'] < 3 || $this->isArabic($p['text'])) {
            return null;
        }
        preg_match_all('/\S+/u', $p['text'], $m, PREG_OFFSET_CAPTURE);
        $tokens = $m[0];
        $count = count($tokens);
        if ($count < 4) {
            return null;
        }
        for ($k = $count - 3; $k >= 1; $k--) {
            $w = TextNormalizer::normalize($tokens[$k][0]);
            if (! isset($this->words['employer'][$w])) {
                continue;
            }
            $cutAt = $p['start'] + $tokens[$k + 1][1];
            $right = substr($p['src'], $cutAt, $p['end'] - $cutAt);
            $left = rtrim(substr($p['src'], $p['start'], $cutAt - $p['start']));
            $last = TextNormalizer::normalize($tokens[$count - 1][0]);
            if (! isset($this->words['title'][$last]) || $this->titlePoints($right) < 4 || $this->employerPoints($left, false) < 3) {
                return null;
            }

            return [
                ['text' => $this->strip($left), 'end' => $p['start'] + strlen($left)] + $p,
                ['text' => $this->strip($right), 'start' => $cutAt, 'marker' => null] + $p,
            ];
        }

        return null;
    }

    private function scored(array $p): array
    {
        if ($p['forced'] !== null) {
            return $p;
        }
        $p['t'] = $this->titlePoints($p['text']) + ($p['bold'] ? 0.5 : 0);
        // "at Nile Foods", "لدى شركة النيل" — but not "with unlimited signing authority".
        $p['e'] = $this->employerPoints($p['text'], $p['marker'] !== null && preg_match('/^(?:\p{Lu}|\p{Arabic}|\d)/u', $p['text']) === 1);
        $p['p'] = $this->isPlace($p['text']) ? 6.0 : 0.0;
        if ($p['p'] && $p['t'] < 6) {
            $p['t'] = 0.0;       // "Cairo" is a place, not a title
            $p['e'] = 0.0;
        }

        return $p;
    }

    /** @return list<string> the forms a part is looked up by in the backbone */
    private function titleForms(string $text): array
    {
        $h = $this->jobHeader($text);
        $norm = TextNormalizer::normalize($h);
        if ($norm === '' || substr_count($norm, ' ') > 8) {
            return [];
        }
        $out = [$norm, TextNormalizer::normalize(OccupationClassifier::withoutNoise($h))];
        $words = explode(' ', $norm);
        for ($n = 2; $n <= 3 && $n < count($words); $n++) {
            $out[] = implode(' ', array_slice($words, -$n));     // "maintenance technician" of "Senior Maintenance Technician"
            $out[] = implode(' ', array_slice($words, 0, $n));   // "محاسب اول" of "محاسب اول بالشركة"
        }

        return array_values(array_unique(array_filter($out)));
    }

    /** @return array<string, true> the forms that are official titles */
    private function knownTitles(array $forms): array
    {
        $hits = [];
        foreach ($forms as $f) {
            if (isset($this->ruleTitles[$f])) {
                $hits[$f] = true;
            }
        }
        if (! $forms) {
            return $hits;
        }
        try {
            foreach (array_chunk($forms, 500) as $chunk) {
                foreach (DB::table('occupation_labels')->whereIn('normalized', $chunk)->distinct()->pluck('normalized') as $n) {
                    $hits[$n] = true;
                }
            }
        } catch (\Throwable) {
            // The backbone is not loaded: the title words still work.
        }

        return $hits;
    }

    /** Title points of one part (see the header of this section). */
    private function titlePoints(string $text): float
    {
        $h = $this->jobHeader($text);
        $norm = TextNormalizer::normalize($h);
        if ($norm === '') {
            return 0.0;
        }
        $words = explode(' ', $norm);
        $t = 0.0;
        $forms = $this->titleForms($text);
        if (isset($this->titleHits[$forms[0] ?? '']) || isset($this->titleHits[$forms[1] ?? ''])) {
            $t += 6;
        } elseif (array_filter(array_slice($forms, 2), fn ($f) => isset($this->titleHits[$f]))) {
            $t += 2;
        }
        if ($this->hasAny($norm, 'title')) {
            $t += 3;
            $head = $this->isArabic($h) ? $words[0] : $words[count($words) - 1];
            if (isset($this->words['title'][$head])) {
                $t += 1;
            }
        }
        // "Head of Finance Leasing Company", "Director of Finance", "Manager for Retail Sales": a title,
        // even when a company word comes after "of".
        if (count($words) >= 3 && isset($this->words['title'][$words[0]]) && in_array($words[1], ['of', 'for'], true)) {
            $t += 3;
        }
        if (count($words) > 12) {
            $t -= 3;
        } elseif (count($words) > 8) {
            $t -= 1;
        }
        if (preg_match('/\d/u', $norm)) {
            $t -= 1;
        }

        return max(0.0, $t);
    }

    /** Employer points of one part (see the header of this section). */
    private function employerPoints(string $text, bool $afterAt): float
    {
        $norm = TextNormalizer::normalize($text);
        if ($norm === '') {
            return 0.0;
        }
        $words = explode(' ', $norm);
        $e = 0.0;
        if (isset($this->knownEmployers[$norm])) {
            $e += 6;
            $this->used += $this->knownEmployers[$norm] ? [$this->knownEmployers[$norm] => true] : [];
        } elseif (isset($this->words['known_employer'][$norm])) {
            $e += 6;                  // "Xerox", "EY", "Citibank"
        } elseif (count($words) <= 8 && ($this->hasAny($norm, 'known_employer_in') || (in_array($words[0], ['ey', 'pwc', 'kpmg', 'citi'], true) && count($words) <= 5))) {
            $e += 4;                  // "KPMG Hazem Hassan", "EY Cairo office"
        }
        // "Central Accounting Department", "Planning & Organization Department": a department, not the employer.
        if (preg_match('/\b(?:department|dept|division|unit|section|sector|team)$|^(?:قسم|اداره|إدارة|ادارة)\s/u', $norm)) {
            return 0.0;
        }
        if ($this->hasAny($norm, 'employer')) {
            $e += 3;
            $head = $this->isArabic($text) ? $words[0] : $words[count($words) - 1];
            if (isset($this->words['employer'][$head])) {
                $e += 1;
            }
        }
        if ($afterAt) {
            $e += 3;
        }
        if (count($words) >= 2 && in_array($words[count($words) - 1], ['egypt', 'misr', 'مصر', 'egypte'], true)) {
            $e += 2;          // "Vodafone Egypt", "Emaar Misr"
        }
        if (count($words) >= 2 && in_array($words[0], ['al', 'el'], true)) {
            $e += 1;          // "Al Futtaim", "El Sewedy"
        }
        if (preg_match_all('/(?<![\p{L}])([A-Z]{2,6})(?![\p{L}])/u', $text, $m)) {
            foreach ($m[1] as $acronym) {
                if (! in_array($acronym, ['HR', 'IT', 'QA', 'QC', 'CEO', 'CFO', 'COO', 'CTO', 'CIO', 'VP', 'GM', 'PR', 'IR', 'ERP', 'SAP', 'BI', 'UX', 'UI', 'KSA', 'UAE', 'USA', 'UK', 'FMCG', 'HSE', 'MEP', 'OR', 'AND'], true)) {
                    $e += 1;
                    break;
                }
            }
        }

        return $e;
    }

    /** The whole part is a place: "Cairo", "Riyadh", "KSA", "New Cairo", "Doha". */
    private function isPlace(string $text): bool
    {
        $norm = TextNormalizer::normalize(preg_replace('/^(?:in|at|في)\s+/iu', '', $text) ?? $text);

        return $norm !== '' && isset($this->words['place'][$norm]);
    }

    /** Does the normalised text contain a word or phrase of this word list? (pre-built in wordLists) */
    private function hasAny(string $norm, string $list): bool
    {
        foreach (explode(' ', $norm) as $w) {
            if (isset($this->words[$list][$w])) {
                return true;
            }
        }
        $padded = ' '.$norm.' ';
        foreach ($this->phrases[$list] ?? [] as $p) {
            if (str_contains($padded, ' '.$p.' ')) {
                return true;
            }
        }

        return false;
    }

    private function titled(array $u): bool
    {
        foreach ($u['pieces'] as $p) {
            if ($p['forced'] === 'title' || ($p['t'] >= 3 && $p['t'] > $p['e'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Which header lines belong to which date line.
     *
     * @param  list<int>  $anchors  the date units
     * @return array<int, int> header unit => its date unit
     */
    private function headerOwners(array $units, array $anchors): array
    {
        $owner = [];
        if (! $anchors) {
            return $owner;
        }
        $above = 0;
        $below = 0;
        $gaps = [];       // [a, b, list of header units] between two dates with no duty in between

        // The header lines right before a date line, and right after it. A run stops once
        // the job has its title and its employer: the next line is a duty, or another job's.
        $take = function (array $u, array &$have, bool $up = false): bool {
            if ($u['role'] === 'skip') {
                return true;
            }
            $forced = array_column($u['pieces'], 'forced');
            if (in_array('place', $forced, true) || ($u['pieces'] && ! array_filter($u['pieces'], fn ($p) => $p['p'] < 6))) {
                return true;                                    // a place line ("Dubai, UAE", "Location: Cairo")
            }
            $isTitle = $this->titled($u);
            $isEmployer = ! $isTitle && (in_array('employer', $forced, true) || $u['e'] >= 3);
            if ($have['t'] && $have['e']) {
                // Above the dates, more title lines stacked on each other are this job's too
                // ("Finance Business Controller." / "Head of Finance Leasing…" / "Xerox – Egypt." / dates).
                return $up && $isTitle;
            }
            if ($isTitle) {
                $have['t'] = true;                              // a second title line is still taken: the job is then marked Check
            } elseif ($isEmployer) {
                if ($have['e']) {
                    return false;
                }
                $have['e'] = true;
            } elseif ($have['t']) {
                $have['e'] = true;                              // the line next to the title that is not a title: the employer
            } elseif ($have['e']) {
                $have['t'] = true;
            }

            return true;
        };
        $haveOf = function (array $keys) use ($units): array {
            $have = ['t' => false, 'e' => false];
            foreach ($keys as $k) {
                foreach ($units[$k]['pieces'] as $p) {
                    if ($p['forced'] === 'title' || ($p['t'] >= 3 && $p['t'] > $p['e'])) {
                        $have['t'] = true;
                    } elseif ($p['forced'] === 'employer' || ($p['e'] >= 3 && $p['e'] > $p['t'])) {
                        $have['e'] = true;
                    }
                }
            }

            return $have;
        };
        $up = function (int $a, int $stop) use ($units, $take, $haveOf) {
            $run = [];
            $have = $haveOf([$a]);
            for ($k = $a - 1; $k > $stop; $k--) {
                $u = $units[$k];
                if ($u['sec'] !== $units[$a]['sec'] || ! in_array($u['role'], ['header', 'skip'], true) || count($run) >= 4 || ! $take($u, $have, true)) {
                    break;
                }
                if ($u['role'] === 'header') {
                    array_unshift($run, $k);
                }
                if ($u['numbered']) {
                    break;              // "2) Chief Accountant" starts this job
                }
            }

            return $run;
        };
        $down = function (int $a, int $stop, array $mine) use ($units, $take, $haveOf) {
            $run = [];
            $have = $haveOf([$a, ...$mine]);
            if ($mine && ($have['t'] xor $have['e'])) {
                // Title / Company above the dates: the line next to the title counted as the employer already.
                $others = array_filter($mine, fn ($k) => ! $this->titled($units[$k]));
                if ($others) {
                    $have = ['t' => true, 'e' => true];
                }
            }
            for ($k = $a + 1; $k < $stop; $k++) {
                $u = $units[$k];
                if ($u['sec'] !== $units[$a]['sec'] || ! in_array($u['role'], ['header', 'skip'], true) || count($run) >= 4
                    || ($run && $u['numbered']) || ! $take($u, $have)) {
                    return [$run, false];
                }
                if ($u['role'] === 'header') {
                    $run[] = $k;
                }
            }

            return [$run, true];        // true: only header lines up to the next date line (or the end)
        };
        $count = count($units);
        foreach ($anchors as $n => $a) {
            $prev = $anchors[$n - 1] ?? -1;
            $next = $anchors[$n + 1] ?? $count;
            // Only header lines since the last date line: decided below, with the CV's usual side.
            $onlyHeaders = $prev >= 0;
            for ($k = $prev + 1; $k < $a && $onlyHeaders; $k++) {
                $onlyHeaders = in_array($units[$k]['role'], ['header', 'skip'], true) && $units[$k]['sec'] === $units[$a]['sec'];
            }
            if ($onlyHeaders) {
                $run = [];
                for ($k = $prev + 1; $k < $a; $k++) {
                    if ($units[$k]['role'] === 'header' && ! isset($owner[$k])) {
                        $run[] = $k;
                    }
                }
                if ($run) {
                    $gaps[] = [$prev, $a, $run];
                }
            } elseif ($t = $up($a, $prev)) {
                foreach ($t as $k) {
                    $owner[$k] = $a;
                }
                $above += 1 + ($this->anyTitled($units, $t) ? 1 : 0);
            }
            // After this date line.
            $mine = array_keys(array_filter($owner, fn ($o) => $o === $a));
            [$l, $reached] = $down($a, $next, $mine);
            if (! $l || ($reached && $next < $count)) {
                continue;               // only header lines up to the next date line: a gap, decided below
            }
            foreach ($l as $k) {
                $owner[$k] = $a;
            }
            $below += 1 + ($this->anyTitled($units, $l) ? 1 : 0);
        }

        // Lines between two dates with no duty in between: where this CV usually puts them.
        $side = $below > $above ? 'below' : 'above';
        foreach ($gaps as [$a, $b, $run]) {
            $numbered = null;
            foreach ($run as $pos => $k) {
                if ($units[$k]['numbered'] && $pos > 0) {
                    $numbered = $pos;
                    break;
                }
            }
            $titledAt = array_values(array_filter(array_keys($run), fn ($pos) => $this->titled($units[$run[$pos]])));
            $needA = ! $this->titled($units[$a]) && ! $this->anyTitled($units, array_keys(array_filter($owner, fn ($o) => $o === $a)));
            $needB = ! $this->titled($units[$b]) && ! $this->anyTitled($units, array_keys(array_filter($owner, fn ($o) => $o === $b)));
            if ($numbered !== null) {
                $split = $numbered;                           // "2) …" starts the next job
            } elseif (count($titledAt) >= 2) {
                $split = $titledAt[1];                        // two jobs' lines: the second title starts the next job
            } elseif ($needA && ! $needB) {
                $split = count($run);                         // the job above has no title yet
            } elseif ($needB && ! $needA) {
                $split = 0;
            } else {
                $split = $side === 'below' ? count($run) : 0;
            }
            foreach ($run as $pos => $k) {
                $owner[$k] = $pos < $split ? $a : $b;
            }
        }

        return $owner;
    }

    private function onlySkips(array $units, int $from, int $to): bool
    {
        for ($k = $from; $k < $to; $k++) {
            if ($units[$k]['role'] !== 'skip') {
                return false;
            }
        }

        return true;
    }

    private function anyTitled(array $units, array $keys): bool
    {
        foreach ($keys as $k) {
            if ($this->titled($units[$k])) {
                return true;
            }
        }

        return false;
    }

    /**
     * One job from its date line (null: a job without dates) and its header lines.
     *
     * @param  ?string  $order  'title_first' | 'employer_first': this CV's usual order, for lines the words do not settle
     */
    private function jobFrom(array $units, ?int $anchor, array $headers, ?string $order = null): ?array
    {
        $keys = $anchor === null ? $headers : array_merge([$anchor], $headers);
        sort($keys);
        $pieces = [];
        foreach ($keys as $k) {
            foreach ($units[$k]['pieces'] as $p) {
                $pieces[] = $p + ['unit' => $k];
            }
        }
        $check = [];
        $guess = false;
        $title = null;
        $employer = null;
        $places = [];
        $left = [];
        foreach ($pieces as $n => $p) {
            if ($p['forced'] === 'title' && $title === null) {
                $title = $n;
            } elseif ($p['forced'] === 'employer' && $employer === null) {
                $employer = $n;
            } elseif ($p['forced'] === 'place' || ($p['forced'] === null && $p['p'] >= 6)) {
                $places[] = $n;
            } elseif ($p['forced'] === null) {
                $left[] = $n;
            }
        }

        // ── The title ───────────────────────────────────────────────
        if ($title === null && $left) {
            $rank = $left;
            usort($rank, fn ($x, $y) => ($pieces[$y]['t'] - $pieces[$y]['e']) <=> ($pieces[$x]['t'] - $pieces[$x]['e']) ?: $x <=> $y);
            $best = $rank[0];
            if ($pieces[$best]['t'] > 0 && $pieces[$best]['t'] > $pieces[$best]['e']) {
                $title = $best;
                // Another part is clearly a title too: a person decides (the first one in the CV is offered).
                $rivals = array_values(array_filter($rank, fn ($other) => $other !== $best && $pieces[$other]['t'] >= 3
                    && $pieces[$other]['t'] > $pieces[$other]['e'] && $pieces[$best]['t'] - $pieces[$other]['t'] < 3));
                if ($rivals) {
                    $check[] = 'title';
                    $title = min([$best, ...$rivals]);
                }
            } else {
                // No title words: the part that is not the employer.
                $plain = array_values(array_filter($left, fn ($n) => $pieces[$n]['e'] <= 0));
                $companies = array_values(array_filter($left, fn ($n) => $pieces[$n]['e'] > 0));
                $choice = $plain ?: $left;
                if (count($plain) === 1 && $companies) {
                    $title = $plain[0];
                } elseif (! $plain) {
                    $check[] = 'title';          // only a company ("Vodafone Egypt (2015 – Present)"): the title is missing
                } else {
                    $title = $this->byOrder($pieces, $choice, $order, 'title') ?? $choice[0];
                    $lines = array_unique(array_map(fn ($n) => $pieces[$n]['unit'], $choice));
                    if (! ($order !== null && count($lines) > 1)) {
                        $guess = true;
                        $check[] = 'title';
                    }
                }
            }
        }

        // ── The employer ────────────────────────────────────────────
        $rest = array_values(array_filter($left, fn ($n) => $n !== $title));
        if ($employer === null && $rest) {
            usort($rest, fn ($x, $y) => ($pieces[$y]['e'] - $pieces[$y]['t']) <=> ($pieces[$x]['e'] - $pieces[$x]['t']) ?: $x <=> $y);
            $best = $rest[0];
            if ($pieces[$best]['e'] > 0 && $pieces[$best]['e'] >= $pieces[$best]['t']) {
                // Two company names on one line ("General Lighting Company (GLC) - Egypt / (Saudi Lighting Company - Philips)"): the first one.
                $same = array_filter($rest, fn ($n) => $pieces[$n]['unit'] === $pieces[$best]['unit'] && $pieces[$n]['e'] >= 3 && $pieces[$n]['e'] > $pieces[$n]['t']);
                $employer = $same ? min($same) : $best;
            } else {
                // No company words: the one other part that is not a title.
                $plain = array_values(array_filter($rest, fn ($n) => $pieces[$n]['t'] < 3));
                if ($plain) {
                    sort($plain);
                    $employer = $this->byOrder($pieces, $plain, $order, 'employer') ?? $plain[0];
                } elseif ($title !== null && ! in_array('title', $check, true)) {
                    $employer = $rest[0];       // two titles on two lines, one of them decided: the other is kept for the reviewer
                }
            }
        }

        if ($title === null && $employer === null) {
            return null;
        }

        // The rest of the employer's line belongs to it ("Mashreq Bank, Cairo Branch", "CIB – Commercial International Bank").
        $employerText = null;
        if ($employer !== null) {
            $e = $pieces[$employer];
            $from = $e['start'];
            $to = $e['end'];
            if ($e['forced'] === null) {
                $same = array_values(array_filter(array_keys($pieces), fn ($n) => $pieces[$n]['unit'] === $e['unit'] && $pieces[$n]['forced'] === null));
                $pos = array_search($employer, $same, true);
                for ($q = $pos + 1; $q < count($same); $q++) {
                    $n = $same[$q];
                    if ($n === $title || in_array($n, $places, true) || $pieces[$n]['t'] >= 3 || $pieces[$n]['marker'] !== null) {
                        break;
                    }
                    $to = $pieces[$n]['end'];
                }
                for ($q = $pos - 1; $q >= 0; $q--) {
                    $n = $same[$q];
                    if ($n === $title || in_array($n, $places, true) || $pieces[$n]['t'] >= 3 || $e['marker'] !== null) {
                        break;
                    }
                    $from = $pieces[$n]['start'];
                    $e['marker'] = $pieces[$n]['marker'];
                }
                $employerText = $this->strip(substr($e['src'], $from, $to - $from));
                // "Nile Foods Co." keeps its full stop.
                if (substr($e['src'], $to, 1) === '.' && preg_match('/(?:^|\s)(?:co|inc|ltd|corp|est)$/iu', $employerText)) {
                    $employerText .= '.';
                }
            } else {
                $employerText = $e['text'];
            }
        }

        // The places, as written ("Doha, Qatar").
        $location = null;
        if ($places) {
            $parts = [];
            foreach ($places as $n) {
                $p = $pieces[$n];
                $last = $parts ? count($parts) - 1 : null;
                if ($last !== null && $parts[$last]['unit'] === $p['unit'] && $p['forced'] === null && $parts[$last]['forced'] === null) {
                    $parts[$last]['text'] = $this->strip(substr($p['src'], $parts[$last]['start'], $p['end'] - $parts[$last]['start']));
                } else {
                    $parts[] = $p;
                }
            }
            $location = mb_substr(implode(', ', array_unique(array_column($parts, 'text'))), 0, 100);
        }

        $titleText = $title !== null ? $this->balanced($this->jobHeader($pieces[$title]['text'])) : null;
        if ($titleText === null || $titleText === '') {
            $check[] = 'title';
        }

        $date = $anchor !== null ? $units[$anchor]['date'] : null;
        if ($date && (! $date['exact'] || $date['single'])) {
            $check[] = 'dates';
        }
        $lines = array_map(fn ($k) => $units[$k]['i'], $keys);
        $ends = array_map(fn ($k) => $units[$k]['j'], $keys);

        return [
            'title'    => $titleText !== null && $titleText !== '' ? mb_substr($titleText, 0, 150) : null,
            'employer' => $employerText ? mb_substr($this->balanced($employerText), 0, 150) : null,
            'location' => $location,
            'from'     => $date['from'] ?? null,
            'to'       => $date ? ($date['current'] ? null : $date['to']) : null,
            'current'  => (bool) ($date['current'] ?? false),
            'check'    => $check,
            'guess'    => $guess,
            'order'    => $this->orderOf($pieces, $title, $employer),
            'anchor'   => $anchor,
            'units'    => $headers,
            'unknown_section' => $units[$keys[0]]['sec'] === 'unknown',
            'first'    => min($lines),
            'last'     => max($ends),
        ];
    }

    /**
     * Jobs that are really one job, or one company with its jobs:
     *  · "Vodafone Egypt (2015 – Present)" then "Team Leader (2018 – Present)" and
     *    "Agent (2015 – 2018)": the company line gives its name to the jobs under it;
     *  · "Lazurde Group (June 2014 – Jan 2019)", a line about the company, then
     *    "Finance Director": the title under the company takes its dates;
     *  · the same period twice (a summary list and the detailed history): one job,
     *    with what each of them has;
     *  · "As Product Officer," (a job described again further down): its duties go
     *    to the dated job with the same title.
     *
     * @param  list<array>  $jobs  in the order of the CV
     * @return list<array>
     */
    private function mergeJobs(array $jobs, array $units): array
    {
        $sec = fn (array $j) => $units[$j['anchor'] ?? $j['units'][0]]['sec'] ?? '';
        // 1. A company line with dates and no title: the jobs under it.
        for ($n = 0; $n < count($jobs); $n++) {
            $job = $jobs[$n];
            if ($job === null || $job['title'] !== null || $job['employer'] === null || $job['anchor'] === null) {
                continue;
            }
            $children = [];
            for ($q = $n + 1; $q < count($jobs); $q++) {
                $child = $jobs[$q];
                if ($child === null) {
                    continue;
                }
                $sameEmployer = $child['employer'] === null || TextNormalizer::normalize($child['employer']) === TextNormalizer::normalize($job['employer']);
                $inside = $child['from'] === null || $job['from'] === null || ($child['from'] >= $job['from'] && ($job['current'] || $job['to'] === null || $child['from'] <= $job['to']));
                if ($child['title'] === null || ! $sameEmployer || ! $inside || $sec($child) !== $sec($job)) {
                    break;
                }
                $children[] = $q;
            }
            if (! $children) {
                continue;
            }
            $dated = array_filter($children, fn ($q) => $jobs[$q]['from'] !== null);
            foreach ($children as $pos => $q) {
                $jobs[$q]['employer'] = $job['employer'];
                $jobs[$q]['location'] ??= $job['location'];
                if (! $dated && $pos === 0) {
                    // The only title under the company: the company's period is its period.
                    $jobs[$q]['from'] = $job['from'];
                    $jobs[$q]['to'] = $job['to'];
                    $jobs[$q]['current'] = $job['current'];
                    $jobs[$q]['check'] = array_values(array_diff($jobs[$q]['check'], ['dates']));
                    if (in_array('dates', $job['check'], true)) {
                        $jobs[$q]['check'][] = 'dates';
                    }
                }
            }
            $jobs[$n] = null;
        }
        $jobs = array_values(array_filter($jobs));

        // 2. The same period twice: one job.
        for ($n = 0; $n < count($jobs); $n++) {
            for ($q = $n + 1; $q < count($jobs); $q++) {
                $a = $jobs[$n];
                $b = $jobs[$q];
                if ($a === null || $b === null || $a['from'] === null || $a['from'] !== $b['from'] || $a['to'] !== $b['to'] || $a['current'] !== $b['current']
                    || ! $this->alike($a['title'], $b['title']) || ! $this->alike($a['employer'], $b['employer'])) {
                    continue;       // two different jobs held at the same time stay two jobs
                }
                $jobs[$n] = $this->combine($a, $b);
                $jobs[$q] = null;
            }
        }
        $jobs = array_values(array_filter($jobs));

        // 3. A job described again further down, without dates ("As Product Officer,").
        foreach ($jobs as $n => $job) {
            if ($job === null || $job['from'] !== null || $job['title'] === null) {
                continue;
            }
            $t = TextNormalizer::normalize($job['title']);
            foreach ($jobs as $q => $other) {
                if ($q === $n || $other === null || $other['from'] === null || $other['title'] === null) {
                    continue;
                }
                $o = TextNormalizer::normalize($other['title']);
                if ($t === $o || (mb_strlen($o) >= 6 && str_contains(' '.$t.' ', ' '.$o.' ')) || (mb_strlen($t) >= 6 && str_contains(' '.$o.' ', ' '.$t.' '))) {
                    $jobs[$q] = $this->combine($other, $job);
                    $jobs[$n] = null;
                    break;
                }
            }
        }

        return array_values(array_filter($jobs));
    }

    /** Empty, the same, or one inside the other ("CFO" / "Chief Financial Officer – CFO", "GSK" / "GlaxoSmithKline, - (GSK)"). */
    private function alike(?string $a, ?string $b): bool
    {
        if ($a === null || $b === null) {
            return true;
        }
        $x = TextNormalizer::normalize($a);
        $y = TextNormalizer::normalize($b);

        return $x === $y || str_contains(' '.$x.' ', ' '.$y.' ') || str_contains(' '.$y.' ', ' '.$x.' ');
    }

    /** Two readings of one job: each field from the one that has it; the longer list of duties. */
    private function combine(array $a, array $b): array
    {
        $out = $a;
        foreach (['title', 'employer', 'location', 'from', 'to'] as $f) {
            $out[$f] = $a[$f] ?? $b[$f];
        }
        // The detailed history usually has the full title: "Chief Financial Officer – CFO" over "CFO".
        if ($a['title'] !== null && $b['title'] !== null && in_array('title', $a['check'], true) && ! in_array('title', $b['check'], true)) {
            $out['title'] = $b['title'];
        }
        $out['current'] = $a['current'] || $b['current'];
        $out['responsibilities'] = count($b['responsibilities'] ?? []) > count($a['responsibilities'] ?? []) ? $b['responsibilities'] : ($a['responsibilities'] ?? []);
        $out['check'] = array_values(array_unique(array_filter([...$a['check'], ...$b['check']],
            fn ($c) => ! ($c === 'title' && $out['title'] !== null && (! in_array('title', $a['check'], true) || ! in_array('title', $b['check'], true)))
                && ! ($c === 'dates' && $out['from'] !== null && (! in_array('dates', $a['check'], true) || ! in_array('dates', $b['check'], true))))));
        $out['unknown_section'] = $a['unknown_section'] && $b['unknown_section'];

        return $out;
    }

    /** The part at this CV's usual place ('title_first': the title line comes before the employer line). */
    private function byOrder(array $pieces, array $choice, ?string $order, string $want): ?int
    {
        if ($order === null || count($choice) < 1) {
            return null;
        }
        $byLine = $choice;
        usort($byLine, fn ($x, $y) => [$pieces[$x]['unit'], $x] <=> [$pieces[$y]['unit'], $y]);
        $first = ($order === 'title_first') === ($want === 'title');

        return $first ? $byLine[0] : $byLine[count($byLine) - 1];
    }

    private function orderOf(array $pieces, ?int $title, ?int $employer): ?string
    {
        if ($title === null || $employer === null || $pieces[$title]['unit'] === $pieces[$employer]['unit']) {
            return null;
        }

        return $pieces[$title]['unit'] < $pieces[$employer]['unit'] ? 'title_first' : 'employer_first';
    }

    /** How this CV usually orders the title line and the employer line, from its clear jobs. */
    private function usualOrder(array $jobs): ?string
    {
        $votes = ['title_first' => 0, 'employer_first' => 0];
        foreach ($jobs as $job) {
            if (! $job['guess'] && ! in_array('title', $job['check'], true) && $job['order'] !== null) {
                $votes[$job['order']]++;
            }
        }
        if ($votes['title_first'] === $votes['employer_first']) {
            return null;
        }

        return $votes['title_first'] > $votes['employer_first'] ? 'title_first' : 'employer_first';
    }

    /** The job this undated title line sits under: the job above it, with duties in between. */
    private function enclosingJob(array $units, array $jobs, int $k): ?array
    {
        $best = null;
        foreach ($jobs as $at => $job) {
            if ($at < $k && ($best === null || $at > $best)) {
                $best = $at;
            }
        }
        if ($best === null || $units[$best]['sec'] !== $units[$k]['sec']) {
            return null;
        }
        for ($q = $best + 1; $q < $k; $q++) {
            if (in_array($units[$q]['role'], ['duty', 'jobhead'], true)) {
                return $jobs[$best];
            }
        }

        return null;
    }

    /**
     * What the person did in one job: the lines between the job's title and
     * the next job. When the job has a sub-heading ("Key Responsibilities:",
     * "المهام الوظيفية"), only the lines under it are taken — a line about
     * the company above it is not a duty.
     *
     * @return list<string>
     */
    private function responsibilities(array $lines, array $parsed, int $from, int $to, array $used, bool $wrapped, array $names): array
    {
        $block = [];
        $heads = [];
        for ($i = $from; $i < $to; $i++) {
            if (! in_array($parsed['section'][$i] ?? '', self::JOB_SECTIONS, true)) {
                break;          // the next section started
            }
            if (isset($parsed['job_heads'][$i])) {
                $heads[] = count($block);
                if (isset($parsed['inline'][$i])) {
                    $block[] = [$i, $parsed['inline'][$i]];      // "Job description: Handling all …"
                }
                continue;
            }
            if (isset($parsed['known'][$i]) || isset($used[$i])) {
                continue;
            }
            $block[] = [$i, $lines[$i]];
        }
        if ($heads) {
            $block = array_slice($block, $heads[0]);
        }

        $items = [];
        $nameNorms = array_filter(array_map(fn ($n) => TextNormalizer::normalize($n), $names));
        foreach ($block as [, $raw]) {
            $t = trim(str_replace(self::BOLD, '', $raw));
            if ($t === '' || preg_match('/^[^\p{L}]*$/u', $t)) {
                continue;      // empty, bullets only, page numbers
            }
            $norm = TextNormalizer::normalize($t);
            if (preg_match('/^(?:page\s*\d|\d+\s*(?:of|\/)\s*\d+)|@|https?:|www\./iu', $t) || $this->ranges($t)
                || ($nameNorms && array_filter($nameNorms, fn ($n) => str_contains($norm, $n)))) {
                continue;      // footers, links, another job's dates
            }
            if (preg_match('/^([^:]{2,30}):/u', $t, $m) && $this->lineLabel($m[1]) !== null) {
                continue;      // "Web site: …", "Location: …"
            }
            $bullet = $this->isBulleted($t) || preg_match(self::NUMBERED, $t);
            $item = trim(preg_replace(self::NUMBERED, '', preg_replace(self::BULLET, '', $t) ?? $t) ?? $t);
            $item = trim(preg_replace(self::BULLET, '', $item) ?? $item);
            if ($item === '') {
                continue;
            }
            $prev = $items ? $items[count($items) - 1] : null;
            $startsLow = preg_match('/^[a-z(]/u', $item) === 1;
            $dangling = $prev !== null && preg_match('/(?:[,&\-–]|\b(?:and|or|of|the|to|for|in|with|by|on|at|a|an|as|from|including)|[،و])$/iu', $prev);
            // A PDF breaks long lines: a line starting in lower case, or after a dangling
            // "and", ",", "of" …, continues the item above. In Word each line is a paragraph.
            $continues = $prev !== null && ! $bullet && ($wrapped ? ($startsLow || $dangling) : ($startsLow && ! preg_match('/[.;:!?؛]$/u', $prev)));
            if ($continues) {
                $items[count($items) - 1] = mb_substr($prev.' '.$item, 0, 500);
            } else {
                $items[] = mb_substr($item, 0, 500);
            }
        }
        $items = array_values(array_filter(array_map(fn ($x) => $this->strip($x), $items), fn ($x) => mb_strlen($x) >= 3));

        return array_slice($items, 0, (int) config('beneficiaries.max.responsibilities', 30));
    }

    private function previousLine(array $lines, int $i): ?int
    {
        for ($j = $i - 1; $j >= 0; $j--) {
            if (trim($lines[$j]) !== '') {
                return $j;
            }
        }

        return null;
    }

    private function nextLine(array $lines, int $i): ?int
    {
        for ($j = $i + 1; $j < count($lines); $j++) {
            if (trim($lines[$j]) !== '') {
                return $j;
            }
        }

        return null;
    }

    /** "Banque Du Caire (Bank Al Qahira" → "Banque Du Caire (Bank Al Qahira)" */
    private function balanced(string $t): string
    {
        $t = trim($t);
        $open = substr_count($t, '(') - substr_count($t, ')');
        if ($open > 0) {
            $t .= str_repeat(')', $open);
        } elseif ($open < 0) {
            $t = preg_replace('/^([^(]*?)\)/u', '$1', $t, -$open) ?? $t;     // "W.V.B) World Vest" → "W.V.B World Vest"
        }

        return trim($t);
    }

    /** "1- Worked as Accountant at X:" → "Accountant at X" */
    private function jobHeader(string $t): string
    {
        $t = trim(str_replace(self::BOLD, '', $t));
        $t = preg_replace(self::NUMBERED, '', preg_replace(self::BULLET, '', $t) ?? $t) ?? $t;
        $t = rtrim(trim($t), ': ');
        $norm = mb_strtolower($t);
        foreach (CvDictionary::TITLE_PREFIXES as $p) {
            if (str_starts_with($norm, $p.' ')) {
                $t = mb_substr($t, mb_strlen($p) + 1);
                break;
            }
        }

        return $this->strip($t);
    }

    private function withoutDates(string $line, string $match): string
    {
        $rest = str_ireplace($match, ' ', $this->plain($line));
        $rest = preg_replace('/\(\s*\)|\[\s*\]/u', ' ', $rest) ?? $rest;
        $rest = preg_replace('/\s+(?:from|since|من|منذ)\s*$/u', '', $rest) ?? $rest;

        return $this->strip($rest);
    }

    // ══ 8 · Education ═══════════════════════════════════════════════

    /** @return array{0: list<array>, 1: ?string, 2: string, 3: string} */
    private function education(array $lines, array $parsed): array
    {
        $entries = [];
        $levels = [];
        $current = null;
        $inSection = false;
        $max = (int) config('beneficiaries.max.education', 10);

        foreach ($lines as $i => $raw) {
            if (($parsed['section'][$i] ?? '') !== 'education' || isset($parsed['known'][$i]) && ! isset($parsed['inline'][$i])) {
                if (($parsed['section'][$i] ?? '') === 'education') {
                    $inSection = true;
                }
                continue;
            }
            $inSection = true;
            $line = trim(preg_replace(self::BULLET, '', trim($parsed['inline'][$i] ?? $raw)) ?? '');
            if ($line === '') {
                continue;
            }
            $norm = TextNormalizer::normalize($line);
            $level = $this->levelIn($norm);
            $isPlace = $this->hasWord($norm, CvDictionary::INSTITUTION_WORDS);
            $year = $this->latestYear($line);

            if ($level) {
                $levels[] = $level;
            }
            if ($level || ($isPlace && ! $current)) {
                if ($current) {
                    $entries[] = $current;
                }
                $current = $this->educationEntry($line);
            } elseif ($current) {
                // "Major: Accounting", "التخصص: محاسبة"
                if (! $current['field'] && preg_match('/^(?:majors?|major in|specialization|specialisation|specialty|speciality|concentration|field of study|field|department|division|التخصص|الشعبة|القسم|شعبة|تخصص)\s*[:\-]\s*(.{2,})$/iu', $line, $fm)) {
                    $current['field'] = $this->cut($this->strip($fm[1]));
                }
                if ($isPlace && ! $current['institution']) {
                    $current['institution'] = $this->cut($this->withoutYears($line));
                }
                if ($year && ! $current['year']) {
                    $current['year'] = $year;
                }
            }
            if (count($entries) >= $max) {
                break;
            }
        }
        if ($current && count($entries) < $max) {
            $entries[] = $current;
        }
        $entries = array_values(array_filter($entries, fn ($e) => $e['qualification']));

        $order = array_keys(CvDictionary::EDUCATION_LEVELS);   // highest first
        $best = null;
        foreach ($order as $level) {
            if (in_array($level, $levels, true)) {
                $best = $level;
                break;
            }
        }

        return [
            $entries,
            $best,
            $entries ? 'found' : ($inSection ? 'check' : 'missing'),
            $best ? 'found' : ($entries ? 'check' : 'missing'),
        ];
    }

    private function educationEntry(string $line): array
    {
        $year = $this->latestYear($line);
        $parts = array_values(array_filter(array_map(
            fn ($p) => $this->strip($this->withoutYears($p)),
            preg_split('/\s+[|—–\-]\s*|\s*[|—–\-]\s+|\s*[,،]\s+/u', $line),
        )));
        $qualification = null;
        $field = null;
        $institution = null;
        foreach ($parts as $p) {
            $n = TextNormalizer::normalize($p);
            if (! $institution && $this->hasWord($n, CvDictionary::INSTITUTION_WORDS) && ! $this->levelIn($n)) {
                $institution = $p;
            } elseif (! $qualification) {
                $qualification = $p;
            } elseif (! $field) {
                $field = $p;
            }
        }
        // A degree line that is only a place ("Cairo University, Faculty of Commerce").
        if (! $qualification && $institution) {
            [$qualification, $institution] = [$institution, null];
        }
        // "Bachelor of Science in Computer Science", "بكالوريوس تجارة شعبة محاسبة"
        if ($qualification && ! $field && preg_match('/\s(?:in|major(?:ing)? in|تخصص|شعبة|قسم)\s+(.{3,})$/u', $qualification, $m)) {
            $field = trim($m[1]);
        }

        return [
            'qualification' => $this->cut($qualification),
            'field'         => $this->cut($field),
            'institution'   => $this->cut($institution),
            'year'          => $year,
        ];
    }

    private function levelIn(string $norm): ?string
    {
        foreach (CvDictionary::EDUCATION_LEVELS as $level => $words) {
            if ($this->hasWord($norm, $words)) {
                return $level;
            }
        }

        return null;
    }

    private function latestYear(string $line): ?int
    {
        $v = strtr($line, array_combine(['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'], range(0, 9)));
        if (! preg_match_all('/(?<!\d)('.self::YEAR.')(?!\d)/', $v, $m)) {
            return null;
        }
        $year = max(array_map('intval', $m[1]));

        return $year <= (int) date('Y') + 6 ? $year : null;
    }

    private function withoutYears(string $text): string
    {
        $text = strtr($text, array_combine(['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'], range(0, 9)));
        $text = preg_replace('/\(?\s*(?:class of\s*)?'.self::YEAR.'(?:\s*[-–]\s*(?:'.self::YEAR.'|'.$this->presentRx.'))?\s*\)?/iu', ' ', $text) ?? $text;

        return $this->strip(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    // ══ 9 · Skills ══════════════════════════════════════════════════

    /** @return array{0: list<string>, 1: list<string>, 2: string} skills, the ones ESCO knows, mark */
    private function skills(array $lines, array $parsed): array
    {
        $items = [];
        foreach ($lines as $i => $raw) {
            if (! in_array($parsed['section'][$i] ?? '', ['skills', 'skills_languages'], true)) {
                continue;
            }
            $text = isset($parsed['known'][$i]) ? ($parsed['inline'][$i] ?? '') : trim($raw);
            // "Gender: Male" under the skills heading is a detail, not a skill.
            if (preg_match('/^([^:]{1,40}):/u', $text, $lm) && isset($this->labels[TextNormalizer::normalize($lm[1])])) {
                continue;
            }
            $pieces = preg_split('/\s*[,،;؛•·|\/]\s*|\s+-\s+|\t/u', $text);
            // A piece that is only a version or a number belongs to the piece before it:
            // "ERP Sun System MFF - Version 4 & 5", "Office 2016 / 2019".
            $joined = [];
            foreach ($pieces as $piece) {
                if ($joined && preg_match('/^\s*(?:(?:version|ver\.?|v\.?|release|edition|الإصدار|اصدار|إصدار)\s*[\d.]+.*|[\d.\s&+x-]+)$/iu', $piece)) {
                    $joined[count($joined) - 1] .= ' – '.trim($piece);
                } else {
                    $joined[] = $piece;
                }
            }
            foreach ($joined as $item) {
                $item = trim(preg_replace(self::BULLET, '', trim($item)) ?? '', " \t.:-");
                // "Excel: advanced" → "Excel"
                $item = trim(preg_replace('/\s*[:(].*$/u', '', $item) ?? $item);
                // The version joined above ("– Version 4 & 5") does not count in the 6-word limit.
                $core = trim(preg_replace('/\s–\s.*$/u', '', $item) ?? $item);
                $words = $core === '' ? 0 : count(preg_split('/\s+/u', $core));
                if ($words === 0 || $words > 6 || mb_strlen($item) > 80 || $this->languageCode(TextNormalizer::normalize($core))) {
                    continue;
                }
                $items[mb_strtolower($item)] ??= $item;
            }
        }
        $items = array_slice(array_values($items), 0, (int) config('beneficiaries.max.skills', 40));
        $known = $this->knownSkills($items);

        return [$items, $known, $items ? 'found' : 'missing'];
    }

    /** @return list<string> the items that are ESCO skill names (any language, any spelling). */
    private function knownSkills(array $items): array
    {
        if (! $items) {
            return [];
        }
        $byNorm = [];
        foreach ($items as $item) {
            foreach ($this->variants($item) as $v) {
                $byNorm[$v][] = $item;
            }
        }
        try {
            $hits = DB::table('skill_labels')->whereIn('normalized', array_keys($byNorm))->distinct()->pluck('normalized');
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        foreach ($hits as $n) {
            foreach ($byNorm[$n] ?? [] as $item) {
                $out[$item] = true;
            }
        }

        return array_keys($out);
    }

    // ══ 10 · Languages ══════════════════════════════════════════════

    /** @return array{0: list<array{code: string, level: ?string}>, 1: string} */
    private function languages(array $lines, array $parsed, array $labelled): array
    {
        $segments = $labelled;
        foreach ($lines as $i => $raw) {
            if (in_array($parsed['section'][$i] ?? '', ['languages', 'skills_languages'], true) && ! isset($parsed['known'][$i])) {
                $segments[] = trim($raw);
            }
        }
        $found = [];
        foreach ($segments as $segment) {
            $segment = preg_replace('/\s*\|\s*(?=[:\-])/u', ' ', $segment) ?? $segment;   // "Arabic | : Native" (a Word tab)
            // "Arabic (native), English - very good" → one piece per language
            foreach (preg_split('/\s*[,،;؛|•]\s*|\s+و\s+|\s+and\s+/u', $segment) as $piece) {
                $norm = TextNormalizer::normalize($piece);
                $code = $this->languageCode($norm);
                if (! $code || isset($found[$code])) {
                    continue;
                }
                $found[$code] = ['code' => $code, 'level' => $this->levelOf($norm)];
            }
        }
        $found = array_slice(array_values($found), 0, (int) config('beneficiaries.max.languages', 10));
        $sure = $found && ! in_array(null, array_column($found, 'level'), true);

        return [$found, $found ? ($sure ? 'found' : 'check') : 'missing'];
    }

    private function languageCode(string $norm): ?string
    {
        foreach (CvDictionary::LANGUAGES as $code => $words) {
            if ($this->hasWord($norm, $words)) {
                return $code;
            }
        }

        return null;
    }

    private function levelOf(string $norm): ?string
    {
        foreach (CvDictionary::LANGUAGE_LEVELS as $level => $words) {
            if ($this->hasWord($norm, $words)) {
                return $level;
            }
        }

        return null;
    }

    // ══ Helpers ═════════════════════════════════════════════════════

    /** The normalised forms of a dictionary word (with the broken-"لا" spelling). */
    private function variants(string $word): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn ($v) => TextNormalizer::normalize($v),
            ArabicRepair::variants($word),
        ))));
    }

    /** Does the normalised text contain one of these words or phrases, as whole words? */
    private function hasWord(string $norm, array $words): bool
    {
        $padded = ' '.$norm.' ';
        foreach ($words as $w) {
            foreach ($this->variants($w) as $v) {
                if ($v !== '' && str_contains($padded, ' '.$v.' ')) {
                    return true;
                }
            }
        }

        return false;
    }

    /** The first list (in dictionary order) with a word in the value. */
    private function pick(string $value, array $lists): ?string
    {
        $norm = TextNormalizer::normalize($value);
        foreach ($lists as $key => $words) {
            if ($this->hasWord($norm, $words)) {
                return $key;
            }
        }

        return null;
    }

    /** Like pick(), but the whole value must be the word ("Male", not "Female" containing "male"). */
    private function exact(string $value, array $lists): ?string
    {
        $norm = TextNormalizer::normalize(explode(' ', trim($value))[0] ?? '');
        foreach ($lists as $key => $words) {
            foreach ($words as $w) {
                if (in_array($norm, $this->variants($w), true)) {
                    return $key;
                }
            }
        }

        return null;
    }

    private function isContactLine(string $line): bool
    {
        return (bool) preg_match('/@|https?:|www\.|linkedin|(?:\+|00)\s*20|[\d٠-٩]{3}[\s\-.]?[\d٠-٩]{3}/iu', $line);
    }

    private function isArabic(string $text): bool
    {
        return preg_match_all('/\p{Arabic}/u', $text) > preg_match_all('/[A-Za-z]/', $text);
    }

    private function languageOf(string $text): string
    {
        $ar = preg_match_all('/\p{Arabic}/u', $text);
        $en = preg_match_all('/[A-Za-z]/', $text);
        $total = max(1, $ar + $en);

        return $ar / $total >= 0.7 ? 'ar' : ($ar / $total <= 0.3 ? 'en' : 'mixed');
    }

    /** "Senior Accountant (Remote)" → "Senior Accountant" */
    private function cleanTitle(string $t): string
    {
        $t = preg_replace('/\([^)]*\)|\[[^]]*\]/u', ' ', $t) ?? $t;
        $t = $this->strip(preg_replace('/\s+/u', ' ', $t) ?? $t);

        return mb_strlen($t) >= 2 && mb_strlen($t) <= 100 ? $t : '';
    }

    private function titleCase(string $v): string
    {
        return $this->isArabic($v) || mb_strtolower($v) !== $v ? $v : mb_convert_case($v, MB_CASE_TITLE);
    }

    /** trim() for Arabic text: PHP's own trim() works on bytes and would cut Arabic letters in half. */
    private function strip(string $v): string
    {
        return preg_replace('/^[\s|,،;؛:.()\[\]\-–—\/]+|[\s|,،;؛:.()\[\]\-–—\/]+$/u', '', $v) ?? $v;
    }

    private function cut(?string $v): ?string
    {
        $v = $v === null ? null : trim($v);

        return $v === null || $v === '' ? null : mb_substr($v, 0, 150);
    }
}
