<?php
$page_title = 'Manage Children';
require_once '../includes/header.php';

// FIX: Update role check to include super_admin
$session->requireRole(['hospital_admin', 'super_admin']);

$database = new Database();
$db = $database->getConnection();

// FIX: Handle hospital_id for both super_admin and hospital_admin
$current_user = $session->getCurrentUser();
$hospital_id = null;

if ($current_user['role'] === 'super_admin') {
    $hospital_id = isset($_GET['hospital_id']) ? (int)$_GET['hospital_id'] : null;
    if (!$hospital_id) {
        header('Location: dashboard.php');
        exit;
    }
    
    // Verify hospital exists
    $hospital_check_query = "SELECT hospital_id FROM hospitals WHERE hospital_id = :hospital_id";
    $hospital_check_stmt = $db->prepare($hospital_check_query);
    $hospital_check_stmt->bindParam(':hospital_id', $hospital_id);
    $hospital_check_stmt->execute();
    
    if (!$hospital_check_stmt->fetch()) {
        header('Location: dashboard.php');
        exit;
    }
} else {
    $hospital_id = $current_user['hospital_id'];
    
    // Check if hospital_id exists for regular admin
    if (!$hospital_id) {
        echo '<div class="container mt-4">';
        echo '<div class="alert alert-danger">';
        echo '<h5><i class="fas fa-exclamation-triangle me-2"></i>No Hospital Assignment</h5>';
        echo '<p>Your account is not assigned to any hospital. Please contact the system administrator.</p>';
        echo '<a href="../login.php" class="btn btn-primary">Back to Login</a>';
        echo '</div></div>';
        require_once '../includes/footer.php';
        exit;
    }
}

$error = '';
$success = '';

// Handle search
$search = isset($_GET['search']) ? $utils->cleanInput($_GET['search']) : '';
$search_condition = '';
$search_params = [];

if (!empty($search)) {
    $search_condition = "AND (c.child_name LIKE :search OR c.registration_number LIKE :search OR c.parent_name LIKE :search OR c.parent_phone LIKE :search)";
    $search_params[':search'] = "%$search%";
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'edit_child') {
        $child_id = $_POST['child_id'] ?? '';
        $child_name = $utils->cleanInput($_POST['child_name'] ?? '');
        $parent_name = $utils->cleanInput($_POST['parent_name'] ?? '');
        $parent_phone = $utils->cleanInput($_POST['parent_phone'] ?? '');
        $parent_email = $utils->cleanInput($_POST['parent_email'] ?? '');
        $address = $utils->cleanInput($_POST['address'] ?? '');
        
        if (empty($child_name) || empty($parent_name) || empty($parent_phone)) {
            $error = 'Please fill in all required fields';
        } elseif (!$utils->validatePhone($parent_phone)) {
            $error = 'Please enter a valid phone number';
        } else {
            $query = "UPDATE children SET child_name = :child_name, parent_name = :parent_name, 
                     parent_phone = :parent_phone, parent_email = :parent_email, address = :address 
                     WHERE child_id = :child_id AND hospital_id = :hospital_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':child_name', $child_name);
            $stmt->bindParam(':parent_name', $parent_name);
            $stmt->bindParam(':parent_phone', $parent_phone);
            $stmt->bindParam(':parent_email', $parent_email);
            $stmt->bindParam(':address', $address);
            $stmt->bindParam(':child_id', $child_id);
            $stmt->bindParam(':hospital_id', $hospital_id);
            
            if ($stmt->execute()) {
                $success = 'Child information updated successfully';
            } else {
                $error = 'Failed to update child information';
            }
        }
    }
}

// Get children with pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Count total children
$count_query = "SELECT COUNT(*) as total FROM children c WHERE c.hospital_id = :hospital_id $search_condition";
$count_stmt = $db->prepare($count_query);
$count_stmt->bindParam(':hospital_id', $hospital_id);
foreach ($search_params as $key => $value) {
    $count_stmt->bindParam($key, $value);
}
$count_stmt->execute();
$total_children = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_children / $per_page);

