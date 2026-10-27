(function () {
    'use strict';

    const config = window.SMART_BUDGET_FINDER || {};
    const form = document.getElementById('budgetSearchForm');
    const grid = document.getElementById('budgetServiceGrid');
    const results = document.getElementById('budgetResults');
    const list = document.getElementById('budgetPackageList');
    const budgetInput = document.getElementById('budgetAmount');
    const liveTotal = document.getElementById('budgetLiveTotal');
    const liveStatus = document.getElementById('budgetLiveStatus');
    const summaryTotal = document.getElementById('budgetSummaryTotal');
    const summaryStatus = document.getElementById('budgetSummaryStatus');
    const addServiceSelect = document.getElementById('addServiceSelect');
    const addServiceBtn = document.getElementById('addServiceBtn');
    const addAllForm = document.getElementById('budgetAddAllForm');
    const selectedProviders = document.getElementById('budgetSelectedProviders');

    const money = (value) => '৳' + Number(value || 0).toLocaleString('en-BD', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });

    const packagesByService = config.packagesByService || {};
    const services = Array.isArray(config.services) ? config.services : [];

    const serviceTranslations = {
        'Photography':'ফটোগ্রাফি', 'Catering':'খাবার ও আপ্যায়ন', 'Decoration':'সাজসজ্জা',
        'Car Rental':'গাড়ি ভাড়া', 'Makeup Artist':'মেকআপ শিল্পী', 'Wedding Planner':'বিয়ের পরিকল্পনাকারী',
        'Convention Center':'কনভেনশন সেন্টার', 'Resort / Lawn':'রিসোর্ট / লন', 'Mehendi Artist':'মেহেদি শিল্পী',
        'Wedding Flowers':'বিয়ের ফুল', 'Basor Ghor Decoration':'বাসর ঘর সাজসজ্জা', 'Wedding Dress':'বিয়ের পোশাক',
        'Groom Dress':'বরের পোশাক', 'Bridal Accessories':'কনের সাজসজ্জার সামগ্রী', 'Sound & Lighting':'সাউন্ড ও লাইটিং',
        'Invitation & Printing':'নিমন্ত্রণপত্র ও মুদ্রণ'
    };
    function translateService(name) {
        return serviceTranslations[name] || name || 'সেবা';
    }

    function updateServiceCardState(input) {
        const label = input.closest('.budget-service-option');
        if (label) label.classList.toggle('is-checked', input.checked);
    }

    document.querySelectorAll('#budgetServiceGrid input[type="checkbox"]').forEach((input) => {
        input.addEventListener('change', () => updateServiceCardState(input));
    });

    const selectAll = document.getElementById('selectAllServices');
    const clearAll = document.getElementById('clearAllServices');

    if (selectAll) {
        selectAll.addEventListener('click', () => {
            document.querySelectorAll('#budgetServiceGrid input[type="checkbox"]').forEach((input) => {
                const serviceId = String(input.value);
                // Select All means all services that currently have at least one active package.
                input.checked = Array.isArray(packagesByService[serviceId]) && packagesByService[serviceId].length > 0;
                updateServiceCardState(input);
            });
        });
    }

    if (clearAll) {
        clearAll.addEventListener('click', () => {
            document.querySelectorAll('#budgetServiceGrid input[type="checkbox"]').forEach((input) => {
                input.checked = false;
                updateServiceCardState(input);
            });
        });
    }

    function packageRows() {
        return list ? Array.from(list.querySelectorAll('.budget-package-row')) : [];
    }

    function selectedOptionData(select) {
        return select && select.selectedOptions[0] ? select.selectedOptions[0] : null;
    }

    function updatePackageRow(row, select) {
        const option = selectedOptionData(select);
        if (!row || !option) return 0;

        const price = Number(option.dataset.price || 0);
        const original = Number(option.dataset.original || price);
        const discount = Number(option.dataset.discount || 0);
        const image = option.dataset.image || '';
        const rating = option.dataset.rating || '0.0';
        const reviews = option.dataset.reviews || '0';

        row.dataset.providerId = option.value;
        row.dataset.price = String(price);

        const title = row.querySelector('.budget-package-copy h3');
        const codeBadge = row.querySelector('.budget-package-copy .package-code-badge');
        const provider = row.querySelector('.budget-package-copy p');
        const details = row.querySelector('.budget-package-copy small');
        const priceBox = row.querySelector('.budget-package-price');
        const imageBox = row.querySelector('.budget-package-image');
        const ratingBox = row.querySelector('.budget-package-rating');

        if (title) title.textContent = option.dataset.name || 'নির্বাচিত প্যাকেজ';
        if (codeBadge) {
            codeBadge.textContent = option.dataset.code || '';
            codeBadge.hidden = !option.dataset.code;
        }
        if (provider) provider.textContent = option.dataset.provider || '';
        if (details) {
            details.textContent = option.dataset.details || '';
            details.style.display = option.dataset.details ? '' : 'none';
        }

        if (imageBox) {
            imageBox.innerHTML = '';
            if (image) {
                const img = document.createElement('img');
                img.src = (config.baseUrl || '../') + 'uploads/services/' + image;
                img.alt = option.dataset.name || 'নির্বাচিত প্যাকেজ';
                img.loading = 'lazy';
                imageBox.appendChild(img);
            } else {
                const icon = document.createElement('i');
                icon.className = 'fa-solid fa-ring';
                imageBox.appendChild(icon);
            }
        }

        if (priceBox) {
            priceBox.innerHTML = '';
            if (discount > 0) {
                const del = document.createElement('del');
                del.textContent = money(original);
                priceBox.appendChild(del);
            }
            const strong = document.createElement('strong');
            strong.textContent = money(price);
            priceBox.appendChild(strong);
            if (discount > 0) {
                const em = document.createElement('em');
                em.textContent = `${discount.toLocaleString('en-BD', { maximumFractionDigits: 2 })}% OFF`;
                priceBox.appendChild(em);
            }
        }

        if (ratingBox) {
            ratingBox.innerHTML = '<i class="fa-solid fa-star"></i> ' + Number(rating).toFixed(1) + '<small>(' + Number(reviews).toLocaleString('en-BD') + ')</small>';
        }

        return price;
    }

    function updateSummary(total) {
        if (!budgetInput) return;
        const budget = Number(budgetInput.value || 0);
        const tolerance = Number(config.tolerance || 0.10);
        const max = budget * (1 + tolerance);
        const difference = budget - total;

        if (summaryTotal) summaryTotal.textContent = money(total);
        if (liveTotal) liveTotal.textContent = money(total);

        const setStatus = (element) => {
            if (!element) return;
            element.classList.remove('within', 'over');
            if (difference >= -0.009) {
                element.classList.add('within');
                element.textContent = money(Math.max(0, difference)) + ' অবশিষ্ট';
            } else {
                element.classList.add('over');
                element.textContent = money(Math.abs(difference)) + ' বেশি';
            }
        };

        setStatus(summaryStatus);

        if (liveStatus) {
            liveStatus.classList.remove('within', 'over');
            if (total <= budget + 0.009) {
                liveStatus.classList.add('within');
                liveStatus.textContent = 'আপনার বাজেটের মধ্যে';
            } else if (total <= max + 0.009) {
                liveStatus.classList.add('over');
                liveStatus.textContent = 'বাজেটের বেশি—তবে অনুমোদিত ১০% সীমার মধ্যে';
            } else {
                liveStatus.classList.add('over');
                liveStatus.textContent = 'অনুমোদিত ১০% সীমার বেশি';
            }
        }
    }

    function recalcTotal() {
        if (!list) return;
        let total = 0;
        packageRows().forEach((row) => {
            const select = row.querySelector('.budget-package-select');
            total += updatePackageRow(row, select);
        });
        updateSummary(total);
    }

    function buildPackageRow(serviceId, packageList) {
        if (!list || !Array.isArray(packageList) || !packageList.length) return null;

        const service = services.find((item) => Number(item.service_id) === Number(serviceId));
        const packageItem = packageList[0];
        const row = document.createElement('article');
        row.className = 'budget-package-row';
        row.dataset.serviceId = String(serviceId);
        row.dataset.serviceName = service ? translateService(service.service_name) : 'সেবা';

        const imageBox = document.createElement('div');
        imageBox.className = 'budget-package-image';
        row.appendChild(imageBox);

        const copy = document.createElement('div');
        copy.className = 'budget-package-copy';
        const serviceLabel = document.createElement('span');
        serviceLabel.textContent = service ? translateService(service.service_name) : 'বিয়ের সেবা';
        const codeBadge = document.createElement('span');
        codeBadge.className = 'package-code-badge';
        codeBadge.hidden = true;
        const title = document.createElement('h3');
        const provider = document.createElement('p');
        const details = document.createElement('small');
        copy.append(serviceLabel, codeBadge, title, provider, details);
        row.appendChild(copy);

        const priceBox = document.createElement('div');
        priceBox.className = 'budget-package-price';
        row.appendChild(priceBox);

        const ratingBox = document.createElement('div');
        ratingBox.className = 'budget-package-rating';
        row.appendChild(ratingBox);

        const actions = document.createElement('div');
        actions.className = 'budget-package-actions';
        const select = document.createElement('select');
        select.className = 'budget-package-select';
        select.dataset.serviceId = String(serviceId);
        select.setAttribute('aria-label', 'প্যাকেজ পরিবর্তন করুন: ' + (service ? translateService(service.service_name) : 'সেবা'));

        packageList.forEach((item) => {
            const option = document.createElement('option');
            const finalPrice = Number(item._final_price || 0);
            option.value = String(item.provider_id);
            option.dataset.price = String(finalPrice);
            option.dataset.original = String(item.price || 0);
            option.dataset.discount = String(item.discount_percent || 0);
            option.dataset.name = item.package_name || item.provider_name || 'Package';
            option.dataset.provider = item.provider_name || '';
            option.dataset.details = item.package_details || '';
            option.dataset.image = item.image || '';
            option.dataset.rating = String(item.rating || 0);
            option.dataset.reviews = String(item.review_count || 0);
            option.dataset.code = item.package_code || '';
            option.textContent = (item.package_name || item.provider_name || 'Package') + ' — ' + money(finalPrice);
            select.appendChild(option);
        });

        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'budget-remove-btn';
        remove.dataset.removeService = String(serviceId);
        remove.title = 'এই সেবাটি সরান';
        remove.innerHTML = '<i class="fa-solid fa-xmark"></i>';

        actions.append(select, remove);
        row.appendChild(actions);
        list.appendChild(row);

        select.addEventListener('change', recalcTotal);
        remove.addEventListener('click', removeServiceRow);
        updatePackageRow(row, select);
        return row;
    }

    function removeServiceRow(event) {
        const button = event.currentTarget;
        const row = button.closest('.budget-package-row');
        if (!row) return;
        const serviceId = String(row.dataset.serviceId || '');
        row.remove();

        if (addServiceSelect && serviceId && !Array.from(addServiceSelect.options).some((option) => option.value === serviceId)) {
            const service = services.find((item) => String(item.service_id) === serviceId);
            if (service && Array.isArray(packagesByService[serviceId]) && packagesByService[serviceId].length) {
                const option = document.createElement('option');
                option.value = serviceId;
                option.textContent = service.service_name;
                addServiceSelect.appendChild(option);
            }
        }

        const checkbox = grid ? grid.querySelector(`input[value="${CSS.escape(serviceId)}"]`) : null;
        if (checkbox) {
            checkbox.checked = false;
            updateServiceCardState(checkbox);
        }
        recalcTotal();
    }

    document.querySelectorAll('.budget-package-select').forEach((select) => {
        select.addEventListener('change', recalcTotal);
    });

    document.querySelectorAll('[data-remove-service]').forEach((button) => {
        button.addEventListener('click', removeServiceRow);
    });

    if (budgetInput) budgetInput.addEventListener('input', recalcTotal);

    if (addServiceBtn && addServiceSelect) {
        addServiceBtn.addEventListener('click', () => {
            const serviceId = String(addServiceSelect.value || '');
            const packageList = packagesByService[serviceId];
            if (!serviceId || !Array.isArray(packageList) || !packageList.length) return;

            if (packageRows().some((row) => String(row.dataset.serviceId) === serviceId)) return;

            const checkbox = grid ? grid.querySelector(`input[value="${CSS.escape(serviceId)}"]`) : null;
            if (checkbox) {
                checkbox.checked = true;
                updateServiceCardState(checkbox);
            }

            buildPackageRow(serviceId, packageList);
            const optionToRemove = Array.from(addServiceSelect.options).find((option) => option.value === serviceId);
            if (optionToRemove) optionToRemove.remove();
            addServiceSelect.value = '';
            recalcTotal();
        });
    }

    function syncSelectedProviders() {
        if (!selectedProviders) return;
        selectedProviders.innerHTML = '';
        packageRows().forEach((row) => {
            const providerId = Number(row.dataset.providerId || 0);
            const serviceId = Number(row.dataset.serviceId || 0);
            if (!providerId || !serviceId) return;
            const providerInput = document.createElement('input');
            providerInput.type = 'hidden';
            providerInput.name = 'provider_ids[]';
            providerInput.value = String(providerId);
            providerInput.dataset.serviceId = String(serviceId);
            selectedProviders.appendChild(providerInput);
        });
    }

    if (addAllForm) {
        addAllForm.addEventListener('submit', (event) => {
            syncSelectedProviders();
            if (!selectedProviders || !selectedProviders.children.length) {
                event.preventDefault();
            }
        });
    }

    document.querySelectorAll('.budget-guest-cart-btn').forEach((button) => {
        button.addEventListener('click', () => {
            // Use the actual rendered page language as the source of truth.
            // SMART_BUDGET_FINDER may contain stale/mismatched language config on some sessions.
            const pageLanguage = String(document.documentElement.lang || config.language || 'bn').toLowerCase();
            const isEnglish = pageLanguage === 'en' || pageLanguage.startsWith('en-');
            const popup = {
                title: isEnglish ? 'Login Required' : 'লগইন প্রয়োজন',
                message: isEnglish
                    ? 'Please log in or register to access all available services.'
                    : 'সকল সেবা ব্যবহার করতে লগইন করুন অথবা নিবন্ধন করুন।',
                login: isEnglish ? 'Login / Register' : 'লগইন / নিবন্ধন',
                continue: isEnglish ? 'Continue Browsing' : 'ব্রাউজিং চালিয়ে যান'
            };
            if (window.Swal) {
                window.Swal.fire({
                    icon: 'info',
                    title: popup.title,
                    text: popup.message,
                    confirmButtonText: popup.login,
                    showCancelButton: true,
                    cancelButtonText: popup.continue,
                    confirmButtonColor: '#0f766e'
                }).then((result) => {
                    if (result.isConfirmed) window.location.href = (config.baseUrl || '../') + 'login.php';
                });
            } else {
                window.alert(popup.message);
            }
        });
    });

    recalcTotal();
})();
