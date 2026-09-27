<?php

namespace App\Services\Cv;

// ══════════════════════════════════════════════════════════════════
//  Massar — CvDictionary (the words the CV reader knows)
//  Location: app/Services/Cv/CvDictionary.php
//  Scope v2 §3 CV Reading Engine (rule-based, no AI)
//
//  Everything the reader recognises is written out here, in plain
//  English and Arabic, so anyone can see WHY a CV was read the way it
//  was, and add a missing word. Matching ignores upper/lower case,
//  Arabic hamza forms, tashkeel and "ال" (App\Support\TextNormalizer),
//  and accepts the broken "لا" spelling of some PDFs (ArabicRepair).
//
//  To add a word: put it in the right list below — nothing else.
//
//  The section headings include EVERY heading of the two reference
//  files "CV Section Title Variations — Parser Reference" (kept in
//  docs/cv-headings/). tests/Feature/CvHeadingsTest.php reads those two
//  files and checks that the reader recognises every heading in them.
//  (Words a reviewer teaches in the app — Learned Rules — come in the
//  next step and are kept per workspace, in the database.)
// ══════════════════════════════════════════════════════════════════

final class CvDictionary
{
    /**
     * Section headings → the section they start.
     *
     * A heading is recognised however it is written: upper or lower case,
     * singular or plural ("Skill" / "Skills"), British or American
     * spelling (SPELLING), "&" or "and" or "و", with a colon or a dash,
     * numbered ("1. Education", "03 | Skills"), decorated ("★ SKILLS ★",
     * "** Education **"), with its letters spaced out by the PDF
     * ("S ki ll s"), and bilingual ("Experience | الخبرات", "Skills / المهارات").
     * A combined heading ("Education & Training") is read as its first part
     * when it is not listed itself.
     *
     * Sections with no field on the form (references, hobbies, salary …)
     * are still listed: knowing where they START stops their text from
     * being read as part of the section above (hobbies read as skills).
     */
    public const HEADINGS = [
        // ── Professional summary / profile ─────────────────────────
        'summary' => [
            'summary', 'professional summary', 'career summary', 'profile', 'professional profile',
            'personal profile', 'about me', 'about', 'overview', 'personal statement', 'نبذة', 'نبذة عني',
            'نبذة مختصرة', 'ملخص', 'الملخص', 'الملخص المهني', 'الملف الشخصي', 'نبذة شخصية',
            // from your reference files
            'Professional Overview', 'Career Profile', 'Career Overview', 'Executive Summary', 'Executive Profile',
            'Summary of Qualifications', 'Qualifications Summary', 'Professional Highlights', 'Career Highlights',
            'Candidate Summary', 'Resume Summary', 'CV Summary', 'Profile Summary', 'About Myself', 'Introduction',
            'Professional Statement', 'Career Statement', 'My Profile', 'Who I Am', 'Snapshot', 'At a Glance',
            'Highlights', 'Professional Snapshot', 'Career Snapshot', 'نبذة مهنية', 'الملف المهني', 'ملخص الخبرات',
            'ملخص المؤهلات', 'الهدف المهني والملخص', 'Professional Summary & Objective',
        ],
        // ── Career objective ───────────────────────────────────────
        'objective' => [
            'objective', 'career objective', 'objectives', 'job objective', 'professional objective', 'الهدف',
            'الهدف الوظيفي', 'الهدف المهني', 'الأهداف',
            // from your reference files
            'Career Goal', 'Career Goals', 'Professional Goal', 'Career Aim', 'Employment Objective',
            'Career Aspiration', 'Career Aspirations', 'Professional Aspirations', 'Career Direction',
            'Career Target', 'Career Plan', 'My Objective', 'My Career Objective', 'Objective Statement',
            'Career Objective Statement', 'Professional Aim', 'الأهداف المهنية', 'هدفي المهني', 'الهدف من العمل',
            'التوجه المهني', 'الطموح المهني', 'Career Objective & Summary',
            // seen in the CVs you sent
            'Career Objectives',
        ],
        // ── Work experience (jobs are read here) ───────────────────
        'experience' => [
            'experience', 'experiences', 'work experience', 'professional experience', 'employment history',
            'employment', 'work history', 'career history', 'relevant experience', 'job history',
            'working experience', 'practical experience', 'الخبرة', 'الخبرات', 'الخبرة العملية', 'الخبرات العملية',
            'الخبرة المهنية', 'الخبرات المهنية', 'الخبرات السابقة', 'الخبرات الوظيفية', 'الخبرة الوظيفية',
            'سجل العمل', 'الخبرات العمليه',
            // from your reference files
            'Professional Experiences', 'Work Experiences', 'Employment Experience', 'Employment Record',
            'Career Experience', 'Career Record', 'Work Record', 'Job Experience', 'Professional Background',
            'Work Background', 'Employment Background', 'Career Background', 'Relevant Work Experience',
            'Relevant Professional Experience', 'Industry Experience', 'Corporate Experience',
            'Occupational Experience', 'Previous Employment', 'Previous Experience', 'Past Experience',
            'Experience History', 'Experience & Achievements', 'Professional Journey', 'Career Journey',
            'Career Path', 'Positions Held', 'Professional Career', 'Work Exp.', 'Prof. Experience',
            'Employment Hist.', 'Career Hist.', 'السجل الوظيفي', 'التاريخ الوظيفي', 'المسار المهني',
            'الخبرة العملية والمهنية', 'خبرات العمل', 'الوظائف السابقة', 'المناصب السابقة',
            'الخبرات الوظيفية السابقة', 'الخبرات العملية السابقة', 'Sales Experience', 'Marketing Experience',
            'Finance Experience', 'Accounting Experience', 'HR Experience', 'Human Resources Experience',
            'Engineering Experience', 'IT Experience', 'Software Development Experience', 'Management Experience',
            'Leadership Experience', 'Teaching Experience', 'Consulting Experience', 'Banking Experience',
            'Legal Experience', 'Medical Experience', 'Healthcare Experience', 'Manufacturing Experience',
            'Retail Experience', 'Customer Service Experience', 'Project Management Experience',
            'Experience & Skills', 'Experience & Education', 'Professional Experience, Skills & Achievements',
            // seen in the CVs you sent
            'Current Job', 'Current Position', 'Historical Experience', 'Previous Jobs',
        ],
        // ── Internships (read as jobs, like work experience) ───────
        'internships' => [
            // from your reference files
            'Internships', 'Internship Experience', 'Internship', 'Internship History', 'Student Internships',
            'Training & Internships', 'Industrial Training', 'Summer Training', 'Practical Training',
            'Cooperative Training', 'Co-op Experience', 'Trainee Experience', 'Graduate Training', 'التدريب العملي',
            'التدريب الصيفي', 'التدريب التعاوني', 'فترة التدريب', 'الخبرات التدريبية', 'التدريب الصناعي',
            // seen in the CVs you sent
            'Training Experience', 'Summer Internship', 'Summer Internships',
        ],
        // ── Education ──────────────────────────────────────────────
        'education' => [
            'education', 'educational background', 'academic background', 'qualifications',
            'academic qualifications', 'education and qualifications', 'educational qualifications',
            'academic history', 'education & training', 'التعليم', 'المؤهلات', 'المؤهل', 'المؤهل الدراسي',
            'المؤهلات الدراسية', 'المؤهلات العلمية', 'المؤهل العلمي', 'التعليم والمؤهلات', 'المؤهلات التعليمية',
            'الدراسة',
            // from your reference files
            'Education Background', 'Academic Experience', 'Educational History', 'Academic Credentials',
            'Academic Record', 'Educational Record', 'Degrees', 'Academic Degrees', 'Degrees & Education',
            'Formal Education', 'المؤهلات الأكاديمية', 'الخلفية التعليمية', 'الخلفية الأكاديمية', 'التاريخ التعليمي',
            'التاريخ الأكاديمي', 'الشهادات الأكاديمية', 'الدرجات العلمية', 'Education & Certifications',
            'Education, Training & Certifications',
        ],
        // ── Skills ─────────────────────────────────────────────────
        'skills' => [
            'skills', 'key skills', 'core skills', 'technical skills', 'computer skills', 'soft skills',
            'hard skills', 'professional skills', 'competencies', 'core competencies', 'skills and abilities',
            'skills & abilities', 'it skills', 'personal skills', 'areas of expertise', 'expertise', 'المهارات',
            'المهارات الشخصية', 'المهارات الفنية', 'المهارات التقنية', 'مهارات الحاسب الآلي', 'مهارات الكمبيوتر',
            'المهارات والقدرات', 'القدرات', 'المهارات العملية', 'المهارات المهنية', 'مهارات',
            // from your reference files
            'Skill Set', 'Skillset', 'Skills Summary', 'Skills Profile', 'Main Skills', 'Essential Skills',
            'Relevant Skills', 'Key Competencies', 'Professional Competencies', 'Areas of Competence',
            'Areas of Knowledge', 'Capabilities', 'Core Capabilities', 'Strengths', 'Key Strengths',
            'Professional Strengths', 'Abilities', 'Key Abilities', 'Proficiencies', 'Core Proficiencies',
            'Specialties', 'Specializations', 'Expertise & Skills', 'Skills & Competencies', 'Competencies & Skills',
            'Technical Skillset', 'Technical Competencies', 'Technical Expertise', 'Technical Proficiencies',
            'Technical Knowledge', 'Technical Capabilities', 'Technology Skills', 'Information Technology Skills',
            'Computer Proficiency', 'Computer Knowledge', 'Software Skills', 'Software Proficiency',
            'Digital Skills', 'Digital Competencies', 'Systems Skills', 'Systems Knowledge', 'Tools & Technologies',
            'Technologies', 'Technical Tools', 'Technical Background', 'Technical Qualifications',
            'Technical Abilities', 'Interpersonal Skills', 'Personal Competencies', 'Behavioral Skills',
            'People Skills', 'Social Skills', 'Communication Skills', 'Leadership Skills', 'Management Skills',
            'Teamwork Skills', 'Collaboration Skills', 'Personal Strengths', 'Core Personal Skills',
            'Transferable Skills', 'Essential Soft Skills', 'المهارات الأساسية', 'الكفاءات', 'الكفاءات الأساسية',
            'نقاط القوة', 'الخبرات والمهارات', 'المهارات والكفاءات', 'مجالات الخبرة', 'مجالات التخصص',
            'المهارات التكنولوجية', 'مهارات تكنولوجيا المعلومات', 'الخبرات التقنية', 'الكفاءات التقنية',
            'المعرفة التقنية', 'الأدوات والتقنيات', 'المهارات الناعمة', 'المهارات السلوكية', 'المهارات الاجتماعية',
            'مهارات التواصل', 'مهارات الاتصال', 'مهارات القيادة', 'مهارات العمل الجماعي', 'المهارات الإدارية',
            'Accounting Skills', 'Finance Skills', 'Sales Skills', 'Marketing Skills', 'Engineering Skills',
            'Programming Skills', 'Software Development Skills', 'Analytical Skills', 'Research Skills',
            'Customer Service Skills', 'Negotiation Skills', 'Additional Skills', 'Skills & Expertise',
            'Skills & Qualifications',
            // seen in the CVs you sent
            'Intra-Personal Skills', 'Inter-Personal Skills', 'Other Skills',
        ],
        // ── Skills and languages together (both are read) ──────────
        'skills_languages' => [
            // from your reference files
            'Languages & Skills',
            // seen in the CVs you sent
            'Computer and Linguistic Abilities', 'Computer and Language Skills', 'Language and Computer Skills',
            'Skills and Languages', 'Languages and Computer Skills', 'المهارات واللغات', 'اللغات والمهارات',
        ],
        // ── Languages ──────────────────────────────────────────────
        'languages' => [
            'languages', 'language skills', 'language', 'اللغات', 'المهارات اللغوية', 'اللغة',
            // from your reference files
            'Language Proficiency', 'Languages & Proficiency', 'Language Proficiencies', 'Foreign Languages',
            'Foreign Language Skills', 'Linguistic Skills', 'Linguistic Abilities', 'Language Competencies',
            'Language Knowledge', 'Spoken Languages', 'Languages Known', 'Languages Spoken',
            'Languages & Communication', 'اللغات الأجنبية', 'مهارات اللغة', 'اللغات ومستويات الإتقان',
            'إجادة اللغات', 'اللغات التي أتقنها',
        ],
        // ── Courses, training, certificates, licences, conferences ─
        'courses' => [
            'courses', 'training', 'trainings', 'training courses', 'courses and training', 'courses & training',
            'certifications', 'certificates', 'licenses and certifications', 'licenses & certifications',
            'professional development', 'workshops', 'الدورات', 'الدورات التدريبية', 'الدورات التدريبية والشهادات',
            'الشهادات', 'التدريب', 'الدورات والشهادات', 'الشهادات المهنية', 'ورش العمل',
            // from your reference files
            'Professional Certifications', 'Professional Certificates', 'Certifications & Licenses',
            'Certificates & Licenses', 'Credentials', 'Professional Credentials', 'Industry Certifications',
            'Technical Certifications', 'Certification', 'Certifications and Credentials', 'Licenses', 'Licences',
            'Professional Licenses', 'Professional Licence', 'Registrations', 'Professional Registrations',
            'الشهادات الاحترافية', 'الاعتمادات المهنية', 'الشهادات والاعتمادات', 'التراخيص', 'الرخص المهنية',
            'التراخيص المهنية', 'Training & Development', 'Professional Training', 'Training Programs',
            'Workshops & Training', 'Seminars & Training', 'Development Programs', 'Learning & Development',
            'Continuing Education', 'Continuing Professional Development', 'CPD', 'Training History',
            'Training Record', 'Courses Completed', 'Completed Courses', 'Relevant Courses', 'Professional Courses',
            'Academic Courses', 'Short Courses', 'Online Courses', 'Additional Courses', 'Selected Courses',
            'Workshops & Courses', 'التدريب والتطوير', 'التدريب المهني', 'البرامج التدريبية', 'الدورات والتدريب',
            'التطوير المهني', 'التعليم المستمر', 'التدريب والتطوير المهني', 'الدورات التي تم الحصول عليها',
            'الدورات المهنية', 'الدورات الإضافية', 'Licences & Certifications', 'Memberships & Licenses',
            'التسجيلات المهنية', 'الدورات ذات الصلة', 'Conferences', 'Conferences Attended',
            'Conference Participation', 'Seminars', 'Seminars Attended', 'Events', 'Professional Events',
            'Industry Events', 'Speaking Engagements', 'Presentations', 'Conference Presentations',
            'Professional Activities', 'المؤتمرات', 'المؤتمرات التي تم حضورها', 'الندوات', 'الفعاليات',
            'الفعاليات المهنية', 'المشاركة في المؤتمرات', 'العروض التقديمية', 'الاعتمادات',
            'Training & Certifications', 'Courses & Certifications',
            // seen in the CVs you sent
            'Courses and Sessions', 'Special Courses', 'Personality Enhancement', 'Scholarships',
            'Courses / Workshops',
        ],
        // ── Personal information ───────────────────────────────────
        'personal' => [
            'personal information', 'personal details', 'personal data', 'personal info', 'personal',
            'البيانات الشخصية', 'المعلومات الشخصية', 'بيانات شخصية', 'معلومات شخصية',
            // from your reference files
            'Personal Background', 'Candidate Information', 'Candidate Details', 'Bio Data', 'Biodata',
            'Personal Particulars', 'Basic Information', 'General Information', 'البيانات الأساسية', 'بيانات المرشح',
            'معلومات المرشح', 'معلومات عامة', 'Personal Information & Contact Details',
        ],
        // ── Contact information ────────────────────────────────────
        'contact' => [
            'contact', 'contact information', 'contact details', 'contact info', 'contacts', 'بيانات الاتصال',
            'معلومات الاتصال', 'للتواصل', 'التواصل', 'وسائل الاتصال',
            // from your reference files
            'Personal Contact', 'Contact Data', 'Candidate Contact', 'My Details', 'Get In Touch', 'Reach Me',
            'How to Contact Me', 'Contact Me', 'My Contact', 'Personal Contact Information', 'بيانات التواصل',
            'معلومات التواصل', 'Contact Information & Personal Details',
        ],
        // ── Military service (the line under it is read as the status) 
        'military' => [
            // from your reference files
            'Military Service', 'Military Status', 'Military Record', 'National Service', 'Military Background',
            'Armed Forces Service', 'الخدمة العسكرية', 'الموقف من التجنيد', 'الخدمة الوطنية',
            'Military Service Status', 'Military Duty', 'Conscription Status', 'Military Obligation',
            'Military Experience', 'موقف التجنيد', 'حالة التجنيد', 'الموقف من الخدمة العسكرية', 'التجنيد',
        ],
        // ── Projects and portfolio ─────────────────────────────────
        'projects' => [
            'projects', 'key projects', 'academic projects', 'graduation project', 'المشاريع', 'المشروعات',
            'مشروع التخرج',
            // from your reference files
            'Project Experience', 'Project History', 'Project Portfolio', 'Selected Projects', 'Major Projects',
            'Relevant Projects', 'Professional Projects', 'Personal Projects', 'University Projects',
            'Featured Projects', 'Project Highlights', 'Projects & Achievements', 'Selected Work',
            'Portfolio Projects', 'Portfolio', 'Work Portfolio', 'Professional Portfolio', 'Work Samples',
            'Samples of Work', 'Creative Portfolio', 'Online Portfolio', 'خبرات المشاريع', 'المشاريع الرئيسية',
            'أهم المشاريع', 'المشاريع المختارة', 'المشاريع الشخصية', 'المشاريع الأكاديمية', 'مشاريع التخرج',
            'معرض الأعمال', 'ملف الأعمال', 'نماذج الأعمال', 'Professional Work', 'أعمال سابقة', 'نماذج من الأعمال',
            'Projects & Experience',
        ],
        // ── References ─────────────────────────────────────────────
        'references' => [
            'references', 'referees', 'المراجع', 'المعرفون', 'المعرفين',
            // from your reference files
            'Professional References', 'References Available', 'References Upon Request', 'Professional Referees',
            'References & Recommendations', 'Recommendations', 'Professional Recommendations', 'Testimonials',
            'Testimonial', 'References Available Upon Request', 'Available Upon Request', 'التزكيات', 'التوصيات',
            'المراجع المهنية', 'التوصيات المهنية',
        ],
        // ── Interests and hobbies ──────────────────────────────────
        'interests' => [
            'interests', 'hobbies', 'hobbies and interests', 'الهوايات', 'الاهتمامات',
            // from your reference files
            'Personal Interests', 'Professional Interests', 'Interests & Hobbies', 'Personal Interests & Hobbies',
            'Personal Activities', 'Leisure Activities', 'Outside Interests', 'Areas of Interest',
            'الهوايات والاهتمامات', 'الاهتمامات الشخصية', 'الأنشطة الشخصية', 'الأنشطة اللامنهجية',
        ],
        // ── Volunteering and activities ────────────────────────────
        'volunteering' => [
            'volunteering', 'volunteer experience', 'volunteer work', 'activities', 'extracurricular activities',
            'العمل التطوعي', 'الأنشطة التطوعية', 'الأنشطة', 'الانشطة',
            // from your reference files
            'Community Service', 'Community Involvement', 'Social Service', 'Voluntary Work', 'Volunteer Activities',
            'Community Activities', 'Civic Engagement', 'Nonprofit Experience', 'NGO Experience', 'الخبرات التطوعية',
            'التطوع', 'الخدمة المجتمعية', 'المشاركة المجتمعية',
            // seen in the CVs you sent
            'Other Activities', 'Extracurricular Experience', 'Extra-Curricular Activities',
            'Extracircular Activities', 'Curricular Activities', 'Student Activities', 'الأنشطة الطلابية',
        ],
        // ── Achievements and awards ────────────────────────────────
        'achievements' => [
            'achievements', 'awards', 'honors', 'الإنجازات', 'الجوائز',
            // from your reference files
            'Key Achievements', 'Major Achievements', 'Professional Achievements', 'Career Achievements',
            'Notable Achievements', 'Significant Achievements', 'Accomplishments', 'Key Accomplishments',
            'Professional Accomplishments', 'Successes', 'Milestones', 'Key Contributions', 'Contributions',
            'Impact', 'Awards & Honors', 'Honours', 'Honors & Awards', 'Awards and Recognition', 'Recognition',
            'Professional Recognition', 'Distinctions', 'Achievements & Awards', 'Awards & Achievements', 'Prizes',
            'Academic Honors', 'Academic Awards', 'Professional Awards', 'أهم الإنجازات', 'الإنجازات المهنية',
            'الإنجازات الوظيفية', 'أبرز الإنجازات', 'النجاحات', 'المساهمات', 'الجوائز والتكريمات', 'التكريمات',
            'الجوائز والتقدير', 'الإنجازات الرئيسية', 'التقدير والجوائز', 'الجوائز المهنية', 'الجوائز الأكاديمية',
        ],
        // ── Publications ───────────────────────────────────────────
        'publications' => [
            // from your reference files
            'Publications', 'Published Work', 'Publications & Research', 'Research Publications',
            'Academic Publications', 'Papers', 'Published Papers', 'Articles', 'Journal Articles', 'Books',
            'Book Chapters', 'Writing', 'Selected Publications', 'Selected Works', 'المنشورات', 'الأبحاث المنشورة',
            'المنشورات العلمية', 'الأبحاث', 'المقالات المنشورة', 'المؤلفات',
        ],
        // ── Research ───────────────────────────────────────────────
        'research' => [
            // from your reference files
            'Research', 'Research Experience', 'Research Interests', 'Research Projects', 'Research Background',
            'Research Work', 'Academic Research', 'Research Activities', 'Research Profile', 'Areas of Research',
            'البحث العلمي', 'الخبرات البحثية', 'الاهتمامات البحثية', 'المشاريع البحثية', 'مجالات البحث',
        ],
        // ── Memberships ────────────────────────────────────────────
        'memberships' => [
            // from your reference files
            'Memberships', 'Professional Memberships', 'Professional Affiliations', 'Affiliations', 'Associations',
            'Professional Associations', 'Organizational Memberships', 'Memberships & Affiliations',
            'Professional Organizations', 'Societies', 'Memberships & Associations', 'العضويات', 'العضويات المهنية',
            'الجمعيات المهنية', 'العضويات والجمعيات', 'الانتماءات المهنية', 'عضوية الجمعيات المهنية',
        ],
        // ── Availability / notice period ───────────────────────────
        'availability' => [
            // from your reference files
            'Availability', 'Start Date', 'Available From', 'Joining Date', 'Notice Period', 'Availability Date',
            'Earliest Start Date', 'Expected Start Date', 'Joining Availability', 'Work Availability', 'التفرغ',
            'تاريخ التفرغ', 'تاريخ البدء', 'تاريخ الانضمام', 'فترة الإخطار', 'مدة الإخطار', 'موعد بدء العمل',
        ],
        // ── Salary expectations ────────────────────────────────────
        'salary' => [
            // from your reference files
            'Salary Expectations', 'Expected Salary', 'Salary Requirement', 'Compensation Expectations',
            'Compensation Requirement', 'Expected Compensation', 'Desired Salary', 'Desired Compensation',
            'Pay Expectations', 'Remuneration Expectations', 'Compensation', 'Salary Range', 'الراتب المتوقع',
            'الراتب المطلوب', 'توقعات الراتب', 'الراتب المستهدف', 'الأجر المتوقع', 'توقعات الأجر', 'متطلبات الراتب',
            'الراتب والبدلات',
        ],
        // ── Career preferences ─────────────────────────────────────
        'preferences' => [
            // from your reference files
            'Career Preferences', 'Job Preferences', 'Employment Preferences', 'Career Interests', 'Job Interests',
            'Desired Position', 'Desired Job', 'Desired Role', 'Target Position', 'Target Role',
            'Preferred Position', 'Preferred Role', 'Preferred Job', 'Career Interests & Goals',
            'التفضيلات الوظيفية', 'التفضيلات المهنية', 'الوظيفة المطلوبة', 'الوظيفة المستهدفة',
            'المسمى الوظيفي المطلوب', 'المجال الوظيفي المطلوب', 'الاهتمامات المهنية',
        ],
        // ── Additional information ─────────────────────────────────
        'additional' => [
            // from your reference files
            'Additional Information', 'Additional Details', 'Other Information', 'Other Details',
            'Further Information', 'Miscellaneous', 'Additional Qualifications', 'Other Qualifications',
            'Additional Experience', 'Other Experience', 'Additional Activities', 'Notes', 'Comments',
            'معلومات إضافية', 'بيانات إضافية', 'تفاصيل إضافية', 'معلومات أخرى', 'بيانات أخرى', 'مؤهلات إضافية',
            'ملاحظات',
        ],
    ];

