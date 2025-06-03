<?php
$page_title = 'Manage Staff';
require_once '../includes/header.php';

$session->requireRole('hospital_admin');

$database = new Database();
$db = $database->getConnection();

// ADD THIS LINE: Initialize NeoCareUtils
require_once '../includes/functions.php';

$current_user = $session->getCurrentUser();
$hospital_id = $current_user['hospital_id'];

$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'add') {
        $username = $utils->cleanInput($_POST['username'] ?? '');
        $password = $utils->cleanInput($_POST['password'] ?? '');
        $full_name = $utils->cleanInput($_POST['full_name'] ?? '');
        $email = $utils->cleanInput($_POST['email'] ?? '');
        $phone = $utils->cleanInput($_POST['phone'] ?? '');
        $role = $utils->cleanInput($_POST['role'] ?? '');
        
        if (empty($username) || empty($password) || empty($full_name) || empty($role)) {
            $error = 'Please fill in all required fields';
        } elseif (!in_array($role, ['doctor', 'nurse'])) {
            $error = 'Invalid role selected';
        } else {
            // Validate email if provided
            if (!empty($email) && !$utils->validateEmail($email)) {
                $error = 'Please enter a valid email address';
            }
            // Validate phone if provided
            elseif (!empty($phone) && !$utils->validatePhone($phone)) {
                $error = 'Please enter a valid phone number (Tanzanian format)';
            } else {
                // Check if username exists
                $check_query = "SELECT COUNT(*) as count FROM users WHERE username = :username";
                $check_stmt = $db->prepare($check_query);
                $check_stmt->bindParam(':username', $username);
                $check_stmt->execute();
                $exists = $check_stmt->fetch(PDO::FETCH_ASSOC)['count'];
                
                if ($exists > 0) {
                    $error = 'Username already exists';
                } else {
                    $query = "INSERT INTO users (hospital_id, username, password, full_name, email, phone, role, is_active, created_at) 
                             VALUES (:hospital_id, :username, :password, :full_name, :email, :phone, :role, 1, NOW())";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':hospital_id', $hospital_id);
                    $stmt->bindParam(':username', $username);
                    $stmt->bindParam(':password', $password);
                    $stmt->bindParam(':full_name', $full_name);
                    $stmt->bindParam(':email', $email);
                    $stmt->bindParam(':phone', $phone);
                    $stmt->bindParam(':role', $role);
                    
                    if ($stmt->execute()) {
                        $success = 'Staff member added successfully';
                    } else {
                        $error = 'Failed to add staff member: ' . implode(', ', $stmt->errorInfo());
                    }
                }
            }
        }
    } elseif ($action == 'edit') {
        $user_id = $_POST['user_id'] ?? '';
        $username = $utils->cleanInput($_POST['username'] ?? '');
        $password = $utils->cleanInput($_POST['password'] ?? '');
        $full_name = $utils->cleanInput($_POST['full_name'] ?? '');
        $email = $utils->cleanInput($_POST['email'] ?? '');
        $phone = $utils->cleanInput($_POST['phone'] ?? '');
        $role = $utils->cleanInput($_POST['role'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if (empty($username) || empty($full_name) || empty($role)) {
            $error = 'Please fill in all required fields';
        } elseif (!in_array($role, ['doctor', 'nurse'])) {
            $error = 'Invalid role selected';
        } else {
            // Validate email if provided
            if (!empty($email) && !$utils->validateEmail($email)) {
                $error = 'Please enter a valid email address';
            }
            // Validate phone if provided
            elseif (!empty($phone) && !$utils->validatePhone($phone)) {
                $error = 'Please enter a valid phone number (Tanzanian format)';
            } else {
                $query = "UPDATE users SET username = :username, full_name = :full_name, email = :email, 
                         phone = :phone, role = :role, is_active = :is_active";
                
                if (!empty($password)) {
                    $query .= ", password = :password";
                }
                
                $query .= " WHERE user_id = :user_id AND hospital_id = :hospital_id";
                
                $stmt = $db->prepare($query);
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':full_name', $full_name);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':phone', $phone);
                $stmt->bindParam(':role', $role);
                $stmt->bindParam(':is_active', $is_active);
                $stmt->bindParam(':user_id', $user_id);
                $stmt->bindParam(':hospital_id', $hospital_id);
                
                if (!empty($password)) {
                    $stmt->bindParam(':password', $password);
                }
                
                if ($stmt->execute()) {
                    $success = 'Staff member updated successfully';
                } else {
                    $error = 'Failed to update staff member';
                }
            }
        }
    } elseif ($action == 'delete') {
        $user_id = $_POST['user_id'] ?? '';
        
        // Check if staff member has any children registered or vaccines administered
        $check_query = "SELECT 
            (SELECT COUNT(*) FROM children WHERE registered_by = :user_id) as children_registered,
            (SELECT COUNT(*) FROM vaccination_schedule WHERE administered_by = :user_id) as vaccines_administered";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':user_id', $user_id);
        $check_stmt->execute();
        $activity_check = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($activity_check['children_registered'] > 0 || $activity_check['vaccines_administered'] > 0) {
            $error = 'Cannot delete staff member. They have registered children or administered vaccines.';
        } else {
            $query = "DELETE FROM users WHERE user_id = :user_id AND hospital_id = :hospital_id AND role IN ('doctor', 'nurse')";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':hospital_id', $hospital_id);
            
            if ($stmt->execute()) {
                $success = 'Staff member deleted successfully';
            } else {
                $error = 'Failed to delete staff member';
            }
        }
    }
}

