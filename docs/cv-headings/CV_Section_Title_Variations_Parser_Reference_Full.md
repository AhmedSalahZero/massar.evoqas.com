# CV Section Title Variations — Parser Reference (Full Comprehensive Version)

## Purpose

This document is a comprehensive reference dataset for **automatic CV/resume parsing**.

It maps the many different headings candidates use for the same section to a **canonical section category**.  
The goal is maximum coverage of real-world variations (including minor differences in wording, spelling, punctuation, singular/plural, English, Arabic, and bilingual forms).

> **Core Principle**  
> Classify headings using **both** the heading text **and** the content underneath.  
> Some headings are ambiguous and require context.

---

# 1. Recommended Canonical Section Taxonomy

| Canonical ID                | Recommended Display Name          | Notes |
|----------------------------|-----------------------------------|-------|
| `contact_information`      | Contact Information               |       |
| `professional_summary`     | Professional Summary              | Includes Profile, About Me, Overview |
| `career_objective`         | Career Objective                  |       |
| `professional_experience`  | Professional Experience           | **Main ID** for all work history |
| `education`                | Education                         |       |
| `skills`                   | Skills                            | Hard + Soft skills |
| `languages`                | Languages                         |       |
| `certifications`           | Certifications                    |       |
| `training`                 | Training & Courses                |       |
| `projects`                 | Projects                          |       |
| `achievements`             | Achievements                      |       |
| `awards`                   | Awards & Honors                   |       |
| `volunteer_experience`     | Volunteer Experience              |       |
| `internships`              | Internships                       |       |
| `publications`             | Publications                      |       |
| `research`                 | Research                          |       |
| `memberships`              | Professional Memberships          |       |
| `references`               | References                        |       |
| `interests`                | Interests & Hobbies               |       |
| `personal_information`     | Personal Information              | DOB, Nationality, etc. |
| `military_service`         | Military Service                  |       |
| `additional_information`   | Additional Information            |       |
| `custom_other`             | Other / Unclassified              |       |

**Important Design Decision:**  
All of the following map to the single canonical ID `professional_experience`:
- Work Experience
- Employment History
- Career History
- Professional Experience
- Work History
- etc.

---

# 2. Contact Information

**Canonical ID:** `contact_information`

### English Variations
- Contact
- Contact Information
- Contact Details
- Contact Info
- Personal Contact
- Contact Data
- Candidate Contact
- Candidate Information
- Personal Details
- Personal Information
- My Details
- Get In Touch
- Reach Me
- How to Contact Me
- Contact Me
- My Contact
- Contact Data
- Personal Contact Information

### Arabic / Bilingual
- بيانات الاتصال
- معلومات الاتصال
- بيانات التواصل
- معلومات التواصل
- معلومات شخصية
- البيانات الشخصية
- بيانات المرشح
- معلومات المرشح
- Contact Information | بيانات الاتصال
- Personal Information | البيانات الشخصية
- Contact Details | بيانات الاتصال

---

# 3. Professional Summary

**Canonical ID:** `professional_summary`

### English Variations
- Professional Summary
- Professional Profile
- Professional Overview
- Career Summary
- Career Profile
- Career Overview
- Executive Summary
- Executive Profile
- Summary
- Summary of Qualifications
- Qualifications Summary
- Professional Highlights
- Career Highlights
- Candidate Summary
- Resume Summary
- CV Summary
- Profile Summary
- About Me
- About
- About Myself
- Introduction
- Personal Profile
- Personal Statement
- Professional Statement
- Career Statement
- Overview
- Profile
- My Profile
- Who I Am
- Snapshot
- At a Glance
- Highlights
- Professional Snapshot
- Career Snapshot

### Arabic / Bilingual
- الملخص المهني
- نبذة مهنية
- الملف المهني
- نبذة عني
- نبذة شخصية
- نبذة مختصرة
- ملخص الخبرات
- ملخص المؤهلات
- الهدف المهني والملخص
- Professional Summary | الملخص المهني
- Profile | نبذة مهنية
- About Me | نبذة عني

---

# 4. Career Objective

**Canonical ID:** `career_objective`