    /**
     * Sub-headings INSIDE one job ("Key Responsibilities:", "المهام الوظيفية").
     * They never start a new section: the lines under them are that job's
     * responsibilities (duties). A few words are also section headings
     * ("Achievements", "Activities", "الإنجازات"); inside the work
     * experience, after a job and before the next job, they are read as
     * part of the job.
     */
    public const JOB_SUBHEADINGS = [
        'Responsibilities', 'Job Responsibilities', 'Job Responsibility', 'Key Responsibilities',
        'Main Responsibilities', 'Major Responsibilities', 'Primary Responsibilities', 'Core Responsibilities',
        'Core Job Responsibilities', 'Main Job Responsibilities', 'Key Job Responsibilities',
        'Primary Job Responsibilities', 'Principal Responsibilities', 'Professional Responsibilities',
        'Work Responsibilities', 'Role Responsibilities', 'Position Responsibilities', 'Duties', 'Job Duties',
        'Key Duties', 'Main Duties', 'Major Duties', 'Primary Duties', 'Core Duties', 'Principal Duties',
        'Duties and Responsibilities', 'Key Duties and Responsibilities', 'Main Duties and Responsibilities',
        'Job Duties and Responsibilities', 'Duties & Functions', 'Roles & Responsibilities',
        'Roles, Responsibilities & Achievements', 'Roles, Duties & Responsibilities',
        'Role, Duties and Responsibilities', 'Key Roles & Responsibilities', 'Main Roles & Responsibilities',
        'Roles and Key Responsibilities', 'Accountabilities', 'Key Accountabilities', 'Main Accountabilities',
        'Major Accountabilities', 'Primary Accountabilities', 'Core Accountabilities', 'Job Accountabilities',
        'Role Accountabilities', 'Position Accountabilities', 'Key Areas of Accountability',
        'Areas of Accountability', 'Accountabilities & Responsibilities', 'Key Responsibilities & Accountabilities',
        'Functions', 'Job Functions', 'Main Functions', 'Key Functions', 'Core Functions', 'Primary Functions',
        'Functional Responsibilities', 'Role Functions', 'Position Functions', 'Key Functions & Responsibilities',
        'Tasks', 'Job Tasks', 'Key Tasks', 'Main Tasks', 'Primary Tasks', 'Core Tasks', 'Major Tasks',
        'Main Job Tasks', 'Key Job Tasks', 'Duties and Tasks', 'Responsibilities and Tasks', 'Role Description',
        'Job Role Description', 'Position Description', 'Job Description', 'Position Overview', 'Role Overview',
        'Job Overview', 'Position Overview & Responsibilities', 'Role Summary', 'Job Summary', 'Position Summary',
        'Areas of Responsibility', 'Areas of Responsibilities', 'Responsibility Areas', 'Key Responsibility Areas',
        'Key Responsibility Area', 'KRAs', 'Key Result Areas', 'Areas of Work', 'Scope of Responsibilities',
        'Scope of Work', 'Scope of Role', 'Role Scope', 'Job Scope', 'Position Scope', 'Key Deliverables',
        'Deliverables', 'Major Deliverables', 'Work Scope', 'Responsibilities & Deliverables',
        'Role Scope & Deliverables', 'Key Areas of Responsibility', 'Areas of Responsibility & Deliverables',
        'Leadership Responsibilities', 'Management Responsibilities', 'Management Duties', 'Leadership Duties',
        'Executive Responsibilities', 'Managerial Responsibilities', 'Supervisory Responsibilities',
        'Team Management Responsibilities', 'Management Functions', 'Leadership Functions', 'Areas of Leadership',
        'Areas of Management', 'Contributions', 'Key Contributions', 'Major Contributions',
        'Professional Contributions', 'Contributions & Achievements', 'Key Contributions & Achievements',
        'Value Added', 'Value-Added Contributions', 'Major Contributions to the Organization', 'Activities',
        'Job Activities', 'Work Activities', 'Professional Activities', 'Role Activities', 'Position Activities',
        'Key Activities', 'Main Activities', 'Major Activities', 'Work-Related Activities',
        'Responsibilities & Achievements', 'Duties & Achievements', 'Roles & Achievements',
        'Key Responsibilities & Achievements', 'Responsibilities, Achievements & Skills',
        'Duties, Responsibilities & Achievements', 'المسؤوليات', 'المسؤوليات الوظيفية', 'مسؤوليات الوظيفة',
        'المسؤوليات الرئيسية', 'المسؤوليات الأساسية', 'أهم المسؤوليات', 'المهام والمسؤوليات', 'المسؤوليات والمهام',
        'مسؤوليات العمل', 'مسؤوليات الدور الوظيفي', 'مسؤوليات المنصب', 'المهام', 'مهام العمل', 'مهام الوظيفة',
        'المهام الوظيفية', 'المهام الرئيسية', 'المهام الأساسية', 'أهم المهام', 'الواجبات', 'واجبات الوظيفة',
        'الواجبات والمسؤوليات', 'المساءلة', 'المسؤوليات والمساءلة', 'مجالات المسؤولية', 'مجالات المساءلة',
        'المسؤوليات الرئيسية والمساءلة', 'الاختصاصات', 'اختصاصات الوظيفة', 'المهام والاختصاصات',
        'الوظائف والاختصاصات', 'المهام الأساسية والاختصاصات', 'نطاق العمل', 'نطاق المسؤوليات', 'نطاق الوظيفة',
        'نطاق الدور', 'اختصاصات ونطاق العمل', 'الإنجازات', 'الإنجازات الوظيفية', 'الإنجازات المهنية',
        'أهم الإنجازات', 'المساهمات', 'أهم المساهمات', 'المساهمات والإنجازات', 'الأدوار والمسؤوليات',
        'الوصف الوظيفي', 'وصف الدور الوظيفي', 'الاختصاصات الرئيسية', 'Key Contributions & Responsibilities',
        // added: common inside a job in the CVs you sent (a section again after the last job)
        'Achievements', 'Key Achievements', 'Accomplishments', 'Key Accomplishments',
    ];

