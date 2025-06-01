<?php
$page_title = 'Children List';
require_once '../includes/header.php';

$session->requireRole('nurse');

$database = new Database();
$db = $database->getConnection();
$current_user = $session->getCurrentUser();
$hospital_id = $current_user['hospital_id'];

// Handle search and filters
$search = isset($_GET['search']) ? $utils->cleanInput($_GET['search']) : '';
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';
$age_filter = isset($_GET['age_filter']) ? $_GET['age_filter'] : '';

$search_conditions = ["c.hospital_id = :hospital_id"];
$search_params = [':hospital_id' => $hospital_id];

if (!empty($search)) {
    $search_conditions[] = "(c.child_name LIKE :search OR c.registration_number LIKE :search OR c.parent_name LIKE :search OR c.parent_phone LIKE :search)";
    $search_params[':search'] = "%$search%";
}

if (!empty($status_filter)) {
    switch ($status_filter) {
        case 'due_today':
            $search_conditions[] = "EXISTS (SELECT 1 FROM vaccination_schedule vs WHERE vs.child_id = c.child_id AND vs.status = 'pending' AND vs.scheduled_date = CURDATE())";
            break;
        case 'overdue':
            $search_conditions[] = "EXISTS (SELECT 1 FROM vaccination_schedule vs WHERE vs.child_id = c.child_id AND vs.status = 'pending' AND vs.scheduled_date < CURDATE())";
            break;
        case 'up_to_date':
            $search_conditions[] = "NOT EXISTS (SELECT 1 FROM vaccination_schedule vs WHERE vs.child_id = c.child_id AND vs.status = 'pending' AND vs.scheduled_date <= CURDATE())";
            break;
    }
}

if (!empty($age_filter)) {
    switch ($age_filter) {
        case 'newborn':
            $search_conditions[] = "TIMESTAMPDIFF(MONTH, c.date_of_birth, CURDATE()) <= 3";
            break;
        case 'infant':
            $search_conditions[] = "TIMESTAMPDIFF(MONTH, c.date_of_birth, CURDATE()) BETWEEN 4 AND 12";
            break;
        case 'toddler':
            $search_conditions[] = "TIMESTAMPDIFF(MONTH, c.date_of_birth, CURDATE()) BETWEEN 13 AND 36";
            break;
        case 'preschool':
            $search_conditions[] = "TIMESTAMPDIFF(MONTH, c.date_of_birth, CURDATE()) > 36";
            break;
    }
}

$where_clause = "WHERE " . implode(" AND ", $search_conditions);

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Count total children
$count_query = "SELECT COUNT(DISTINCT c.child_id) as total FROM children c $where_clause";
$count_stmt = $db->prepare($count_query);
foreach ($search_params as $key => $value) {
    $count_stmt->bindParam($key, $value);
}
$count_stmt->execute();
$total_children = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_children / $per_page);

// Get children with vaccination status
$children_query = "SELECT c.*,
    TIMESTAMPDIFF(MONTH, c.date_of_birth, CURDATE()) as age_months,
    COUNT(vs.schedule_id) as total_vaccines,
    SUM(CASE WHEN vs.status = 'completed' THEN 1 ELSE 0 END) as completed_vaccines,
    SUM(CASE WHEN vs.status = 'pending' AND vs.scheduled_date = CURDATE() THEN 1 ELSE 0 END) as due_today,
    SUM(CASE WHEN vs.status = 'pending' AND vs.scheduled_date < CURDATE() THEN 1 ELSE 0 END) as overdue_vaccines,
    (SELECT vs2.scheduled_date FROM vaccination_schedule vs2 WHERE vs2.child_id = c.child_id AND vs2.status = 'pending' ORDER BY vs2.scheduled_date ASC LIMIT 1) as next_vaccine_date,
    (SELECT v.vaccine_name FROM vaccination_schedule vs3 JOIN vaccines v ON vs3.vaccine_id = v.vaccine_id WHERE vs3.child_id = c.child_id AND vs3.status = 'pending' ORDER BY vs3.scheduled_date ASC LIMIT 1) as next_vaccine_name,
    u.full_name as registered_by_name
    FROM children c
    LEFT JOIN vaccination_schedule vs ON c.child_id = vs.child_id
    LEFT JOIN users u ON c.registered_by = u.user_id
    $where_clause
    GROUP BY c.child_id
    ORDER BY c.child_name
    LIMIT :offset, :per_page";