// Get children with vaccination stats
$children_query = "SELECT c.*, 
                   COUNT(vs.schedule_id) as total_vaccines,
                   SUM(CASE WHEN vs.status = 'completed' THEN 1 ELSE 0 END) as completed_vaccines,
                   SUM(CASE WHEN vs.status = 'pending' AND vs.scheduled_date < CURDATE() THEN 1 ELSE 0 END) as overdue_vaccines,
                   (SELECT cmr.visit_date FROM child_medical_records cmr WHERE cmr.child_id = c.child_id ORDER BY cmr.visit_date DESC LIMIT 1) as last_visit
                   FROM children c
                   LEFT JOIN vaccination_schedule vs ON c.child_id = vs.child_id
                   WHERE c.hospital_id = :hospital_id $search_condition
                   GROUP BY c.child_id
                   ORDER BY c.child_name
                   LIMIT :offset, :per_page";

$children_stmt = $db->prepare($children_query);
$children_stmt->bindParam(':hospital_id', $hospital_id);
$children_stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$children_stmt->bindParam(':per_page', $per_page, PDO::PARAM_INT);
foreach ($search_params as $key => $value) {
    $children_stmt->bindParam($key, $value);
}
$children_stmt->execute();
$children = $children_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get summary statistics
$stats_query = "SELECT 
                COUNT(*) as total_children,
                COUNT(CASE WHEN YEAR(c.date_of_birth) = YEAR(CURDATE()) THEN 1 END) as new_this_year,
                COUNT(CASE WHEN c.gender = 'male' THEN 1 END) as male_children,
                COUNT(CASE WHEN c.gender = 'female' THEN 1 END) as female_children
                FROM children c WHERE c.hospital_id = :hospital_id";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->bindParam(':hospital_id', $hospital_id);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get hospital info for display
$hospital_query = "SELECT hospital_name FROM hospitals WHERE hospital_id = :hospital_id";
$hospital_stmt = $db->prepare($hospital_query);
$hospital_stmt->bindParam(':hospital_id', $hospital_id);
$hospital_stmt->execute();
$hospital_info = $hospital_stmt->fetch(PDO::FETCH_ASSOC);
$hospital_name = $hospital_info['hospital_name'] ?? 'Unknown Hospital';

// Helper function to build URLs with hospital_id for super admin
function buildUrl($base_url, $params = []) {
    global $current_user, $hospital_id;
    if ($current_user['role'] === 'super_admin') {
        $params['hospital_id'] = $hospital_id;
    }
    return $base_url . (!empty($params) ? '?' . http_build_query($params) : '');
}

// Helper function for pagination URLs
function buildPageUrl($page_num, $search = '') {
    global $current_user, $hospital_id;
    $params = ['page' => $page_num];
    if (!empty($search)) {
        $params['search'] = $search;
    }
    if ($current_user['role'] === 'super_admin') {
        $params['hospital_id'] = $hospital_id;
    }
    return 'children.php?' . http_build_query($params);
}
?>

<!-- FIX: Add breadcrumb for super admin -->
<?php if ($current_user['role'] === 'super_admin'): ?>
<nav aria-label="breadcrumb" class="mt-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="dashboard.php">Select Hospital</a></li>
        <li class="breadcrumb-item"><a href="dashboard.php?hospital_id=<?php echo $hospital_id; ?>"><?php echo htmlspecialchars($hospital_name); ?></a></li>
        <li class="breadcrumb-item active" aria-current="page">Manage Children</li>
    </ol>
