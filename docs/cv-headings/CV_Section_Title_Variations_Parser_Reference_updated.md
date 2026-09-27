# CV Section Title Variations --- Parser Reference

## Purpose

This document is a reference dataset for CV/resume parsing and section
classification.

It maps the many headings candidates may use for the same CV section to
a **canonical section category**. The focus is on wording variation,
abbreviations, punctuation, capitalization, singular/plural differences,
alternative terminology, and common English/Arabic combinations.

The intended use is to help build or evaluate CV parsers, resume
extraction systems, ATS integrations, and normalization logic for
platforms such as job portals.

> **Important:** A heading should be classified using both the heading
> text and the surrounding content. Some phrases are inherently
> ambiguous. For example, `Education` is normally a section title, while
> `Training` may refer to courses, professional training, or an
> employer's training program.

------------------------------------------------------------------------

# 1. Recommended Canonical Section Taxonomy

A parser should ideally normalize headings into a controlled set of
canonical section IDs.

  Canonical ID                Recommended Display Name
  --------------------------- --------------------------
  `contact_information`       Contact Information
  `professional_summary`      Professional Summary
  `career_objective`          Career Objective
  `profile`                   Profile
  `professional_experience`   Professional Experience
  `work_experience`           Work Experience
  `employment_history`        Employment History
  `career_history`            Career History
  `education`                 Education
  `academic_background`       Academic Background
  `qualifications`            Qualifications
  `skills`                    Skills
  `technical_skills`          Technical Skills
  `soft_skills`               Soft Skills
  `professional_skills`       Professional Skills
  `languages`                 Languages
  `certifications`            Certifications
  `licenses`                  Licenses
  `training`                  Training
  `courses`                   Courses
  `projects`                  Projects
  `portfolio`                 Portfolio
  `achievements`              Achievements
  `awards`                    Awards
  `honors`                    Honors
  `volunteer_experience`      Volunteer Experience
  `internships`               Internships
  `publications`              Publications
  `research`                  Research
  `conferences`               Conferences
  `memberships`               Professional Memberships
  `references`                References
  `interests`                 Interests
  `hobbies`                   Hobbies
  `additional_information`    Additional Information
  `personal_information`      Personal Information
  `military_service`          Military Service
  `availability`              Availability
  `salary_expectations`       Salary Expectations
  `career_preferences`        Career Preferences
  `custom_other`              Other / Unclassified

------------------------------------------------------------------------

# 2. Contact Information

## Canonical meaning

Basic information used to contact or identify the candidate:

-   Full name
-   Phone/mobile number
-   Email
-   Address/location
-   LinkedIn
-   Personal website
-   Portfolio URL

## Common headings

-   Contact
-   Contact Information
-   Contact Details
-   Contact Info
-   Personal Contact
-   Contact Data
-   Candidate Contact
-   Candidate Information
-   Personal Details
-   Personal Information
-   My Details
-   Get In Touch
-   Reach Me
-   How to Contact Me

## Common abbreviations

-   Contact Info
-   Contact Details
-   Personal Info
-   Personal Details

## Arabic / bilingual variants

-   بيانات الاتصال
-   معلومات الاتصال
-   بيانات التواصل
-   معلومات التواصل
-   معلومات شخصية
-   البيانات الشخصية
-   بيانات المرشح
-   معلومات المرشح
-   Contact Information \| بيانات الاتصال
-   Personal Information \| البيانات الشخصية

## Parser notes

Do not assume every occurrence of `Personal Information` is a dedicated
section. In some CVs it may contain:

-   Date of birth
-   Nationality
-   Marital status
-   Gender
-   Address

These fields may appear before the first major CV section without a
heading.

------------------------------------------------------------------------

# 3. Professional Summary

## Canonical meaning

A short professional overview describing the candidate's experience,
specialization, value proposition, or career profile.

## Common headings

-   Professional Summary
-   Professional Profile
-   Professional Overview
-   Career Summary
-   Career Profile
-   Career Overview
-   Executive Summary
-   Executive Profile
-   Summary
-   Summary of Qualifications
-   Qualifications Summary
-   Professional Highlights
-   Career Highlights
-   Candidate Summary
-   Resume Summary
-   CV Summary
-   Profile Summary
-   About Me
-   About
-   About Myself
-   Introduction
-   Personal Profile
-   Personal Statement
-   Professional Statement
-   Career Statement
-   Overview
-   Profile
-   My Profile
-   Who I Am
-   Snapshot
-   At a Glance

## Arabic / bilingual variants

-   الملخص المهني
-   نبذة مهنية
-   الملف المهني
-   نبذة عني
-   نبذة شخصية
-   نبذة مختصرة
-   ملخص الخبرات
-   ملخص المؤهلات
-   الهدف المهني والملخص
-   Professional Summary \| الملخص المهني
-   Profile \| نبذة مهنية

## Parser notes

`Profile`, `Overview`, and `About Me` are highly context-dependent.
Classify them as `professional_summary` when the content is prose
describing the candidate professionally.

------------------------------------------------------------------------

# 4. Career Objective

## Canonical meaning

A statement describing the type of role, career direction, or
professional goal the candidate is seeking.

## Common headings

-   Career Objective
-   Career Goal
-   Career Goals
-   Professional Objective
-   Professional Goal
-   Career Aim
-   Objective
-   Job Objective
-   Employment Objective
-   Career Aspiration
-   Career Aspirations
-   Professional Aspirations
-   Career Direction
-   Career Target
-   Career Plan
-   My Objective
-   My Career Objective
-   Objective Statement
-   Career Objective Statement

## Arabic / bilingual variants

-   الهدف الوظيفي
-   الهدف المهني
-   الأهداف المهنية
-   هدفي المهني
-   الهدف من العمل
-   التوجه المهني
-   الطموح المهني
-   Career Objective \| الهدف الوظيفي

## Parser notes

Do not automatically classify every `Objective` heading as a career
objective. It may occur in academic CVs, project documents, or
job-specific resumes.

------------------------------------------------------------------------

# 5. Professional Experience

## Canonical meaning

Paid professional employment, normally organized by employer, position,
and dates.

## Common headings

-   Professional Experience
-   Professional Experiences
-   Work Experience
-   Work Experiences
-   Employment Experience
-   Employment History
-   Employment Record
-   Career History
-   Career Experience
-   Career Record
-   Work History
-   Work Record
-   Job History
-   Job Experience
-   Professional Background
-   Work Background
-   Employment Background
-   Career Background
-   Experience
-   Experiences
-   Relevant Experience
-   Relevant Work Experience
-   Relevant Professional Experience
-   Industry Experience
-   Corporate Experience
-   Occupational Experience
-   Previous Employment
-   Previous Experience
-   Past Experience
-   Experience History
-   Experience & Achievements
-   Work & Experience
-   Professional Journey
-   Career Journey

## Common abbreviations

-   Work Exp.
-   Work Exp
-   Prof. Experience
-   Prof Experience
-   Employment Hist.
-   Career Hist.

## Arabic / bilingual variants

-   الخبرات العملية
-   الخبرة العملية
-   الخبرات المهنية
-   الخبرة المهنية
-   الخبرات الوظيفية
-   الخبرة الوظيفية
-   السجل الوظيفي
-   التاريخ الوظيفي
-   المسار المهني
-   الخبرات السابقة
-   الخبرة العملية والمهنية
-   Work Experience \| الخبرة العملية
-   Professional Experience \| الخبرات المهنية

## Parser notes

`Experience` alone is one of the most important generic headings to
recognize.

Do not confuse:

-   `Teaching Experience` → potentially professional experience, but may
    deserve a subtype.
-   `Sales Experience` → professional experience unless it appears under
    skills.
-   `Project Experience` → may be project history rather than
    employment.
-   `Years of Experience` → usually a profile fact, not a section.

------------------------------------------------------------------------

# 6. Education

## Canonical meaning

Formal academic education such as university, college, school, diploma,
or degree programs.

## Common headings

