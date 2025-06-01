<?php
$page_title = 'Nursing Reports';
require_once '../includes/header.php';

$session->requireRole('nurse');

$database = new Database();
$db = $database->getConnection();
$current_user = $session->getCurrentUser();
$hospital_id = $current_user['hospital_id'];

// Date filters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// My registrations summary
$my_registrations_query = "SELECT 
    COUNT(*) as total_registered,
    COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as registered_today,
    COUNT(CASE WHEN WEEK(created_at) = WEEK(CURDATE()) THEN 1 END) as registered_this_week,
    COUNT(CASE WHEN MONTH(created_at) = MONTH(CURDATE()) THEN 1 END) as registered_this_month
    FROM children 
    WHERE registered_by = :user_id 
    AND created_at BETWEEN :start_date AND :end_date";

$my_registrations_stmt = $db->prepare($my_registrations_query);
$my_registrations_stmt->bindParam(':user_id', $current_user['user_id']);
$my_registrations_stmt->bindParam(':start_date', $start_date);
$my_registrations_stmt->bindParam(':end_date', $end_date);
$my_registrations_stmt->execute();
$my_registrations = $my_registrations_stmt->fetch(PDO::FETCH_ASSOC);

// Vaccination administration summary
$my_vaccinations_query = "SELECT 
    COUNT(*) as total_administered,
    COUNT(CASE WHEN DATE(administered_date) = CURDATE() THEN 1 END) as administered_today,
    COUNT(CASE WHEN WEEK(administered_date) = WEEK(CURDATE()) THEN 1 END) as administered_this_week,
    COUNT(CASE WHEN MONTH(administered_date) = MONTH(CURDATE()) THEN 1 END) as administered_this_month
    FROM vaccination_schedule vs
    JOIN children c ON vs.child_id = c.child_id
    WHERE vs.administered_by = :user_id 
    AND c.hospital_id = :hospital_id
    AND vs.administered_date BETWEEN :start_date AND :end_date";

$my_vaccinations_stmt = $db->prepare($my_vaccinations_query);
$my_vaccinations_stmt->bindParam(':user_id', $current_user['user_id']);
$my_vaccinations_stmt->bindParam(':hospital_id', $hospital_id);
$my_vaccinations_stmt->bindParam(':start_date', $start_date);
$my_vaccinations_stmt->bindParam(':end_date', $end_date);
$my_vaccinations_stmt->execute();
$my_vaccinations = $my_vaccinations_stmt->fetch(PDO::FETCH_ASSOC);

// Current vaccination status for my hospital
$vaccination_status_query = "SELECT 
    COUNT(vs.schedule_id) as total_scheduled,
    COUNT(CASE WHEN vs.status = 'completed' THEN 1 END) as completed,
    COUNT(CASE WHEN vs.status = 'pending' AND vs.scheduled_date = CURDATE() THEN 1 END) as due_today,
    COUNT(CASE WHEN vs.status = 'pending' AND vs.scheduled_date < CURDATE() THEN 1 END) as overdue,
    COUNT(CASE WHEN vs.status = 'pending' AND vs.scheduled_date > CURDATE() THEN 1 END) as upcoming
    FROM vaccination_schedule vs
    JOIN children c ON vs.child_id = c.child_id
    WHERE c.hospital_id = :hospital_id";

$vaccination_status_stmt = $db->prepare($vaccination_status_query);
$vaccination_status_stmt->bindParam(':hospital_id', $hospital_id);
$vaccination_status_stmt->execute();
$vaccination_status = $vaccination_status_stmt->fetch(PDO::FETCH_ASSOC);

// My recent activities
$recent_activities_query = "SELECT 
    'registration' as activity_type,
    c.child_name,
    c.registration_number,
    c.created_at as activity_date,
    'Registered new child' as description
    FROM children c
    WHERE c.registered_by = :user_id AND c.hospital_id = :hospital_id
    
    UNION ALL
    
    SELECT 
    'vaccination' as activity_type,
    c.child_name,
    c.registration_number,
    vs.administered_date as activity_date,
    CONCAT('Administered ', v.vaccine_name) as description
    FROM vaccination_schedule vs
    JOIN children c ON vs.child_id = c.child_id
    JOIN vaccines v ON vs.vaccine_id = v.vaccine_id
    WHERE vs.administered_by = :user_id AND c.hospital_id = :hospital_id
    
    ORDER BY activity_date DESC
    LIMIT 15";

