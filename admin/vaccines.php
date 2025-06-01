<?php
$page_title = 'Manage Vaccines';
require_once '../includes/header.php';

$session->requireRole('hospital_admin');

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'add') {
        $vaccine_name = $utils->cleanInput($_POST['vaccine_name'] ?? '');
        $description = $utils->cleanInput($_POST['description'] ?? '');
        $child_age_weeks = $_POST['child_age_weeks'] ?? '';
        $dose_number = $_POST['dose_number'] ?? 1;
        
        if (empty($vaccine_name) || empty($child_age_weeks)) {
            $error = 'Please fill in all required fields';
        } elseif (!is_numeric($child_age_weeks) || $child_age_weeks < 0) {
            $error = 'Please enter a valid age in weeks';
        } else {
            $query = "INSERT INTO vaccines (vaccine_name, description, child_age_weeks, dose_number) 
                     VALUES (:vaccine_name, :description, :child_age_weeks, :dose_number)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':vaccine_name', $vaccine_name);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':child_age_weeks', $child_age_weeks);
            $stmt->bindParam(':dose_number', $dose_number);
            
            if ($stmt->execute()) {
                $success = 'Vaccine added successfully';
            } else {
                $error = 'Failed to add vaccine';
            }
        }
    } elseif ($action == 'edit') {
        $vaccine_id = $_POST['vaccine_id'] ?? '';
        $vaccine_name = $utils->cleanInput($_POST['vaccine_name'] ?? '');
        $description = $utils->cleanInput($_POST['description'] ?? '');
        $child_age_weeks = $_POST['child_age_weeks'] ?? '';
        $dose_number = $_POST['dose_number'] ?? 1;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if (empty($vaccine_name) || empty($child_age_weeks)) {
            $error = 'Please fill in all required fields';
        } else {
            $query = "UPDATE vaccines SET vaccine_name = :vaccine_name, description = :description, 
                     child_age_weeks = :child_age_weeks, dose_number = :dose_number, is_active = :is_active 
                     WHERE vaccine_id = :vaccine_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':vaccine_name', $vaccine_name);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':child_age_weeks', $child_age_weeks);
            $stmt->bindParam(':dose_number', $dose_number);
            $stmt->bindParam(':is_active', $is_active);
            $stmt->bindParam(':vaccine_id', $vaccine_id);
            
            if ($stmt->execute()) {
                $success = 'Vaccine updated successfully';
            } else {
                $error = 'Failed to update vaccine';
            }
        }
    } elseif ($action == 'toggle_status') {
        $vaccine_id = $_POST['vaccine_id'] ?? '';
        
        $query = "UPDATE vaccines SET is_active = NOT is_active WHERE vaccine_id = :vaccine_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':vaccine_id', $vaccine_id);
        
        if ($stmt->execute()) {
            $success = 'Vaccine status updated successfully';
        } else {
            $error = 'Failed to update vaccine status';
        }
    }
}

// Get all vaccines with usage statistics
$vaccines_query = "SELECT v.*, 
                   COUNT(vs.schedule_id) as total_scheduled,
                   SUM(CASE WHEN vs.status = 'completed' THEN 1 ELSE 0 END) as total_administered,
                   SUM(CASE WHEN vs.status = 'pending' AND vs.scheduled_date < CURDATE() THEN 1 ELSE 0 END) as overdue_count
                   FROM vaccines v
                   LEFT JOIN vaccination_schedule vs ON v.vaccine_id = vs.vaccine_id
                   GROUP BY v.vaccine_id
                   ORDER BY v.child_age_weeks, v.dose_number";
$vaccines_stmt = $db->prepare($vaccines_query);
$vaccines_stmt->execute();
$vaccines = $vaccines_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get vaccine statistics
$stats_query = "SELECT 
                COUNT(*) as total_vaccines,
                COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_vaccines,
                COUNT(CASE WHEN is_active = 0 THEN 1 END) as inactive_vaccines
                FROM vaccines";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Manage Vaccines</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addVaccineModal">
            <i class="fas fa-plus me-1"></i>Add Vaccine
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
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-syringe fa-2x text-primary mb-2"></i>
                <h4><?php echo $stats['total_vaccines']; ?></h4>
                <p class="card-text">Total Vaccines</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                <h4><?php echo $stats['active_vaccines']; ?></h4>
                <p class="card-text">Active Vaccines</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-times-circle fa-2x text-danger mb-2"></i>
                <h4><?php echo $stats['inactive_vaccines']; ?></h4>
                <p class="card-text">Inactive Vaccines</p>
            </div>
        </div>
    </div>