-   Education
-   Educational Background
-   Education Background
-   Academic Background
-   Academic History
-   Academic Experience
-   Educational History
-   Educational Qualifications
-   Academic Qualifications
-   Academic Credentials
-   Academic Record
-   Educational Record
-   Degrees
-   Academic Degrees
-   Degrees & Education
-   Education & Qualifications
-   Education and Qualifications
-   Qualifications
-   Academic Qualifications

## Arabic / bilingual variants

-   التعليم
-   المؤهلات التعليمية
-   المؤهلات الأكاديمية
-   الخلفية التعليمية
-   الخلفية الأكاديمية
-   التاريخ التعليمي
-   التاريخ الأكاديمي
-   المؤهلات
-   الشهادات الأكاديمية
-   الدرجات العلمية
-   التعليم والمؤهلات
-   Education \| التعليم
-   Academic Background \| الخلفية الأكاديمية

## Parser notes

`Qualifications` is ambiguous. It may contain degrees, professional
certifications, skills, or a mixture.

Use content-based classification where possible.

------------------------------------------------------------------------

# 7. Skills --- General

## Canonical meaning

Candidate capabilities, competencies, or areas of proficiency.

## Common headings

-   Skills
-   Skill Set
-   Skillset
-   Skills Summary
-   Skills Profile
-   Core Skills
-   Key Skills
-   Main Skills
-   Essential Skills
-   Relevant Skills
-   Professional Skills
-   Competencies
-   Core Competencies
-   Key Competencies
-   Professional Competencies
-   Areas of Competence
-   Areas of Expertise
-   Expertise
-   Areas of Knowledge
-   Capabilities
-   Core Capabilities
-   Strengths
-   Key Strengths
-   Professional Strengths
-   Abilities
-   Key Abilities
-   Proficiencies
-   Core Proficiencies
-   Specialties
-   Specializations
-   Expertise & Skills
-   Skills & Competencies
-   Competencies & Skills

## Arabic / bilingual variants

-   المهارات
-   المهارات الأساسية
-   المهارات المهنية
-   المهارات الشخصية
-   الكفاءات
-   الكفاءات الأساسية
-   القدرات
-   نقاط القوة
-   الخبرات والمهارات
-   المهارات والكفاءات
-   مجالات الخبرة
-   مجالات التخصص
-   Skills \| المهارات
-   Core Competencies \| الكفاءات الأساسية

------------------------------------------------------------------------

# 8. Technical Skills

## Canonical meaning

Technical, software, engineering, IT, tools, systems, platforms, or
technical domain capabilities.

## Common headings

-   Technical Skills
-   Technical Skillset
-   Technical Competencies
-   Technical Expertise
-   Technical Proficiencies
-   Technical Knowledge
-   Technical Capabilities
-   Technology Skills
-   IT Skills
-   Information Technology Skills
-   Computer Skills
-   Computer Proficiency
-   Computer Knowledge
-   Software Skills
-   Software Proficiency
-   Digital Skills
-   Digital Competencies
-   Systems Skills
-   Systems Knowledge
-   Tools & Technologies
-   Technologies
-   Technical Tools
-   Technical Background
-   Technical Qualifications
-   Technical Abilities

## Arabic / bilingual variants

-   المهارات التقنية
-   المهارات الفنية
-   المهارات التكنولوجية
-   مهارات تكنولوجيا المعلومات
-   مهارات الحاسب الآلي
-   مهارات الكمبيوتر
-   الخبرات التقنية
-   الكفاءات التقنية
-   المعرفة التقنية
-   الأدوات والتقنيات
-   Technical Skills \| المهارات التقنية

## Examples of content

-   Excel
-   SQL
-   Python
-   Laravel
-   AutoCAD
-   SAP
-   Odoo
-   Microsoft Office
-   Power BI
-   Adobe Photoshop

------------------------------------------------------------------------

# 9. Soft Skills / Interpersonal Skills

## Common headings

-   Soft Skills
-   Interpersonal Skills
-   Personal Skills
-   Personal Competencies
-   Behavioral Skills
-   People Skills
-   Social Skills
-   Communication Skills
-   Leadership Skills
-   Management Skills
-   Teamwork Skills
-   Collaboration Skills
-   Personal Strengths
-   Core Personal Skills
-   Transferable Skills
-   Essential Soft Skills

## Arabic / bilingual variants

-   المهارات الشخصية
-   المهارات الناعمة
-   المهارات السلوكية
-   المهارات الاجتماعية
-   مهارات التواصل
-   مهارات الاتصال
-   مهارات القيادة
-   مهارات العمل الجماعي
-   المهارات الإدارية
-   Soft Skills \| المهارات الشخصية

## Parser notes

Some CVs place communication, leadership, and teamwork under a general
`Skills` section. They should not necessarily be forced into a separate
soft-skills section unless the source clearly separates them.

------------------------------------------------------------------------

# 10. Languages

## Canonical meaning

Human languages and proficiency levels.

## Common headings

-   Languages
-   Language Skills
-   Language Proficiency
-   Languages & Proficiency
-   Language Proficiencies
-   Foreign Languages
-   Foreign Language Skills
-   Linguistic Skills
-   Linguistic Abilities
-   Language Competencies
-   Language Knowledge
-   Spoken Languages
-   Languages Known
-   Languages Spoken
-   Languages & Communication

## Arabic / bilingual variants

-   اللغات
-   اللغات الأجنبية
-   مهارات اللغة
-   المهارات اللغوية
-   اللغات ومستويات الإتقان
-   إجادة اللغات
-   اللغات التي أتقنها
-   Languages \| اللغات

## Parser notes

Language names can appear without a heading. Examples:

-   Arabic --- Native
-   English --- Fluent
-   French --- Intermediate

A robust parser should detect language entities even if they appear in a
general skills section.

------------------------------------------------------------------------

# 11. Certifications

## Canonical meaning

Professional certificates or credentials earned by the candidate.

## Common headings

-   Certifications
-   Certificates
-   Professional Certifications
-   Professional Certificates
-   Certifications & Licenses
-   Certificates & Licenses
-   Credentials
-   Professional Credentials
-   Industry Certifications
-   Technical Certifications
-   Certification
-   Licenses & Certifications
-   Certifications and Credentials

## Arabic / bilingual variants

-   الشهادات
-   الشهادات المهنية
-   الشهادات الاحترافية
-   الاعتمادات المهنية
-   الشهادات والاعتمادات
-   Certifications \| الشهادات المهنية

## Parser notes

`Certificate` can mean:

1.  A professional certification
2.  An academic certificate
3.  A training/course completion certificate

Content and issuing organization can help distinguish these.

------------------------------------------------------------------------

# 12. Licenses

## Common headings

-   Licenses
-   Licences
-   Professional Licenses
-   Professional Licence
-   Licenses & Certifications
-   Licences & Certifications
-   Certifications & Licenses
-   Credentials
-   Registrations
-   Professional Registrations
-   Memberships & Licenses

## Arabic / bilingual variants

-   التراخيص
-   الرخص المهنية
-   التراخيص المهنية
-   التسجيلات المهنية
-   Licenses \| التراخيص

## Examples

-   Driving License
-   Professional Engineer License
-   Medical License
-   CPA License

------------------------------------------------------------------------

# 13. Training

## Common headings

-   Training
-   Training Courses
-   Training & Development
-   Professional Training
-   Professional Development
-   Training Programs
-   Training & Courses
-   Courses & Training
-   Workshops & Training
-   Workshops
-   Seminars & Training
-   Development Programs
-   Learning & Development
-   Continuing Education
-   Continuing Professional Development
-   CPD
-   Training History
-   Training Record

## Arabic / bilingual variants

-   التدريب
-   الدورات التدريبية
-   التدريب والتطوير
-   التدريب المهني
-   البرامج التدريبية
-   الدورات والتدريب
-   التطوير المهني
-   التعليم المستمر
-   التدريب والتطوير المهني
-   Training \| التدريب

## Parser notes

`Training` is frequently mixed with certifications. A parser should
preserve the original distinction when possible.