$recent_activities_stmt = $db->prepare($recent_activities_query);
$recent_activities_stmt->bindParam(':user_id', $current_user['user_id']);
$recent_activities_stmt->bindParam(':hospital_id', $hospital_id);
$recent_activities_stmt->execute();
$recent_activities = $recent_activities_stmt->fetchAll(PDO::FETCH_ASSOC);

// Children needing immediate attention
$urgent_children_query = "SELECT 
    c.child_id,
    c.child_name,
    c.registration_number,
    c.parent_name,
    c.parent_phone,
    COUNT(CASE WHEN vs.status = 'pending' AND vs.scheduled_date < CURDATE() THEN 1 END) as overdue_count,
    COUNT(CASE WHEN vs.status = 'pending' AND vs.scheduled_date = CURDATE() THEN 1 END) as due_today_count,
    MIN(CASE WHEN vs.status = 'pending' AND vs.scheduled_date < CURDATE() THEN vs.scheduled_date END) as earliest_overdue
    FROM children c
    LEFT JOIN vaccination_schedule vs ON c.child_id = vs.child_id
    WHERE c.hospital_id = :hospital_id
    GROUP BY c.child_id
    HAVING overdue_count > 0 OR due_today_count > 0
    ORDER BY earliest_overdue ASC, due_today_count DESC
    LIMIT 20";

$urgent_children_stmt = $db->prepare($urgent_children_query);
$urgent_children_stmt->bindParam(':hospital_id', $hospital_id);
$urgent_children_stmt->execute();
$urgent_children = $urgent_children_stmt->fetchAll(PDO::FETCH_ASSOC);

// Monthly vaccination trends
$monthly_trends_query = "SELECT 
    DATE_FORMAT(vs.administered_date, '%Y-%m') as month,
    COUNT(*) as vaccinations_given
    FROM vaccination_schedule vs
    JOIN children c ON vs.child_id = c.child_id
    WHERE c.hospital_id = :hospital_id 
    AND vs.administered_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    AND vs.status = 'completed'
    GROUP BY DATE_FORMAT(vs.administered_date, '%Y-%m')
    ORDER BY month";

$monthly_trends_stmt = $db->prepare($monthly_trends_query);
$monthly_trends_stmt->bindParam(':hospital_id', $hospital_id);
$monthly_trends_stmt->execute();
$monthly_trends = $monthly_trends_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate coverage percentage
$coverage_percentage = $vaccination_status['total_scheduled'] > 0 ? 
    round(($vaccination_status['completed'] / $vaccination_status['total_scheduled']) * 100, 1) : 0;
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Nursing Reports</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <button onclick="window.print()" class="btn btn-outline-secondary me-2">
            <i class="fas fa-print me-1"></i>Print Report
        </button>
        <button onclick="exportMyData()" class="btn btn-primary">
            <i class="fas fa-download me-1"></i>Export My Data
        </button>
    </div>
</div>

