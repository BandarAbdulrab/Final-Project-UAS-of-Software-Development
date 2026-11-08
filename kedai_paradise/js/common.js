/**
 * Kedai Paradise Common JS Utilities
 */

function formatRupiah(n) {
    return 'Rp' + Number(n).toLocaleString('id-ID');
}

function t(key) {
    if (window.I18N && window.I18N[key]) {
        return window.I18N[key];
    }
    return key;
}

function showToast(message, type = 'success') {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'fixed bottom-5 right-5 z-50 flex flex-col gap-2';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `px-4 py-3 rounded-lg shadow-lg text-white text-sm transition-all transform translate-y-2 opacity-0 ${
        type === 'success' ? 'bg-[#1B4D3E]' : 'bg-red-600'
    }`;
    toast.textContent = message;
    container.appendChild(toast);

    setTimeout(() => {
        toast.classList.remove('translate-y-2', 'opacity-0');
    }, 10);

    setTimeout(() => {
        toast.classList.add('translate-y-2', 'opacity-0');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

async function apiFetch(url, options = {}) {
    const defaultHeaders = {
        'Content-Type': 'application/json',
        'X-CSRF-Token': window.CSRF_TOKEN || ''
    };

    options.headers = { ...defaultHeaders, ...(options.headers || {}) };

    const response = await fetch(url, options);
    const data = await response.json();

    if (!response.ok || data.success === false) {
        throw new Error(data.message || 'Terjadi kesalahan pada server.');
    }

    return data;
}