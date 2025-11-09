/**
 * Common JavaScript Functions
 * Shared functionality for both POS and ERP systems
 */

// Global configuration
const MinangConfig = {
    currency: 'QAR',
    currencySymbol: 'QR',
    dateFormat: 'dd/mm/yyyy',
    timeFormat: '12h',
    apiTimeout: 30000,
    syncInterval: 300000, // 5 minutes
};

// Utility Functions
const MinangUtils = {
    // Format currency
    formatCurrency(amount) {
        const formatted = parseFloat(amount || 0).toFixed(2);
        return `${MinangConfig.currencySymbol} ${formatted}`;
    },

    // Format date
    formatDate(dateString, format = null) {
        const date = new Date(dateString);
        if (format === 'time') {
            return date.toLocaleTimeString('en-US', { 
                hour12: true, 
                hour: '2-digit', 
                minute: '2-digit' 
            });
        }
        return date.toLocaleDateString('en-GB'); // dd/mm/yyyy format
    },

    // Format datetime
    formatDateTime(dateString) {
        const date = new Date(dateString);
        return `${this.formatDate(dateString)} ${this.formatDate(dateString, 'time')}`;
    },

    // Sanitize input
    sanitize(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    },

    // Generate random ID
    generateId(prefix = 'id') {
        return `${prefix}_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
    },

    // Debounce function
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },

    // Deep clone object
    deepClone(obj) {
        return JSON.parse(JSON.stringify(obj));
    },

    // Check if mobile device
    isMobile() {
        return window.innerWidth <= 768;
    },

    // Get URL parameter
    getUrlParameter(name) {
        name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
        const regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
        const results = regex.exec(location.search);
        return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
    },

    // Storage functions (fallback for when localStorage is not available)
    setStorage(key, value) {
        try {
            if (typeof(Storage) !== "undefined") {
                localStorage.setItem(key, JSON.stringify(value));
                return true;
            } else {
                // Fallback to cookies or session storage
                document.cookie = `${key}=${JSON.stringify(value)}; path=/`;
                return true;
            }
        } catch (e) {
            console.warn('Storage not available:', e);
            return false;
        }
    },

    getStorage(key) {
        try {
            if (typeof(Storage) !== "undefined") {
                const item = localStorage.getItem(key);
                return item ? JSON.parse(item) : null;
            } else {
                // Fallback to cookies
                const name = key + "=";
                const decodedCookie = decodeURIComponent(document.cookie);
                const ca = decodedCookie.split(';');
                for (let i = 0; i < ca.length; i++) {
                    let c = ca[i];
                    while (c.charAt(0) === ' ') {
                        c = c.substring(1);
                    }
                    if (c.indexOf(name) === 0) {
                        return JSON.parse(c.substring(name.length, c.length));
                    }
                }
                return null;
            }
        } catch (e) {
            console.warn('Storage retrieval failed:', e);
            return null;
        }
    }
};

// Notification System
const MinangNotification = {
    show(message, type = 'info', duration = 3000) {
        const notification = document.createElement('div');
        notification.className = `notification ${type} fade-in`;
        
        const icon = this.getIcon(type);
        notification.innerHTML = `
            <div class="flex items-center">
                <i class="fas fa-${icon} mr-3"></i>
                <span>${MinangUtils.sanitize(message)}</span>
                <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-white hover:text-gray-200">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        // Auto remove
        setTimeout(() => {
            if (notification.parentNode) {
                notification.style.opacity = '0';
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => notification.remove(), 300);
            }
        }, duration);
        
        return notification;
    },

    getIcon(type) {
        const icons = {
            success: 'check-circle',
            error: 'exclamation-circle',
            warning: 'exclamation-triangle',
            info: 'info-circle'
        };
        return icons[type] || 'info-circle';
    },

    success(message, duration) {
        return this.show(message, 'success', duration);
    },

    error(message, duration) {
        return this.show(message, 'error', duration);
    },

    warning(message, duration) {
        return this.show(message, 'warning', duration);
    },

    info(message, duration) {
        return this.show(message, 'info', duration);
    }
};

