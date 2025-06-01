<?php
$page_title = 'Super Admin Dashboard';
require_once '../includes/header.php';

$session->requireRole('super_admin');

$database = new Database();
$db = $database->getConnection();

// First, let's check what columns exist in hospitals table
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
    // Fallback if DESCRIBE fails
    $hospital_columns = ['hospital_id', 'hospital_name', 'location', 'contact_person', 'is_active'];
}

// Determine the correct name column
$name_column = 'hospital_id'; // fallback
if (in_array('hospital_name', $hospital_columns)) {
    $name_column = 'hospital_name';
} elseif (in_array('name', $hospital_columns)) {
    $name_column = 'name';
}

// Global System Statistics
$global_stats_query = "SELECT 
    (SELECT COUNT(*) FROM hospitals) as total_hospitals,
    (SELECT COUNT(*) FROM users WHERE role != 'super_admin') as total_staff,
    (SELECT COUNT(*) FROM children) as total_children,
    (SELECT COUNT(*) FROM vaccination_schedule WHERE status = 'completed') as total_vaccinations,
    (SELECT COUNT(*) FROM vaccination_schedule WHERE status = 'pending' AND scheduled_date <= CURDATE()) as pending_vaccines,
    (SELECT COUNT(*) FROM vaccination_schedule WHERE status = 'pending' AND scheduled_date < CURDATE()) as overdue_vaccines";

$global_stmt = $db->prepare($global_stats_query);
$global_stmt->execute();
$global_stats = $global_stmt->fetch(PDO::FETCH_ASSOC);

// Hospital Performance Overview - Dynamic column selection
$hospital_performance_query = "SELECT 
    h.hospital_id,
    h.$name_column as hospital_name,
    " . (in_array('location', $hospital_columns) ? "h.location" : "'Not specified' as location") . ",
    " . (in_array('contact_person', $hospital_columns) ? "h.contact_person" : "'No contact' as contact_person") . ",
    " . (in_array('is_active', $hospital_columns) ? "h.is_active" : "1 as is_active") . ",
    (SELECT COUNT(*) FROM users WHERE hospital_id = h.hospital_id AND role != 'super_admin') as staff_count,
    (SELECT COUNT(*) FROM children WHERE hospital_id = h.hospital_id) as children_count,
    (SELECT COUNT(*) FROM vaccination_schedule vs JOIN children c ON vs.child_id = c.child_id WHERE c.hospital_id = h.hospital_id AND vs.status = 'completed') as completed_vaccines,
    (SELECT COUNT(*) FROM vaccination_schedule vs JOIN children c ON vs.child_id = c.child_id WHERE c.hospital_id = h.hospital_id AND vs.status = 'pending') as pending_vaccines
    FROM hospitals h
    ORDER BY h.$name_column";

$hospital_stmt = $db->prepare($hospital_performance_query);
$hospital_stmt->execute();
$hospital_performance = $hospital_stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent System Activities - Dynamic column selection
$recent_activities_query = "SELECT 
    'child_registration' as activity_type,
    CONCAT('New child registered: ', c.child_name) as description,
    h.$name_column as hospital_name,
    c.created_at as activity_date
    FROM children c
    JOIN hospitals h ON c.hospital_id = h.hospital_id
    WHERE c.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    
    UNION ALL
    
    SELECT 
    'user_creation' as activity_type,
    CONCAT('New ', u.role, ' created: ', u.full_name) as description,
    COALESCE(h.$name_column, 'System') as hospital_name,
    u.created_at as activity_date
    FROM users u
    LEFT JOIN hospitals h ON u.hospital_id = h.hospital_id
    WHERE u.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND u.role != 'super_admin'
    
    ORDER BY activity_date DESC
    LIMIT 15";

$activities_stmt = $db->prepare($recent_activities_query);
$activities_stmt->execute();
$recent_activities = $activities_stmt->fetchAll(PDO::FETCH_ASSOC);

// Monthly vaccination coverage by hospital - Dynamic column selection
$coverage_query = "SELECT 
    h.$name_column as hospital_name,
    COUNT(CASE WHEN vs.status = 'completed' AND MONTH(vs.administered_date) = MONTH(CURDATE()) THEN 1 END) as monthly_completed,
    COUNT(CASE WHEN vs.status = 'pending' THEN 1 END) as total_pending,
    ROUND(
        (COUNT(CASE WHEN vs.status = 'completed' THEN 1 END) * 100.0) / 
        NULLIF(COUNT(*), 0), 1
    ) as coverage_percentage
    FROM hospitals h
    LEFT JOIN children c ON h.hospital_id = c.hospital_id
    LEFT JOIN vaccination_schedule vs ON c.child_id = vs.child_id
    " . (in_array('is_active', $hospital_columns) ? "WHERE h.is_active = 1" : "") . "
    GROUP BY h.hospital_id, h.$name_column
    ORDER BY coverage_percentage DESC";

$coverage_stmt = $db->prepare($coverage_query);
$coverage_stmt->execute();
$coverage_data = $coverage_stmt->fetchAll(PDO::FETCH_ASSOC);

