<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\Backbone\EscoOccupation;
use App\Models\Backbone\IscoGroup;
use App\Models\Beneficiary;
use App\Models\Company;
use App\Models\CvBatch;
use App\Models\CvDocument;
use App\Models\Employer;
use App\Models\EligibilityAssessment;
use App\Models\Opportunity;
use App\Models\OpportunityMatch;
use App\Models\Sector;
use App\Models\User;
use App\Services\Beneficiaries\BeneficiaryRecorder;
use App\Services\Cv\CvStorage;
use App\Services\Eligibility\Assessor;
use App\Services\Matches\Matcher;
use App\Services\Opportunities\OpportunityBook;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

// ══════════════════════════════════════════════════════════════════
//  Massar — massar:seed-demo   (DEMO / TESTING DATA ONLY)
//  Location: app/Console/Commands/SeedDemoData.php
//
//      php artisan massar:seed-demo
//      php artisan massar:seed-demo --count=500
//      php artisan massar:seed-demo --fresh          (wipe previous demo data first)
//
//  Fills the demo partner workspace ("Al-Amal Foundation") with a full,
//  realistic dataset for every feature: beneficiaries, CV upload/Review
//  Queue/CV Bank, Jobs & Training postings, Eligibility, and Matches —
//  spread across occupations, gender, age, governorates and industries,
//  roughly half "white collar" and half "blue collar".
//
//  It goes through the SAME services the app itself uses to write this
//  data (BeneficiaryRecorder, Assessor, Matcher, OpportunityBook), so
//  everything it creates — occupation codes, eligibility scores, match
//  timelines, seat counts — is exactly as consistent as data a real
//  case worker would produce by hand, just much faster.
//
//  Safe to re-run. Requires the occupation backbone to already be
//  imported (`php artisan backbone:import`, or `migrate --seed`).
// ══════════════════════════════════════════════════════════════════

class SeedDemoData extends Command
{
    protected $signature = 'massar:seed-demo {--count=500 : How many beneficiaries to create} {--fresh : Delete this workspace’s previous demo data first}';

    protected $description = 'Fill the demo partner workspace with realistic beneficiaries, CVs, jobs, training and matches';

    private BeneficiaryRecorder $recorder;

    private OpportunityBook $book;

    private Assessor $assessor;

    private Matcher $matcher;

    private CvStorage $storage;

    private Company $company;

    /** @var array<int, User> */
    private array $team = [];

    private array $whiteUnits = [];

    private array $blueUnits = [];

    private array $employers = [];

    private array $subSectors = [];

    private int $failures = 0;

    public function handle(
        BeneficiaryRecorder $recorder,
        OpportunityBook $book,
        Assessor $assessor,
        Matcher $matcher,
        CvStorage $storage,
    ): int {
        $this->recorder = $recorder;
        $this->book = $book;
        $this->assessor = $assessor;
        $this->matcher = $matcher;
        $this->storage = $storage;

        $count = max(10, (int) $this->option('count'));

        if (IscoGroup::query()->count() === 0 || EscoOccupation::query()->count() === 0) {
            $this->error('The occupation backbone is empty. Run `php artisan backbone:import` first, then try again.');

            return self::FAILURE;
        }

        $this->company = $this->company();
        $this->team = $this->team($this->company);

        if ($this->option('fresh')) {
            $this->wipe($this->company);
        }

        $this->ensureSectors();
        $this->loadOccupationPools();
        $this->loadEmployers();

        $this->info("Workspace: {$this->company->name} (#{$this->company->id})");

        $this->info('Creating jobs and training programs…');
        $opportunities = $this->makeOpportunities();
        $this->line('  '.count($opportunities).' postings created.');

        $this->info("Creating {$count} beneficiaries (this reads and writes each one, so it takes a few minutes)…");
        $beneficiaries = $this->makeBeneficiaries($count);
        $this->line('  '.count($beneficiaries).' beneficiaries created.');

        $this->info('Creating CV uploads, the Review Queue and the CV Bank…');
        $this->makeCvDocuments($beneficiaries);

        $this->info('Checking eligibility against every job and training…');
        $this->assessEligibility($opportunities, $beneficiaries);

        $this->info('Referring eligible people and moving them through the pipeline…');
        $this->makeMatches($opportunities);

        if ($this->failures > 0) {
            $this->warn("{$this->failures} records were skipped after an error (see above). Everything else was created normally.");
        }

        $this->info('Done. Sign in as admin@alamal.test or caseworker@alamal.test to see it.');

        return self::SUCCESS;
    }

    // ── Setup ────────────────────────────────────────────────────────

    private function company(): Company
    {
        $company = Company::query()->firstOrCreate(
            ['name' => 'Al-Amal Foundation'],
            [
                'name_ar' => 'مؤسسة الأمل',
                'type' => 'ngo',
                'governorate' => 'cai',
                'contact_email' => 'info@alamal.test',
                'seat_limit' => 10,
                'subscription_ends_at' => now()->addYear(),
            ]
        );

        return $company;
    }

    /** @return array<int, User> [admin, caseworker1, caseworker2] */
    private function team(Company $company): array
    {
        $people = [
            ['admin@alamal.test', 'Rana Hassan', UserRole::CompanyAdmin, 'Programme Manager'],
            ['caseworker@alamal.test', 'Karim Adel', UserRole::Employee, 'Case Worker'],
            ['caseworker2@alamal.test', 'Mariam Fathy', UserRole::Employee, 'Case Worker'],
        ];

        $users = [];
        foreach ($people as [$email, $name, $role, $title]) {
            $user = User::query()->firstOrNew(['email' => $email]);
            $isNew = ! $user->exists;
            $user->fill([
                'name' => $name,
                'role' => $role->value,
                'job_title' => $title,
                'company_id' => $company->id,
                'language' => 'en',
                'is_active' => true,
            ]);
            $user->email_verified_at ??= now();
            if ($isNew) {
                $user->password = config('app.default_password') ?: bcrypt(Str::random(20));
            }
            $user->save();
            $users[] = $user->fresh();
        }

        return $users;
    }