$children_stmt = $db->prepare($children_query);
foreach ($search_params as $key => $value) {
    $children_stmt->bindParam($key, $value);
}
$children_stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$children_stmt->bindParam(':per_page', $per_page, PDO::PARAM_INT);
$children_stmt->execute();
$children = $children_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get summary statistics
$stats_query = "SELECT 
    COUNT(DISTINCT c.child_id) as total_children,
    COUNT(DISTINCT CASE WHEN vs.status = 'pending' AND vs.scheduled_date = CURDATE() THEN c.child_id END) as due_today,
    COUNT(DISTINCT CASE WHEN vs.status = 'pending' AND vs.scheduled_date < CURDATE() THEN c.child_id END) as overdue,
    COUNT(DISTINCT CASE WHEN YEAR(c.created_at) = YEAR(CURDATE()) THEN c.child_id END) as registered_this_year
    FROM children c
    LEFT JOIN vaccination_schedule vs ON c.child_id = vs.child_id
    WHERE c.hospital_id = :hospital_id";

$stats_stmt = $db->prepare($stats_query);
$stats_stmt->bindParam(':hospital_id', $hospital_id);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Children List</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="register_child.php" class="btn btn-primary me-2">
            <i class="fas fa-plus me-1"></i>Register New Child
        </a>
        <a href="vaccination.php" class="btn btn-outline-secondary">
            <i class="fas fa-syringe me-1"></i>Vaccinations
        </a>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-baby fa-2x text-primary mb-2"></i>
                <h4><?php echo $stats['total_children']; ?></h4>
                <p class="card-text">Total Children</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-calendar-day fa-2x text-warning mb-2"></i>
                <h4><?php echo $stats['due_today']; ?></h4>
                <p class="card-text">Due Today</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-exclamation-triangle fa-2x text-danger mb-2"></i>
                <h4><?php echo $stats['overdue']; ?></h4>
                <p class="card-text">Overdue</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-calendar-plus fa-2x text-success mb-2"></i>
                <h4><?php echo $stats['registered_this_year']; ?></h4>
                <p class="card-text">Registered This Year</p>
            </div>
        </div>
    </div>
</div>