    /**
     * Short headings that can mean more than one section. The text under
     * the heading decides: job dates → experience; degrees or places of
     * study → education; otherwise the first section in the list.
     */
    public const AMBIGUOUS_HEADINGS = [
        'background'     => ['summary', 'experience', 'education'],
        'qualifications' => ['education', 'courses'],
        'qualification'  => ['education', 'courses'],
        'history'        => ['experience', 'education'],
        'career'         => ['experience', 'summary'],
        'highlights'     => ['summary', 'experience'],
        'credentials'    => ['courses', 'education'],
        'professional'   => ['summary', 'experience'],
        'work'           => ['experience'],
        'information'    => ['personal'],
        'details'        => ['personal'],
        'المؤهلات'       => ['education', 'courses'],
        'الخلفية'        => ['summary', 'experience', 'education'],
    ];

    /**
     * One-word headings that are also ordinary words inside a list
     * ("• Research", "Writing" under Skills). They start a section only
     * when the line LOOKS like a heading: in capitals, ending with ":",
     * bold, decorated ("★ RESEARCH"), or in Arabic.
     */
    public const WEAK_HEADINGS = [
        'writing', 'papers', 'books', 'articles', 'events', 'presentations', 'impact', 'recognition', 'successes',
        'milestones', 'notes', 'comments', 'compensation', 'societies', 'associations', 'testimonial', 'testimonials',
        'recommendations', 'introduction', 'snapshot', 'strengths', 'abilities', 'capabilities', 'technologies',
        'research', 'portfolio', 'credentials', 'registrations', 'seminars', 'conferences', 'highlights', 'prizes',
        'distinctions', 'affiliations', 'specialties', 'specializations', 'proficiencies', 'degrees', 'miscellaneous',
        'availability', 'career', 'work', 'history', 'background', 'information', 'details', 'professional',
        'licenses', 'licences', 'certification', 'internship', 'contributions', 'deliverables', 'functions', 'tasks',
        'duties', 'accountabilities', 'responsibilities', 'publications', 'memberships', 'volunteering',
    ];