// System alerts and issues - Dynamic column selection
$alerts_query = "SELECT 
    'overdue_vaccines' as alert_type,
    CONCAT(COUNT(*), ' children have overdue vaccinations') as message,
    'warning' as severity,
    h.$name_column as hospital_name
    FROM vaccination_schedule vs
    JOIN children c ON vs.child_id = c.child_id
    JOIN hospitals h ON c.hospital_id = h.hospital_id
    WHERE vs.status = 'pending' AND vs.scheduled_date < DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY h.hospital_id, h.$name_column
    HAVING COUNT(*) > 5
    ORDER BY COUNT(*) DESC";

$alerts_stmt = $db->prepare($alerts_query);
$alerts_stmt->execute();
$system_alerts = $alerts_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="fas fa-crown me-2 text-warning"></i>Super Admin Dashboard</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="hospitals.php" class="btn btn-primary">
                <i class="fas fa-hospital me-1"></i>Manage Hospitals
            </a>
            <a href="admins.php" class="btn btn-info">
                <i class="fas fa-users-cog me-1"></i>Manage Admins
            </a>
            <a href="reports.php" class="btn btn-success">
                <i class="fas fa-chart-bar me-1"></i>Global Reports
            </a>
        </div>
    </div>
</div>

<!-- Debug Info (remove after testing) -->
<div class="alert alert-info">
    <strong>Debug Info:</strong> Using column "<?php echo $name_column; ?>" for hospital names. 
    Available columns: <?php echo implode(', ', $hospital_columns); ?>
</div>

<!-- System Overview Stats -->
<div class="row mb-4">
    <div class="col-lg-2 col-md-4 col-sm-6">
        <div class="card text-center border-primary">
            <div class="card-body">
                <i class="fas fa-hospital fa-3x text-primary mb-3"></i>
                <h3 class="text-primary"><?php echo $global_stats['total_hospitals']; ?></h3>
                <p class="card-text">Hospitals</p>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6">
        <div class="card text-center border-info">
            <div class="card-body">
                <i class="fas fa-users fa-3x text-info mb-3"></i>
                <h3 class="text-info"><?php echo $global_stats['total_staff']; ?></h3>
                <p class="card-text">Staff Members</p>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6">
        <div class="card text-center border-success">
            <div class="card-body">
                <i class="fas fa-baby fa-3x text-success mb-3"></i>
                <h3 class="text-success"><?php echo $global_stats['total_children']; ?></h3>
                <p class="card-text">Children</p>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6">
        <div class="card text-center border-warning">
            <div class="card-body">
                <i class="fas fa-syringe fa-3x text-warning mb-3"></i>
                <h3 class="text-warning"><?php echo $global_stats['total_vaccinations']; ?></h3>
                <p class="card-text">Vaccinations</p>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6">
        <div class="card text-center border-danger">
            <div class="card-body">
                <i class="fas fa-clock fa-3x text-danger mb-3"></i>
                <h3 class="text-danger"><?php echo $global_stats['pending_vaccines']; ?></h3>
                <p class="card-text">Pending</p>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6">
        <div class="card text-center border-dark">
            <div class="card-body">
                <i class="fas fa-exclamation-triangle fa-3x text-dark mb-3"></i>
                <h3 class="text-dark"><?php echo $global_stats['overdue_vaccines']; ?></h3>
                <p class="card-text">Overdue</p>
            </div>
        </div>
    </div>
</div>

<!-- System Alerts -->
<?php if (!empty($system_alerts)): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="alert alert-warning">
            <h5><i class="fas fa-exclamation-triangle me-2"></i>System Alerts</h5>
            <div class="row">
                <?php foreach ($system_alerts as $alert): ?>
                <div class="col-md-6">
                    <p class="mb-1">
                        <strong><?php echo htmlspecialchars($alert['hospital_name']); ?>:</strong>
                        <?php echo htmlspecialchars($alert['message']); ?>
                    </p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Main Dashboard Content -->