</nav>
<?php endif; ?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <?php echo $current_user['role'] === 'super_admin' ? 'Children Management - ' . htmlspecialchars($hospital_name) : 'Manage Children'; ?>
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="<?php echo buildUrl('../nurse/register_child.php'); ?>" class="btn btn-primary me-2">
            <i class="fas fa-plus me-1"></i>Register New Child
        </a>
        <button onclick="window.print()" class="btn btn-outline-secondary me-2">
            <i class="fas fa-print me-1"></i>Print List
        </button>
        <?php if ($current_user['role'] === 'super_admin'): ?>
        <a href="dashboard.php?hospital_id=<?php echo $hospital_id; ?>" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
        </a>
        <?php endif; ?>
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
                <i class="fas fa-baby fa-2x text-primary mb-2"></i>
                <h4><?php echo $stats['total_children']; ?></h4>
                <p class="card-text">Total Children</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-calendar fa-2x text-success mb-2"></i>
                <h4><?php echo $stats['new_this_year']; ?></h4>
                <p class="card-text">Registered This Year</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-mars fa-2x text-info mb-2"></i>
                <h4><?php echo $stats['male_children']; ?></h4>
                <p class="card-text">Male</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-venus fa-2x text-warning mb-2"></i>
                <h4><?php echo $stats['female_children']; ?></h4>
                <p class="card-text">Female</p>
            </div>
        </div>
    </div>
</div>

<!-- Search and Filter -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <?php if ($current_user['role'] === 'super_admin'): ?>
                <input type="hidden" name="hospital_id" value="<?php echo $hospital_id; ?>">
            <?php endif; ?>
            <div class="col-md-8">
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Search by child name, registration number, parent name, or phone...">
                </div>
            </div>
            <div class="col-md-4">
                <div class="btn-group w-100" role="group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i>Search
                    </button>
                    <a href="<?php echo buildUrl('children.php'); ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-times me-1"></i>Clear
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Children List -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5><i class="fas fa-list me-2"></i>Children List 
            <?php if (!empty($search)): ?>
                <small class="text-muted">- Search results for "<?php echo htmlspecialchars($search); ?>"</small>
            <?php endif; ?>
        </h5>
        <span class="badge bg-primary"><?php echo $total_children; ?> children</span>
    </div>
    <div class="card-body">
        <?php if (empty($children)): ?>
            <div class="text-center py-4">
                <i class="fas fa-baby fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No children found</h5>
                <?php if (!empty($search)): ?>
                    <p>Try adjusting your search criteria</p>
                    <a href="<?php echo buildUrl('children.php'); ?>" class="btn btn-outline-primary">View All Children</a>
                <?php else: ?>
                    <p>Start by registering your first child</p>
                    <a href="<?php echo buildUrl('../nurse/register_child.php'); ?>" class="btn btn-primary">Register Child</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Child Information</th>
                            <th>Parent/Guardian</th>
                            <th>Age</th>
                            <th>Vaccination Status</th>
                            <th>Last Visit</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($children as $child): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="me-3">
                                        <i class="fas fa-<?php echo $child['gender'] == 'male' ? 'mars' : 'venus'; ?> fa-2x text-<?php echo $child['gender'] == 'male' ? 'info' : 'warning'; ?>"></i>
                                    </div>
                                    <div>
                                        <strong><?php echo htmlspecialchars($child['child_name']); ?></strong><br>
                                        <small class="text-muted">
                                            <i class="fas fa-id-card me-1"></i><?php echo htmlspecialchars($child['registration_number']); ?><br>
                                            <i class="fas fa-birthday-cake me-1"></i><?php echo $utils->formatDate($child['date_of_birth']); ?>
                                        </small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($child['parent_name']); ?></strong><br>
                                <small class="text-muted">
                                    <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($child['parent_phone']); ?>
                                    <?php if ($child['parent_email']): ?>
                                        <br><i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($child['parent_email']); ?>
                                    <?php endif; ?>
                                </small>
                            </td>
                            <td>
                                <span class="badge bg-info"><?php echo $utils->getChildAgeInMonths($child['date_of_birth']); ?> months</span>
                            </td>
                            <td>
                                <div class="progress mb-1" style="height: 10px;">
                                    <?php 
                                    $completion = $child['total_vaccines'] > 0 ? ($child['completed_vaccines'] / $child['total_vaccines']) * 100 : 0;
                                    ?>
                                    <div class="progress-bar bg-success" style="width: <?php echo $completion; ?>%"></div>
                                </div>
                                <small>
                                    <?php echo $child['completed_vaccines']; ?>/<?php echo $child['total_vaccines']; ?> completed
                                    <?php if ($child['overdue_vaccines'] > 0): ?>
                                        <span class="badge bg-danger ms-1"><?php echo $child['overdue_vaccines']; ?> overdue</span>
                                    <?php endif; ?>
                                </small>
                            </td>
                            <td>
                                <?php if ($child['last_visit']): ?>
                                    <small><?php echo $utils->formatDate($child['last_visit']); ?></small>
                                <?php else: ?>
                                    <small class="text-muted">No visits</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    <button class="btn btn-outline-primary" onclick="viewChild(<?php echo $child['child_id']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-outline-success" onclick="editChild(<?php echo htmlspecialchars(json_encode($child)); ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="<?php echo buildUrl('../nurse/vaccination.php', ['child_id' => $child['child_id']]); ?>" class="btn btn-outline-info">
                                        <i class="fas fa-syringe"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <nav aria-label="Children pagination">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo buildPageUrl($page - 1, $search); ?>">Previous</a>
                    </li>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                        <a class="page-link" href="<?php echo buildPageUrl($i, $search); ?>"><?php echo $i; ?></a>
                    </li>
                    <?php endfor; ?>
                    
                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo buildPageUrl($page + 1, $search); ?>">Next</a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Edit Child Modal -->
