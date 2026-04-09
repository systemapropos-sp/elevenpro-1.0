/**
 * ElevenPro POS - JavaScript
 * https://elevenpropos.com
 */

// API Configuration
const API_BASE_URL = window.location.origin + '/api';

// Toast notification
function showToast(message, type = 'info') {
    const toast = document.getElementById('toast');
    if (!toast) return;
    
    toast.textContent = message;
    toast.className = `toast ${type}`;
    toast.classList.add('show');
    
    setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
}

// API Request helper
async function apiRequest(endpoint, options = {}) {
    const url = API_BASE_URL + endpoint;
    
    const defaultOptions = {
        headers: {
            'Content-Type': 'application/json'
        }
    };
    
    // Add auth token if available
    const token = localStorage.getItem('token');
    if (token) {
        defaultOptions.headers['Authorization'] = `Bearer ${token}`;
    }
    
    const config = {
        ...defaultOptions,
        ...options,
        headers: {
            ...defaultOptions.headers,
            ...options.headers
        }
    };
    
    // Handle FormData
    if (options.body instanceof FormData) {
        delete config.headers['Content-Type'];
    } else if (typeof options.body === 'object' && !(options.body instanceof FormData)) {
        config.body = JSON.stringify(options.body);
    }
    
    try {
        const response = await fetch(url, config);
        const data = await response.json();
        
        if (!response.ok) {
            throw new Error(data.message || 'Error en la petición');
        }
        
        return data;
    } catch (error) {
        console.error('API Error:', error);
        throw error;
    }
}

// API Helper functions
function apiGet(endpoint) {
    return apiRequest(endpoint, { method: 'GET' });
}

function apiPost(endpoint, body) {
    return apiRequest(endpoint, { method: 'POST', body });
}

function apiPut(endpoint, body) {
    return apiRequest(endpoint, { method: 'PUT', body });
}

function apiDelete(endpoint) {
    return apiRequest(endpoint, { method: 'DELETE' });
}

// Format currency
function formatCurrency(amount) {
    return '$' + parseFloat(amount).toFixed(2);
}

// Format date
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('es-MX', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit'
    });
}

// Format datetime
function formatDateTime(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('es-MX', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Debounce function
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Generate unique ID
function generateId() {
    return Date.now().toString(36) + Math.random().toString(36).substr(2);
}

// Validate email
function isValidEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

// Validate phone
function isValidPhone(phone) {
    const re = /^[0-9\s\-\+\(\)]{8,20}$/;
    return re.test(phone);
}

// Print element
function printElement(elementId) {
    const element = document.getElementById(elementId);
    if (!element) return;
    
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
        <head>
            <title>Imprimir</title>
            <style>
                body { font-family: Arial, sans-serif; }
                @media print { .no-print { display: none; } }
            </style>
        </head>
        <body>
            ${element.innerHTML}
        </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
}

// Download file
function downloadFile(content, filename, type = 'text/plain') {
    const blob = new Blob([content], { type });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}

// Copy to clipboard
async function copyToClipboard(text) {
    try {
        await navigator.clipboard.writeText(text);
        showToast('Copiado al portapapeles', 'success');
    } catch (err) {
        showToast('Error al copiar', 'error');
    }
}

// Local storage helpers
const storage = {
    set: (key, value) => {
        localStorage.setItem(key, JSON.stringify(value));
    },
    get: (key, defaultValue = null) => {
        const item = localStorage.getItem(key);
        return item ? JSON.parse(item) : defaultValue;
    },
    remove: (key) => {
        localStorage.removeItem(key);
    },
    clear: () => {
        localStorage.clear();
    }
};

// Session storage helpers
const session = {
    set: (key, value) => {
        sessionStorage.setItem(key, JSON.stringify(value));
    },
    get: (key, defaultValue = null) => {
        const item = sessionStorage.getItem(key);
        return item ? JSON.parse(item) : defaultValue;
    },
    remove: (key) => {
        sessionStorage.removeItem(key);
    }
};

// Confirm dialog
function confirmDialog(message, onConfirm, onCancel = null) {
    if (confirm(message)) {
        onConfirm();
    } else if (onCancel) {
        onCancel();
    }
}

// Loading overlay
function showLoading(message = 'Cargando...') {
    const overlay = document.createElement('div');
    overlay.id = 'loadingOverlay';
    overlay.className = 'loading-overlay';
    overlay.innerHTML = `
        <div class="loading-spinner">
            <i class="fas fa-spinner fa-spin"></i>
            <span>${message}</span>
        </div>
    `;
    document.body.appendChild(overlay);
}

function hideLoading() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.remove();
    }
}

// Pagination helper
function renderPagination(container, currentPage, totalPages, onPageChange) {
    if (totalPages <= 1) return;
    
    let html = '<div class="pagination">';
    
    // Previous button
    html += `
        <button class="page-btn ${currentPage === 1 ? 'disabled' : ''}" 
                onclick="${currentPage > 1 ? onPageChange(currentPage - 1) : ''}">
            <i class="fas fa-chevron-left"></i>
        </button>
    `;
    
    // Page numbers
    const startPage = Math.max(1, currentPage - 2);
    const endPage = Math.min(totalPages, currentPage + 2);
    
    if (startPage > 1) {
        html += `<button class="page-btn" onclick="${onPageChange(1)}">1</button>`;
        if (startPage > 2) {
            html += `<span class="page-ellipsis">...</span>`;
        }
    }
    
    for (let i = startPage; i <= endPage; i++) {
        html += `
            <button class="page-btn ${i === currentPage ? 'active' : ''}" 
                    onclick="${onPageChange(i)}">
                ${i}
            </button>
        `;
    }
    
    if (endPage < totalPages) {
        if (endPage < totalPages - 1) {
            html += `<span class="page-ellipsis">...</span>`;
        }
        html += `<button class="page-btn" onclick="${onPageChange(totalPages)}">${totalPages}</button>`;
    }
    
    // Next button
    html += `
        <button class="page-btn ${currentPage === totalPages ? 'disabled' : ''}" 
                onclick="${currentPage < totalPages ? onPageChange(currentPage + 1) : ''}">
            <i class="fas fa-chevron-right"></i>
        </button>
    `;
    
    html += '</div>';
    container.innerHTML = html;
}

// Export functions for use in other scripts
window.POS = {
    showToast,
    apiRequest,
    apiGet,
    apiPost,
    apiPut,
    apiDelete,
    formatCurrency,
    formatDate,
    formatDateTime,
    debounce,
    generateId,
    isValidEmail,
    isValidPhone,
    printElement,
    downloadFile,
    copyToClipboard,
    storage,
    session,
    confirmDialog,
    showLoading,
    hideLoading,
    renderPagination
};
