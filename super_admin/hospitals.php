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
                        <tr data-hospital-id="<?php echo $hospital['hospital_id']; ?>" data-hospital='<?php echo htmlspecialchars(json_encode($hospital)); ?>'>
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
    <div class="modal-dialog modal-xl">
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
    // Show loading first
    document.getElementById("hospitalDetailsContent").innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading hospital details...</p>
        </div>
    `;
    
    // Show the modal
    new bootstrap.Modal(document.getElementById("hospitalDetailsModal")).show();
    
    // Find the hospital data from the table row
    const hospitalRow = document.querySelector(`tr[data-hospital-id="${hospitalId}"]`);
    if (!hospitalRow) {
        document.getElementById("hospitalDetailsContent").innerHTML = `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Hospital information not found.
            </div>
        `;
        return;
    }
    
    // Extract hospital data from the data attribute
    const hospital = JSON.parse(hospitalRow.getAttribute('data-hospital'));
    
    // Calculate metrics
    const staff_ratio = hospital.total_children > 0 ? hospital.total_staff / hospital.total_children : 0;
    const vaccine_ratio = hospital.total_children > 0 ? hospital.total_vaccinations / hospital.total_children : 0;
    
    // Generate detailed hospital information
    setTimeout(() => {
        document.getElementById("hospitalDetailsContent").innerHTML = `
            <!-- Hospital Header -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-primary">
                        <div class="card-header bg-primary text-white">
                            <div class="row align-items-center">
                                <div class="col">
                                    <h4 class="mb-0">
                                        <i class="fas fa-hospital me-2"></i>
                                        ${hospital.hospital_name}
                                    </h4>
                                </div>
                                <div class="col-auto">
                                    <button class="btn btn-light btn-sm" onclick="editHospitalFromDetails(${hospital.hospital_id})">
                                        <i class="fas fa-edit me-1"></i>Edit
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="text-muted mb-3">
                                        <i class="fas fa-info-circle me-2"></i>Contact Information
                                    </h6>
                                    <table class="table table-borderless table-sm">
                                        <tr>
                                            <td width="30%"><strong>Address:</strong></td>
                                            <td>
                                                ${hospital.address ? 
                                                    `<i class="fas fa-map-marker-alt text-success me-1"></i>${hospital.address}` : 
                                                    '<span class="text-muted">Not provided</span>'
                                                }
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>Phone:</strong></td>
                                            <td>
                                                ${hospital.phone ? 
                                                    `<i class="fas fa-phone text-success me-1"></i><a href="tel:${hospital.phone}" class="text-decoration-none">${hospital.phone}</a>` : 
                                                    '<span class="text-muted">Not provided</span>'
                                                }
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>Email:</strong></td>
                                            <td>
                                                ${hospital.email ? 
                                                    `<i class="fas fa-envelope text-info me-1"></i><a href="mailto:${hospital.email}" class="text-decoration-none">${hospital.email}</a>` : 
                                                    '<span class="text-muted">Not provided</span>'
                                                }
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="text-muted mb-3">
                                        <i class="fas fa-chart-bar me-2"></i>Quick Statistics
                                    </h6>
                                    <div class="row text-center">
                                        <div class="col-4">
                                            <div class="bg-light p-3 rounded">
                                                <i class="fas fa-baby fa-2x text-primary mb-2"></i>
                                                <h4 class="text-primary mb-0">${hospital.total_children}</h4>
                                                <small class="text-muted">Children</small>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="bg-light p-3 rounded">
                                                <i class="fas fa-users fa-2x text-info mb-2"></i>
                                                <h4 class="text-info mb-0">${hospital.total_staff}</h4>
                                                <small class="text-muted">Staff</small>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="bg-light p-3 rounded">
                                                <i class="fas fa-syringe fa-2x text-success mb-2"></i>
                                                <h4 class="text-success mb-0">${hospital.total_vaccinations}</h4>
                                                <small class="text-muted">Vaccines</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Performance Metrics -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-warning text-dark">
                            <h6 class="mb-0">
                                <i class="fas fa-tachometer-alt me-2"></i>Performance Metrics
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span>Staff to Children Ratio</span>
                                    <span class="badge bg-${staff_ratio >= 0.1 ? 'success' : 'warning'} fs-6">
                                        ${staff_ratio.toFixed(2)}
                                    </span>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar bg-${staff_ratio >= 0.1 ? 'success' : 'warning'}" 
                                         style="width: ${Math.min(staff_ratio * 1000, 100)}%"></div>
                                </div>
                                <small class="text-muted">
                                    ${staff_ratio >= 0.1 ? 'Good staff coverage' : 'Consider increasing staff'}
                                </small>
                            </div>
                            
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span>Vaccination Rate per Child</span>
                                    <span class="badge bg-${vaccine_ratio >= 5 ? 'success' : (vaccine_ratio >= 2 ? 'warning' : 'danger')} fs-6">
                                        ${vaccine_ratio.toFixed(1)}
                                    </span>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar bg-${vaccine_ratio >= 5 ? 'success' : (vaccine_ratio >= 2 ? 'warning' : 'danger')}" 
                                         style="width: ${Math.min(vaccine_ratio * 10, 100)}%"></div>
                                </div>
                                <small class="text-muted">
                                    ${vaccine_ratio >= 5 ? 'Excellent vaccination coverage' : 
                                      (vaccine_ratio >= 2 ? 'Good vaccination rate' : 'Needs improvement in vaccination coverage')}
                                </small>
                            </div>
                            
                            <div class="alert alert-info mb-0">
                                <i class="fas fa-lightbulb me-2"></i>
                                <strong>Performance Summary:</strong><br>
                                This hospital ${hospital.total_children > 0 ? 'is actively serving' : 'has no registered'} 
                                ${hospital.total_children} children with ${hospital.total_staff} staff members. 
                                ${hospital.total_vaccinations > 0 ? 
                                    `A total of ${hospital.total_vaccinations} vaccinations have been completed.` : 
                                    'No vaccinations have been recorded yet.'
                                }
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h6 class="mb-0">
                                <i class="fas fa-chart-pie me-2"></i>Hospital Status
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row text-center mb-3">
                                <div class="col-12">
                                    <div class="p-3 rounded ${getHospitalStatusClass(hospital)}">
                                        <i class="fas fa-${getHospitalStatusIcon(hospital)} fa-3x mb-2"></i>
                                        <h5 class="mb-0">${getHospitalStatus(hospital)}</h5>
                                        <small>${getHospitalStatusDescription(hospital)}</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="list-group list-group-flush">
                                <div class="list-group-item d-flex justify-content-between align-items-center border-0 px-0">
                                    <span>Registration Status:</span>
                                    <span class="badge bg-success">Active</span>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center border-0 px-0">
                                    <span>Data Completeness:</span>
                                    <span class="badge bg-${getDataCompletenessStatus(hospital).class}">
                                        ${getDataCompletenessStatus(hospital).percentage}%
                                    </span>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center border-0 px-0">
                                    <span>Operational Level:</span>
                                    <span class="badge bg-${getOperationalLevel(hospital).class}">
                                        ${getOperationalLevel(hospital).level}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Action Recommendations -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-secondary text-white">
                            <h6 class="mb-0">
                                <i class="fas fa-tasks me-2"></i>Recommendations & Quick Actions
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-8">
                                    <h6 class="text-muted mb-3">Action Recommendations:</h6>
                                    <div class="list-group">
                                        ${generateRecommendations(hospital)}
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <h6 class="text-muted mb-3">Quick Actions:</h6>
                                    <div class="d-grid gap-2">
                                        <button class="btn btn-primary btn-sm" onclick="editHospitalFromDetails(${hospital.hospital_id})">
                                            <i class="fas fa-edit me-1"></i>Edit Hospital Info
                                        </button>
                                        <button class="btn btn-info btn-sm" onclick="alert('Feature coming soon!')">
                                            <i class="fas fa-chart-line me-1"></i>View Analytics
                                        </button>
                                        <button class="btn btn-success btn-sm" onclick="window.print()">
                                            <i class="fas fa-print me-1"></i>Print Report
                                        </button>
                                        <button class="btn btn-outline-secondary btn-sm" onclick="alert('Export feature coming soon!')">
                                            <i class="fas fa-download me-1"></i>Export Data
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }, 800); // Small delay to show loading
}