<!-- Date Filter -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label for="start_date" class="form-label">Start Date</label>
                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
            </div>
            <div class="col-md-4">
                <label for="end_date" class="form-label">End Date</label>
                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter me-1"></i>Apply Filter
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- My Performance Summary -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5><i class="fas fa-user-plus me-2"></i>My Registrations</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6">
                        <h3 class="text-primary"><?php echo $my_registrations['total_registered']; ?></h3>
                        <p class="mb-0">Total Registered</p>
                        <small class="text-muted">In selected period</small>
                    </div>
                    <div class="col-6">
                        <h3 class="text-success"><?php echo $my_registrations['registered_this_month']; ?></h3>
                        <p class="mb-0">This Month</p>
                        <small class="text-muted"><?php echo $my_registrations['registered_this_week']; ?> this week</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5><i class="fas fa-syringe me-2"></i>My Vaccinations</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6">
                        <h3 class="text-success"><?php echo $my_vaccinations['total_administered']; ?></h3>
                        <p class="mb-0">Total Administered</p>
                        <small class="text-muted">In selected period</small>
                    </div>
                    <div class="col-6">
                        <h3 class="text-info"><?php echo $my_vaccinations['administered_this_month']; ?></h3>
                        <p class="mb-0">This Month</p>
                        <small class="text-muted"><?php echo $my_vaccinations['administered_this_week']; ?> this week</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Hospital Vaccination Status -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-chart-pie fa-2x text-success mb-2"></i>
                <h4 class="text-success"><?php echo $coverage_percentage; ?>%</h4>
                <p class="card-text">Coverage Rate</p>
                <small class="text-muted"><?php echo $vaccination_status['completed']; ?>/<?php echo $vaccination_status['total_scheduled']; ?> completed</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-calendar-day fa-2x text-warning mb-2"></i>
                <h4 class="text-warning"><?php echo $vaccination_status['due_today']; ?></h4>
                <p class="card-text">Due Today</p>
                <small class="text-muted">Need attention</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-exclamation-triangle fa-2x text-danger mb-2"></i>
                <h4 class="text-danger"><?php echo $vaccination_status['overdue']; ?></h4>
                <p class="card-text">Overdue</p>
                <small class="text-muted">Urgent action needed</small>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-chart-line me-2"></i>Monthly Vaccination Trends (Last 12 Months)</h5>
            </div>
            <div class="card-body">
                <canvas id="monthlyTrendsChart" width="800" height="300"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Children Needing Immediate Attention -->
