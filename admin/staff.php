<?php
$page_title = 'Manage Staff';
require_once '../includes/header.php';

$session->requireRole('hospital_admin');

$database = new Database();
$db = $database->getConnection();
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
            // Check if username exists
            $check_query = "SELECT COUNT(*) as count FROM users WHERE username = :username";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(':username', $username);
            $check_stmt->execute();
            $exists = $check_stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($exists > 0) {
                $error = 'Username already exists';
            } else {
                $query = "INSERT INTO users (hospital_id, username, password, full_name, email, phone, role) 
                         VALUES (:hospital_id, :username, :password, :full_name, :email, :phone, :role)";
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
                    $error = 'Failed to add staff member';
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
    } elseif ($action == 'delete') {
        $user_id = $_POST['user_id'] ?? '';
        
        $query = "DELETE FROM users WHERE user_id = :user_id AND hospital_id = :hospital_id";
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

// Get all staff members for this hospital
$query = "SELECT * FROM users WHERE hospital_id = :hospital_id AND role IN ('doctor', 'nurse') ORDER BY role, full_name";
$stmt = $db->prepare($query);
$stmt->bindParam(':hospital_id', $hospital_id);
$stmt->execute();
$staff_members = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Manage Staff</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStaffModal">
            <i class="fas fa-plus me-1"></i>Add Staff Member
        </button>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
    </div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
    </div>
<?php endif; ?>

<div class="row mb-4">
    <div class="col-md-6">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-user-md fa-2x text-primary mb-2"></i>
                <h4><?php echo count(array_filter($staff_members, function($s) { return $s['role'] == 'doctor'; })); ?></h4>
                <p class="card-text">Doctors</p>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-user-nurse fa-2x text-success mb-2"></i>
                <h4><?php echo count(array_filter($staff_members, function($s) { return $s['role'] == 'nurse'; })); ?></h4>
                <p class="card-text">Nurses</p>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5><i class="fas fa-users me-2"></i>Staff Members</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Role</th>
                        <th>Username</th>
                        <th>Contact</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($staff_members as $staff): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($staff['full_name']); ?></strong>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo $staff['role'] == 'doctor' ? 'primary' : 'success'; ?>">
                                <?php echo ucfirst($staff['role']); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($staff['username']); ?></td>
                        <td>
                            <?php if ($staff['phone']): ?>
                                <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($staff['phone']); ?><br>
                            <?php endif; ?>
                            <?php if ($staff['email']): ?>
                                <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($staff['email']); ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo $staff['is_active'] ? 'success' : 'danger'; ?>">
                                <?php echo $staff['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="editStaff(<?php echo htmlspecialchars(json_encode($staff)); ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger btn-delete" onclick="deleteStaff(<?php echo $staff['user_id']; ?>)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Staff Modal -->
<div class="modal fade" id="addStaffModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Staff Member</h5>
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
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password *</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email">
                    </div>
                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone</label>
                        <input type="tel" class="form-control" id="phone" name="phone">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Staff Member</button>
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
                    <h5 class="modal-title">Edit Staff Member</h5>
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
                        <label for="edit_email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="edit_email" name="email">
                    </div>
                    <div class="mb-3">
                        <label for="edit_phone" class="form-label">Phone</label>
                        <input type="tel" class="form-control" id="edit_phone" name="phone">
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="edit_is_active" name="is_active">
                        <label class="form-check-label" for="edit_is_active">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Staff Member</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$extra_scripts = '
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
</script>';

require_once '../includes/footer.php';
?>
