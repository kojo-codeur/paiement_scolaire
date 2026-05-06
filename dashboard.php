<?php
session_start();
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';
include_once 'includes/Mailer.php';

if (!estConnecte()) {
    header('Location: index.php');
    exit();
}

$role = $_SESSION['role'];
$current_page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#4361ee">
    <title>Gestion Scolaire | <?php echo ucfirst($current_page); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <link rel="stylesheet" href="assets/css/dashboard.css">
    
</head>
<body>
    <div class="dashboard-wrapper">
        <!-- Sidebar OptiFlow Premium -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <span><?php echo APP_NOM; ?></span>
                </div>
                <div class="user-info">
                    <div class="avatar">
                        <i class="fas fa-user-circle"></i>
                    </div>
                    <div class="user-details">
                        <h4><?php echo htmlspecialchars($_SESSION['user_name']); ?></h4>
                        <p><?php echo $role == 'admin' ? 'Administrateur' : 'Élève'; ?></p>
                    </div>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <?php if($role == 'admin'): ?>
                    <a href="dashboard.php?page=dashboard" class="nav-item <?php echo $current_page == 'dashboard' ? 'active' : ''; ?>">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="dashboard.php?page=eleves" class="nav-item <?php echo $current_page == 'eleves' ? 'active' : ''; ?>">
                        <i class="fas fa-users"></i>
                        <span>Élèves</span>
                    </a>
                    <a href="dashboard.php?page=validation" class="nav-item <?php echo $current_page == 'validation' ? 'active' : ''; ?>">
                        <i class="fas fa-check-circle"></i>
                        <span>Validations</span>
                    </a>
                    <a href="dashboard.php?page=factures" class="nav-item <?php echo $current_page == 'factures' ? 'active' : ''; ?>">
                        <i class="fas fa-file-invoice"></i>
                        <span>Factures</span>
                    </a>
                    <a href="dashboard.php?page=rapports" class="nav-item <?php echo $current_page == 'rapports' ? 'active' : ''; ?>">
                        <i class="fas fa-chart-bar"></i>
                        <span>Rapports</span>
                    </a>
                <?php else: ?>
                    <a href="dashboard.php?page=dashboard" class="nav-item <?php echo $current_page == 'dashboard' ? 'active' : ''; ?>">
                        <i class="fas fa-home"></i>
                        <span>Accueil</span>
                    </a>
                    <a href="dashboard.php?page=paiement" class="nav-item <?php echo $current_page == 'paiement' ? 'active' : ''; ?>">
                        <i class="fas fa-credit-card"></i>
                        <span>Paiement</span>
                    </a>
                    <a href="dashboard.php?page=historique" class="nav-item <?php echo $current_page == 'historique' ? 'active' : ''; ?>">
                        <i class="fas fa-history"></i>
                        <span>Historique</span>
                    </a>
                    <a href="dashboard.php?page=factures" class="nav-item <?php echo $current_page == 'factures' ? 'active' : ''; ?>">
                        <i class="fas fa-file-invoice"></i>
                        <span>Mes factures</span>
                    </a>
                <?php endif; ?>
                <a href="logout.php" class="nav-item logout">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Déconnexion</span>
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="top-bar">
                <button class="menu-toggle" id="menuToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="page-title">
                    <h1>
                        <?php 
                            $titles = [
                                'dashboard' => 'Dashboard',
                                'paiement' => 'Paiement',
                                'historique' => 'Historique',
                                'factures' => 'Factures',
                                'eleves' => 'Élèves',
                                'validation' => 'Validations',
                                'rapports' => 'Rapports'
                            ];
                            echo $titles[$current_page] ?? 'Dashboard';
                        ?>
                    </h1>
                </div>
            </header>

            <div class="content-area">
                <?php
                $page_file = ($role == 'admin' ? 'admin/' : 'eleve/') . $current_page . '.php';
                if (file_exists($page_file)) {
                    include $page_file;
                } else {
                    include ($role == 'admin' ? 'admin/dashboard.php' : 'eleve/dashboard.php');
                }
                ?>
            </div>
        </main>
    </div>

    <!-- Overlay pour mobile -->
    <div id="sidebarOverlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 999;" onclick="closeSidebar()"></div>

    <script>
        // Menu toggle pour mobile
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        
        function closeSidebar() {
            if (sidebar) {
                sidebar.classList.remove('open');
            }
            if (overlay) {
                overlay.style.display = 'none';
            }
        }
        
        function openSidebar() {
            if (sidebar) {
                sidebar.classList.add('open');
            }
            if (overlay) {
                overlay.style.display = 'block';
            }
        }
        
        if (menuToggle && sidebar) {
            menuToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                if (sidebar.classList.contains('open')) {
                    closeSidebar();
                } else {
                    openSidebar();
                }
            });
            
            // Fermer le menu en cliquant à l'extérieur
            document.addEventListener('click', function(e) {
                if (window.innerWidth <= 992) {
                    if (!sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
                        closeSidebar();
                    }
                }
            });
        }
        
        // Fermer le menu sur les liens
        const navLinks = document.querySelectorAll('.nav-item');
        navLinks.forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 992) {
                    closeSidebar();
                }
            });
        });
        
        // Fermer les modals en cliquant en dehors
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal-premium');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.classList.remove('show');
                }
            });
        }
        
        // Mise à jour du badge
        <?php if($role == 'admin'): ?>
        function updatePendingCount() {
            fetch('admin/get_pending_count.php')
                .then(response => response.json())
                .then(data => {
                    const badge = document.getElementById('pendingCount');
                    const notifBadge = document.getElementById('notificationBadge');
                    if(data.count > 0) {
                        badge.textContent = data.count;
                        badge.style.display = 'inline-block';
                        notifBadge.textContent = data.count;
                        notifBadge.style.display = 'flex';
                    } else {
                        badge.style.display = 'none';
                        notifBadge.style.display = 'none';
                    }
                })
                .catch(error => console.error('Error:', error));
        }
        
        updatePendingCount();
        setInterval(updatePendingCount, 30000);
        <?php endif; ?>
        
        // Fonctions globales pour les modals
        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('show');
                // Empêcher le scroll du body
                document.body.style.overflow = 'hidden';
            }
        }
        
        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.remove('show');
                document.body.style.overflow = '';
            }
        }
        
        // Toast notification premium
        function showToast(message, type = 'success') {
            // Supprimer les toasts existants
            const existingToasts = document.querySelectorAll('.toast-notification');
            existingToasts.forEach(toast => toast.remove());
            
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
                animation: fadeInUp 0.3s ease;
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
            if (toast.querySelector('.toast-icon i')) {
                toast.querySelector('.toast-icon i').style.color = colors[type].icon;
            }
            
            document.body.appendChild(toast);
            
            const closeBtn = toast.querySelector('.toast-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', () => {
                    toast.style.animation = 'fadeOutRight 0.3s ease';
                    setTimeout(() => toast.remove(), 300);
                });
            }
            
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.style.animation = 'fadeOutRight 0.3s ease';
                    setTimeout(() => toast.remove(), 300);
                }
            }, 4000);
        }
        
        window.showToast = showToast;
        window.openModal = openModal;
        window.closeModal = closeModal;
        
        // Gestion du redimensionnement
        let resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                if (window.innerWidth > 992) {
                    closeSidebar();
                }
            }, 250);
        });
        
        // Ajout de l'animation fadeOutRight
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fadeOutRight {
                from {
                    transform: translateX(0);
                    opacity: 1;
                }
                to {
                    transform: translateX(100%);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>