<?php
$page_title = 'Manage Hospitals';
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
        $hospital_name = $utils->cleanInput($_POST['hospital_name'] ?? '');
        $address = $utils->cleanInput($_POST['address'] ?? '');
        $phone = $utils->cleanInput($_POST['phone'] ?? '');
        $email = $utils->cleanInput($_POST['email'] ?? '');
        
        if (empty($hospital_name)) {
            $error = 'Hospital name is required';
        } else {
            // Validate email if provided
            if (!empty($email) && !$utils->validateEmail($email)) {
                $error = 'Please enter a valid email address';
            }
            // Validate phone if provided
            elseif (!empty($phone) && !$utils->validatePhone($phone)) {
                $error = 'Please enter a valid phone number (Tanzanian format)';
            } else {
                $query = "INSERT INTO hospitals (hospital_name, address, phone, email) VALUES (:name, :address, :phone, :email)";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':name', $hospital_name);
                $stmt->bindParam(':address', $address);
                $stmt->bindParam(':phone', $phone);
                $stmt->bindParam(':email', $email);
                
                if ($stmt->execute()) {
                    $success = 'Hospital added successfully';
                } else {
                    $error = 'Failed to add hospital';
                }
            }
        }
    } elseif ($action == 'edit') {
        $hospital_id = $_POST['hospital_id'] ?? '';
        $hospital_name = $utils->cleanInput($_POST['hospital_name'] ?? '');
        $address = $utils->cleanInput($_POST['address'] ?? '');
        $phone = $utils->cleanInput($_POST['phone'] ?? '');
        $email = $utils->cleanInput($_POST['email'] ?? '');
        
        if (empty($hospital_name)) {
            $error = 'Hospital name is required';
        } else {
            // Validate email if provided
            if (!empty($email) && !$utils->validateEmail($email)) {
                $error = 'Please enter a valid email address';
            }
            // Validate phone if provided
            elseif (!empty($phone) && !$utils->validatePhone($phone)) {
                $error = 'Please enter a valid phone number (Tanzanian format)';
            } else {
                $query = "UPDATE hospitals SET hospital_name = :name, address = :address, phone = :phone, email = :email WHERE hospital_id = :id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':name', $hospital_name);
                $stmt->bindParam(':address', $address);
                $stmt->bindParam(':phone', $phone);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':id', $hospital_id);
                
                if ($stmt->execute()) {
                    $success = 'Hospital updated successfully';
                } else {
                    $error = 'Failed to update hospital';
                }
            }
        }
    } elseif ($action == 'delete') {
        $hospital_id = $_POST['hospital_id'] ?? '';
        
        // Check if hospital has children
        $check_query = "SELECT COUNT(*) as count FROM children WHERE hospital_id = :id";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':id', $hospital_id);
        $check_stmt->execute();
        $count = $check_stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Also check if hospital has staff
        $staff_check_query = "SELECT COUNT(*) as count FROM users WHERE hospital_id = :id";
        $staff_check_stmt = $db->prepare($staff_check_query);
        $staff_check_stmt->bindParam(':id', $hospital_id);
        $staff_check_stmt->execute();
        $staff_count = $staff_check_stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($count > 0) {
            $error = 'Cannot delete hospital with existing children records';
        } elseif ($staff_count > 0) {
            $error = 'Cannot delete hospital with existing staff members';
        } else {
            $query = "DELETE FROM hospitals WHERE hospital_id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $hospital_id);
            
            if ($stmt->execute()) {
                $success = 'Hospital deleted successfully';
            } else {
                $error = 'Failed to delete hospital';
            }
        }
    }
}

// Get all hospitals with statistics
$query = "SELECT h.*, 
          COUNT(DISTINCT c.child_id) as total_children,
          COUNT(DISTINCT u.user_id) as total_staff,
          COUNT(DISTINCT CASE WHEN vs.status = 'completed' THEN vs.schedule_id END) as total_vaccinations
          FROM hospitals h 
          LEFT JOIN children c ON h.hospital_id = c.hospital_id
          LEFT JOIN users u ON h.hospital_id = u.hospital_id AND u.role != 'super_admin'
          LEFT JOIN vaccination_schedule vs ON c.child_id = vs.child_id
          GROUP BY h.hospital_id 
          ORDER BY h.hospital_name";
