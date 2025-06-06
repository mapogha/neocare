<?php
$page_title = 'Admin Dashboard';
require_once '../includes/header.php';

// FIX: Update session role check to accept 'hospital_admin'
$session->requireRole(['admin', 'hospital_admin']);

$database = new Database();
$db = $database->getConnection();
$current_user = $session->getCurrentUser();
$hospital_id = $current_user['hospital_id'] ?? null;

// FIX: Check if hospital_id exists
if (!$hospital_id) {
    echo '<div class="container mt-4">';
    echo '<div class="alert alert-danger">';
    echo '<h5><i class="fas fa-exclamation-triangle me-2"></i>No Hospital Assignment</h5>';
    echo '<p>Your account is not assigned to any hospital. Please contact the system administrator.</p>';
    echo '<p><strong>User Details:</strong></p>';
    echo '<ul>';
    echo '<li>Username: ' . htmlspecialchars($current_user['username'] ?? 'Unknown') . '</li>';
    echo '<li>Role: ' . htmlspecialchars($current_user['role'] ?? 'Unknown') . '</li>';
    echo '<li>User ID: ' . htmlspecialchars($current_user['user_id'] ?? 'Unknown') . '</li>';
    echo '</ul>';
    echo '<a href="../login.php" class="btn btn-primary">Back to Login</a>';
    echo '</div></div>';
    require_once '../includes/footer.php';
    exit;
}

// Hospital Information - FIX: Add error handling and default values
$hospital_query = "SELECT * FROM hospitals WHERE hospital_id = :hospital_id";
$hospital_stmt = $db->prepare($hospital_query);
$hospital_stmt->bindParam(':hospital_id', $hospital_id);
$hospital_stmt->execute();
$hospital_info = $hospital_stmt->fetch(PDO::FETCH_ASSOC);

// FIX: Set default hospital info if not found
if (!$hospital_info || $hospital_info === false) {
    $hospital_info = [
        'hospital_name' => 'Unknown Hospital',
        'address' => 'Not specified',
        'phone' => 'Not specified',
        'email' => 'Not specified',
        'created_at' => date('Y-m-d')
    ];
}

// Hospital Statistics
$stats_query = "SELECT 
    (SELECT COUNT(*) FROM users WHERE hospital_id = :hospital_id AND role != 'super_admin') as total_staff,
    (SELECT COUNT(*) FROM users WHERE hospital_id = :hospital_id AND role = 'doctor') as doctors_count,
    (SELECT COUNT(*) FROM users WHERE hospital_id = :hospital_id AND role = 'nurse') as nurses_count,
    (SELECT COUNT(*) FROM children WHERE hospital_id = :hospital_id) as total_children,
    (SELECT COUNT(*) FROM children WHERE hospital_id = :hospital_id AND DATE(created_at) = CURDATE()) as children_today,
    (SELECT COUNT(*) FROM children WHERE hospital_id = :hospital_id AND WEEK(created_at) = WEEK(CURDATE())) as children_this_week";

$stats_stmt = $db->prepare($stats_query);
$stats_stmt->bindParam(':hospital_id', $hospital_id);
$stats_stmt->execute();
$hospital_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// FIX: Set default values if query fails
if (!$hospital_stats || $hospital_stats === false) {
    $hospital_stats = [
        'total_staff' => 0,
        'doctors_count' => 0,
        'nurses_count' => 0,
        'total_children' => 0,
        'children_today' => 0,
        'children_this_week' => 0
    ];
}

// Vaccination Statistics
$vaccine_stats_query = "SELECT 
    COUNT(CASE WHEN vs.status = 'completed' THEN 1 END) as completed_vaccines,
    COUNT(CASE WHEN vs.status = 'pending' AND vs.scheduled_date <= CURDATE() THEN 1 END) as due_vaccines,
    COUNT(CASE WHEN vs.status = 'pending' AND vs.scheduled_date < CURDATE() THEN 1 END) as overdue_vaccines,
    COUNT(CASE WHEN vs.status = 'completed' AND DATE(vs.administered_date) = CURDATE() THEN 1 END) as vaccines_today
    FROM vaccination_schedule vs
    JOIN children c ON vs.child_id = c.child_id
    WHERE c.hospital_id = :hospital_id";

