<?php
$page_title = 'Growth Charts';
require_once '../includes/header.php';
require_once '../includes/functions.php';

$session->requireRole('doctor');

$database = new Database();
$db = $database->getConnection();
$current_user = $session->getCurrentUser();
$hospital_id = $current_user['hospital_id'];

// Get selected child if any
$selected_child_id = isset($_GET['child_id']) ? $_GET['child_id'] : '';

// Get children for selection
$children_query = "SELECT child_id, child_name, registration_number, date_of_birth, gender 
                   FROM children WHERE hospital_id = :hospital_id ORDER BY child_name";
$children_stmt = $db->prepare($children_query);
$children_stmt->bindParam(':hospital_id', $hospital_id);
$children_stmt->execute();
$children = $children_stmt->fetchAll(PDO::FETCH_ASSOC);

$selected_child = null;
$growth_data = [];
$latest_measurements = null;

if (!empty($selected_child_id)) {
    // Get selected child details
    $child_query = "SELECT * FROM children WHERE child_id = :child_id AND hospital_id = :hospital_id";
    $child_stmt = $db->prepare($child_query);
    $child_stmt->bindParam(':child_id', $selected_child_id);
    $child_stmt->bindParam(':hospital_id', $hospital_id);
    $child_stmt->execute();
    $selected_child = $child_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($selected_child) {
        // Get growth data
        $growth_query = "SELECT weight_kg, height_cm, age_months, visit_date, temperature, notes,
                        u.full_name as recorded_by_name
                        FROM child_medical_records cmr
                        JOIN users u ON cmr.recorded_by = u.user_id
                        WHERE cmr.child_id = :child_id 
                        ORDER BY cmr.visit_date ASC";
        $growth_stmt = $db->prepare($growth_query);
        $growth_stmt->bindParam(':child_id', $selected_child_id);
        $growth_stmt->execute();
        $growth_data = $growth_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get latest measurements
        if (!empty($growth_data)) {
            $latest_measurements = end($growth_data);
        }
    }
}

// Calculate WHO growth percentiles (simplified)
function calculatePercentile($value, $age_months, $gender, $measurement) {
    // Simplified percentile calculation - in real implementation, use WHO growth charts
    $percentiles = [
        'weight' => [
            'male' => [3 => 5.8, 6 => 7.9, 9 => 9.2, 12 => 10.2, 18 => 11.8, 24 => 13.0],
            'female' => [3 => 5.4, 6 => 7.3, 9 => 8.6, 12 => 9.5, 18 => 11.0, 24 => 12.3]
        ],
        'height' => [
            'male' => [3 => 61.4, 6 => 67.6, 9 => 72.0, 12 => 75.7, 18 => 82.3, 24 => 87.1],
            'female' => [3 => 59.8, 6 => 65.7, 9 => 70.1, 12 => 74.0, 18 => 80.7, 24 => 85.7]
        ]
    ];
    
    if (!isset($percentiles[$measurement][$gender])) return 50;
    
    $chart = $percentiles[$measurement][$gender];
    $closest_age = null;
    $min_diff = PHP_INT_MAX;
    
    foreach ($chart as $age => $median) {
        $diff = abs($age - $age_months);
        if ($diff < $min_diff) {
            $min_diff = $diff;
            $closest_age = $age;
        }
    }
    
    if ($closest_age === null) return 50;
    
    $median = $chart[$closest_age];
    $percentile = (($value / $median) - 1) * 50 + 50;
    
    return max(1, min(99, round($percentile)));
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Growth Charts</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="medical_records.php<?php echo $selected_child_id ? '?child_id=' . $selected_child_id : ''; ?>" class="btn btn-primary me-2">
            <i class="fas fa-plus me-1"></i>Add Medical Record
        </a>
        <a href="children.php" class="btn btn-outline-secondary">
            <i class="fas fa-list me-1"></i>View All Children
        </a>
    </div>
</div>

<!-- Child Selection -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-8">
                <label for="child_id" class="form-label">Select Child</label>
                <select class="form-select" id="child_id" name="child_id" onchange="this.form.submit()">
                    <option value="">Choose a child to view growth charts...</option>
                    <?php foreach ($children as $child): ?>
                    <option value="<?php echo $child['child_id']; ?>" <?php echo $child['child_id'] == $selected_child_id ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($child['child_name']); ?> 
                        (<?php echo htmlspecialchars($child['registration_number']); ?>) - 
                        <?php echo $utils->getChildAgeInMonths($child['date_of_birth']); ?> months
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <?php if ($selected_child): ?>
                <button type="button" class="btn btn-outline-primary w-100" onclick="window.print()">
                    <i class="fas fa-print me-1"></i>Print Charts
                </button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php if ($selected_child): ?>
<!-- Child Information -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row">
            <div class="col-md-8">
                <h5><i class="fas fa-baby me-2"></i>Child Information</h5>
                <div class="row">
                    <div class="col-sm-6">
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($selected_child['child_name']); ?></p>
                        <p><strong>Registration:</strong> <?php echo htmlspecialchars($selected_child['registration_number']); ?></p>
                        <p><strong>Date of Birth:</strong> <?php echo $utils->formatDate($selected_child['date_of_birth']); ?></p>
                    </div>
                    <div class="col-sm-6">
                        <p><strong>Age:</strong> <?php echo $utils->getChildAgeInMonths($selected_child['date_of_birth']); ?> months</p>
                        <p><strong>Gender:</strong> <?php echo ucfirst($selected_child['gender']); ?></p>
                        <p><strong>Total Visits:</strong> <?php echo count($growth_data); ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <?php if ($latest_measurements): ?>
                <h6>Latest Measurements</h6>
                <div class="row text-center">
                    <?php if ($latest_measurements['weight_kg']): ?>
                    <div class="col-6">
                        <div class="bg-light p-2 rounded">
                            <small class="text-muted">Weight</small>
                            <div><strong><?php echo $latest_measurements['weight_kg']; ?> kg</strong></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ($latest_measurements['height_cm']): ?>
                    <div class="col-6">
                        <div class="bg-light p-2 rounded">
                            <small class="text-muted">Height</small>
                            <div><strong><?php echo $latest_measurements['height_cm']; ?> cm</strong></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <small class="text-muted">
                    Last visit: <?php echo $utils->formatDate($latest_measurements['visit_date']); ?>
                </small>
                <?php else: ?>
                <div class="text-center py-3">
                    <i class="fas fa-chart-line fa-2x text-muted mb-2"></i>
                    <p class="text-muted">No measurements recorded yet</p>
                    <a href="medical_records.php?child_id=<?php echo $selected_child_id; ?>" class="btn btn-sm btn-primary">
                        Add First Record
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($growth_data)): ?>
<!-- Growth Charts -->
<div class="row mb-4">
    <!-- Weight Chart -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-weight me-2"></i>Weight Progress</h5>
            </div>
            <div class="card-body">
                <canvas id="weightChart" width="400" height="300"></canvas>
                <?php if ($latest_measurements && $latest_measurements['weight_kg']): ?>
                <div class="mt-3 p-2 bg-light rounded">
                    <small class="text-muted">Current weight percentile: </small>
                    <strong>
                        <?php echo calculatePercentile($latest_measurements['weight_kg'], $latest_measurements['age_months'], $selected_child['gender'], 'weight'); ?>th percentile
                    </strong>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Height Chart -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-ruler-vertical me-2"></i>Height Progress</h5>
            </div>
            <div class="card-body">
                <canvas id="heightChart" width="400" height="300"></canvas>
                <?php if ($latest_measurements && $latest_measurements['height_cm']): ?>
                <div class="mt-3 p-2 bg-light rounded">
                    <small class="text-muted">Current height percentile: </small>
                    <strong>
                        <?php echo calculatePercentile($latest_measurements['height_cm'], $latest_measurements['age_months'], $selected_child['gender'], 'height'); ?>th percentile
                    </strong>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- BMI and Growth Velocity -->
