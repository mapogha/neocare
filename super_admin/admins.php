<?php
$page_title = 'Manage Hospital Admins';
require_once '../includes/header.php';

$session->requireRole('super_admin');

$database = new Database();
$db = $database->getConnection();

// Initialize utils if not already available
if (!isset($utils)) {
    require_once '../includes/functions.php';
    if (!class_exists('Utils')) {
        // Define a minimal Utils class if not already defined
        class Utils {
            public function cleanInput($input) {
                return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
            }
        }
    }
    $utils = new Utils();
}

$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'add') {
        $hospital_id = $_POST['hospital_id'] ?? '';
        $username = $utils->cleanInput($_POST['username'] ?? '');
        $password = $utils->cleanInput($_POST['password'] ?? '');
        $full_name = $utils->cleanInput($_POST['full_name'] ?? '');
        $email = $utils->cleanInput($_POST['email'] ?? '');
        $phone = $utils->cleanInput($_POST['phone'] ?? '');
        
        if (empty($hospital_id) || empty($username) || empty($password) || empty($full_name)) {
            $error = 'Please fill in all required fields';
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
                         VALUES (:hospital_id, :username, :password, :full_name, :email, :phone, 'admin')";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':hospital_id', $hospital_id);
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':password', $password);
                $stmt->bindParam(':full_name', $full_name);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':phone', $phone);
                
                if ($stmt->execute()) {
                    $success = 'Hospital admin added successfully';
                } else {
                    $error = 'Failed to add hospital admin';
                }
            }
        }
    } elseif ($action == 'edit') {
        $user_id = $_POST['user_id'] ?? '';
        $hospital_id = $_POST['hospital_id'] ?? '';
        $username = $utils->cleanInput($_POST['username'] ?? '');
        $password = $utils->cleanInput($_POST['password'] ?? '');
        $full_name = $utils->cleanInput($_POST['full_name'] ?? '');
        $email = $utils->cleanInput($_POST['email'] ?? '');
        $phone = $utils->cleanInput($_POST['phone'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if (empty($hospital_id) || empty($username) || empty($full_name)) {
            $error = 'Please fill in all required fields';
        } else {
            $query = "UPDATE users SET hospital_id = :hospital_id, username = :username, 
                     full_name = :full_name, email = :email, phone = :phone, is_active = :is_active";
            
            if (!empty($password)) {
                $query .= ", password = :password";
            }
            
            $query .= " WHERE user_id = :user_id AND role = 'admin'";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':hospital_id', $hospital_id);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':full_name', $full_name);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':phone', $phone);
            $stmt->bindParam(':is_active', $is_active);
            $stmt->bindParam(':user_id', $user_id);
            
            if (!empty($password)) {
                $stmt->bindParam(':password', $password);
            }
            
            if ($stmt->execute()) {
                $success = 'Hospital admin updated successfully';
            } else {
                $error = 'Failed to update hospital admin';
            }
        }
    } elseif ($action == 'delete') {
        $user_id = $_POST['user_id'] ?? '';
        
        // Check if admin has any children registered under them
        $check_query = "SELECT COUNT(*) as count FROM children WHERE hospital_id = (SELECT hospital_id FROM users WHERE user_id = :user_id)";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':user_id', $user_id);
        $check_stmt->execute();
        $children_count = $check_stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($children_count > 0) {
            $error = 'Cannot delete admin. Hospital has registered children.';
        } else {
            $query = "DELETE FROM users WHERE user_id = :user_id AND role = 'admin'";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            
            if ($stmt->execute()) {
                $success = 'Hospital admin deleted successfully';
            } else {
                $error = 'Failed to delete hospital admin';
            }
        }
    }
}

// Get all hospitals - using dynamic column detection
$hospital_columns = [];
try {
    $check_query = "DESCRIBE hospitals";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->execute();
    $columns = $check_stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $column) {
        $hospital_columns[] = $column['Field'];
    }
} catch (Exception $e) {
    $hospital_columns = ['hospital_id', 'hospital_name'];
}

$name_column = 'hospital_id';
if (in_array('hospital_name', $hospital_columns)) {
    $name_column = 'hospital_name';
} elseif (in_array('name', $hospital_columns)) {
    $name_column = 'name';
}