$vaccine_stmt = $db->prepare($vaccine_stats_query);
$vaccine_stmt->bindParam(':hospital_id', $hospital_id);
$vaccine_stmt->execute();
$vaccine_stats = $vaccine_stmt->fetch(PDO::FETCH_ASSOC);

// FIX: Set default values if query fails
if (!$vaccine_stats || $vaccine_stats === false) {
    $vaccine_stats = [
        'completed_vaccines' => 0,
        'due_vaccines' => 0,
        'overdue_vaccines' => 0,
        'vaccines_today' => 0
    ];
}

// Staff Performance Overview - FIX: Remove last_login reference
$staff_performance_query = "SELECT 
    u.user_id,
    u.full_name,
    u.role,
    u.is_active,
    u.created_at,
    COALESCE(child_count.count, 0) as children_registered,
    COALESCE(vaccine_count.count, 0) as vaccines_administered
    FROM users u
    LEFT JOIN (
        SELECT registered_by, COUNT(*) as count 
        FROM children 
        WHERE hospital_id = :hospital_id 
        GROUP BY registered_by
    ) child_count ON u.user_id = child_count.registered_by
    LEFT JOIN (
        SELECT vs.administered_by, COUNT(*) as count 
        FROM vaccination_schedule vs 
        JOIN children c ON vs.child_id = c.child_id 
        WHERE c.hospital_id = :hospital_id AND vs.status = 'completed'
        GROUP BY vs.administered_by
    ) vaccine_count ON u.user_id = vaccine_count.administered_by
    WHERE u.hospital_id = :hospital_id AND u.role != 'super_admin'
    ORDER BY u.role, u.full_name";

$staff_stmt = $db->prepare($staff_performance_query);
$staff_stmt->bindParam(':hospital_id', $hospital_id);
$staff_stmt->execute();
$staff_performance = $staff_stmt->fetchAll(PDO::FETCH_ASSOC);

// FIX: Ensure staff_performance is always an array
if (!$staff_performance || $staff_performance === false) {
    $staff_performance = [];
}

// Recent Activities in Hospital
$activities_query = "SELECT 
    'registration' as activity_type,
    c.child_name as description,
    u.full_name as staff_name,
    c.created_at as activity_date
    FROM children c
    LEFT JOIN users u ON c.registered_by = u.user_id
    WHERE c.hospital_id = :hospital_id AND c.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    
    UNION ALL
    
    SELECT 
    'vaccination' as activity_type,
    CONCAT(c.child_name, ' - ', v.vaccine_name) as description,
    u.full_name as staff_name,
    vs.administered_date as activity_date
    FROM vaccination_schedule vs
    JOIN children c ON vs.child_id = c.child_id
    JOIN vaccines v ON vs.vaccine_id = v.vaccine_id
    LEFT JOIN users u ON vs.administered_by = u.user_id
    WHERE c.hospital_id = :hospital_id AND vs.administered_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    
    ORDER BY activity_date DESC
    LIMIT 20";

$activities_stmt = $db->prepare($activities_query);
$activities_stmt->bindParam(':hospital_id', $hospital_id);
$activities_stmt->execute();
$recent_activities = $activities_stmt->fetchAll(PDO::FETCH_ASSOC);

// FIX: Ensure recent_activities is always an array
if (!$recent_activities || $recent_activities === false) {
    $recent_activities = [];
}