------------------------------------------------------------------------

# 14. Courses

## Common headings

-   Courses
-   Training Courses
-   Courses Completed
-   Completed Courses
-   Relevant Courses
-   Professional Courses
-   Academic Courses
-   Short Courses
-   Online Courses
-   Additional Courses
-   Selected Courses
-   Courses & Training
-   Training & Courses
-   Continuing Education
-   Workshops & Courses

## Arabic / bilingual variants

-   الدورات
-   الدورات التدريبية
-   الدورات التي تم الحصول عليها
-   الدورات المهنية
-   الدورات الإضافية
-   الدورات ذات الصلة
-   الدورات والتدريب
-   Courses \| الدورات

------------------------------------------------------------------------

# 15. Projects

## Common headings

-   Projects
-   Project Experience
-   Project History
-   Project Portfolio
-   Selected Projects
-   Key Projects
-   Major Projects
-   Relevant Projects
-   Professional Projects
-   Academic Projects
-   Personal Projects
-   University Projects
-   Featured Projects
-   Project Highlights
-   Projects & Achievements
-   Selected Work
-   Portfolio Projects

## Arabic / bilingual variants

-   المشاريع
-   خبرات المشاريع
-   مشروعات
-   المشاريع الرئيسية
-   أهم المشاريع
-   المشاريع المختارة
-   المشاريع الشخصية
-   المشاريع الأكاديمية
-   مشاريع التخرج
-   مشاريع مختارة
-   Projects \| المشاريع

## Parser notes

`Project Experience` may contain employment-like project assignments. Do
not automatically merge it into employment history.

------------------------------------------------------------------------

# 16. Portfolio

## Common headings

-   Portfolio
-   Work Portfolio
-   Professional Portfolio
-   Project Portfolio
-   Portfolio & Projects
-   Selected Work
-   Work Samples
-   Samples of Work
-   Professional Work
-   Creative Portfolio
-   Online Portfolio

## Arabic / bilingual variants

-   معرض الأعمال
-   ملف الأعمال
-   نماذج الأعمال
-   أعمال سابقة
-   نماذج من الأعمال
-   Portfolio \| معرض الأعمال

------------------------------------------------------------------------

# 17. Achievements

## Common headings

-   Achievements
-   Key Achievements
-   Major Achievements
-   Professional Achievements
-   Career Achievements
-   Notable Achievements
-   Significant Achievements
-   Accomplishments
-   Key Accomplishments
-   Professional Accomplishments
-   Career Highlights
-   Professional Highlights
-   Highlights
-   Successes
-   Milestones
-   Key Contributions
-   Contributions
-   Impact

## Arabic / bilingual variants

-   الإنجازات
-   أهم الإنجازات
-   الإنجازات المهنية
-   الإنجازات الوظيفية
-   أبرز الإنجازات
-   النجاحات
-   المساهمات
-   الإنجازات الرئيسية
-   Achievements \| الإنجازات

------------------------------------------------------------------------

# 18. Awards & Honors

## Common headings

-   Awards
-   Awards & Honors
-   Honors
-   Honours
-   Honors & Awards
-   Awards and Recognition
-   Recognition
-   Professional Recognition
-   Distinctions
-   Achievements & Awards
-   Awards & Achievements
-   Prizes
-   Academic Honors
-   Academic Awards
-   Professional Awards

## Arabic / bilingual variants

-   الجوائز
-   الجوائز والتكريمات
-   التكريمات
-   الجوائز والتقدير
-   التقدير والجوائز
-   الجوائز المهنية
-   الجوائز الأكاديمية
-   Awards \| الجوائز

------------------------------------------------------------------------

# 19. Volunteer Experience

## Common headings

-   Volunteer Experience
-   Volunteering
-   Volunteer Work
-   Community Service
-   Community Involvement
-   Social Service
-   Voluntary Work
-   Volunteer Activities
-   Community Activities
-   Civic Engagement
-   Nonprofit Experience
-   NGO Experience

## Arabic / bilingual variants

-   العمل التطوعي
-   الخبرات التطوعية
-   التطوع
-   الخدمة المجتمعية
-   المشاركة المجتمعية
-   الأنشطة التطوعية
-   Volunteer Experience \| الخبرات التطوعية

------------------------------------------------------------------------

# 20. Internships

## Common headings

-   Internships
-   Internship Experience
-   Internship
-   Internship History
-   Student Internships
-   Training & Internships
-   Industrial Training
-   Summer Training
-   Practical Training
-   Cooperative Training
-   Co-op Experience
-   Trainee Experience
-   Graduate Training

## Arabic / bilingual variants

-   التدريب العملي
-   التدريب الصيفي
-   التدريب التعاوني
-   فترة التدريب
-   الخبرات التدريبية
-   التدريب الصناعي
-   Internship \| التدريب

## Parser notes

Internships may be part of `Experience` rather than a standalone
section. Preserve the internship nature when the source explicitly
identifies it.

------------------------------------------------------------------------

# 21. Publications

## Common headings

-   Publications
-   Published Work
-   Publications & Research
-   Research Publications
-   Academic Publications
-   Papers
-   Published Papers
-   Articles
-   Journal Articles
-   Books
-   Book Chapters
-   Writing
-   Selected Publications
-   Selected Works

## Arabic / bilingual variants

-   المنشورات
-   الأبحاث المنشورة
-   المنشورات العلمية
-   الأبحاث
-   المقالات المنشورة
-   المؤلفات
-   Publications \| المنشورات

------------------------------------------------------------------------

# 22. Research

## Common headings

-   Research
-   Research Experience
-   Research Interests
-   Research Projects
-   Research Background
-   Research Work
-   Academic Research
-   Research Activities
-   Research Profile
-   Areas of Research
-   Research & Publications

## Arabic / bilingual variants

-   البحث العلمي
-   الخبرات البحثية
-   الأبحاث
-   الاهتمامات البحثية
-   المشاريع البحثية
-   مجالات البحث
-   Research \| البحث العلمي

------------------------------------------------------------------------

# 23. Conferences / Seminars / Events

## Common headings

-   Conferences
-   Conferences Attended
-   Conference Participation
-   Seminars
-   Seminars Attended
-   Events
-   Professional Events
-   Industry Events
-   Speaking Engagements
-   Presentations
-   Conference Presentations
-   Workshops
-   Professional Activities

## Arabic / bilingual variants

-   المؤتمرات
-   المؤتمرات التي تم حضورها
-   الندوات
-   الفعاليات
-   الفعاليات المهنية
-   المشاركة في المؤتمرات
-   العروض التقديمية
-   Conferences \| المؤتمرات

------------------------------------------------------------------------

# 24. Professional Memberships

## Common headings

-   Memberships
-   Professional Memberships
-   Professional Affiliations
-   Affiliations
-   Associations
-   Professional Associations
-   Organizational Memberships
-   Memberships & Affiliations
-   Professional Organizations
-   Societies
-   Memberships & Associations

## Arabic / bilingual variants

-   العضويات
-   العضويات المهنية
-   الجمعيات المهنية
-   العضويات والجمعيات
-   الانتماءات المهنية
-   عضوية الجمعيات المهنية
-   Memberships \| العضويات

------------------------------------------------------------------------

# 25. References

## Common headings

-   References
-   Professional References
-   References Available
-   References Upon Request
-   Referees
-   Professional Referees
-   References & Recommendations
-   Recommendations
-   Professional Recommendations
-   Testimonials
-   Testimonial

## Arabic / bilingual variants

-   المراجع
-   التزكيات
-   التوصيات
-   المراجع المهنية
-   التوصيات المهنية
-   References \| المراجع

## Parser notes

Some CVs provide actual reference contacts. Others simply state
`References available upon request`. The parser should distinguish
between:

-   `references_section`
-   `reference_contacts`
-   `references_available_on_request`

------------------------------------------------------------------------

# 26. Interests & Hobbies

## Common headings