<!-- Search and Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Search by name, registration, parent...">
                </div>
            </div>
            <div class="col-md-3">
                <select class="form-select" name="status_filter">
                    <option value="">All Status</option>
                    <option value="due_today" <?php echo $status_filter == 'due_today' ? 'selected' : ''; ?>>Due Today</option>
                    <option value="overdue" <?php echo $status_filter == 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                    <option value="up_to_date" <?php echo $status_filter == 'up_to_date' ? 'selected' : ''; ?>>Up to Date</option>
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select" name="age_filter">
                    <option value="">All Ages</option>
                    <option value="newborn" <?php echo $age_filter == 'newborn' ? 'selected' : ''; ?>>Newborn (0-3 months)</option>
                    <option value="infant" <?php echo $age_filter == 'infant' ? 'selected' : ''; ?>>Infant (4-12 months)</option>
                    <option value="toddler" <?php echo $age_filter == 'toddler' ? 'selected' : ''; ?>>Toddler (1-3 years)</option>
                    <option value="preschool" <?php echo $age_filter == 'preschool' ? 'selected' : ''; ?>>Preschool (3+ years)</option>
                </select>
            </div>
            <div class="col-md-2">
                <div class="btn-group w-100" role="group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i>
                    </button>
                    <a href="children_list.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times"></i>
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Children List -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5><i class="fas fa-list me-2"></i>Children List
            <?php if (!empty($search) || !empty($status_filter) || !empty($age_filter)): ?>
                <small class="text-muted">- Filtered results</small>
            <?php endif; ?>
        </h5>
        <span class="badge bg-primary"><?php echo $total_children; ?> children</span>
    </div>
    <div class="card-body">
        <?php if (empty($children)): ?>
            <div class="text-center py-4">
                <i class="fas fa-baby fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No children found</h5>
                <?php if (!empty($search) || !empty($status_filter) || !empty($age_filter)): ?>
                    <p>Try adjusting your search criteria or filters</p>
                    <a href="children_list.php" class="btn btn-outline-primary">View All Children</a>
                <?php else: ?>
                    <p>Start by registering your first child</p>
                    <a href="register_child.php" class="btn btn-primary">Register Child</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Child Information</th>
                            <th>Age</th>
                            <th>Parent/Guardian</th>
                            <th>Vaccination Status</th>
                            <th>Next Vaccine</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($children as $child): ?>
                        <tr class="<?php echo $child['overdue_vaccines'] > 0 ? 'table-warning' : ($child['due_today'] > 0 ? 'table-info' : ''); ?>">
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="me-3">
                                        <i class="fas fa-<?php echo $child['gender'] == 'male' ? 'mars' : 'venus'; ?> fa-2x text-<?php echo $child['gender'] == 'male' ? 'info' : 'warning'; ?>"></i>
                                    </div>
                                    <div>
                                        <strong><?php echo htmlspecialchars($child['child_name']); ?></strong><br>
                                        <small class="text-muted">
                                            <i class="fas fa-id-card me-1"></i><?php echo htmlspecialchars($child['registration_number']); ?><br>
                                            <i class="fas fa-birthday-cake me-1"></i><?php echo $utils->formatDate($child['date_of_birth']); ?>
                                        </small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-info"><?php echo $child['age_months']; ?> months</span><br>
                                <small class="text-muted">
                                    <?php 
                                    $years = floor($child['age_months'] / 12);
                                    $months = $child['age_months'] % 12;
                                    if ($years > 0) {
                                        echo $years . 'y ' . $months . 'm';
                                    } else {
                                        echo $months . ' months';
                                    }
                                    ?>
                                </small>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($child['parent_name']); ?></strong><br>
                                <small class="text-muted">
                                    <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($child['parent_phone']); ?>
                                </small>
                            </td>
                            <td>
                                <div class="progress mb-1" style="height: 10px;">
                                    <?php 
                                    $completion = $child['total_vaccines'] > 0 ? ($child['completed_vaccines'] / $child['total_vaccines']) * 100 : 0;
                                    ?>
                                    <div class="progress-bar bg-success" style="width: <?php echo $completion; ?>%"></div>
                                </div>
                                <small>
                                    <?php echo $child['completed_vaccines']; ?>/<?php echo $child['total_vaccines']; ?> completed
                                </small><br>
                                
                                <?php if ($child['due_today'] > 0): ?>
                                    <span class="badge bg-warning"><?php echo $child['due_today']; ?> due today</span>
                                <?php endif; ?>
                                
                                <?php if ($child['overdue_vaccines'] > 0): ?>
                                    <span class="badge bg-danger"><?php echo $child['overdue_vaccines']; ?> overdue</span>
                                <?php endif; ?>
                                
                                <?php if ($child['due_today'] == 0 && $child['overdue_vaccines'] == 0): ?>
                                    <span class="badge bg-success">Up to date</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($child['next_vaccine_date']): ?>
                                    <strong><?php echo htmlspecialchars($child['next_vaccine_name']); ?></strong><br>
                                    <small class="text-muted">
                                        <?php echo $utils->formatDate($child['next_vaccine_date']); ?>
                                        <?php
                                        $days_until = (strtotime($child['next_vaccine_date']) - time()) / (24 * 60 * 60);
                                        if ($days_until < 0) {
                                            echo ' <span class="text-danger">(' . abs(ceil($days_until)) . ' days overdue)</span>';
                                        } elseif ($days_until == 0) {
                                            echo ' <span class="text-warning">(Today)</span>';
                                        } elseif ($days_until <= 7) {
                                            echo ' <span class="text-info">(in ' . ceil($days_until) . ' days)</span>';
                                        }
                                        ?>
                                    </small>
                                <?php else: ?>
                                    <span class="text-success">All vaccines completed</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    <button class="btn btn-outline-primary" onclick="viewChild(<?php echo $child['child_id']; ?>)" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <a href="vaccination.php?child_id=<?php echo $child['child_id']; ?>" class="btn btn-outline-success" title="Vaccinations">
                                        <i class="fas fa-syringe"></i>
                                    </a>
                                    <button class="btn btn-outline-info" onclick="sendReminder(<?php echo $child['child_id']; ?>)" title="Send SMS Reminder">
                                        <i class="fas fa-paper-plane"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <nav aria-label="Children pagination" class="mt-4">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status_filter=<?php echo $status_filter; ?>&age_filter=<?php echo $age_filter; ?>">Previous</a>
                    </li>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status_filter=<?php echo $status_filter; ?>&age_filter=<?php echo $age_filter; ?>"><?php echo $i; ?></a>
                    </li>
                    <?php endfor; ?>
                    
                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status_filter=<?php echo $status_filter; ?>&age_filter=<?php echo $age_filter; ?>">Next</a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Quick Actions Floating Button -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1050;">
    <div class="btn-group-vertical" role="group">
        <a href="register_child.php" class="btn btn-primary mb-2" title="Register New Child">
            <i class="fas fa-plus"></i>
        </a>
        <button class="btn btn-success mb-2" onclick="showBulkActions()" title="Bulk Actions">
            <i class="fas fa-cogs"></i>
        </button>
        <button class="btn btn-info" onclick="window.print()" title="Print List">
            <i class="fas fa-print"></i>
        </button>
    </div>