// Modal System
const MinangModal = {
    create(options = {}) {
        const modal = document.createElement('div');
        modal.className = 'modal-overlay modal-enter';
        modal.innerHTML = `
            <div class="modal-content">
                <div class="p-6">
                    ${options.title ? `
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-800">${options.title}</h3>
                            <button onclick="MinangModal.close(this)" class="text-gray-500 hover:text-gray-700">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    ` : ''}
                    <div class="modal-body">
                        ${options.content || ''}
                    </div>
                    ${options.buttons ? `
                        <div class="flex justify-end space-x-3 mt-6">
                            ${options.buttons}
                        </div>
                    ` : ''}
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Close on overlay click
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                this.close(modal);
            }
        });
        
        return modal;
    },

    close(element) {
        const modal = element.closest ? element.closest('.modal-overlay') : element;
        if (modal) {
            modal.style.opacity = '0';
            setTimeout(() => modal.remove(), 300);
        }
    },

    confirm(message, callback, options = {}) {
        const modal = this.create({
            title: options.title || 'Confirmation',
            content: `<p class="text-gray-600 mb-4">${message}</p>`,
            buttons: `
                <button onclick="MinangModal.close(this)" 
                        class="px-4 py-2 bg-gray-300 hover:bg-gray-400 text-gray-700 rounded-lg font-medium transition-colors">
                    ${options.cancelText || 'Cancel'}
                </button>
                <button onclick="MinangModal.handleConfirm(this, ${callback})" 
                        class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg font-medium transition-colors">
                    ${options.confirmText || 'Confirm'}
                </button>
            `
        });
        
        return modal;
    },

    handleConfirm(element, callback) {
        this.close(element);
        if (typeof callback === 'function') {
            callback();
        }
    },

    alert(message, type = 'info', options = {}) {
        const colors = {
            info: 'text-blue-600',
            success: 'text-green-600',
            warning: 'text-orange-600',
            error: 'text-red-600'
        };
        
        const icons = {
            info: 'info-circle',
            success: 'check-circle',
            warning: 'exclamation-triangle',
            error: 'exclamation-circle'
        };
        
        const modal = this.create({
            title: options.title || type.charAt(0).toUpperCase() + type.slice(1),
            content: `
                <div class="text-center">
                    <i class="fas fa-${icons[type]} text-4xl ${colors[type]} mb-4"></i>
                    <p class="text-gray-600">${message}</p>
                </div>
            `,
            buttons: `
                <button onclick="MinangModal.close(this)" 
                        class="px-6 py-2 bg-primary hover:bg-blue-600 text-white rounded-lg font-medium transition-colors">
                    OK
                </button>
            `
        });
        
        return modal;
    }
};

// API Helper
const MinangAPI = {
    async request(url, options = {}) {
        const defaultOptions = {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            timeout: MinangConfig.apiTimeout
        };
        
        const config = { ...defaultOptions, ...options };
        
        try {
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), config.timeout);
            
            const response = await fetch(url, {
                ...config,
                signal: controller.signal
            });
            
            clearTimeout(timeoutId);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            return data;
            
        } catch (error) {
            console.error('API request failed:', error);
            throw error;
        }
    },

    async get(url, params = {}) {
        const urlParams = new URLSearchParams(params);
        const fullUrl = urlParams.toString() ? `${url}?${urlParams}` : url;
        return this.request(fullUrl);
    },

    async post(url, data = {}) {
        return this.request(url, {
            method: 'POST',
            body: JSON.stringify(data)
        });
    },

    async put(url, data = {}) {
        return this.request(url, {
            method: 'PUT',
            body: JSON.stringify(data)
        });
    },

    async delete(url, data = {}) {
        return this.request(url, {
            method: 'DELETE',
            body: JSON.stringify(data)
        });
    }
};

// Form Helper
const MinangForm = {
    // Serialize form data
    serialize(form) {
        const formData = new FormData(form);
        const data = {};
        
        for (let [key, value] of formData.entries()) {
            if (data[key]) {
                // Handle multiple values (checkboxes, etc.)
                if (Array.isArray(data[key])) {
                    data[key].push(value);
                } else {
                    data[key] = [data[key], value];
                }
            } else {
                data[key] = value;
            }
        }
        
        return data;
    },

    // Validate form
    validate(form, rules = {}) {
        const errors = {};
        const data = this.serialize(form);
        
        Object.keys(rules).forEach(field => {
            const rule = rules[field];
            const value = data[field];
            
            if (rule.required && (!value || value.trim() === '')) {
                errors[field] = rule.requiredMessage || `${field} is required`;
            }
            
            if (value && rule.minLength && value.length < rule.minLength) {
                errors[field] = rule.minLengthMessage || `${field} must be at least ${rule.minLength} characters`;
            }
            
            if (value && rule.pattern && !rule.pattern.test(value)) {
                errors[field] = rule.patternMessage || `${field} format is invalid`;
            }
        });
        
        return {
            isValid: Object.keys(errors).length === 0,
            errors
        };
    },

    // Display validation errors
    showErrors(form, errors) {
        // Clear previous errors
        form.querySelectorAll('.form-error').forEach(el => el.classList.remove('form-error'));
        form.querySelectorAll('.form-error-message').forEach(el => el.remove());
        
        Object.keys(errors).forEach(field => {
            const input = form.querySelector(`[name="${field}"]`);
            if (input) {
                input.classList.add('form-error');
                
                const errorDiv = document.createElement('div');
                errorDiv.className = 'form-error-message';
                errorDiv.textContent = errors[field];
                
                input.parentNode.insertBefore(errorDiv, input.nextSibling);
            }
        });
    },

    // Clear form
    clear(form) {
        form.reset();
        form.querySelectorAll('.form-error').forEach(el => el.classList.remove('form-error'));
        form.querySelectorAll('.form-error-message').forEach(el => el.remove());
    }
};

// Loading System
const MinangLoading = {
    show(target = document.body, message = 'Loading...') {
        const loading = document.createElement('div');
        loading.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
        loading.id = 'minang-loading';
        loading.innerHTML = `
            <div class="bg-white rounded-xl shadow-2xl p-8 text-center">
                <div class="loading-spinner w-8 h-8 mx-auto mb-4"></div>
                <p class="text-gray-600 font-medium">${message}</p>
            </div>
        `;
        
        target.appendChild(loading);
        return loading;
    },

    hide(target = document.body) {
        const loading = target.querySelector('#minang-loading');
        if (loading) {
            loading.remove();
        }
    }
};

// Local Data Management (for when localStorage is not available)
const MinangData = {
    cache: new Map(),

    set(key, value, expiry = null) {
        const data = {
            value,
            expiry: expiry ? Date.now() + expiry : null
        };
        
        this.cache.set(key, data);
        
        // Try to persist
        MinangUtils.setStorage(key, data);
    },

    get(key) {
        let data = this.cache.get(key);
        
        // Try to get from persistent storage if not in cache
        if (!data) {
            data = MinangUtils.getStorage(key);
            if (data) {
                this.cache.set(key, data);
            }
        }
        
        if (!data) {
            return null;
        }
        
        // Check expiry
        if (data.expiry && Date.now() > data.expiry) {
            this.remove(key);
            return null;
        }
        
        return data.value;
    },

    remove(key) {
        this.cache.delete(key);
        try {
            if (typeof(Storage) !== "undefined") {
                localStorage.removeItem(key);
            }
        } catch (e) {
            console.warn('Storage removal failed:', e);
        }
    },

    clear() {
        this.cache.clear();
        try {
            if (typeof(Storage) !== "undefined") {
                localStorage.clear();
            }
        } catch (e) {
            console.warn('Storage clear failed:', e);
        }
    }
};

// Keyboard Shortcuts Manager
const MinangShortcuts = {
    shortcuts: new Map(),

    register(key, callback, description = '') {
        this.shortcuts.set(key.toLowerCase(), { callback, description });
    },

    init() {
        document.addEventListener('keydown', (e) => {
            const key = this.getKeyString(e);
            const shortcut = this.shortcuts.get(key);
            
            if (shortcut && typeof shortcut.callback === 'function') {
                e.preventDefault();
                shortcut.callback(e);
            }
        });
    },

    getKeyString(event) {
        const parts = [];
        
        if (event.ctrlKey) parts.push('ctrl');
        if (event.altKey) parts.push('alt');
        if (event.shiftKey) parts.push('shift');
        if (event.metaKey) parts.push('meta');
        
        // Handle special keys
        if (event.key.startsWith('F') && event.key.length <= 3) {
            parts.push(event.key.toLowerCase());
        } else if (event.key === 'Enter') {
            parts.push('enter');
        } else if (event.key === 'Escape') {
            parts.push('esc');
        } else if (event.key.length === 1) {
            parts.push(event.key.toLowerCase());
        }
        
        return parts.join('+');
    },

    showHelp() {
        const shortcuts = Array.from(this.shortcuts.entries())
            .filter(([key, data]) => data.description)
            .map(([key, data]) => `
                <div class="flex justify-between py-2 border-b border-gray-200">
                    <span class="font-mono text-sm bg-gray-100 px-2 py-1 rounded">${key}</span>
                    <span class="text-gray-600">${data.description}</span>
                </div>
            `).join('');
        
        MinangModal.create({
            title: 'Keyboard Shortcuts',
            content: `
                <div class="max-h-64 overflow-y-auto">
                    ${shortcuts || '<p class="text-gray-500">No shortcuts available</p>'}
                </div>
            `,
            buttons: `
                <button onclick="MinangModal.close(this)" 
                        class="px-4 py-2 bg-primary hover:bg-blue-600 text-white rounded-lg font-medium">
                    Close
                </button>
            `
        });
    }
};

// Print Helper
const MinangPrint = {
    // Print element
    printElement(elementId, title = 'Print') {
        const element = document.getElementById(elementId);
        if (!element) return false;
        
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <html>
                <head>
                    <title>${title}</title>
                    <link href="https://cdn.tailwindcss.com" rel="stylesheet">
                    <style>
                        @media print {
                            body { -webkit-print-color-adjust: exact; }
                            .no-print { display: none !important; }
                        }
                    </style>
                </head>
                <body>
                    ${element.outerHTML}
                    <script>
                        window.onload = () => {
                            window.print();
                            window.onafterprint = () => window.close();
                        };
                    </script>
                </body>
            </html>
        `);
        
        printWindow.document.close();
        return true;
    },

    // Print receipt
    printReceipt(receiptHtml) {
        const printWindow = window.open('', '_blank');
        printWindow.document.write(receiptHtml);
        printWindow.document.close();
        printWindow.print();
        return true;
    }
};