    /** British → American spelling, so either way of writing a heading is recognised. */
    public const SPELLING = [
        'organisation' => 'organization', 'organisational' => 'organizational', 'organisations' => 'organizations',
        'specialisation' => 'specialization', 'specialisations' => 'specializations', 'licence' => 'license',
        'licences' => 'licenses', 'programme' => 'program', 'programmes' => 'programs', 'honour' => 'honor',
        'honours' => 'honors', 'centre' => 'center', 'behaviour' => 'behavior', 'behavioural' => 'behavioral',
        'analyse' => 'analyze', 'summarise' => 'summarize', 'extracurricular' => 'extra curricular',
        'extracircular' => 'extra curricular', 'interpersonal' => 'inter personal', 'intrapersonal' => 'intra personal',
        'expérience' => 'experience', 'expériences' => 'experiences', 'éducation' => 'education', 'compétences' => 'skills',
    ];

    /**
     * Words written as a small label on their own line INSIDE a section
     * ("Degree", "Graduation year", "Position:", "Web site:"). They are
     * never a new section heading, and a block of such labels followed by
     * a block of values (a table in a Word or PDF CV) is read label by
     * label.
     */
    public const FIELD_LABELS = [
        'degree', 'department', 'academy', 'major', 'minor', 'grade', 'gpa', 'cgpa', 'graduation year', 'year of graduation',
        'graduated', 'graduation', 'last year grade', 'university', 'faculty', 'college', 'school', 'high school', 'institute',
        'bachelor', 'specialization', 'field of study', 'overall grade', 'total grade', 'estimate', 'expected graduation',
        'company', 'employer', 'organization', 'duration', 'period', 'from', 'to', 'location', 'website', 'web site',
        'department name', 'industry', 'reporting to', 'reports to', 'job type', 'employment type', 'dates',
        'المؤهل', 'التقدير', 'سنة التخرج', 'الجامعة', 'الكلية', 'القسم', 'الشعبة', 'التخصص', 'المدرسة', 'الشركة',
        'جهة العمل', 'المدة', 'الفترة', 'من', 'إلى', 'الموقع',
    ];

