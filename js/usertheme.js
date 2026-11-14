// Switches between light and dark mode and remembers the choice.
document.addEventListener('DOMContentLoaded', () => {
    const themeToggle = document.getElementById('themeToggle');
    if (!themeToggle) {
        return;
    }

    function setTheme(isDark) {
        document.documentElement.classList.toggle('dark-mode', isDark);
        themeToggle.setAttribute('aria-checked', String(isDark));
        try {
            localStorage.setItem('theme', isDark ? 'dark' : 'light');
        } catch (error) {
            console.warn('Theme preference could not be saved:', error);
        }
    }

    themeToggle.addEventListener('click', () => {
        setTheme(!document.documentElement.classList.contains('dark-mode'));
    });

    let saved = null;
    try {
        saved = localStorage.getItem('theme');
    } catch (error) {
        saved = null;
    }
    setTheme(saved === 'dark');
});