// Clock and Time Functions
const MinangClock = {
    intervals: new Map(),

    start(elementId, format = 'full') {
        if (this.intervals.has(elementId)) {
            this.stop(elementId);
        }
        
        const update = () => {
            const element = document.getElementById(elementId);
            if (element) {
                const now = new Date();
                
                if (format === 'time') {
                    element.textContent = now.toLocaleTimeString('en-US', {
                        hour12: true, hour: '2-digit', minute: '2-digit', second: '2-digit'
                    });
                } else if (format === 'date') {
                    element.textContent = now.toLocaleDateString('en-US', {
                        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
                    });
                } else {
                    element.textContent = MinangUtils.formatDateTime(now);
                }
            }
        };
        
        update(); // Initial update
        const interval = setInterval(update, 1000);
        this.intervals.set(elementId, interval);
        
        return interval;
    },

    stop(elementId) {
        const interval = this.intervals.get(elementId);
        if (interval) {
            clearInterval(interval);
            this.intervals.delete(elementId);
        }
    },

    stopAll() {
        this.intervals.forEach(interval => clearInterval(interval));
        this.intervals.clear();
    }
};

// Initialize common functionality
document.addEventListener('DOMContentLoaded', function() {
    // Initialize keyboard shortcuts
    MinangShortcuts.init();
    
    // Register common shortcuts
    MinangShortcuts.register('ctrl+shift+h', () => MinangShortcuts.showHelp(), 'Show keyboard shortcuts');
    
    // Start clocks
    MinangClock.start('current-time', 'time');
    MinangClock.start('current-date', 'date');
    MinangClock.start('header-current-time', 'time');
    MinangClock.start('header-current-date', 'date');
    
    // Auto-save forms on input (debounced)
    const autoSaveInputs = document.querySelectorAll('[data-auto-save]');
    autoSaveInputs.forEach(input => {
        input.addEventListener('input', MinangUtils.debounce(() => {
            const key = `autosave_${input.name}_${window.location.pathname}`;
            MinangData.set(key, input.value, 3600000); // 1 hour expiry
        }, 1000));
        
        // Restore auto-saved value
        const savedValue = MinangData.get(`autosave_${input.name}_${window.location.pathname}`);
        if (savedValue && !input.value) {
            input.value = savedValue;
        }
    });
    
    // Initialize tooltips
    const tooltips = document.querySelectorAll('[title]');
    tooltips.forEach(element => {
        element.addEventListener('mouseenter', function(e) {
            // Simple tooltip implementation
            const tooltip = document.createElement('div');
            tooltip.className = 'fixed bg-gray-800 text-white px-2 py-1 rounded text-xs z-50 pointer-events-none';
            tooltip.textContent = this.title;
            tooltip.style.left = e.pageX + 'px';
            tooltip.style.top = (e.pageY - 30) + 'px';
            document.body.appendChild(tooltip);
            
            this.addEventListener('mouseleave', () => {
                tooltip.remove();
            }, { once: true });
            
            // Remove title to prevent browser tooltip
            this.dataset.title = this.title;
            this.removeAttribute('title');
        });
        
        element.addEventListener('mouseleave', function() {
            // Restore title
            if (this.dataset.title) {
                this.title = this.dataset.title;
            }
        });
    });
});

// Cleanup on page unload
window.addEventListener('beforeunload', function() {
    MinangClock.stopAll();
});

// Export for global use
window.Minang = {
    Utils: MinangUtils,
    Notification: MinangNotification,
    Modal: MinangModal,
    API: MinangAPI,
    Form: MinangForm,
    Loading: MinangLoading,
    Data: MinangData,
    Shortcuts: MinangShortcuts,
    Clock: MinangClock,
    Print: MinangPrint,
    Config: MinangConfig
};