### English Variations
- Career Objective
- Career Goal
- Career Goals
- Professional Objective
- Professional Goal
- Career Aim
- Objective
- Job Objective
- Employment Objective
- Career Aspiration
- Career Aspirations
- Professional Aspirations
- Career Direction
- Career Target
- Career Plan
- My Objective
- My Career Objective
- Objective Statement
- Career Objective Statement
- Professional Aim

### Arabic / Bilingual
- الهدف الوظيفي
- الهدف المهني
- الأهداف المهنية
- هدفي المهني
- الهدف من العمل
- التوجه المهني
- الطموح المهني
- Career Objective | الهدف الوظيفي
- Objective | الهدف المهني

---

# 5. Professional Experience (Main Section)

**Canonical ID:** `professional_experience`

### English Variations
- Professional Experience
- Professional Experiences
- Work Experience
- Work Experiences
- Employment Experience
- Employment History
- Employment Record
- Career History
- Career Experience
- Career Record
- Work History
- Work Record
- Job History
- Job Experience
- Professional Background
- Work Background
- Employment Background
- Career Background
- Experience
- Experiences
- Relevant Experience
- Relevant Work Experience
- Relevant Professional Experience
- Industry Experience
- Corporate Experience
- Occupational Experience
- Previous Employment
- Previous Experience
- Past Experience
- Experience History
- Experience & Achievements
- Work & Experience
- Professional Journey
- Career Journey
- Employment
- Career Path
- Positions Held
- Professional Career
- Work Exp.
- Work Exp
- Prof. Experience
- Prof Experience
- Employment Hist.
- Career Hist.

### Arabic / Bilingual
- الخبرات العملية
- الخبرة العملية
- الخبرات المهنية
- الخبرة المهنية
- الخبرات الوظيفية
- الخبرة الوظيفية
- السجل الوظيفي
- التاريخ الوظيفي
- المسار المهني
- الخبرات السابقة
- الخبرة العملية والمهنية
- الخبرات
- خبرات العمل
- الوظائف السابقة
- المناصب السابقة
- الخبرات الوظيفية السابقة
- Work Experience | الخبرة العملية
- Professional Experience | الخبرات المهنية
- Employment History | السجل الوظيفي
- Career History | التاريخ الوظيفي

---

# 6. Responsibilities (Sub-section under each Job)

> **Important Rule**  
> Responsibilities is **never** a top-level section.  
> It always belongs **inside** an individual employment record under `professional_experience`.

### Recommended Structure
```text
professional_experience
└── employment
    ├── job_title
    ├── company
    ├── location
    ├── start_date
    ├── end_date
    ├── description              (optional high-level overview)
    ├── responsibilities[]       ← Main field for duties
    ├── achievements[]
    └── skills_used[]
```

### English Variations for Responsibilities
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
- Key Deliverables
- Deliverables
- Major Deliverables
- Work Scope
- Responsibilities & Deliverables
- Role Scope & Deliverables
- Key Areas of Responsibility
- Areas of Responsibility & Deliverables
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
- Contributions
- Key Contributions
- Major Contributions
- Professional Contributions
- Contributions & Achievements
- Key Contributions & Achievements
- Value Added
- Value-Added Contributions
- Major Contributions to the Organization
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
- Responsibilities & Achievements
- Responsibilities and Achievements
- Duties & Achievements
- Duties and Achievements
- Roles & Achievements
- Key Responsibilities & Achievements
- Responsibilities, Achievements & Skills
- Duties, Responsibilities & Achievements

### Arabic Variations for Responsibilities
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
- المساءلة
- المسؤوليات والمساءلة
- مجالات المسؤولية
- مجالات المساءلة
- المسؤوليات الرئيسية والمساءلة
- الاختصاصات
- اختصاصات الوظيفة
- المهام والاختصاصات
- الوظائف والاختصاصات
- المهام الأساسية والاختصاصات
- نطاق العمل
- نطاق المسؤوليات
- نطاق الوظيفة
- نطاق الدور
- اختصاصات ونطاق العمل
- الإنجازات
- الإنجازات الوظيفية
- الإنجازات المهنية
- أهم الإنجازات
- المساهمات
- أهم المساهمات
- المساهمات والإنجازات

