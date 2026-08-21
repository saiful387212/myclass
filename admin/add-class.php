<?php
/**
 * Admin - Add Class
 */

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . '/includes/auth-middleware.php';

// Ensure admin access
Auth::requireAdmin();

$pageTitle = 'Add Class';
$pageCss = ['admin.css', 'forms.css'];
$pageJs = ['add-class.js'];
$isAdmin = true;

$db = Database::getInstance();
$classManager = new ClassManager();

$error = null;
$success = null;

// Get courses for dropdown
$stmt = $db->query("SELECT id, course_code, course_title FROM courses WHERE status = 'active' ORDER BY course_code");
$courses = $stmt->fetchAll();

// Default values
$formData = [
    'course_id' => '',
    'class_code' => '',
    'class_title' => '',
    'description' => '',
    'instructor' => '',
    'class_date' => date('Y-m-d'),
    'start_time' => '09:00',
    'end_time' => '10:30',
    'room' => '',
    'venue' => '',
    'semester' => date('Y') . '-' . (date('Y') + 1),
    'academic_year' => date('Y') . '-' . (date('Y') + 1),
    'topic' => '',
    'class_task' => '',
    'additional_info' => '',
    'status' => 'scheduled'
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        if (($_POST['form_action'] ?? 'create_class') === 'add_course') {
            $newCourseCode = strtoupper(trim($_POST['new_course_code'] ?? ''));
            $newCourseTitle = trim($_POST['new_course_title'] ?? '');

            if ($newCourseCode === '' || $newCourseTitle === '') {
                $error = 'Course code and course name are required.';
            } else {
                try {
                    $stmt = $db->prepare("SELECT id FROM courses WHERE course_code = ?");
                    $stmt->execute([$newCourseCode]);

                    if ($stmt->fetch()) {
                        $error = 'A course with this code already exists.';
                    } else {
                        $stmt = $db->prepare("INSERT INTO courses (course_code, course_title, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
                        $stmt->execute([$newCourseCode, $newCourseTitle]);
                        $newCourseId = $db->lastInsertId();
                        $stmt = $db->query("SELECT id, course_code, course_title FROM courses WHERE status = 'active' ORDER BY course_code");
                        $courses = $stmt->fetchAll();
                        $formData['course_id'] = $newCourseId;
                        $success = 'Course added successfully. It is selected for this class.';
                        logActivity('course_created', "Created course: {$newCourseCode} - {$newCourseTitle}");
                    }
                } catch (Throwable $e) {
                    $error = 'Unable to add the course. Please try again.';
                    error_log('Course creation failed: ' . $e->getMessage());
                }
            }
        } else {
            // Get form data
            $data = [
                'course_id' => $_POST['course_id'] ?? '',
                'class_code' => trim($_POST['class_code'] ?? ''),
                'class_title' => trim($_POST['class_title'] ?? ''),
                'description' => trim($_POST['description'] ?? ''),
                'instructor' => trim($_POST['instructor'] ?? ''),
                'class_date' => $_POST['class_date'] ?? '',
                'start_time' => $_POST['start_time'] ?? '',
                'end_time' => $_POST['end_time'] ?? '',
                'room' => trim($_POST['room'] ?? ''),
                'venue' => trim($_POST['venue'] ?? ''),
                'semester' => trim($_POST['semester'] ?? ''),
                'academic_year' => trim($_POST['academic_year'] ?? ''),
                'topic' => trim($_POST['topic'] ?? ''),
                'class_task' => trim($_POST['class_task'] ?? ''),
                'additional_info' => trim($_POST['additional_info'] ?? ''),
                'status' => $_POST['status'] ?? 'scheduled',
                'created_by' => Session::get('user_id')
            ];
            
            $result = $classManager->create($data);
            
            if ($result['success']) {
                Session::setFlash('success', 'Class added successfully!');
                redirect(url('admin/classes.php'));
            } else {
                $error = $result['message'];
                $formData = $data;
            }
        }
    }
}

include_once BASE_PATH . '/includes/header.php';
include_once BASE_PATH . '/includes/sidebar-admin.php';
?>

