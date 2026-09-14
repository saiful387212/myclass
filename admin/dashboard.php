<?php
/**
 * Admin Dashboard
 * Displays real-time statistics from the database
 */

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . '/includes/auth-middleware.php';

// Ensure admin access
Auth::requireAdmin();

$pageTitle = 'Dashboard';
$pageCss = ['admin.css', 'dashboard.css'];
$pageJs = ['dashboard.js'];
$isAdmin = true;

$db = Database::getInstance();

// ============================================
// DEBUG - Check if tables exist
// ============================================
$tables = ['classes', 'assignments', 'students', 'registration_requests', 'fund_transactions'];
$missingTables = [];
foreach ($tables as $table) {
    try {
        $stmt = $db->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() == 0) {
            $missingTables[] = $table;
        }
    } catch (Exception $e) {
        $missingTables[] = $table . ' (error)';
    }
}

// ============================================
// CLASS STATISTICS
// ============================================

$today = date('Y-m-d');
$classStats = [];
$todayClasses = ['total' => 0, 'scheduled' => 0, 'completed' => 0, 'cancelled' => 0, 'retaked' => 0];
$upcomingClasses = 0;

try {
    // Total classes by status
    $stmt = $db->query("
        SELECT 
            status,
            COUNT(*) as count
        FROM classes
        GROUP BY status
    ");
    while ($row = $stmt->fetch()) {
        $classStats[$row['status']] = (int)$row['count'];
    }

    // Today's classes
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
            SUM(CASE WHEN status = 'retaked' THEN 1 ELSE 0 END) as retaked
        FROM classes
        WHERE class_date = ?
    ");
    $stmt->execute([$today]);
    $todayClasses = $stmt->fetch();

    // Upcoming classes
    $stmt = $db->prepare("
        SELECT COUNT(*) as count
        FROM classes
        WHERE class_date BETWEEN ? AND ? AND status = 'scheduled'
    ");
    $stmt->execute([$today, date('Y-m-d', strtotime('+7 days'))]);
    $upcomingClasses = $stmt->fetchColumn();
} catch (Exception $e) {
    // Tables might not exist yet
}

// ============================================
// ASSIGNMENT STATISTICS
// ============================================

$now = date('Y-m-d H:i:s');
$assignmentStats = [];
$assignmentCalc = ['total' => 0, 'ongoing' => 0, 'due_soon' => 0, 'overdue' => 0, 'active' => 0];

try {
    $stmt = $db->query("
        SELECT 
            status,
            COUNT(*) as count
        FROM assignments
        GROUP BY status
    ");
    while ($row = $stmt->fetch()) {
        $assignmentStats[$row['status']] = (int)$row['count'];
    }

    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN deadline > ? AND deadline <= DATE_ADD(?, INTERVAL 3 DAY) AND status IN ('published', 'open') THEN 1 ELSE 0 END) as ongoing,
            SUM(CASE WHEN deadline > ? AND deadline <= DATE_ADD(?, INTERVAL 1 DAY) AND status IN ('published', 'open') THEN 1 ELSE 0 END) as due_soon,
            SUM(CASE WHEN deadline < ? AND status IN ('published', 'open') THEN 1 ELSE 0 END) as overdue,
            SUM(CASE WHEN status IN ('published', 'open') THEN 1 ELSE 0 END) as active
        FROM assignments
    ");
    $stmt->execute([$now, $now, $now, $now, $now]);
    $assignmentCalc = $stmt->fetch();
} catch (Exception $e) {
    // Tables might not exist yet
}

// ============================================
// STUDENT STATISTICS
// ============================================

$studentStats = [];
$pendingRegistrations = 0;
$newStudentsThisMonth = 0;

try {
    $stmt = $db->query("
        SELECT 
            status,
            COUNT(*) as count
        FROM students
        GROUP BY status
    ");
    while ($row = $stmt->fetch()) {
        $studentStats[$row['status']] = (int)$row['count'];
    }

    $stmt = $db->query("SELECT COUNT(*) FROM registration_requests WHERE status = 'pending'");
    $pendingRegistrations = $stmt->fetchColumn();

    $stmt = $db->prepare("
        SELECT COUNT(*) 
        FROM students 
        WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) 
        AND YEAR(created_at) = YEAR(CURRENT_DATE())
    ");
    $stmt->execute();
    $newStudentsThisMonth = $stmt->fetchColumn();
} catch (Exception $e) {
    // Tables might not exist yet
}

// ============================================
// FUND STATISTICS
// ============================================

$fundTotals = ['total_income' => 0, 'total_expenditure' => 0, 'total_refunds' => 0];
$currentBalance = 0;
$monthlyFundData = [];

try {
    $stmt = $db->query("
        SELECT 
            COALESCE(SUM(CASE WHEN LOWER(TRIM(transaction_type)) = 'income' THEN amount ELSE 0 END), 0) as total_income,
            COALESCE(SUM(CASE WHEN LOWER(TRIM(transaction_type)) = 'expenditure' THEN amount ELSE 0 END), 0) as total_expenditure,
            COALESCE(SUM(CASE WHEN LOWER(TRIM(transaction_type)) = 'refund' THEN amount ELSE 0 END), 0) as total_refunds
        FROM fund_transactions
        WHERE LOWER(TRIM(status)) = 'approved'
    ");
    $fundTotals = $stmt->fetch();
    $currentBalance = $fundTotals['total_income'] - $fundTotals['total_expenditure'] - $fundTotals['total_refunds'];

    $stmt = $db->prepare("
        SELECT 
            DATE_FORMAT(transaction_date, '%Y-%m') as month,
            COALESCE(SUM(CASE WHEN transaction_type = 'income' THEN amount ELSE 0 END), 0) as income,
            COALESCE(SUM(CASE WHEN transaction_type = 'expenditure' THEN amount ELSE 0 END), 0) as expenditure,
            COALESCE(SUM(CASE WHEN transaction_type = 'refund' THEN amount ELSE 0 END), 0) as refunds
        FROM fund_transactions
        WHERE status = 'approved'
        AND transaction_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(transaction_date, '%Y-%m')
        ORDER BY month ASC
    ");
    $stmt->execute();
    $monthlyFundData = $stmt->fetchAll();
} catch (Exception $e) {
    // Tables might not exist yet
}

// ============================================
// RECENT ACTIVITIES
// ============================================

$recentActivities = [];

try {
    $stmt = $db->prepare("
        SELECT 
            'registration' as type,
            s.full_name as title,
            s.class_roll as detail,
            s.created_at as created_at,
            NULL as link
        FROM students s
        WHERE s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        UNION ALL
        SELECT 
            'class' as type,
            c.class_title as title,
            CONCAT('Status: ', c.status) as detail,
            c.created_at as created_at,
            CONCAT('/admin/class-details.php?id=', c.id) as link
        FROM classes c
        WHERE c.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        UNION ALL
        SELECT 
            'assignment' as type,
            a.title as title,
            CONCAT('Status: ', a.status) as detail,
            a.created_at as created_at,
            CONCAT('/admin/assignment-details.php?id=', a.id) as link
        FROM assignments a
        WHERE a.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        UNION ALL
        SELECT 
            'fund' as type,
            CONCAT(UCASE(t.transaction_type), ' - ', t.description) as title,
            CONCAT('BDT ', FORMAT(t.amount, 2)) as detail,
            t.created_at as created_at,
            CONCAT('/admin/funds.php?class_id=', t.class_id) as link
        FROM fund_transactions t
        WHERE t.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        UNION ALL
        SELECT 
            'announcement' as type,
            a.title as title,
            a.content as detail,
            a.created_at as created_at,
            CONCAT('/admin/announcements.php') as link
        FROM announcements a
        WHERE a.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        AND a.status = 'published'
        ORDER BY created_at DESC
        LIMIT 20
    ");
    $stmt->execute();
    $recentActivities = $stmt->fetchAll();
} catch (Exception $e) {
    // Tables might not exist yet
}

// ============================================
// STATISTICS CARDS DATA
// ============================================

$statCards = [
    'classes' => [
        'total' => array_sum($classStats),
        'scheduled' => $classStats['scheduled'] ?? 0,
        'completed' => $classStats['completed'] ?? 0,
        'cancelled' => $classStats['cancelled'] ?? 0,
        'retaked' => $classStats['retaked'] ?? 0,
        'today' => (int)($todayClasses['total'] ?? 0),
        'upcoming' => (int)$upcomingClasses
    ],
    'assignments' => [
        'total' => array_sum($assignmentStats),
        'ongoing' => (int)($assignmentCalc['ongoing'] ?? 0),
        'due_soon' => (int)($assignmentCalc['due_soon'] ?? 0),
        'overdue' => (int)($assignmentCalc['overdue'] ?? 0)
    ],
    'students' => [
        'total' => array_sum($studentStats),
        'active' => $studentStats['active'] ?? 0,
        'pending' => $studentStats['pending'] ?? 0,
        'suspended' => $studentStats['suspended'] ?? 0,
        'pending_registrations' => (int)$pendingRegistrations,
        'new_this_month' => (int)$newStudentsThisMonth
    ],
    'fund' => [
        'balance' => (float)$currentBalance,
        'income' => (float)$fundTotals['total_income'],
        'expenditure' => (float)$fundTotals['total_expenditure'],
        'refunds' => (float)$fundTotals['total_refunds']
    ]
];

include_once BASE_PATH . '/includes/header.php';
?>

    <!-- ===== DEBUG: Missing Tables Warning ===== -->
    <?php if (!empty($missingTables)): ?>
    <div class="alert alert-warning" style="margin-bottom: 20px;">
        <i class="fas fa-exclamation-triangle"></i>
        <strong>Database Tables Missing:</strong> 
        The following tables were not found: <?php echo implode(', ', $missingTables); ?>
        <br><small>Please import the database schema to see real data.</small>
    </div>
    <?php endif; ?>
    
    <!-- ===== Welcome Banner ===== -->
    <div class="dashboard-welcome">
        <div class="welcome-content">
            <div>
                <h1>Welcome back, <?php echo htmlspecialchars($currentUser['full_name'] ?? 'Admin'); ?>!</h1>
                <p class="text-muted">Here's what's happening with your classes today</p>
            </div>
            <div class="welcome-date">
                <i class="fas fa-calendar-alt"></i>
                <span><?php echo date('l, d F Y'); ?></span>
            </div>
        </div>
    </div>
    
    <!-- ===== Quick Stats Row ===== -->
    <div class="quick-stats">
        <div class="stat-card stat-classes">
            <div class="stat-icon"><i class="fas fa-book"></i></div>
            <div class="stat-info">
                <h3><?php echo number_format($statCards['classes']['total']); ?></h3>
                <p>Total Classes</p>
            </div>
            <div class="stat-change">
                <span class="badge badge-info"><?php echo $statCards['classes']['today']; ?> today</span>
            </div>
        </div>
        <div class="stat-card stat-assignments">
            <div class="stat-icon"><i class="fas fa-tasks"></i></div>
            <div class="stat-info">
                <h3><?php echo number_format($statCards['assignments']['total']); ?></h3>
                <p>Total Assignments</p>
            </div>
            <div class="stat-change">
                <span class="badge badge-warning"><?php echo $statCards['assignments']['due_soon']; ?> due soon</span>
            </div>
        </div>
        <div class="stat-card stat-students">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-info">
                <h3><?php echo number_format($statCards['students']['total']); ?></h3>
                <p>Total Students</p>
            </div>
            <div class="stat-change">
                <span class="badge badge-success"><?php echo $statCards['students']['active']; ?> active</span>
            </div>
        </div>
        <div class="stat-card stat-funds">
            <div class="stat-icon"><i class="fas fa-wallet"></i></div>
            <div class="stat-info">
                <h3>BDT <?php echo number_format($statCards['fund']['balance'], 2); ?></h3>
                <p>Current Balance</p>
            </div>
            <div class="stat-change">
                <span class="badge badge-primary"><?php echo $statCards['fund']['income'] > 0 ? '+' . number_format($statCards['fund']['income'], 2) : '0.00'; ?></span>
            </div>
        </div>
    </div>
    
    <!-- ===== Dashboard Grid ===== -->
    <div class="dashboard-grid">
        
        <!-- ===== Column 1: Charts ===== -->
        <div class="dashboard-column">
            
            <!-- ===== Class Status Chart ===== -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-chart-pie text-primary"></i> Class Status Distribution</h3>
                </div>
                <div class="card-body">
                    <?php if ($statCards['classes']['total'] > 0): ?>
                        <div class="chart-container chart-pie">
                            <canvas id="classStatusChart"></canvas>
                        </div>
                        <div class="chart-legend">
                            <div class="legend-item">
                                <span class="color-dot" style="background: #4F46E5;"></span>
                                <span>Scheduled: <?php echo $statCards['classes']['scheduled']; ?></span>
                            </div>
                            <div class="legend-item">
                                <span class="color-dot" style="background: #10B981;"></span>
                                <span>Completed: <?php echo $statCards['classes']['completed']; ?></span>
                            </div>
                            <div class="legend-item">
                                <span class="color-dot" style="background: #EF4444;"></span>
                                <span>Cancelled: <?php echo $statCards['classes']['cancelled']; ?></span>
                            </div>
                            <div class="legend-item">
                                <span class="color-dot" style="background: #F59E0B;"></span>
                                <span>Retaked: <?php echo $statCards['classes']['retaked']; ?></span>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="empty-state small">
                            <i class="fas fa-chart-pie"></i>
                            <p>No class data available yet. Start by adding your first class.</p>
                            <a href="<?php echo url('admin/add-class.php'); ?>" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus"></i> Add Class
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- ===== Student Status Chart ===== -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-users text-primary"></i> Student Status</h3>
                </div>
                <div class="card-body">
                    <?php if ($statCards['students']['total'] > 0): ?>
                        <div class="chart-container chart-pie">
                            <canvas id="studentStatusChart"></canvas>
                        </div>
                        <div class="chart-legend">
                            <div class="legend-item">
                                <span class="color-dot" style="background: #10B981;"></span>
                                <span>Active: <?php echo $statCards['students']['active']; ?></span>
                            </div>
                            <div class="legend-item">
                                <span class="color-dot" style="background: #F59E0B;"></span>
                                <span>Pending: <?php echo $statCards['students']['pending']; ?></span>
                            </div>
                            <div class="legend-item">
                                <span class="color-dot" style="background: #EF4444;"></span>
                                <span>Suspended: <?php echo $statCards['students']['suspended']; ?></span>
                            </div>
                            <div class="legend-item">
                                <span class="color-dot" style="background: #6B7280;"></span>
                                <span>Inactive: <?php echo $statCards['students']['total'] - $statCards['students']['active'] - $statCards['students']['pending'] - $statCards['students']['suspended']; ?></span>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="empty-state small">
                            <i class="fas fa-users"></i>
                            <p>No student data available yet.</p>
                            <a href="<?php echo url('admin/students.php'); ?>" class="btn btn-primary btn-sm">
                                <i class="fas fa-users"></i> Manage Students
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- ===== Column 2: Charts ===== -->
        <div class="dashboard-column">
            
            <!-- ===== Monthly Fund Chart ===== -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-chart-bar text-primary"></i> Monthly Fund Overview</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($monthlyFundData)): ?>
                        <div class="chart-container chart-bar">
                            <canvas id="monthlyFundChart"></canvas>
                        </div>
                    <?php else: ?>
                        <div class="empty-state small">
                            <i class="fas fa-chart-bar"></i>
                            <p>No fund data available yet.</p>
                            <a href="<?php echo url('admin/add-income.php'); ?>" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus"></i> Add Income
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- ===== Assignment Status Chart ===== -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-chart-doughnut text-primary"></i> Assignment Overview</h3>
                </div>
                <div class="card-body">
                    <?php if ($statCards['assignments']['total'] > 0): ?>
                        <div class="chart-container chart-pie">
                            <canvas id="assignmentStatusChart"></canvas>
                        </div>
                        <div class="chart-legend">
                            <div class="legend-item">
                                <span class="color-dot" style="background: #3B82F6;"></span>
                                <span>Ongoing: <?php echo $statCards['assignments']['ongoing']; ?></span>
                            </div>
                            <div class="legend-item">
                                <span class="color-dot" style="background: #F59E0B;"></span>
                                <span>Due Soon: <?php echo $statCards['assignments']['due_soon']; ?></span>
                            </div>
                            <div class="legend-item">
                                <span class="color-dot" style="background: #EF4444;"></span>
                                <span>Overdue: <?php echo $statCards['assignments']['overdue']; ?></span>
                            </div>
                            <div class="legend-item">
                                <span class="color-dot" style="background: #6B7280;"></span>
                                <span>Completed: <?php echo $statCards['assignments']['total'] - $statCards['assignments']['ongoing'] - $statCards['assignments']['due_soon'] - $statCards['assignments']['overdue']; ?></span>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="empty-state small">
                            <i class="fas fa-tasks"></i>
                            <p>No assignment data available yet.</p>
                            <a href="<?php echo url('admin/add-assignment.php'); ?>" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus"></i> Add Assignment
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- ===== Recent Activities ===== -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-clock text-primary"></i> Recent Activities</h3>
            <span class="badge badge-info">Last 30 days</span>
        </div>
        <div class="card-body">
            <?php if (empty($recentActivities)): ?>
                <div class="empty-state small">
                    <i class="fas fa-history"></i>
                    <p>No recent activities to display. Activities will appear here as you use the system.</p>
                </div>
            <?php else: ?>
                <div class="activity-timeline">
                    <?php 
                    $typeIcons = [
                        'registration' => 'fa-user-plus',
                        'class' => 'fa-book',
                        'assignment' => 'fa-tasks',
                        'fund' => 'fa-coins',
                        'announcement' => 'fa-bullhorn'
                    ];
                    $typeColors = [
                        'registration' => 'success',
                        'class' => 'primary',
                        'assignment' => 'warning',
                        'fund' => 'info',
                        'announcement' => 'danger'
                    ];
                    $typeLabels = [
                        'registration' => 'New Registration',
                        'class' => 'Class',
                        'assignment' => 'Assignment',
                        'fund' => 'Fund Transaction',
                        'announcement' => 'Announcement'
                    ];
                    ?>
                    <?php foreach ($recentActivities as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon" style="background: var(--<?php echo $typeColors[$activity['type']] . '-light'; ?>); color: var(--<?php echo $typeColors[$activity['type']]; ?>);">
                                <i class="fas <?php echo $typeIcons[$activity['type']] ?? 'fa-info-circle'; ?>"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-header">
                                    <span class="activity-type badge badge-<?php echo $typeColors[$activity['type']]; ?>">
                                        <?php echo $typeLabels[$activity['type']] ?? ucfirst($activity['type']); ?>
                                    </span>
                                    <span class="activity-time"><?php echo timeAgo($activity['created_at']); ?></span>
                                </div>
                                <div class="activity-title">
                                    <?php if ($activity['link']): ?>
                                        <a href="<?php echo url(ltrim($activity['link'], '/')); ?>">
                                            <?php echo htmlspecialchars($activity['title']); ?>
                                        </a>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($activity['title']); ?>
                                    <?php endif; ?>
                                </div>
                                <?php if ($activity['detail']): ?>
                                    <div class="activity-detail">
                                        <small class="text-muted"><?php echo htmlspecialchars($activity['detail']); ?></small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="activity-footer">
                    <a href="<?php echo url('admin/activity-logs.php'); ?>" class="btn btn-sm btn-outline">
                        <i class="fas fa-history"></i> View All Activities
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- ===== Quick Actions ===== -->
    <div class="quick-actions">
        <h4><i class="fas fa-bolt text-primary"></i> Quick Actions</h4>
        <div class="actions-grid">
            <a href="<?php echo url('admin/add-class.php'); ?>" class="action-item">
                <div class="action-icon"><i class="fas fa-plus-circle"></i></div>
                <span>Add Class</span>
            </a>
            <a href="<?php echo url('admin/add-assignment.php'); ?>" class="action-item">
                <div class="action-icon"><i class="fas fa-plus-circle"></i></div>
                <span>Add Assignment</span>
            </a>
            <a href="<?php echo url('admin/registrations.php'); ?>" class="action-item">
                <div class="action-icon"><i class="fas fa-user-plus"></i></div>
                <span>Review Registrations</span>
                <?php if ($statCards['students']['pending_registrations'] > 0): ?>
                    <span class="badge badge-danger"><?php echo $statCards['students']['pending_registrations']; ?></span>
                <?php endif; ?>
            </a>
            <a href="<?php echo url('admin/add-income.php'); ?>" class="action-item">
                <div class="action-icon"><i class="fas fa-plus-circle"></i></div>
                <span>Add Income</span>
            </a>
            <a href="<?php echo url('admin/announcements.php'); ?>" class="action-item">
                <div class="action-icon"><i class="fas fa-bullhorn"></i></div>
                <span>Post Announcement</span>
            </a>
            <a href="<?php echo url('admin/students.php'); ?>" class="action-item">
                <div class="action-icon"><i class="fas fa-users"></i></div>
                <span>Manage Students</span>
            </a>
        </div>
    </div>
<script>
// Pass data to JavaScript for charts
const chartData = {
    classStatus: {
        scheduled: <?php echo $statCards['classes']['scheduled']; ?>,
        completed: <?php echo $statCards['classes']['completed']; ?>,
        cancelled: <?php echo $statCards['classes']['cancelled']; ?>,
        retaked: <?php echo $statCards['classes']['retaked']; ?>
    },
    studentStatus: {
        active: <?php echo $statCards['students']['active']; ?>,
        pending: <?php echo $statCards['students']['pending']; ?>,
        suspended: <?php echo $statCards['students']['suspended']; ?>,
        inactive: <?php echo $statCards['students']['total'] - $statCards['students']['active'] - $statCards['students']['pending'] - $statCards['students']['suspended']; ?>
    },
    assignmentStatus: {
        ongoing: <?php echo $statCards['assignments']['ongoing']; ?>,
        due_soon: <?php echo $statCards['assignments']['due_soon']; ?>,
        overdue: <?php echo $statCards['assignments']['overdue']; ?>,
        completed: <?php echo $statCards['assignments']['total'] - $statCards['assignments']['ongoing'] - $statCards['assignments']['due_soon'] - $statCards['assignments']['overdue']; ?>
    },
    monthlyFund: <?php echo json_encode($monthlyFundData); ?>
};

console.log('Dashboard Data:', chartData);
</script>

<?php include_once BASE_PATH . '/includes/footer.php'; ?>