    /** Words in front of a job title that are not part of it ("Worked as Accountant" → "Accountant"). */
    public const TITLE_PREFIXES = [
        'worked as a', 'worked as an', 'worked as', 'working as a', 'working as an', 'working as', 'work as', 'served as',
        'employed as', 'i worked as', 'i work as', 'attended an', 'attended a', 'attended', 'joined as', 'as a', 'as an', 'as the', 'as',
        'promoted to', 'promoted as',
        'عملت ك', 'عملت', 'أعمل ك', 'اعمل ك', 'العمل ك',
    ];

    /** "Label: value" lines → the field they fill. */
    public const LABELS = [
        'name'      => ['name', 'full name', 'الاسم', 'الاسم بالكامل', 'الاسم الكامل', 'اسم'],
        'dob'       => ['date of birth', 'birth date', 'birthdate', 'dob', 'd o b', 'born', 'birthday', 'تاريخ الميلاد', 'الميلاد', 'تاريخ الميلاد ومحله'],
        'gender'    => ['gender', 'sex', 'النوع', 'الجنس', 'نوع'],
        'military'  => [
            'military status', 'military service', 'military', 'military service status', 'army status',
            'الموقف من التجنيد', 'الموقف التجنيدي', 'موقف التجنيد', 'التجنيد', 'الخدمة العسكرية', 'الموقف من الخدمة العسكرية',
        ],
        'address'   => ['address', 'location', 'residence', 'city', 'lives in', 'العنوان', 'محل الإقامة', 'الإقامة', 'السكن', 'المدينة', 'محل السكن'],
        'phone'     => ['mobile', 'phone', 'tel', 'telephone', 'cell', 'mobile number', 'phone number', 'whatsapp',
                        'موبايل', 'الموبايل', 'المحمول', 'الهاتف', 'تليفون', 'رقم الهاتف', 'جوال', 'رقم الموبايل', 'تلفون'],
        'email'     => ['email', 'e mail', 'mail', 'البريد الإلكتروني', 'البريد', 'الإيميل', 'ايميل'],
        'national_id' => ['national id', 'national id number', 'id number', 'nid', 'الرقم القومي', 'رقم البطاقة', 'الرقم القومى'],
        'position'  => ['position', 'job title', 'current position', 'title', 'desired position', 'applying for', 'occupation', 'profession',
                        'المسمى الوظيفي', 'الوظيفة', 'الوظيفة الحالية', 'الوظيفة المطلوبة', 'المهنة', 'الوظيفة المتقدم لها'],
        'languages' => ['languages', 'language', 'اللغات'],
        'marital'   => ['marital status', 'social status', 'الحالة الاجتماعية', 'الحالة الإجتماعية'],
        'nationality' => ['nationality', 'الجنسية'],
    ];

    /** Words that mean "a CV", never a person's name. */
    public const NOT_A_NAME = [
        'curriculum vitae', 'cv', 'resume', 'résumé', 'السيرة الذاتية', 'سيرة ذاتية', 'السيره الذاتيه', 'personal cv', 'my cv',
    ];

    public const GENDER = [
        'male'   => ['male', 'm', 'man', 'ذكر', 'ذكر.', 'رجل'],
        'female' => ['female', 'f', 'woman', 'انثى', 'أنثى', 'انثي', 'أنثي', 'امرأة'],
    ];

    /**
     * Arabic marital status is written in the person's own gender
     * (أعزب / عزباء), so it tells the gender without guessing.
     */
    public const MARITAL_GENDER = [
        'male'   => ['أعزب', 'متزوج', 'مطلق', 'أرمل'],
        'female' => ['عزباء', 'متزوجة', 'مطلقة', 'أرملة', 'آنسة'],
    ];

    /** Military status words → config/beneficiaries.php codes. Checked in this order. */
    public const MILITARY = [
        'postponed' => ['temporary exemption', 'temporarily exempted', 'postponed', 'deferred', 'deferment', 'postpone',
                        'إعفاء مؤقت', 'معفى مؤقت', 'مؤجل', 'تأجيل', 'مؤجل دراسة', 'تأجيل دراسي'],
        'exempted'  => ['exempted', 'exempt', 'exemption', 'final exemption', 'permanently exempted', 'not required',
                        'معفى', 'معافى', 'إعفاء', 'إعفاء نهائي', 'معفي', 'اعفاء نهائي', 'معاف', 'غير مطلوب'],
        'completed' => ['completed', 'complete', 'done', 'finished', 'performed', 'served', 'fulfilled', 'completed service',
                        'أدى الخدمة', 'ادى الخدمة', 'أديت الخدمة', 'تم أداء الخدمة', 'أنهى الخدمة', 'انهى الخدمة', 'تم الأداء', 'أدى', 'أديت', 'انتهيت', 'منتهي'],
        'serving'   => ['currently serving', 'serving', 'in service', 'يؤدي الخدمة', 'في الخدمة حاليا', 'تحت التجنيد'],
        'not_yet'   => ['not yet', 'not applicable', 'under age', 'لم يحن', 'لم يحن الموعد', 'لم أؤد بعد', 'لم يحن بعد'],
    ];

    /**
     * Places → governorate code (config/beneficiaries.php). The first
     * word of each list is the governorate itself; the rest are its
     * well-known cities and districts.
     */
    public const PLACES = [
        'cai' => ['cairo', 'القاهرة', 'nasr city', 'madinet nasr', 'maadi', 'heliopolis', 'new cairo', 'fifth settlement', '5th settlement',
                  'zamalek', 'mokattam', 'helwan', 'ain shams', 'shubra', 'abbasiya', 'downtown cairo', 'el marg', 'matareya', 'rehab', 'madinaty',
                  'مدينة نصر', 'المعادي', 'مصر الجديدة', 'القاهرة الجديدة', 'التجمع الخامس', 'التجمع', 'الزمالك', 'المقطم', 'حلوان',
                  'عين شمس', 'شبرا', 'العباسية', 'وسط البلد', 'المرج', 'المطرية', 'الرحاب', 'مدينتي', 'السلام', 'الشروق'],
        'giz' => ['giza', 'الجيزة', 'dokki', 'mohandessin', 'mohandeseen', 'haram', 'faisal', '6th of october', '6 october', 'october city',
                  'sheikh zayed', 'imbaba', 'agouza', 'الدقي', 'المهندسين', 'الهرم', 'فيصل', '6 أكتوبر', 'السادس من أكتوبر', 'أكتوبر',
                  'الشيخ زايد', 'إمبابة', 'العجوزة', 'حدائق الأهرام'],
        'alx' => ['alexandria', 'alex', 'الإسكندرية', 'اسكندرية', 'إسكندرية', 'smouha', 'sidi gaber', 'borg el arab', 'سموحة', 'سيدي جابر', 'برج العرب', 'العجمي', 'agami'],
        'qal' => ['qalyubia', 'qaliubiya', 'kalyoubia', 'qalubia', 'القليوبية', 'banha', 'benha', 'shubra el kheima', 'بنها', 'شبرا الخيمة', 'قليوب', 'العبور', 'obour'],
        'dak' => ['dakahlia', 'daqahlia', 'الدقهلية', 'mansoura', 'المنصورة', 'mit ghamr', 'ميت غمر', 'طلخا'],
        'sha' => ['sharqia', 'sharkia', 'el sharkia', 'الشرقية', 'zagazig', 'الزقازيق', '10th of ramadan', 'العاشر من رمضان', 'بلبيس', 'belbeis'],
        'gha' => ['gharbia', 'gharbiya', 'الغربية', 'tanta', 'طنطا', 'el mahalla', 'mahalla', 'المحلة', 'المحلة الكبرى', 'كفر الزيات'],
        'mnf' => ['monufia', 'menoufia', 'menofia', 'المنوفية', 'shebin el kom', 'شبين الكوم', 'منوف', 'السادات', 'sadat city'],
        'beh' => ['beheira', 'behira', 'البحيرة', 'damanhour', 'دمنهور', 'كفر الدوار', 'kafr el dawar'],
        'kfs' => ['kafr el sheikh', 'kafr elsheikh', 'كفر الشيخ', 'desouk', 'دسوق'],
        'dam' => ['damietta', 'دمياط', 'new damietta', 'دمياط الجديدة', 'رأس البر'],
        'pts' => ['port said', 'portsaid', 'بورسعيد', 'بور سعيد', 'بورفؤاد', 'port fouad'],
        'ism' => ['ismailia', 'الإسماعيلية', 'الاسماعيلية'],
        'suz' => ['suez', 'السويس'],
        'fay' => ['fayoum', 'faiyum', 'fayum', 'الفيوم'],
        'bns' => ['beni suef', 'bani suef', 'beni sweif', 'بني سويف'],
        'min' => ['minya', 'minia', 'el minya', 'المنيا', 'ملوي'],
        'ast' => ['assiut', 'asyut', 'assuit', 'أسيوط', 'اسيوط'],
        'soh' => ['sohag', 'suhag', 'سوهاج'],
        'qen' => ['qena', 'kena', 'قنا', 'نجع حمادي'],
        'lux' => ['luxor', 'الأقصر', 'الاقصر'],
        'asw' => ['aswan', 'أسوان', 'اسوان'],
        'red' => ['red sea', 'البحر الأحمر', 'hurghada', 'الغردقة', 'safaga', 'سفاجا'],
        'wad' => ['new valley', 'الوادي الجديد', 'kharga', 'الخارجة'],
        'mat' => ['matrouh', 'marsa matrouh', 'مطروح', 'مرسى مطروح', 'العلمين', 'el alamein'],
        'nsi' => ['north sinai', 'شمال سيناء', 'arish', 'العريش'],
        'ssi' => ['south sinai', 'جنوب سيناء', 'sharm el sheikh', 'شرم الشيخ', 'dahab', 'دهب', 'الطور'],
    ];

