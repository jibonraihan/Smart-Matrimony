(function () {
    'use strict';

    const modal = document.getElementById('matchDetailsModal');
    const list = document.getElementById('matchDetailsList');
    if (!modal || !list) return;

    const overall = document.getElementById('matchDetailsOverall');
    const title = document.getElementById('matchDetailsTitle');
    const forward = document.getElementById('matchDetailsForward');
    const reverse = document.getElementById('matchDetailsReverse');
    const isEnglish = modal.dataset.language === 'en';
    const text = (en, bn) => isEnglish ? en : bn;
    const labels = {
        'Age': 'বয়স', 'Height': 'উচ্চতা', 'Weight': 'ওজন', 'Religion': 'ধর্ম', 'Islamic practice': 'ধর্মীয় অনুশীলন',
        'Marital status': 'বৈবাহিক অবস্থা', 'Education': 'শিক্ষা', 'Profession': 'পেশা', 'Location': 'ঠিকানা',
        'Lifestyle': 'জীবনযাপন', 'Question answers': 'প্রশ্নের উত্তর', 'Complexion': 'গায়ের বর্ণ', 'Skin Colour': 'গায়ের বর্ণ', 'Skin Color': 'গায়ের বর্ণ', 'Weight': 'ওজন',
        'Family status': 'পারিবারিক অবস্থা', 'You want': 'আপনার প্রত্যাশা', 'They have': 'প্রোফাইলে দেওয়া তথ্য', 'Contribution': 'মিলের হিসাবে অবদান',
        'No preference': 'কোনো পছন্দ উল্লেখ করা হয়নি', 'Not provided': 'তথ্য দেওয়া হয়নি',
        'Question-based answers': 'প্রশ্নের উত্তর', 'Question-based answers match': 'প্রশ্নের উত্তরের মিল',
        'Islamic Practice': 'ধর্মীয় অনুশীলন', 'Religious Practice': 'ধর্মীয় অনুশীলন',
        'Family Status': 'পারিবারিক অবস্থা', 'Marital Status': 'বৈবাহিক অবস্থা',
        'Preferred Gender': 'প্রত্যাশিত জীবনসঙ্গী', 'Partner Preference': 'জীবনসঙ্গীর পছন্দ',
        'Current Location': 'বর্তমান ঠিকানা', 'Height Range': 'উচ্চতার সীমা', 'Age Range': 'বয়সের সীমা'
    };
    const valueLabels = {
        'Islam': 'ইসলাম', 'Muslim': 'মুসলিম', 'Hinduism': 'হিন্দুধর্ম', 'Christianity': 'খ্রিষ্টধর্ম', 'Buddhism': 'বৌদ্ধধর্ম',
        'Male': 'পুরুষ', 'Female': 'নারী', 'Unmarried': 'অবিবাহিত', 'Married': 'বিবাহিত', 'Divorced': 'বিবাহবিচ্ছেদ হয়েছে',
        'Widowed': 'বিধবা/বিপত্নীক', 'Single': 'অবিবাহিত', 'Verified': 'যাচাইকৃত', 'Pending': 'অপেক্ষমাণ', 'BSc Engineering': 'বিএসসি ইঞ্জিনিয়ারিং', 'MSc Engineering': 'এমএসসি ইঞ্জিনিয়ারিং', 'MBBS': 'এমবিবিএস', 'BDS': 'বিডিএস', 'LLB': 'এলএলবি', 'BBA': 'বিবিএ', 'MBA': 'এমবিএ', 'MPhil': 'এমফিল', 'Kamil (Master)': 'কামিল (স্নাতকোত্তর)', 'Fazil (Bachelor)': 'ফাজিল (স্নাতক)',
        'Five Times Daily': 'দিনে পাঁচ ওয়াক্ত', 'Usually Five Times': 'সাধারণত পাঁচ ওয়াক্ত', 'Sometimes': 'মাঝেমধ্যে', 'Rarely': 'কদাচিৎ',
        'Hanafi': 'হানাফি', 'Shafi': 'শাফেয়ি', 'Maliki': 'মালিকি', 'Hanbali': 'হাম্বলি', 'Student': 'শিক্ষার্থী',
        'Teacher': 'শিক্ষক', 'Doctor': 'চিকিৎসক', 'Engineer': 'প্রকৌশলী', 'Business': 'ব্যবসা', 'Government Service': 'সরকারি চাকরি',
        'Private Service': 'বেসরকারি চাকরি', 'Small': 'ছোট পরিবার', 'Joint': 'যৌথ পরিবার', 'Lower Middle Class': 'নিম্ন-মধ্যবিত্ত',
        'Middle Class': 'মধ্যবিত্ত', 'Upper Middle Class': 'উচ্চ-মধ্যবিত্ত', 'Affluent': 'সচ্ছল', 'Very Fair': 'খুব ফর্সা', 'Fair': 'ফর্সা',
        'Medium': 'মাঝারি', 'Medium-Deep': 'শ্যামলা', 'Dark': 'গাঢ় বর্ণ', 'Wheatish': 'গম-রঙা', 'Full Beard': 'পূর্ণ দাড়ি', 'Short Beard': 'ছোট দাড়ি', 'No Beard': 'দাড়ি নেই', 'Not Yet Grown': 'এখনো গজায়নি', 'Not provided': 'তথ্য দেওয়া হয়নি',
        'No preference': 'কোনো পছন্দ উল্লেখ করা হয়নি', 'Any': 'যেকোনো', 'Any religion': 'যেকোনো ধর্ম',
        'Any education': 'যেকোনো শিক্ষাগত যোগ্যতা', 'Any profession': 'যেকোনো পেশা',
        'Islamic': 'ইসলামি', 'Practicing': 'ধর্মচর্চা করেন', 'Properly Practicing': 'নিয়মিত ধর্মচর্চা করেন',
        'Regularly': 'নিয়মিত', 'Occasionally': 'মাঝেমধ্যে', 'Moderate': 'মাঝারি', 'Moderately': 'মাঝারিভাবে',
        'Yes': 'হ্যাঁ', 'No': 'না', 'Not specified': 'উল্লেখ করা হয়নি',
        'Primary': 'প্রাথমিক', 'Secondary': 'মাধ্যমিক', 'Higher Secondary': 'উচ্চমাধ্যমিক',
        'HSC': 'এইচএসসি', 'SSC': 'এসএসসি', 'Bachelor': 'স্নাতক', "Bachelor's": 'স্নাতক',
        'Master': 'স্নাতকোত্তর', "Master's": 'স্নাতকোত্তর', 'Diploma': 'ডিপ্লোমা', 'PhD': 'পিএইচডি',
        'Never': 'কখনো না', 'Occasional': 'মাঝেমধ্যে', 'Non-smoker': 'ধূমপান করেন না', 'Smoker': 'ধূমপান করেন',
        'Rural': 'গ্রামাঞ্চল', 'Urban': 'শহরাঞ্চল', 'Own House': 'নিজস্ব বাড়ি', 'Rented': 'ভাড়া বাসা',
        'Lower': 'নিম্ন', 'Upper': 'উচ্চ', 'Average': 'গড়পড়তা', 'Prefer not to say': 'উল্লেখ করতে অনিচ্ছুক',
        'Prefer non-smoker': 'ধূমপান করেন না এমন জীবনসঙ্গী পছন্দ', 'Personality': 'ব্যক্তিত্ব',
        'Introvert': 'অন্তর্মুখী', 'Extrovert': 'বহির্মুখী', 'Ambivert': 'উভয়মুখী', 'Strict': 'কঠোরভাবে মেনে চলেন', 'Mostly': 'বেশিরভাগ ক্ষেত্রে', 'Basic': 'প্রাথমিক', 'Good': 'ভালো', 'Jaldhaka': 'জলঢাকা', 'Nilphamari': 'নীলফামারী', 'Delduar': 'দেলদুয়ার', 'Tangail': 'টাঙ্গাইল',
        'Quit': 'ধূমপান ছেড়েছেন', 'Never': 'কখনো না', 'Sometimes': 'মাঝেমধ্যে',
        'Daily': 'প্রতিদিন', 'Occasionally': 'মাঝেমধ্যে', 'Yes': 'হ্যাঁ', 'No': 'না'
    };
    const detailKeys = {
        'madhhab': 'মাযহাব', 'prayer_status': 'নামাজ', 'smoking': 'ধূমপান', 'halal_lifestyle': 'হালাল জীবনযাপন',
        'mahram_maintained': 'মাহরাম মেনে চলা', 'islamic_knowledge': 'ইসলামি জ্ঞান',
        'beard_status': 'দাড়ি', 'hijab_status': 'হিজাব', 'smoking_status': 'ধূমপান',
        'personality_type': 'ব্যক্তিত্ব', 'marital_status': 'বৈবাহিক অবস্থা',
        'family_status': 'পারিবারিক অবস্থা', 'family_type': 'পারিবারিক ধরন',
        'highest_education': 'শিক্ষাগত যোগ্যতা', 'profession': 'পেশা',
        'district': 'জেলা', 'upazila': 'উপজেলা', 'division': 'বিভাগ',
        'blood_group': 'রক্তের গ্রুপ', 'complexion': 'গায়ের বর্ণ'
    };

    function translated(value, map) {
        if (isEnglish || value === null || value === undefined) return value;
        const raw = String(value).trim();
        if (map[raw]) return map[raw];

        // Translate field names inside changing compound details, such as lifestyle summaries.
        let result = raw.replace(/([a-z_]+)\s*:\s*/gi, function (whole, key) {
            return (detailKeys[key.toLowerCase()] || key.replace(/_/g, ' ')) + ': ';
        });

        // Translate common units/words in dynamic age, height, weight and lifestyle values.
        // These substitutions are display-only; the underlying profile data remains unchanged.
        result = result
            .replace(/\byears?\b/gi, 'বছর')
            .replace(/\bft\b/gi, 'ফুট')
            .replace(/\binch(?:es)?\b/gi, 'ইঞ্চি')
            .replace(/\bkg\b/gi, 'কেজি')
            .replace(/\bcm\b/gi, 'সেমি')
            .replace(/\bSmoking\b/gi, 'ধূমপান');

        // Translate known values inside composite strings while leaving unknown values intact.
        const allValues = Object.assign({}, valueLabels, labels);
        Object.keys(allValues).sort((a, b) => b.length - a.length).forEach(function (english) {
            const escaped = english.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            result = result.replace(new RegExp('(^|[\\s,:·–-])' + escaped + '(?=$|[\\s,:·–-])', 'g'), function (_, prefix) {
                return prefix + allValues[english];
            });
        });
        return result.replace(/\d+(?:\.\d+)?/g, function (number) {
            return number.replace(/[0-9]/g, digit => '০১২৩৪৫৬৭৮৯'[Number(digit)]);
        });
    }

    function fmt(value) {
        if (value === null || value === undefined || value === '') return '—';
        return (isEnglish ? Math.round(Number(value)) : String(Math.round(Number(value)).toString().replace(/[0-9]/g, digit => '০১২৩৪৫৬৭৮৯'[Number(digit)]))) + '%';
    }

    function statusClass(value) {
        if (value === null || value === undefined) return 'is-na';
        if (Number(value) >= 100) return 'is-match';
        if (Number(value) <= 0) return 'is-no';
        return 'is-partial';
    }

    function safe(value) {
        const div = document.createElement('div');
        div.textContent = value === null || value === undefined || value === '' ? '—' : String(value);
        return div.innerHTML;
    }

    function openModal(data, name) {
        if (!data) return;
        overall.textContent = fmt(data.score);
        if (forward) forward.textContent = fmt(data.score);
        if (reverse) reverse.textContent = '—%';
        title.textContent = name ? name + ' — ' + text('Match Details', 'মিলের বিস্তারিত') : text('Match Details', 'মিলের বিস্তারিত');
        list.innerHTML = '';

        Object.values(data.details || {}).forEach(function (item) {
            const row = document.createElement('div');
            row.className = 'match-detail-row';
            const contribution = Number(item.contribution || 0);
            row.innerHTML =
                '<div class="match-detail-name"><i class="fa-solid ' + String(item.icon || 'fa-circle').replace(/[^a-z0-9-]/gi, '') + '"></i><span>' + safe(translated(item.label || '', labels)) + '</span></div>' +
                '<div class="match-detail-value"><span class="match-detail-label">' + text('You want', 'আপনার পছন্দ') + '</span><strong>' + safe(translated(item.want || 'No preference', valueLabels)) + '</strong></div>' +
                '<div class="match-detail-value"><span class="match-detail-label">' + text('They have', 'তাঁর তথ্য') + '</span><strong>' + safe(translated(item.have || 'Not provided', valueLabels)) + '</strong></div>' +
                '<div class="match-detail-contribution ' + statusClass(item.score) + '"><strong>' + (isEnglish ? Math.round(contribution) : String(Math.round(contribution)).replace(/[0-9]/g, digit => '০১২৩৪৫৬৭৮৯'[Number(digit)])) + '%</strong><span>' + text('Contribution', 'অবদান') + '</span></div>';
            list.appendChild(row);
        });

        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('match-details-open');
    }

    function closeModal() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('match-details-open');
    }

    document.querySelectorAll('.profile-match-details-button').forEach(function (button) {
        button.addEventListener('click', function () {
            let data = null;
            try { data = JSON.parse(button.getAttribute('data-match-details') || 'null'); } catch (e) { data = null; }
            const card = button.closest('.profile-card');
            const name = card ? (card.querySelector('.profile-card-name-row h4') || {}).textContent : '';
            openModal(data, name ? name.trim() : '');
        });
    });

    modal.querySelectorAll('[data-match-close]').forEach(function (element) {
        element.addEventListener('click', closeModal);
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal.classList.contains('is-open')) closeModal();
    });
})();