### Arabic-English Mixed
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

### Golden Parser Rule for Responsibilities

> If the heading appears **inside a specific job entry** and the text describes what the candidate did in that role →  
> classify it as `employment.responsibilities`  
> (regardless of whether the heading says Responsibilities, Duties, Functions, Tasks, Job Description, Accountabilities, etc.)

---

# 7. Education

**Canonical ID:** `education`

### English Variations
- Education
- Educational Background
- Education Background
- Academic Background
- Academic History
- Academic Experience
- Educational History
- Educational Qualifications
- Academic Qualifications
- Academic Credentials
- Academic Record
- Educational Record
- Degrees
- Academic Degrees
- Degrees & Education
- Education & Qualifications
- Education and Qualifications
- Qualifications
- Academic Qualifications
- Formal Education
- Educational Qualifications

### Arabic / Bilingual
- التعليم
- المؤهلات التعليمية
- المؤهلات الأكاديمية
- الخلفية التعليمية
- الخلفية الأكاديمية
- التاريخ التعليمي
- التاريخ الأكاديمي
- المؤهلات
- الشهادات الأكاديمية
- الدرجات العلمية
- التعليم والمؤهلات
- Education | التعليم
- Academic Background | الخلفية الأكاديمية
- Qualifications | المؤهلات

---

# 8. Skills

**Canonical ID:** `skills`

### General Skills Variations
- Skills
- Skill Set
- Skillset
- Skills Summary
- Skills Profile
- Core Skills
- Key Skills
- Main Skills
- Essential Skills
- Relevant Skills
- Professional Skills
- Competencies
- Core Competencies
- Key Competencies
- Professional Competencies
- Areas of Competence
- Areas of Expertise
- Expertise
- Areas of Knowledge
- Capabilities
- Core Capabilities
- Strengths
- Key Strengths
- Professional Strengths
- Abilities
- Key Abilities
- Proficiencies
- Core Proficiencies
- Specialties
- Specializations
- Expertise & Skills
- Skills & Competencies
- Competencies & Skills

### Technical Skills Variations
- Technical Skills
- Technical Skillset
- Technical Competencies
- Technical Expertise
- Technical Proficiencies
- Technical Knowledge
- Technical Capabilities
- Technology Skills
- IT Skills
- Information Technology Skills
- Computer Skills
- Computer Proficiency
- Computer Knowledge
- Software Skills
- Software Proficiency
- Digital Skills
- Digital Competencies
- Systems Skills
- Systems Knowledge
- Tools & Technologies
- Technologies
- Technical Tools
- Technical Background
- Technical Qualifications
- Technical Abilities

### Soft Skills Variations
- Soft Skills
- Interpersonal Skills
- Personal Skills
- Personal Competencies
- Behavioral Skills
- People Skills
- Social Skills
- Communication Skills
- Leadership Skills
- Management Skills
- Teamwork Skills
- Collaboration Skills
- Personal Strengths
- Core Personal Skills
- Transferable Skills
- Essential Soft Skills

### Arabic / Bilingual
- المهارات
- المهارات الأساسية
- المهارات المهنية
- المهارات الشخصية
- الكفاءات
- الكفاءات الأساسية
- القدرات
- نقاط القوة
- الخبرات والمهارات
- المهارات والكفاءات
- مجالات الخبرة
- مجالات التخصص
- المهارات التقنية
- المهارات الفنية
- المهارات التكنولوجية
- مهارات تكنولوجيا المعلومات
- مهارات الحاسب الآلي
- مهارات الكمبيوتر
- Skills | المهارات
- Core Competencies | الكفاءات الأساسية
- Technical Skills | المهارات التقنية
- Soft Skills | المهارات الشخصية

---

# 9. Languages

**Canonical ID:** `languages`

### English Variations
- Languages
- Language Skills
- Language Proficiency
- Languages & Proficiency
- Language Proficiencies
- Foreign Languages
- Foreign Language Skills
- Linguistic Skills
- Linguistic Abilities
- Language Competencies
- Language Knowledge
- Spoken Languages
- Languages Known
- Languages Spoken
- Languages & Communication

