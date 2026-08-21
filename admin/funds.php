<?php
/**
 * Admin - Class Fund Management
 */

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . '/includes/auth-middleware.php';

// Ensure admin access
Auth::requireAdmin();

$pageTitle = 'Class Fund Management';
$pageCss = ['admin.css', 'fund.css'];
$pageJs = ['fund.js', 'charts.js'];
$isAdmin = true;

$db = Database::getInstance();
$fundManager = new FundManager();

// Get class filter
$classId = $_GET['class_id'] ?? null;
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? 0;

// Handle actions
if ($action === 'delete' && $id) {
    if (!Security::verifyCSRFToken($_GET['csrf_token'] ?? '')) {
        Session::setFlash('error', 'Invalid security token');
        redirect(url('admin/funds.php' . ($classId ? '?class_id=' . $classId : '')));
    }
    
    $result = $fundManager->deleteTransaction($id);
    Session::setFlash($result['success'] ? 'success' : 'error', $result['message']);
    redirect(url('admin/funds.php' . ($classId ? '?class_id=' . $classId : '')));
}

// Get filters
$filters = [
    'type' => $_GET['type'] ?? '',
    'category' => $_GET['category'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'search' => $_GET['search'] ?? ''
];
// Build filters for funds
$filters = [
    'type' => $_GET['type'] ?? '',
    'category' => $_GET['category'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'search' => $_GET['search'] ?? ''
];

// Build filter query
$filterBuilder = new FilterBuilder();

if (!empty($filters['search'])) {
    $filterBuilder->addSearch($filters['search'], ['t.description', 't.reference_number', 't.payer_receiver']);
}

if (!empty($filters['type'])) {
    $filterBuilder->addEqual('transaction_type', $filters['type'], 't');
}

if (!empty($filters['category'])) {
    $filterBuilder->addEqual('category', $filters['category'], 't');
}

if (!empty($filters['date_from']) || !empty($filters['date_to'])) {
    $filterBuilder->addDateRange('transaction_date', $filters['date_from'], $filters['date_to'], 't');
}

if ($classId) {
    $filterBuilder->addEqual('class_id', $classId, 't');
}

// Get total count and paginate...

// Get transactions
$transactions = $fundManager->getTransactions($classId, $filters);

// Get classes for filter
$stmt = $db->query("SELECT id, class_title, class_code FROM classes ORDER BY class_title");
$classes = $stmt->fetchAll();

// Get fund summary
$funds = $fundManager->getFundSummary($classId);
$summary = $classId ? ($funds[0] ?? null) : null;

// Calculate totals
$totalBalance = 0;
$totalIncome = 0;
$totalExpenditure = 0;
$totalRefunds = 0;

foreach ($funds as $fund) {
    $totalIncome += (float)$fund['total_income'];
    $totalExpenditure += (float)$fund['total_expenditure'];
    $totalRefunds += (float)$fund['total_refunds'];
}
$totalBalance = $totalIncome - $totalExpenditure - $totalRefunds;

// Get categories for filter
$categories = $fundManager->getCategories($classId);

// Get monthly data for chart
$monthlyData = $fundManager->getMonthlySummary($classId ?: 1, 6);

include_once BASE_PATH . '/includes/header.php';
include_once BASE_PATH . '/includes/sidebar-admin.php';
?>

<div class="main-content">
    <div class="page-header">
        <div class="page-header-content">
            <div>
                <h1>Class Fund Management</h1>
                <p class="text-muted">Track income, expenditure, and refunds</p>
            </div>
            <div class="page-actions">
                <div class="btn-group">
                    <button class="btn btn-primary dropdown-toggle" data-dropdown="addTransactionDropdown">
                        <i class="fas fa-plus"></i> Add Transaction
                    </button>
                    <div class="dropdown-menu" id="addTransactionDropdown">
                        <a href="<?php echo url('admin/add-income.php' . ($classId ? '?class_id=' . $classId : '')); ?>" class="dropdown-item">
                            <i class="fas fa-arrow-down text-success"></i> Add Income
                        </a>
                        <a href="<?php echo url('admin/add-expenditure.php' . ($classId ? '?class_id=' . $classId : '')); ?>" class="dropdown-item">
                            <i class="fas fa-arrow-up text-danger"></i> Add Expenditure
                        </a>
                        <a href="<?php echo url('admin/add-refund.php' . ($classId ? '?class_id=' . $classId : '')); ?>" class="dropdown-item">
                            <i class="fas fa-undo text-warning"></i> Add Refund
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Class Filter -->
    <div class="filter-bar">
        <form method="GET" action="" class="filter-form">
            <div class="filter-row">
                <div class="filter-group">
                    <select name="class_id" onchange="this.form.submit()">
                        <option value="">All Classes</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?php echo $class['id']; ?>" 
                                    <?php echo $classId == $class['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($class['class_code'] . ' - ' . $class['class_title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Financial Summary Cards -->
    <div class="fund-stats-grid">
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
    
    <!-- Class-wise Summary -->
    <?php if (!$classId && count($funds) > 0): ?>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Class-wise Financial Summary</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Class</th>
                                <th>Income</th>
                                <th>Expenditure</th>
                                <th>Refunds</th>
                                <th>Balance</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($funds as $fund): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($fund['class_title']); ?></strong>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($fund['class_code']); ?></small>
                                    </td>
                                    <td class="text-success">BDT <?php echo number_format($fund['total_income'], 2); ?></td>
                                    <td class="text-danger">BDT <?php echo number_format($fund['total_expenditure'], 2); ?></td>
                                    <td class="text-warning">BDT <?php echo number_format($fund['total_refunds'], 2); ?></td>
                                    <td class="text-primary font-weight-bold">
                                        BDT <?php echo number_format($fund['balance'], 2); ?>
                                    </td>
                                    <td>
                                        <a href="<?php echo url('admin/funds.php?class_id=' . $fund['class_id']); ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Monthly Chart -->
    <?php if ($classId && !empty($monthlyData)): ?>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Monthly Financial Overview</h3>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="monthlyChart"></canvas>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Transactions Table -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list"></i> Transaction History
                <span class="badge badge-info"><?php echo count($transactions); ?> transactions</span>
            </h3>
        </div>
        <div class="card-body">
            <!-- Filters -->
            <div class="transaction-filters">
                <form method="GET" action="" class="filter-form">
                    <?php if ($classId): ?>
                        <input type="hidden" name="class_id" value="<?php echo $classId; ?>">
                    <?php endif; ?>
                    
                    <div class="filter-row">
                        <div class="filter-group">
                            <input type="text" name="search" placeholder="Search..." 
                                   value="<?php echo htmlspecialchars($filters['search']); ?>">
                        </div>
                        
                        <div class="filter-group">
                            <select name="type">
                                <option value="">All Types</option>
                                <option value="income" <?php echo $filters['type'] === 'income' ? 'selected' : ''; ?>>Income</option>
                                <option value="expenditure" <?php echo $filters['type'] === 'expenditure' ? 'selected' : ''; ?>>Expenditure</option>
                                <option value="refund" <?php echo $filters['type'] === 'refund' ? 'selected' : ''; ?>>Refund</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <select name="category">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo htmlspecialchars($category); ?>" 
                                            <?php echo $filters['category'] === $category ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group date-group">
                            <input type="date" name="date_from" placeholder="From" 
                                   value="<?php echo htmlspecialchars($filters['date_from']); ?>">
                            <span>to</span>
                            <input type="date" name="date_to" placeholder="To" 
                                   value="<?php echo htmlspecialchars($filters['date_to']); ?>">
                        </div>
                        
                        <div class="filter-group filter-actions">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fas fa-search"></i> Filter
                            </button>
                            <a href="<?php echo url('admin/funds.php' . ($classId ? '?class_id=' . $classId : '')); ?>" class="btn btn-outline btn-sm">
                                <i class="fas fa-redo"></i> Reset
                            </a>
                        </div>
                    </div>
                </form>
            </div>
            
            <?php if (empty($transactions)): ?>
                <div class="empty-state">
                    <i class="fas fa-coins"></i>
                    <h4>No Transactions Found</h4>
                    <p>Try adjusting your filters or add a new transaction.</p>
                    <div class="empty-actions">
                        <a href="<?php echo url('admin/add-income.php' . ($classId ? '?class_id=' . $classId : '')); ?>" class="btn btn-success">
                            <i class="fas fa-plus"></i> Add Income
                        </a>
                        <a href="<?php echo url('admin/add-expenditure.php' . ($classId ? '?class_id=' . $classId : '')); ?>" class="btn btn-danger">
                            <i class="fas fa-plus"></i> Add Expenditure
                        </a>
                        <a href="<?php echo url('admin/add-refund.php' . ($classId ? '?class_id=' . $classId : '')); ?>" class="btn btn-warning">
                            <i class="fas fa-plus"></i> Add Refund
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table transaction-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Type</th>
                                <th>Category</th>
                                <th>Amount</th>
                                <th>Description</th>
                                <th>Date</th>
                                <th>Reference</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $index => $transaction): ?>
                                <tr class="transaction-<?php echo $transaction['transaction_type']; ?>">
                                    <td><?php echo $index + 1; ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $transaction['transaction_type']; ?>">
                                            <i class="fas fa-<?php 
                                                echo $transaction['transaction_type'] === 'income' ? 'arrow-down' : 
                                                    ($transaction['transaction_type'] === 'expenditure' ? 'arrow-up' : 'undo'); 
                                            ?>"></i>
                                            <?php echo ucfirst($transaction['transaction_type']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($transaction['category'] ?? 'N/A'); ?></td>
                                    <td class="amount-<?php echo $transaction['transaction_type']; ?>">
                                        BDT <?php echo number_format($transaction['amount'], 2); ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars(substr($transaction['description'], 0, 30)); ?>
                                        <?php if (strlen($transaction['description']) > 30): ?>...<?php endif; ?>
                                    </td>
                                    <td><?php echo formatDate($transaction['transaction_date']); ?></td>
                                    <td><?php echo htmlspecialchars($transaction['reference_number'] ?? 'N/A'); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="<?php echo url('admin/edit-transaction.php?id=' . $transaction['id'] . ($classId ? '&class_id=' . $classId : '')); ?>" 
                                               class="btn btn-sm btn-primary">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="<?php echo url('admin/funds.php?action=delete&id=' . $transaction['id'] . '&csrf_token=' . Security::generateCSRFToken() . ($classId ? '&class_id=' . $classId : '')); ?>" 
                                               class="btn btn-sm btn-danger"
                                               onclick="return confirmDelete('<?php echo htmlspecialchars($transaction['description']); ?>', '<?php echo number_format($transaction['amount'], 2); ?>')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal modal-sm">
        <div class="modal-header">
            <h3>Confirm Delete</h3>
            <button class="modal-close" onclick="closeModal('deleteModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="delete-warning">
                <i class="fas fa-exclamation-triangle text-danger"></i>
                <p>Are you sure you want to delete this transaction?</p>
                <div class="transaction-details">
                    <span id="deleteDescription"></span>
                    <span class="amount" id="deleteAmount"></span>
                </div>
                <p class="text-danger small"><i class="fas fa-exclamation-circle"></i> This action cannot be undone.</p>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeModal('deleteModal')">Cancel</button>
            <a href="#" id="deleteConfirmBtn" class="btn btn-danger">Delete Transaction</a>
        </div>
    </div>
</div>

<script>
// Chart.js for monthly data
<?php if ($classId && !empty($monthlyData)): ?>
const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
const monthlyData = <?php echo json_encode($monthlyData); ?>;

new Chart(monthlyCtx, {
    type: 'bar',
    data: {
        labels: monthlyData.map(item => item.month),
        datasets: [
            {
                label: 'Income',
                data: monthlyData.map(item => item.income),
                backgroundColor: 'rgba(16, 185, 129, 0.7)',
                borderColor: '#10B981',
                borderWidth: 2
            },
            {
                label: 'Expenditure',
                data: monthlyData.map(item => item.expenditure),
                backgroundColor: 'rgba(239, 68, 68, 0.7)',
                borderColor: '#EF4444',
                borderWidth: 2
            },
            {
                label: 'Refunds',
                data: monthlyData.map(item => item.refunds),
                backgroundColor: 'rgba(245, 158, 11, 0.7)',
                borderColor: '#F59E0B',
                borderWidth: 2
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    usePointStyle: true,
                    padding: 20
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return 'BDT ' + value.toLocaleString();
                    }
                }
            }
        }
    }
});
<?php endif; ?>

function confirmDelete(description, amount) {
    document.getElementById('deleteDescription').textContent = description;
    document.getElementById('deleteAmount').textContent = 'BDT ' + amount;
    
    const confirmBtn = document.getElementById('deleteConfirmBtn');
    const currentUrl = window.location.href;
    confirmBtn.href = currentUrl + '&confirm=yes';
    
    openModal('deleteModal');
    return false;
}

// Dropdown toggle
document.querySelectorAll('.dropdown-toggle').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.stopPropagation();
        const target = document.getElementById(this.dataset.dropdown);
        if (target) {
            target.classList.toggle('show');
        }
    });
});

document.addEventListener('click', function() {
    document.querySelectorAll('.dropdown-menu.show').forEach(d => {
        d.classList.remove('show');
    });
});
</script>

<?php include_once BASE_PATH . '/includes/footer.php'; ?>