$stmt = $db->prepare($query);
$stmt->execute();
$hospitals = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get summary statistics
$summary_query = "SELECT 
                  COUNT(DISTINCT h.hospital_id) as total_hospitals,
                  COUNT(DISTINCT c.child_id) as total_children,
                  COUNT(DISTINCT u.user_id) as total_staff,
                  COUNT(DISTINCT CASE WHEN vs.status = 'completed' THEN vs.schedule_id END) as total_vaccinations
                  FROM hospitals h
                  LEFT JOIN children c ON h.hospital_id = c.hospital_id
                  LEFT JOIN users u ON h.hospital_id = u.hospital_id AND u.role != 'super_admin'
                  LEFT JOIN vaccination_schedule vs ON c.child_id = vs.child_id";
$summary_stmt = $db->prepare($summary_query);
$summary_stmt->execute();
$summary = $summary_stmt->fetch(PDO::FETCH_ASSOC);
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="fas fa-hospital me-2 text-primary"></i>Manage Hospitals</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addHospitalModal">
            <i class="fas fa-plus me-1"></i>Add Hospital
        </button>
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

<!-- Summary Statistics -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-center border-primary">
            <div class="card-body">
                <i class="fas fa-hospital fa-2x text-primary mb-2"></i>
                <h4 class="text-primary"><?php echo $summary['total_hospitals']; ?></h4>
                <p class="card-text">Total Hospitals</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center border-success">
            <div class="card-body">
                <i class="fas fa-baby fa-2x text-success mb-2"></i>
                <h4 class="text-success"><?php echo $summary['total_children']; ?></h4>
                <p class="card-text">Total Children</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center border-info">
            <div class="card-body">
                <i class="fas fa-users fa-2x text-info mb-2"></i>
                <h4 class="text-info"><?php echo $summary['total_staff']; ?></h4>
                <p class="card-text">Total Staff</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center border-warning">
            <div class="card-body">
                <i class="fas fa-syringe fa-2x text-warning mb-2"></i>
                <h4 class="text-warning"><?php echo $summary['total_vaccinations']; ?></h4>
                <p class="card-text">Vaccinations</p>
            </div>
        </div>
    </div>
</div>

<!-- Hospitals List -->
<div class="card">
    <div class="card-header">
        <h5><i class="fas fa-hospital me-2"></i>Hospitals List</h5>
    </div>
    <div class="card-body">
        <?php if (empty($hospitals)): ?>
            <div class="text-center py-4">
                <i class="fas fa-hospital fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No hospitals found</h5>
                <p>Start by adding your first hospital to the system</p>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addHospitalModal">
                    <i class="fas fa-plus me-1"></i>Add Hospital
                </button>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Hospital Information</th>
                            <th>Contact Details</th>
                            <th>Statistics</th>
                            <th>Performance</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($hospitals as $hospital): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="me-3">
                                        <i class="fas fa-hospital fa-2x text-primary"></i>
                                    </div>
                                    <div>
                                        <strong><?php echo htmlspecialchars($hospital['hospital_name']); ?></strong>
                                        <br>
                                        <?php if ($hospital['address']): ?>
                                            <small class="text-muted">
                                                <i class="fas fa-map-marker-alt me-1"></i>
                                                <?php echo htmlspecialchars($hospital['address']); ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if ($hospital['phone']): ?>
                                    <div class="mb-1">
                                        <i class="fas fa-phone me-1 text-success"></i>
                                        <a href="tel:<?php echo htmlspecialchars($hospital['phone']); ?>"><?php echo htmlspecialchars($hospital['phone']); ?></a>
                                    </div>
                                <?php endif; ?>
                                <?php if ($hospital['email']): ?>
                                    <div>
                                        <i class="fas fa-envelope me-1 text-info"></i>
                                        <a href="mailto:<?php echo htmlspecialchars($hospital['email']); ?>"><?php echo htmlspecialchars($hospital['email']); ?></a>
                                    </div>
                                <?php endif; ?>
                                <?php if (!$hospital['phone'] && !$hospital['email']): ?>
                                    <span class="text-muted">No contact info</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="row text-center">
                                    <div class="col-4">
                                        <span class="badge bg-primary fs-6"><?php echo $hospital['total_children']; ?></span>
                                        <div><small class="text-muted">Children</small></div>
                                    </div>
                                    <div class="col-4">
                                        <span class="badge bg-info fs-6"><?php echo $hospital['total_staff']; ?></span>
                                        <div><small class="text-muted">Staff</small></div>
                                    </div>
                                    <div class="col-4">
                                        <span class="badge bg-success fs-6"><?php echo $hospital['total_vaccinations']; ?></span>
                                        <div><small class="text-muted">Vaccines</small></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php
                                $staff_ratio = $hospital['total_children'] > 0 ? $hospital['total_staff'] / $hospital['total_children'] : 0;
                                $vaccine_ratio = $hospital['total_children'] > 0 ? $hospital['total_vaccinations'] / $hospital['total_children'] : 0;
                                ?>
                                <div class="text-center">
                                    <div class="mb-1">
                                        <small class="text-muted">Staff Ratio:</small>
                                        <span class="badge bg-<?php echo $staff_ratio >= 0.1 ? 'success' : 'warning'; ?>">
                                            <?php echo number_format($staff_ratio, 2); ?>
                                        </span>
                                    </div>
                                    <div>
                                        <small class="text-muted">Vaccine Rate:</small>
                                        <span class="badge bg-<?php echo $vaccine_ratio >= 5 ? 'success' : ($vaccine_ratio >= 2 ? 'warning' : 'danger'); ?>">
                                            <?php echo number_format($vaccine_ratio, 1); ?>
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    <button class="btn btn-outline-primary" onclick="editHospital(<?php echo htmlspecialchars(json_encode($hospital)); ?>)" title="Edit Hospital">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-outline-info" onclick="viewHospitalDetails(<?php echo $hospital['hospital_id']; ?>)" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-outline-danger btn-delete" onclick="deleteHospital(<?php echo $hospital['hospital_id']; ?>)" title="Delete Hospital">
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