$hospitals_query = "SELECT hospital_id, $name_column as hospital_name FROM hospitals ORDER BY $name_column";
$hospitals_stmt = $db->prepare($hospitals_query);
$hospitals_stmt->execute();
$hospitals = $hospitals_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all hospital admins with their hospital and statistics
$admins_query = "SELECT u.*, h.$name_column as hospital_name,
                  COUNT(DISTINCT c.child_id) as total_children,
                  COUNT(DISTINCT staff.user_id) as total_staff,
                  COUNT(DISTINCT CASE WHEN vs.status = 'completed' THEN vs.schedule_id END) as total_vaccinations
                  FROM users u
                  JOIN hospitals h ON u.hospital_id = h.hospital_id
                  LEFT JOIN children c ON h.hospital_id = c.hospital_id
                  LEFT JOIN users staff ON h.hospital_id = staff.hospital_id AND staff.role IN ('doctor', 'nurse')
                  LEFT JOIN vaccination_schedule vs ON c.child_id = vs.child_id
                  WHERE u.role = 'admin'
                  GROUP BY u.user_id
                  ORDER BY h.$name_column, u.full_name";

$admins_stmt = $db->prepare($admins_query);
$admins_stmt->execute();
$admins = $admins_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get summary statistics
$stats_query = "SELECT 
                COUNT(DISTINCT u.user_id) as total_admins,
                COUNT(DISTINCT CASE WHEN u.is_active = 1 THEN u.user_id END) as active_admins,
                COUNT(DISTINCT h.hospital_id) as total_hospitals,
                COUNT(DISTINCT c.child_id) as total_children
                FROM users u
                LEFT JOIN hospitals h ON u.hospital_id = h.hospital_id
                LEFT JOIN children c ON h.hospital_id = c.hospital_id
                WHERE u.role = 'admin'";

