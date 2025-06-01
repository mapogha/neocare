<?php
$page_title = 'Growth Records';
require_once '../includes/header.php';

// Ensure parent is logged in
if (!$session->isParentLoggedIn()) {
    header('Location: ../index.php');
    exit();
}

$child = $session->getCurrentChild();
$child_id = $child['child_id'];

$database = new Database();
$db = $database->getConnection();

// Get child's medical records
$records_query = "SELECT cmr.*, u.full_name as recorded_by_name
                  FROM child_medical_records cmr
                  LEFT JOIN users u ON cmr.recorded_by = u.user_id
                  WHERE cmr.child_id = :child_id
                  ORDER BY cmr.visit_date DESC";

$records_stmt = $db->prepare($records_query);
$records_stmt->bindParam(':child_id', $child_id);
$records_stmt->execute();
$medical_records = $records_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get growth chart data
$growth_data = $utils->getGrowthChartData($child_id);

// Calculate current stats if we have records
$latest_record = !empty($medical_records) ? $medical_records[0] : null;
$first_record = !empty($medical_records) ? end($medical_records) : null;

$growth_summary = null;
if ($latest_record && $first_record && count($medical_records) > 1) {
    $time_span_months = $latest_record['age_months'] - $first_record['age_months'];
    $weight_gain = $latest_record['weight_kg'] - $first_record['weight_kg'];
    $height_gain = $latest_record['height_cm'] - $first_record['height_cm'];
    
    $growth_summary = [
        'time_span' => $time_span_months,
        'weight_gain' => $weight_gain,
        'height_gain' => $height_gain,
        'avg_weight_gain' => $time_span_months > 0 ? $weight_gain / $time_span_months : 0,
        'avg_height_gain' => $time_span_months > 0 ? $height_gain / $time_span_months : 0,
        'total_visits' => count($medical_records)
    ];
}

