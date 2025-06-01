<?php
$page_title = 'Dashboard';
require_once 'includes/header.php';

$session->requireLogin();

$database = new Database();
$db = $database->getConnection();

if ($is_parent) {
    // Parent Dashboard
    $child = $session->getCurrentChild();
    $child_id = $child['child_id'];
    
    // Get vaccination status
    $vac_status = $utils->getVaccinationStatus($child_id);
    
    // Get upcoming vaccinations
    $query = "SELECT vs.*, v.vaccine_name 
              FROM vaccination_schedule vs 
              JOIN vaccines v ON vs.vaccine_id = v.vaccine_id 
              WHERE vs.child_id = :child_id AND vs.status = 'pending' 
              ORDER BY vs.scheduled_date ASC LIMIT 5";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':child_id', $child_id);
    $stmt->execute();
    $upcoming_vaccines = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get latest medical record
    $query = "SELECT * FROM child_medical_records 
              WHERE child_id = :child_id 
              ORDER BY visit_date DESC LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':child_id', $child_id);
    $stmt->execute();
    $latest_record = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get growth data for chart
    $growth_data = $utils->getGrowthChartData($child_id);
    
} else {
    // Staff Dashboard
    $user = $session->getCurrentUser();
    $hospital_id = $user['hospital_id'];
    
    // Get statistics based on role
    if ($user['role'] == 'super_admin') {
        // Global statistics
        $query = "SELECT COUNT(*) as total_hospitals FROM hospitals";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $total_hospitals = $stmt->fetch(PDO::FETCH_ASSOC)['total_hospitals'];
        
        $query = "SELECT COUNT(*) as total_children FROM children";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $total_children = $stmt->fetch(PDO::FETCH_ASSOC)['total_children'];
        
        $query = "SELECT COUNT(*) as total_staff FROM users WHERE role != 'super_admin'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $total_staff = $stmt->fetch(PDO::FETCH_ASSOC)['total_staff'];
        
    } else {
        // Hospital-specific statistics
        $where_clause = $hospital_id ? "WHERE hospital_id = :hospital_id" : "";
        
        $query = "SELECT COUNT(*) as total_children FROM children $where_clause";
        $stmt = $db->prepare($query);
        if ($hospital_id) $stmt->bindParam(':hospital_id', $hospital_id);
        $stmt->execute();
        $total_children = $stmt->fetch(PDO::FETCH_ASSOC)['total_children'];
        
        $query = "SELECT COUNT(*) as pending_vaccines 
                  FROM vaccination_schedule vs 
                  JOIN children c ON vs.child_id = c.child_id 
                  WHERE vs.status = 'pending' AND vs.scheduled_date <= CURDATE() 
                  " . ($hospital_id ? "AND c.hospital_id = :hospital_id" : "");
        $stmt = $db->prepare($query);
        if ($hospital_id) $stmt->bindParam(':hospital_id', $hospital_id);
        $stmt->execute();
        $pending_vaccines = $stmt->fetch(PDO::FETCH_ASSOC)['pending_vaccines'];
        
        $query = "SELECT COUNT(*) as overdue_vaccines 
                  FROM vaccination_schedule vs 
                  JOIN children c ON vs.child_id = c.child_id 
                  WHERE vs.status = 'pending' AND vs.scheduled_date < CURDATE() 
                  " . ($hospital_id ? "AND c.hospital_id = :hospital_id" : "");
        $stmt = $db->prepare($query);
        if ($hospital_id) $stmt->bindParam(':hospital_id', $hospital_id);
        $stmt->execute();
        $overdue_vaccines = $stmt->fetch(PDO::FETCH_ASSOC)['overdue_vaccines'];
    }
    
    // Get recent activities
    $query = "SELECT c.child_name, c.registration_number, vs.scheduled_date, v.vaccine_name, vs.status
              FROM vaccination_schedule vs
              JOIN children c ON vs.child_id = c.child_id
              JOIN vaccines v ON vs.vaccine_id = v.vaccine_id
              " . ($hospital_id ? "WHERE c.hospital_id = :hospital_id" : "") . "
              ORDER BY vs.updated_at DESC LIMIT 10";
    $stmt = $db->prepare($query);
    if ($hospital_id) $stmt->bindParam(':hospital_id', $hospital_id);
    $stmt->execute();
    $recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<?php if ($is_parent): ?>
<!-- Parent Dashboard -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Welcome, <?php echo htmlspecialchars($child['parent_name']); ?></h1>
</div>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-baby me-2"></i>Child Information</h5>
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($child['child_name']); ?></p>
                        <p><strong>Registration Number:</strong> <?php echo htmlspecialchars($child['registration_number']); ?></p>
                        <p><strong>Date of Birth:</strong> <?php echo $utils->formatDate($child['date_of_birth']); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Age:</strong> <?php echo $utils->getChildAgeInMonths($child['date_of_birth']); ?> months</p>
                        <p><strong>Hospital:</strong> <?php echo htmlspecialchars($child['hospital_name']); ?></p>
                        <p><strong>Gender:</strong> <?php echo ucfirst($child['gender']); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-syringe fa-2x text-primary mb-2"></i>
                <h5><?php echo $vac_status['completed_vaccines']; ?>/<?php echo $vac_status['total_vaccines']; ?></h5>
                <p class="card-text">Vaccines Completed</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                <h5><?php echo $vac_status['total_vaccines'] - $vac_status['completed_vaccines']; ?></h5>
                <p class="card-text">Pending Vaccines</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-exclamation-triangle fa-2x text-danger mb-2"></i>
                <h5><?php echo $vac_status['overdue_vaccines']; ?></h5>
                <p class="card-text">Overdue Vaccines</p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-calendar me-2"></i>Upcoming Vaccinations</h5>
            </div>
            <div class="card-body">
                <?php if (empty($upcoming_vaccines)): ?>
                    <p class="text-muted">No upcoming vaccinations.</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($upcoming_vaccines as $vaccine): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1"><?php echo htmlspecialchars($vaccine['vaccine_name']); ?></h6>
                                <small class="text-muted"><?php echo $utils->formatDate($vaccine['scheduled_date']); ?></small>
                            </div>
                            <?php
                            $date_diff = (strtotime($vaccine['scheduled_date']) - time()) / (24 * 60 * 60);
                            if ($date_diff < 0) {
                                echo '<span class="badge bg-danger">Overdue</span>';
                            } elseif ($date_diff <= 7) {
                                echo '<span class="badge bg-warning">Due Soon</span>';
                            } else {
                                echo '<span class="badge bg-success">Scheduled</span>';
                            }
                            ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-chart-line me-2"></i>Latest Growth Record</h5>
            </div>
            <div class="card-body">
                <?php if ($latest_record): ?>
                    <div class="row text-center">
                        <div class="col-4">
                            <h6><?php echo $latest_record['weight_kg']; ?> kg</h6>
                            <small class="text-muted">Weight</small>
                        </div>
                        <div class="col-4">
                            <h6><?php echo $latest_record['height_cm']; ?> cm</h6>
                            <small class="text-muted">Height</small>
                        </div>
                        <div class="col-4">
                            <h6><?php echo $latest_record['age_months']; ?> months</h6>
                            <small class="text-muted">Age</small>
                        </div>
                    </div>
                    <hr>
                    <p><strong>Last Visit:</strong> <?php echo $utils->formatDate($latest_record['visit_date']); ?></p>
                    <?php if ($latest_record['notes']): ?>
                        <p><strong>Notes:</strong> <?php echo htmlspecialchars($latest_record['notes']); ?></p>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="text-muted">No medical records available.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
<!-- Staff Dashboard -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Dashboard</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                <i class="fas fa-download me-1"></i>Export
            </button>
        </div>
    </div>
</div>

<div class="row mb-4">
    <?php if ($user['role'] == 'super_admin'): ?>
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-hospital fa-2x text-primary mb-2"></i>
                <h3><?php echo $total_hospitals; ?></h3>
                <p class="card-text">Total Hospitals</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-baby fa-2x text-success mb-2"></i>
                <h3><?php echo $total_children; ?></h3>
                <p class="card-text">Total Children</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-users fa-2x text-info mb-2"></i>
                <h3><?php echo $total_staff; ?></h3>
                <p class="card-text">Total Staff</p>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-baby fa-2x text-primary mb-2"></i>
                <h3><?php echo $total_children; ?></h3>
                <p class="card-text">Total Children</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-syringe fa-2x text-warning mb-2"></i>
                <h3><?php echo $pending_vaccines; ?></h3>
                <p class="card-text">Due Vaccines</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-exclamation-triangle fa-2x text-danger mb-2"></i>
                <h3><?php echo $overdue_vaccines; ?></h3>
                <p class="card-text">Overdue Vaccines</p>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-activity me-2"></i>Recent Activities</h5>
            </div>
            <div class="card-body">
                <?php if (empty($recent_activities)): ?>
                    <p class="text-muted">No recent activities.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Child</th>
                                    <th>Vaccine</th>
                                    <th>Scheduled Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_activities as $activity): ?>
                                <tr>
                                    <td>
                                        <?php echo htmlspecialchars($activity['child_name']); ?><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($activity['registration_number']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($activity['vaccine_name']); ?></td>
                                    <td><?php echo $utils->formatDate($activity['scheduled_date']); ?></td>
                                    <td>
                                        <?php
                                        switch ($activity['status']) {
                                            case 'completed':
                                                echo '<span class="badge bg-success">Completed</span>';
                                                break;
                                            case 'pending':
                                                if (strtotime($activity['scheduled_date']) < time()) {
                                                    echo '<span class="badge bg-danger">Overdue</span>';
                                                } else {
                                                    echo '<span class="badge bg-warning">Pending</span>';
                                                }
                                                break;
                                            case 'missed':
                                                echo '<span class="badge bg-secondary">Missed</span>';
                                                break;
                                        }
                                        ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-bell me-2"></i>Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <?php if ($session->hasPermission('nurse')): ?>
                        <a href="nurse/register_child.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Register New Child
                        </a>
                        <a href="nurse/vaccination.php" class="btn btn-outline-primary">
                            <i class="fas fa-syringe me-2"></i>Record Vaccination
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($session->hasPermission('doctor')): ?>
                        <a href="doctor/medical_records.php" class="btn btn-primary">
                            <i class="fas fa-file-medical me-2"></i>Add Medical Record
                        </a>
                        <a href="doctor/growth_charts.php" class="btn btn-outline-primary">
                            <i class="fas fa-chart-area me-2"></i>View Growth Charts
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($session->hasPermission('hospital_admin')): ?>
                        <a href="admin/staff.php" class="btn btn-primary">
                            <i class="fas fa-user-plus me-2"></i>Add Staff Member
                        </a>
                        <a href="admin/reports.php" class="btn btn-outline-primary">
                            <i class="fas fa-chart-bar me-2"></i>View Reports
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($session->hasPermission('super_admin')): ?>
                        <a href="super_admin/hospitals.php" class="btn btn-primary">
                            <i class="fas fa-hospital me-2"></i>Manage Hospitals
                        </a>
                        <a href="super_admin/reports.php" class="btn btn-outline-primary">
                            <i class="fas fa-chart-line me-2"></i>Global Reports
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-header">
                <h5><i class="fas fa-info-circle me-2"></i>System Info</h5>
            </div>
            <div class="card-body">
                <p><strong>Role:</strong> <?php echo ucwords(str_replace('_', ' ', $user['role'])); ?></p>
                <?php if ($user['hospital_id']): ?>
                    <p><strong>Hospital:</strong> <?php echo htmlspecialchars($user['hospital_name']); ?></p>
                <?php endif; ?>
                <p><strong>Last Login:</strong> <?php echo date('M d, Y H:i'); ?></p>
                <hr>
                <small class="text-muted">NeoCare System v1.0.0</small>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
$extra_scripts = '
<script>
    // Auto refresh every 5 minutes
    setTimeout(function() {
        location.reload();
    }, 300000);
    
    // Initialize any charts if needed
    document.addEventListener("DOMContentLoaded", function() {
        // Add any dashboard-specific JavaScript here
    });
</script>';

require_once 'includes/footer.php';
?>