<div class="modal fade" id="editChildModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="edit_child">
                <input type="hidden" name="child_id" id="edit_child_id">
                <?php if ($current_user['role'] === 'super_admin'): ?>
                    <input type="hidden" name="hospital_id" value="<?php echo $hospital_id; ?>">
                <?php endif; ?>
                <div class="modal-header">
                    <h5 class="modal-title">Edit Child Information</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_child_name" class="form-label">Child Name *</label>
                                <input type="text" class="form-control" id="edit_child_name" name="child_name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_parent_name" class="form-label">Parent/Guardian Name *</label>
                                <input type="text" class="form-control" id="edit_parent_name" name="parent_name" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_parent_phone" class="form-label">Phone Number *</label>
                                <input type="tel" class="form-control" id="edit_parent_phone" name="parent_phone" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_parent_email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="edit_parent_email" name="parent_email">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_address" class="form-label">Address</label>
                        <textarea class="form-control" id="edit_address" name="address" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Information</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Child Modal -->
<div class="modal fade" id="viewChildModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Child Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="childDetails">
                <!-- Content will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="window.print()">Print</button>
            </div>
        </div>
    </div>
</div>

<?php
$extra_scripts = '
<script>
function editChild(child) {
    document.getElementById("edit_child_id").value = child.child_id;
    document.getElementById("edit_child_name").value = child.child_name;
    document.getElementById("edit_parent_name").value = child.parent_name;
    document.getElementById("edit_parent_phone").value = child.parent_phone;
    document.getElementById("edit_parent_email").value = child.parent_email || "";
    document.getElementById("edit_address").value = child.address || "";
    
    new bootstrap.Modal(document.getElementById("editChildModal")).show();
}

function viewChild(childId) {
    const hospitalParam = ' . ($current_user['role'] === 'super_admin' ? '"&hospital_id=' . $hospital_id . '"' : '""') . ';
    // Load child details via AJAX
    fetch(`../api/get_child_details.php?child_id=${childId}${hospitalParam}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById("childDetails").innerHTML = data.html;
                new bootstrap.Modal(document.getElementById("viewChildModal")).show();
            } else {
                alert("Error loading child details");
            }
        })
        .catch(error => {
            console.error("Error:", error);
            alert("Error loading child details");
        });
}

// Auto search on typing (debounced)
let searchTimeout;
document.querySelector("input[name=search]").addEventListener("input", function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        if (this.value.length >= 3 || this.value.length === 0) {
            this.form.submit();
        }
    }, 500);
});
</script>';

require_once '../includes/footer.php';
?>