// Calculate WHO percentiles (simplified)
function calculatePercentile($value, $age_months, $gender, $measurement) {
    // Simplified percentile calculation - in real implementation, use WHO growth charts
    $percentiles = [
        'weight' => [
            'male' => [3 => 5.8, 6 => 7.9, 9 => 9.2, 12 => 10.2, 18 => 11.8, 24 => 13.0, 36 => 15.0],
            'female' => [3 => 5.4, 6 => 7.3, 9 => 8.6, 12 => 9.5, 18 => 11.0, 24 => 12.3, 36 => 14.2]
        ],
        'height' => [
            'male' => [3 => 61.4, 6 => 67.6, 9 => 72.0, 12 => 75.7, 18 => 82.3, 24 => 87.1, 36 => 96.1],
            'female' => [3 => 59.8, 6 => 65.7, 9 => 70.1, 12 => 74.0, 18 => 80.7, 24 => 85.7, 36 => 94.1]
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

<div class="container-fluid">
    <div class="row">
        <main class="col-md-12 px-md-4 main-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Growth Records - <?php echo htmlspecialchars($child['child_name']); ?></h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="../dashboard.php" class="btn btn-outline-secondary me-2">
                        <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                    </a>
                    <a href="vaccination_schedule.php" class="btn btn-outline-primary me-2">
                        <i class="fas fa-syringe me-1"></i>Vaccination Schedule
                    </a>
                    <button onclick="window.print()" class="btn btn-outline-secondary">
                        <i class="fas fa-print me-1"></i>Print Records
                    </button>
                </div>
            </div>

            <!-- Child Information -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <h5><i class="fas fa-baby me-2"></i>Child Information</h5>
                            <div class="row">
                                <div class="col-sm-6">
                                    <p><strong>Name:</strong> <?php echo htmlspecialchars($child['child_name']); ?></p>
                                    <p><strong>Registration Number:</strong> <?php echo htmlspecialchars($child['registration_number']); ?></p>
                                    <p><strong>Date of Birth:</strong> <?php echo $utils->formatDate($child['date_of_birth']); ?></p>
                                </div>
                                <div class="col-sm-6">
                                    <p><strong>Current Age:</strong> <?php echo $utils->getChildAgeInMonths($child['date_of_birth']); ?> months</p>
                                    <p><strong>Gender:</strong> <?php echo ucfirst($child['gender']); ?></p>
                                    <p><strong>Total Medical Visits:</strong> <?php echo count($medical_records); ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <?php if ($latest_record): ?>
                                <h6>Latest Measurements</h6>
                                <div class="row text-center">
                                    <?php if ($latest_record['weight_kg']): ?>
                                    <div class="col-6">
                                        <div class="bg-light p-2 rounded mb-2">
                                            <small class="text-muted">Weight</small>
                                            <div><strong><?php echo $latest_record['weight_kg']; ?> kg</strong></div>
                                            <?php if ($latest_record['age_months']): ?>
                                                <small class="text-info">
                                                    <?php echo calculatePercentile($latest_record['weight_kg'], $latest_record['age_months'], $child['gender'], 'weight'); ?>th percentile
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($latest_record['height_cm']): ?>
                                    <div class="col-6">
                                        <div class="bg-light p-2 rounded mb-2">
                                            <small class="text-muted">Height</small>
                                            <div><strong><?php echo $latest_record['height_cm']; ?> cm</strong></div>
                                            <?php if ($latest_record['age_months']): ?>
                                                <small class="text-info">
                                                    <?php echo calculatePercentile($latest_record['height_cm'], $latest_record['age_months'], $child['gender'], 'height'); ?>th percentile
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <small class="text-muted">
                                    Last visit: <?php echo $utils->formatDate($latest_record['visit_date']); ?>
                                </small>
                            <?php else: ?>
                                <div class="text-center py-3">
                                    <i class="fas fa-chart-line fa-2x text-muted mb-2"></i>
                                    <p class="text-muted">No measurements recorded yet</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!empty($medical_records)): ?>
            <!-- Growth Summary -->
            <?php if ($growth_summary): ?>
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h5><i class="fas fa-chart-area me-2"></i>Growth Summary</h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-6">
                                    <div class="p-3 bg-success bg-opacity-10 rounded">
                                        <h4 class="text-success"><?php echo number_format($growth_summary['weight_gain'], 1); ?> kg</h4>
                                        <small class="text-muted">Weight Gained</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="p-3 bg-info bg-opacity-10 rounded">
                                        <h4 class="text-info"><?php echo number_format($growth_summary['height_gain'], 1); ?> cm</h4>
                                        <small class="text-muted">Height Gained</small>
                                    </div>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <div class="row text-center">
                                <div class="col-6">
                                    <h6><?php echo number_format($growth_summary['avg_weight_gain'], 2); ?> kg/month</h6>
                                    <small class="text-muted">Average Weight Gain</small>
                                </div>
                                <div class="col-6">
                                    <h6><?php echo number_format($growth_summary['avg_height_gain'], 2); ?> cm/month</h6>
                                    <small class="text-muted">Average Height Gain</small>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <p class="mb-1"><strong>Growth Period:</strong> <?php echo $growth_summary['time_span']; ?> months</p>
                            <p class="mb-0"><strong>Total Visits:</strong> <?php echo $growth_summary['total_visits']; ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Latest BMI and Status -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h5><i class="fas fa-calculator me-2"></i>Current Health Status</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($latest_record && $latest_record['weight_kg'] && $latest_record['height_cm']): ?>
                                <?php $current_bmi = $utils->calculateBMI($latest_record['weight_kg'], $latest_record['height_cm']); ?>
                                <div class="text-center mb-3">
                                    <h4 class="text-primary"><?php echo $current_bmi; ?></h4>
                                    <p class="mb-0">Current BMI</p>
                                    <small class="text-muted">Body Mass Index</small>
                                </div>
                                
                                <div class="row text-center">
                                    <div class="col-12">
                                        <?php
                                        $bmi_status = 'Normal';
                                        $bmi_color = 'success';
                                        if ($current_bmi < 18.5) {
                                            $bmi_status = 'Underweight';
                                            $bmi_color = 'warning';
                                        } elseif ($current_bmi > 25) {
                                            $bmi_status = 'Overweight';
                                            $bmi_color = 'warning';
                                        }
                                        ?>
                                        <span class="badge bg-<?php echo $bmi_color; ?> fs-6"><?php echo $bmi_status; ?></span>
                                    </div>
                                </div>
                                
                                <hr>
                                
                                <?php if ($latest_record['temperature']): ?>
                                    <p><strong>Last Temperature:</strong> <?php echo $latest_record['temperature']; ?>°C</p>
                                <?php endif; ?>
                                
                                <p><strong>Age at Last Visit:</strong> <?php echo $latest_record['age_months']; ?> months</p>
                                
                            <?php else: ?>
                                <div class="text-center py-3">
                                    <i class="fas fa-info-circle fa-2x text-muted mb-2"></i>
                                    <p class="text-muted">Insufficient data for BMI calculation</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Growth Charts -->
            <?php if (count($growth_data) >= 2): ?>
            <div class="row mb-4">
                <!-- Weight Chart -->
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-weight me-2"></i>Weight Progress Chart</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="weightChart" width="400" height="300"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- Height Chart -->
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-ruler-vertical me-2"></i>Height Progress Chart</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="heightChart" width="400" height="300"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Medical Records History -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-history me-2"></i>Complete Medical Records History</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($medical_records)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-file-medical fa-4x text-muted mb-4"></i>
                            <h4 class="text-muted">No medical records yet</h4>
                            <p class="text-muted">Medical records will appear here after your child's first doctor visit.</p>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>What to expect:</strong> During each visit, the doctor will record your child's weight, height, and other health measurements.
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="timeline">
                            <?php foreach ($medical_records as $index => $record): ?>
                            <div class="timeline-item">
                                <div class="timeline-marker bg-<?php echo $index == 0 ? 'primary' : 'secondary'; ?>">
                                    <i class="fas fa-<?php echo $index == 0 ? 'star' : 'circle'; ?>"></i>
                                </div>
                                <div class="timeline-content">
                                    <div class="card">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <div>
                                                    <h6 class="card-title mb-1">
                                                        Medical Visit - <?php echo $utils->formatDate($record['visit_date']); ?>
                                                        <?php if ($index == 0): ?>
                                                            <span class="badge bg-primary ms-2">Latest</span>
                                                        <?php endif; ?>
                                                    </h6>
                                                    <small class="text-muted">
                                                        Recorded by: <?php echo htmlspecialchars($record['recorded_by_name'] ?: 'Doctor'); ?>
                                                    </small>
                                                </div>
                                                <span class="badge bg-info"><?php echo $record['age_months']; ?> months old</span>
                                            </div>
                                            
                                            <div class="row">
                                                <!-- Measurements -->
                                                <div class="col-md-6">
                                                    <h6 class="text-muted mb-2">Measurements</h6>
                                                    <div class="row">
                                                        <?php if ($record['weight_kg']): ?>
                                                        <div class="col-6">
                                                            <div class="text-center p-2 bg-light rounded mb-2">
                                                                <i class="fas fa-weight-hanging text-success"></i>
                                                                <div><strong><?php echo $record['weight_kg']; ?> kg</strong></div>
                                                                <small class="text-muted">Weight</small>
                                                                <?php if ($record['age_months']): ?>
                                                                    <br><small class="text-info">
                                                                        <?php echo calculatePercentile($record['weight_kg'], $record['age_months'], $child['gender'], 'weight'); ?>th percentile
                                                                    </small>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($record['height_cm']): ?>
                                                        <div class="col-6">
                                                            <div class="text-center p-2 bg-light rounded mb-2">
                                                                <i class="fas fa-ruler-vertical text-info"></i>
                                                                <div><strong><?php echo $record['height_cm']; ?> cm</strong></div>
                                                                <small class="text-muted">Height</small>
                                                                <?php if ($record['age_months']): ?>
                                                                    <br><small class="text-info">
                                                                        <?php echo calculatePercentile($record['height_cm'], $record['age_months'], $child['gender'], 'height'); ?>th percentile
                                                                    </small>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <?php if ($record['weight_kg'] && $record['height_cm']): ?>
                                                        <div class="text-center p-2 bg-warning bg-opacity-10 rounded">
                                                            <i class="fas fa-calculator text-warning"></i>
                                                            <div><strong><?php echo $utils->calculateBMI($record['weight_kg'], $record['height_cm']); ?></strong></div>
                                                            <small class="text-muted">BMI</small>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <!-- Vital Signs & Notes -->
                                                <div class="col-md-6">
                                                    <?php if ($record['temperature'] || $record['blood_pressure']): ?>
                                                    <h6 class="text-muted mb-2">Vital Signs</h6>
                                                    <?php if ($record['temperature']): ?>
                                                        <p class="mb-1">
                                                            <i class="fas fa-thermometer-half text-danger me-2"></i>
                                                            <strong>Temperature:</strong> <?php echo $record['temperature']; ?>°C
                                                        </p>
                                                    <?php endif; ?>
                                                    <?php if ($record['blood_pressure']): ?>
                                                        <p class="mb-1">
                                                            <i class="fas fa-heartbeat text-primary me-2"></i>
                                                            <strong>Blood Pressure:</strong> <?php echo htmlspecialchars($record['blood_pressure']); ?>
                                                        </p>
                                                    <?php endif; ?>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($record['notes']): ?>
                                                    <h6 class="text-muted mb-2 mt-3">Doctor's Notes</h6>
                                                    <div class="p-2 bg-light rounded">
                                                        <small><?php echo nl2br(htmlspecialchars($record['notes'])); ?></small>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            
                                            <!-- Growth Progress (if not first record) -->
                                            <?php if ($index < count($medical_records) - 1): ?>
                                                <?php
                                                $previous_record = $medical_records[$index + 1];
                                                $weight_change = $record['weight_kg'] - $previous_record['weight_kg'];
                                                $height_change = $record['height_cm'] - $previous_record['height_cm'];
                                                $time_diff = $record['age_months'] - $previous_record['age_months'];
                                                ?>
                                                <?php if ($weight_change != 0 || $height_change != 0): ?>
                                                <hr>
                                                <div class="row text-center">
                                                    <div class="col-12">
                                                        <small class="text-muted">Since last visit (<?php echo $time_diff; ?> months ago):</small>
                                                    </div>
                                                    <?php if ($weight_change != 0): ?>
                                                    <div class="col-6">
                                                        <small class="text-<?php echo $weight_change > 0 ? 'success' : 'warning'; ?>">
                                                            <i class="fas fa-arrow-<?php echo $weight_change > 0 ? 'up' : 'down'; ?> me-1"></i>
                                                            <?php echo number_format(abs($weight_change), 1); ?> kg
                                                        </small>
                                                    </div>
                                                    <?php endif; ?>
                                                    <?php if ($height_change != 0): ?>
                                                    <div class="col-6">
                                                        <small class="text-<?php echo $height_change > 0 ? 'success' : 'warning'; ?>">
                                                            <i class="fas fa-arrow-<?php echo $height_change > 0 ? 'up' : 'down'; ?> me-1"></i>
                                                            <?php echo number_format(abs($height_change), 1); ?> cm
                                                        </small>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php else: ?>
            <!-- No records message -->
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-file-medical fa-4x text-muted mb-4"></i>
                    <h4 class="text-muted">No medical records available</h4>
                    <p class="text-muted">Medical records will be available after your child's first doctor visit.</p>
                    
                    <div class="row justify-content-center mt-4">
                        <div class="col-md-8">
                            <div class="alert alert-info">
                                <h6><i class="fas fa-info-circle me-2"></i>What happens during medical visits:</h6>
                                <ul class="text-start mb-0">
                                    <li>Weight and height measurements</li>
                                    <li>Temperature and vital signs check</li>
                                    <li>Growth assessment and percentile tracking</li>
                                    <li>Doctor's examination and notes</li>
                                    <li>Health recommendations and advice</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Important Information -->
            <div class="card mt-4">
                <div class="card-header bg-light">
                    <h5><i class="fas fa-lightbulb me-2"></i>Understanding Your Child's Growth</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Growth Percentiles</h6>
                            <p class="small text-muted">
                                Percentiles compare your child's measurements to other children of the same age and gender. 
                                A 50th percentile means your child is average for their age group.
                            </p>
                            <ul class="small">
                                <li><strong>Above 85th percentile:</strong> Above average</li>
                                <li><strong>15th-85th percentile:</strong> Normal range</li>
                                <li><strong>Below 15th percentile:</strong> Below average</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6>BMI for Children</h6>
                            <p class="small text-muted">
                                BMI (Body Mass Index) helps assess if your child's weight is appropriate for their height. 
                                The healthy range varies by age for children.
                            </p>
                            <div class="alert alert-info small">
                                <i class="fas fa-info-circle me-1"></i>
                                Always discuss your child's growth with their healthcare provider for proper interpretation.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<style>
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #dee2e6;
}

.timeline-item {
    position: relative;
    margin-bottom: 30px;
}

.timeline-marker {
    position: absolute;
    left: -22px;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 12px;
    z-index: 1;
}

.timeline-content {
    margin-left: 15px;
}

@media print {
    .btn-toolbar, .navbar, .sidebar {
        display: none !important;
    }
    
    .main-content {
        margin: 0 !important;
        padding: 0 !important;
    }
    
    .timeline-marker {
        background: #000 !important;
    }
    
    .card {
        break-inside: avoid;
        margin-bottom: 15px;
    }
}
</style>

<?php
if (!empty($growth_data) && count($growth_data) >= 2) {
    // Prepare data for JavaScript charts
    $chart_data = [
        'labels' => array_map(function($record) use ($utils) { 
            return $utils->formatDate($record['visit_date']); 
        }, $growth_data),
        'weights' => array_map(function($record) { 
            return $record['weight_kg'] ? floatval($record['weight_kg']) : null; 
        }, $growth_data),
        'heights' => array_map(function($record) { 
            return $record['height_cm'] ? floatval($record['height_cm']) : null; 
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
                pointHoverRadius: 8,
                pointBackgroundColor: "rgb(40, 167, 69)",
                pointBorderColor: "#fff",
                pointBorderWidth: 2
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
                },
                tooltip: {
                    callbacks: {
                        title: function(context) {
                            return "Visit: " + context[0].label;
                        },
                        label: function(context) {
                            return "Weight: " + context.parsed.y + " kg";
                        }
                    }
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
                pointHoverRadius: 8,
                pointBackgroundColor: "rgb(23, 162, 184)",
                pointBorderColor: "#fff",
                pointBorderWidth: 2
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
                },
                tooltip: {
                    callbacks: {
                        title: function(context) {
                            return "Visit: " + context[0].label;
                        },
                        label: function(context) {
                            return "Height: " + context.parsed.y + " cm";
                        }
                    }
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