    private function byUser(): User
    {
        return $this->team[array_rand($this->team, 1)] ?? $this->team[0];
    }

    private function caseworker(): User
    {
        // Prefer one of the two case workers over the admin for day-to-day records.
        $pool = array_slice($this->team, 1) ?: $this->team;

        return $pool[array_rand($pool)];
    }

    private function ensureSectors(): void
    {
        if (Sector::query()->count() > 0) {
            return;
        }
        try {
            Artisan::call('employers:import');
        } catch (Throwable $e) {
            $this->warn('Could not import database/data/employers/employers.xlsx automatically ('.$e->getMessage().'). Jobs will use plain employer names instead.');
        }
    }

    private function loadOccupationPools(): void
    {
        // White collar: managers, professionals, technicians, clerical support (ISCO-08 majors 1-4).
        // Kept to a modest number of units so several beneficiaries share each one — otherwise a
        // job asking for one exact occupation would rarely find anybody to be eligible.
        $this->whiteUnits = IscoGroup::query()->units()->whereIn('major_code', ['1', '2', '3', '4'])
            ->inRandomOrder()->limit(24)->get()->all();

        // Blue collar: services/sales, skilled agricultural, craft trades, plant/machine operators, elementary (majors 5-9).
        $this->blueUnits = IscoGroup::query()->units()->whereIn('major_code', ['5', '6', '7', '8', '9'])
            ->inRandomOrder()->limit(24)->get()->all();

        if ($this->whiteUnits === [] || $this->blueUnits === []) {
            $this->whiteUnits = $this->whiteUnits ?: IscoGroup::query()->units()->inRandomOrder()->limit(30)->get()->all();
            $this->blueUnits = $this->blueUnits ?: IscoGroup::query()->units()->inRandomOrder()->limit(30)->get()->all();
        }
    }

    private function loadEmployers(): void
    {
        $this->employers = Employer::query()->whereNull('company_id')->inRandomOrder()->limit(200)->get()->all();
        $this->subSectors = Sector::query()->whereNotNull('parent')->pluck('code')->all();
    }

    private function wipe(Company $company): void
    {
        $this->warn('Removing this workspace’s previous demo data…');
        DB::table('opportunity_match_events')->where('company_id', $company->id)->delete();
        DB::table('opportunity_matches')->where('company_id', $company->id)->delete();
        DB::table('eligibility_runs')->where('company_id', $company->id)->delete();
        DB::table('eligibility_assessments')->where('company_id', $company->id)->delete();
        DB::table('opportunities')->where('company_id', $company->id)->delete();
        DB::table('cv_access_logs')->where('company_id', $company->id)->delete();
        DB::table('cv_documents')->where('company_id', $company->id)->delete();
        DB::table('cv_batches')->where('company_id', $company->id)->delete();
        DB::table('cv_bank_index')->where('company_id', $company->id)->delete();
        DB::table('beneficiary_changes')->where('company_id', $company->id)->delete();
        DB::table('beneficiaries')->where('company_id', $company->id)->delete();
        $this->storage->deleteWorkspace($company->id);
    }

    // ── Reference pools (names, skills, employers…) ─────────────────

    /** @return array{0: list<string>, 1: list<string>} [arabic, english] first names for one gender */
    private static function firstNames(string $gender): array
    {
        return $gender === 'female'
            ? [
                ['فاطمة', 'مريم', 'نور', 'سارة', 'ياسمين', 'هبة', 'منى', 'رنا', 'دينا', 'إيمان', 'سلمى', 'آية', 'ندى', 'مي', 'رحمة', 'شيماء', 'هدى', 'أمل', 'ريم', 'لمياء', 'دعاء', 'مروة', 'نهى', 'جيهان', 'مارى', 'كرستين', 'ملك', 'جنى', 'روان', 'أسماء'],
                ['Fatma', 'Mariam', 'Nour', 'Sara', 'Yasmin', 'Heba', 'Mona', 'Rana', 'Dina', 'Eman', 'Salma', 'Aya', 'Nada', 'Mai', 'Rahma', 'Shaimaa', 'Hoda', 'Amal', 'Reem', 'Lamia', 'Doaa', 'Marwa', 'Noha', 'Gehan', 'Mary', 'Christine', 'Malak', 'Jana', 'Rawan', 'Asmaa'],
            ]
            : [
                ['أحمد', 'محمد', 'محمود', 'مصطفى', 'عمر', 'يوسف', 'خالد', 'كريم', 'طارق', 'هشام', 'وليد', 'سامح', 'إبراهيم', 'حسن', 'حسين', 'عادل', 'رامي', 'شريف', 'ياسر', 'أيمن', 'فادي', 'باسم', 'مينا', 'جورج', 'عصام', 'نادر', 'معاذ', 'أنس', 'زياد', 'سيف'],
                ['Ahmed', 'Mohamed', 'Mahmoud', 'Mostafa', 'Omar', 'Youssef', 'Khaled', 'Kareem', 'Tarek', 'Hisham', 'Walid', 'Sameh', 'Ibrahim', 'Hassan', 'Hussein', 'Adel', 'Rami', 'Sherif', 'Yasser', 'Ayman', 'Fadi', 'Bassem', 'Mina', 'George', 'Essam', 'Nader', 'Moaz', 'Anas', 'Ziad', 'Seif'],
            ];
    }

    private static function familyNames(): array
    {
        return [
            ['حسن', 'محمد', 'أحمد', 'إبراهيم', 'عبدالله', 'السيد', 'علي', 'مصطفى', 'عبدالرحمن', 'سالم', 'خليل', 'يوسف', 'الشريف', 'عزت', 'فهمي', 'جاد', 'منصور', 'رزق', 'صابر', 'نصار', 'البدري', 'شعبان', 'حماد', 'سعيد', 'زكي', 'عوض', 'جمعة', 'لبيب', 'رياض', 'فوزي'],
            ['Hassan', 'Mohamed', 'Ahmed', 'Ibrahim', 'Abdallah', 'Elsayed', 'Aly', 'Mostafa', 'Abdelrahman', 'Salem', 'Khalil', 'Youssef', 'Elsherif', 'Ezzat', 'Fahmy', 'Gad', 'Mansour', 'Rizk', 'Saber', 'Nassar', 'Elbadry', 'Shaaban', 'Hammad', 'Said', 'Zaki', 'Awad', 'Gomaa', 'Labib', 'Riad', 'Fawzy'],
        ];
    }