    /** Words that make a place look like a street, not a governorate mention ("Cairo University"). */
    public const NOT_AN_ADDRESS = ['university', 'جامعة', 'bank', 'بنك', 'airport', 'مطار', 'company', 'شركة', 'faculty', 'كلية', 'school', 'مدرسة'];

    /** Education keywords → level (config/beneficiaries.php). Checked from the highest level down. */
    public const EDUCATION_LEVELS = [
        'postgraduate' => ['phd', 'ph d', 'doctorate', 'doctor of', 'master', 'masters', 'msc', 'm sc', 'mba', 'm a', 'ma in', 'meng',
                           'postgraduate', 'post graduate', 'higher diploma', 'general diploma', 'professional diploma', 'diploma in education', 'pre-master', 'doctoral', 'doctoral degree', 'pre master', 'pre masters', 'diploma of higher studies',
                           'دكتوراه', 'دكتوراة', 'ماجستير', 'دبلوم عام', 'الدبلوم العام', 'دبلوم مهني', 'دبلومة مهنية', 'الدبلوم المهني', 'ماجيستير', 'دبلوم عالي', 'دبلومة عليا', 'دبلوم الدراسات العليا', 'تمهيدي ماجستير', 'الدراسات العليا'],
        'university'   => ['bachelor', 'bachelors', 'bsc', 'b sc', 'b a', 'ba in', 'b com', 'bcom', 'beng', 'b eng', 'llb', 'licence', 'license in',
                           'faculty of', 'college of', 'higher institute', 'university degree', 'graduate of', 'bba', 'bachelor s degree',
                           'بكالوريوس', 'بكالريوس', 'ليسانس', 'كلية', 'المعهد العالي', 'معهد عالي', 'معهد عالى', 'مؤهل جامعي'],
        'above_intermediate' => ['technical institute', 'institute of', 'two year diploma', 'associate degree', 'intermediate institute',
                           'معهد فني', 'معهد متوسط', 'دبلوم فوق المتوسط', 'المعهد الفني', 'معهد فنى'],
        'secondary_technical' => ['technical diploma', 'technical secondary', 'commercial diploma', 'industrial diploma', 'agricultural diploma',
                           'دبلوم صنايع', 'دبلوم تجارة', 'دبلوم زراعة', 'دبلوم فني', 'دبلوم فنى', 'ثانوي فني', 'ثانوية فنية', 'دبلوم تجاري', 'دبلوم صناعي'],
        'secondary_general' => ['high school', 'secondary school', 'general secondary', 'thanaweya amma', 'thanaweya', 'igcse', 'american diploma',
                           'british diploma', 'ib diploma', 'ثانوية عامة', 'الثانوية العامة', 'ثانوي عام', 'الثانوية الأزهرية', 'ثانوية أزهرية'],
        'preparatory'  => ['preparatory', 'middle school', 'الشهادة الإعدادية', 'إعدادية', 'الإعدادية', 'اعدادية'],
        'primary'      => ['primary school', 'الشهادة الابتدائية', 'ابتدائية', 'الابتدائية'],
    ];

    /** Words that make an education line a place of study. */
    public const INSTITUTION_WORDS = [
        'university', 'college', 'institute', 'academy', 'school', 'faculty', 'polytechnic',
        'جامعة', 'كلية', 'معهد', 'أكاديمية', 'اكاديمية', 'مدرسة', 'المعهد',
    ];

    /** Words that make a work line an employer, not a job title. */
    public const EMPLOYER_WORDS = [
        'company', 'co', 'ltd', 'llc', 'inc', 'group', 'bank', 'corporation', 'corp', 'organization', 'organisation', 'foundation',
        'hotel', 'hospital', 'clinic', 'factory', 'agency', 'firm', 'office', 'store', 'stores', 'restaurant', 'ministry', 'authority',
        'holding', 'industries', 'trading', 'solutions', 'services', 'sae', 's a e',
        'banque', 'plc', 'gmbh', 'jsc', 'est', 'establishment', 'enterprises', 'partners', 'associates', 'consultants', 'consulting',
        'foods', 'food industries', 'hotels', 'resort', 'resorts', 'hospitals', 'clinics', 'pharma', 'pharmaceuticals', 'pharmacy', 'pharmacies',
        'laboratories', 'labs', 'center', 'centre', 'mall', 'supermarket', 'hypermarket', 'markets', 'contractors', 'contracting',
        'construction', 'constructions', 'developments', 'properties', 'real estate', 'investments', 'investment', 'capital', 'insurance',
        'airways', 'airlines', 'telecom', 'telecommunications', 'communications', 'logistics', 'shipping', 'motors', 'electric', 'cables',
        'steel', 'cement', 'petroleum', 'chemicals', 'textiles', 'plastics', 'packaging', 'mills', 'systems', 'technologies', 'software',
        'media', 'studio', 'studios', 'embassy', 'council', 'association', 'society', 'ngo', 'unicef', 'undp', 'usaid',
        'مصرف', 'مصانع', 'صيدلية', 'صيدليات', 'مركز', 'مجمع', 'شركات', 'فنادق', 'مستشفيات', 'منظمة', 'سفارة', 'مجلس', 'نقابة',
    ];

    /**
     * Words that make a line a JOB TITLE (with the official titles of
     * ENOC, ESCO and ISCO-08 and the workspace's taught titles). The
     * main word of a title is usually the last word in English
     * ("Bank Teller") and the first word in Arabic ("محاسب أول").
     */
    public const TITLE_WORDS = [
        'manager', 'accountant', 'engineer', 'officer', 'specialist', 'supervisor', 'assistant', 'analyst', 'director', 'executive',
        'representative', 'rep', 'coordinator', 'clerk', 'technician', 'intern', 'internship', 'trainee', 'consultant', 'auditor',
        'head', 'chief', 'controller', 'teller', 'cashier', 'agent', 'advisor', 'adviser', 'associate', 'administrator', 'admin',
        'secretary', 'receptionist', 'nurse', 'doctor', 'physician', 'pharmacist', 'dentist', 'therapist', 'teacher', 'instructor',
        'lecturer', 'tutor', 'trainer', 'coach', 'driver', 'designer', 'developer', 'programmer', 'architect', 'surveyor', 'planner',
        'buyer', 'merchandiser', 'operator', 'worker', 'foreman', 'electrician', 'plumber', 'carpenter', 'mechanic', 'chef', 'cook',
        'waiter', 'waitress', 'barista', 'captain', 'lawyer', 'attorney', 'counsel', 'paralegal', 'editor', 'writer', 'translator',
        'interpreter', 'journalist', 'photographer', 'researcher', 'scientist', 'economist', 'statistician', 'lead', 'leader',
        'president', 'vp', 'ceo', 'cfo', 'coo', 'cto', 'cio', 'founder', 'banker', 'broker', 'underwriter', 'actuary', 'inspector',
        'storekeeper', 'bookkeeper', 'keeper', 'steward', 'guard', 'ambassador', 'dispatcher', 'estimator', 'draftsman', 'expert',
        'collector', 'promoter', 'salesman', 'saleswoman', 'salesperson', 'superintendent', 'moderator', 'author', 'artist', 'chemist',
        'biologist', 'geologist', 'pilot', 'veterinarian', 'midwife', 'paramedic', 'technologist', 'marketer', 'recruiter', 'strategist',
        'محاسب', 'محاسبة', 'مدير', 'مديرة', 'مهندس', 'مهندسة', 'مشرف', 'مشرفة', 'مسؤول', 'مسئول', 'مسؤولة', 'أخصائي', 'اخصائي', 'أخصائية',
        'مندوب', 'مندوبة', 'فني', 'فنية', 'موظف', 'موظفة', 'سكرتير', 'سكرتيرة', 'مساعد', 'مساعدة', 'رئيس', 'رئيسة', 'مراجع', 'محلل',
        'مستشار', 'مدرس', 'معلم', 'معلمة', 'ممرض', 'ممرضة', 'طبيب', 'طبيبة', 'صيدلي', 'صيدلانية', 'سائق', 'مصمم', 'مصممة', 'مطور',
        'مبرمج', 'منسق', 'منسقة', 'كاتب', 'أمين', 'عامل', 'كهربائي', 'ميكانيكي', 'نجار', 'سباك', 'طباخ', 'شيف', 'كاشير', 'خبير',
        'متدرب', 'متدربة', 'نائب', 'وكيل', 'باحث', 'باحثة', 'مترجم', 'مفتش', 'ضابط', 'حارس', 'مدقق', 'مراقب', 'ممثل', 'مدير عام',
    ];