<div class="row mb-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header bg-warning text-dark">
                <h5><i class="fas fa-exclamation-triangle me-2"></i>Children Needing Immediate Attention</h5>
            </div>
            <div class="card-body">
                <?php if (empty($urgent_children)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                        <h5 class="text-success">All children are up to date!</h5>
                        <p class="text-muted">No urgent vaccinations needed at this time.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>Child</th>
                                    <th>Parent Contact</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($urgent_children as $child): ?>
                                <tr class="<?php echo $child['overdue_count'] > 0 ? 'table-danger' : 'table-warning'; ?>">
                                    <td>
                                        <strong><?php echo htmlspecialchars($child['child_name']); ?></strong><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($child['registration_number']); ?></small>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($child['parent_name']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($child['parent_phone']); ?></small>
                                    </td>
                                    <td>
                                        <?php if ($child['overdue_count'] > 0): ?>
                                            <span class="badge bg-danger"><?php echo $child['overdue_count']; ?> overdue</span>
                                            <?php if ($child['earliest_overdue']): ?>
                                                <br><small class="text-muted">Since <?php echo $utils->formatDate($child['earliest_overdue']); ?></small>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        
                                        <?php if ($child['due_today_count'] > 0): ?>
                                            <span class="badge bg-warning"><?php echo $child['due_today_count']; ?> due today</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="vaccination.php?child_id=<?php echo $child['child_id']; ?>" class="btn btn-outline-success">
                                                <i class="fas fa-syringe"></i>
                                            </a>
                                            <button class="btn btn-outline-primary" onclick="sendUrgentReminder(<?php echo $child['child_id']; ?>)">
                                                <i class="fas fa-paper-plane"></i>
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
    </div>
    
    <!-- My Recent Activities -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-history me-2"></i>My Recent Activities</h5>
            </div>
            <div class="card-body">
                <?php if (empty($recent_activities)): ?>
                    <div class="text-center py-3">
                        <i class="fas fa-clipboard-list fa-2x text-muted mb-2"></i>
                        <p class="text-muted">No recent activities</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recent_activities as $activity): ?>
                        <div class="list-group-item px-0">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <small class="text-muted">
                                        <i class="fas fa-<?php echo $activity['activity_type'] == 'registration' ? 'user-plus' : 'syringe'; ?> me-1"></i>
                                        <?php echo $utils->formatDate($activity['activity_date']); ?>
                                    </small>
                                    <div class="fw-bold"><?php echo htmlspecialchars($activity['child_name']); ?></div>
                                    <small><?php echo htmlspecialchars($activity['description']); ?></small>
                                </div>
                                <span class="badge bg-<?php echo $activity['activity_type'] == 'registration' ? 'primary' : 'success'; ?>">
                                    <?php echo ucfirst($activity['activity_type']); ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Quick Action Summary -->
<div class="card">
    <div class="card-header">
        <h5><i class="fas fa-tasks me-2"></i>Quick Actions Summary</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-3 text-center">
                <a href="register_child.php" class="btn btn-primary btn-lg w-100 mb-2">
                    <i class="fas fa-plus fa-2x mb-2"></i><br>
                    Register New Child
                </a>
                <small class="text-muted">Add new children to the system</small>
            </div>
            <div class="col-md-3 text-center">
                <a href="vaccination.php" class="btn btn-success btn-lg w-100 mb-2">
                    <i class="fas fa-syringe fa-2x mb-2"></i><br>
                    Record Vaccinations
                </a>
                <small class="text-muted">Administer and record vaccines</small>
            </div>
            <div class="col-md-3 text-center">
                <a href="children_list.php?status_filter=due_today" class="btn btn-warning btn-lg w-100 mb-2">
                    <i class="fas fa-calendar-day fa-2x mb-2"></i><br>
                    Due Today (<?php echo $vaccination_status['due_today']; ?>)
                </a>
                <small class="text-muted">Children with vaccines due today</small>
            </div>
            <div class="col-md-3 text-center">
                <a href="children_list.php?status_filter=overdue" class="btn btn-danger btn-lg w-100 mb-2">
                    <i class="fas fa-exclamation-triangle fa-2x mb-2"></i><br>
                    Overdue (<?php echo $vaccination_status['overdue']; ?>)
                </a>
                <small class="text-muted">Children with overdue vaccines</small>
            </div>
        </div>
    </div>
</div>

<?php
$extra_scripts = '
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
<script>
// Monthly Trends Chart
const monthlyCtx = document.getElementById("monthlyTrendsChart").getContext("2d");
new Chart(monthlyCtx, {
    type: "line",
    data: {
        labels: [' . implode(',', array_map(function($mt) { return '"' . date('M Y', strtotime($mt['month'] . '-01')) . '"'; }, $monthly_trends)) . '],
        datasets: [{
            label: "Vaccinations Given",
            data: [' . implode(',', array_column($monthly_trends, 'vaccinations_given')) . '],
            borderColor: "rgb(40, 167, 69)",
            backgroundColor: "rgba(40, 167, 69, 0.1)",
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        }
    }
});

function sendUrgentReminder(childId) {
    if (confirm("Send urgent vaccination reminder to parent?")) {
        fetch("../api/send_reminder.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify({child_id: childId, urgent: true})
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert("Urgent reminder sent successfully!");
            } else {
                alert("Failed to send reminder: " + (data.message || "Unknown error"));
            }
        })
        .catch(error => {
            alert("Error sending reminder");
        });
    }
}

function exportMyData() {
    const reportData = {
        nurse: "' . htmlspecialchars($current_user['full_name']) . '",
        hospital: "' . htmlspecialchars($current_user['hospital_name']) . '",
        period: "' . $start_date . ' to ' . $end_date . '",
        registrations: ' . json_encode($my_registrations) . ',
        vaccinations: ' . json_encode($my_vaccinations) . ',
        activities: ' . json_encode($recent_activities) . '
    };
    
    const dataStr = JSON.stringify(reportData, null, 2);
    const dataBlob = new Blob([dataStr], {type: "application/json"});
    const url = URL.createObjectURL(dataBlob);
    const link = document.createElement("a");
    link.href = url;
    link.download = "my_nursing_report_" + new Date().toISOString().split("T")[0] + ".json";
    link.click();
    URL.revokeObjectURL(url);
}
</script>';

require_once '../includes/footer.php';
?>