</div>

<!-- Vaccines List -->
<div class="card">
    <div class="card-header">
        <h5><i class="fas fa-list me-2"></i>Vaccine Schedule</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Vaccine Information</th>
                        <th>Schedule</th>
                        <th>Usage Statistics</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vaccines as $vaccine): ?>
                    <tr>
                        <td>
                            <div>
                                <strong><?php echo htmlspecialchars($vaccine['vaccine_name']); ?></strong>
                                <?php if ($vaccine['dose_number'] > 1): ?>
                                    <span class="badge bg-secondary">Dose <?php echo $vaccine['dose_number']; ?></span>
                                <?php endif; ?>
                                <br>
                                <small class="text-muted"><?php echo htmlspecialchars($vaccine['description']); ?></small>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-info">Week <?php echo $vaccine['child_age_weeks']; ?></span><br>
                            <small class="text-muted">
                                <?php 
                                if ($vaccine['child_age_weeks'] == 0) {
                                    echo 'At birth';
                                } elseif ($vaccine['child_age_weeks'] < 4) {
                                    echo $vaccine['child_age_weeks'] . ' weeks old';
                                } else {
                                    $months = round($vaccine['child_age_weeks'] / 4.33);
                                    echo $months . ' month' . ($months > 1 ? 's' : '') . ' old';
                                }
                                ?>
                            </small>
                        </td>
                        <td>
                            <div class="progress mb-1" style="height: 8px;">
                                <?php 
                                $completion = $vaccine['total_scheduled'] > 0 ? 
                                    ($vaccine['total_administered'] / $vaccine['total_scheduled']) * 100 : 0;
                                ?>
                                <div class="progress-bar bg-success" style="width: <?php echo $completion; ?>%"></div>
                            </div>
                            <small>
                                <?php echo $vaccine['total_administered']; ?>/<?php echo $vaccine['total_scheduled']; ?> administered
                                <?php if ($vaccine['overdue_count'] > 0): ?>
                                    <br><span class="text-danger"><?php echo $vaccine['overdue_count']; ?> overdue</span>
                                <?php endif; ?>
                            </small>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo $vaccine['is_active'] ? 'success' : 'danger'; ?>">
                                <?php echo $vaccine['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm" role="group">
                                <button class="btn btn-outline-primary" onclick="editVaccine(<?php echo htmlspecialchars(json_encode($vaccine)); ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-outline-<?php echo $vaccine['is_active'] ? 'warning' : 'success'; ?>" 
                                        onclick="toggleVaccineStatus(<?php echo $vaccine['vaccine_id']; ?>)">
                                    <i class="fas fa-<?php echo $vaccine['is_active'] ? 'pause' : 'play'; ?>"></i>
                                </button>
                                <button class="btn btn-outline-info" onclick="viewVaccineStats(<?php echo $vaccine['vaccine_id']; ?>)">
                                    <i class="fas fa-chart-bar"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Standard Immunization Schedule Information -->
<div class="card mt-4">
    <div class="card-header">
        <h5><i class="fas fa-info-circle me-2"></i>Standard Immunization Schedule</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h6>Birth to 6 Months</h6>
                <ul class="list-unstyled">
                    <li><i class="fas fa-syringe text-primary me-2"></i>BCG - At birth</li>
                    <li><i class="fas fa-syringe text-primary me-2"></i>OPV 1, DPT 1 - 6 weeks</li>
                    <li><i class="fas fa-syringe text-primary me-2"></i>OPV 2, DPT 2 - 10 weeks</li>
                    <li><i class="fas fa-syringe text-primary me-2"></i>OPV 3, DPT 3 - 14 weeks</li>
                </ul>
            </div>
            <div class="col-md-6">
                <h6>6 Months to 2 Years</h6>
                <ul class="list-unstyled">
                    <li><i class="fas fa-syringe text-success me-2"></i>Measles - 9 months</li>
                    <li><i class="fas fa-syringe text-success me-2"></i>MMR - 12 months</li>
                    <li><i class="fas fa-syringe text-success me-2"></i>Additional boosters as needed</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Add Vaccine Modal -->
<div class="modal fade" id="addVaccineModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Vaccine</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="vaccine_name" class="form-label">Vaccine Name *</label>
                        <input type="text" class="form-control" id="vaccine_name" name="vaccine_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="2"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="child_age_weeks" class="form-label">Age (weeks) *</label>
                                <input type="number" class="form-control" id="child_age_weeks" name="child_age_weeks" 
                                       min="0" max="520" required>
                                <div class="form-text">When this vaccine should be given</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="dose_number" class="form-label">Dose Number</label>
                                <select class="form-select" id="dose_number" name="dose_number">
                                    <option value="1">1st Dose</option>
                                    <option value="2">2nd Dose</option>
                                    <option value="3">3rd Dose</option>
                                    <option value="4">4th Dose</option>
                                    <option value="5">Booster</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Vaccine</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Vaccine Modal -->
<div class="modal fade" id="editVaccineModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="vaccine_id" id="edit_vaccine_id">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Vaccine</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_vaccine_name" class="form-label">Vaccine Name *</label>
                        <input type="text" class="form-control" id="edit_vaccine_name" name="vaccine_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="2"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_child_age_weeks" class="form-label">Age (weeks) *</label>
                                <input type="number" class="form-control" id="edit_child_age_weeks" name="child_age_weeks" 
                                       min="0" max="520" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_dose_number" class="form-label">Dose Number</label>
                                <select class="form-select" id="edit_dose_number" name="dose_number">
                                    <option value="1">1st Dose</option>
                                    <option value="2">2nd Dose</option>
                                    <option value="3">3rd Dose</option>
                                    <option value="4">4th Dose</option>
                                    <option value="5">Booster</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="edit_is_active" name="is_active">
                        <label class="form-check-label" for="edit_is_active">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Vaccine</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Vaccine Statistics Modal -->
<div class="modal fade" id="vaccineStatsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Vaccine Statistics</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="vaccineStatsContent">
                <!-- Content will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php
$extra_scripts = '
<script>
function editVaccine(vaccine) {
    document.getElementById("edit_vaccine_id").value = vaccine.vaccine_id;
    document.getElementById("edit_vaccine_name").value = vaccine.vaccine_name;
    document.getElementById("edit_description").value = vaccine.description || "";
    document.getElementById("edit_child_age_weeks").value = vaccine.child_age_weeks;
    document.getElementById("edit_dose_number").value = vaccine.dose_number;
    document.getElementById("edit_is_active").checked = vaccine.is_active == 1;
    
    new bootstrap.Modal(document.getElementById("editVaccineModal")).show();
}

function toggleVaccineStatus(vaccineId) {
    if (confirm("Are you sure you want to change the status of this vaccine?")) {
        const form = document.createElement("form");
        form.method = "POST";
        form.innerHTML = `
            <input type="hidden" name="action" value="toggle_status">
            <input type="hidden" name="vaccine_id" value="${vaccineId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function viewVaccineStats(vaccineId) {
    // Load vaccine statistics via AJAX
    fetch(`../api/get_vaccine_stats.php?vaccine_id=${vaccineId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById("vaccineStatsContent").innerHTML = data.html;
                new bootstrap.Modal(document.getElementById("vaccineStatsModal")).show();
            } else {
                alert("Error loading vaccine statistics");
            }
        })
        .catch(error => {
            console.error("Error:", error);
            // Fallback content
            document.getElementById("vaccineStatsContent").innerHTML = `
                <div class="text-center py-4">
                    <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
                    <h5>Statistics temporarily unavailable</h5>
                    <p class="text-muted">Please try again later</p>
                </div>
            `;
            new bootstrap.Modal(document.getElementById("vaccineStatsModal")).show();
        });
}

// Age calculator helper
document.getElementById("child_age_weeks").addEventListener("input", function() {
    const weeks = parseInt(this.value);
    const helpText = this.nextElementSibling;
    
    if (weeks > 0) {
        if (weeks < 4) {
            helpText.textContent = `${weeks} week${weeks > 1 ? "s" : ""} old`;
        } else {
            const months = Math.round(weeks / 4.33);
            helpText.textContent = `Approximately ${months} month${months > 1 ? "s" : ""} old`;
        }
    } else if (weeks === 0) {
        helpText.textContent = "At birth";
    } else {
        helpText.textContent = "When this vaccine should be given";
    }
});
</script>';

require_once '../includes/footer.php';
?>