-   Interests
-   Personal Interests
-   Professional Interests
-   Hobbies
-   Hobbies & Interests
-   Interests & Hobbies
-   Personal Interests & Hobbies
-   Activities
-   Personal Activities
-   Extracurricular Activities
-   Leisure Activities
-   Outside Interests
-   Areas of Interest

## Arabic / bilingual variants

-   الاهتمامات
-   الهوايات
-   الهوايات والاهتمامات
-   الاهتمامات الشخصية
-   الأنشطة
-   الأنشطة الشخصية
-   الأنشطة اللامنهجية
-   Interests \| الاهتمامات

## Parser notes

`Activities` is ambiguous and may refer to professional, volunteer,
academic, or extracurricular activities.

------------------------------------------------------------------------

# 27. Personal Information

## Common headings

-   Personal Information
-   Personal Details
-   Personal Data
-   Personal Profile
-   Personal Background
-   Candidate Information
-   Candidate Details
-   Bio Data
-   Biodata
-   Personal Particulars
-   Basic Information
-   General Information

## Arabic / bilingual variants

-   البيانات الشخصية
-   المعلومات الشخصية
-   البيانات الأساسية
-   بيانات المرشح
-   معلومات المرشح
-   بيانات شخصية
-   معلومات عامة
-   Personal Details \| البيانات الشخصية

## Possible content

-   Date of birth
-   Nationality
-   Gender
-   Marital status
-   Address
-   Driving license
-   Military status

## Parser warning

Avoid treating all personal fields as mandatory. Many modern CVs
intentionally omit them.

------------------------------------------------------------------------

# 28. Military Service

## Common headings

-   Military Service
-   Military Status
-   Military Service Status
-   Military Duty
-   Military Record
-   National Service
-   Conscription Status
-   Military Obligation
-   Military Experience

## Arabic / bilingual variants

-   الخدمة العسكرية
-   الموقف من التجنيد
-   موقف التجنيد
-   حالة التجنيد
-   الخدمة الوطنية
-   الموقف من الخدمة العسكرية
-   التجنيد
-   Military Status \| الموقف من التجنيد

## Common Egyptian CV values

-   Completed
-   Exempted
-   Postponed
-   Not Applicable
-   Exemption
-   Deferred

Arabic examples:

-   أدى الخدمة العسكرية
-   إعفاء نهائي
-   إعفاء مؤقت
-   مؤجل
-   غير مطلوب

------------------------------------------------------------------------

# 29. Availability

## Common headings

-   Availability
-   Start Date
-   Available From
-   Joining Date
-   Notice Period
-   Availability Date
-   Earliest Start Date
-   Expected Start Date
-   Joining Availability
-   Work Availability

## Arabic / bilingual variants

-   التفرغ
-   تاريخ التفرغ
-   تاريخ البدء
-   تاريخ الانضمام
-   فترة الإخطار
-   مدة الإخطار
-   موعد بدء العمل
-   Availability \| التفرغ

------------------------------------------------------------------------

# 30. Salary Expectations

## Common headings

-   Salary Expectations
-   Expected Salary
-   Salary Requirement
-   Compensation Expectations
-   Compensation Requirement
-   Expected Compensation
-   Desired Salary
-   Desired Compensation
-   Pay Expectations
-   Remuneration Expectations
-   Compensation
-   Salary Range

## Arabic / bilingual variants

-   الراتب المتوقع
-   الراتب المطلوب
-   توقعات الراتب
-   الراتب المستهدف
-   الأجر المتوقع
-   توقعات الأجر
-   متطلبات الراتب
-   الراتب والبدلات

## Parser notes

This information may appear in an application form rather than the CV
itself.

------------------------------------------------------------------------

# 31. Career Preferences

## Common headings

-   Career Preferences
-   Job Preferences
-   Employment Preferences
-   Career Interests
-   Job Interests
-   Desired Position
-   Desired Job
-   Desired Role
-   Target Position
-   Target Role
-   Preferred Position
-   Preferred Role
-   Preferred Job
-   Career Interests & Goals

## Arabic / bilingual variants

-   التفضيلات الوظيفية
-   التفضيلات المهنية
-   الوظيفة المطلوبة
-   الوظيفة المستهدفة
-   المسمى الوظيفي المطلوب
-   المجال الوظيفي المطلوب
-   الاهتمامات المهنية
-   Career Preferences \| التفضيلات الوظيفية

------------------------------------------------------------------------

# 32. Additional Information

## Common headings

-   Additional Information
-   Additional Details
-   Additional Skills
-   Other Information
-   Other Details
-   Further Information
-   Miscellaneous
-   General Information
-   Additional Qualifications
-   Other Qualifications
-   Additional Experience
-   Other Experience
-   Additional Activities
-   Notes
-   Comments

## Arabic / bilingual variants

-   معلومات إضافية
-   بيانات إضافية
-   تفاصيل إضافية
-   معلومات أخرى
-   بيانات أخرى
-   مؤهلات إضافية
-   ملاحظات
-   Additional Information \| معلومات إضافية

## Parser notes

This should usually remain a catch-all category rather than being
aggressively classified.

------------------------------------------------------------------------

# 33. Common Combined Headings

Candidates frequently combine multiple sections into one heading.

## Examples

-   Education & Qualifications
-   Education & Training
-   Education & Certifications
-   Skills & Competencies
-   Skills & Expertise
-   Skills & Qualifications
-   Certifications & Licenses
-   Training & Certifications
-   Courses & Certifications
-   Experience & Skills
-   Experience & Achievements
-   Experience & Education
-   Projects & Experience
-   Projects & Achievements
-   Awards & Achievements
-   Interests & Hobbies
-   Memberships & Affiliations
-   Publications & Research
-   Research & Publications
-   Languages & Skills
-   Professional Summary & Objective
-   Career Objective & Summary
-   Personal Information & Contact Details
-   Contact Information & Personal Details
-   Education, Training & Certifications
-   Professional Experience, Skills & Achievements

## Parser strategy

A combined heading can be handled in one of two ways:

1.  **Multi-label classification**\
    Map one heading to multiple canonical categories.

2.  **Container classification**\
    Keep the section as a combined section and classify individual
    entries underneath it.

For ATS/resume parsing, multi-label classification is usually more
useful when the content clearly contains separate subgroups.

------------------------------------------------------------------------

# 34. Very Short / Generic Headings

These headings occur frequently and require contextual interpretation.

  Heading          Possible Interpretation
  ---------------- --------------------------------------------
  Profile          Professional summary / personal profile
  Summary          Professional summary
  Objective        Career objective
  Experience       Professional experience
  Work             Work experience
  Education        Education
  Qualifications   Education / certifications / skills
  Skills           Skills
  Expertise        Skills / specialization
  Background       Education / experience / profile
  History          Employment history / academic history
  Training         Training / courses
  Activities       Volunteer / extracurricular / professional
  Projects         Projects
  Highlights       Achievements / summary
  Information      Additional/personal/contact information
  Details          Personal/contact information
  About            Professional summary
  Career           Career profile / experience
  Achievements     Achievements
  Awards           Awards
  Interests        Interests
  References       References

------------------------------------------------------------------------

# 35. Alternative Spelling Variations

A parser should normally normalize these spelling variants:

## British vs American English

-   Organisation / Organization
-   Organisational / Organizational
-   Specialisation / Specialization
-   Licence / License
-   Programme / Program
-   Honour / Honor
-   Centre / Center
-   Behaviour / Behavior
-   Analyse / Analyze
-   Summarise / Summarize

These differences may occur inside headings as well as content.

------------------------------------------------------------------------

# 36. Capitalization Variations

Treat capitalization as irrelevant for heading classification.

Examples:

-   Professional Experience
-   PROFESSIONAL EXPERIENCE
-   professional experience
-   Professional experience
-   PROFESSIONAL Experience
-   professional EXPERIENCE

All should normalize to:

`professional_experience`

------------------------------------------------------------------------

# 37. Punctuation Variations

The parser should normally ignore decorative punctuation.

Examples:

-   Professional Experience:
-   Professional Experience -
-   Professional Experience ---
-   Professional Experience \|
-   Professional Experience /
-   Professional Experience:
-   \*\* PROFESSIONAL EXPERIENCE \*\*
-   PROFESSIONAL EXPERIENCE
-   PROFESSIONAL-EXPERIENCE
-   PROFESSIONAL_EXPERIENCE

Recommended normalization:

1.  Convert to lowercase.
2.  Normalize Unicode.
3.  Remove decorative punctuation.
4.  Collapse whitespace.
5.  Compare against normalized aliases.

------------------------------------------------------------------------

# 38. Numbered Section Titles

CVs may number sections.

Examples:

-   1.  Professional Summary
-   1.  Professional Summary
-   01 - Professional Experience
-   2.  Education
-   03 \| Skills
-   Section 1: Profile
-   I. Professional Experience
-   II. Education
-   III. Skills

The numeric prefix should generally be removed before classification.

------------------------------------------------------------------------

# 39. Decorative Section Titles

Common visual formatting should not prevent recognition.

Examples:

-   `★ PROFESSIONAL EXPERIENCE`
-   `● EDUCATION`
-   `◆ SKILLS`
-   `— EXPERIENCE —`
-   `| EDUCATION |`
-   `••• PROFESSIONAL SUMMARY •••`
-   `>> EXPERIENCE`
-   `EDUCATION →`
-   `### SKILLS`

The parser should separate presentation from semantic text.

------------------------------------------------------------------------

# 40. Arabic Heading Variations

Arabic CVs often use different wording for the same section.

## Professional Experience

-   الخبرة العملية
-   الخبرات العملية
-   الخبرة المهنية
-   الخبرات المهنية
-   الخبرات الوظيفية
-   الخبرة الوظيفية
-   الخبرات السابقة
-   الخبرات العملية السابقة
-   التاريخ الوظيفي
-   السجل الوظيفي
-   المسار المهني

## Education

-   التعليم
-   المؤهلات التعليمية
-   المؤهلات الأكاديمية
-   المؤهل الدراسي
-   المؤهلات الدراسية
-   الخلفية التعليمية
-   الخلفية الأكاديمية
-   التعليم والمؤهلات

## Skills

-   المهارات
-   المهارات الأساسية
-   المهارات المهنية
-   المهارات الشخصية
-   المهارات الفنية
-   المهارات التقنية
-   القدرات
-   الكفاءات
-   الكفاءات الأساسية

## Languages

-   اللغات
-   اللغات الأجنبية
-   المهارات اللغوية
-   إجادة اللغات
-   اللغات التي أتقنها

## Certifications

-   الشهادات
-   الشهادات المهنية
-   الشهادات الاحترافية
-   الاعتمادات
-   الاعتمادات المهنية

## Training

-   التدريب
-   الدورات التدريبية
-   الدورات
-   التدريب المهني
-   البرامج التدريبية
-   التطوير المهني

## Projects

-   المشاريع
-   المشروعات
-   المشاريع الرئيسية
-   المشاريع المختارة
-   مشاريع التخرج
-   المشاريع الشخصية

## Achievements

-   الإنجازات
-   أهم الإنجازات
-   أبرز الإنجازات
-   الإنجازات المهنية
-   النجاحات

## Awards

-   الجوائز
-   الجوائز والتكريمات
-   التكريمات
-   الجوائز المهنية
-   الجوائز الأكاديمية

## Volunteer Work

-   العمل التطوعي
-   الخبرات التطوعية
-   التطوع
-   الخدمة المجتمعية
-   المشاركة المجتمعية

## References

-   المراجع
-   التوصيات
-   التزكيات
-   المراجع المهنية

------------------------------------------------------------------------

# 41. Egyptian / Arabic-English Mixed CV Headings

Egyptian CVs frequently mix English and Arabic in the same document.

Examples:

-   Professional Experience \| الخبرات العملية
-   Work Experience / الخبرة العملية
-   Education - المؤهلات الدراسية
-   Skills \| المهارات
-   Technical Skills / المهارات الفنية
-   Languages \| اللغات
-   Courses & Training \| الدورات والتدريب
-   Certifications \| الشهادات
-   Personal Information \| البيانات الشخصية
-   Military Status \| موقف التجنيد
-   References \| المراجع
-   Career Objective \| الهدف الوظيفي
-   Professional Summary \| نبذة مهنية

A parser should support mixed-script headings without requiring the
entire document to be in one language.

------------------------------------------------------------------------

# 42. Common CV Section Ordering

There is no universal order, but these patterns are common.

## Experienced Professional

1.  Contact Information
2.  Professional Summary
3.  Professional Experience
4.  Education
5.  Skills
6.  Certifications
7.  Languages
8.  Additional Information

## Graduate / Entry Level

1.  Contact Information
2.  Career Objective
3.  Education
4.  Internships
5.  Projects
6.  Skills
7.  Training / Courses
8.  Certifications
9.  Languages
10. Activities

## Academic CV

1.  Contact Information
2.  Profile
3.  Education
4.  Research
5.  Publications
6.  Teaching Experience
7.  Conferences
8.  Grants
9.  Awards
10. Professional Memberships
11. References

## Technical CV

1.  Contact Information
2.  Professional Summary
3.  Technical Skills
4.  Professional Experience
5.  Projects
6.  Education
7.  Certifications
8.  Languages

------------------------------------------------------------------------

# 43. Section Detection Signals

Heading text alone is not always sufficient.

A parser should consider:

## 43.1 Typography

Useful signals:

-   Larger font size
-   Bold text
-   All caps
-   Underline
-   Horizontal line
-   Different color
-   Heading style
-   Extra whitespace before/after
-   Numbered sections

## 43.2 Position

Common patterns:

-   Section title followed by multiple entries
-   Section title followed by dates
-   Section title at the beginning of a page
-   Repeated heading style throughout the document

## 43.3 Content patterns

### Experience indicators

-   Job title
-   Company name
-   Start/end dates
-   Location
-   Responsibilities
-   Achievements

### Education indicators

-   University
-   College
-   Faculty
-   Degree
-   Bachelor
-   Master
-   PhD
-   GPA
-   Graduation

### Skills indicators

-   Bullet lists
-   Technology names
-   Software names
-   Proficiency levels
-   Competency keywords

### Certifications indicators

-   Certificate name
-   Issuing organization
-   Credential ID
-   Issue date
-   Expiry date

------------------------------------------------------------------------

# 44. Date Patterns Useful for Section Classification

Experience and education sections commonly contain dates such as:

-   2022 - 2025
-   2022--2025
-   2022 / 2025
-   Jan 2022 - Dec 2025
-   January 2022 -- Present
-   01/2022 - Present
-   2022 - Current
-   2022 - Now
-   2022--Present
-   Since 2022
-   2022

Arabic examples:

-   ٢٠٢٢ - ٢٠٢٥
-   من ٢٠٢٢ إلى ٢٠٢٥
-   ٢٠٢٢ - حتى الآن
-   منذ ٢٠٢٢

A robust parser should support both Western and Arabic-Indic numerals.

------------------------------------------------------------------------

# 45. Specialization-Based Experience Headings

Some candidates use the industry or function as the section title.

Examples:

-   Sales Experience
-   Marketing Experience
-   Finance Experience
-   Accounting Experience
-   HR Experience
-   Human Resources Experience
-   Engineering Experience
-   IT Experience
-   Software Development Experience
-   Management Experience
-   Leadership Experience
-   Teaching Experience
-   Consulting Experience
-   Banking Experience
-   Legal Experience
-   Medical Experience
-   Healthcare Experience
-   Manufacturing Experience
-   Retail Experience
-   Customer Service Experience
-   Project Management Experience

## Parser recommendation

If the content represents employment history, map these to:

`professional_experience`

and preserve the specialization as a subtype/tag, e.g.:

``` text
section = professional_experience
specialization = sales
```

------------------------------------------------------------------------

# 46. Role-Specific Skill Headings

Examples:

