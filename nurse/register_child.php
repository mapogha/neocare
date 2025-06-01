<?php
$page_title = 'Register Child';
require_once '../includes/header.php';

$session->requireRole('nurse');

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

// Get hospitals list
$query = "SELECT * FROM hospitals ORDER BY hospital_name";
$stmt = $db->prepare($query);
$stmt->execute();
$hospitals = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $hospital_id = $utils->cleanInput($_POST['hospital_id'] ?? '');
    $child_name = $utils->cleanInput($_POST['child_name'] ?? '');
    $date_of_birth = $utils->cleanInput($_POST['date_of_birth'] ?? '');
    $gender = $utils->cleanInput($_POST['gender'] ?? '');
    $parent_name = $utils->cleanInput($_POST['parent_name'] ?? '');
    $parent_phone = $utils->cleanInput($_POST['parent_phone'] ?? '');
    $parent_email = $utils->cleanInput($_POST['parent_email'] ?? '');
    $address = $utils->cleanInput($_POST['address'] ?? '');
    
    // Validation
    if (empty($hospital_id) || empty($child_name) || empty($date_of_birth) || empty($gender) || 
        empty($parent_name) || empty($parent_phone)) {
        $error = 'Please fill in all required fields';
    } elseif (strtotime($date_of_birth) > time()) {
        $error = 'Date of birth cannot be in the future';
    } elseif (!$utils->validatePhone($parent_phone)) {
        $error = 'Please enter a valid phone number (e.g., +255XXXXXXXXX or 0XXXXXXXXX)';
    } elseif (!empty($parent_email) && !$utils->validateEmail($parent_email)) {
        $error = 'Please enter a valid email address';
    } else {
        // Generate registration number
        $registration_number = $utils->generateRegistrationNumber($hospital_id);
        
        try {
            $db->beginTransaction();
            
            // Insert child
            $query = "INSERT INTO children (hospital_id, registration_number, child_name, date_of_birth, 
                     gender, parent_name, parent_phone, parent_email, address, registered_by) 
                     VALUES (:hospital_id, :registration_number, :child_name, :date_of_birth, 
                     :gender, :parent_name, :parent_phone, :parent_email, :address, :registered_by)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':hospital_id', $hospital_id);
            $stmt->bindParam(':registration_number', $registration_number);
            $stmt->bindParam(':child_name', $child_name);
            $stmt->bindParam(':date_of_birth', $date_of_birth);
            $stmt->bindParam(':gender', $gender);
            $stmt->bindParam(':parent_name', $parent_name);
            $stmt->bindParam(':parent_phone', $parent_phone);
            $stmt->bindParam(':parent_email', $parent_email);
            $stmt->bindParam(':address', $address);
            $stmt->bindParam(':registered_by', $_SESSION['user_id']);
            $stmt->execute();
            
            $child_id = $db->lastInsertId();
            
            // Create vaccination schedule
            $utils->createVaccinationSchedule($child_id, $date_of_birth);
            
            $db->commit();
            
            $success = "Child registered successfully! Registration Number: $registration_number";
            
            // Send welcome SMS to parent
            $welcome_message = "Welcome to NeoCare! Your child " . $child_name . " has been registered. Registration Number: " . $registration_number . ". Keep this number safe for future reference.";
            $utils->sendSMSReminder($parent_phone, $welcome_message);
            
        } catch (Exception $e) {
            $db->rollback();
            $error = 'Registration failed. Please try again.';
        }
    }
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Register New Child</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="children_list.php" class="btn btn-outline-secondary">
            <i class="fas fa-list me-1"></i>View Children
        </a>
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

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-baby me-2"></i>Child Information</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="hospital_id" class="form-label">Hospital <span class="text-danger">*</span></label>
                                <select class="form-select" id="hospital_id" name="hospital_id" required>
                                    <option value="">Select Hospital</option>
                                    <?php foreach ($hospitals as $hospital): ?>
                                        <option value="<?php echo $hospital['hospital_id']; ?>">
                                            <?php echo htmlspecialchars($hospital['hospital_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="child_name" class="form-label">Child's Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="child_name" name="child_name" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="date_of_birth" class="form-label">Date of Birth <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" 
                                       max="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="gender" class="form-label">Gender <span class="text-danger">*</span></label>
                                <select class="form-select" id="gender" name="gender" required>
                                    <option value="">Select Gender</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    <h6><i class="fas fa-users me-2"></i>Parent/Guardian Information</h6>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="parent_name" class="form-label">Parent/Guardian Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="parent_name" name="parent_name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="parent_phone" class="form-label">Phone Number <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control" id="parent_phone" name="parent_phone" 
                                       placeholder="+255XXXXXXXXX or 0XXXXXXXXX" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="parent_email" class="form-label">Email Address (Optional)</label>
                                <input type="email" class="form-control" id="parent_email" name="parent_email">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="address" class="form-label">Address</label>
                                <textarea class="form-control" id="address" name="address" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="reset" class="btn btn-outline-secondary me-md-2">
                            <i class="fas fa-redo me-1"></i>Reset
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Register Child
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-info-circle me-2"></i>Registration Information</h5>
            </div>
            <div class="card-body">
                <ul class="list-unstyled">
                    <li class="mb-2">
                        <i class="fas fa-check text-success me-2"></i>
                        Unique registration number will be generated automatically
                    </li>
                    <li class="mb-2">
                        <i class="fas fa-check text-success me-2"></i>
                        Vaccination schedule will be created based on birth date
                    </li>
                    <li class="mb-2">
                        <i class="fas fa-check text-success me-2"></i>
                        Welcome SMS will be sent to parent
                    </li>
                    <li class="mb-2">
                        <i class="fas fa-check text-success me-2"></i>
                        Parent can login using child name and registration number
                    </li>
                </ul>
                
                <hr>
                
                <h6>Phone Number Format:</h6>
                <small class="text-muted">
                    • +255XXXXXXXXX (international)<br>
                    • 0XXXXXXXXX (local)<br>
                    • Must be a valid Tanzanian number
                </small>
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-header">
                <h5><i class="fas fa-syringe me-2"></i>Default Vaccines</h5>
            </div>
            <div class="card-body">
                <small class="text-muted">
                    The following vaccination schedule will be automatically created:
                </small>
                <ul class="list-unstyled mt-2">
                    <li><small>• BCG - At birth</small></li>
                    <li><small>• OPV 1, DPT 1 - 6 weeks</small></li>
                    <li><small>• OPV 2, DPT 2 - 10 weeks</small></li>
                    <li><small>• OPV 3, DPT 3 - 14 weeks</small></li>
                    <li><small>• Measles - 9 months</small></li>
                    <li><small>• MMR - 12 months</small></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php
$extra_scripts = '
<script>
    // Auto-format phone number
    document.getElementById("parent_phone").addEventListener("input", function(e) {
        let value = e.target.value.replace(/\D/g, "");
        if (value.startsWith("255")) {
            e.target.value = "+" + value;
        } else if (value.startsWith("0")) {
            e.target.value = value;
        }
    });
    
    // Validate date of birth
    document.getElementById("date_of_birth").addEventListener("change", function(e) {
        let birthDate = new Date(e.target.value);
        let today = new Date();
        let age = today.getFullYear() - birthDate.getFullYear();
        
        if (age > 5) {
            if (!confirm("The child is over 5 years old. Are you sure this is correct?")) {
                e.target.value = "";
            }
        }
    });
</script>';

require_once '../includes/footer.php';
?>
