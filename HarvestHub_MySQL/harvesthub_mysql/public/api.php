<?php
/**
 * api.php — HarvestHub JSON API
 *
 * Routes are grouped by role. All routes return { ok: bool, ... } JSON.
 * Every write action re-validates on the server and uses prepared PDO
 * statements — client-side checks in the dashboards are for UX only.
 *
 *  AUTH (public)
 *    POST action=login        { email, password }
 *    POST action=logout
 *
 *  CUSTOMER (requires customer session)
 *    GET  action=list                 -> exchange listings (search, min_qty)
 *    POST action=create               -> new exchange listing
 *    POST action=claim                -> claim a listing
 *    GET  action=my_plot              -> the gardener's plot + application status
 *    POST action=apply_plot           -> apply for an available plot
 *    GET  action=my_croplog           -> the gardener's crop log entries
 *    POST action=croplog_create       -> add a crop log entry
 *    GET  action=resources            -> resource catalogue + availability
 *    POST action=resource_request     -> request a resource
 *    GET  action=my_resource_requests -> the gardener's own requests
 *
 *  STAFF (requires staff session)
 *    GET  action=pending_applications
 *    POST action=process_application  { app_id, decision: approve|reject }
 *    GET  action=pending_resource_txns
 *    POST action=process_resource_txn { txn_id, decision: approve|reject }
 *    GET  action=all_plots
 *
 *  ADMIN (requires admin session)
 *    GET  action=stats
 *    GET  action=accounts
 *    POST action=add_coordinator      { name, email, password, shift }
 *    POST action=delete_account       { table: gardener|coordinator, id }
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth.php';

function sendResetEmail($toEmail, $resetLink) {
    // Your Bird API Key
    $apiKey = 'bk_eu1_5vCFHtcgJ9G2iPdwf5EIaYT4AbFB5'; 
    
    // Bird requires you to use the regional host that matches your key prefix (eu1)
    $apiUrl = 'https://eu1.platform.bird.com/v1/email/messages';

    $htmlContent = "
        <h2>HarvestHub Password Reset</h2>
        <p>You requested a password reset. Click the link below to set a new password:</p>
        <p><a href='{$resetLink}'>Reset Password</a></p>
        <p>If you did not request this, please ignore this email.</p>
    ";

    $payload = [
        'from' => [
            // During onboarding, you must use this exact testing email address
            'email' => 'onboarding@messagebird.dev', 
            'name' => 'HarvestHub'
        ],
        'to' => [$toEmail],
        'subject' => 'Reset your HarvestHub password',
        'html' => $htmlContent
    ];

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    // 1. Check if the server failed to connect entirely
    if ($curlError) {
        respond(['ok' => false, 'error' => "Connection Error: " . $curlError], 500);
    }
    
    // 2. Check if Bird rejected the email (HTTP codes 400 and above are errors)
    if ($httpCode >= 400) {
        respond(['ok' => false, 'error' => "Bird API Error: " . $response], 500);
    }
}

header('Content-Type: application/json');

$pdo = getDb();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

function respond(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

function requireJsonRole(string $role): array {
    $user = currentUser();
    if (!$user || $user['role'] !== $role) {
        respond(['ok' => false, 'error' => 'Not authorized.'], 403);
    }
    return $user;
}

// Whitelisted sort options for the Exchange Board
const SORT_OPTIONS = [
    'newest'   => 'L.CreatedAt DESC',
    'oldest'   => 'L.CreatedAt ASC',
    'qty_high' => 'L.Qty DESC',
    'qty_low'  => 'L.Qty ASC',
];

const NCR_CITIES = [
    'Caloocan', 'Las Piñas', 'Makati', 'Malabon', 'Mandaluyong', 'Manila',
    'Marikina', 'Muntinlupa', 'Navotas', 'Parañaque', 'Pasay', 'Pasig',
    'Pateros', 'Quezon City', 'San Juan', 'Taguig', 'Valenzuela',
];

try {
    switch ($action) {

        // ---------------- AUTH ----------------

        case 'login': {
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';

            if ($email === '' || $password === '') {
                respond(['ok' => false, 'error' => 'Please fill in all fields.'], 422);
            }

            $userRecord = null;
            $role = null;

            // 1. Check if the user is a Community Gardener
            $stmt = $pdo->prepare("SELECT GardenerID as id, Name, PasswordHash FROM COMMUNITY_GARDENER WHERE Email = ?");
            $stmt->execute([$email]);
            if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $userRecord = $row;
                $role = 'customer';
            }

            // 2. Check if the user is a Garden Coordinator
            if (!$userRecord) {
                $stmt = $pdo->prepare("SELECT CoordID as id, Name, PasswordHash FROM GARDEN_COORDINATOR WHERE Email = ?");
                $stmt->execute([$email]);
                if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $userRecord = $row;
                    $role = 'staff';
                }
            }

            // 3. Check if the user is a System Administrator
            if (!$userRecord) {
                $stmt = $pdo->prepare("SELECT AdminID as id, Name, PasswordHash FROM SYSTEM_ADMINISTRATOR WHERE Email = ?");
                $stmt->execute([$email]);
                if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $userRecord = $row;
                    $role = 'admin';
                }
            }

            if (!$userRecord || !password_verify($password, $userRecord['PasswordHash'])) {
                respond(['ok' => false, 'error' => 'Invalid email or password.'], 401);
            }

            $_SESSION['user'] = [
                'role' => $role,
                'id' => (int) $userRecord['id'],
                'name' => $userRecord['Name'],
            ];

            // If checked, save the email. If unchecked, delete the cookie.
            if (($_POST['remember'] ?? '0') === '1') {
                setcookie('remembered_email', $email, time() + (86400 * 30), '/');
            } else {
                setcookie('remembered_email', '', time() - 3600, '/');
            }

            respond(['ok' => true, 'redirect' => loginRedirectFor($role)]);

            respond(['ok' => true, 'redirect' => loginRedirectFor($role)]);
        }

        case 'logout':
            // Destroy the remember me cookie by setting its expiration to the past
            setcookie('remember_me', '', time() - 3600, '/');
            
            $_SESSION = [];
            session_destroy();
            respond(['ok' => true, 'redirect' => 'login.php']);

        case 'signup_request': {
            $firstName = trim($_POST['first_name'] ?? '');
            $lastName = trim($_POST['last_name'] ?? '');
            $age = $_POST['age'] ?? '';
            $location = trim($_POST['location'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            
            // Automatically determine role based on email domain
            $role = 'customer'; // Default role
            if (str_ends_with(strtolower($email), '@staff.harvesthub.com')) {
                $role = 'staff';
            }

            // Set a default shift for coordinators
            $shift = 'Morning';

            $errors = [];
            
            if ($firstName === '' || mb_strlen($firstName) > 60) {
                $errors[] = 'First name is required.';
            } elseif (!preg_match("/^[A-Za-z\s\-']+$/u", $firstName)) {
                $errors[] = 'First name must contain only letters.';
            }

            if ($lastName === '' || mb_strlen($lastName) > 60) {
                $errors[] = 'Last name is required.';
            } elseif (!preg_match("/^[A-Za-z\s\-']+$/u", $lastName)) {
                $errors[] = 'Last name must contain only letters.';
            }

            if (!ctype_digit((string) $age) || (int) $age < 18 || (int) $age > 120) {
                $errors[] = 'You must be at least 18 years old to register.';
            }

            if (!in_array($location, NCR_CITIES, true)) $errors[] = 'Please choose a valid NCR city.';
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';
            if (mb_strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
            if ($password !== $confirmPassword) $errors[] = 'Passwords do not match.';
            
            if ($errors) respond(['ok' => false, 'errors' => $errors], 422);

            // An email already active as any account, or already sitting
            // in the queue as a pending request, can't submit another one.
            $inUse = $pdo->prepare("
                SELECT 1 FROM COMMUNITY_GARDENER WHERE Email = ?
                UNION SELECT 1 FROM GARDEN_COORDINATOR WHERE Email = ?
                UNION SELECT 1 FROM SYSTEM_ADMINISTRATOR WHERE Email = ?
                UNION SELECT 1 FROM SIGNUP_REQUEST WHERE Email = ? AND Status = 'Pending'
            ");
            $inUse->execute([$email, $email, $email, $email]);
            if ($inUse->fetchColumn()) {
                respond(['ok' => false, 'error' => 'That email already has an account or a pending request.'], 409);
            }

            $pdo->prepare("
                INSERT INTO SIGNUP_REQUEST (FirstName, LastName, Age, Location, Email, PasswordHash, Role, Shift)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($lastName, ENT_QUOTES, 'UTF-8'),
                (int) $age,
                htmlspecialchars($location, ENT_QUOTES, 'UTF-8'),
                $email,
                password_hash($password, PASSWORD_BCRYPT),
                $role,
                $shift,
            ]);
            respond(['ok' => true]);
        }

        // ---------------- PASSWORD RESET ----------------

        case 'forgot_password': {
            $email = trim($_POST['email'] ?? '');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                respond(['ok' => false, 'error' => 'Please enter a valid email address.'], 422);
            }

            // 1. Verify the email exists in ANY of our user tables
            $stmt = $pdo->prepare("
                SELECT Email FROM COMMUNITY_GARDENER WHERE Email = ?
                UNION SELECT Email FROM GARDEN_COORDINATOR WHERE Email = ?
                UNION SELECT Email FROM SYSTEM_ADMINISTRATOR WHERE Email = ?
            ");
            $stmt->execute([$email, $email, $email]);
            
            // Temporarily throw an error so we can debug
            if (!$stmt->fetchColumn()) {
                respond(['ok' => false, 'error' => 'Not found in database!'], 404); 
            }

            // 2. Generate a secure random token
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour expiration

            // 3. Store the hashed token (Upsert so old tokens are overwritten)
            $pdo->prepare("
                INSERT INTO PASSWORD_RESET (Email, TokenHash, ExpiresAt) 
                VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE TokenHash = VALUES(TokenHash), ExpiresAt = VALUES(ExpiresAt)
            ")->execute([$email, $tokenHash, $expiresAt]);

            // 4. Send the email using a cURL helper function
            // Make sure to change 'localhost...' to your actual domain when deploying
            // Automatically detect the current folder path so the link works anywhere
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
            $baseDir = dirname($_SERVER['REQUEST_URI']);
            $resetLink = $protocol . $_SERVER['HTTP_HOST'] . $baseDir . "/reset_password.php?email=" . urlencode($email) . "&token=" . $token;
            
            sendResetEmail($email, $resetLink);

            respond(['ok' => true]);
        }

        case 'reset_password': {
            $email = trim($_POST['email'] ?? '');
            $token = $_POST['token'] ?? '';
            $newPassword = $_POST['password'] ?? '';

            if (mb_strlen($newPassword) < 6) {
                respond(['ok' => false, 'error' => 'Password must be at least 6 characters.'], 422);
            }

            // 1. Verify the token
            $tokenHash = hash('sha256', $token);
            $stmt = $pdo->prepare("SELECT ExpiresAt FROM PASSWORD_RESET WHERE Email = ? AND TokenHash = ?");
            $stmt->execute([$email, $tokenHash]);
            $expiresAt = $stmt->fetchColumn();

            if (!$expiresAt || strtotime($expiresAt) < time()) {
                respond(['ok' => false, 'error' => 'Invalid or expired reset link. Please request a new one.'], 403);
            }

            // 2. Update the password in whichever table the user belongs to
            $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
            
            $pdo->prepare("UPDATE COMMUNITY_GARDENER SET PasswordHash = ? WHERE Email = ?")->execute([$hashedPassword, $email]);
            $pdo->prepare("UPDATE GARDEN_COORDINATOR SET PasswordHash = ? WHERE Email = ?")->execute([$hashedPassword, $email]);
            $pdo->prepare("UPDATE SYSTEM_ADMINISTRATOR SET PasswordHash = ? WHERE Email = ?")->execute([$hashedPassword, $email]);

            // 3. Delete the used token
            $pdo->prepare("DELETE FROM PASSWORD_RESET WHERE Email = ?")->execute([$email]);

            respond(['ok' => true]);
        }

        // ---------------- CUSTOMER: Exchange Board ----------------

        case 'list': {
            $search = trim($_GET['search'] ?? '');
            $minQty = isset($_GET['min_qty']) && $_GET['min_qty'] !== '' ? (int) $_GET['min_qty'] : null;
            $sortKey = $_GET['sort'] ?? 'newest';
            $orderBy = SORT_OPTIONS[$sortKey] ?? SORT_OPTIONS['newest'];

            $sql = "
                SELECT L.ListingID, L.Crop, L.Qty, L.Notes, L.CreatedAt, G.Name AS GardenerName
                FROM EXCHANGE_LISTING L
                JOIN COMMUNITY_GARDENER G ON G.GardenerID = L.GardenerID
                WHERE L.ListingID NOT IN (SELECT ListingID FROM EXCHANGE_ORDER)
            ";
            $params = [];
            if ($search !== '') { $sql .= " AND L.Crop LIKE :search"; $params[':search'] = '%' . $search . '%'; }
            if ($minQty !== null) { $sql .= " AND L.Qty >= :min_qty"; $params[':min_qty'] = $minQty; }
            $sql .= " ORDER BY {$orderBy}";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            respond(['ok' => true, 'listings' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        }

        case 'create': {
            $user = requireJsonRole('customer');
            $crop = trim($_POST['crop'] ?? '');
            $qty = $_POST['qty'] ?? '';
            $notes = trim($_POST['notes'] ?? '');

            $errors = [];
            if ($crop === '' || mb_strlen($crop) > 60) {
                $errors[] = 'Crop name is required (max 60 characters).';
            } elseif (!preg_match("/^[A-Za-z\s\-']+$/u", $crop)) {
                $errors[] = 'Crop name may only contain letters, spaces, and hyphens.';
            }
            if (!ctype_digit((string) $qty) || (int) $qty < 1 || (int) $qty > 1000) $errors[] = 'Quantity must be between 1 and 1000.';
            if (mb_strlen($notes) > 200) $errors[] = 'Notes must be 200 characters or fewer.';
            if ($errors) respond(['ok' => false, 'errors' => $errors], 422);

            $stmt = $pdo->prepare("INSERT INTO EXCHANGE_LISTING (GardenerID, Crop, Qty, Notes) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user['id'], htmlspecialchars($crop, ENT_QUOTES, 'UTF-8'), (int) $qty, htmlspecialchars($notes, ENT_QUOTES, 'UTF-8')]);
            respond(['ok' => true, 'listing_id' => $pdo->lastInsertId()]);
        }

        case 'claim': {
            $user = requireJsonRole('customer');
            $listingId = $_POST['listing_id'] ?? '';
            if (!ctype_digit((string) $listingId)) respond(['ok' => false, 'error' => 'Invalid listing id.'], 422);

            $check = $pdo->prepare("
                SELECT GardenerID FROM EXCHANGE_LISTING
                WHERE ListingID = ? AND ListingID NOT IN (SELECT ListingID FROM EXCHANGE_ORDER)
            ");
            $check->execute([(int) $listingId]);
            $owner = $check->fetchColumn();

            if ($owner === false) respond(['ok' => false, 'error' => 'Listing not found or already claimed.'], 404);
            if ((int) $owner === $user['id']) respond(['ok' => false, 'error' => "You can't claim your own listing."], 403);

            $pdo->prepare("INSERT INTO EXCHANGE_ORDER (ListingID, GardenerID) VALUES (?, ?)")
                ->execute([(int) $listingId, $user['id']]);
            respond(['ok' => true]);
        }

        // ---------------- CUSTOMER: Plot ----------------

        case 'my_plot': {
            $user = requireJsonRole('customer');
            $plot = $pdo->prepare("SELECT PltID, Label, Status FROM PLOT WHERE GardenerID = ? ORDER BY Label");
            $plot->execute([$user['id']]);
            $plots = $plot->fetchAll(PDO::FETCH_ASSOC);

            $pending = $pdo->prepare("
                SELECT PA.AppID, P.Label, PA.RequestType FROM PLOT_APPLICATION PA
                JOIN PLOT P ON P.PltID = PA.PltID
                WHERE PA.GardenerID = ? AND PA.Status = 'Pending'
            ");
            $pending->execute([$user['id']]);

            $available = $pdo->query("SELECT PltID, Label FROM PLOT WHERE Status = 'Available'")->fetchAll(PDO::FETCH_ASSOC);

            respond([
                'ok' => true,
                'plots' => $plots,
                'pending_application' => $pending->fetch(PDO::FETCH_ASSOC) ?: null,
                'available_plots' => $available,
            ]);
        }

        case 'request_plot_unassignment': {
            $user = requireJsonRole('customer');
            $plotId = $_POST['plt_id'] ?? '';
            if (!ctype_digit((string) $plotId)) {
                respond(['ok' => false, 'error' => 'Invalid plot.'], 422);
            }

            $plot = $pdo->prepare('SELECT PltID FROM PLOT WHERE PltID = ? AND GardenerID = ? AND Status = \'Occupied\'');
            $plot->execute([(int) $plotId, $user['id']]);
            if (!$plot->fetchColumn()) {
                respond(['ok' => false, 'error' => 'That plot is not assigned to you.'], 409);
            }

            $pending = $pdo->prepare("SELECT 1 FROM PLOT_APPLICATION WHERE GardenerID = ? AND PltID = ? AND Status = 'Pending'");
            $pending->execute([$user['id'], (int) $plotId]);
            if ($pending->fetchColumn()) {
                respond(['ok' => false, 'error' => 'An unassignment request is already pending.'], 409);
            }

            $pdo->prepare("INSERT INTO PLOT_APPLICATION (GardenerID, PltID, Status, RequestType) VALUES (?, ?, 'Pending', 'Unassign')")
                ->execute([$user['id'], (int) $plotId]);
            respond(['ok' => true]);
        }

        case 'apply_plot': {
            $user = requireJsonRole('customer');
            $pltId = $_POST['plt_id'] ?? '';
            if (!ctype_digit((string) $pltId)) respond(['ok' => false, 'error' => 'Invalid plot.'], 422);

            $check = $pdo->prepare("SELECT Status FROM PLOT WHERE PltID = ?");
            $check->execute([(int) $pltId]);
            $status = $check->fetchColumn();
            if ($status !== 'Available') respond(['ok' => false, 'error' => 'That plot is no longer available.'], 409);

            $pdo->prepare("INSERT INTO PLOT_APPLICATION (GardenerID, PltID, Status, RequestType) VALUES (?, ?, 'Pending', 'Apply')")
                ->execute([$user['id'], (int) $pltId]);
            respond(['ok' => true]);
        }

        // ---------------- CUSTOMER: Crop Log ----------------

        case 'my_croplog': {
            $user = requireJsonRole('customer');
            $stmt = $pdo->prepare("
                SELECT L.LogID, L.CropName, L.MaintenanceNotes, L.HarvestYield, L.LoggedAt, P.Label
                FROM CROP_LOG L JOIN PLOT P ON P.PltID = L.PltID
                WHERE L.GardenerID = ? ORDER BY L.LoggedAt DESC
            ");
            $stmt->execute([$user['id']]);
            respond(['ok' => true, 'logs' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        }

        case 'croplog_create': {
            $user = requireJsonRole('customer');
            $plot = $pdo->prepare("SELECT PltID FROM PLOT WHERE GardenerID = ?");
            $plot->execute([$user['id']]);
            $pltId = $plot->fetchColumn();
            if (!$pltId) respond(['ok' => false, 'error' => 'You need an assigned plot before logging crops.'], 409);

            $crop = trim($_POST['crop_name'] ?? '');
            $notes = trim($_POST['notes'] ?? '');
            $yield = trim($_POST['yield'] ?? '');

            if ($crop === '' || mb_strlen($crop) > 60) respond(['ok' => false, 'error' => 'Crop name is required.'], 422);
            if (mb_strlen($notes) > 300 || mb_strlen($yield) > 60) respond(['ok' => false, 'error' => 'Notes or yield too long.'], 422);

            $pdo->prepare("INSERT INTO CROP_LOG (GardenerID, PltID, CropName, MaintenanceNotes, HarvestYield) VALUES (?, ?, ?, ?, ?)")
                ->execute([$user['id'], $pltId, htmlspecialchars($crop, ENT_QUOTES, 'UTF-8'), htmlspecialchars($notes, ENT_QUOTES, 'UTF-8'), htmlspecialchars($yield, ENT_QUOTES, 'UTF-8')]);
            respond(['ok' => true]);
        }

        // ---------------- CUSTOMER: Resources ----------------

        case 'resources': {
            requireJsonRole('customer');
            $rows = $pdo->query("SELECT ResourceID, Name, TotalQty, AvailableQty FROM RESOURCE ORDER BY Name")->fetchAll(PDO::FETCH_ASSOC);
            respond(['ok' => true, 'resources' => $rows]);
        }

        case 'resource_request': {
            $user = requireJsonRole('customer');
            $resourceId = $_POST['resource_id'] ?? '';
            $qty = $_POST['qty'] ?? '';
            if (!ctype_digit((string) $resourceId) || !ctype_digit((string) $qty) || (int) $qty < 1) {
                respond(['ok' => false, 'error' => 'Invalid request.'], 422);
            }

            $res = $pdo->prepare("SELECT AvailableQty FROM RESOURCE WHERE ResourceID = ?");
            $res->execute([(int) $resourceId]);
            $available = $res->fetchColumn();
            if ($available === false || (int) $qty > (int) $available) {
                respond(['ok' => false, 'error' => 'Not enough of that resource available.'], 409);
            }

            $pdo->prepare("INSERT INTO RESOURCE_TXN (GardenerID, ResourceID, Qty, Status) VALUES (?, ?, ?, 'Requested')")
                ->execute([$user['id'], (int) $resourceId, (int) $qty]);
            respond(['ok' => true]);
        }

        case 'my_resource_requests': {
            $user = requireJsonRole('customer');
            $stmt = $pdo->prepare("
                SELECT T.TxnID, R.Name, T.Qty, T.Status, T.RequestedAt
                FROM RESOURCE_TXN T JOIN RESOURCE R ON R.ResourceID = T.ResourceID
                WHERE T.GardenerID = ? ORDER BY T.RequestedAt DESC
            ");
            $stmt->execute([$user['id']]);
            respond(['ok' => true, 'requests' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        }

        // ---------------- STAFF ----------------

        case 'pending_applications': {
            requireJsonRole('staff');
            $rows = $pdo->query("
                SELECT PA.AppID, G.Name AS GardenerName, P.Label, PA.AppliedAt, PA.RequestType
                FROM PLOT_APPLICATION PA
                JOIN COMMUNITY_GARDENER G ON G.GardenerID = PA.GardenerID
                JOIN PLOT P ON P.PltID = PA.PltID
                WHERE PA.Status = 'Pending' ORDER BY PA.AppliedAt ASC
            ")->fetchAll(PDO::FETCH_ASSOC);
            respond(['ok' => true, 'applications' => $rows]);
        }

        case 'process_application': {
            $user = requireJsonRole('staff');
            $appId = $_POST['app_id'] ?? '';
            $decision = $_POST['decision'] ?? '';
            if (!ctype_digit((string) $appId) || !in_array($decision, ['approve', 'reject'], true)) {
                respond(['ok' => false, 'error' => 'Invalid request.'], 422);
            }

            $app = $pdo->prepare("SELECT GardenerID, PltID, Status, RequestType FROM PLOT_APPLICATION WHERE AppID = ?");
            $app->execute([(int) $appId]);
            $row = $app->fetch(PDO::FETCH_ASSOC);
            if (!$row || $row['Status'] !== 'Pending') respond(['ok' => false, 'error' => 'Application already processed.'], 409);

            $newStatus = $decision === 'approve' ? 'Approved' : 'Rejected';
            $pdo->prepare("UPDATE PLOT_APPLICATION SET Status = ?, CoordID = ? WHERE AppID = ?")
                ->execute([$newStatus, $user['id'], (int) $appId]);

            if ($decision === 'approve') {
                if ($row['RequestType'] === 'Unassign') {
                    $pdo->prepare("UPDATE PLOT SET GardenerID = NULL, Status = 'Available' WHERE PltID = ? AND GardenerID = ?")
                        ->execute([$row['PltID'], $row['GardenerID']]);
                } else {
                    $pdo->prepare("UPDATE PLOT SET GardenerID = ?, Status = 'Occupied' WHERE PltID = ?")
                        ->execute([$row['GardenerID'], $row['PltID']]);
                }
            }
            respond(['ok' => true]);
        }

        case 'pending_resource_txns': {
            requireJsonRole('staff');
            $rows = $pdo->query("
                SELECT T.TxnID, G.Name AS GardenerName, R.Name AS ResourceName, T.Qty, T.RequestedAt
                FROM RESOURCE_TXN T
                JOIN COMMUNITY_GARDENER G ON G.GardenerID = T.GardenerID
                JOIN RESOURCE R ON R.ResourceID = T.ResourceID
                WHERE T.Status = 'Requested' ORDER BY T.RequestedAt ASC
            ")->fetchAll(PDO::FETCH_ASSOC);
            respond(['ok' => true, 'transactions' => $rows]);
        }

        case 'process_resource_txn': {
            $user = requireJsonRole('staff');
            $txnId = $_POST['txn_id'] ?? '';
            $decision = $_POST['decision'] ?? '';
            if (!ctype_digit((string) $txnId) || !in_array($decision, ['approve', 'reject'], true)) {
                respond(['ok' => false, 'error' => 'Invalid request.'], 422);
            }

            $txn = $pdo->prepare("SELECT ResourceID, Qty, Status FROM RESOURCE_TXN WHERE TxnID = ?");
            $txn->execute([(int) $txnId]);
            $row = $txn->fetch(PDO::FETCH_ASSOC);
            if (!$row || $row['Status'] !== 'Requested') respond(['ok' => false, 'error' => 'Already processed.'], 409);

            if ($decision === 'approve') {
                $res = $pdo->prepare("SELECT AvailableQty FROM RESOURCE WHERE ResourceID = ?");
                $res->execute([$row['ResourceID']]);
                $avail = (int) $res->fetchColumn();
                if ($row['Qty'] > $avail) respond(['ok' => false, 'error' => 'Not enough stock left to approve.'], 409);

                $pdo->prepare("UPDATE RESOURCE SET AvailableQty = AvailableQty - ? WHERE ResourceID = ?")
                    ->execute([$row['Qty'], $row['ResourceID']]);
                $pdo->prepare("UPDATE RESOURCE_TXN SET Status = 'Approved', CoordID = ? WHERE TxnID = ?")
                    ->execute([$user['id'], (int) $txnId]);
            } else {
                $pdo->prepare("UPDATE RESOURCE_TXN SET Status = 'Rejected', CoordID = ? WHERE TxnID = ?")
                    ->execute([$user['id'], (int) $txnId]);
            }
            respond(['ok' => true]);
        }

        case 'create_plot': {
            requireJsonRole('staff');
            $label = trim($_POST['label'] ?? '');
            if ($label === '' || strlen($label) > 80) {
                respond(['ok' => false, 'error' => 'Enter a plot name up to 80 characters.'], 422);
            }
            if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9 ._-]*$/', $label)) {
                respond(['ok' => false, 'error' => 'Plot names may use letters, numbers, spaces, dots, hyphens, and underscores.'], 422);
            }

            $duplicate = $pdo->prepare('SELECT 1 FROM PLOT WHERE LOWER(Label) = LOWER(?) LIMIT 1');
            $duplicate->execute([$label]);  
            if ($duplicate->fetchColumn()) {
                respond(['ok' => false, 'error' => 'A plot with that name already exists.'], 409);
            }

            $pdo->prepare("INSERT INTO PLOT (Label, GardenerID, Status) VALUES (?, NULL, 'Available')")
                ->execute([$label]);
            respond(['ok' => true]);
        }

        case 'delete_plot': {
            requireJsonRole('staff');
            $plotId = $_POST['plot_id'] ?? '';
            if (!ctype_digit((string) $plotId)) {
                respond(['ok' => false, 'error' => 'Invalid plot.'], 422);
            }

            $plot = $pdo->prepare('SELECT GardenerID, Status FROM PLOT WHERE PltID = ?');
            $plot->execute([(int) $plotId]);
            $row = $plot->fetch(PDO::FETCH_ASSOC);
            if (!$row) respond(['ok' => false, 'error' => 'Plot not found.'], 404);
            if ($row['GardenerID'] !== null || $row['Status'] !== 'Available') {
                respond(['ok' => false, 'error' => 'Only an available, unassigned plot can be deleted.'], 409);
            }

            $references = $pdo->prepare('SELECT 1 FROM PLOT_APPLICATION WHERE PltID = ? LIMIT 1');
            $references->execute([(int) $plotId]);
            if ($references->fetchColumn()) {
                respond(['ok' => false, 'error' => 'This plot has application history and cannot be deleted.'], 409);
            }

            $pdo->prepare('DELETE FROM PLOT WHERE PltID = ?')->execute([(int) $plotId]);
            respond(['ok' => true]);
        }

        case 'all_plots': {
            requireJsonRole('staff');
            $rows = $pdo->query("
                SELECT P.PltID, P.Label, P.Status, G.Name AS GardenerName
                FROM PLOT P LEFT JOIN COMMUNITY_GARDENER G ON G.GardenerID = P.GardenerID
                ORDER BY P.Label
            ")->fetchAll(PDO::FETCH_ASSOC);
            respond(['ok' => true, 'plots' => $rows]);
        }

        case 'all_resources': {
            requireJsonRole('staff');
            $rows = $pdo->query("
                SELECT R.ResourceID, R.Name, R.TotalQty, R.AvailableQty,
                       GROUP_CONCAT(CONCAT(G.Name, ' (', T.Qty, ')') SEPARATOR ', ') AS Borrowers
                FROM RESOURCE R
                LEFT JOIN RESOURCE_TXN T
                  ON T.ResourceID = R.ResourceID AND T.Status = 'Approved'
                LEFT JOIN COMMUNITY_GARDENER G ON G.GardenerID = T.GardenerID
                GROUP BY R.ResourceID, R.Name, R.TotalQty, R.AvailableQty
                ORDER BY R.Name
            ")
                ->fetchAll(PDO::FETCH_ASSOC);
            respond(['ok' => true, 'resources' => $rows]);
        }

        // ---------------- ADMIN ----------------

        case 'stats': {
            requireJsonRole('admin');
            $count = fn($sql) => (int) $pdo->query($sql)->fetchColumn();
            respond(['ok' => true, 'stats' => [
                'gardeners' => $count("SELECT COUNT(*) FROM COMMUNITY_GARDENER"),
                'coordinators' => $count("SELECT COUNT(*) FROM GARDEN_COORDINATOR"),
                'plots_occupied' => $count("SELECT COUNT(*) FROM PLOT WHERE Status = 'Occupied'"),
                'plots_available' => $count("SELECT COUNT(*) FROM PLOT WHERE Status = 'Available'"),
                'pending_applications' => $count("SELECT COUNT(*) FROM PLOT_APPLICATION WHERE Status = 'Pending'"),
                'pending_resource_txns' => $count("SELECT COUNT(*) FROM RESOURCE_TXN WHERE Status = 'Requested'"),
                'pending_signups' => $count("SELECT COUNT(*) FROM SIGNUP_REQUEST WHERE Status = 'Pending'"),
                'active_listings' => $count("SELECT COUNT(*) FROM EXCHANGE_LISTING WHERE ListingID NOT IN (SELECT ListingID FROM EXCHANGE_ORDER)"),
                'completed_trades' => $count("SELECT COUNT(*) FROM EXCHANGE_ORDER"),
            ]]);
        }

        case 'accounts': {
            requireJsonRole('admin');
            $gardeners = $pdo->query("SELECT GardenerID AS id, Name, Email, COALESCE(NULLIF(Location, ''), 'Not provided') AS Location FROM COMMUNITY_GARDENER ORDER BY Name")->fetchAll(PDO::FETCH_ASSOC);
            $coordinators = $pdo->query("SELECT CoordID AS id, Name, Email, Shift, COALESCE(NULLIF(Location, ''), 'Not provided') AS Location FROM GARDEN_COORDINATOR ORDER BY Name")->fetchAll(PDO::FETCH_ASSOC);
            respond(['ok' => true, 'gardeners' => $gardeners, 'coordinators' => $coordinators]);
        }

        case 'pending_signups': {
            requireJsonRole('admin');
            $rows = $pdo->query("
                SELECT RequestID, FirstName, LastName, Age, Location, Email, Role, Shift, RequestedAt
                FROM SIGNUP_REQUEST WHERE Status = 'Pending' ORDER BY RequestedAt ASC
            ")->fetchAll(PDO::FETCH_ASSOC);
            respond(['ok' => true, 'requests' => $rows]);
        }

        case 'process_signup': {
            $user = requireJsonRole('admin');
            $requestId = $_POST['request_id'] ?? '';
            $decision = $_POST['decision'] ?? '';
            if (!ctype_digit((string) $requestId) || !in_array($decision, ['approve', 'reject'], true)) {
                respond(['ok' => false, 'error' => 'Invalid request.'], 422);
            }

            $stmt = $pdo->prepare("SELECT * FROM SIGNUP_REQUEST WHERE RequestID = ?");
            $stmt->execute([(int) $requestId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row || $row['Status'] !== 'Pending') respond(['ok' => false, 'error' => 'Already processed.'], 409);

            if ($decision === 'approve') {
                // Re-check the email hasn't been taken since the request came in.
                $table = $row['Role'] === 'staff' ? 'GARDEN_COORDINATOR' : 'COMMUNITY_GARDENER';
                $dupe = $pdo->prepare("SELECT 1 FROM $table WHERE Email = ?");
                $dupe->execute([$row['Email']]);
                if ($dupe->fetchColumn()) {
                    respond(['ok' => false, 'error' => 'That email is already in use.'], 409);
                }

                $name = trim($row['FirstName'] . ' ' . $row['LastName']);
                try {
                    if ($row['Role'] === 'staff') {
                        $pdo->prepare("INSERT INTO GARDEN_COORDINATOR (Name, Email, PasswordHash, Shift, Location) VALUES (?, ?, ?, ?, ?)")
                            ->execute([$name, $row['Email'], $row['PasswordHash'], $row['Shift'], $row['Location']]);
                    } else {
                        $pdo->prepare("INSERT INTO COMMUNITY_GARDENER (Name, Email, PasswordHash, Age, Location) VALUES (?, ?, ?, ?, ?)")
                            ->execute([$name, $row['Email'], $row['PasswordHash'], $row['Age'], $row['Location']]);
                    }
                } catch (PDOException $e) {
                    respond(['ok' => false, 'error' => 'That email is already in use.'], 409);
                }
            }

            $newStatus = $decision === 'approve' ? 'Approved' : 'Rejected';
            $pdo->prepare("UPDATE SIGNUP_REQUEST SET Status = ?, ReviewedAt = NOW(), ReviewedBy = ? WHERE RequestID = ?")
                ->execute([$newStatus, $user['id'], (int) $requestId]);

            respond(['ok' => true]);
        }

        case 'add_coordinator': {
            requireJsonRole('admin');
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $shift = trim($_POST['shift'] ?? 'Morning');
            $location = trim($_POST['location'] ?? 'Not provided');

            $errors = [];
            if ($name === '' || mb_strlen($name) > 80) $errors[] = 'Name is required.';
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';
            if (mb_strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
            if (!in_array($shift, ['Morning', 'Afternoon', 'Evening'], true)) $errors[] = 'Invalid shift.';
            if ($errors) respond(['ok' => false, 'errors' => $errors], 422);

            try {
                $pdo->prepare("INSERT INTO GARDEN_COORDINATOR (Name, Email, PasswordHash, Shift, Location) VALUES (?, ?, ?, ?, ?)")
                    ->execute([htmlspecialchars($name, ENT_QUOTES, 'UTF-8'), $email, password_hash($password, PASSWORD_BCRYPT), $shift, $location]);
            } catch (PDOException $e) {
                respond(['ok' => false, 'error' => 'That email is already in use.'], 409);
            }
            respond(['ok' => true]);
        }

        case 'delete_account': {
            requireJsonRole('admin');
            $table = $_POST['table'] ?? '';
            $id = $_POST['id'] ?? '';
            $map = ['gardener' => ['COMMUNITY_GARDENER', 'GardenerID'], 'coordinator' => ['GARDEN_COORDINATOR', 'CoordID']];
            if (!isset($map[$table]) || !ctype_digit((string) $id)) respond(['ok' => false, 'error' => 'Invalid request.'], 422);

            $idNum = (int) $id;

            if ($table === 'gardener') {
                $pdo->beginTransaction();
                try {
                    // Release any plot assigned to this gardener
                    $pdo->prepare("UPDATE PLOT SET GardenerID = NULL, Status = 'Available' WHERE GardenerID = ?")->execute([$idNum]);

                    // Removes associated transaction/log records
                    $pdo->prepare("DELETE FROM PLOT_APPLICATION WHERE GardenerID = ?")->execute([$idNum]);
                    $pdo->prepare("DELETE FROM CROP_LOG WHERE GardenerID = ?")->execute([$idNum]);
                    $pdo->prepare("DELETE FROM RESOURCE_TXN WHERE GardenerID = ?")->execute([$idNum]);
                    $pdo->prepare("DELETE FROM EXCHANGE_ORDER WHERE GardenerID = ?")->execute([$idNum]);

                    // Deletes listings created by the gardener
                    $pdo->prepare("DELETE FROM EXCHANGE_ORDER WHERE ListingID IN (SELECT ListingID FROM EXCHANGE_LISTING WHERE GardenerID = ?)")->execute([$idNum]);
                    $pdo->prepare("DELETE FROM EXCHANGE_LISTING WHERE GardenerID = ?")->execute([$idNum]);

                    // Deletes gardener profile
                    $pdo->prepare("DELETE FROM COMMUNITY_GARDENER WHERE GardenerID = ?")->execute([$idNum]);

                    $pdo->commit();
                } catch (Throwable $t) {
                    $pdo->rollBack();
                    throw $t;
                }
            } else {
                [$tbl, $col] = $map[$table];
                $pdo->prepare("DELETE FROM $tbl WHERE $col = ?")->execute([$idNum]);
            }

            respond(['ok' => true]);
        }

        default:
            respond(['ok' => false, 'error' => 'Unknown action.'], 400);
    }
} catch (Throwable $e) {
    // This will print the exact SQL or PHP error to your screen
    respond(['ok' => false, 'error' => 'Error: ' . $e->getMessage()], 500);
}