-   Accounting Skills
-   Finance Skills
-   Sales Skills
-   Marketing Skills
-   Management Skills
-   Leadership Skills
-   Engineering Skills
-   IT Skills
-   Programming Skills
-   Software Development Skills
-   Analytical Skills
-   Research Skills
-   Communication Skills
-   Customer Service Skills
-   Negotiation Skills

Recommended approach:

``` text
section = skills
subtype = accounting
```

Do not create a new top-level section for every specialization.

------------------------------------------------------------------------

# 47. Education Degree Variations

Degree information may appear under:

-   Education
-   Academic Background
-   Qualifications
-   Academic Qualifications
-   Degrees
-   Educational History
-   Academic History

Common degree terms include:

### English

-   High School
-   Secondary School
-   Diploma
-   Higher Diploma
-   Associate Degree
-   Bachelor
-   Bachelor's Degree
-   BSc
-   B.Sc.
-   BA
-   B.A.
-   BBA
-   MBA
-   Master
-   Master's Degree
-   MSc
-   M.Sc.
-   MA
-   M.A.
-   PhD
-   Ph.D.
-   Doctorate
-   Doctoral Degree

### Arabic

-   الثانوية العامة
-   دبلوم
-   دبلوم عالي
-   ليسانس
-   بكالوريوس
-   بكالوريوس إدارة أعمال
-   ماجستير
-   دكتوراه
-   درجة علمية
-   مؤهل جامعي

------------------------------------------------------------------------

# 48. Parser Normalization Pipeline

A practical heading-normalization pipeline can follow these steps:

## Step 1 --- Extract candidate heading

Extract text blocks that visually or structurally resemble headings.

## Step 2 --- Normalize Unicode

Apply Unicode normalization, especially for multilingual documents.

## Step 3 --- Normalize whitespace

Convert repeated spaces, tabs, and line breaks into normalized
whitespace.

## Step 4 --- Remove decorative characters

Strip:

-   bullets
-   arrows
-   section numbers
-   decorative borders
-   repeated punctuation

## Step 5 --- Normalize case

Convert English text to lowercase for matching.

## Step 6 --- Normalize spelling

Support British/American variants where relevant.

## Step 7 --- Normalize bilingual separators

Treat these as separators:

-   `|`
-   `/`
-   `-`
-   `:`
-   `—`
-   `|`
-   line breaks

## Step 8 --- Alias matching

Compare the normalized heading against an alias dictionary.

## Step 9 --- Context classification

If no exact alias is found, examine:

-   nearby text
-   dates
-   entities
-   typography
-   document position
-   keywords

## Step 10 --- Assign confidence

Example:

``` text
canonical_section: professional_experience
confidence: 0.97
matched_heading: "Career History"
match_type: alias
```

------------------------------------------------------------------------

# 49. Recommended Alias Data Structure

A machine-readable representation can look like this:

``` json
{
  "professional_experience": {
    "label": "Professional Experience",
    "aliases": [
      "professional experience",
      "work experience",
      "employment history",
      "career history",
      "work history",
      "career experience",
      "experience",
      "relevant experience",
      "professional background",
      "work background"
    ]
  },
  "education": {
    "label": "Education",
    "aliases": [
      "education",
      "educational background",
      "academic background",
      "academic history",
      "educational history",
      "academic qualifications",
      "educational qualifications",
      "degrees"
    ]
  },
  "skills": {
    "label": "Skills",
    "aliases": [
      "skills",
      "skill set",
      "skillset",
      "core skills",
      "key skills",
      "competencies",
      "core competencies",
      "expertise",
      "proficiencies"
    ]
  }
}
```

------------------------------------------------------------------------

# 50. Recommended Confidence Model

A useful parser can assign confidence based on multiple signals.

  Signal                    Example                      Suggested Weight
  ------------------------- -------------------------- ------------------
  Exact canonical heading   `Education`                         Very High
  Exact alias               `Academic Background`               Very High
  Bilingual alias           `Education \| التعليم`              Very High
  Close lexical match       `Professional Exp.`                      High
  Section context           Dates + employers                        High
  Typography                Large/bold heading                     Medium
  Document position         Typical section position           Low-Medium
  Content keywords          `Bachelor`, `University`               Medium
  Generic heading           `Background`                              Low

Do not rely on a single signal when the heading is generic.

------------------------------------------------------------------------

# 51. Ambiguous Terms Requiring Context

These terms should not be blindly mapped:

-   Profile
-   Summary
-   Background
-   Qualifications
-   Experience
-   History
-   Activities
-   Training
-   Courses
-   Certificates
-   Credentials
-   Expertise
-   Highlights
-   Information
-   Details
-   Career
-   Work
-   Achievements
-   Professional
-   Skills

For example:

`Professional`

could mean:

-   Professional Summary
-   Professional Experience
-   Professional Skills
-   Professional Memberships
-   Professional Certifications

The content underneath is required to determine the correct
classification.

------------------------------------------------------------------------

# 52. Sections That May Be Embedded Without Headings

A CV parser should also recognize information even when no explicit
section title exists.

Examples:

## Contact information

``` text
Mahmoud Youssef
Cairo, Egypt
+20 xxx xxx xxxx
email@example.com
linkedin.com/in/...
```

## Experience

``` text
Senior Financial Consultant
SQUAD Business Consulting
2021 - Present
```

## Education

``` text
Bachelor of Commerce
Cairo University
2012
```

## Skills

``` text
Excel | Power BI | Financial Modeling | Odoo
```

The absence of a heading should not prevent entity extraction.

------------------------------------------------------------------------

# 53. Recommended Canonical Mapping Rules

1.  Treat case as irrelevant.
2.  Ignore decorative punctuation.
3.  Remove numbering before matching.
4.  Support singular/plural variations.
5.  Support abbreviations.
6.  Support British/American spelling.
7.  Support English-Arabic bilingual headings.
8.  Support Arabic-only headings.
9.  Preserve the original heading in the extracted data.
10. Store the normalized canonical section separately.
11. Use context for ambiguous headings.
12. Allow one source heading to map to multiple categories when
    appropriate.
13. Preserve section order.
14. Preserve repeated sections.
15. Do not assume every CV follows a standard order.
16. Do not discard unknown headings.
17. Store unknown headings as `custom_other` until further
    classification.
18. Separate section classification from entity extraction.
19. Preserve confidence scores.
20. Keep an alias dictionary extensible.

------------------------------------------------------------------------

# 54. Suggested Parsed Output

A useful internal representation could be:

``` json
{
  "section_id": "professional_experience",
  "original_heading": "Career History",
  "normalized_heading": "career history",
  "language": "en",
  "confidence": 0.98,
  "match_type": "alias",
  "order": 3,
  "content": []
}
```

For bilingual headings:

``` json
{
  "section_id": "education",
  "original_heading": "Education | المؤهلات الدراسية",
  "normalized_heading": "education",
  "language": "en+ar",
  "confidence": 0.99,
  "match_type": "bilingual_alias",
  "order": 4,
  "content": []
}
```

------------------------------------------------------------------------

# 55. Important Distinction: Heading vs Content

A good CV parser should not only ask:

> "What does this heading say?"

It should also ask:

> "What kind of information follows this heading?"

For example:

``` text
Qualifications

MBA — Cairo University
B.Sc. Accounting — Ain Shams University
```

This is probably `education`.

But:

``` text
Qualifications

PMP
CFA Level II
Microsoft Certified: Azure Fundamentals
```

This is probably `certifications`.

And:

``` text
Qualifications

Financial Analysis
Budgeting
Financial Modeling
```

This is probably `skills`.

Therefore:

**Heading classification + content classification** is more reliable
than heading matching alone.

------------------------------------------------------------------------

# 56. Minimal High-Value Alias Set

If implementation needs a smaller initial dictionary, start with these:

