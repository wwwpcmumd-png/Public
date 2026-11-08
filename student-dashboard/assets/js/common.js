'use strict';

/** Escape text before inserting it into innerHTML. */
function esc(value) {
    return String(value ?? '').replace(/[&<>"']/g, function (c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
}

// Dark Mode Toggle Logic
function toggleDarkMode() {
    const html = document.documentElement;
    const icon = document.getElementById('themeIcon');
    if (html.classList.contains('dark')) {
        html.classList.remove('dark');
        icon.className = 'fa-solid fa-sun text-lg text-amber-500';
    } else {
        html.classList.add('dark');
        icon.className = 'fa-solid fa-moon text-lg';
    }
}

// Custom Notification Toast Function
let toastTimer = null;
function showToast(message, isError) {
    const toast = document.getElementById('toastNotification');
    const toastMsg = document.getElementById('toastMessage');
    const toastIcon = document.getElementById('toastIcon');
    if (!toast) return;
    toastMsg.textContent = message;
    if (toastIcon) {
        toastIcon.className = isError ? 'text-rose-400 text-lg' : 'text-emerald-400 text-lg';
        toastIcon.innerHTML = isError
            ? '<i class="fa-solid fa-circle-exclamation"></i>'
            : '<i class="fa-solid fa-circle-check"></i>';
    }
    toast.classList.remove('translate-y-20', 'opacity-0');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => {
        toast.classList.add('translate-y-20', 'opacity-0');
    }, 3000);
}
