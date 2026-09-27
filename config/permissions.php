<?php

// ══════════════════════════════════════════════════════════════════
//  Massar — Permission Registry (the "permission backbone")
//  Location: config/permissions.php
//
//  Scope v2 §5: every screen and action checks a NAMED permission
//  from day one; the detailed Permission Builder (checkbox matrix
//  per user) is configured at the end of the build.
//
//  HOW IT WORKS
//  ─────────────
//  · Every permission key used anywhere in the app is listed below,
//    grouped by module. Nothing may check a key that is not here —
//    App\Support\Permissions refuses unknown keys, so a typo shows up
//    as "denied", never as an accidental "allowed".
//  · `defaults` says which keys each role holds while a user's own
//    users.permissions column is NULL (i.e. until the Permission
//    Builder exists). During the build Company Admins hold everything
//    and employees hold everything except team / workspace settings,
//    so nothing is blocked while we build.
//  · Checking: in PHP → $user->can('cv.upload'), Gate, or the route
//    middleware `can:cv.upload`. In Vue → usePermissions().can('cv.upload').
//    The list of keys the signed-in user holds is shared to the
//    frontend by HandleInertiaRequests as auth.user.permissions.
//
//  ADDING A NEW PERMISSION
//  ───────────────────────
//  1. Add the key to the right module below (with en/ar labels).
//  2. Add it to the role defaults it belongs to.
//  3. Use it. That's all — no migration, no seeder.
// ══════════════════════════════════════════════════════════════════

return [

    'modules' => [

        // ── Beneficiaries & CV Bank (Module B) ─────────────────────
        'beneficiaries' => [
            'label' => ['en' => 'Beneficiaries', 'ar' => 'المستفيدون'],
            'keys'  => [
                'beneficiaries.view'   => ['en' => 'View beneficiaries',          'ar' => 'عرض المستفيدين'],
                'beneficiaries.create' => ['en' => 'Register beneficiaries',      'ar' => 'تسجيل المستفيدين'],
                'beneficiaries.edit'   => ['en' => 'Edit beneficiaries',          'ar' => 'تعديل المستفيدين'],
                'beneficiaries.delete' => ['en' => 'Delete beneficiaries',        'ar' => 'حذف المستفيدين'],
                'cv.upload'            => ['en' => 'Upload CVs',                  'ar' => 'رفع السير الذاتية'],
                'cv.review'            => ['en' => 'Review and approve CVs',      'ar' => 'مراجعة واعتماد السير'],
                'cv.download'          => ['en' => 'Download original CV files',  'ar' => 'تحميل ملفات السير الأصلية'],
                'pool.view'            => ['en' => 'View the public talent pool', 'ar' => 'عرض مجمع المواهب العام'],
                'pool.add'             => ['en' => 'Add from the public pool',    'ar' => 'الإضافة من المجمع العام'],
            ],
        ],

        // ── Learned rules ──────────────────────────────────────────
        'rules' => [
            'label' => ['en' => 'Learned rules', 'ar' => 'القواعد المكتسبة'],
            'keys'  => [
                'rules.view'    => ['en' => 'View learned rules',         'ar' => 'عرض القواعد المكتسبة'],
                'rules.manage'  => ['en' => 'Teach and edit rules',       'ar' => 'تعليم وتعديل القواعد'],
                'rules.propose' => ['en' => 'Propose rules to backbone',  'ar' => 'اقتراح قواعد للقاعدة المشتركة'],
            ],
        ],

        // ── Opportunities: Jobs & Training, with eligibility inside (Step 12) ──
        //    Checking people is separate from managing a job's rules, so a case
        //    worker can check people without being allowed to change them.
        //    Matches (Step 13) are in the same module.
        'opportunities' => [
            'label' => ['en' => 'Jobs & Training', 'ar' => 'الوظائف والتدريب'],
            'keys'  => [
                'opportunities.view'   => ['en' => 'See jobs, trainings, their rules and shortlists', 'ar' => 'عرض الوظائف والتدريب وقواعدها وقوائمها المختصرة'],
                'opportunities.manage' => ['en' => 'Post and manage jobs and trainings, with their rules', 'ar' => 'نشر الوظائف والتدريب وإدارتها مع قواعدها'],
                'eligibility.check'    => ['en' => 'Check people against a job or training', 'ar' => 'فحص الأشخاص على وظيفة أو تدريب'],
                'eligibility.decide'   => ['en' => 'Change an eligibility result (with a reason)', 'ar' => 'تغيير نتيجة الأهلية (مع ذكر السبب)'],
                // Step 13: seeing matches is separate from changing them.
                'matches.view'         => ['en' => 'See matches', 'ar' => 'عرض المطابقات'],
                'matches.manage'       => ['en' => 'Refer people, move stages, stop, restart and correct matches', 'ar' => 'إحالة الأشخاص ونقل المراحل والإيقاف وإعادة التشغيل وتصحيح المطابقات'],
            ],
        ],

        // ── Insights ───────────────────────────────────────────────
        'insights' => [
            'label' => ['en' => 'Insights', 'ar' => 'التحليلات'],
            'keys'  => [
                'occupations.view' => ['en' => 'Browse occupations',  'ar' => 'تصفح المهن'],
                'reports.view'     => ['en' => 'View reports',        'ar' => 'عرض التقارير'],
                'reports.export'   => ['en' => 'Export reports',      'ar' => 'تصدير التقارير'],
            ],
        ],

        // ── Workspace settings ─────────────────────────────────────
        'workspace' => [
            'label' => ['en' => 'Workspace', 'ar' => 'مساحة العمل'],
            'keys'  => [
                'team.manage'        => ['en' => 'Manage team members',   'ar' => 'إدارة أعضاء الفريق'],
                'workspace.settings' => ['en' => 'Workspace settings',    'ar' => 'إعدادات مساحة العمل'],
            ],
        ],

        // ── Platform (Super Admin only) ────────────────────────────
        'platform' => [
            'label' => ['en' => 'Platform', 'ar' => 'المنصة'],
            'keys'  => [
                'platform.companies' => ['en' => 'Manage partner organisations', 'ar' => 'إدارة المؤسسات الشريكة'],
                'platform.activity'  => ['en' => 'View platform activity',       'ar' => 'عرض نشاط المنصة'],
                'platform.backbone'  => ['en' => 'Maintain the occupation backbone', 'ar' => 'صيانة قاعدة بيانات المهن'],
                'platform.rules'     => ['en' => 'Promote rules to the backbone',    'ar' => 'ترقية القواعد للقاعدة المشتركة'],
            ],
        ],
    ],

    // Role defaults — used while users.permissions is NULL.
    // '*' means every key in the given modules.
    'defaults' => [
        'super_admin'   => ['platform.*', 'occupations.view', 'reports.view', 'reports.export'],
        'company_admin' => ['beneficiaries.*', 'rules.*', 'opportunities.*', 'insights.*', 'workspace.*'],
        'employee'      => ['beneficiaries.*', 'rules.*', 'opportunities.*', 'insights.*'],
    ],
];