    /**
     * The first word of a line that says what the person DID (a duty),
     * not what the job was: "Managing …", "Prepared …", "إعداد …".
     * (Any English word ending in -ing or -ed at the start counts too.)
     */
    public const DUTY_WORDS = [
        'manage', 'prepare', 'handle', 'responsible', 'assist', 'support', 'develop', 'create', 'maintain', 'ensure', 'monitor',
        'review', 'coordinate', 'conduct', 'perform', 'achieve', 'provide', 'analyze', 'analyse', 'follow', 'report', 'supervise',
        'participate', 'organize', 'organise', 'plan', 'build', 'design', 'implement', 'deliver', 'train', 'negotiate', 'answer',
        'communicate', 'track', 'identify', 'set', 'drive', 'oversee', 'reconcile', 'record', 'process', 'issue', 'contribute',
        'establish', 'restructure', 'close', 'work', 'launch', 'assess', 'evaluate', 'execute', 'control', 'reduce', 'increase',
        'improve', 'optimize', 'optimise', 'streamline', 'direct', 'led', 'built', 'made', 'ran', 'grew', 'cut', 'drove', 'won',
        'took', 'met', 'saved', 'initiate', 'introduce', 'raise', 'secure', 'obtain', 'negotiated', 'represent', 'coach', 'mentor',
        'recruit', 'hire', 'audit', 'check', 'verify', 'calculate', 'follow up', 'liaise', 'attend', 'deal', 'act', 'serve', 'help',
        'take', 'make', 'keep', 'maintain', 'handle', 'lead the', 'head the', 'responsible for', 'in charge of', 'accountable for',
        'إعداد', 'اعداد', 'متابعة', 'مراجعة', 'تنفيذ', 'تحضير', 'تسجيل', 'تنظيم', 'الإشراف', 'الاشراف', 'إشراف', 'العمل', 'التعامل',
        'المشاركة', 'تقديم', 'استقبال', 'الرد', 'حل', 'تدريب', 'تطوير', 'التواصل', 'التنسيق', 'تنسيق', 'عمل', 'القيام', 'المساعدة',
        'مسؤول عن', 'مسئول عن', 'تحليل', 'تصميم', 'إدخال', 'ادخال', 'جمع', 'بيع', 'تحصيل', 'توزيع', 'صيانة',
    ];

    /**
     * Places outside Egypt's own list (PLACES) that CVs name next to a
     * job: "Riyadh, KSA", "Doha – Qatar". A line (or a part of it) that is
     * only a place goes to the job's location — never into the job title
     * or the employer.
     */
    public const WORLD_PLACES = [
        'egypt', 'misr', 'مصر', 'جمهورية مصر العربية', 'arab republic of egypt',
        'ksa', 'k s a', 'saudi arabia', 'saudi', 'kingdom of saudi arabia', 'السعودية', 'المملكة العربية السعودية', 'المملكة',
        'uae', 'u a e', 'united arab emirates', 'emirates', 'الإمارات', 'الامارات', 'الإمارات العربية المتحدة',
        'qatar', 'قطر', 'kuwait', 'الكويت', 'bahrain', 'البحرين', 'oman', 'sultanate of oman', 'سلطنة عمان', 'jordan', 'الأردن', 'الاردن',
        'lebanon', 'لبنان', 'iraq', 'العراق', 'libya', 'ليبيا', 'sudan', 'السودان', 'morocco', 'المغرب', 'tunisia', 'تونس', 'algeria', 'الجزائر',
        'yemen', 'اليمن', 'syria', 'سوريا', 'palestine', 'فلسطين', 'turkey', 'تركيا', 'usa', 'u s a', 'united states', 'us', 'uk', 'u k',
        'united kingdom', 'england', 'germany', 'ألمانيا', 'france', 'فرنسا', 'italy', 'إيطاليا', 'spain', 'canada', 'كندا', 'australia',
        'china', 'india', 'malaysia', 'cyprus', 'greece', 'netherlands', 'ireland', 'switzerland', 'remote', 'عن بعد',
        'riyadh', 'الرياض', 'jeddah', 'jedda', 'جدة', 'dammam', 'الدمام', 'khobar', 'al khobar', 'الخبر', 'dhahran', 'الظهران',
        'makkah', 'mecca', 'مكة', 'مكة المكرمة', 'madinah', 'medina', 'المدينة المنورة', 'jubail', 'الجبيل', 'yanbu', 'ينبع', 'tabuk', 'تبوك',
        'abha', 'أبها', 'taif', 'الطائف', 'qassim', 'القصيم', 'hail', 'حائل', 'neom', 'نيوم',
        'dubai', 'دبي', 'abu dhabi', 'أبوظبي', 'أبو ظبي', 'sharjah', 'الشارقة', 'ajman', 'عجمان', 'al ain', 'العين', 'ras al khaimah', 'رأس الخيمة',
        'doha', 'الدوحة', 'kuwait city', 'مدينة الكويت', 'manama', 'المنامة', 'muscat', 'مسقط', 'amman', 'عمّان', 'beirut', 'بيروت',
        'baghdad', 'بغداد', 'erbil', 'أربيل', 'tripoli', 'طرابلس', 'benghazi', 'بنغازي', 'khartoum', 'الخرطوم', 'casablanca', 'الدار البيضاء',
        'istanbul', 'إسطنبول', 'london', 'لندن', 'paris', 'باريس', 'berlin', 'new york', 'toronto',
    ];

    /**
     * Labels on a line inside a job ("Industry: FMCG"). The value of a
     * title / employer / place label goes to that box; "skip" labels are
     * details the form has no box for, and are not duties.
     */
    public const JOB_LABELS = [
        'title'    => ['position', 'job title', 'title', 'role', 'designation', 'job', 'post', 'job position', 'current position', 'last position', 'present position', 'occupation', 'المسمى الوظيفي', 'المسمى', 'الوظيفة', 'المنصب'],
        'employer' => ['company', 'employer', 'organization', 'organisation', 'company name', 'employer name', 'client', 'current employer',
                       'previous employer', 'last employer', 'current company', 'previous company', 'الشركة', 'جهة العمل', 'جهة العمل الحالية', 'جهة العمل السابقة',
                       'اسم الشركة', 'المؤسسة', 'اسم الجهة'],
        'place'    => ['location', 'city', 'country', 'place', 'work location', 'based in', 'المكان', 'الموقع', 'المدينة', 'الدولة', 'البلد', 'مكان العمل'],
        'dates'    => ['period', 'duration', 'dates', 'date', 'from', 'years', 'الفترة', 'المدة', 'التاريخ', 'مدة العمل'],
        'skip'     => ['employer profile', 'position purpose', 'job purpose', 'purpose', 'company overview', 'about the company', 'industry', 'sector', 'website', 'web site', 'web', 'reporting to', 'reports to', 'report to', 'reporting line',
                       'department', 'dept', 'division', 'job type', 'employment type', 'type', 'company size', 'business', 'field',
                       'activity', 'company profile', 'company activity', 'reason for leaving', 'reason of leaving', 'salary', 'team size',
                       'النشاط', 'القطاع', 'المجال', 'الموقع الإلكتروني', 'القسم', 'الإدارة', 'نوع العمل', 'سبب ترك العمل', 'مجال الشركة',
                       'نشاط الشركة', 'التبعية'],
    ];