<div class="row mb-4">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-calculator me-2"></i>BMI Trend</h5>
            </div>
            <div class="card-body">
                <canvas id="bmiChart" width="400" height="300"></canvas>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-chart-area me-2"></i>Growth Summary</h5>
            </div>
            <div class="card-body">
                <?php
                $first_record = reset($growth_data);
                $last_record = end($growth_data);
                
                if ($first_record && $last_record && $first_record !== $last_record) {
                    $weight_gain = $last_record['weight_kg'] - $first_record['weight_kg'];
                    $height_gain = $last_record['height_cm'] - $first_record['height_cm'];
                    $time_span = $last_record['age_months'] - $first_record['age_months'];
                ?>
                <div class="row text-center">
                    <div class="col-6">
                        <div class="p-3 bg-success bg-opacity-10 rounded">
                            <h4 class="text-success"><?php echo number_format($weight_gain, 1); ?> kg</h4>
                            <small class="text-muted">Weight Gain</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 bg-info bg-opacity-10 rounded">
                            <h4 class="text-info"><?php echo number_format($height_gain, 1); ?> cm</h4>
                            <small class="text-muted">Height Gain</small>
                        </div>
                    </div>
                </div>
                
                <hr>
                
                <div class="row text-center">
                    <div class="col-6">
                        <h6><?php echo number_format($weight_gain / max(1, $time_span), 2); ?> kg/month</h6>
                        <small class="text-muted">Average Weight Gain</small>
                    </div>
                    <div class="col-6">
                        <h6><?php echo number_format($height_gain / max(1, $time_span), 2); ?> cm/month</h6>
                        <small class="text-muted">Average Height Gain</small>
                    </div>
                </div>
                
                <hr>
                
                <p><strong>Growth Period:</strong> <?php echo $time_span; ?> months</p>
                <p><strong>Total Visits:</strong> <?php echo count($growth_data); ?></p>
                
                <?php } else { ?>
                <div class="text-center py-4">
                    <i class="fas fa-info-circle fa-2x text-muted mb-2"></i>
                    <p class="text-muted">Need at least 2 measurements to show growth summary</p>
                </div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<!-- Measurements History Table -->
<div class="card">
    <div class="card-header">
        <h5><i class="fas fa-history me-2"></i>Measurements History</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Visit Date</th>
                        <th>Age (months)</th>
                        <th>Weight (kg)</th>
                        <th>Height (cm)</th>
                        <th>BMI</th>
                        <th>Temperature</th>
                        <th>Recorded By</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_reverse($growth_data) as $record): ?>
                    <tr>
                        <td><?php echo $utils->formatDate($record['visit_date']); ?></td>
                        <td><span class="badge bg-info"><?php echo $record['age_months']; ?></span></td>
                        <td>
                            <?php if ($record['weight_kg']): ?>
                                <strong><?php echo $record['weight_kg']; ?></strong>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($record['height_cm']): ?>
                                <strong><?php echo $record['height_cm']; ?></strong>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($record['weight_kg'] && $record['height_cm']): ?>
                                <?php echo $utils->calculateBMI($record['weight_kg'], $record['height_cm']); ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($record['temperature']): ?>
                                <?php echo $record['temperature']; ?>°C
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <small><?php echo htmlspecialchars($record['recorded_by_name']); ?></small>
                        </td>
                        <td>
                            <?php if ($record['notes']): ?>
                                <small><?php echo htmlspecialchars(substr($record['notes'], 0, 50)) . (strlen($record['notes']) > 50 ? '...' : ''); ?></small>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php else: ?>
