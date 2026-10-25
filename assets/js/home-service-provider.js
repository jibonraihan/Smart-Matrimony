(function () {
    'use strict';

    const stylesheet = document.createElement('link');
    stylesheet.rel = 'stylesheet';
    stylesheet.href = (window.SMART_SERVICE_BASE_URL || '/') + 'assets/css/home-service-provider.css?v=1';
    document.head.appendChild(stylesheet);

    const modal = document.getElementById('homeServiceProviderModal');
    const directoryNode = document.getElementById('homeManagerDirectoryData');
    if (!modal || !directoryNode) return;

    let directory = {};
    try { directory = JSON.parse(directoryNode.textContent || '{}'); } catch (error) { return; }

    const language = window.SMART_HOME_LANGUAGE === 'en' ? 'en' : 'bn';
    const t = (en, bn) => language === 'en' ? en : bn;
    const managers = Object.values(directory).filter((manager) => manager && Number(manager.manager_id) > 0);
    const managerSelect = modal.querySelector('[data-home-manager-select]');
    const packageSelect = modal.querySelector('[data-home-manager-package-select]');
    const avatar = modal.querySelector('[data-home-provider-avatar]');
    const code = modal.querySelector('[data-home-provider-code]');
    const name = modal.querySelector('[data-home-provider-name]');
    const count = modal.querySelector('[data-home-provider-count]');
    const mobile = modal.querySelector('[data-home-provider-mobile]');
    const email = modal.querySelector('[data-home-provider-email]');
    const loginNote = modal.querySelector('[data-home-provider-login-note]');
    const copyButtons = Array.from(modal.querySelectorAll('[data-home-provider-copy]'));
    const loggedIn = modal.dataset.loggedIn === '1';
    const dialog = modal.querySelector('.home-service-provider-dialog');
    let lastTrigger = null;
    let requestVersion = 0;
    let previousOverflow = '';

    const setText = () => {
        modal.querySelectorAll('[data-hsp-text]').forEach((node) => {
            const key = node.dataset.hspText;
            const strings = {
                eyebrow: ["Provider's Contract", 'প্রোভাইডারের সাথে চুক্তি'],
                title: ["Provider's Contract", 'প্রোভাইডারের সাথে চুক্তি'],
                subtitle: ["Explore the manager's details and packages.", 'ম্যানেজারের তথ্য ও প্যাকেজগুলো দেখুন।'],
                managerLabel: ['Select Manager', 'ম্যানেজার নির্বাচন করুন'],
                packagesLabel: ["Manager's Packages", 'ম্যানেজারের প্যাকেজসমূহ'],
                mobileLabel: ["Manager's Mobile", 'ম্যানেজারের মোবাইল'],
                emailLabel: ["Manager's Email", 'ম্যানেজারের ইমেইল'],
                packageHint: ['Choose a package to view its card.', 'প্যাকেজ নির্বাচন করলে সেই প্যাকেজ কার্ডটি দেখাবে।'],
                close: ['Close', 'বন্ধ করুন']
            };
            if (strings[key]) node.textContent = strings[key][language === 'en' ? 0 : 1];
        });
        managerSelect.setAttribute('aria-label', t('Select manager', 'ম্যানেজার নির্বাচন করুন'));
        packageSelect.setAttribute('aria-label', t("Select manager's package", 'ম্যানেজারের প্যাকেজ নির্বাচন করুন'));
        modal.querySelectorAll('[data-home-provider-copy]').forEach((button) => {
            const field = button.dataset.homeProviderCopy;
            const label = field === 'mobile' ? t('Copy mobile', 'মোবাইল কপি করুন') : t('Copy email', 'ইমেইল কপি করুন');
            button.setAttribute('aria-label', label);
            button.title = label;
        });
        modal.querySelector('[data-home-provider-close]').setAttribute('aria-label', t('Close', 'বন্ধ করুন'));
    };

    function setAvatar(manager) {
        avatar.replaceChildren();
        if (manager && manager.photo) {
            const image = document.createElement('img');
            image.src = manager.photo;
            image.alt = t('Manager profile photo', 'ম্যানেজারের প্রোফাইল ছবি');
            image.loading = 'lazy';
            image.addEventListener('error', () => setFallbackAvatar(), { once: true });
            avatar.appendChild(image);
        } else setFallbackAvatar();
    }

    function setFallbackAvatar() {
        avatar.replaceChildren();
        const icon = document.createElement('i');
        icon.className = 'fa-solid fa-user-tie';
        icon.setAttribute('aria-hidden', 'true');
        avatar.appendChild(icon);
    }

    function resetContact() {
        mobile.textContent = t('Loading…', 'লোড হচ্ছে…');
        email.textContent = t('Loading…', 'লোড হচ্ছে…');
        mobile.dataset.copyValue = '';
        email.dataset.copyValue = '';
        copyButtons.forEach((button) => { button.hidden = true; });
        loginNote.hidden = true;
    }

    function guestContact() {
        const prompt = t('Please log in / register', 'লগইন / রেজিস্ট্রার করুন');
        mobile.textContent = prompt;
        email.textContent = prompt;
        copyButtons.forEach((button) => { button.hidden = true; });
        loginNote.hidden = false;
        loginNote.textContent = t('Log in or register to view the manager’s mobile and email.', 'ম্যানেজারের মোবাইল ও ইমেইল দেখতে লগইন অথবা রেজিস্ট্রেশন করুন।');
    }

    async function loadContact(managerId) {
        const thisRequest = ++requestVersion;
        if (!loggedIn) { guestContact(); return; }
        resetContact();
        try {
            const response = await fetch(modal.dataset.contactEndpoint, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8', 'X-Requested-With': 'XMLHttpRequest' },
                body: new URLSearchParams({ manager_id: String(managerId) }).toString()
            });
            const payload = await response.json();
            if (thisRequest !== requestVersion) return;
            if (!response.ok || !payload.success) throw new Error('Contact details unavailable');
            mobile.textContent = payload.mobile || t('Not provided', 'তথ্য দেওয়া নেই');
            email.textContent = payload.email || t('Not provided', 'তথ্য দেওয়া নেই');
            mobile.dataset.copyValue = payload.mobile || '';
            email.dataset.copyValue = payload.email || '';
            copyButtons.forEach((button) => {
                const value = button.dataset.homeProviderCopy === 'mobile' ? payload.mobile : payload.email;
                button.hidden = !value;
            });
        } catch (error) {
            if (thisRequest !== requestVersion) return;
            mobile.textContent = t('Unable to load', 'তথ্য পাওয়া যায়নি');
            email.textContent = t('Unable to load', 'তথ্য পাওয়া যায়নি');
            copyButtons.forEach((button) => { button.hidden = true; });
        }
    }

    function syncManager(managerId) {
        const manager = directory[String(managerId)];
        if (!manager) return;
        code.textContent = manager.manager_code || ('SM-' + String(manager.manager_id).padStart(6, '0'));
        name.textContent = manager.name || t('Event Manager', 'ইভেন্ট ম্যানেজার');
        const packages = Array.isArray(manager.packages) ? manager.packages : [];
        count.textContent = language === 'en'
            ? `${packages.length} ${packages.length === 1 ? 'package' : 'packages'}`
            : `${packages.length} টি প্যাকেজ`;
        setAvatar(manager);

        packageSelect.replaceChildren();
        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = t('Select a package', 'প্যাকেজ নির্বাচন করুন');
        packageSelect.appendChild(placeholder);
        packages.forEach((item) => {
            const option = document.createElement('option');
            option.value = String(item.provider_id);
            option.textContent = (item.package_code ? item.package_code + ' - ' : '') + item.package_name;
            packageSelect.appendChild(option);
        });
        packageSelect.disabled = packages.length === 0;
        if (packages.length === 0) {
            placeholder.textContent = t('No active packages', 'কোনো সক্রিয় প্যাকেজ নেই');
        }
        loadContact(manager.manager_id);
    }

    function openModal(providerId, trigger) {
        lastTrigger = trigger || null;
        managerSelect.replaceChildren();
        const managerPlaceholder = document.createElement('option');
        managerPlaceholder.value = '';
        managerPlaceholder.textContent = t('Select a manager', 'ম্যানেজার নির্বাচন করুন');
        managerSelect.appendChild(managerPlaceholder);
        managers.sort((a, b) => String(a.name || '').localeCompare(String(b.name || ''))).forEach((manager) => {
            const option = document.createElement('option');
            option.value = String(manager.manager_id);
            option.textContent = manager.name || ('SM-' + String(manager.manager_id).padStart(6, '0'));
            managerSelect.appendChild(option);
        });
        let selectedManager = providerId
            ? (managers.find((manager) => (manager.packages || []).some((item) => Number(item.provider_id) === Number(providerId))) || null)
            : (managers[0] || null);
        if (selectedManager) {
            managerSelect.value = String(selectedManager.manager_id);
            syncManager(selectedManager.manager_id);
            const selectedPackage = (selectedManager.packages || []).find((item) => Number(item.provider_id) === Number(providerId));
            if (selectedPackage) packageSelect.value = String(selectedPackage.provider_id);
        } else {
            code.textContent = '';
            name.textContent = providerId ? t('No manager is assigned to this package', 'এই প্যাকেজে কোনো ম্যানেজার যুক্ত নেই') : t('No managers available', 'কোনো ম্যানেজার পাওয়া যায়নি');
            count.textContent = '';
            setFallbackAvatar();
            packageSelect.replaceChildren();
            resetContact();
        }
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        window.setTimeout(() => dialog.focus(), 20);
    }

    function closeModal() {
        if (modal.hidden) return;
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = previousOverflow;
        if (lastTrigger && typeof lastTrigger.focus === 'function') lastTrigger.focus();
    }

    setText();
    managers.sort((a, b) => String(a.name || '').localeCompare(String(b.name || '')));
    managerSelect.innerHTML = `<option value="">${t('Select a manager', 'ম্যানেজার নির্বাচন করুন')}</option>`;
    managers.forEach((manager) => {
        const option = document.createElement('option');
        option.value = String(manager.manager_id);
        option.textContent = manager.name || ('SM-' + String(manager.manager_id).padStart(6, '0'));
        managerSelect.appendChild(option);
    });
    managerSelect.addEventListener('change', () => {
        if (managerSelect.value) syncManager(managerSelect.value);
    });
    packageSelect.addEventListener('change', () => {
        const providerId = Number(packageSelect.value);
        if (!providerId) return;
        closeModal();
        document.dispatchEvent(new CustomEvent('sm:show-home-package', { detail: { providerId } }));
    });
    document.addEventListener('sm:open-service-provider', (event) => {
        openModal(Number(event.detail && event.detail.providerId) || 0, event.detail && event.detail.trigger);
    });
    modal.querySelectorAll('[data-home-provider-close]').forEach((button) => button.addEventListener('click', closeModal));
    modal.addEventListener('click', (event) => { if (event.target === modal) closeModal(); });
    document.addEventListener('keydown', (event) => { if (!modal.hidden && event.key === 'Escape') closeModal(); });

    copyButtons.forEach((button) => button.addEventListener('click', async () => {
        const source = button.dataset.homeProviderCopy === 'mobile' ? mobile : email;
        const value = source.dataset.copyValue || '';
        if (!value) return;
        try {
            await navigator.clipboard.writeText(value);
        } catch (error) {
            const field = document.createElement('textarea');
            field.value = value;
            field.setAttribute('readonly', 'readonly');
            field.style.position = 'fixed'; field.style.opacity = '0';
            document.body.appendChild(field); field.select();
            try { document.execCommand('copy'); } catch (ignored) { /* clipboard fallback */ }
            field.remove();
        }
        const icon = button.querySelector('i');
        if (icon) {
            icon.className = 'fa-solid fa-check';
            window.setTimeout(() => { icon.className = 'fa-regular fa-copy'; }, 1200);
        }
    }));
})();
