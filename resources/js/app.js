// The header's theme menu (<x-kit.theme-picker>): light, dark or as the device has it, applied to <html data-theme> at once
// and kept in a plain cookie for the next pages (partials/head applies it before paint, the base reads it); "auto" forgets
// it, and the device's scheme decides.
document.querySelectorAll('details[data-kit="theme-picker"]').forEach((menu) => menu.addEventListener('click', (event) => {
    const choice = event.target.closest('[data-theme-choice]');
    if (!choice) return;
    const theme = choice.dataset.themeChoice;
    if (theme === 'auto') delete document.documentElement.dataset.theme;
    else document.documentElement.dataset.theme = theme;
    document.cookie = theme === 'auto' ? 'theme=; path=/; max-age=0' : `theme=${theme}; path=/; max-age=31536000; samesite=lax`;
    menu.dataset.current = theme;
    menu.querySelectorAll('[data-theme-choice]').forEach((button) => (button === choice ? button.setAttribute('aria-current', 'true') : button.removeAttribute('aria-current')));
    const summary = menu.querySelector('summary');
    summary.setAttribute('aria-label', summary.getAttribute('aria-label').replace(/: .*$/, `: ${choice.dataset.label}`));
    menu.removeAttribute('open');
}));

// The header's menus (the theme's, and the phone's menu of links; each a <details>) close on a click anywhere else, as a menu does.
document.addEventListener('click', (event) => document.querySelectorAll('details:is([data-kit="theme-picker"], [data-kit-part="site-header-menu"])[open]').forEach((menu) => menu.contains(event.target) || menu.removeAttribute('open')));