<!-- No measurements yet -->
<div class="card">
    <div class="card-body text-center py-5">
        <i class="fas fa-chart-line fa-4x text-muted mb-4"></i>
        <h4 class="text-muted">No measurements recorded yet</h4>
        <p class="text-muted">Start tracking this child's growth by adding their first medical record.</p>
        <a href="medical_records.php?child_id=<?php echo $selected_child_id; ?>" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Add First Medical Record
        </a>
    </div>
</div>
<?php endif; ?>

<?php else: ?>
<!-- No child selected -->
<div class="card">
    <div class="card-body text-center py-5">
        <i class="fas fa-baby fa-4x text-muted mb-4"></i>
        <h4 class="text-muted">Select a child to view growth charts</h4>
        <p class="text-muted">Choose a child from the dropdown above to see their growth progress and charts.</p>
    </div>
</div>
<?php endif; ?>

<?php
if ($selected_child && !empty($growth_data)) {
    // Prepare data for JavaScript charts
    $chart_data = [
        'labels' => array_map(function($record) use ($utils) { 
            return $utils->formatDate($record['visit_date']); 
        }, $growth_data),
        'ages' => array_column($growth_data, 'age_months'),
        'weights' => array_map(function($record) { 
            return $record['weight_kg'] ? floatval($record['weight_kg']) : null; 
        }, $growth_data),
        'heights' => array_map(function($record) { 
            return $record['height_cm'] ? floatval($record['height_cm']) : null; 
        }, $growth_data),
        'bmis' => array_map(function($record) use ($utils) { 
            return ($record['weight_kg'] && $record['height_cm']) ? 
                $utils->calculateBMI($record['weight_kg'], $record['height_cm']) : null; 
        }, $growth_data)
    ];

    $extra_scripts = '
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <script>
    const chartData = ' . json_encode($chart_data) . ';
    
    // Weight Chart
    const weightCtx = document.getElementById("weightChart").getContext("2d");
    new Chart(weightCtx, {
        type: "line",
        data: {
            labels: chartData.labels,
            datasets: [{
                label: "Weight (kg)",
                data: chartData.weights,
                borderColor: "rgb(40, 167, 69)",
                backgroundColor: "rgba(40, 167, 69, 0.1)",
                tension: 0.4,
                fill: true,
                pointRadius: 6,
                pointHoverRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: "Weight (kg)"
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: "Visit Date"
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
    
    // Height Chart
    const heightCtx = document.getElementById("heightChart").getContext("2d");
    new Chart(heightCtx, {
        type: "line",
        data: {
            labels: chartData.labels,
            datasets: [{
                label: "Height (cm)",
                data: chartData.heights,
                borderColor: "rgb(23, 162, 184)",
                backgroundColor: "rgba(23, 162, 184, 0.1)",
                tension: 0.4,
                fill: true,
                pointRadius: 6,
                pointHoverRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: "Height (cm)"
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: "Visit Date"
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
    
    // BMI Chart
    const bmiCtx = document.getElementById("bmiChart").getContext("2d");
    new Chart(bmiCtx, {
        type: "line",
        data: {
            labels: chartData.labels,
            datasets: [{
                label: "BMI",
                data: chartData.bmis,
                borderColor: "rgb(255, 193, 7)",
                backgroundColor: "rgba(255, 193, 7, 0.1)",
                tension: 0.4,
                fill: true,
                pointRadius: 6,
                pointHoverRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: "BMI"
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: "Visit Date"
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
    </script>';
} else {
    $extra_scripts = '';
}

require_once '../includes/footer.php';
?>
