<?php
$page_title = 'Manage Hospital Admins';
require_once '../includes/header.php';

$session->requireRole('super_admin');

$database = new Database();
$db = $database->getConnection();

// Initialize NeoCareUtils
require_once '../includes/functions.php';

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
                    // FIXED: Changed 'admin' to 'hospital_admin'
                    $query = "INSERT INTO users (hospital_id, username, password, full_name, email, phone, role, is_active, created_at) 
                             VALUES (:hospital_id, :username, :password, :full_name, :email, :phone, 'hospital_admin', 1, NOW())";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':hospital_id', $hospital_id);
                    $stmt->bindParam(':username', $username);
                    $stmt->bindParam(':password', $password);
                    $stmt->bindParam(':full_name', $full_name);
                    $stmt->bindParam(':email', $email);
                    $stmt->bindParam(':phone', $phone);
                    
                    if ($stmt->execute()) {
                        $success = 'Hospital admin added successfully! User ID: ' . $db->lastInsertId();
                    } else {
                        $error = 'Failed to add hospital admin: ' . implode(', ', $stmt->errorInfo());
                    }
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
            // Validate email if provided
            if (!empty($email) && !$utils->validateEmail($email)) {
                $error = 'Please enter a valid email address';
            }
            // Validate phone if provided
            elseif (!empty($phone) && !$utils->validatePhone($phone)) {
                $error = 'Please enter a valid phone number (Tanzanian format)';
            } else {
                $query = "UPDATE users SET hospital_id = :hospital_id, username = :username, 
                         full_name = :full_name, email = :email, phone = :phone, is_active = :is_active";
                
                if (!empty($password)) {
                    $query .= ", password = :password";
                }
                
                // FIXED: Changed 'admin' to 'hospital_admin'
                $query .= " WHERE user_id = :user_id AND role = 'hospital_admin'";
                
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
            // FIXED: Changed 'admin' to 'hospital_admin'
            $query = "DELETE FROM users WHERE user_id = :user_id AND role = 'hospital_admin'";
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

// FIXED: Debug check for hospital_admin users before complex query
$simple_admin_check = "SELECT COUNT(*) as count FROM users WHERE role = 'hospital_admin'";
$simple_stmt = $db->prepare($simple_admin_check);
$simple_stmt->execute();
$admin_count = $simple_stmt->fetch(PDO::FETCH_ASSOC)['count'];

// FIXED: Get all hospital admins with their hospital and statistics
$admins_query = "SELECT u.*, h.$name_column as hospital_name,
                  COUNT(DISTINCT c.child_id) as total_children,
                  COUNT(DISTINCT staff.user_id) as total_staff,
                  COUNT(DISTINCT CASE WHEN vs.status = 'completed' THEN vs.schedule_id END) as total_vaccinations
                  FROM users u
                  JOIN hospitals h ON u.hospital_id = h.hospital_id
                  LEFT JOIN children c ON h.hospital_id = c.hospital_id
                  LEFT JOIN users staff ON h.hospital_id = staff.hospital_id AND staff.role IN ('doctor', 'nurse')
                  LEFT JOIN vaccination_schedule vs ON c.child_id = vs.child_id
                  WHERE u.role = 'hospital_admin'
                  GROUP BY u.user_id
                  ORDER BY h.$name_column, u.full_name";

$admins_stmt = $db->prepare($admins_query);
$admins_stmt->execute();
$admins = $admins_stmt->fetchAll(PDO::FETCH_ASSOC);

// FIXED: Get summary statistics
$stats_query = "SELECT 
                COUNT(DISTINCT u.user_id) as total_admins,
                COUNT(DISTINCT CASE WHEN u.is_active = 1 THEN u.user_id END) as active_admins,
                COUNT(DISTINCT h.hospital_id) as total_hospitals,
                COUNT(DISTINCT c.child_id) as total_children
                FROM users u
                LEFT JOIN hospitals h ON u.hospital_id = h.hospital_id
                LEFT JOIN children c ON h.hospital_id = c.hospital_id
                WHERE u.role = 'hospital_admin'";

$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="fas fa-users-cog me-2 text-primary"></i>Manage Hospital Admins</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAdminModal">
            <i class="fas fa-plus me-1"></i>Add Hospital Admin
        </button>
        <a href="debug_admins.php" class="btn btn-info ms-2" target="_blank">
            <i class="fas fa-bug me-1"></i>Debug
        </a>
    </div>
</div>

<!-- Debug Info -->
<div class="alert alert-info">
    <strong>Debug Info:</strong> 
    Found <?php echo $admin_count; ?> users with role 'hospital_admin' in database. 
    Hospital column: <?php echo $name_column; ?>. 
    Complex query returned <?php echo count($admins); ?> results.
    <?php if (empty($hospitals)): ?>
        <span class="text-danger">WARNING: No hospitals found! You must add hospitals first.</span>
    <?php endif; ?>
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

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-center border-primary">
            <div class="card-body">
                <i class="fas fa-users-cog fa-2x text-primary mb-2"></i>
                <h4><?php echo $stats['total_admins']; ?></h4>
                <p class="card-text">Total Admins</p>
                <small class="text-muted"><?php echo $stats['active_admins']; ?> active</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center border-success">
            <div class="card-body">
                <i class="fas fa-hospital fa-2x text-success mb-2"></i>
                <h4><?php echo $stats['total_hospitals']; ?></h4>
                <p class="card-text">Hospitals</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center border-info">
            <div class="card-body">
                <i class="fas fa-baby fa-2x text-info mb-2"></i>
                <h4><?php echo $stats['total_children']; ?></h4>
                <p class="card-text">Total Children</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center border-warning">
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
        <?php if (empty($hospitals)): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>No hospitals found!</strong> You need to add hospitals first before creating admins.
                <a href="hospitals.php" class="btn btn-primary btn-sm ms-2">
                    <i class="fas fa-plus me-1"></i>Add Hospital
                </a>
            </div>
        <?php elseif (empty($admins)): ?>
            <div class="text-center py-4">
                <i class="fas fa-users-cog fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No hospital admins found</h5>
                <?php if ($admin_count > 0): ?>
                    <div class="alert alert-warning">
                        Found <?php echo $admin_count; ?> hospital_admin users in database, but they're not showing due to missing hospital association.
                        <a href="debug_admins.php" target="_blank" class="btn btn-info btn-sm">Debug Issue</a>
                    </div>
                <?php else: ?>
                    <p>Start by adding your first hospital administrator</p>
                <?php endif; ?>
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
                                    <div class="mb-1">
                                        <i class="fas fa-envelope me-1"></i>
                                        <a href="mailto:<?php echo htmlspecialchars($admin['email']); ?>"><?php echo htmlspecialchars($admin['email']); ?></a>
                                    </div>
                                <?php endif; ?>
                                <?php if ($admin['phone']): ?>
                                    <div>
                                        <i class="fas fa-phone me-1"></i>
                                        <a href="tel:<?php echo htmlspecialchars($admin['phone']); ?>"><?php echo htmlspecialchars($admin['phone']); ?></a>
                                    </div>
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
                                    Joined: <?php echo $utils->formatDate($admin['created_at']); ?>
                                </small>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    <button class="btn btn-outline-primary" onclick="editAdmin(<?php echo htmlspecialchars(json_encode($admin)); ?>)" title="Edit Admin">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-outline-info" onclick="viewAdminDetails(<?php echo $admin['user_id']; ?>)" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <!-- <a href="../admin/dashboard.php?hospital_id=<?php echo $admin['hospital_id']; ?>" class="btn btn-outline-success btn-sm" title="View Hospital Dashboard">
                                        <i class="fas fa-chart-bar"></i>
                                    </a> -->
                                    <button class="btn btn-outline-danger btn-delete" onclick="deleteAdmin(<?php echo $admin['user_id']; ?>)" title="Delete Admin">
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
                    <h5 class="modal-title">
                        <i class="fas fa-plus me-2"></i>Add Hospital Administrator
                    </h5>
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
                        <?php if (empty($hospitals)): ?>
                            <div class="form-text text-danger">No hospitals available! Please add a hospital first.</div>
                        <?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label for="full_name" class="form-label">Full Name *</label>
                        <input type="text" class="form-control" id="full_name" name="full_name" required>
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
                    <button type="submit" class="btn btn-primary" <?php echo empty($hospitals) ? 'disabled' : ''; ?>>
                        <i class="fas fa-save me-1"></i>Add Administrator
                    </button>
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
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>Edit Hospital Administrator
                    </h5>
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
                        <i class="fas fa-save me-1"></i>Update Administrator
                    </button>
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
                <h5 class="modal-title">
                    <i class="fas fa-user-tie me-2"></i>Administrator Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="adminDetailsContent">
                <!-- Content will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="window.print()">
                    <i class="fas fa-print me-1"></i>Print
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function viewAdminDetails(userId) {
    // Find the admin data from the existing admins array
    const adminData = <?php echo json_encode($admins); ?>;
    const admin = adminData.find(a => a.user_id == userId);
    
    if (!admin) {
        document.getElementById("adminDetailsContent").innerHTML = `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Administrator not found.
            </div>
        `;
        new bootstrap.Modal(document.getElementById("adminDetailsModal")).show();
        return;
    }
    
    // Show loading first
    document.getElementById("adminDetailsContent").innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading administrator details...</p>
        </div>
    `;
    
    new bootstrap.Modal(document.getElementById("adminDetailsModal")).show();
    
    // Show actual admin details after brief delay
    setTimeout(() => {
        const statusBadge = admin.is_active == 1 ? 
            '<span class="badge bg-success">Active</span>' : 
            '<span class="badge bg-danger">Inactive</span>';
            
        const contactInfo = [];
        if (admin.email) contactInfo.push(`<i class="fas fa-envelope me-1"></i> ${admin.email}`);
        if (admin.phone) contactInfo.push(`<i class="fas fa-phone me-1"></i> ${admin.phone}`);
        
        document.getElementById("adminDetailsContent").innerHTML = `
            <div class="row">
                <div class="col-md-4">
                    <div class="text-center mb-4">
                        <i class="fas fa-user-tie fa-4x text-primary mb-3"></i>
                        <h5>${admin.full_name}</h5>
                        <p class="text-muted">@${admin.username}</p>
                        ${statusBadge}
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="row">
                        <div class="col-12 mb-4">
                            <h6><i class="fas fa-hospital me-2"></i>Hospital Information</h6>
                            <div class="card">
                                <div class="card-body">
                                    <h6 class="card-title">${admin.hospital_name}</h6>
                                    <small class="text-muted">Hospital ID: ${admin.hospital_id}</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-12 mb-4">
                            <h6><i class="fas fa-address-card me-2"></i>Contact Information</h6>
                            <div class="card">
                                <div class="card-body">
                                    ${contactInfo.length > 0 ? contactInfo.join('<br>') : '<span class="text-muted">No contact information available</span>'}
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-12 mb-4">
                            <h6><i class="fas fa-chart-bar me-2"></i>Hospital Statistics</h6>
                            <div class="row">
                                <div class="col-4">
                                    <div class="card text-center">
                                        <div class="card-body">
                                            <i class="fas fa-baby text-info mb-2"></i>
                                            <h5>${admin.total_children}</h5>
                                            <small>Children</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="card text-center">
                                        <div class="card-body">
                                            <i class="fas fa-user-md text-success mb-2"></i>
                                            <h5>${admin.total_staff}</h5>
                                            <small>Staff</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="card text-center">
                                        <div class="card-body">
                                            <i class="fas fa-syringe text-warning mb-2"></i>
                                            <h5>${admin.total_vaccinations}</h5>
                                            <small>Vaccinations</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <h6><i class="fas fa-info-circle me-2"></i>Account Details</h6>
                            <div class="card">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-6">
                                            <strong>User ID:</strong> ${admin.user_id}<br>
                                            <strong>Role:</strong> Hospital Administrator<br>
                                            <strong>Status:</strong> ${admin.is_active == 1 ? 'Active' : 'Inactive'}
                                        </div>
                                        <div class="col-6">
                                            <strong>Created:</strong> ${admin.created_at ? new Date(admin.created_at).toLocaleDateString() : 'N/A'}<br>
                                            <strong>Last Updated:</strong> ${admin.updated_at ? new Date(admin.updated_at).toLocaleDateString() : 'N/A'}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row mt-4">
                <div class="col-12">
                    <h6><i class="fas fa-tools me-2"></i>Quick Actions</h6>
                    <div class="d-flex gap-2 flex-wrap">
                        <button class="btn btn-outline-primary btn-sm" onclick="editAdmin(${JSON.stringify(admin).replace(/"/g, '&quot;')})">
                            <i class="fas fa-edit me-1"></i>Edit Admin
                        </button>
                
                        <a href="hospitals.php" class="btn btn-outline-success btn-sm">
                            <i class="fas fa-hospital me-1"></i>Manage Hospitals
                        </a>
    
                    </div>
                </div>
            </div>
        `;
    }, 500);
}

// Add function to allow super admin to login as hospital admin (impersonation)
function loginAsAdmin(userId) {
    if (confirm('Login as this administrator? You will be switched to their account view.')) {
        // You can implement admin impersonation here
        window.location.href = `../admin/dashboard.php?impersonate=${userId}`;
    }
}

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