### Arabic / Bilingual
- اللغات
- اللغات الأجنبية
- مهارات اللغة
- المهارات اللغوية
- اللغات ومستويات الإتقان
- إجادة اللغات
- اللغات التي أتقنها
- Languages | اللغات

---

# 10. Certifications

**Canonical ID:** `certifications`

### English Variations
- Certifications
- Certificates
- Professional Certifications
- Professional Certificates
- Certifications & Licenses
- Certificates & Licenses
- Credentials
- Professional Credentials
- Industry Certifications
- Technical Certifications
- Certification
- Licenses & Certifications
- Certifications and Credentials
- Licenses
- Licences
- Professional Licenses
- Professional Licence
- Registrations
- Professional Registrations

### Arabic / Bilingual
- الشهادات
- الشهادات المهنية
- الشهادات الاحترافية
- الاعتمادات المهنية
- الشهادات والاعتمادات
- التراخيص
- الرخص المهنية
- التراخيص المهنية
- Certifications | الشهادات المهنية
- Licenses | التراخيص

---

# 11. Training & Courses

**Canonical ID:** `training`

### English Variations
- Training
- Training Courses
- Training & Development
- Professional Training
- Professional Development
- Training Programs
- Training & Courses
- Courses & Training
- Workshops & Training
- Workshops
- Seminars & Training
- Development Programs
- Learning & Development
- Continuing Education
- Continuing Professional Development
- CPD
- Training History
- Training Record
- Courses
- Courses Completed
- Completed Courses
- Relevant Courses
- Professional Courses
- Academic Courses
- Short Courses
- Online Courses
- Additional Courses
- Selected Courses
- Workshops & Courses

### Arabic / Bilingual
- التدريب
- الدورات التدريبية
- التدريب والتطوير
- التدريب المهني
- البرامج التدريبية
- الدورات والتدريب
- التطوير المهني
- التعليم المستمر
- التدريب والتطوير المهني
- الدورات
- الدورات التي تم الحصول عليها
- الدورات المهنية
- الدورات الإضافية
- Training | التدريب
- Courses | الدورات

---

# 12. Projects

**Canonical ID:** `projects`

### English Variations
- Projects
- Project Experience
- Project History
- Project Portfolio
- Selected Projects
- Key Projects
- Major Projects
- Relevant Projects
- Professional Projects
- Academic Projects
- Personal Projects
- University Projects
- Featured Projects
- Project Highlights
- Projects & Achievements
- Selected Work
- Portfolio Projects
- Portfolio
- Work Portfolio
- Professional Portfolio
- Portfolio & Projects
- Work Samples
- Samples of Work
- Creative Portfolio
- Online Portfolio

### Arabic / Bilingual
- المشاريع
- خبرات المشاريع
- مشروعات
- المشاريع الرئيسية
- أهم المشاريع
- المشاريع المختارة
- المشاريع الشخصية
- المشاريع الأكاديمية
- مشاريع التخرج
- مشاريع مختارة
- معرض الأعمال
- ملف الأعمال
- نماذج الأعمال
- Projects | المشاريع
- Portfolio | معرض الأعمال

---

# 13. Achievements & Awards

**Canonical ID:** `achievements` / `awards`

### Achievements Variations
- Achievements
- Key Achievements
- Major Achievements
- Professional Achievements
- Career Achievements
- Notable Achievements
- Significant Achievements
- Accomplishments
- Key Accomplishments
- Professional Accomplishments
- Career Highlights
- Professional Highlights
- Highlights
- Successes
- Milestones
- Key Contributions
- Contributions
- Impact

### Awards Variations
- Awards
- Awards & Honors
- Honors
- Honours
- Honors & Awards
- Awards and Recognition
- Recognition
- Professional Recognition
- Distinctions
- Achievements & Awards
- Awards & Achievements
- Prizes
- Academic Honors
- Academic Awards
- Professional Awards