    private static function whiteSkills(): array
    {
        return ['Microsoft Excel', 'Microsoft Word', 'Customer Service', 'Bookkeeping', 'English Correspondence',
            'Data Entry', 'SAP', 'Communication Skills', 'Team Leadership', 'Sales Reporting', 'Social Media Marketing',
            'Project Coordination', 'Financial Analysis', 'Presentation Skills', 'Problem Solving', 'Time Management',
            'CRM Software', 'Content Writing', 'Recruitment', 'Inventory Systems'];
    }

    private static function blueSkills(): array
    {
        return ['Electrical Wiring', 'Welding', 'Forklift Operation', 'Carpentry', 'Plumbing', 'Machine Operation',
            'Quality Control', 'Safety Procedures', 'Vehicle Maintenance', 'Tailoring & Sewing', 'Masonry', 'Painting',
            'HVAC Maintenance', 'Food Preparation', 'Warehouse Handling', 'Construction Safety', 'Blueprint Reading',
            'Tool Maintenance', 'Hydraulics', 'Assembly Line Work'];
    }

    private static function whiteJobTitles(): array
    {
        return ['Accountant', 'Administrative Assistant', 'Customer Service Representative', 'HR Coordinator',
            'Sales Executive', 'Data Entry Clerk', 'Marketing Assistant', 'Bookkeeper', 'Receptionist',
            'IT Support Technician', 'Call Center Agent', 'Junior Accountant', 'Office Manager', 'Procurement Officer'];
    }

    private static function blueJobTitles(): array
    {
        return ['Electrician', 'Welder', 'Machine Operator', 'Forklift Driver', 'Carpenter', 'Plumber',
            'Construction Worker', 'Warehouse Worker', 'Tailor', 'Food Production Worker', 'Auto Mechanic',
            'Delivery Driver', 'Security Guard', 'Quality Control Inspector', 'Assembly Line Worker'];
    }

    private static function governorateWeights(): array
    {
        // Heavier around Greater Cairo and Alexandria, spread across the rest.
        return [
            'cai' => 20, 'giz' => 14, 'alx' => 10, 'qal' => 5, 'sha' => 4, 'dak' => 4, 'gha' => 3, 'mnf' => 3,
            'beh' => 3, 'kfs' => 3, 'dam' => 2, 'pts' => 2, 'ism' => 3, 'suz' => 2, 'fay' => 3, 'bns' => 3,
            'min' => 3, 'ast' => 3, 'soh' => 3, 'qen' => 2, 'lux' => 2, 'asw' => 2, 'red' => 1, 'wad' => 1,
            'mat' => 1, 'nsi' => 1, 'ssi' => 1,
        ];
    }

    // ── Random helpers ───────────────────────────────────────────────

    private static function weighted(array $weights): string
    {
        $total = array_sum($weights);
        $r = mt_rand(1, $total);
        foreach ($weights as $key => $w) {
            $r -= $w;
            if ($r <= 0) {
                return (string) $key;
            }
        }

        return (string) array_key_first($weights);
    }

    private static function pick(array $items)
    {
        return $items[array_rand($items)];
    }

    private static function chance(float $probability): bool
    {
        return mt_rand(1, 10000) <= $probability * 10000;
    }

    private static function egyptianMobile(): string
    {
        $prefix = self::pick(['010', '011', '012', '015']);

        return $prefix.str_pad((string) mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT);
    }

    /** now() minus a random number of days, never in the future, never more than 5 years old. */
    private static function backdate(int $minDaysAgo, int $maxDaysAgo): Carbon
    {
        return now()->subDays(mt_rand($minDaysAgo, $maxDaysAgo));
    }

    private static function laterOf(Carbon $from, int $minDays, int $maxDays): Carbon
    {
        $day = $from->copy()->addDays(mt_rand($minDays, $maxDays));

        return $day->greaterThan(today()) ? today()->copy() : $day;
    }

    // ── Beneficiaries ────────────────────────────────────────────────

