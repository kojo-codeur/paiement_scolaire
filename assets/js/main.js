const App = {

    init: function() {
        this.initAlerts();
        this.initFileUpload();
        this.initModals();
        this.initResponsiveMenu();
        this.initTooltips();
        this.initNumberFormatting();
        this.initNotifications();
        this.initCharts();
    },
    
    initAlerts: function() {
        const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.style.transition = 'opacity 0.3s ease';
                alert.style.opacity = '0';
                setTimeout(() => {
                    if(alert.parentNode) alert.remove();
                }, 300);
            }, 4000);
        });
    },
    
    initFileUpload: function() {
        const fileInputs = document.querySelectorAll('input[type="file"]');
        fileInputs.forEach(input => {
            input.addEventListener('change', function(e) {
                const file = this.files[0];
                if(file) {
                    const maxSize = 5 * 1024 * 1024;
                    if(file.size > maxSize) {
                        App.showToast('Le fichier est trop volumineux. Maximum 5MB.', 'error');
                        this.value = '';
                        return;
                    }
                    
                    const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
                    if(!allowedTypes.includes(file.type)) {
                        App.showToast('Format non supporté. Utilisez JPG, PNG ou PDF.', 'error');
                        this.value = '';
                        return;
                    }
                    
                    App.showToast('Fichier sélectionné : ' + file.name, 'success');
                }
            });
        });
    },
    
    initModals: function() {
        const modals = document.querySelectorAll('.modal');
        const closeButtons = document.querySelectorAll('.modal .close');
        
        closeButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                const modal = this.closest('.modal');
                if(modal) modal.style.display = 'none';
            });
        });
        
        window.addEventListener('click', function(e) {
            modals.forEach(modal => {
                if(e.target === modal) {
                    modal.style.display = 'none';
                }
            });
        });
    },
    
    initResponsiveMenu: function() {
        const menuToggle = document.querySelector('.menu-toggle');
        const sidebar = document.querySelector('.sidebar');
        
        if(menuToggle) {
            menuToggle.addEventListener('click', function() {
                sidebar.classList.toggle('open');
            });
        }

        const navLinks = document.querySelectorAll('.nav-item');
        navLinks.forEach(link => {
            link.addEventListener('click', function() {
                if(window.innerWidth <= 768) {
                    sidebar.classList.remove('open');
                }
            });
        });
    },
    
    initTooltips: function() {
        const elements = document.querySelectorAll('[data-tooltip]');
        elements.forEach(el => {
            el.addEventListener('mouseenter', function(e) {
                const tooltip = document.createElement('div');
                tooltip.className = 'custom-tooltip';
                tooltip.textContent = this.getAttribute('data-tooltip');
                document.body.appendChild(tooltip);
                
                const rect = this.getBoundingClientRect();
                tooltip.style.position = 'fixed';
                tooltip.style.top = (rect.top - 35) + 'px';
                tooltip.style.left = (rect.left + rect.width/2 - tooltip.offsetWidth/2) + 'px';
                
                this.addEventListener('mouseleave', () => tooltip.remove());
            });
        });
    },
    
    initNumberFormatting: function() {
        const numbers = document.querySelectorAll('.format-number');
        numbers.forEach(el => {
            let value = el.textContent.replace(/[^0-9]/g, '');
            if(value && !isNaN(value)) {
                el.textContent = new Intl.NumberFormat('fr-FR').format(value) + ' FCFA';
            }
        });
    },
    
    initNotifications: function() {
        const notifBtn = document.querySelector('.notifications');
        if(notifBtn) {
            notifBtn.addEventListener('click', function() {
                App.showToast('Aucune nouvelle notification', 'info');
            });
        }
    },
    

    initCharts: function() {

        const paymentCtx = document.getElementById('paymentChart');
        if(paymentCtx) {
            new Chart(paymentCtx, {
                type: 'line',
                data: window.paymentChartData || { labels: [], datasets: [] },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: { position: 'top', labels: { usePointStyle: true, boxWidth: 10 } },
                        tooltip: { 
                            callbacks: { 
                                label: function(context) { 
                                    return context.dataset.label + ': ' + new Intl.NumberFormat('fr-FR').format(context.raw) + ' FCFA'; 
                                } 
                            } 
                        }
                    },
                    scales: {
                        y: { 
                            beginAtZero: true, 
                            grid: { color: '#e2e8f0' }, 
                            ticks: { 
                                callback: function(value) { 
                                    return new Intl.NumberFormat('fr-FR').format(value) + ' FCFA';
                                } 
                            } 
                        },
                        x: { grid: { display: false } }
                    }
                }
            });
        }
        

        const statusCtx = document.getElementById('statusChart');
        if(statusCtx) {
            new Chart(statusCtx, {
                type: 'doughnut',
                data: window.statusChartData || { labels: [], datasets: [] },
                options: { 
                    responsive: true, 
                    maintainAspectRatio: true, 
                    plugins: { legend: { display: false } }, 
                    cutout: '65%' 
                }
            });
        }
    },
    

    showToast: function(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `toast-notification toast-${type}`;
        toast.innerHTML = `
            <div class="toast-icon">
                <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i>
            </div>
            <div class="toast-message">${message}</div>
            <button class="toast-close">&times;</button>
        `;
        
        toast.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: white;
            border-radius: 12px;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            z-index: 10000;
            animation: slideInRight 0.3s ease;
            font-size: 14px;
            min-width: 280px;
            max-width: 400px;
        `;
        
        const colors = {
            success: { bg: '#e8f5e9', icon: '#4caf50' },
            error: { bg: '#ffebee', icon: '#f44336' },
            info: { bg: '#e3f2fd', icon: '#2196f3' }
        };
        
        toast.style.background = colors[type].bg;
        toast.querySelector('.toast-icon i').style.color = colors[type].icon;
        
        document.body.appendChild(toast);
        
        toast.querySelector('.toast-close').addEventListener('click', () => {
            toast.style.animation = 'slideOutRight 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        });
        
        setTimeout(() => {
            if(toast.parentNode) {
                toast.style.animation = 'slideOutRight 0.3s ease';
                setTimeout(() => toast.remove(), 300);
            }
        }, 4000);
    },
    

    confirm: function(message, callback) {
        const modal = document.createElement('div');
        modal.className = 'modal confirm-modal';
        modal.style.display = 'flex';
        modal.innerHTML = `
            <div class="modal-content" style="max-width: 400px;">
                <div class="modal-header">
                    <h3><i class="fas fa-question-circle"></i> Confirmation</h3>
                    <span class="close">&times;</span>
                </div>
                <div class="modal-body">
                    <p>${message}</p>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" id="confirmNo">Annuler</button>
                    <button class="btn btn-primary" id="confirmYes">Confirmer</button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        const closeModal = () => modal.remove();
        
        modal.querySelector('.close').addEventListener('click', closeModal);
        modal.querySelector('#confirmNo').addEventListener('click', closeModal);
        modal.querySelector('#confirmYes').addEventListener('click', () => {
            if(callback) callback();
            closeModal();
        });
        
        modal.addEventListener('click', (e) => {
            if(e.target === modal) closeModal();
        });
    },
    
    formatMoney: function(amount) {
        return new Intl.NumberFormat('fr-FR', { 
            style: 'currency', 
            currency: 'XAF',
            minimumFractionDigits: 0
        }).format(amount);
    },
    
    showLoading: function(element) {
        const spinner = document.createElement('div');
        spinner.className = 'loading-spinner';
        spinner.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        element.style.position = 'relative';
        element.appendChild(spinner);
        return spinner;
    },
    
    hideLoading: function(spinner) {
        if(spinner && spinner.parentNode) spinner.remove();
    }
};

const styleSheet = document.createElement('style');
styleSheet.textContent = `
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
    
    .custom-tooltip {
        background: #1a1a2e;
        color: white;
        padding: 6px 12px;
        border-radius: 8px;
        font-size: 12px;
        white-space: nowrap;
        z-index: 10000;
        pointer-events: none;
        animation: fadeIn 0.2s;
    }
    
    .loading-spinner {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(255,255,255,0.9);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10;
        border-radius: inherit;
    }
    
    .loading-spinner i {
        font-size: 32px;
        color: var(--primary);
    }
    
    .btn-secondary {
        background: #64748b;
        color: white;
    }
    
    .btn-secondary:hover {
        background: #475569;
    }
    
    .confirm-modal .modal-content {
        margin: 20% auto;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
`;

document.head.appendChild(styleSheet);

document.addEventListener('DOMContentLoaded', () => App.init());

window.App = App;

