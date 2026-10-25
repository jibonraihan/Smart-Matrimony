<?php
/**
 * Central display-only translations for standard profile option values.
 * Stored database values are never changed. User-written text is not translated.
 */
if (!function_exists('sm_profile_value_translation_map')) {
    function sm_profile_value_translation_map(): array {
        return [
            // Identity, religion and marital status
            'Male' => 'পুরুষ', 'Female' => 'নারী',
            'Islam' => 'ইসলাম', 'Hinduism' => 'হিন্দুধর্ম', 'Christianity' => 'খ্রিস্টধর্ম', 'Buddhism' => 'বৌদ্ধধর্ম', 'Other' => 'অন্যান্য',
            'Hanafi' => 'হানাফি', 'Shafi' => 'শাফিঈ', 'Shafi’i' => 'শাফিঈ', 'Maliki' => 'মালিকি', 'Hanbali' => 'হাম্বলি', 'Ahle Hadith' => 'আহলে হাদিস', 'Salafi' => 'সালাফি', 'Not Applicable' => 'প্রযোজ্য নয়',
            'Unmarried' => 'অবিবাহিত', 'Single' => 'অবিবাহিত', 'Married' => 'বিবাহিত', 'Divorced' => 'তালাকপ্রাপ্ত', 'Widowed' => 'বিধবা/বিপত্নীক', 'Separated' => 'আলাদা থাকেন',
            'Self' => 'নিজে', 'Guardian' => 'অভিভাবক', 'Parents' => 'মা-বাবা', 'Family' => 'পরিবার', 'Student' => 'শিক্ষার্থী',
            // Education options
            'No Formal Education' => 'প্রাতিষ্ঠানিক শিক্ষা নেই', 'Primary' => 'প্রাথমিক', 'Ebtedayi' => 'ইবতেদায়ি', 'JDC / JSC' => 'জেডিসি / জেএসসি',
            'Dakhil (SSC)' => 'দাখিল (এসএসসি)', 'SSC (General)' => 'এসএসসি (সাধারণ)', 'Vocational SSC' => 'ভোকেশনাল এসএসসি',
            'Alim (HSC)' => 'আলিম (এইচএসসি)', 'HSC (General)' => 'এইচএসসি (সাধারণ)', 'Diploma' => 'ডিপ্লোমা', 'Fazil (Bachelor)' => 'ফাজিল (স্নাতক)',
            "Bachelor's" => 'স্নাতক', "Bachelor's Degree" => 'স্নাতক', 'BSc Engineering' => 'বিএসসি ইঞ্জিনিয়ারিং', 'MBBS' => 'এমবিবিএস', 'BDS' => 'বিডিএস', 'DVM' => 'ডিভিএম', 'LLB' => 'এলএলবি', 'BBA' => 'বিবিএ',
            'Kamil (Master)' => 'কামিল (স্নাতকোত্তর)', "Master's" => 'স্নাতকোত্তর', "Master's Degree" => 'স্নাতকোত্তর', 'MSc Engineering' => 'এমএসসি ইঞ্জিনিয়ারিং', 'MBA' => 'এমবিএ', 'LLM' => 'এলএলএম', 'MPhil' => 'এমফিল', 'PhD' => 'পিএইচডি', 'Hifz' => 'হিফজ', 'Qari' => 'কারি', 'Mufti' => 'মুফতি',
            // Occupations and family professions
            'Agriculture (Farmer)' => 'কৃষিকাজ (কৃষক)', 'Business' => 'ব্যবসা', 'Government Service' => 'সরকারি চাকরি', 'Private Service' => 'বেসরকারি চাকরি',
            'Teacher' => 'শিক্ষক', 'Engineer' => 'প্রকৌশলী', 'Doctor' => 'চিকিৎসক', 'Nurse' => 'নার্স', 'Lecturer' => 'প্রভাষক', 'Professor' => 'অধ্যাপক',
            'Software Engineer' => 'সফটওয়্যার প্রকৌশলী', 'IT Professional' => 'আইটি পেশাজীবী', 'Dentist' => 'দন্তচিকিৎসক', 'Pharmacist' => 'ফার্মাসিস্ট', 'Architect' => 'স্থপতি', 'Accountant' => 'হিসাবরক্ষক', 'Journalist' => 'সাংবাদিক', 'Lawyer' => 'আইনজীবী', 'Banker' => 'ব্যাংকার',
            'Police' => 'পুলিশ', 'Army' => 'সেনাবাহিনী', 'Navy' => 'নৌবাহিনী', 'Air Force' => 'বিমানবাহিনী', 'BGB' => 'বিজিবি', 'Ansar' => 'আনসার', 'Fire Service' => 'ফায়ার সার্ভিস', 'Imam' => 'ইমাম', 'Muazzin' => 'মুয়াজ্জিন', 'Islamic Scholar' => 'ইসলামি পণ্ডিত',
            'Driver' => 'চালক', 'Shopkeeper' => 'দোকানদার', 'Electrician' => 'ইলেকট্রিশিয়ান', 'Plumber' => 'প্লাম্বার', 'Mechanic' => 'মেকানিক', 'Tailor' => 'দর্জি', 'Overseas Worker' => 'প্রবাসী কর্মী', 'Retired' => 'অবসরপ্রাপ্ত', 'Housewife' => 'গৃহিণী', 'House Husband' => 'গৃহস্বামী', 'Entrepreneur' => 'উদ্যোক্তা', 'Freelancer' => 'ফ্রিল্যান্সার', 'Unemployed' => 'বেকার',
            // Family and household
            'Small' => 'ছোট', 'Joint' => 'যৌথ', 'Joint Family' => 'যৌথ পরিবার', 'Nuclear Family' => 'একক পরিবার',
            'Lower Middle Class' => 'নিম্ন-মধ্যবিত্ত', 'Middle Class' => 'মধ্যবিত্ত', 'Upper Middle Class' => 'উচ্চ-মধ্যবিত্ত', 'Affluent' => 'সচ্ছল', 'Upper Class' => 'উচ্চবিত্ত', 'Lower Class' => 'নিম্নবিত্ত',
            'Fully Islamic' => 'সম্পূর্ণ ইসলামি', 'Mixed' => 'মিশ্র', 'Modern' => 'আধুনিক', 'Normal' => 'স্বাভাবিক',
            'Father' => 'বাবা', 'Mother' => 'মা', 'Brother' => 'ভাই', 'Sister' => 'বোন', 'Uncle' => 'চাচা/মামা', 'Grandfather' => 'দাদা/নানা', 'Aunt' => 'খালা/ফুফু',
            'Yes' => 'হ্যাঁ', 'No' => 'না', 'Immigrant (প্রবাসী)' => 'প্রবাসী',
            'Urban' => 'শহরাঞ্চল', 'Rural' => 'গ্রামাঞ্চল', 'Semi-Urban' => 'আধা-শহরাঞ্চল', 'Own House' => 'নিজস্ব বাড়ি', 'Family House' => 'পারিবারিক বাড়ি', 'Rented' => 'ভাড়া বাসা',
            // Physical, health and religious practice
            'Very Fair' => 'উজ্জ্বল ফর্সা', 'Fair' => 'ফর্সা', 'Medium' => 'মাঝারি', 'Medium-Deep' => 'মাঝারি-গাঢ়', 'Dark' => 'গাঢ় শ্যামলা', 'Wheatish' => 'শ্যামলা',
            'A+' => 'এ পজিটিভ', 'A-' => 'এ নেগেটিভ', 'B+' => 'বি পজিটিভ', 'B-' => 'বি নেগেটিভ', 'AB+' => 'এবি পজিটিভ', 'AB-' => 'এবি নেগেটিভ', 'O+' => 'ও পজিটিভ', 'O-' => 'ও নেগেটিভ', "Don't Know" => 'জানা নেই',
            'Diabetes' => 'ডায়াবেটিস', 'Hypertension' => 'উচ্চ রক্তচাপ', 'Heart Disease' => 'হৃদরোগ', 'Kidney Disease' => 'কিডনির রোগ', 'Liver Disease' => 'যকৃতের রোগ', 'Asthma' => 'হাঁপানি', 'Thyroid Disorder' => 'থাইরয়েডের সমস্যা', 'Cancer' => 'ক্যানসার', 'Thalassemia' => 'থ্যালাসেমিয়া',
            'None' => 'নেই', 'None / Not provided' => 'নেই / উল্লেখ করা হয়নি', 'Not provided' => 'উল্লেখ করা হয়নি', 'Not specified' => 'উল্লেখ করা হয়নি', 'Unknown' => 'অজানা', 'No diseases' => 'কোনো রোগ নেই', 'No disease' => 'কোনো রোগ নেই', 'No disabilities' => 'কোনো প্রতিবন্ধিতা নেই', 'No disability' => 'কোনো প্রতিবন্ধিতা নেই', 'No allergies' => 'কোনো অ্যালার্জি নেই', 'No chronic disease' => 'দীর্ঘমেয়াদি রোগ নেই',
            'Vision Impairment' => 'দৃষ্টিজনিত সমস্যা', 'Hearing Impairment' => 'শ্রবণজনিত সমস্যা', 'Speech Impairment' => 'কথা বলার সমস্যা', 'Physical Disability' => 'শারীরিক প্রতিবন্ধিতা', 'Wheelchair User' => 'হুইলচেয়ার ব্যবহারকারী',
            'Full Beard' => 'পূর্ণ দাড়ি', 'Short Beard' => 'ছোট দাড়ি', 'No Beard' => 'দাড়ি নেই', 'Not Yet Grown' => 'এখনো গজায়নি', 'Not yet grown' => 'এখনো গজায়নি',
            'Orna' => 'ওড়না', 'Hijab (Face Open)' => 'হিজাব (মুখ খোলা)', 'Hijab (Face Covered)' => 'হিজাব (মুখ ঢাকা)', 'Hijab (Hand, Foot, Face Covered)' => 'হিজাব (হাত, পা ও মুখ আবৃত)', 'Hijab (Hand, Face Covered)' => 'হিজাব (হাত ও মুখ আবৃত)', 'Hijab + Niqab (Fully Covered)' => 'হিজাব ও নিকাব (সম্পূর্ণ আবৃত)', 'Hijab + Niqab' => 'হিজাব ও নিকাব', 'Not Maintained' => 'পালন করা হয় না', 'Not maintained' => 'পালন করা হয় না',
            'Never' => 'কখনো না', 'Occasionally' => 'মাঝেমধ্যে', 'Regular' => 'নিয়মিত', 'Quit' => 'ছেড়ে দিয়েছেন', 'Daily' => 'প্রতিদিন', 'Often' => 'প্রায়ই', 'Rarely' => 'কদাচিৎ', 'Five Times Daily' => 'দিনে পাঁচ ওয়াক্ত', 'Five times daily' => 'দিনে পাঁচ ওয়াক্ত', 'Usually Five Times' => 'সাধারণত পাঁচ ওয়াক্ত', 'Usually five times' => 'সাধারণত পাঁচ ওয়াক্ত', 'Sometimes' => 'মাঝেমধ্যে', 'Regularly' => 'নিয়মিত',
            'Practicing' => 'অনুশীলন করেন', 'Moderately Practicing' => 'মাঝারি মাত্রায় অনুশীলন করেন', 'Properly Practicing' => 'যথাযথভাবে অনুশীলন করেন', 'Learning' => 'শিখছেন', 'Prefer not to say' => 'বলতে অনিচ্ছুক', 'Basic' => 'প্রাথমিক', 'Moderate' => 'মাঝারি', 'Good' => 'ভালো', 'Advanced' => 'উন্নত', 'Strict' => 'কঠোরভাবে পালন করেন', 'Mostly' => 'বেশিরভাগ ক্ষেত্রে', 'Prefer not to disclose' => 'প্রকাশ করতে অনিচ্ছুক', 'Not Applicable' => 'প্রযোজ্য নয়', 'Maintained' => 'পালন করা হয়', 'No smoker' => 'ধূমপান করেন না', 'No alcohol' => 'মদ্যপান করেন না',
            // Lifestyle and partner-preference values
            'Vegetarian' => 'নিরামিষভোজী', 'Non-vegetarian' => 'আমিষভোজী', 'Both' => 'আমিষ নিরামিষ উভয়', 'Both' => 'আমিষ নিরামিষ উভয়', 'No specific preference' => 'কোনো নির্দিষ্ট পছন্দ নেই',
            'Early sleeper' => 'তাড়াতাড়ি ঘুমান', 'Late sleeper' => 'দেরিতে ঘুমান', 'Regular sleeper' => 'নিয়মিত সময়ে ঘুমান', 'Night owl' => 'রাতে জেগে থাকেন',
            'Introvert' => 'অন্তর্মুখী', 'Extrovert' => 'বহির্মুখী', 'Ambivert' => 'উভয়মুখী', 'Very social' => 'খুব মিশুক', 'Moderately social' => 'মাঝারি মাত্রায় মিশুক', 'Prefer small circle' => 'ছোট পরিসরে মেলামেশা পছন্দ', 'Private' => 'ব্যক্তিগত',
            'Love travelling' => 'ভ্রমণ করতে ভালোবাসেন', 'Love Traveling' => 'ভ্রমণ করতে ভালোবাসেন', 'Occasionally' => 'মাঝেমধ্যে', 'Prefer staying home' => 'বাড়িতে থাকতে পছন্দ করেন',
            'Saver' => 'সঞ্চয়ী', 'Balanced' => 'ভারসাম্যপূর্ণ', 'Spender' => 'খরুচে', 'Spendthrift' => 'অমিতব্যয়ী',
            'Comfortable with pets' => 'পোষা প্রাণীর সঙ্গে স্বচ্ছন্দ', 'Not comfortable with pets' => 'পোষা প্রাণীর সঙ্গে স্বচ্ছন্দ নন', 'Prefer no pets' => 'পোষা প্রাণী না থাকাই পছন্দ', 'Have pets' => 'পোষা প্রাণী আছে', 'Allergic' => 'অ্যালার্জি আছে',
            'Reading' => 'বই পড়া', 'Travelling' => 'ভ্রমণ', 'Traveling' => 'ভ্রমণ', 'Sports' => 'খেলাধুলা', 'Photography' => 'ছবি তোলা', 'Cooking' => 'রান্না করা', 'Gaming' => 'গেম খেলা', 'Writing' => 'লেখালেখি', 'Gardening' => 'বাগান করা', 'Volunteering' => 'স্বেচ্ছাসেবা', 'Islamic learning' => 'ইসলামি শিক্ষা', 'Offline Chatting' => 'সরাসরি আড্ডা', 'Riding' => 'সাইকেল/মোটরসাইকেল চালানো', 'Housework' => 'ঘরের কাজ', 'Social Media' => 'সামাজিক যোগাযোগমাধ্যম', 'Sleeping' => 'ঘুমানো',
            // Address and privacy enum values
            'Bangladesh' => 'বাংলাদেশ', 'Current Address' => 'বর্তমান ঠিকানা', 'Permanent Address' => 'স্থায়ী ঠিকানা', 'Same as current location' => 'বর্তমান ঠিকানার মতোই', 'Current location' => 'বর্তমান ঠিকানা', 'No preference' => 'কোনো নির্দিষ্ট পছন্দ নেই', 'Any' => 'যেকোনো',
            'Public' => 'সবার জন্য দৃশ্যমান', 'Hidden' => 'আড়াল করা', 'Everyone' => 'সবাই', 'Verified Users' => 'যাচাইকৃত সদস্যরা', 'Matched Users' => 'ম্যাচ হওয়া সদস্যরা', 'Pending' => 'অপেক্ষমাণ', 'Accepted' => 'গৃহীত', 'Rejected' => 'প্রত্যাখ্যাত', 'Cancelled' => 'বাতিল', 'Matched' => 'ম্যাচ হয়েছে', 'Unmatched' => 'ম্যাচ হয়নি',
            // Common compound strings shown in preference summaries
            'Personality: Introvert' => 'ব্যক্তিত্ব: অন্তর্মুখী', 'Personality: Extrovert' => 'ব্যক্তিত্ব: বহির্মুখী', 'Personality: Ambivert' => 'ব্যক্তিত্ব: উভয়মুখী',
        ];
    }
}