    /**
     * Well-known employers in Egypt and the Gulf, so a line with only
     * their name is read as the employer ("EY", "Xerox – Egypt",
     * "in Pricewater House Coopers"). Employers in your own profiles and
     * taught employers (Learned Rules) are added to these automatically.
     */
    public const WELL_KNOWN_EMPLOYERS = [
        // audit and consulting
        'pwc', 'pricewaterhousecoopers', 'pricewater house coopers', 'price waterhouse coopers', 'kpmg', 'kpmg hazem hassan', 'ey', 'ernst & young',
        'ernst and young', 'deloitte', 'grant thornton', 'bdo', 'baker tilly', 'mazars', 'crowe', 'rsm', 'moore', 'mckinsey', 'bcg', 'accenture',
        // banks
        'citibank', 'citi', 'hsbc', 'cib', 'commercial international bank', 'qnb', 'qnb alahli', 'nbe', 'national bank of egypt', 'banque misr',
        'bank misr', 'banque du caire', 'aaib', 'arab african international bank', 'adib', 'faisal islamic bank', 'emirates nbd', 'mashreq',
        'alex bank', 'bank of alexandria', 'credit agricole', 'nsgb', 'societe generale', 'barclays', 'standard chartered', 'jp morgan',
        'efg hermes', 'ci capital', 'beltone', 'naeem', 'pioneers', 'al ahly capital', 'gulf investment corporation',
        // telecom and technology
        'vodafone', 'orange', 'etisalat', 'etisalat misr', 'we', 'telecom egypt', 'mobinil', 'huawei', 'ericsson', 'nokia', 'ibm', 'microsoft',
        'oracle', 'sap', 'xerox', 'hp', 'dell', 'cisco', 'raya', 'raya holding', 'link datacenter', 'fawry', 'valeo',
        // consumer goods, retail, food
        'pepsico', 'pepsi', 'coca cola', 'coca-cola', 'nestle', 'unilever', 'procter & gamble', 'p&g', 'mondelez', 'cadbury', 'kraft', 'heinz',
        'juhayna', 'edita', 'americana', 'almarai', 'savola', 'danone', 'carrefour', 'spinneys', 'metro', 'hyper one', 'ikea', 'lazurde',
        'majid al futtaim', 'al futtaim', 'mansour', 'mansour group', 'el sewedy', 'elsewedy', 'elsewedy electric', 'elaraby', 'olympic group',
        'philips', 'siemens', 'abb', 'schneider electric', 'general electric', 'ge', 'general motors', 'gm', 'toyota', 'nissan', 'mercedes',
        'bmw', 'gb auto', 'ghabbour', 'bosch', 'samsung', 'lg',
        // pharma
        'pfizer', 'gsk', 'glaxosmithkline', 'sanofi', 'novartis', 'astrazeneca', 'roche', 'bayer', 'eva pharma', 'amoun', 'pharco', 'wyeth',
        'abbott', 'merck', 'hikma',
        // energy, construction, real estate, transport
        'weatherford', 'schlumberger', 'halliburton', 'baker hughes', 'petrojet', 'enppi', 'apache', 'bp', 'shell', 'eni', 'total', 'aramco',
        'saudi aramco', 'sabic', 'adnoc', 'qatar petroleum', 'orascom', 'orascom construction', 'hassan allam', 'arab contractors',
        'talaat moustafa', 'emaar', 'emaar misr', 'sodic', 'palm hills', 'mountain view', 'aramex', 'dhl', 'fedex', 'maersk', 'egyptair',
        'emirates', 'qatar airways', 'saudia',
        // hotels
        'hilton', 'marriott', 'sheraton', 'four seasons', 'accor', 'movenpick', 'rotana', 'intercontinental', 'kempinski', 'fairmont',
    ];

    /** Words that make a line a course or a certificate — never a job. */
    public const COURSE_WORDS = [
        'course', 'courses', 'certificate', 'certification', 'certified', 'workshop', 'seminar', 'bootcamp', 'training program',
        'training course', 'diploma course', 'ccna', 'ccnp', 'mcsa', 'mcse', 'icdl', 'tefl', 'celta', 'ielts', 'toefl', 'pmp', 'cma',
        'cpa', 'cia', 'acca', 'udemy', 'coursera', 'edx', 'linkedin learning', 'itida', 'iti', 'new horizons',
        'دورة', 'دورات', 'كورس', 'شهادة', 'ورشة', 'ورشة عمل', 'برنامج تدريبي', 'دبلومة تدريبية',
    ];

    /** Words that mean "until now" in a date range. */
    public const PRESENT = [
        'present time', 'present', 'current', 'currently', 'now', 'today', 'to this day', 'to date', 'till now', 'until now', 'ongoing', 'to present', 'till date',
        'الآن', 'الان', 'حتى الآن', 'حتى الان', 'حاليا', 'حالياً', 'حتى تاريخه', 'لحد الآن', 'إلى الآن', 'الى الان', 'حتي الان',
    ];

    public const MONTHS = [
        1 => ['jan', 'january', 'يناير', 'كانون الثاني'],
        2 => ['feb', 'february', 'febraury', 'feburary', 'فبراير', 'شباط'],
        3 => ['mar', 'march', 'مارس', 'آذار'],
        4 => ['apr', 'april', 'abril', 'aprile', 'أبريل', 'ابريل', 'إبريل', 'نيسان'],
        5 => ['may', 'مايو', 'أيار'],
        6 => ['jun', 'june', 'يونيو', 'يونيه', 'حزيران'],
        7 => ['jul', 'july', 'يوليو', 'يوليه', 'تموز'],
        8 => ['aug', 'august', 'augst', 'agust', 'أغسطس', 'اغسطس', 'آب'],
        9 => ['sep', 'sept', 'september', 'seb', 'sebtember', 'septemper', 'سبتمبر', 'أيلول'],
        10 => ['oct', 'october', 'أكتوبر', 'اكتوبر', 'تشرين الأول'],
        11 => ['nov', 'november', 'نوفمبر', 'تشرين الثاني'],
        12 => ['dec', 'december', 'ديسمبر', 'كانون الأول'],
    ];

    /** Language names → config/beneficiaries.php codes. */
    public const LANGUAGES = [
        'ar' => ['arabic', 'العربية', 'عربي', 'العربي', 'اللغة العربية', 'عربية'],
        'en' => ['english', 'الإنجليزية', 'الانجليزية', 'انجليزي', 'إنجليزي', 'الإنجليزي', 'اللغة الإنجليزية', 'الانكليزية', 'انجليزية'],
        'fr' => ['french', 'الفرنسية', 'فرنسي', 'اللغة الفرنسية', 'فرنساوي'],
        'de' => ['german', 'الألمانية', 'ألماني', 'الالمانية', 'اللغة الألمانية'],
        'it' => ['italian', 'الإيطالية', 'الايطالية', 'إيطالي'],
        'es' => ['spanish', 'الإسبانية', 'الاسبانية', 'إسباني'],
        'ru' => ['russian', 'الروسية', 'روسي'],
        'zh' => ['chinese', 'mandarin', 'الصينية', 'صيني'],
        'tr' => ['turkish', 'التركية', 'تركي'],
    ];

    /** Language level words → codes. Checked in this order (so "very good" is not read as "good"… both are 'good'). */
    public const LANGUAGE_LEVELS = [
        'native' => ['native', 'mother tongue', 'mother language', 'first language', 'native speaker', 'اللغة الأم', 'لغة أم', 'اللغه الام', 'الأم', 'لغتي الأم'],
        'fluent' => ['fluent', 'fluently', 'excellent', 'proficient', 'advanced', 'full professional', 'bilingual', 'c1', 'c2',
                     'ممتاز', 'ممتازة', 'بطلاقة', 'متقدم', 'طلاقة', 'إجادة تامة', 'اجادة تامة'],
        'good'   => ['very good', 'good', 'intermediate', 'upper intermediate', 'working knowledge', 'professional working', 'b1', 'b2', 'conversational',
                     'جيد جدا', 'جيد جداً', 'جيد', 'متوسط', 'جيدة', 'جيدة جدا'],
        'basic'  => ['basic', 'basics', 'beginner', 'elementary', 'fair', 'weak', 'limited', 'pre intermediate', 'a1', 'a2',
                     'مبتدئ', 'ضعيف', 'مقبول', 'أساسي', 'اساسي', 'مبتدأ'],
    ];

    /** Words in front of a job title that do not change the occupation ("Senior Accountant" = accountant). */
    public const TITLE_NOISE = [
        'senior', 'junior', 'sr', 'jr', 'trainee', 'experienced', 'certified', 'freelance', 'freelancer',
        'أول', 'اول', 'أقدم', 'مبتدئ', 'متدرب', 'خبير', 'حر',
    ];
}
