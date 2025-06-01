<?php
$page_title = 'Manage Hospitals';
require_once '../includes/header.php';

$session->requireRole('super_admin');

$database = new Database();
$db = $database->getConnection();

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
    } elseif ($action == 'edit') {
        $hospital_id = $_POST['hospital_id'] ?? '';
        $hospital_name = $utils->cleanInput($_POST['hospital_name'] ?? '');
        $address = $utils->cleanInput($_POST['address'] ?? '');
        $phone = $utils->cleanInput($_POST['phone'] ?? '');
        $email = $utils->cleanInput($_POST['email'] ?? '');
        
        if (empty($hospital_name)) {
            $error = 'Hospital name is required';
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
    } elseif ($action == 'delete') {
        $hospital_id = $_POST['hospital_id'] ?? '';
        
        // Check if hospital has children
        $check_query = "SELECT COUNT(*) as count FROM children WHERE hospital_id = :id";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':id', $hospital_id);
        $check_stmt->execute();
        $count = $check_stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($count > 0) {
            $error = 'Cannot delete hospital with existing children records';
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
          COUNT(DISTINCT u.user_id) as total_staff
          FROM hospitals h 
          LEFT JOIN children c ON h.hospital_id = c.hospital_id
          LEFT JOIN users u ON h.hospital_id = u.hospital_id
          GROUP BY h.hospital_id 
          ORDER BY h.hospital_name";
$stmt = $db->prepare($query);
$stmt->execute();
$hospitals = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Manage Hospitals</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addHospitalModal">
            <i class="fas fa-plus me-1"></i>Add Hospital
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

<div class="card">
    <div class="card-header">
        <h5><i class="fas fa-hospital me-2"></i>Hospitals List</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Hospital Name</th>
                        <th>Address</th>
                        <th>Contact</th>
                        <th>Children</th>
                        <th>Staff</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($hospitals as $hospital): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($hospital['hospital_name']); ?></strong>
                        </td>
                        <td><?php echo htmlspecialchars($hospital['address']); ?></td>
                        <td>
                            <?php if ($hospital['phone']): ?>
                                <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($hospital['phone']); ?><br>
                            <?php endif; ?>
                            <?php if ($hospital['email']): ?>
                                <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($hospital['email']); ?>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge bg-primary"><?php echo $hospital['total_children']; ?></span></td>
                        <td><span class="badge bg-info"><?php echo $hospital['total_staff']; ?></span></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="editHospital(<?php echo htmlspecialchars(json_encode($hospital)); ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger btn-delete" onclick="deleteHospital(<?php echo $hospital['hospital_id']; ?>)">
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

<!-- Add Hospital Modal -->
<div class="modal fade" id="addHospitalModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Hospital</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="hospital_name" class="form-label">Hospital Name *</label>
                        <input type="text" class="form-control" id="hospital_name" name="hospital_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone</label>
                        <input type="tel" class="form-control" id="phone" name="phone">
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Hospital</button>
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
                    <h5 class="modal-title">Edit Hospital</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_hospital_name" class="form-label">Hospital Name *</label>
                        <input type="text" class="form-control" id="edit_hospital_name" name="hospital_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_address" class="form-label">Address</label>
                        <textarea class="form-control" id="edit_address" name="address" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="edit_phone" class="form-label">Phone</label>
                        <input type="tel" class="form-control" id="edit_phone" name="phone">
                    </div>
                    <div class="mb-3">
                        <label for="edit_email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="edit_email" name="email">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Hospital</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$extra_scripts = '
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
    if (confirm("Are you sure you want to delete this hospital? This action cannot be undone.")) {
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
</script>';

require_once '../includes/footer.php';
?>
