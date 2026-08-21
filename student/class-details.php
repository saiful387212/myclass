<?php
/**
 * Student - View Class Details
 */

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . '/includes/auth-middleware.php';

// Ensure student access
Auth::requireStudent();

$pageTitle = 'Class Details';
$pageCss = ['student.css', 'class-details.css'];
$isStudent = true;

$id = $_GET['id'] ?? 0;
if (!$id) {
    Session::setFlash('error', 'Invalid class ID');
    redirect(url('student/classes.php'));
}

$db = Database::getInstance();
$classManager = new ClassManager();
$studentId = Session::get('user_id');

// Check if student is enrolled
if (!$classManager->isStudentEnrolled($studentId, $id)) {
    Session::setFlash('error', 'You are not enrolled in this class');
    redirect(url('student/classes.php'));
}

$class = $classManager->getById($id);
if (!$class) {
    Session::setFlash('error', 'Class not found');
    redirect(url('student/classes.php'));
}

// Get materials
$materials = $classManager->getMaterials($id);

// Get assignments
$stmt = $db->prepare("
    SELECT a.*, 
           (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.id AND student_id = ?) as has_submitted
    FROM assignments a
    WHERE a.class_id = ? AND a.status IN ('published', 'open')
    ORDER BY a.deadline ASC
");
$stmt->execute([$studentId, $id]);
$assignments = $stmt->fetchAll();

include_once BASE_PATH . '/includes/header.php';
?>

<div class="dashboard-container">
    <div class="page-header">
        <div class="page-header-content">
            <div>
                <h1><?php echo htmlspecialchars($class['class_title']); ?></h1>
                <p class="text-muted"><?php echo htmlspecialchars($class['class_code']); ?> | <?php echo htmlspecialchars($class['course_code']); ?></p>
            </div>
            <a href="<?php echo url('student/classes.php'); ?>" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Classes
            </a>
        </div>
    </div>
    
    <!-- Class Info -->
    <div class="class-info-card">
        <div class="info-grid">
            <div class="info-item">
                <label>Status</label>
                <span class="badge badge-<?php echo $class['status']; ?>"><?php echo ucfirst($class['status']); ?></span>
            </div>
            <div class="info-item">
                <label>Instructor</label>
                <span><?php echo htmlspecialchars($class['instructor']); ?></span>
            </div>
            <div class="info-item">
                <label>Date</label>
                <span><?php echo formatDate($class['class_date']); ?></span>
            </div>
            <div class="info-item">
                <label>Time</label>
                <span><?php echo date('h:i A', strtotime($class['start_time'])); ?> - <?php echo date('h:i A', strtotime($class['end_time'])); ?></span>
            </div>
            <div class="info-item">
                <label>Room</label>
                <span><?php echo htmlspecialchars($class['room'] ?? 'TBA'); ?></span>
            </div>
        </div>
        
        <?php if ($class['topic']): ?>
            <div class="info-item full">
                <label>Topic</label>
                <span><?php echo htmlspecialchars($class['topic']); ?></span>
            </div>
        <?php endif; ?>
        
        <?php if ($class['class_task']): ?>
            <div class="info-item full">
                <label>Class Task</label>
                <span><?php echo nl2br(htmlspecialchars($class['class_task'])); ?></span>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Materials -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-file-alt"></i> Class Notes & Materials</h3>
        </div>
        <div class="card-body">
            <?php if (empty($materials)): ?>
                <div class="empty-state small">
                    <i class="fas fa-file-alt"></i>
                    <p>No notes or materials available for this class.</p>
                </div>
            <?php else: ?>
                <div class="materials-list">
                    <?php foreach ($materials as $material): ?>
                        <div class="material-item">
                            <div class="material-icon">
                                <i class="fas <?php echo $material['link_url'] ? 'fa-link' : getFileIcon($material['file_name'] ?? 'file'); ?>"></i>
                            </div>
                            <div class="material-info">
                                <h4><?php echo htmlspecialchars($material['title']); ?></h4>
                                <?php if ($material['description']): ?>
                                    <p><?php echo htmlspecialchars($material['description']); ?></p>
                                <?php endif; ?>
                                <div class="material-meta">
                                    <span><i class="fas fa-clock"></i> <?php echo timeAgo($material['created_at']); ?></span>
                                    <?php if ($material['file_size']): ?>
                                        <span><i class="fas fa-file"></i> <?php echo getFileSize($material['file_size']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if ($material['link_url']): ?>
                                <a href="<?php echo htmlspecialchars($material['link_url']); ?>" class="btn btn-sm btn-primary" target="_blank" rel="noopener noreferrer">
                                    <i class="fas fa-external-link-alt"></i> Open Link
                                </a>
                            <?php elseif ($material['file_path']): ?>
                                <a href="<?php echo uploadUrl($material['file_path']); ?>" class="btn btn-sm btn-primary" target="_blank">
                                    <i class="fas fa-download"></i> Download
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Assignments -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-tasks"></i> Assignments</h3>
        </div>
        <div class="card-body">
            <?php if (empty($assignments)): ?>
                <div class="empty-state small">
                    <i class="fas fa-tasks"></i>
                    <p>No assignments for this class.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Type</th>
                                <th>Max Score</th>
                                <th>Deadline</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assignments as $assignment): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($assignment['title']); ?></td>
                                    <td><?php echo ucfirst($assignment['assignment_type']); ?></td>
                                    <td><?php echo $assignment['max_score']; ?></td>
                                    <td><?php echo formatDateTime($assignment['deadline']); ?></td>
                                    <td>
                                        <?php if ($assignment['has_submitted'] > 0): ?>
                                            <span class="badge badge-success">Submitted</span>
                                        <?php elseif (strtotime($assignment['deadline']) < time()): ?>
                                            <span class="badge badge-danger">Overdue</span>
                                        <?php else: ?>
                                            <span class="badge badge-warning">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="<?php echo url('student/assignment-details.php?id=' . $assignment['id']); ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i>
                                        </a>
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

<?php include_once BASE_PATH . '/includes/footer.php'; ?>