// Helper function to edit hospital from details modal
function editHospitalFromDetails(hospitalId) {
    // Close the details modal first
    bootstrap.Modal.getInstance(document.getElementById("hospitalDetailsModal")).hide();
    
    // Find the hospital data and trigger edit
    const hospitalRow = document.querySelector(`tr[data-hospital-id="${hospitalId}"]`);
    if (hospitalRow) {
        const hospital = JSON.parse(hospitalRow.getAttribute('data-hospital'));
        editHospital(hospital);
    }
}

// Helper functions for status determination
function getHospitalStatus(hospital) {
    if (hospital.total_children === 0) return "Setup Required";
    if (hospital.total_staff === 0) return "Needs Staff";
    if (hospital.total_vaccinations / hospital.total_children >= 5) return "Excellent";
    if (hospital.total_vaccinations / hospital.total_children >= 2) return "Good";
    return "Needs Improvement";
}

function getHospitalStatusClass(hospital) {
    const status = getHospitalStatus(hospital);
    switch (status) {
        case "Excellent": return "bg-success text-white";
        case "Good": return "bg-info text-white";
        case "Needs Staff": return "bg-warning text-dark";
        case "Setup Required": return "bg-secondary text-white";
        default: return "bg-danger text-white";
    }
}

function getHospitalStatusIcon(hospital) {
    const status = getHospitalStatus(hospital);
    switch (status) {
        case "Excellent": return "star";
        case "Good": return "thumbs-up";
        case "Needs Staff": return "user-plus";
        case "Setup Required": return "cog";
        default: return "exclamation-triangle";
    }
}

