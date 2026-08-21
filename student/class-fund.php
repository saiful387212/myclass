<?php
/**
 * Student - View Class Fund
 */

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . '/includes/auth-middleware.php';

// Ensure student access
Auth::requireStudent();

$pageTitle = 'Class Fund';
$pageCss = ['student.css', 'fund.css'];
$isStudent = true;

$db = Database::getInstance();
$fundManager = new FundManager();
$studentId = Session::get('user_id');

// Get student's funds
$funds = $fundManager->getStudentFunds($studentId);

// Calculate totals
$totalBalance = 0;
$totalIncome = 0;
$totalExpenditure = 0;
$totalRefunds = 0;

foreach ($funds as $fund) {
    $totalBalance += (float)$fund['balance'];
    $totalIncome += (float)$fund['total_income'];
    $totalExpenditure += (float)$fund['total_expenditure'];
    $totalRefunds += (float)$fund['total_refunds'];
}

include_once BASE_PATH . '/includes/header.php';
?>

<div class="dashboard-container">
    <div class="page-header">
        <div class="page-header-content">
            <div>
                <h1>Class Fund</h1>
                <p class="text-muted">Financial overview of your enrolled classes</p>
            </div>
        </div>
    </div>
    
    <!-- Fund Summary -->
    <div class="fund-stats-grid student">
        <div class="stat-card stat-income">
            <div class="stat-icon"><i class="fas fa-arrow-down"></i></div>
            <div class="stat-info">
                <h3>BDT <?php echo number_format($totalIncome, 2); ?></h3>
                <p>Total Income</p>
            </div>
        </div>
        <div class="stat-card stat-expenditure">
            <div class="stat-icon"><i class="fas fa-arrow-up"></i></div>
            <div class="stat-info">
                <h3>BDT <?php echo number_format($totalExpenditure, 2); ?></h3>
                <p>Total Expenditure</p>
            </div>
        </div>
        <div class="stat-card stat-refund">
            <div class="stat-icon"><i class="fas fa-undo"></i></div>
            <div class="stat-info">
                <h3>BDT <?php echo number_format($totalRefunds, 2); ?></h3>
                <p>Total Refunds</p>
            </div>
        </div>
        <div class="stat-card stat-balance">
            <div class="stat-icon"><i class="fas fa-wallet"></i></div>
            <div class="stat-info">
                <h3>BDT <?php echo number_format($totalBalance, 2); ?></h3>
                <p>Current Balance</p>
            </div>
        </div>
    </div>
    
    <?php if (empty($funds)): ?>
        <div class="empty-state">
            <i class="fas fa-coins"></i>
            <h3>No Fund Data Available</h3>
            <p>You are not enrolled in any classes with fund information.</p>
        </div>
    <?php else: ?>
        <div class="fund-classes-grid">
            <?php foreach ($funds as $fund): ?>
                <div class="fund-class-card">
                    <div class="fund-class-header">
                        <h3><?php echo htmlspecialchars($fund['class_title']); ?></h3>
                        <span class="class-code"><?php echo htmlspecialchars($fund['class_code']); ?></span>
                    </div>
                    
                    <div class="fund-class-body">
                        <div class="fund-details-grid">
                            <div class="fund-detail">
                                <span class="label">Balance</span>
                                <span class="value balance">BDT <?php echo number_format($fund['balance'], 2); ?></span>
                            </div>
                            <div class="fund-detail">
                                <span class="label">Income</span>
                                <span class="value income">BDT <?php echo number_format($fund['total_income'], 2); ?></span>
                            </div>
                            <div class="fund-detail">
                                <span class="label">Expenditure</span>
                                <span class="value expenditure">BDT <?php echo number_format($fund['total_expenditure'], 2); ?></span>
                            </div>
                            <div class="fund-detail">
                                <span class="label">Refunds</span>
                                <span class="value refund">BDT <?php echo number_format($fund['total_refunds'], 2); ?></span>
                            </div>
                        </div>
                        
                        <div class="fund-progress">
                            <div class="progress-bar-container">
                                <div class="progress-label">
                                    <span>Income vs Expenditure</span>
                                    <span><?php echo $fund['total_income'] > 0 ? round(($fund['total_expenditure'] / $fund['total_income']) * 100) : 0; ?>%</span>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar" style="width: <?php echo $fund['total_income'] > 0 ? min(($fund['total_expenditure'] / $fund['total_income']) * 100, 100) : 0; ?>%;"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="fund-meta">
                            <span><i class="fas fa-user-tie"></i> <?php echo htmlspecialchars($fund['instructor']); ?></span>
                            <span><i class="fas fa-file-invoice"></i> <?php echo $fund['transaction_count']; ?> transactions</span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <div class="info-box">
        <i class="fas fa-info-circle"></i>
        <div>
            <h4>About Class Fund</h4>
            <p>This section displays financial information for all classes you are enrolled in. 
            The balance is calculated as: <strong>Balance = Total Income - Total Expenditure - Total Refunds</strong></p>
        </div>
    </div>
</div>

<?php include_once BASE_PATH . '/includes/footer.php'; ?>