</div>

<!-- Child Details Modal -->
<div class="modal fade" id="childDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Child Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="childDetailsContent">
                <!-- Content will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="window.print()">Print</button>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Actions Modal -->
<div class="modal fade" id="bulkActionsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Bulk Actions</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="d-grid gap-2">
                    <button class="btn btn-outline-primary" onclick="sendBulkReminders('due_today')">
                        <i class="fas fa-paper-plane me-2"></i>Send Reminders - Due Today
                    </button>
                    <button class="btn btn-outline-warning" onclick="sendBulkReminders('overdue')">
                        <i class="fas fa-exclamation-triangle me-2"></i>Send Reminders - Overdue
                    </button>
                    <button class="btn btn-outline-info" onclick="exportChildrenList()">
                        <i class="fas fa-download me-2"></i>Export Children List
                    </button>
                </div>
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
function viewChild(childId) {
    // Show loading
    document.getElementById("childDetailsContent").innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading child details...</p>
        </div>
    `;
    
    new bootstrap.Modal(document.getElementById("childDetailsModal")).show();
    
    // Load child details via AJAX
    fetch(`../api/get_child_details.php?child_id=${childId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById("childDetailsContent").innerHTML = data.html;
            } else {
                document.getElementById("childDetailsContent").innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        Error loading child details
                    </div>
                `;
            }
        })
        .catch(error => {
            document.getElementById("childDetailsContent").innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    Error loading child details
                </div>
            `;
        });
}

function sendReminder(childId) {
    if (confirm("Send vaccination reminder SMS to parent?")) {
        fetch("../api/send_reminder.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify({child_id: childId})
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert("Reminder sent successfully!");
            } else {
                alert("Failed to send reminder: " + (data.message || "Unknown error"));
            }
        })
        .catch(error => {
            alert("Error sending reminder");
        });
    }
}

function showBulkActions() {
    new bootstrap.Modal(document.getElementById("bulkActionsModal")).show();
}

function sendBulkReminders(type) {
    const message = type === "due_today" ? 
        "Send reminders to all children with vaccinations due today?" :
        "Send reminders to all children with overdue vaccinations?";
        
    if (confirm(message)) {
        fetch("../api/send_bulk_reminders.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify({type: type})
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(`Sent ${data.count} reminder(s) successfully!`);
                bootstrap.Modal.getInstance(document.getElementById("bulkActionsModal")).hide();
            } else {
                alert("Failed to send reminders: " + (data.message || "Unknown error"));
            }
        })
        .catch(error => {
            alert("Error sending reminders");
        });
    }
}

function exportChildrenList() {
    window.open("../api/export_children.php?format=csv", "_blank");
    bootstrap.Modal.getInstance(document.getElementById("bulkActionsModal")).hide();
}

// Auto search on typing (debounced)
let searchTimeout;
document.querySelector("input[name=search]").addEventListener("input", function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        if (this.value.length >= 3 || this.value.length === 0) {
            this.form.submit();
        }
    }, 500);
});

// Highlight urgent cases
document.addEventListener("DOMContentLoaded", function() {
    const urgentRows = document.querySelectorAll(".table-warning, .table-danger");
    urgentRows.forEach(row => {
        row.style.borderLeft = "4px solid #dc3545";
    });
});
</script>';

require_once '../includes/footer.php';
?>