<div class="main-content">
    <div class="page-header">
        <div class="page-header-content">
            <div>
                <h1>Add New Class</h1>
                <p class="text-muted">Create a new class in the system</p>
            </div>
            <a href="<?php echo url('admin/classes.php'); ?>" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Classes
            </a>
        </div>
    </div>
    
    <div class="card">
        <div class="card-body">
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" class="class-form" data-validate>
                <?php echo Security::csrfField(); ?>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="course_id">Course <span class="required">*</span></label>
                        <select id="course_id" name="course_id" class="form-control" required>
                            <option value="">Select Course</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo $course['id']; ?>" 
                                        <?php echo $formData['course_id'] == $course['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                            <details class="form-help" style="margin-top: 8px;">
                                <summary><i class="fas fa-plus-circle"></i> Add a new course</summary>
                                <div class="form-row" style="margin-top: 12px;">
                                    <div class="form-group">
                                        <label for="new_course_code">Course Code</label>
                                        <input type="text" id="new_course_code" name="new_course_code" class="form-control" maxlength="20" placeholder="e.g., GEO-501">
                                    </div>
                                    <div class="form-group">
                                        <label for="new_course_title">Course Name</label>
                                        <input type="text" id="new_course_title" name="new_course_title" class="form-control" maxlength="200" placeholder="Enter course name">
                                    </div>
                                </div>
                                <button type="submit" name="form_action" value="add_course" class="btn btn-outline btn-sm">
                                    <i class="fas fa-plus"></i> Save Course
                                </button>
                            </details>
                    </div>
                    
                    <div class="form-group">
                        <label for="class_code">Class Code <span class="required">*</span></label>
                        <input type="text" id="class_code" name="class_code" class="form-control" 
                               placeholder="e.g., GEO-101-01" value="<?php echo htmlspecialchars($formData['class_code']); ?>" required>
                        <span class="form-help">Unique identifier for this class</span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="class_title">Class Title <span class="required">*</span></label>
                    <input type="text" id="class_title" name="class_title" class="form-control" 
                           placeholder="Enter class title" value="<?php echo htmlspecialchars($formData['class_title']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" class="form-control" rows="3" 
                              placeholder="Describe the class"><?php echo htmlspecialchars($formData['description']); ?></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="instructor">Instructor <span class="required">*</span></label>
                        <input type="text" id="instructor" name="instructor" class="form-control" 
                               placeholder="Instructor name" value="<?php echo htmlspecialchars($formData['instructor']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="room">Room</label>
                        <input type="text" id="room" name="room" class="form-control" 
                               placeholder="Room number" value="<?php echo htmlspecialchars($formData['room']); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="class_date">Date <span class="required">*</span></label>
                        <input type="date" id="class_date" name="class_date" class="form-control" 
                               value="<?php echo htmlspecialchars($formData['class_date']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="start_time">Start Time <span class="required">*</span></label>
                        <input type="time" id="start_time" name="start_time" class="form-control" 
                               value="<?php echo htmlspecialchars($formData['start_time']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="end_time">End Time <span class="required">*</span></label>
                        <input type="time" id="end_time" name="end_time" class="form-control" 
                               value="<?php echo htmlspecialchars($formData['end_time']); ?>" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="venue">Venue</label>
                        <input type="text" id="venue" name="venue" class="form-control" 
                               placeholder="Venue name" value="<?php echo htmlspecialchars($formData['venue']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status" class="form-control">
                            <option value="scheduled" <?php echo $formData['status'] === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                            <option value="completed" <?php echo $formData['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo $formData['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            <option value="retaked" <?php echo $formData['status'] === 'retaked' ? 'selected' : ''; ?>>Retaked</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="semester">Semester</label>
                        <input type="text" id="semester" name="semester" class="form-control" 
                               placeholder="e.g., Fall 2024" value="<?php echo htmlspecialchars($formData['semester']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="academic_year">Academic Year</label>
                        <input type="text" id="academic_year" name="academic_year" class="form-control" 
                               placeholder="e.g., 2024-2025" value="<?php echo htmlspecialchars($formData['academic_year']); ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="topic">Topic / Planned Topic</label>
                    <input type="text" id="topic" name="topic" class="form-control" 
                           placeholder="Class topic" value="<?php echo htmlspecialchars($formData['topic']); ?>">
                </div>
                
                <div class="form-group">
                    <label for="class_task">Class Task</label>
                    <textarea id="class_task" name="class_task" class="form-control" rows="2" 
                              placeholder="Tasks for this class"><?php echo htmlspecialchars($formData['class_task']); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="additional_info">Additional Information</label>
                    <textarea id="additional_info" name="additional_info" class="form-control" rows="2" 
                              placeholder="Any additional information"><?php echo htmlspecialchars($formData['additional_info']); ?></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Create Class
                    </button>
                    <a href="<?php echo url('admin/classes.php'); ?>" class="btn btn-outline">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include_once BASE_PATH . '/includes/footer.php'; ?>