    /** @return list<Beneficiary> */
    private function makeBeneficiaries(int $count): array
    {
        $out = [];
        $usedPhones = [];
        $usedEmails = [];
        $govWeights = self::governorateWeights();
        $sources = ['cv_upload', 'cv_upload', 'cv_upload', 'intake', 'intake', 'manual'];
        $bar = $this->output->createProgressBar($count);
        $bar->start();

        for ($i = 0; $i < $count; $i++) {
            $collar = $i % 2 === 0 ? 'white' : 'blue';
            $gender = self::chance(0.5) ? 'male' : 'female';
            [$firstAr, $firstEn] = self::firstNames($gender);
            $idx = array_rand($firstAr);
            [$familyAr, $familyEn] = self::familyNames();
            $fidx = array_rand($familyAr);
            $nameAr = $firstAr[$idx].' '.$familyAr[$fidx];
            $nameEn = $firstEn[$idx].' '.$familyEn[$fidx];

            $age = self::weightedAge();
            $dob = today()->subYears($age)->subDays(mt_rand(0, 364));

            $unitPool = $collar === 'white' ? $this->whiteUnits : $this->blueUnits;
            $unit = $unitPool === [] ? null : self::pick($unitPool);
            $esco = $unit ? EscoOccupation::query()->where('isco_group_id', $unit->id)->where('is_active', true)->inRandomOrder()->first() : null;

            $educationLevel = $this->educationLevelFor($collar, $age);
            $skillsPool = $collar === 'white' ? self::whiteSkills() : self::blueSkills();
            $skills = collect($skillsPool)->shuffle()->take(mt_rand(4, 8))->values()->all();

            $jobTitle = $esco?->title_en ?: ($unit?->title_en ?: ($collar === 'white' ? self::pick(self::whiteJobTitles()) : self::pick(self::blueJobTitles())));
            $workHistory = $this->workHistoryFor($age, $educationLevel, $jobTitle, $collar);

            $languages = $this->languagesFor($collar);
            $militaryStatus = $gender === 'male' ? $this->militaryStatusFor($age) : null;
            $expectedSalary = $this->salaryFor($collar, $workHistory);

            $phone = self::egyptianMobile();
            while (isset($usedPhones[$phone])) {
                $phone = self::egyptianMobile();
            }
            $usedPhones[$phone] = true;

            $email = null;
            if (self::chance(0.7)) {
                $email = strtolower($firstEn[$idx].'.'.$familyEn[$fidx].mt_rand(1, 999).'@'.self::pick(['gmail.com', 'yahoo.com', 'hotmail.com']));
                while (isset($usedEmails[$email])) {
                    $email = strtolower($firstEn[$idx].'.'.$familyEn[$fidx].mt_rand(1000, 99999).'@'.self::pick(['gmail.com', 'yahoo.com', 'hotmail.com']));
                }
                $usedEmails[$email] = true;
            }

            $data = [
                'name_ar' => $nameAr,
                'name_en' => $nameEn,
                'gender' => $gender,
                'date_of_birth' => $dob->toDateString(),
                'military_status' => $militaryStatus,
                'governorate' => self::weighted($govWeights),
                'city' => null,
                'phone' => $phone,
                'email' => $email,
                'education_level' => $educationLevel,
                'education' => $this->educationRecordsFor($educationLevel, $dob),
                'work_history' => $workHistory,
                'skills' => $skills,
                'languages' => $languages,
                'expected_salary' => $expectedSalary,
                'job_type' => self::weighted(['full_time' => 70, 'part_time' => 15, 'temporary' => 10, 'any' => 5]),
            ];
            if ($esco) {
                $data['esco_occupation_id'] = $esco->id;
            } elseif ($unit) {
                $data['occupation_unit'] = $unit->code;
            }

            try {
                $by = $this->caseworker();
                $source = self::pick($sources);
                $b = $this->recorder->create($this->company, $data, $by, 'manual', $source);

                // Spread registrations over the last ~9 months for realistic dashboards/reports.
                $registered = self::backdate(2, 270);
                DB::table('beneficiaries')->where('id', $b->id)->update([
                    'created_at' => $registered, 'updated_at' => $registered,
                ]);
                DB::table('beneficiary_changes')->where('beneficiary_id', $b->id)->where('action', 'created')
                    ->update(['created_at' => $registered]);

                $out[] = $b;
            } catch (Throwable $e) {
                $this->failures++;
                $this->newLine();
                $this->warn('Skipped one beneficiary after an error: '.$e->getMessage());
            }

            $bar->advance();
        }
        $bar->finish();
        $this->newLine();

        return $out;
    }

    private static function weightedAge(): int
    {
        $band = self::weighted(['young' => 55, 'mid' => 30, 'older' => 15]);

        return match ($band) {
            'young' => mt_rand(19, 30),
            'mid' => mt_rand(31, 45),
            default => mt_rand(46, 60),
        };
    }

    private function educationLevelFor(string $collar, int $age): string
    {
        $levels = $collar === 'white'
            ? ['secondary_technical' => 10, 'above_intermediate' => 20, 'university' => 55, 'postgraduate' => 10, 'secondary_general' => 5]
            : ['read_write' => 5, 'primary' => 10, 'preparatory' => 20, 'secondary_general' => 10, 'secondary_technical' => 45, 'above_intermediate' => 8, 'university' => 2];

        return self::weighted($levels);
    }

    private function educationRecordsFor(string $level, Carbon $dob): array
    {
        $fields = ['Commerce', 'Accounting', 'Business Administration', 'Mechanical Engineering', 'Electrical Technology',
            'General Secondary', 'Industrial Technology', 'Computer Science', 'Marketing', 'Vocational Trades'];
        $qualificationFor = [
            'none' => null, 'read_write' => null, 'primary' => 'Primary Certificate', 'preparatory' => 'Preparatory Certificate',
            'secondary_general' => 'General Secondary Certificate', 'secondary_technical' => 'Technical Secondary Diploma',
            'above_intermediate' => 'Intermediate Institute Diploma', 'university' => 'Bachelor’s Degree', 'postgraduate' => 'Postgraduate Diploma',
        ];
        $q = $qualificationFor[$level] ?? null;
        if (! $q) {
            return [];
        }

        return [[
            'qualification' => $q,
            'field' => self::pick($fields),
            'institution' => null,
            'year' => (string) $dob->copy()->addYears(mt_rand(18, 24))->year,
        ]];
    }