<!-- Add Hospital Modal -->
<div class="modal fade" id="addHospitalModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus me-2"></i>Add New Hospital
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="hospital_name" class="form-label">Hospital Name *</label>
                        <input type="text" class="form-control" id="hospital_name" name="hospital_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="3" placeholder="Enter hospital address"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone</label>
                        <input type="tel" class="form-control" id="phone" name="phone" placeholder="+255 XXX XXX XXX">
                        <div class="form-text">Format: +255XXXXXXXXX or 0XXXXXXXXX</div>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" placeholder="hospital@example.com">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Add Hospital
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Hospital Modal -->
<div class="modal fade" id="editHospitalModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="hospital_id" id="edit_hospital_id">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>Edit Hospital
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_hospital_name" class="form-label">Hospital Name *</label>
                        <input type="text" class="form-control" id="edit_hospital_name" name="hospital_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_address" class="form-label">Address</label>
                        <textarea class="form-control" id="edit_address" name="address" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="edit_phone" class="form-label">Phone</label>
                        <input type="tel" class="form-control" id="edit_phone" name="phone">
                        <div class="form-text">Format: +255XXXXXXXXX or 0XXXXXXXXX</div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="edit_email" name="email">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Update Hospital
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Hospital Details Modal -->
<div class="modal fade" id="hospitalDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-hospital me-2"></i>Hospital Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="hospitalDetailsContent">
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
function editHospital(hospital) {
    document.getElementById("edit_hospital_id").value = hospital.hospital_id;
    document.getElementById("edit_hospital_name").value = hospital.hospital_name;
    document.getElementById("edit_address").value = hospital.address || "";
    document.getElementById("edit_phone").value = hospital.phone || "";
    document.getElementById("edit_email").value = hospital.email || "";
    
    new bootstrap.Modal(document.getElementById("editHospitalModal")).show();
}

function deleteHospital(hospitalId) {
    if (confirm("Are you sure you want to delete this hospital? This action cannot be undone and will affect all associated records.")) {
        const form = document.createElement("form");
        form.method = "POST";
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="hospital_id" value="${hospitalId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function viewHospitalDetails(hospitalId) {
    // Show loading
    document.getElementById("hospitalDetailsContent").innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading hospital details...</p>
        </div>
    `;
    
    new bootstrap.Modal(document.getElementById("hospitalDetailsModal")).show();
    
    // Simple fallback since detailed API might not exist
    setTimeout(() => {
        document.getElementById("hospitalDetailsContent").innerHTML = `
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                Detailed hospital analytics and reporting features are under development.
            </div>
            <div class="row">
                <div class="col-md-6">
                    <h6>Quick Actions</h6>
                    <div class="d-grid gap-2">
                        <a href="../admin/dashboard.php" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-chart-bar me-1"></i>View Hospital Dashboard
                        </a>
                        <a href="admins.php" class="btn btn-outline-info btn-sm">
                            <i class="fas fa-users-cog me-1"></i>Manage Administrators
                        </a>
                    </div>
                </div>
                <div class="col-md-6">
                    <h6>Hospital Statistics</h6>
                    <p class="text-muted">Detailed statistics will be shown here in future updates.</p>
                </div>
            </div>
        `;
    }, 1000);
}

// Form validation and auto-clear alerts
document.addEventListener('DOMContentLoaded', function() {
    // Auto-clear alerts after 5 seconds
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