// Urgent Actions Needed - FIX: Simplify query to avoid complex joins
$urgent_actions_query = "SELECT 
    COUNT(CASE WHEN vs.status = 'pending' AND vs.scheduled_date < DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as critical_overdue,
    COUNT(CASE WHEN vs.status = 'pending' AND vs.scheduled_date = CURDATE() THEN 1 END) as due_today,
    (SELECT COUNT(*) FROM users WHERE hospital_id = :hospital_id AND created_at < DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND is_active = 0 AND role != 'super_admin') as inactive_staff
    FROM vaccination_schedule vs
    JOIN children c ON vs.child_id = c.child_id
    WHERE c.hospital_id = :hospital_id";

$urgent_stmt = $db->prepare($urgent_actions_query);
$urgent_stmt->bindParam(':hospital_id', $hospital_id);
$urgent_stmt->execute();
$urgent_actions = $urgent_stmt->fetch(PDO::FETCH_ASSOC);

// FIX: Set default values if query fails
if (!$urgent_actions || $urgent_actions === false) {
    $urgent_actions = [
        'critical_overdue' => 0,
        'due_today' => 0,
        'inactive_staff' => 0
    ];
}

// Monthly vaccination trends
$trends_query = "SELECT 
    DATE_FORMAT(vs.administered_date, '%Y-%m') as month,
    COUNT(*) as vaccinations
    FROM vaccination_schedule vs
    JOIN children c ON vs.child_id = c.child_id
    WHERE c.hospital_id = :hospital_id AND vs.status = 'completed'
    AND vs.administered_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(vs.administered_date, '%Y-%m')
    ORDER BY month";

$trends_stmt = $db->prepare($trends_query);
$trends_stmt->bindParam(':hospital_id', $hospital_id);
$trends_stmt->execute();
$vaccination_trends = $trends_stmt->fetchAll(PDO::FETCH_ASSOC);

// FIX: Ensure vaccination_trends is always an array
if (!$vaccination_trends || $vaccination_trends === false) {
    $vaccination_trends = [];
}

// FIX: Helper function to safely display values
function safeDisplay($value, $default = 'Not specified') {
    return htmlspecialchars($value ?? $default);
}

// FIX: Helper function to safely get numeric values
function safeNumber($value, $default = 0) {
    return is_numeric($value) ? (int)$value : $default;
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-user-tie me-2 text-primary"></i>
        Admin Dashboard - <?php echo safeDisplay($hospital_info['hospital_name']); ?>
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="staff.php" class="btn btn-primary">
                <i class="fas fa-users me-1"></i>Manage Staff
            </a>
            <a href="children.php" class="btn btn-info">
                <i class="fas fa-baby me-1"></i>Children
            </a>
            <a href="vaccines.php" class="btn btn-warning">
                <i class="fas fa-syringe me-1"></i>Vaccines
            </a>
            <a href="reports.php" class="btn btn-success">
                <i class="fas fa-chart-bar me-1"></i>Reports
            </a>
        </div>
    </div>
</div>

<!-- Hospital Overview Stats -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6">
        <div class="card text-center border-primary">
            <div class="card-body">
                <i class="fas fa-users fa-3x text-primary mb-3"></i>
                <h3 class="text-primary"><?php echo safeNumber($hospital_stats['total_staff']); ?></h3>
                <p class="card-text">Total Staff</p>
                <small class="text-muted"><?php echo safeNumber($hospital_stats['doctors_count']); ?> doctors, <?php echo safeNumber($hospital_stats['nurses_count']); ?> nurses</small>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="card text-center border-success">
            <div class="card-body">
                <i class="fas fa-baby fa-3x text-success mb-3"></i>
                <h3 class="text-success"><?php echo safeNumber($hospital_stats['total_children']); ?></h3>
                <p class="card-text">Registered Children</p>
                <small class="text-muted"><?php echo safeNumber($hospital_stats['children_today']); ?> today, <?php echo safeNumber($hospital_stats['children_this_week']); ?> this week</small>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="card text-center border-info">
            <div class="card-body">
                <i class="fas fa-syringe fa-3x text-info mb-3"></i>
                <h3 class="text-info"><?php echo safeNumber($vaccine_stats['completed_vaccines']); ?></h3>
                <p class="card-text">Vaccinations Given</p>
                <small class="text-muted"><?php echo safeNumber($vaccine_stats['vaccines_today']); ?> administered today</small>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="card text-center border-warning">
            <div class="card-body">
                <i class="fas fa-clock fa-3x text-warning mb-3"></i>
                <h3 class="text-warning"><?php echo safeNumber($vaccine_stats['due_vaccines']); ?></h3>
                <p class="card-text">Due Vaccines</p>
                <small class="text-danger"><?php echo safeNumber($vaccine_stats['overdue_vaccines']); ?> overdue</small>
            </div>
        </div>
    </div>
</div>

<!-- Urgent Actions Alert -->
<?php if (safeNumber($urgent_actions['critical_overdue']) > 0 || safeNumber($urgent_actions['due_today']) > 5 || safeNumber($urgent_actions['inactive_staff']) > 0): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="alert alert-danger">
            <h5><i class="fas fa-exclamation-triangle me-2"></i>Urgent Actions Required</h5>
            <div class="row">
                <div class="col-md-4">
                    <?php if (safeNumber($urgent_actions['critical_overdue']) > 0): ?>
                        <p class="mb-1"><strong><?php echo safeNumber($urgent_actions['critical_overdue']); ?></strong> children have vaccines overdue by more than 7 days.</p>
                    <?php endif; ?>
                </div>
                <div class="col-md-4">
                    <?php if (safeNumber($urgent_actions['due_today']) > 5): ?>
                        <p class="mb-1"><strong><?php echo safeNumber($urgent_actions['due_today']); ?></strong> children have vaccines due today.</p>
                    <?php endif; ?>
                </div>
                <div class="col-md-4">
                    <?php if (safeNumber($urgent_actions['inactive_staff']) > 0): ?>
                        <p class="mb-1"><strong><?php echo safeNumber($urgent_actions['inactive_staff']); ?></strong> staff members are inactive.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Main Dashboard Content -->
<div class="row">
    <!-- Staff Performance -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-users me-2"></i>Staff Performance Overview</h5>
            </div>
            <div class="card-body">
                <?php if (empty($staff_performance)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No Staff Members Found</h5>
                        <p class="text-muted">Start by adding staff members to your hospital.</p>
                        <a href="staff.php?action=add" class="btn btn-primary">
                            <i class="fas fa-user-plus me-1"></i>Add Staff Member
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Staff Member</th>
                                    <th>Role</th>
                                    <th>Children Registered</th>
                                    <th>Vaccines Given</th>
                                    <th>Account Created</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($staff_performance as $staff): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo safeDisplay($staff['full_name']); ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo ($staff['role'] ?? '') == 'doctor' ? 'primary' : 'success'; ?>">
                                            <?php echo ucfirst(safeDisplay($staff['role'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (($staff['role'] ?? '') == 'nurse'): ?>
                                            <span class="badge bg-info"><?php echo safeNumber($staff['children_registered']); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (($staff['role'] ?? '') == 'nurse'): ?>
                                            <span class="badge bg-success"><?php echo safeNumber($staff['vaccines_administered']); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($staff['created_at'])): ?>
                                            <?php 
                                            try {
                                                $created = new DateTime($staff['created_at']);
                                                echo '<span class="text-muted">' . $created->format('M j, Y') . '</span>';
                                            } catch (Exception $e) {
                                                echo '<span class="text-muted">Unknown</span>';
                                            }
                                            ?>
                                        <?php else: ?>
                                            <span class="text-muted">Unknown</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($staff['is_active'])): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="staff.php?action=edit&id=<?php echo safeNumber($staff['user_id']); ?>" class="btn btn-outline-primary">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="staff_details.php?id=<?php echo safeNumber($staff['user_id']); ?>" class="btn btn-outline-info">
                                                <i class="fas fa-eye"></i>
                                            </a>
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
        
        <!-- Vaccination Trends Chart -->
        <div class="card mt-3">
            <div class="card-header">
                <h5><i class="fas fa-chart-line me-2"></i>Vaccination Trends (Last 6 Months)</h5>
            </div>
            <div class="card-body">
                <?php if (empty($vaccination_trends)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No vaccination data available for chart display.</p>
                    </div>
                <?php else: ?>
                    <canvas id="trendsChart" height="100"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Sidebar Content -->
    <div class="col-lg-4">
        <!-- Hospital Information -->
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5><i class="fas fa-hospital me-2"></i>Hospital Information</h5>
            </div>
            <div class="card-body">
                <p><strong>Name:</strong> <?php echo safeDisplay($hospital_info['hospital_name']); ?></p>
                <p><strong>Address:</strong> <?php echo safeDisplay($hospital_info['address']); ?></p>
                <p><strong>Phone:</strong> <?php echo safeDisplay($hospital_info['phone']); ?></p>
                <p><strong>Email:</strong> <?php echo safeDisplay($hospital_info['email']); ?></p>
                <p><strong>Established:</strong> 
                    <?php 
                    try {
                        echo date('M j, Y', strtotime($hospital_info['created_at'] ?? 'now'));
                    } catch (Exception $e) {
                        echo 'Unknown';
                    }
                    ?>
                </p>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="card mt-3">
            <div class="card-header bg-primary text-white">
                <h5><i class="fas fa-tools me-2"></i>Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="staff.php?action=add" class="btn btn-outline-primary">
                        <i class="fas fa-user-plus me-2"></i>Add New Staff
                    </a>
                    <a href="vaccines.php?action=add" class="btn btn-outline-success">
                        <i class="fas fa-plus me-2"></i>Add Vaccine
                    </a>
                    <a href="children.php" class="btn btn-outline-info">
                        <i class="fas fa-search me-2"></i>Search Children
                    </a>
                    <a href="reports.php?type=vaccination_coverage" class="btn btn-outline-warning">
                        <i class="fas fa-chart-pie me-2"></i>Coverage Report
                    </a>
                </div>
                
                <hr>
                
                <h6 class="text-muted">Communication</h6>
                <div class="d-grid gap-2">
                    <button class="btn btn-outline-secondary btn-sm" onclick="sendBulkReminders()">
                        <i class="fas fa-paper-plane me-1"></i>Send Bulk Reminders
                    </button>
                    <button class="btn btn-outline-secondary btn-sm" onclick="exportData()">
                        <i class="fas fa-download me-1"></i>Export Hospital Data
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Recent Activities -->
        <div class="card mt-3">
            <div class="card-header">
                <h5><i class="fas fa-clock me-2"></i>Recent Hospital Activities</h5>
            </div>
            <div class="card-body">
                <?php if (empty($recent_activities)): ?>
                    <p class="text-muted text-center">No recent activities</p>
                <?php else: ?>
                    <div class="list-group list-group-flush" style="max-height: 300px; overflow-y: auto;">
                        <?php foreach (array_slice($recent_activities, 0, 10) as $activity): ?>
                        <div class="list-group-item px-0 py-2">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <?php if (($activity['activity_type'] ?? '') == 'registration'): ?>
                                        <i class="fas fa-baby text-success me-1"></i>
                                        <small>Registered:</small> <?php echo safeDisplay($activity['description']); ?>
                                    <?php else: ?>
                                        <i class="fas fa-syringe text-primary me-1"></i>
                                        <small>Vaccinated:</small> <?php echo safeDisplay($activity['description']); ?>
                                    <?php endif; ?>
                                    <br>
                                    <small class="text-muted">By: <?php echo safeDisplay($activity['staff_name'], 'Unknown'); ?></small>
                                </div>
                                <small class="text-muted">
                                    <?php 
                                    try {
                                        echo date('M j', strtotime($activity['activity_date'] ?? 'now'));
                                    } catch (Exception $e) {
                                        echo 'N/A';
                                    }
                                    ?>
                                </small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (count($recent_activities) > 10): ?>
                        <div class="text-center mt-2">
                            <a href="activities.php" class="btn btn-sm btn-outline-secondary">View All Activities</a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Performance Summary -->
        <div class="card mt-3">
            <div class="card-header">
                <h5><i class="fas fa-trophy me-2"></i>Performance Summary</h5>
            </div>
            <div class="card-body">
                <?php
                $completed_vaccines = safeNumber($vaccine_stats['completed_vaccines']);
                $due_vaccines = safeNumber($vaccine_stats['due_vaccines']);
                $total_scheduled = $completed_vaccines + $due_vaccines;
                $coverage_rate = $total_scheduled > 0 ? round(($completed_vaccines / $total_scheduled) * 100, 1) : 0;
                ?>
                <div class="row text-center">
                    <div class="col-6">
                        <div class="border-end">
                            <h4 class="<?php echo $coverage_rate >= 80 ? 'text-success' : ($coverage_rate >= 60 ? 'text-warning' : 'text-danger'); ?>">
                                <?php echo $coverage_rate; ?>%
                            </h4>
                            <small class="text-muted">Vaccination Coverage</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <?php
                        $total_staff = safeNumber($hospital_stats['total_staff']);
                        $inactive_staff = safeNumber($urgent_actions['inactive_staff']);
                        $active_staff_rate = $total_staff > 0 ? 
                            round((($total_staff - $inactive_staff) / $total_staff) * 100, 1) : 0;
                        ?>
                        <h4 class="<?php echo $active_staff_rate >= 90 ? 'text-success' : ($active_staff_rate >= 70 ? 'text-warning' : 'text-danger'); ?>">
                            <?php echo $active_staff_rate; ?>%
                        </h4>
                        <small class="text-muted">Active Staff</small>
                    </div>
                </div>
                <hr>
                <div class="text-center">
                    <small class="text-muted">
                        Hospital Performance Rating: 
                        <?php
                        $overall_rating = ($coverage_rate + $active_staff_rate) / 2;
                        if ($overall_rating >= 85) {
                            echo '<span class="badge bg-success">Excellent</span>';
                        } elseif ($overall_rating >= 70) {
                            echo '<span class="badge bg-warning">Good</span>';
                        } elseif ($overall_rating >= 50) {
                            echo '<span class="badge bg-warning">Fair</span>';
                        } else {
                            echo '<span class="badge bg-danger">Needs Improvement</span>';
                        }
                        ?>
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($vaccination_trends)): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
<script>
// Vaccination Trends Chart
const trendsCtx = document.getElementById('trendsChart').getContext('2d');
const trendsChart = new Chart(trendsCtx, {
    type: 'line',
    data: {
        labels: [
            <?php foreach ($vaccination_trends as $trend): ?>
                '<?php echo date('M Y', strtotime(($trend['month'] ?? '2024-01') . '-01')); ?>',
            <?php endforeach; ?>
        ],
        datasets: [{
            label: 'Vaccinations',
            data: [
                <?php foreach ($vaccination_trends as $trend): ?>
                    <?php echo safeNumber($trend['vaccinations']); ?>,
                <?php endforeach; ?>
            ],
            borderColor: '#007bff',
            backgroundColor: 'rgba(0, 123, 255, 0.1)',
            borderWidth: 2,
            fill: true,
            tension: 0.4
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
                beginAtZero: true
            }
        }
    }
});
</script>
<?php endif; ?>

<script>
function sendBulkReminders() {
    if (confirm('Send vaccination reminders to all parents with pending vaccines?')) {
        fetch('ajax/send_bulk_reminders.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({hospital_id: <?php echo safeNumber($hospital_id); ?>})
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(`Successfully sent ${data.count} reminders.`);
            } else {
                alert('Error sending reminders: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error sending reminders. Please try again.');
        });
    }
}

function exportData() {
    window.location.href = 'export.php?type=hospital_data&hospital_id=<?php echo safeNumber($hospital_id); ?>';
}

// Auto-refresh dashboard every 10 minutes
setTimeout(function() {
    location.reload();
}, 600000);
</script>

<?php require_once '../includes/footer.php'; ?>