``` text
CONTACT
CONTACT INFORMATION
CONTACT DETAILS
PERSONAL INFORMATION
PERSONAL DETAILS

SUMMARY
PROFESSIONAL SUMMARY
PROFILE
PROFESSIONAL PROFILE
CAREER SUMMARY
EXECUTIVE SUMMARY
ABOUT ME

OBJECTIVE
CAREER OBJECTIVE
PROFESSIONAL OBJECTIVE

EXPERIENCE
WORK EXPERIENCE
PROFESSIONAL EXPERIENCE
EMPLOYMENT HISTORY
WORK HISTORY
CAREER HISTORY
RELEVANT EXPERIENCE

EDUCATION
EDUCATIONAL BACKGROUND
ACADEMIC BACKGROUND
ACADEMIC HISTORY
QUALIFICATIONS
DEGREES

SKILLS
KEY SKILLS
CORE SKILLS
SKILL SET
SKILLSET
COMPETENCIES
CORE COMPETENCIES
EXPERTISE
PROFICIENCIES

TECHNICAL SKILLS
IT SKILLS
COMPUTER SKILLS
SOFTWARE SKILLS
DIGITAL SKILLS
TECHNICAL EXPERTISE

SOFT SKILLS
INTERPERSONAL SKILLS
PERSONAL SKILLS
COMMUNICATION SKILLS
LEADERSHIP SKILLS

LANGUAGES
LANGUAGE SKILLS
LANGUAGE PROFICIENCY

CERTIFICATIONS
CERTIFICATES
PROFESSIONAL CERTIFICATIONS
CREDENTIALS
LICENSES
LICENSES & CERTIFICATIONS

TRAINING
TRAINING COURSES
PROFESSIONAL TRAINING
PROFESSIONAL DEVELOPMENT
COURSES
RELEVANT COURSES

PROJECTS
PROJECT EXPERIENCE
SELECTED PROJECTS
PERSONAL PROJECTS
ACADEMIC PROJECTS

ACHIEVEMENTS
KEY ACHIEVEMENTS
ACCOMPLISHMENTS
CAREER HIGHLIGHTS

AWARDS
HONORS
AWARDS & HONORS
RECOGNITION

VOLUNTEER EXPERIENCE
VOLUNTEERING
VOLUNTEER WORK
COMMUNITY SERVICE

INTERNSHIPS
INTERNSHIP EXPERIENCE
PRACTICAL TRAINING

PUBLICATIONS
PUBLISHED WORK
RESEARCH
RESEARCH EXPERIENCE

CONFERENCES
SEMINARS
PRESENTATIONS

MEMBERSHIPS
PROFESSIONAL MEMBERSHIPS
AFFILIATIONS
PROFESSIONAL AFFILIATIONS

REFERENCES
PROFESSIONAL REFERENCES
REFERENCES AVAILABLE

INTERESTS
HOBBIES
INTERESTS & HOBBIES

MILITARY SERVICE
MILITARY STATUS
MILITARY RECORD

AVAILABILITY
NOTICE PERIOD
JOINING DATE

SALARY EXPECTATIONS
EXPECTED SALARY
DESIRED SALARY

ADDITIONAL INFORMATION
OTHER INFORMATION
ADDITIONAL DETAILS
```

------------------------------------------------------------------------

# 57. Final Parser Design Recommendation

For a production CV parser, do not build the system around a simple list
of headings.

Use a layered architecture:

``` text
Document
   ↓
Layout / Text Extraction
   ↓
Candidate Heading Detection
   ↓
Heading Normalization
   ↓
Alias Dictionary
   ↓
Context Classification
   ↓
Canonical Section
   ↓
Entity Extraction
   ↓
Structured Candidate Profile
```

The alias dictionary should be treated as a **living dataset**. Every
time the parser encounters an unknown but valid heading, the heading can
be reviewed and added to the alias library.

For an Egyptian job platform, it is particularly important to support:

-   English CVs
-   Arabic CVs
-   Arabic-English bilingual CVs
-   Mixed Arabic/English headings
-   Different date formats
-   Arabic-Indic numerals
-   British/American spelling
-   Abbreviations
-   Generic headings
-   CVs with no section headings
-   CVs with highly customized visual designs

The ultimate goal should be to normalize different candidate wording
into a stable internal schema while **preserving the original CV text
and structure**.

# 58. Job Responsibilities — Heading Variations

## Canonical classification

`Job Responsibilities` should normally **not** be treated as a top-level CV section.

It should be classified as content belonging to an individual employment record inside:

`professional_experience → employment → responsibilities`

Recommended structure:

```text
Work Experience
  └── Employment
      ├── Company
      ├── Job Title
      ├── Department
      ├── Location
      ├── Start Date
      ├── End Date
      ├── Responsibilities
      ├── Achievements
      └── Skills / Technologies
```

## Direct / standard headings

The following headings are strong indicators of responsibilities performed in a specific job:

- Responsibilities
- Job Responsibilities
- Job Responsibility
- Key Responsibilities
- Main Responsibilities
- Major Responsibilities
- Primary Responsibilities
- Core Responsibilities
- Core Job Responsibilities
- Main Job Responsibilities
- Key Job Responsibilities
- Primary Job Responsibilities
- Principal Responsibilities
- Professional Responsibilities
- Work Responsibilities
- Role Responsibilities
- Position Responsibilities

## Duties variations

Many CVs use `Duties` instead of `Responsibilities`.

- Duties
- Job Duties
- Key Duties
- Main Duties
- Major Duties
- Primary Duties
- Core Duties
- Principal Duties
- Duties and Responsibilities
- Duties & Responsibilities
- Key Duties and Responsibilities
- Main Duties and Responsibilities
- Job Duties and Responsibilities
- Duties / Responsibilities
- Duties & Functions

Recommended normalization:

```text
Duties → employment.responsibilities
```

## Roles & responsibilities variations

- Roles & Responsibilities
- Roles and Responsibilities
- Role & Responsibilities
- Role and Responsibilities
- Role Responsibilities
- Roles, Responsibilities & Achievements
- Roles, Duties & Responsibilities
- Role, Duties and Responsibilities
- Key Roles & Responsibilities
- Main Roles & Responsibilities
- Roles and Key Responsibilities

Recommended normalization:

```text
Roles & Responsibilities → employment.responsibilities
```

The word `Role` itself should not automatically be interpreted as responsibilities. The surrounding content must be examined.

## Accountabilities variations

More senior or management-oriented CVs frequently use `Accountabilities`.

- Accountabilities
- Key Accountabilities
- Main Accountabilities
- Major Accountabilities
- Primary Accountabilities
- Core Accountabilities
- Job Accountabilities
- Role Accountabilities
- Position Accountabilities
- Key Areas of Accountability
- Areas of Accountability
- Accountabilities & Responsibilities
- Key Responsibilities & Accountabilities

Recommended normalization:

```text
Accountabilities → employment.responsibilities
```

`Accountabilities` can be particularly useful for managerial, financial, HR, operations, and executive CVs.

## Functions variations

- Functions
- Job Functions
- Main Functions
- Key Functions
- Core Functions
- Primary Functions
- Functional Responsibilities
- Role Functions
- Position Functions
- Duties & Functions
- Key Functions & Responsibilities

Recommended normalization:

```text
Job Functions → employment.responsibilities
```

However, `Functions` alone is relatively ambiguous and should receive a lower classification confidence unless the surrounding content clearly describes work performed.

## Tasks variations

- Tasks
- Job Tasks
- Key Tasks
- Main Tasks
- Primary Tasks
- Core Tasks
- Major Tasks
- Main Job Tasks
- Key Job Tasks
- Duties and Tasks
- Responsibilities and Tasks

Recommended normalization:

```text
Tasks → employment.responsibilities
```

`Tasks` is generally more operational than `Responsibilities`, but both can represent the same underlying employment content.

## Role description variations

Some CVs describe responsibilities under a heading that sounds like a description of the position:

- Role Description
- Job Role Description
- Position Description
- Job Description
- Position Overview
- Role Overview
- Job Overview
- Position Overview & Responsibilities
- Role Summary
- Job Summary
- Position Summary

Recommended treatment depends on context.

If the heading appears inside an employment record:

