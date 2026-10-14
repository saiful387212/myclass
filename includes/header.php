<?php
/**
 * ClassMate - Main Header
 * Includes navigation, user profile, notifications, and search
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' . APP_NAME : APP_NAME; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" href="<?php echo asset('images/favicon.ico'); ?>" type="image/x-icon">
    
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Google Fonts - Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <!-- Custom CSS -->
        <link rel="stylesheet" href="<?php echo asset('css/style.css') . '?v=' . APP_VERSION; ?>">
        <link rel="stylesheet" href="<?php echo asset('css/components.css') . '?v=' . APP_VERSION; ?>">
        <link rel="stylesheet" href="<?php echo asset('css/responsive.css') . '?v=' . APP_VERSION; ?>">
    
    <?php 
    // Page specific CSS
    if (isset($pageCss)) {
        if (is_array($pageCss)) {
            foreach ($pageCss as $css) {
                 echo '<link rel="stylesheet" href="' . asset('css/' . $css) . '?v=' . APP_VERSION . '">';
            }
        } else {
              echo '<link rel="stylesheet" href="' . asset('css/' . $pageCss) . '?v=' . APP_VERSION . '">';
        }
    }
    
    // Admin specific CSS
    if (isset($isAdmin) && $isAdmin) {
           echo '<link rel="stylesheet" href="' . asset('css/admin.css') . '?v=' . APP_VERSION . '">';
    }
    
    // Student specific CSS
    if (isset($isStudent) && $isStudent) {
        echo '<link rel="stylesheet" href="' . asset('css/student.css') . '?v=' . APP_VERSION . '">';
    }
    ?>
    <!-- ===== PWA META TAGS ===== -->
<!-- Basic PWA Meta -->
<link rel="manifest" href="<?php echo url('manifest.json'); ?>?v=<?php echo rawurlencode(APP_VERSION); ?>">
<meta name="theme-color" content="#4F46E5">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="ClassMate">
<meta name="mobile-web-app-capable" content="yes">
<meta name="application-name" content="ClassMate">

<!-- Favicon & Icons -->
<link rel="icon" href="<?php echo asset('images/favicon.ico'); ?>" type="image/x-icon">
<link rel="apple-touch-icon" href="<?php echo asset('icons/apple-touch-icon.png'); ?>">
<link rel="apple-touch-icon" sizes="192x192" href="<?php echo asset('icons/web-app-manifest-192x192.png'); ?>">
<link rel="apple-touch-icon" sizes="512x512" href="<?php echo asset('icons/web-app-manifest-512x512.png'); ?>">

<!-- Service Worker Registration -->
<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        navigator.serviceWorker.register('<?php echo url('sw.js'); ?>')
            .then(function(registration) {
                console.log('ServiceWorker registration successful');
            })
            .catch(function(error) {
                console.log('ServiceWorker registration failed:', error);
            });
    });
}

// ===== Update App Function =====
function updateApp() {
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.getRegistration().then(function(registration) {
            if (registration) {
                registration.update();
                alert('Updating ClassMate... Please wait.');
                setTimeout(function() {
                    location.reload();
                }, 2000);
            }
        });
    }
}

// ===== Prompt to Install PWA =====
let deferredPrompt;
let installPromptElement;

function createInstallPrompt() {
    if (installPromptElement || window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true) {
        return;
    }

    installPromptElement = document.createElement('div');
    installPromptElement.className = 'install-prompt';
    installPromptElement.innerHTML = `
        <div class="install-content">
            <i class="fas fa-download"></i>
            <span>Install ClassMate App</span>
            <button onclick="installApp()" class="btn btn-sm btn-success">Install</button>
            <button onclick="dismissInstall()" class="btn btn-sm btn-outline" aria-label="Dismiss install prompt">×</button>
        </div>
    `;
    document.body.appendChild(installPromptElement);
}

window.addEventListener('beforeinstallprompt', function(e) {
    // Prevent Chrome 67 and earlier from automatically showing the prompt
    e.preventDefault();
    // Stash the event so it can be triggered later
    deferredPrompt = e;
    
    createInstallPrompt();
});

// Keep a visible fallback for browsers that do not expose beforeinstallprompt.
window.addEventListener('load', function() {
    setTimeout(createInstallPrompt, 1500);
});

window.addEventListener('appinstalled', function() {
    deferredPrompt = null;
    if (installPromptElement) {
        installPromptElement.remove();
        installPromptElement = null;
    }
});

function installApp() {
    if (deferredPrompt) {
        deferredPrompt.prompt();
        deferredPrompt.userChoice.then(function(choiceResult) {
            if (choiceResult.outcome === 'accepted') {
                console.log('User accepted the install prompt');
            } else {
                console.log('User dismissed the install prompt');
            }
            deferredPrompt = null;
            
            // Remove install button
            const prompt = document.querySelector('.install-prompt');
            if (prompt) {
                prompt.remove();
                installPromptElement = null;
            }
        });
    } else if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true) {
        alert('ClassMate is already installed.');
    } else {
        if (!window.isSecureContext) {
            alert('App installation requires HTTPS. Open ClassMate using https:// or use http://localhost/myclass/ on this computer.');
            return;
        }

        // Detect iOS device
        const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
        if (isIOS) {
            alert('To install the app on iOS, tap the Share icon at the bottom of the screen and select "Add to Home Screen".');
        } else {
            showInstallHelp();
        }
    }
}

function showInstallHelp() {
    const existingHelp = document.getElementById('installHelp');
    if (existingHelp) {
        existingHelp.hidden = false;
        return;
    }

    const isAndroid = /Android/i.test(navigator.userAgent);
    const instructions = isAndroid
        ? 'Chrome cannot install ClassMate as an app right now. To add it to your home screen, choose <strong>Create shortcut</strong> in the Chrome menu. The shortcut will open ClassMate in Chrome.'
        : 'Open this page in the latest Chrome or Edge browser. Open the browser menu, then choose <strong>Install ClassMate</strong> or <strong>Add to Home screen</strong>.';
    const help = document.createElement('div');
    help.id = 'installHelp';
    help.setAttribute('role', 'dialog');
    help.setAttribute('aria-modal', 'true');
    help.innerHTML = `
        <div style="position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9999;display:flex;align-items:center;justify-content:center;padding:20px;">
            <div style="background:#fff;border-radius:8px;max-width:420px;padding:24px;box-shadow:0 12px 32px rgba(0,0,0,.25);">
                <h3 style="margin:0 0 12px;color:#111827;">Install ClassMate</h3>
                <p style="margin:0 0 16px;color:#374151;line-height:1.5;">${instructions}</p>
                <button type="button" onclick="document.getElementById('installHelp').remove()" class="btn btn-primary">Close</button>
            </div>
        </div>
    `;
    document.body.appendChild(help);
}

function dismissInstall() {
    const prompt = document.querySelector('.install-prompt');
    if (prompt) prompt.remove();
}

// ===== Offline Status Detection =====
window.addEventListener('online', function() {
    showToast('Back online!', 'success');
    // Refresh data
    location.reload();
});

window.addEventListener('offline', function() {
    showToast('You are offline. Some features may be unavailable.', 'warning');
});

// ===== Toast for offline status =====
function showToast(message, type) {
    const container = document.querySelector('.toast-container');
    if (!container) return;
    
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
        <div class="toast-content">
            <div class="toast-message">${message}</div>
        </div>
        <button class="toast-close" onclick="this.closest('.toast').remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    container.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.add('removing');
        setTimeout(() => toast.remove(), 300);
    }, 5000);
}
</script>
</head>
<body>
    
    <!-- Sidebar Overlay (Mobile) -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
    
    <!-- Toast Notification Container -->
    <div class="toast-container" id="toastContainer"></div>

    <?php if (Auth::isLoggedIn()): ?>
    <!-- ===== Main Application Container ===== -->
    <div class="app-container">
        
        <!-- ===== Sidebar ===== -->
            <?php if (Authorization::isAdmin()): ?>
            <?php include_once BASE_PATH . '/includes/sidebar-admin.php'; ?>
        <?php else: ?>
            <?php include_once BASE_PATH . '/includes/sidebar-student.php'; ?>
        <?php endif; ?>
        
        <!-- ===== Main Content Wrapper ===== -->
        <div class="main-wrapper">
            
            <!-- ===== Top Header ===== -->
            <header class="app-header" id="appHeader">
                <div class="header-left">
                    <!-- Mobile Menu Toggle -->
                    <button type="button" class="menu-toggle" id="menuToggle" aria-label="Toggle sidebar">
                        <i class="fas fa-bars"></i>
                    </button>
                    
                    <!-- Page Title / Breadcrumb -->
                    <div class="header-title">
                        <?php if (isset($pageTitle)): ?>
                            <h1 class="page-title"><?php echo htmlspecialchars($pageTitle); ?></h1>
                        <?php endif; ?>
                        <?php if (isset($breadcrumb)): ?>
                            <nav class="breadcrumb" aria-label="Breadcrumb">
                                <?php foreach ($breadcrumb as $index => $item): ?>
                                    <?php if ($index === count($breadcrumb) - 1): ?>
                                        <span class="current"><?php echo htmlspecialchars($item['label']); ?></span>
                                    <?php else: ?>
                                        <a href="<?php echo htmlspecialchars($item['url']); ?>">
                                            <?php echo htmlspecialchars($item['label']); ?>
                                        </a>
                                        <span class="separator"><i class="fas fa-chevron-right"></i></span>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </nav>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="header-right">
                    <!-- ===== Search Bar ===== -->
                    <div class="search-bar">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" id="headerSearch" placeholder="Search..." 
                               aria-label="Search" autocomplete="off">
                        <div class="search-results" id="searchResults"></div>
                    </div>
                    
                    <!-- ===== Header Actions ===== -->
                    <div class="header-actions">
                        <!-- Notifications -->
                        <?php 
                        $unreadCount = 0;
                        if (Auth::isLoggedIn()) {
                            try {
                                $db = Database::getInstance();
                                $userId = Session::get('user_id');
                                $userType = Session::get('user_type');
                                $stmt = $db->prepare("
                                    SELECT COUNT(*) FROM notifications 
                                    WHERE user_id = ? AND user_type = ? AND is_read = 0
                                ");
                                $stmt->execute([$userId, $userType]);
                                $unreadCount = (int)$stmt->fetchColumn();
                            } catch (Throwable $e) {
                                $unreadCount = 0;
                            }
                        }
                        ?>
                        <button type="button"
                                class="action-btn notification-btn <?php echo $unreadCount > 0 ? 'has-unread' : ''; ?>"
                                data-dropdown="notificationDropdown"
                                aria-label="Notifications<?php echo $unreadCount > 0 ? ', ' . $unreadCount . ' unread' : ''; ?>"
                                aria-expanded="false"
                                aria-controls="notificationDropdown">
                            <i class="fas fa-bell"></i>
                            <?php if ($unreadCount > 0): ?>
                                <span class="badge"><?php echo $unreadCount > 99 ? '99+' : $unreadCount; ?></span>
                            <?php endif; ?>
                        </button>
                        
                        <!-- Notification Dropdown -->
                        <div class="dropdown-menu" id="notificationDropdown">
                            <div class="dropdown-header">
                                <span class="dropdown-title">Notifications</span>
                                <?php if ($unreadCount > 0): ?>
                                    <button class="btn btn-sm btn-outline" onclick="markAllAsRead()">
                                        Mark all read
                                    </button>
                                <?php endif; ?>
                            </div>
                            <div class="dropdown-body" id="notificationList">
                                <?php if (Auth::isLoggedIn()): ?>
                                    <?php
                                    $notifications = [];
                                    try {
                                        $db = Database::getInstance();
                                        $userId = Session::get('user_id');
                                        $userType = Session::get('user_type');
                                        $stmt = $db->prepare("
                                            SELECT * FROM notifications 
                                            WHERE user_id = ? AND user_type = ? 
                                            ORDER BY created_at DESC LIMIT 10
                                        ");
                                        $stmt->execute([$userId, $userType]);
                                        $notifications = $stmt->fetchAll();
                                    } catch (Throwable $e) {
                                        $notifications = [];
                                    }
                                    
                                    if (empty($notifications)):
                                    ?>
                                        <div class="empty-notifications">
                                            <i class="fas fa-bell-slash"></i>
                                            <p>No notifications</p>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($notifications as $notif): ?>
                                            <a href="<?php echo htmlspecialchars($notif['link'] ?? '#'); ?>" 
                                               class="notification-item <?php echo $notif['is_read'] ? 'read' : 'unread'; ?>"
                                               data-id="<?php echo $notif['id']; ?>"
                                               onclick="markNotificationRead(<?php echo $notif['id']; ?>)">
                                                <div class="notification-icon">
                                                    <i class="fas fa-<?php 
                                                        echo $notif['notification_type'] === 'success' ? 'check-circle' : 
                                                            ($notif['notification_type'] === 'error' ? 'exclamation-circle' : 
                                                            ($notif['notification_type'] === 'warning' ? 'exclamation-triangle' : 'info-circle')); 
                                                    ?>"></i>
                                                </div>
                                                <div class="notification-content">
                                                    <div class="notification-title"><?php echo htmlspecialchars($notif['title']); ?></div>
                                                    <div class="notification-message"><?php echo htmlspecialchars($notif['message']); ?></div>
                                                    <div class="notification-time"><?php echo timeAgo($notif['created_at']); ?></div>
                                                </div>
                                                <?php if (!$notif['is_read']): ?>
                                                    <span class="notification-dot"></span>
                                                <?php endif; ?>
                                            </a>
                                        <?php endforeach; ?>
                                        
                                        <?php if (count($notifications) >= 10): ?>
                                            <div class="dropdown-footer">
                                                <a href="<?php echo url('notifications.php'); ?>" class="btn btn-sm btn-block btn-outline">
                                                    View all notifications
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="empty-notifications">
                                        <i class="fas fa-sign-in-alt"></i>
                                        <p>Please login to see notifications</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Quick Actions -->
                        <div class="action-btn" data-dropdown="quickActionsDropdown">
                            <i class="fas fa-plus-circle"></i>
                        </div>
                        
                        <!-- Quick Actions Dropdown -->
                        <div class="dropdown-menu dropdown-menu-right" id="quickActionsDropdown">
                            <?php if (Authorization::isAdmin()): ?>
                                <a href="<?php echo url('admin/add-class.php'); ?>" class="dropdown-item">
                                    <i class="fas fa-book"></i> Add Class
                                </a>
                                <a href="<?php echo url('admin/add-assignment.php'); ?>" class="dropdown-item">
                                    <i class="fas fa-tasks"></i> Add Assignment
                                </a>
                                <a href="<?php echo url('admin/add-income.php'); ?>" class="dropdown-item">
                                    <i class="fas fa-coins"></i> Add Income
                                </a>
                                <a href="<?php echo url('admin/add-expenditure.php'); ?>" class="dropdown-item">
                                    <i class="fas fa-money-bill-wave"></i> Add Expenditure
                                </a>
                                <div class="dropdown-divider"></div>
                                <a href="<?php echo url('admin/announcements.php'); ?>" class="dropdown-item">
                                    <i class="fas fa-bullhorn"></i> Post Announcement
                                </a>
                                <a href="<?php echo url('resources_notes.php'); ?>" class="mobile-nav-item">
    <i class="fas fa-folder-open"></i>
    <span>Resources</span>
</a>
                            <?php else: ?>
                                <a href="<?php echo url('student/classes.php'); ?>" class="dropdown-item">
                                    <i class="fas fa-book"></i> View Classes
                                </a>
                                <a href="<?php echo url('student/assignments.php'); ?>" class="dropdown-item">
                                    <i class="fas fa-tasks"></i> View Assignments
                                </a>
                                <a href="<?php echo url('student/class-fund.php'); ?>" class="dropdown-item">
                                    <i class="fas fa-coins"></i> View Class Fund
                                </a>
                            <?php endif; ?>
                        </div>
                        
                        <!-- ===== User Profile ===== -->
                        <?php if (Auth::isLoggedIn() && isset($currentUser)): ?>
                            <div class="user-profile" data-dropdown="profileDropdown">
                                <button class="profile-trigger" aria-label="User menu">
                                    <div class="avatar">
                                        <?php 
                                        $initial = strtoupper(substr($currentUser['full_name'] ?? 'U', 0, 1));
                                        echo $initial;
                                        ?>
                                    </div>
                                    <div class="profile-info">
                                        <span class="name"><?php echo htmlspecialchars($currentUser['full_name'] ?? 'User'); ?></span>
                                        <span class="role">
                                            <?php 
                                            if (Authorization::isAdmin()) {
                                                echo 'Administrator';
                                            } else {
                                                echo 'Student';
                                            }
                                            ?>
                                        </span>
                                    </div>
                                    <i class="fas fa-chevron-down profile-arrow"></i>
                                </button>
                            </div>
                            
                            <!-- Profile Dropdown -->
                            <div class="dropdown-menu dropdown-menu-right" id="profileDropdown">
                                <div class="dropdown-user-info">
                                    <div class="avatar-lg">
                                        <?php echo $initial; ?>
                                    </div>
                                    <div class="user-details">
                                        <span class="user-name"><?php echo htmlspecialchars($currentUser['full_name'] ?? 'User'); ?></span>
                                        <span class="user-email">
                                            <?php 
                                            if (Authorization::isAdmin()) {
                                                echo htmlspecialchars($currentUser['email'] ?? '');
                                            } else {
                                                echo 'Roll: ' . htmlspecialchars($currentUser['class_roll'] ?? '');
                                            }
                                            ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="dropdown-divider"></div>
                                
                                <?php if (Authorization::isAdmin()): ?>
                                    <a href="<?php echo url('admin/dashboard.php'); ?>" class="dropdown-item">
                                        <i class="fas fa-chart-pie"></i> Dashboard
                                    </a>
                                    <a href="<?php echo url('admin/settings.php'); ?>" class="dropdown-item">
                                        <i class="fas fa-cog"></i> Settings
                                    </a>
                                <?php else: ?>
                                    <a href="<?php echo url('student/dashboard.php'); ?>" class="dropdown-item">
                                        <i class="fas fa-chart-pie"></i> Dashboard
                                    </a>
                                    <a href="<?php echo url('student/profile.php'); ?>" class="dropdown-item">
                                        <i class="fas fa-user"></i> My Profile
                                    </a>
                                    <a href="<?php echo url('student/change-pin.php'); ?>" class="dropdown-item">
                                        <i class="fas fa-key"></i> Change PIN
                                    </a>
                                <?php endif; ?>
                                
                                <div class="dropdown-divider"></div>
                                
                                <a href="<?php echo url('auth/logout.php'); ?>" class="dropdown-item text-danger" 
                                   onclick="return confirm('Are you sure you want to logout?')">
                                    <i class="fas fa-sign-out-alt"></i> Logout
                                </a>
                            </div>
                        <?php else: ?>
                            <!-- Login Button when not logged in -->
                            <a href="<?php echo url('auth/login.php'); ?>" class="btn btn-primary btn-sm">
                                <i class="fas fa-sign-in-alt"></i> Login
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </header>
            
            <!-- ===== Main Content ===== -->
            <main class="main-content" id="mainContent">
                
                <!-- Flash Messages -->
                <?php include_once BASE_PATH . '/includes/flash-messages.php'; ?>

<?php else: ?>
    <!-- ===== Public Layout (No Sidebar) ===== -->
    <div class="app-container public-layout">
        <!-- Public Header (logo, search, auth links) -->
        <header class="app-header public-header" id="appHeader">
            <div class="header-left">
                <a href="<?php echo url(''); ?>" class="brand-link">
                    <?php if (file_exists(BASE_PATH . '/assets/images/logo.svg')): ?>
                        <img src="<?php echo asset('images/logo.svg'); ?>" alt="ClassMate" style="height:36px;">
                    <?php else: ?>
                        <span class="brand-text">ClassMate</span>
                    <?php endif; ?>
                </a>
            </div>
            <div class="header-right">
                <div class="search-bar">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" id="headerSearch" placeholder="Search..." aria-label="Search" autocomplete="off">
                    <div class="search-results" id="searchResults"></div>
                </div>
                <div class="header-actions">
                    <a href="<?php echo url('auth/login.php'); ?>" class="btn btn-sm btn-primary">Login</a>
                    <a href="<?php echo url('auth/register.php'); ?>" class="btn btn-sm" style="margin-left:8px;">Register</a>
                </div>
            </div>
        </header>
        <main class="main-content public-content">
            <!-- Flash Messages for Public Pages -->
            <?php include_once BASE_PATH . '/includes/flash-messages.php'; ?>
<?php endif; ?>