### Arabic / Bilingual
- الإنجازات
- أهم الإنجازات
- الإنجازات المهنية
- الإنجازات الوظيفية
- أبرز الإنجازات
- النجاحات
- المساهمات
- الجوائز
- الجوائز والتكريمات
- التكريمات
- الجوائز والتقدير
- Achievements | الإنجازات
- Awards | الجوائز

---

# 14. Volunteer Experience

**Canonical ID:** `volunteer_experience`

### English Variations
- Volunteer Experience
- Volunteering
- Volunteer Work
- Community Service
- Community Involvement
- Social Service
- Voluntary Work
- Volunteer Activities
- Community Activities
- Civic Engagement
- Nonprofit Experience
- NGO Experience

### Arabic / Bilingual
- العمل التطوعي
- الخبرات التطوعية
- التطوع
- الخدمة المجتمعية
- المشاركة المجتمعية
- الأنشطة التطوعية
- Volunteer Experience | الخبرات التطوعية

---

# 15. Internships

**Canonical ID:** `internships`

### English Variations
- Internships
- Internship Experience
- Internship
- Internship History
- Student Internships
- Training & Internships
- Industrial Training
- Summer Training
- Practical Training
- Cooperative Training
- Co-op Experience
- Trainee Experience
- Graduate Training

### Arabic / Bilingual
- التدريب العملي
- التدريب الصيفي
- التدريب التعاوني
- فترة التدريب
- الخبرات التدريبية
- التدريب الصناعي
- Internship | التدريب

---

# 16. References

**Canonical ID:** `references`

### English Variations
- References
- Professional References
- References Available
- References Upon Request
- Referees
- Professional Referees
- References & Recommendations
- Recommendations
- Professional Recommendations
- Testimonials
- Testimonial
- References Available Upon Request
- Available Upon Request

### Arabic / Bilingual
- المراجع
- التزكيات
- التوصيات
- المراجع المهنية
- التوصيات المهنية
- References | المراجع

---

# 17. Interests & Hobbies

**Canonical ID:** `interests`

### English Variations
- Interests
- Personal Interests
- Professional Interests
- Hobbies
- Hobbies & Interests
- Interests & Hobbies
- Personal Interests & Hobbies
- Activities
- Personal Activities
- Extracurricular Activities
- Leisure Activities
- Outside Interests
- Areas of Interest

### Arabic / Bilingual
- الاهتمامات
- الهوايات
- الهوايات والاهتمامات
- الاهتمامات الشخصية
- الأنشطة
- الأنشطة الشخصية
- الأنشطة اللامنهجية
- Interests | الاهتمامات

---

# 18. Personal Information

**Canonical ID:** `personal_information`

### English Variations
- Personal Information
- Personal Details
- Personal Data
- Personal Profile
- Personal Background
- Candidate Information
- Candidate Details
- Bio Data
- Biodata
- Personal Particulars
- Basic Information
- General Information

### Arabic / Bilingual
- البيانات الشخصية
- المعلومات الشخصية
- البيانات الأساسية
- بيانات المرشح
- معلومات المرشح
- بيانات شخصية
- معلومات عامة
- Personal Details | البيانات الشخصية

---

# 19. Military Service

**Canonical ID:** `military_service`

### English Variations
- Military Service
- Military Status
- Military Record
- National Service
- Military Background
- Armed Forces Service

### Arabic
- الخدمة العسكرية
- الموقف من التجنيد
- الخدمة الوطنية

---

# 20. Parser Golden Rules

1. **Responsibilities** is always a child of an employment record — never a top-level section.
2. Prefer one canonical ID per concept.
3. Always store both `original_heading` and `canonical_section`.
4. Use content + context for ambiguous headings.
5. Support English, Arabic, and bilingual headings equally.
6. Preserve the original candidate wording.
7. Assign confidence scores.
8. Unknown headings → `custom_other`.
9. Singular/plural, abbreviations, and minor spelling differences should all be covered.
10. The more real variations included, the higher the parsing success rate.

---

**Document Status:** Full Comprehensive Version  
Designed for real automatic CV parsing systems.  
Maximum coverage of wording variations while maintaining a clean internal structure.