function getHospitalStatusDescription(hospital) {
    const status = getHospitalStatus(hospital);
    switch (status) {
        case "Excellent": return "Outstanding performance metrics";
        case "Good": return "Good operational status";
        case "Needs Staff": return "Requires additional staff";
        case "Setup Required": return "Initial setup needed";
        default: return "Performance below standards";
    }
}

function getDataCompletenessStatus(hospital) {
    let score = 0;
    if (hospital.hospital_name) score += 25;
    if (hospital.address) score += 25;
    if (hospital.phone) score += 25;
    if (hospital.email) score += 25;
    
    return {
        percentage: score,
        class: score >= 75 ? 'success' : (score >= 50 ? 'warning' : 'danger')
    };
}

function getOperationalLevel(hospital) {
    if (hospital.total_children === 0) {
        return { level: "Setup", class: "secondary" };
    }
    if (hospital.total_staff === 0) {
        return { level: "Limited", class: "warning" };
    }
    if (hospital.total_vaccinations / hospital.total_children >= 3) {
        return { level: "Full", class: "success" };
    }
    return { level: "Basic", class: "info" };
}

function generateRecommendations(hospital) {
    const recommendations = [];
    
    if (hospital.total_children === 0) {
        recommendations.push({
            type: "primary",
            icon: "user-plus",
            text: "Start registering children to begin operations"
        });
    }
    
    if (hospital.total_staff === 0) {
        recommendations.push({
            type: "warning",
            icon: "users",
            text: "Assign staff members to this hospital"
        });
    }
    
    if (!hospital.address) {
        recommendations.push({
            type: "info",
            icon: "map-marker-alt",
            text: "Add hospital address for better record keeping"
        });
    }
    
    if (!hospital.phone && !hospital.email) {
        recommendations.push({
            type: "info",
            icon: "phone",
            text: "Add contact information for communication"
        });
    }
    
    if (hospital.total_children > 0 && (hospital.total_staff / hospital.total_children) < 0.1) {
        recommendations.push({
            type: "warning",
            icon: "user-plus",
            text: "Consider increasing staff to improve staff-to-children ratio"
        });
    }
    
    if (hospital.total_children > 0 && (hospital.total_vaccinations / hospital.total_children) < 2) {
        recommendations.push({
            type: "danger",
            icon: "syringe",
            text: "Focus on improving vaccination coverage"
        });
    }
    
    if (recommendations.length === 0) {
        recommendations.push({
            type: "success",
            icon: "check-circle",
            text: "Hospital is performing well! Keep up the good work."
        });
    }
    
    return recommendations.map(rec => `
        <div class="list-group-item border-0 px-0">
            <i class="fas fa-${rec.icon} text-${rec.type} me-2"></i>
            ${rec.text}
        </div>
    `).join('');
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