(function () {
    'use strict';

    const modal = document.getElementById('contractProviderModal');
    const directoryNode = document.getElementById('homeManagerDirectoryData');
    if (!modal || !directoryNode) return;

    let directory = {};
    try {
        directory = JSON.parse(directoryNode.textContent || '{}');
    } catch (error) {
        return;
    }

    const dialog = modal.querySelector('.contract-provider-dialog');
    const closeButtons = modal.querySelectorAll('[data-contract-close]');
    const avatar = modal.querySelector('[data-manager-avatar]');
    const managerCode = modal.querySelector('[data-manager-code]');
    const managerName = modal.querySelector('[data-manager-name]');
    const managerPackageCount = modal.querySelector('[data-manager-package-count]');
    const packageMenu = modal.querySelector('[data-manager-packages]');
    const packageMenuToggle = modal.querySelector('[data-package-menu-toggle]');
    const packageSelected = modal.querySelector('[data-package-selected]');
    const packageHint = modal.querySelector('[data-package-hint]');
    const mobileValue = modal.querySelector('[data-manager-mobile]');
    const emailValue = modal.querySelector('[data-manager-email]');
    const loginNote = modal.querySelector('[data-manager-login-note]');
    const copyButtons = modal.querySelectorAll('[data-copy-field]');
    const isLoggedIn = modal.dataset.loggedIn === '1';
    let lastTrigger = null;
    let activeManager = null;
    let previousBodyOverflow = '';

    function resetContact() {
        mobileValue.textContent = 'লোড হচ্ছে…';
        emailValue.textContent = 'লোড হচ্ছে…';
        loginNote.hidden = true;
        copyButtons.forEach((button) => { button.hidden = true; });
        mobileValue.dataset.copyValue = '';
        emailValue.dataset.copyValue = '';
    }

    function setContactForGuest() {
        mobileValue.textContent = 'লগইন / রেজিস্ট্রার করুন';
        emailValue.textContent = 'লগইন / রেজিস্ট্রার করুন';
        loginNote.hidden = false;
        modal.querySelectorAll('[data-login-link]').forEach((link) => { link.href = modal.dataset.loginUrl; });
        modal.querySelectorAll('[data-register-link]').forEach((link) => { link.href = modal.dataset.registerUrl; });
    }

    async function loadContact(managerId) {
        if (!isLoggedIn) {
            setContactForGuest();
            return;
        }

        resetContact();
        try {
            const response = await fetch(modal.dataset.contactEndpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8', 'X-Requested-With': 'XMLHttpRequest' },
                body: new URLSearchParams({ manager_id: String(managerId) }).toString()
            });
            const payload = await response.json();
            if (!response.ok || !payload.success) throw new Error(payload.message || 'Contact details unavailable');

            mobileValue.textContent = payload.mobile || 'তথ্য দেওয়া নেই';
            emailValue.textContent = payload.email || 'তথ্য দেওয়া নেই';
            mobileValue.dataset.copyValue = payload.mobile || '';
            emailValue.dataset.copyValue = payload.email || '';
            copyButtons.forEach((button) => {
                const field = button.dataset.copyField;
                const value = field === 'mobile' ? payload.mobile : payload.email;
                button.hidden = !value;
            });
        } catch (error) {
            mobileValue.textContent = 'তথ্য পাওয়া যায়নি';
            emailValue.textContent = 'তথ্য পাওয়া যায়নি';
            loginNote.hidden = true;
        }
    }

    function openModal(managerId, trigger) {
        const manager = directory[String(managerId)];
        if (!manager) return;
        activeManager = manager;
        lastTrigger = trigger || null;

        managerCode.textContent = manager.manager_code || ('SM-' + String(manager.manager_id).padStart(6, '0'));
        managerName.textContent = manager.name || 'Event Manager';
        const packageCount = Array.isArray(manager.packages) ? manager.packages.length : 0;
        managerPackageCount.textContent = packageCount + (packageCount === 1 ? ' টি প্যাকেজ' : ' টি প্যাকেজ');

        avatar.replaceChildren();
        if (manager.photo) {
            const image = document.createElement('img');
            image.src = manager.photo;
            image.alt = 'Manager profile photo';
            image.loading = 'lazy';
            image.addEventListener('error', () => {
                avatar.replaceChildren();
                const icon = document.createElement('i');
                icon.className = 'fa-solid fa-user-tie';
                icon.setAttribute('aria-hidden', 'true');
                avatar.appendChild(icon);
            }, { once: true });
            avatar.appendChild(image);
        } else {
            const icon = document.createElement('i');
            icon.className = 'fa-solid fa-user-tie';
            icon.setAttribute('aria-hidden', 'true');
            avatar.appendChild(icon);
        }

        packageMenu.replaceChildren();
        packageSelected.textContent = 'প্যাকেজ নির্বাচন করুন';
        packageMenuToggle.setAttribute('aria-expanded', 'false');
        packageMenu.hidden = true;
        (manager.packages || []).forEach((item) => {
            const option = document.createElement('button');
            option.type = 'button';
            option.className = 'contract-provider-package-option';
            option.setAttribute('role', 'option');
            option.dataset.providerId = String(item.provider_id);
            option.textContent = (item.package_code ? item.package_code + ' · ' : '') + item.package_name;
            option.title = item.service_name || '';
            option.addEventListener('click', () => {
                const providerId = Number(option.dataset.providerId);
                packageSelected.textContent = option.textContent;
                packageMenu.hidden = true;
                packageMenuToggle.setAttribute('aria-expanded', 'false');
                closeModal();
                document.dispatchEvent(new CustomEvent('sm:show-home-package', { detail: { providerId } }));
            });
            packageMenu.appendChild(option);
        });
        packageHint.textContent = manager.packages && manager.packages.length
            ? 'প্যাকেজ নির্বাচন করলে সংশ্লিষ্ট কার্ডে নিয়ে যাবে।'
            : 'এই ম্যানেজারের কোনো সক্রিয় প্যাকেজ পাওয়া যায়নি।';
        packageMenuToggle.disabled = !(manager.packages && manager.packages.length);

        if (isLoggedIn) resetContact(); else setContactForGuest();
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        previousBodyOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        window.setTimeout(() => dialog.focus(), 20);
        loadContact(manager.manager_id);
    }

    function closeModal() {
        if (modal.hidden) return;
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = previousBodyOverflow;
        activeManager = null;
        if (lastTrigger && typeof lastTrigger.focus === 'function') lastTrigger.focus();
    }

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-contract-provider]');
        if (trigger) {
            const managerId = Number(trigger.dataset.managerId);
            if (managerId > 0) openModal(managerId, trigger);
        }
    });

    closeButtons.forEach((button) => button.addEventListener('click', closeModal));
    modal.addEventListener('click', (event) => {
        if (event.target === modal) closeModal();
    });
    document.addEventListener('keydown', (event) => {
        if (modal.hidden) return;
        if (event.key === 'Escape') closeModal();
        if (event.key === 'Tab') {
            const focusable = Array.from(dialog.querySelectorAll('button:not([hidden]):not(:disabled), a[href]'))
                .filter((element) => element.offsetParent !== null);
            if (!focusable.length) return;
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault(); last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault(); first.focus();
            }
        }
    });

    packageMenuToggle.addEventListener('click', () => {
        const shouldOpen = packageMenu.hidden;
        packageMenu.hidden = !shouldOpen;
        packageMenuToggle.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
    });
    document.addEventListener('click', (event) => {
        if (!modal.contains(event.target)) return;
        if (!event.target.closest('[data-manager-package-picker]')) {
            packageMenu.hidden = true;
            packageMenuToggle.setAttribute('aria-expanded', 'false');
        }
    });

    copyButtons.forEach((button) => button.addEventListener('click', async () => {
        const field = button.dataset.copyField;
        const source = field === 'mobile' ? mobileValue : emailValue;
        const value = source.dataset.copyValue || '';
        if (!value) return;
        try {
            await navigator.clipboard.writeText(value);
            const icon = button.querySelector('i');
            if (icon) {
                icon.className = 'fa-solid fa-check';
                window.setTimeout(() => { icon.className = 'fa-regular fa-copy'; }, 1200);
            }
        } catch (error) {
            const temporary = document.createElement('textarea');
            temporary.value = value;
            temporary.setAttribute('readonly', 'readonly');
            temporary.style.position = 'fixed';
            temporary.style.opacity = '0';
            document.body.appendChild(temporary);
            temporary.select();
            try { document.execCommand('copy'); } catch (copyError) { /* no-op */ }
            temporary.remove();
        }
    }));
})();