<div class="row">
    <!-- Hospital Performance Overview -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-hospital me-2"></i>Hospital Performance Overview</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Hospital</th>
                                <th>Location</th>
                                <th>Staff</th>
                                <th>Children</th>
                                <th>Vaccinations</th>
                                <th>Coverage</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($hospital_performance as $hospital): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($hospital['hospital_name']); ?></strong>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($hospital['contact_person']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($hospital['location']); ?></td>
                                <td>
                                    <span class="badge bg-info"><?php echo $hospital['staff_count']; ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-success"><?php echo $hospital['children_count']; ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-primary"><?php echo $hospital['completed_vaccines']; ?></span>
                                    <?php if ($hospital['pending_vaccines'] > 0): ?>
                                        <span class="badge bg-warning"><?php echo $hospital['pending_vaccines']; ?> pending</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    $total_schedules = $hospital['completed_vaccines'] + $hospital['pending_vaccines'];
                                    $coverage = $total_schedules > 0 ? round(($hospital['completed_vaccines'] / $total_schedules) * 100, 1) : 0;
                                    ?>
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar 
                                            <?php echo $coverage >= 80 ? 'bg-success' : ($coverage >= 60 ? 'bg-warning' : 'bg-danger'); ?>" 
                                            style="width: <?php echo $coverage; ?>%">
                                            <?php echo $coverage; ?>%
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($hospital['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="hospitals.php?action=edit&id=<?php echo $hospital['hospital_id']; ?>" class="btn btn-outline-primary">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="hospital_details.php?id=<?php echo $hospital['hospital_id']; ?>" class="btn btn-outline-info">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Vaccination Coverage Chart -->
        <div class="card mt-3">
            <div class="card-header">
                <h5><i class="fas fa-chart-bar me-2"></i>Vaccination Coverage by Hospital</h5>
            </div>
            <div class="card-body">
                <canvas id="coverageChart" height="100"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Sidebar Content -->
    <div class="col-lg-4">
        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5><i class="fas fa-tools me-2"></i>System Management</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="hospitals.php?action=add" class="btn btn-outline-primary">
                        <i class="fas fa-plus me-2"></i>Add New Hospital
                    </a>
                    <a href="admins.php?action=add" class="btn btn-outline-info">
                        <i class="fas fa-user-plus me-2"></i>Create Hospital Admin
                    </a>
                    <a href="system_settings.php" class="btn btn-outline-warning">
                        <i class="fas fa-cogs me-2"></i>System Settings
                    </a>
                    <a href="backup.php" class="btn btn-outline-danger">
                        <i class="fas fa-database me-2"></i>Backup System
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Recent System Activities -->
        <div class="card mt-3">
            <div class="card-header">
                <h5><i class="fas fa-clock me-2"></i>Recent System Activities</h5>
            </div>
            <div class="card-body">
                <?php if (empty($recent_activities)): ?>
                    <p class="text-muted text-center">No recent activities</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recent_activities as $activity): ?>
                        <div class="list-group-item px-0 py-2">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <?php if ($activity['activity_type'] == 'child_registration'): ?>
                                        <i class="fas fa-baby text-success me-1"></i>
                                    <?php else: ?>
                                        <i class="fas fa-user-plus text-info me-1"></i>
                                    <?php endif; ?>
                                    <small><?php echo htmlspecialchars($activity['description']); ?></small>
                                    <br>
                                    <small class="text-muted"><?php echo htmlspecialchars($activity['hospital_name']); ?></small>
                                </div>
                                <small class="text-muted">
                                    <?php echo date('M j', strtotime($activity['activity_date'])); ?>
                                </small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- System Health Status -->
        <div class="card mt-3">
            <div class="card-header">
                <h5><i class="fas fa-heartbeat me-2"></i>System Health</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6">
                        <div class="border-end">
                            <?php
                            $active_hospitals = array_filter($hospital_performance, function($h) { return $h['is_active']; });
                            $health_percentage = count($hospital_performance) > 0 ? (count($active_hospitals) / count($hospital_performance)) * 100 : 0;
                            ?>
                            <h4 class="<?php echo $health_percentage >= 90 ? 'text-success' : ($health_percentage >= 70 ? 'text-warning' : 'text-danger'); ?>">
                                <?php echo round($health_percentage); ?>%
                            </h4>
                            <small class="text-muted">Hospitals Active</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <?php
                        $overall_coverage = $global_stats['total_vaccinations'] + $global_stats['pending_vaccines'] > 0 ? 
                            ($global_stats['total_vaccinations'] / ($global_stats['total_vaccinations'] + $global_stats['pending_vaccines'])) * 100 : 0;
                        ?>
                        <h4 class="<?php echo $overall_coverage >= 80 ? 'text-success' : ($overall_coverage >= 60 ? 'text-warning' : 'text-danger'); ?>">
                            <?php echo round($overall_coverage); ?>%
                        </h4>
                        <small class="text-muted">Vaccination Coverage</small>
                    </div>
                </div>
                <hr>
                <div class="text-center">
                    <small class="text-muted">
                        Last updated: <?php echo date('M j, Y g:i A'); ?>
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
<script>
// Vaccination Coverage Chart
const ctx = document.getElementById('coverageChart').getContext('2d');
const coverageChart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: [
            <?php foreach ($coverage_data as $data): ?>
                '<?php echo htmlspecialchars($data['hospital_name']); ?>',
            <?php endforeach; ?>
        ],
        datasets: [{
            label: 'Coverage Percentage',
            data: [
                <?php foreach ($coverage_data as $data): ?>
                    <?php echo $data['coverage_percentage'] ?? 0; ?>,
                <?php endforeach; ?>
            ],
            backgroundColor: [
                <?php foreach ($coverage_data as $data): ?>
                    '<?php 
                    $coverage = $data['coverage_percentage'] ?? 0;
                    echo $coverage >= 80 ? '#28a745' : ($coverage >= 60 ? '#ffc107' : '#dc3545'); 
                    ?>',
                <?php endforeach; ?>
            ],
            borderColor: '#ffffff',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                max: 100,
                ticks: {
                    callback: function(value) {
                        return value + '%';
                    }
                }
            }
        }
    }
});

// Auto-refresh dashboard every 5 minutes
setTimeout(function() {
    location.reload();
}, 300000);
</script>

<?php require_once '../includes/footer.php'; ?>