    private function workHistoryFor(int $age, string $educationLevel, ?string $jobTitle, string $collar): array
    {
        $startWorkAge = in_array($educationLevel, ['university', 'postgraduate'], true) ? 22 : 18;
        $maxYearsExperience = max(0, $age - $startWorkAge - mt_rand(0, 2));
        if ($maxYearsExperience <= 0 || self::chance(0.12)) {
            return [];   // some people have no recorded work history yet
        }

        $years = min($maxYearsExperience, mt_rand(1, 12));
        $jobsCount = $years > 6 ? mt_rand(2, 3) : ($years > 2 ? mt_rand(1, 2) : 1);
        $employerNames = $this->employers !== []
            ? array_map(fn ($e) => $e->name_en ?: $e->name_ar, $this->employers)
            : ['Nile Trading Co.', 'Delta Manufacturing Group', 'Cairo Retail Stores', 'Alexandria Logistics',
                'Giza Food Industries', 'Smart Solutions Egypt', 'El Nasr Textiles', 'Modern Construction Co.'];

        $titles = $collar === 'white' ? self::whiteJobTitles() : self::blueJobTitles();
        $jobs = [];
        $end = today()->subYears($years - min($years, mt_rand(0, 1)));
        $remainingMonths = $years * 12;
        for ($j = 0; $j < $jobsCount; $j++) {
            $isLast = $j === $jobsCount - 1;
            $span = $isLast ? $remainingMonths : (int) round($remainingMonths / max(1, $jobsCount - $j) * (mt_rand(70, 130) / 100));
            $span = max(3, min($span, $remainingMonths));
            $from = $end->copy()->subMonths($span);
            $jobs[] = [
                'title' => $j === $jobsCount - 1 ? ($jobTitle ?: self::pick($titles)) : self::pick($titles),
                'employer' => self::pick($employerNames),
                'from' => $from->format('Y-m'),
                'to' => $j === 0 && self::chance(0.35) ? null : $end->format('Y-m'),
                'current' => $j === 0 && self::chance(0.35),
                'country' => 'EG',
                'sub_sector' => $this->subSectors !== [] ? self::pick($this->subSectors) : null,
            ];
            $end = $from;
            $remainingMonths -= $span;
            if ($remainingMonths <= 0) {
                break;
            }
        }

        return array_reverse($jobs);
    }

    private function languagesFor(string $collar): array
    {
        $out = [['code' => 'ar', 'level' => 'native']];
        $enChance = $collar === 'white' ? 0.75 : 0.30;
        if (self::chance($enChance)) {
            $out[] = ['code' => 'en', 'level' => self::weighted($collar === 'white' ? ['good' => 40, 'fluent' => 40, 'basic' => 20] : ['basic' => 60, 'good' => 35, 'fluent' => 5])];
        }
        if (self::chance(0.06)) {
            $out[] = ['code' => 'fr', 'level' => self::pick(['basic', 'good'])];
        }

        return $out;
    }

    private function militaryStatusFor(int $age): string
    {
        if ($age < 21) {
            return self::weighted(['postponed' => 50, 'not_yet' => 40, 'serving' => 10]);
        }
        if ($age < 25) {
            return self::weighted(['completed' => 45, 'postponed' => 25, 'serving' => 15, 'exempted' => 15]);
        }

        return self::weighted(['completed' => 75, 'exempted' => 20, 'not_yet' => 5]);
    }

    private function salaryFor(string $collar, array $workHistory): int
    {
        $experienceBoost = count($workHistory) * mt_rand(300, 900);
        $base = $collar === 'white' ? mt_rand(4500, 9000) : mt_rand(2800, 6000);

        return min(30000, $base + $experienceBoost);
    }

    // ── CV upload, Review Queue, CV Bank ─────────────────────────────