// Get all staff members for this hospital
$query = "SELECT * FROM users WHERE hospital_id = :hospital_id AND role IN ('doctor', 'nurse') ORDER BY role, full_name";
$stmt = $db->prepare($query);
$stmt->bindParam(':hospital_id', $hospital_id);
$stmt->execute();
$staff_members = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get staff statistics
$stats_query = "SELECT 
    COUNT(CASE WHEN role = 'doctor' THEN 1 END) as doctors_count,
    COUNT(CASE WHEN role = 'nurse' THEN 1 END) as nurses_count,
    COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_count,
    COUNT(*) as total_count
    FROM users 
    WHERE hospital_id = :hospital_id AND role IN ('doctor', 'nurse')";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->bindParam(':hospital_id', $hospital_id);
$stats_stmt->execute();
$staff_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="fas fa-users me-2 text-primary"></i>Manage Staff</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStaffModal">
            <i class="fas fa-plus me-1"></i>Add Staff Member
        </button>
        <a href="dashboard.php" class="btn btn-outline-secondary ms-2">
            <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
        </a>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Staff Statistics -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-center border-primary">
            <div class="card-body">
                <i class="fas fa-user-md fa-2x text-primary mb-2"></i>
                <h4 class="text-primary"><?php echo $staff_stats['doctors_count']; ?></h4>
                <p class="card-text">Doctors</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center border-success">
            <div class="card-body">
                <i class="fas fa-user-nurse fa-2x text-success mb-2"></i>
                <h4 class="text-success"><?php echo $staff_stats['nurses_count']; ?></h4>
                <p class="card-text">Nurses</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center border-info">
            <div class="card-body">
                <i class="fas fa-users fa-2x text-info mb-2"></i>
                <h4 class="text-info"><?php echo $staff_stats['total_count']; ?></h4>
                <p class="card-text">Total Staff</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center border-warning">
            <div class="card-body">
                <i class="fas fa-check-circle fa-2x text-warning mb-2"></i>
                <h4 class="text-warning"><?php echo $staff_stats['active_count']; ?></h4>
                <p class="card-text">Active Staff</p>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5><i class="fas fa-users me-2"></i>Staff Members</h5>
    </div>
    <div class="card-body">
        <?php if (empty($staff_members)): ?>
            <div class="text-center py-4">
                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No staff members found</h5>
                <p class="text-muted">Start by adding your first staff member</p>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStaffModal">
                    <i class="fas fa-plus me-1"></i>Add Staff Member
                </button>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Staff Member</th>
                            <th>Role</th>
                            <th>Username</th>
                            <th>Contact Information</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($staff_members as $staff): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="me-3">
                                        <i class="fas fa-<?php echo $staff['role'] == 'doctor' ? 'user-md' : 'user-nurse'; ?> fa-2x text-<?php echo $staff['role'] == 'doctor' ? 'primary' : 'success'; ?>"></i>
                                    </div>
                                    <div>
                                        <strong><?php echo htmlspecialchars($staff['full_name']); ?></strong><br>
                                        <small class="text-muted">ID: <?php echo $staff['user_id']; ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $staff['role'] == 'doctor' ? 'primary' : 'success'; ?>">
                                    <?php echo ucfirst($staff['role']); ?>
                                </span>
                            </td>
                            <td>
                                <code><?php echo htmlspecialchars($staff['username']); ?></code><br>
                                <small class="text-muted">Created: <?php echo $utils->formatDate($staff['created_at']); ?></small>
                            </td>
                            <td>
                                <?php if ($staff['email']): ?>
                                    <div class="mb-1">
                                        <i class="fas fa-envelope me-1"></i>
                                        <a href="mailto:<?php echo htmlspecialchars($staff['email']); ?>"><?php echo htmlspecialchars($staff['email']); ?></a>
                                    </div>
                                <?php endif; ?>
                                <?php if ($staff['phone']): ?>
                                    <div>
                                        <i class="fas fa-phone me-1"></i>
                                        <a href="tel:<?php echo htmlspecialchars($staff['phone']); ?>"><?php echo htmlspecialchars($staff['phone']); ?></a>
                                    </div>
                                <?php endif; ?>
                                <?php if (!$staff['email'] && !$staff['phone']): ?>
                                    <span class="text-muted">No contact info</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $staff['is_active'] ? 'success' : 'danger'; ?>">
                                    <?php echo $staff['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    <button class="btn btn-outline-primary" onclick="editStaff(<?php echo htmlspecialchars(json_encode($staff)); ?>)" title="Edit Staff">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-outline-info" onclick="viewStaffDetails(<?php echo $staff['user_id']; ?>)" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-outline-danger btn-delete" onclick="deleteStaff(<?php echo $staff['user_id']; ?>)" title="Delete Staff">
                                        <i class="fas fa-trash"></i>
                                    </button>
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

<!-- Add Staff Modal -->
<div class="modal fade" id="addStaffModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus me-2"></i>Add New Staff Member
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="full_name" class="form-label">Full Name *</label>
                        <input type="text" class="form-control" id="full_name" name="full_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="role" class="form-label">Role *</label>
                        <select class="form-select" id="role" name="role" required>
                            <option value="">Select Role</option>
                            <option value="doctor">Doctor</option>
                            <option value="nurse">Nurse</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="username" class="form-label">Username *</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                        <div class="form-text">This will be used for login</div>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password *</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email">
                    </div>
                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone Number</label>
                        <input type="tel" class="form-control" id="phone" name="phone" placeholder="+255 XXX XXX XXX">
                        <div class="form-text">Format: +255XXXXXXXXX or 0XXXXXXXXX</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Add Staff Member
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Staff Modal -->
<div class="modal fade" id="editStaffModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="user_id" id="edit_user_id">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>Edit Staff Member
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_full_name" class="form-label">Full Name *</label>
                        <input type="text" class="form-control" id="edit_full_name" name="full_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_role" class="form-label">Role *</label>
                        <select class="form-select" id="edit_role" name="role" required>
                            <option value="doctor">Doctor</option>
                            <option value="nurse">Nurse</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit_username" class="form-label">Username *</label>
                        <input type="text" class="form-control" id="edit_username" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_password" class="form-label">Password (leave blank to keep current)</label>
                        <input type="password" class="form-control" id="edit_password" name="password">
                    </div>
                    <div class="mb-3">
                        <label for="edit_email" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="edit_email" name="email">
                    </div>
                    <div class="mb-3">
                        <label for="edit_phone" class="form-label">Phone Number</label>
                        <input type="tel" class="form-control" id="edit_phone" name="phone">
                        <div class="form-text">Format: +255XXXXXXXXX or 0XXXXXXXXX</div>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="edit_is_active" name="is_active">
                        <label class="form-check-label" for="edit_is_active">Active Account</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Update Staff Member
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editStaff(staff) {
    document.getElementById("edit_user_id").value = staff.user_id;
    document.getElementById("edit_full_name").value = staff.full_name;
    document.getElementById("edit_role").value = staff.role;
    document.getElementById("edit_username").value = staff.username;
    document.getElementById("edit_email").value = staff.email || "";
    document.getElementById("edit_phone").value = staff.phone || "";
    document.getElementById("edit_is_active").checked = staff.is_active == 1;
    
    new bootstrap.Modal(document.getElementById("editStaffModal")).show();
}

function deleteStaff(userId) {
    if (confirm("Are you sure you want to delete this staff member? This action cannot be undone.")) {
        const form = document.createElement("form");
        form.method = "POST";
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="user_id" value="${userId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function viewStaffDetails(userId) {
    // Redirect to staff details page or show modal with detailed info
    alert('Staff details view - Feature coming soon!');
}

// Auto-generate username from full name
document.getElementById("full_name").addEventListener("input", function() {
    const fullName = this.value.toLowerCase().replace(/\s+/g, "");
    const username = fullName.substring(0, 15);
    document.getElementById("username").value = username;
});

// Auto-clear alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            if (alert.classList.contains('show')) {
                alert.classList.remove('show');
            }
        });
    }, 5000);
});
</script>

<?php require_once '../includes/footer.php'; ?>