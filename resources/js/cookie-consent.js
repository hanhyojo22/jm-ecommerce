const consent = document.querySelector('[data-cookie-consent]');

if (consent) {
    const dialog = consent.querySelector('[data-cookie-dialog]');
    const functional = dialog.querySelector('[name="functional"]');
    let opener;

    const openPreferences = (button) => {
        opener = button;
        dialog.showModal();
    };

    dialog.addEventListener('close', () => opener?.focus());

    const savePreferences = async (allowFunctional) => {
        const buttons = [...consent.querySelectorAll('[data-cookie-action]')];
        buttons.forEach((button) => { button.disabled = true; });
        consent.querySelectorAll('[data-cookie-error]').forEach((error) => {
            error.textContent = '';
            error.classList.add('hidden');
        });

        try {
            const response = await fetch(consent.dataset.saveUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': consent.dataset.csrf },
                body: JSON.stringify({ functional: Number(allowFunctional), analytics: 0, marketing: 0 }),
            });

            if (!response.ok) {
                throw new Error('Your preference could not be saved. Please try again.');
            }

            if (!allowFunctional) {
                try {
                    Object.keys(window.localStorage).filter((key) => key.startsWith('seller-dashboard:'))
                        .forEach((key) => window.localStorage.removeItem(key));
                } catch {
                    // The server has still withdrawn functional consent if browser storage is unavailable.
                }
            }

            window.location.reload();
        } catch (error) {
            const message = (dialog.open ? dialog : consent.querySelector('[data-cookie-banner]'))?.querySelector('[data-cookie-error]');
            if (message) {
                message.textContent = error.message;
                message.classList.remove('hidden');
            }
            buttons.forEach((button) => { button.disabled = false; });
        }
    };

    document.querySelectorAll('[data-cookie-preferences-open]').forEach((button) => {
        button.addEventListener('click', () => openPreferences(button));
    });

    consent.querySelectorAll('[data-cookie-action]').forEach((button) => {
        button.addEventListener('click', () => {
            switch (button.dataset.cookieAction) {
                case 'customize': openPreferences(button); break;
                case 'close': dialog.close(); break;
                case 'accept': savePreferences(true); break;
                case 'reject': savePreferences(false); break;
                case 'save': savePreferences(functional.checked); break;
            }
        });
    });
}