$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Helper function for date formatting
function formatDate($date) {
    if (!$date) return 'N/A';
    return date('M j, Y', strtotime($date));
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Manage Hospital Admins</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAdminModal">
            <i class="fas fa-plus me-1"></i>Add Hospital Admin
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

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-users-cog fa-2x text-primary mb-2"></i>
                <h4><?php echo $stats['total_admins']; ?></h4>
                <p class="card-text">Total Admins</p>
                <small class="text-muted"><?php echo $stats['active_admins']; ?> active</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-hospital fa-2x text-success mb-2"></i>
                <h4><?php echo $stats['total_hospitals']; ?></h4>
                <p class="card-text">Hospitals</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-baby fa-2x text-info mb-2"></i>
                <h4><?php echo $stats['total_children']; ?></h4>
                <p class="card-text">Total Children</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-chart-line fa-2x text-warning mb-2"></i>
                <h4><?php echo number_format($stats['total_children'] / max(1, $stats['total_hospitals']), 1); ?></h4>
                <p class="card-text">Avg Children/Hospital</p>
            </div>
        </div>
    </div>
</div>

<!-- Hospital Admins List -->
<div class="card">
    <div class="card-header">
        <h5><i class="fas fa-users-cog me-2"></i>Hospital Administrators</h5>
    </div>
    <div class="card-body">
        <?php if (empty($admins)): ?>
            <div class="text-center py-4">
                <i class="fas fa-users-cog fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No hospital admins found</h5>
                <p>Start by adding your first hospital administrator</p>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAdminModal">
                    Add Hospital Admin
                </button>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Administrator</th>
                            <th>Hospital</th>
                            <th>Contact Information</th>
                            <th>Hospital Statistics</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($admins as $admin): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="me-3">
                                        <i class="fas fa-user-tie fa-2x text-primary"></i>
                                    </div>
                                    <div>
                                        <strong><?php echo htmlspecialchars($admin['full_name']); ?></strong><br>
                                        <small class="text-muted">
                                            <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($admin['username']); ?>
                                        </small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($admin['hospital_name']); ?></strong><br>
                                <small class="text-muted">ID: <?php echo $admin['hospital_id']; ?></small>
                            </td>
                            <td>
                                <?php if ($admin['email']): ?>
                                    <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($admin['email']); ?><br>
                                <?php endif; ?>
                                <?php if ($admin['phone']): ?>
                                    <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($admin['phone']); ?>
                                <?php endif; ?>
                                <?php if (!$admin['email'] && !$admin['phone']): ?>
                                    <span class="text-muted">No contact info</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="row text-center">
                                    <div class="col-4">
                                        <small class="text-muted">Children</small>
                                        <div><strong><?php echo $admin['total_children']; ?></strong></div>
                                    </div>
                                    <div class="col-4">
                                        <small class="text-muted">Staff</small>
                                        <div><strong><?php echo $admin['total_staff']; ?></strong></div>
                                    </div>
                                    <div class="col-4">
                                        <small class="text-muted">Vaccines</small>
                                        <div><strong><?php echo $admin['total_vaccinations']; ?></strong></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $admin['is_active'] ? 'success' : 'danger'; ?>">
                                    <?php echo $admin['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span><br>
                                <small class="text-muted">
                                    Joined: <?php echo formatDate($admin['created_at']); ?>
                                </small>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    <button class="btn btn-outline-primary" onclick="editAdmin(<?php echo htmlspecialchars(json_encode($admin)); ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-outline-info" onclick="viewAdminDetails(<?php echo $admin['user_id']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-outline-danger btn-delete" onclick="deleteAdmin(<?php echo $admin['user_id']; ?>)">
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

<!-- Add Admin Modal -->
<div class="modal fade" id="addAdminModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title">Add Hospital Administrator</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="hospital_id" class="form-label">Hospital *</label>
                        <select class="form-select" id="hospital_id" name="hospital_id" required>
                            <option value="">Select Hospital</option>
                            <?php foreach ($hospitals as $hospital): ?>
                                <option value="<?php echo $hospital['hospital_id']; ?>">
                                    <?php echo htmlspecialchars($hospital['hospital_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="full_name" class="form-label">Full Name *</label>
                        <input type="text" class="form-control" id="full_name" name="full_name" required>
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
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email">
                    </div>
                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone Number</label>
                        <input type="tel" class="form-control" id="phone" name="phone">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Administrator</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Admin Modal -->
<div class="modal fade" id="editAdminModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="user_id" id="edit_user_id">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Hospital Administrator</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_hospital_id" class="form-label">Hospital *</label>
                        <select class="form-select" id="edit_hospital_id" name="hospital_id" required>
                            <?php foreach ($hospitals as $hospital): ?>
                                <option value="<?php echo $hospital['hospital_id']; ?>">
                                    <?php echo htmlspecialchars($hospital['hospital_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit_full_name" class="form-label">Full Name *</label>
                        <input type="text" class="form-control" id="edit_full_name" name="full_name" required>
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
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="edit_is_active" name="is_active">
                        <label class="form-check-label" for="edit_is_active">Active Account</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Administrator</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Admin Details Modal -->
<div class="modal fade" id="adminDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Administrator Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="adminDetailsContent">
                <!-- Content will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="window.print()">Print</button>
            </div>
        </div>
    </div>
</div>

<script>
function editAdmin(admin) {
    document.getElementById("edit_user_id").value = admin.user_id;
    document.getElementById("edit_hospital_id").value = admin.hospital_id;
    document.getElementById("edit_full_name").value = admin.full_name;
    document.getElementById("edit_username").value = admin.username;
    document.getElementById("edit_email").value = admin.email || "";
    document.getElementById("edit_phone").value = admin.phone || "";
    document.getElementById("edit_is_active").checked = admin.is_active == 1;
    
    new bootstrap.Modal(document.getElementById("editAdminModal")).show();
}

function deleteAdmin(userId) {
    if (confirm("Are you sure you want to delete this administrator? This action cannot be undone.")) {
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

function viewAdminDetails(userId) {
    // Show loading
    document.getElementById("adminDetailsContent").innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading administrator details...</p>
        </div>
    `;
    
    new bootstrap.Modal(document.getElementById("adminDetailsModal")).show();
    
    // Simple fallback since API might not exist
    setTimeout(() => {
        document.getElementById("adminDetailsContent").innerHTML = `
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                Administrator details feature is under development.
            </div>
        `;
    }, 1000);
}

// Auto-generate username from full name
document.getElementById("full_name").addEventListener("input", function() {
    const fullName = this.value.toLowerCase().replace(/\s+/g, "");
    const username = fullName.substring(0, 15);
    document.getElementById("username").value = username;
});
</script>

<?php require_once '../includes/footer.php'; ?>