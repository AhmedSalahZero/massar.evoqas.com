<?php

// ══════════════════════════════════════════════════════════════════
//  Massar — countries for the work history (Step 10.5)
//  Location: config/countries.php
//
//  The countries a job can be in, by 2-letter code (ISO 3166). The
//  names (English/Arabic) are in resources/js/lang/translations.js
//  (country.XX). Egypt and the countries Egyptians work in most come
//  first. "XX" = another country (not in the list).
//
//  places: words in a CV job line → the country (the CV reader already
//  finds the place of a job, e.g. "Riyadh, KSA"). Lower case.
// ══════════════════════════════════════════════════════════════════

return [
    'codes' => ['EG', 'SA', 'AE', 'QA', 'KW', 'OM', 'BH', 'JO', 'LB', 'LY', 'IQ', 'SD', 'YE', 'SY', 'PS', 'MA', 'TN', 'DZ',
        'TR', 'US', 'GB', 'DE', 'FR', 'IT', 'NL', 'CA', 'AU', 'IN', 'CN', 'XX'],

    // The main cities of a country, for the job's location (English, Arabic).
    // A city not listed can always be typed ("+ Add new city").
    'cities' => [
        'SA' => [['Riyadh', 'الرياض'], ['Jeddah', 'جدة'], ['Dammam', 'الدمام'], ['Al Khobar', 'الخبر'], ['Makkah', 'مكة المكرمة'], ['Madinah', 'المدينة المنورة'], ['Jubail', 'الجبيل'], ['Yanbu', 'ينبع'], ['Tabuk', 'تبوك'], ['Abha', 'أبها'], ['Taif', 'الطائف'], ['Qassim', 'القصيم'], ['Hail', 'حائل'], ['Al Ahsa', 'الأحساء']],
        'AE' => [['Dubai', 'دبي'], ['Abu Dhabi', 'أبوظبي'], ['Sharjah', 'الشارقة'], ['Ajman', 'عجمان'], ['Al Ain', 'العين'], ['Ras Al Khaimah', 'رأس الخيمة'], ['Fujairah', 'الفجيرة'], ['Umm Al Quwain', 'أم القيوين']],
        'QA' => [['Doha', 'الدوحة'], ['Al Wakrah', 'الوكرة'], ['Al Khor', 'الخور'], ['Lusail', 'لوسيل']],
        'KW' => [['Kuwait City', 'مدينة الكويت'], ['Hawalli', 'حولي'], ['Farwaniya', 'الفروانية'], ['Ahmadi', 'الأحمدي'], ['Jahra', 'الجهراء']],
        'OM' => [['Muscat', 'مسقط'], ['Salalah', 'صلالة'], ['Sohar', 'صحار'], ['Nizwa', 'نزوى']],
        'BH' => [['Manama', 'المنامة'], ['Muharraq', 'المحرق'], ['Riffa', 'الرفاع']],
        'JO' => [['Amman', 'عمّان'], ['Irbid', 'إربد'], ['Zarqa', 'الزرقاء'], ['Aqaba', 'العقبة']],
        'LB' => [['Beirut', 'بيروت'], ['Tripoli', 'طرابلس'], ['Sidon', 'صيدا']],
        'LY' => [['Tripoli', 'طرابلس'], ['Benghazi', 'بنغازي'], ['Misrata', 'مصراتة']],
        'IQ' => [['Baghdad', 'بغداد'], ['Erbil', 'أربيل'], ['Basra', 'البصرة'], ['Sulaymaniyah', 'السليمانية']],
        'SD' => [['Khartoum', 'الخرطوم'], ['Port Sudan', 'بورتسودان']],
        'TR' => [['Istanbul', 'إسطنبول'], ['Ankara', 'أنقرة'], ['Izmir', 'إزمير']],
        'US' => [['New York', 'نيويورك'], ['Washington DC', 'واشنطن'], ['California', 'كاليفورنيا'], ['Texas', 'تكساس']],
        'GB' => [['London', 'لندن'], ['Manchester', 'مانشستر'], ['Birmingham', 'برمنغهام']],
        'DE' => [['Berlin', 'برلين'], ['Munich', 'ميونخ'], ['Frankfurt', 'فرانكفورت'], ['Hamburg', 'هامبورغ']],
        'FR' => [['Paris', 'باريس'], ['Lyon', 'ليون'], ['Marseille', 'مارسيليا']],
        'IT' => [['Rome', 'روما'], ['Milan', 'ميلانو'], ['Turin', 'تورينو']],
        'CA' => [['Toronto', 'تورونتو'], ['Montreal', 'مونتريال'], ['Vancouver', 'فانكوفر']],
    ],

    'places' => [
        'EG' => ['egypt', 'misr', 'cairo', 'new cairo', 'giza', 'alexandria', 'mansoura', 'tanta', 'zagazig', 'ismailia', 'suez', 'port said',
            'damietta', 'assiut', 'sohag', 'minya', 'luxor', 'aswan', 'hurghada', 'sharm el sheikh', 'sharm', '6th of october', '6th october',
            'october', 'sheikh zayed', '10th of ramadan', 'nasr city', 'maadi', 'heliopolis', 'mohandessin', 'dokki', 'obour', 'sadat city',
            'مصر', 'القاهرة', 'الجيزة', 'الإسكندرية', 'الاسكندرية', 'المنصورة', 'طنطا', 'الزقازيق', 'الإسماعيلية', 'السويس', 'بورسعيد',
            'دمياط', 'أسيوط', 'سوهاج', 'المنيا', 'الأقصر', 'أسوان', 'الغردقة', 'شرم الشيخ', 'أكتوبر', 'العاشر من رمضان', 'مدينة نصر', 'المعادي'],
        'SA' => ['saudi arabia', 'saudi', 'ksa', 'k.s.a', 'riyadh', 'jeddah', 'jiddah', 'dammam', 'khobar', 'al khobar', 'mecca', 'makkah',
            'medina', 'madinah', 'jubail', 'yanbu', 'tabuk', 'abha', 'السعودية', 'المملكة العربية السعودية', 'الرياض', 'جدة', 'الدمام', 'الخبر', 'مكة', 'المدينة المنورة', 'الجبيل'],
        'AE' => ['uae', 'u.a.e', 'united arab emirates', 'emirates', 'dubai', 'abu dhabi', 'sharjah', 'ajman', 'al ain', 'ras al khaimah', 'fujairah',
            'الإمارات', 'الامارات', 'دبي', 'أبوظبي', 'أبو ظبي', 'الشارقة', 'عجمان', 'العين'],
        'QA' => ['qatar', 'doha', 'قطر', 'الدوحة'],
        'KW' => ['kuwait', 'الكويت'],
        'OM' => ['oman', 'muscat', 'sultanate of oman', 'سلطنة عمان', 'عمان', 'مسقط'],
        'BH' => ['bahrain', 'manama', 'البحرين', 'المنامة'],
        'JO' => ['jordan', 'amman', 'الأردن', 'الاردن'],
        'LB' => ['lebanon', 'beirut', 'لبنان', 'بيروت'],
        'LY' => ['libya', 'tripoli', 'benghazi', 'ليبيا', 'طرابلس', 'بنغازي'],
        'IQ' => ['iraq', 'baghdad', 'erbil', 'basra', 'العراق', 'بغداد', 'أربيل', 'البصرة'],
        'SD' => ['sudan', 'khartoum', 'السودان', 'الخرطوم'],
        'YE' => ['yemen', 'sanaa', 'aden', 'اليمن'],
        'SY' => ['syria', 'damascus', 'سوريا', 'دمشق'],
        'PS' => ['palestine', 'gaza', 'ramallah', 'فلسطين', 'غزة'],
        'MA' => ['morocco', 'casablanca', 'rabat', 'المغرب'],
        'TN' => ['tunisia', 'tunis', 'تونس'],
        'DZ' => ['algeria', 'algiers', 'الجزائر'],
        'TR' => ['turkey', 'türkiye', 'istanbul', 'ankara', 'تركيا', 'إسطنبول'],
        'US' => ['usa', 'u.s.a', 'united states', 'new york', 'california', 'texas', 'الولايات المتحدة', 'أمريكا'],
        'GB' => ['uk', 'u.k', 'united kingdom', 'england', 'london', 'المملكة المتحدة', 'بريطانيا', 'لندن'],
        'DE' => ['germany', 'berlin', 'munich', 'ألمانيا'],
        'FR' => ['france', 'paris', 'فرنسا', 'باريس'],
        'IT' => ['italy', 'rome', 'milan', 'إيطاليا'],
        'NL' => ['netherlands', 'amsterdam', 'هولندا'],
        'CA' => ['canada', 'toronto', 'montreal', 'كندا'],
        'AU' => ['australia', 'sydney', 'melbourne', 'أستراليا'],
        'IN' => ['india', 'mumbai', 'bangalore', 'الهند'],
        'CN' => ['china', 'shanghai', 'beijing', 'الصين'],
    ],
];