    private function makeCvDocuments(array $beneficiaries): void
    {
        $byId = fn () => $this->caseworker();
        $chunks = array_chunk($beneficiaries, 25);
        $bar = $this->output->createProgressBar(count($beneficiaries) + 45);
        $bar->start();

        foreach ($chunks as $chunk) {
            $uploader = $byId();
            $uploadedAt = self::backdate(2, 260);
            $batch = new CvBatch;
            $batch->forceFill([
                'company_id' => $this->company->id,
                'user_id' => $uploader->id,
                'user_name' => $uploader->name,
                'client_id' => (string) Str::uuid(),
                'files_expected' => count($chunk),
                'files_received' => count($chunk),
            ])->save();
            DB::table('cv_batches')->where('id', $batch->id)->update(['created_at' => $uploadedAt, 'updated_at' => $uploadedAt]);

            foreach ($chunk as $b) {
                try {
                    $this->attachApprovedCv($b, $batch, $uploader, $uploadedAt);
                } catch (Throwable $e) {
                    $this->failures++;
                }
                $bar->advance();
            }
        }

        // A realistic pending Review Queue: raw uploads not yet turned into profiles.
        $reviewer = $byId();
        $batch = new CvBatch;
        $batch->forceFill([
            'company_id' => $this->company->id,
            'user_id' => $reviewer->id,
            'user_name' => $reviewer->name,
            'client_id' => (string) Str::uuid(),
            'files_expected' => 45,
            'files_received' => 45,
        ])->save();
        $queueDate = self::backdate(0, 20);
        DB::table('cv_batches')->where('id', $batch->id)->update(['created_at' => $queueDate, 'updated_at' => $queueDate]);

        for ($i = 0; $i < 45; $i++) {
            try {
                $this->makeQueueCv($batch, $reviewer, $beneficiaries, $queueDate);
            } catch (Throwable $e) {
                $this->failures++;
            }
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();
    }

    private function attachApprovedCv(Beneficiary $b, CvBatch $batch, User $uploader, Carbon $uploadedAt): void
    {
        $text = $this->cvText($b);
        $uuid = (string) Str::uuid();
        $path = $this->storage->put($this->company->id, $uuid, $text);

        $doc = new CvDocument;
        $doc->forceFill([
            'uuid' => $uuid,
            'company_id' => $this->company->id,
            'batch_id' => $batch->id,
            'uploaded_by' => $uploader->id,
            'uploaded_by_name' => $uploader->name,
            'original_name' => Str::slug($b->name_en ?: $b->name_ar ?: 'cv').'.txt',
            'extension' => 'txt',
            'size' => strlen($text),
            'sha256' => hash('sha256', $text),
            'stored_path' => $path,
            'status' => self::chance(0.85) ? CvDocument::APPROVED : CvDocument::ADDED,
            'language' => 'en',
            'text' => $text,
            'reading' => [
                'form' => ['name_ar' => $b->name_ar, 'name_en' => $b->name_en, 'phone' => $b->phone, 'email' => $b->email],
                'confidence' => 'high',
            ],
            'phone' => $b->phone,
            'email' => $b->email,
            'beneficiary_id' => $b->id,
            'reviewed_by' => $uploader->id,
            'reviewed_by_name' => $uploader->name,
            'reviewed_at' => $uploadedAt,
        ])->save();
        DB::table('cv_documents')->where('id', $doc->id)->update(['created_at' => $uploadedAt, 'updated_at' => $uploadedAt]);
    }

    private function makeQueueCv(CvBatch $batch, User $uploader, array $beneficiaries, Carbon $uploadedAt): void
    {
        $status = self::weighted(['review' => 55, 'duplicate' => 25, 'unreadable' => 20]);
        $gender = self::chance(0.5) ? 'male' : 'female';
        [$firstAr, $firstEn] = self::firstNames($gender);
        $idx = array_rand($firstAr);
        [$familyAr, $familyEn] = self::familyNames();
        $fidx = array_rand($familyAr);
        $nameEn = $firstEn[$idx].' '.$familyEn[$fidx];

        $uuid = (string) Str::uuid();
        $doc = new CvDocument;
        $base = [
            'uuid' => $uuid,
            'company_id' => $this->company->id,
            'batch_id' => $batch->id,
            'uploaded_by' => $uploader->id,
            'uploaded_by_name' => $uploader->name,
            'original_name' => Str::slug($nameEn).'-cv.pdf',
            'extension' => 'pdf',
            'status' => $status,
        ];

        if ($status === 'duplicate' && $beneficiaries !== []) {
            $existing = self::pick($beneficiaries);
            $text = "CV\n$nameEn\nMobile: {$existing->phone}\n";
            $base += [
                'size' => strlen($text), 'sha256' => hash('sha256', $text.$uuid),
                'stored_path' => $this->storage->put($this->company->id, $uuid, $text),
                'language' => 'en', 'text' => null,
                'reading' => ['form' => ['name_en' => $nameEn, 'phone' => $existing->phone]],
                'duplicates' => [['type' => 'person', 'beneficiary_id' => $existing->id, 'name' => $existing->displayName()]],
                'phone' => $existing->phone, 'email' => $existing->email,
            ];
        } elseif ($status === 'unreadable') {
            $placeholder = "(this file could not be read — a scanned or locked document)\n";
            $base += [
                'size' => mt_rand(80000, 900000), 'sha256' => hash('sha256', $uuid),
                'stored_path' => $this->storage->put($this->company->id, $uuid, $placeholder),
                'problem' => self::pick(['scanned', 'locked']), 'language' => null, 'text' => null,
            ];
        } else { // review — readable, but something is missing or uncertain
            $text = "CV\n$nameEn\n(occupation and mobile number could not be confidently read)\n";
            $base += [
                'size' => strlen($text), 'sha256' => hash('sha256', $text.$uuid),
                'stored_path' => $this->storage->put($this->company->id, $uuid, $text),
                'language' => 'en', 'text' => $text,
                'reading' => ['form' => ['name_en' => $nameEn], 'confidence' => 'low'],
            ];
        }

        $doc->forceFill($base)->save();
        DB::table('cv_documents')->where('id', $doc->id)->update([
            'created_at' => $uploadedAt->copy()->addMinutes(mt_rand(0, 600)),
            'updated_at' => $uploadedAt,
        ]);
    }

    private function cvText(Beneficiary $b): string
    {
        $skills = implode(', ', $b->skills ?? []);
        $jobs = collect($b->work_history ?? [])->map(fn ($j) => sprintf('- %s at %s (%s to %s)', $j['title'] ?? '', $j['employer'] ?? '', $j['from'] ?? '', $j['to'] ?? 'present'))->implode("\n");

        return trim("CV\n\nName: {$b->name_en} / {$b->name_ar}\nMobile: {$b->phone}\nEmail: {$b->email}\nGovernorate: {$b->governorate}\n\nEducation: {$b->education_level}\n\nExperience:\n{$jobs}\n\nSkills: {$skills}\n");
    }

    // ── Jobs & Training postings ─────────────────────────────────────

    /** @return list<Opportunity> */
    private function makeOpportunities(): array
    {
        $out = [];
        $jobDefs = [
            ['Accountant — Retail Chain', 'white', ['IND', 'S05'], ['cai', 'giz'], 8],
            ['Customer Service Representative — Call Centre', 'white', ['S03'], ['cai'], 15],
            ['HR Coordinator — Manufacturing Group', 'white', ['I01'], ['giz'], 3],
            ['Sales Executive — FMCG Distributor', 'white', ['T01'], ['cai', 'alx'], 6],
            ['Data Entry Clerk — Logistics Firm', 'white', ['T02'], ['cai'], 5],
            ['Electrician — Construction Site', 'blue', ['I05'], ['cai', 'giz'], 10],
            ['Welder — Steel Fabrication Plant', 'blue', ['I02'], ['alx'], 8],
            ['Machine Operator — Textile Factory', 'blue', ['I01'], ['mnf', 'dak'], 12],
            ['Warehouse Worker — Retail Distribution', 'blue', ['T02'], ['giz'], 10],
            ['Auto Mechanic — Fleet Services', 'blue', ['T03'], ['cai'], 4],
            ['Forklift Driver — Cold Storage', 'blue', ['T02'], ['alx'], 5],
            ['Security Guard — Facilities Company', 'blue', ['S02'], ['cai', 'giz', 'alx'], 20],
        ];
        $trainingDefs = [
            ['Customer Service Excellence Training', 'white', 'Future Vision Training Center', 25],
            ['Professional English Communication', 'white', 'Egypt Skills Academy', 20],
            ['Basic Bookkeeping & Excel', 'white', 'Cairo Technical Training Institute', 20],
            ['Certified Electrical Wiring Training', 'blue', 'Nile Institute for Vocational Training', 18],
            ['Welding Safety & Skills Certificate', 'blue', 'Egypt Skills Academy', 16],
            ['Forklift Operation Certification', 'blue', 'Career Path Training Hub', 15],
        ];

        foreach ($jobDefs as [$title, $collar, $subSectors, $govs, $seats]) {
            $out[] = $this->makeJob($title, $collar, $subSectors, $govs, $seats);
        }
        foreach ($trainingDefs as [$title, $collar, $provider, $seats]) {
            $out[] = $this->makeTraining($title, $collar, $provider, $seats);
        }

        return $out;
    }

    private function occupationsFor(string $collar, int $howMany = 2): array
    {
        $pool = $collar === 'white' ? $this->whiteUnits : $this->blueUnits;
        if ($pool === []) {
            return [];
        }
        $chosen = collect($pool)->shuffle()->take($howMany);
        $values = [];
        foreach ($chosen as $unit) {
            $esco = EscoOccupation::query()->where('isco_group_id', $unit->id)->where('is_active', true)->inRandomOrder()->first();
            $values[] = $esco ? "esco:{$esco->code}" : "isco:{$unit->code}";
        }

        return array_values(array_unique($values));
    }

    private function rulesFor(string $collar, array $occupations): array
    {
        $rules = [
            ['type' => 'age', 'mode' => 'must', 'min' => $collar === 'white' ? 20 : 18, 'max' => $collar === 'white' ? 40 : 50, 'points' => 0],
            ['type' => 'occupation', 'mode' => 'must', 'values' => $occupations, 'points' => 0],
        ];
        if ($collar === 'white') {
            $rules[] = ['type' => 'education_level', 'mode' => 'counts', 'points' => 30, 'values' => ['above_intermediate', 'university', 'postgraduate']];
            $rules[] = ['type' => 'skills', 'mode' => 'counts', 'points' => 25, 'need' => 'any', 'values' => collect(self::whiteSkills())->shuffle()->take(3)->values()->all()];
            $rules[] = ['type' => 'language', 'mode' => 'counts', 'points' => 25, 'code' => 'en', 'level' => 'good'];
            $rules[] = ['type' => 'experience', 'mode' => 'counts', 'points' => 20, 'min_years' => 1];
        } else {
            $rules[] = ['type' => 'skills', 'mode' => 'counts', 'points' => 40, 'need' => 'any', 'values' => collect(self::blueSkills())->shuffle()->take(3)->values()->all()];
            $rules[] = ['type' => 'experience', 'mode' => 'counts', 'points' => 30, 'min_years' => 1];
            $rules[] = ['type' => 'education_level', 'mode' => 'counts', 'points' => 30, 'values' => ['preparatory', 'secondary_general', 'secondary_technical', 'above_intermediate', 'university']];
        }

        return $rules;
    }

    private function makeJob(string $title, string $collar, array $subSectors, array $govs, int $seats): Opportunity
    {
        $occupations = $this->occupationsFor($collar);
        $employer = $this->employers !== [] ? self::pick($this->employers) : null;
        $by = $this->caseworker();
        $postedAt = self::backdate(5, 250);

        $o = new Opportunity;
        $o->forceFill([
            'company_id' => $this->company->id,
            'kind' => Opportunity::JOB,
            'title' => $title,
            'description' => "A {$title} position, posted for beneficiaries of Al-Amal Foundation.",
            'occupations' => $occupations,
            'occupation_keys' => $this->book->keys($occupations),
            'governorates' => $govs,
            'seats' => $seats,
            'deadline' => today()->addDays(mt_rand(20, 90)),
            'contact_user_id' => $by->id,
            'employer' => $employer?->displayName('en') ?: Str::before($title, ' —'),
            'employer_id' => $employer?->id,
            'sub_sector' => $this->subSectors !== [] ? self::pick($this->subSectors) : null,
            'job_type' => self::pick(Opportunity::JOB_TYPES),
            'salary_from' => $collar === 'white' ? mt_rand(4000, 6000) : mt_rand(2800, 4000),
            'salary_to' => $collar === 'white' ? mt_rand(7000, 12000) : mt_rand(4500, 7000),
            'eligible_from' => 70,
            'check_from' => 50,
            'rules' => $this->rulesFor($collar, $occupations),
            'created_by' => $by->id,
            'created_by_name' => $by->name,
            'status' => Opportunity::OPEN,
        ]);
        $o->note('created', $by->name);
        $o->save();
        DB::table('opportunities')->where('id', $o->id)->update(['created_at' => $postedAt, 'updated_at' => $postedAt]);

        return $o->fresh();
    }

    private function makeTraining(string $title, string $collar, string $provider, int $seats): Opportunity
    {
        $occupations = $this->occupationsFor($collar);
        $by = $this->caseworker();
        $postedAt = self::backdate(5, 250);

        $o = new Opportunity;
        $o->forceFill([
            'company_id' => $this->company->id,
            'kind' => Opportunity::TRAINING,
            'title' => $title,
            'description' => "A training programme run by {$provider} for Al-Amal Foundation beneficiaries.",
            'occupations' => $occupations,
            'occupation_keys' => $this->book->keys($occupations),
            'governorates' => ['cai', 'giz'],
            'seats' => $seats,
            'deadline' => today()->addDays(mt_rand(15, 60)),
            'starts_on' => today()->addDays(mt_rand(20, 70)),
            'ends_on' => today()->addDays(mt_rand(90, 150)),
            'contact_user_id' => $by->id,
            'provider' => $provider,
            'duration_value' => self::pick([2, 4, 6, 8, 12]),
            'duration_unit' => 'weeks',
            'format' => self::pick(Opportunity::FORMATS),
            'cost_type' => self::pick(['free', 'free', 'paid']),
            'cost_amount' => null,
            'certificate' => "{$title} Certificate",
            'eligible_from' => 70,
            'check_from' => 50,
            'rules' => $this->rulesFor($collar, $occupations),
            'created_by' => $by->id,
            'created_by_name' => $by->name,
            'status' => Opportunity::OPEN,
        ]);
        $o->note('created', $by->name);
        $o->save();
        DB::table('opportunities')->where('id', $o->id)->update(['created_at' => $postedAt, 'updated_at' => $postedAt]);

        return $o->fresh();
    }

    // ── Eligibility ──────────────────────────────────────────────────

    /** @param list<Opportunity> $opportunities @param list<Beneficiary> $beneficiaries */
    private function assessEligibility(array $opportunities, array $beneficiaries): void
    {
        $bar = $this->output->createProgressBar(count($opportunities));
        $bar->start();

        foreach ($opportunities as $o) {
            // The exact 4-digit occupation units this posting asks for (from occupation_keys,
            // e.g. "|isco:2|isco:24|isco:241|isco:2411|enoc:2411|esco:2411.1|" → "2411").
            preg_match_all('/isco:(\d{4})(?=\||$)/', (string) $o->occupation_keys, $matches);
            $unitCodes = array_unique($matches[1] ?? []);

            // A relevant pool (the same occupation the posting asks for) plus a sprinkling of
            // random others, similar to what "Check everyone" over a CV Bank search would gather.
            $relevantIds = [];
            $relevant = [];
            foreach ($beneficiaries as $b) {
                if ($unitCodes !== [] && $b->isco_code && in_array($b->isco_code, $unitCodes, true)) {
                    $relevant[] = $b;
                    $relevantIds[$b->id] = true;
                }
            }
            $others = array_values(array_filter($beneficiaries, fn (Beneficiary $b) => ! isset($relevantIds[$b->id])));
            shuffle($others);
            $others = array_slice($others, 0, 25);
            shuffle($relevant);
            $relevant = array_slice($relevant, 0, 90);
            $pool = collect(array_merge($relevant, $others))->unique('id')->values();

            $overridesLeft = mt_rand(0, 3);
            foreach ($pool as $b) {
                try {
                    $a = $this->assessor->assess($o, $b->loadMissing('escoOccupation:id,code'), $this->caseworker()->name);
                    if ($overridesLeft > 0 && $a->result !== EligibilityAssessment::ELIGIBLE && self::chance(0.15)) {
                        $decision = self::pick([EligibilityAssessment::ELIGIBLE, EligibilityAssessment::ON_HOLD]);
                        $reason = $decision === EligibilityAssessment::ELIGIBLE
                            ? 'Strong interview performance in a previous programme; case worker approved despite the score.'
                            : 'Waiting for the person to confirm availability before deciding.';
                        $this->assessor->decide($a, $decision, $reason, $this->caseworker());
                        $overridesLeft--;
                    }
                } catch (Throwable $e) {
                    $this->failures++;
                }
            }
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();
    }

    // ── Matches ──────────────────────────────────────────────────────

    /** @param list<Opportunity> $opportunities */
    private function makeMatches(array $opportunities): void
    {
        $bar = $this->output->createProgressBar(count($opportunities));
        $bar->start();

        foreach ($opportunities as $o) {
            $eligible = EligibilityAssessment::query()->withoutGlobalScopes()
                ->where('opportunity_id', $o->id)->where('result', EligibilityAssessment::ELIGIBLE)
                ->inRandomOrder()->limit(max(6, (int) round($o->seats * 2.2)))->pluck('beneficiary_id');

            if ($eligible->isEmpty()) {
                $bar->advance();

                continue;
            }

            $referredCount = 0;
            $acceptedPlus = 0;
            foreach ($eligible as $beneficiaryId) {
                $b = Beneficiary::query()->withoutGlobalScopes()->find($beneficiaryId);
                if (! $b) {
                    continue;
                }
                $by = $this->caseworker();
                $referredOn = self::backdate(5, 150);

                try {
                    $result = $this->matcher->refer($o, $b, $by, $referredOn->toDateString());
                } catch (Throwable $e) {
                    $this->failures++;

                    continue;
                }
                if (($result['outcome'] ?? null) !== 'referred' || ! $result['match']) {
                    continue;
                }
                $referredCount++;
                $m = $result['match'];

                $path = self::weighted(['referred' => 25, 'accepted' => 25, 'in_progress' => 20, 'done' => 20, 'stopped' => 10]);
                $day = $referredOn;
                try {
                    if (in_array($path, ['accepted', 'in_progress', 'done'], true)) {
                        $day = self::laterOf($day, 3, 15);
                        $this->matcher->move($m, OpportunityMatch::ACCEPTED, $by, $day->toDateString());
                        $acceptedPlus++;
                    }
                    if (in_array($path, ['in_progress', 'done'], true)) {
                        $day = self::laterOf($day, 5, 20);
                        $this->matcher->move($m, OpportunityMatch::IN_PROGRESS, $by, $day->toDateString());
                    }
                    if ($path === 'done') {
                        $day = self::laterOf($day, 10, 45);
                        $this->matcher->move($m, OpportunityMatch::DONE, $by, $day->toDateString());
                    }
                    if ($path === 'stopped') {
                        $day = self::laterOf($day, 2, 20);
                        $reason = self::pick(OpportunityMatch::STOP_REASONS[$o->kind] ?? ['other']);
                        $note = $reason === 'other' ? 'The person is no longer reachable.' : null;
                        $this->matcher->stop($m, $reason, $by, $day->toDateString(), $note);
                    }
                } catch (Throwable $e) {
                    $this->failures++;
                }

                // Let a share of active, unfinished matches look stale, so "Needs follow-up" has real examples.
                if (in_array($path, ['referred', 'accepted', 'in_progress'], true) && self::chance(0.3)) {
                    $stale = now()->subDays(mt_rand(16, 55));
                    DB::table('opportunity_matches')->where('id', $m->id)->update(['changed_at' => $stale]);
                }

                if ($acceptedPlus >= $o->seats + ($o->kind === Opportunity::JOB && self::chance(0.15) ? 3 : 0)) {
                    break;
                }
            }

            // Close a few postings to show closed jobs/training in the lists too.
            if ($referredCount > 0 && self::chance(0.2)) {
                $by = $this->caseworker();
                $reason = self::pick(Opportunity::CLOSE_REASONS);
                $o->forceFill(['status' => Opportunity::CLOSED, 'close_reason' => $reason, 'closed_at' => now()]);
                $o->note('closed', $by->name, ['reason' => $reason]);
                $o->save();
            }

            $bar->advance();
        }
        $bar->finish();
        $this->newLine();
    }
}