```text
Financial Manager
ABC Company
2022 – Present

Job Description
Managed budgeting, reporting and treasury activities...
```

classify the content as:

```text
employment.responsibilities
```

or, where the content is broader than responsibilities:

```text
employment.description
```

Do **not** automatically create a top-level `job_description` CV section.

## Scope / responsibility-area variations

- Areas of Responsibility
- Areas of Responsibilities
- Responsibility Areas
- Areas of Accountability
- Key Responsibility Areas
- Key Responsibility Area
- KRAs
- Key Result Areas
- Areas of Work
- Scope of Responsibilities
- Scope of Work
- Scope of Role
- Role Scope
- Job Scope
- Position Scope

### Important distinction

`Scope of Work` can mean either responsibilities or a project/contract scope. Context is required.

`KRA` / `Key Result Areas` can describe responsibilities, expected outcomes, or performance areas. It is often useful to classify it as:

```text
employment.responsibilities
```

while preserving the original heading and optionally storing a subtype such as `key_result_areas`.

## Responsibilities + achievements combined

Some CVs combine duties with accomplishments:

- Responsibilities & Achievements
- Responsibilities and Achievements
- Duties & Achievements
- Duties and Achievements
- Roles & Achievements
- Key Responsibilities & Achievements
- Responsibilities, Achievements & Skills
- Duties, Responsibilities & Achievements
- Key Contributions & Responsibilities

Do not force all content into `responsibilities` if individual bullets clearly describe measurable accomplishments.

A useful parser can split them into:

```text
employment.responsibilities
employment.achievements
```

Example:

```text
Responsibilities & Achievements
• Managed a team of 15 employees
• Prepared monthly financial reports
• Reduced reporting time by 30%
```

Possible classification:

```json
{
  "responsibilities": [
    "Managed a team of 15 employees",
    "Prepared monthly financial reports"
  ],
  "achievements": [
    "Reduced reporting time by 30%"
  ]
}
```

## Contributions variations

- Contributions
- Key Contributions
- Major Contributions
- Professional Contributions
- Contributions & Achievements
- Key Contributions & Achievements
- Value Added
- Value-Added Contributions
- Major Contributions to the Organization

These should generally be interpreted as `employment.achievements` when they describe measurable impact, and as `employment.responsibilities` when they describe ongoing duties.

## Activities variations

- Activities
- Job Activities
- Work Activities
- Professional Activities
- Role Activities
- Position Activities
- Key Activities
- Main Activities
- Major Activities
- Work-Related Activities

`Activities` is ambiguous. It should only be mapped to `employment.responsibilities` when the surrounding content clearly describes activities performed in that specific job.

For example:

```text
Marketing Manager
XYZ Company

Key Activities
• Developed marketing campaigns
• Managed social media channels
• Coordinated agency relationships
```

→ `employment.responsibilities`

But:

```text
Activities
• Football
• Reading
• Volunteering
```

→ likely `interests` / `hobbies` / `volunteer_experience`, not employment responsibilities.

## Deliverables / scope variations

Some technical and consulting CVs use:

- Key Deliverables
- Deliverables
- Major Deliverables
- Work Scope
- Scope of Work
- Responsibilities & Deliverables
- Role Scope & Deliverables
- Key Areas of Responsibility
- Areas of Responsibility & Deliverables

These may be classified as `employment.responsibilities`, while preserving `deliverables` as a subtype where useful.

## Management / leadership variations

Senior CVs may use headings such as:

- Leadership Responsibilities
- Management Responsibilities
- Management Duties
- Leadership Duties
- Executive Responsibilities
- Managerial Responsibilities
- Supervisory Responsibilities
- Team Management Responsibilities
- Management Functions
- Leadership Functions
- Areas of Leadership
- Areas of Management

When they occur inside a specific employment record, normalize them to:

```text
employment.responsibilities
```

Optionally preserve a subtype:

```text
responsibility_type = management
```

## Arabic headings

Common Arabic equivalents include:

### Responsibilities

- المسؤوليات
- المسؤوليات الوظيفية
- مسؤوليات الوظيفة
- المسؤوليات الرئيسية
- المسؤوليات الأساسية
- أهم المسؤوليات
- المهام والمسؤوليات
- المسؤوليات والمهام
- مسؤوليات العمل
- مسؤوليات الدور الوظيفي
- مسؤوليات المنصب

### Duties

- المهام
- مهام العمل
- مهام الوظيفة
- المهام الوظيفية
- المهام الرئيسية
- المهام الأساسية
- أهم المهام
- الواجبات
- واجبات الوظيفة
- الواجبات والمسؤوليات

### Accountabilities

- المساءلة
- المسؤوليات والمساءلة
- مجالات المسؤولية
- مجالات المساءلة
- المسؤوليات الرئيسية والمساءلة

### Functions

- الاختصاصات
- اختصاصات الوظيفة
- المهام والاختصاصات
- الوظائف والاختصاصات
- المهام الأساسية والاختصاصات

### Scope

- نطاق العمل
- نطاق المسؤوليات
- نطاق الوظيفة
- نطاق الدور
- اختصاصات ونطاق العمل

### Achievements / contributions

- الإنجازات
- الإنجازات الوظيفية
- الإنجازات المهنية
- أهم الإنجازات
- المساهمات
- أهم المساهمات
- المساهمات والإنجازات

## Arabic-English mixed headings

Common Egyptian CV examples include:

- Job Responsibilities | المسؤوليات الوظيفية
- Responsibilities | المسؤوليات
- Key Responsibilities | أهم المسؤوليات
- Duties & Responsibilities | المهام والمسؤوليات
- Job Duties | مهام الوظيفة
- Roles & Responsibilities | الأدوار والمسؤوليات
- Accountabilities | المسؤوليات والمساءلة
- Job Description | الوصف الوظيفي
- Role Description | وصف الدور الوظيفي
- Key Activities | أهم المهام
- Main Functions | الاختصاصات الرئيسية
- Scope of Work | نطاق العمل

These should be normalized based on their context, not on language alone.

---

# 59. Recommended Employment Record Schema for Responsibilities

For a production CV parser, I recommend treating responsibilities as a structured property of each employment record:

```json
{
  "company": "ABC Company",
  "job_title": "Financial Manager",
  "location": "Cairo, Egypt",
  "start_date": "2022-01",
  "end_date": "Present",
  "description": null,
  "responsibilities": [
    "Manage the finance department",
    "Prepare monthly financial reports",
    "Monitor cash flow and banking facilities"
  ],
  "achievements": [
    "Reduced monthly closing time by 20%"
  ],
  "skills": [
    "Financial Analysis",
    "Cash Flow Management",
    "Excel"
  ]
}
```

## Recommended distinction

| Original heading | Recommended field |
|---|---|
| Responsibilities | `employment.responsibilities` |
| Job Responsibilities | `employment.responsibilities` |
| Key Responsibilities | `employment.responsibilities` |
| Duties | `employment.responsibilities` |
| Job Duties | `employment.responsibilities` |
| Roles & Responsibilities | `employment.responsibilities` |
| Accountabilities | `employment.responsibilities` |
| Functions | `employment.responsibilities` |
| Tasks | `employment.responsibilities` |
| Key Activities | `employment.responsibilities` |
| Job Description | `employment.description` or `employment.responsibilities`, depending on content |
| Role Description | `employment.description` or `employment.responsibilities`, depending on content |
| Scope of Work | `employment.responsibilities` or `employment.scope`, depending on context |
| Deliverables | `employment.deliverables` or `employment.responsibilities` |
| Achievements | `employment.achievements` |
| Contributions | `employment.achievements` or `employment.responsibilities`, depending on content |

## Parser rule

The safest general rule is:

> **If a heading occurs within a specific employment record and the following text describes what the candidate did in that role, classify it as `employment.responsibilities`, regardless of whether the heading says Responsibilities, Duties, Functions, Tasks, Accountabilities, Activities, or Job Description.**

This gives Massar a stable internal structure while preserving the candidate's original wording.
