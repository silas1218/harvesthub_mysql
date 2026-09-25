<?php
// Force PHP to show errors on the screen for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$email = $_GET['email'] ?? '';
$token = $_GET['token'] ?? '';

if (!$email || !$token) {
    die("Invalid password reset link. Please request a new one.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HarvestHub — Set New Password</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="login-shell">
  <div class="login-card">
    <div class="login-brand">
      <span class="sprout">🌱</span>
      <h1>HarvestHub</h1>
    </div>
    <h2 class="login-title">Set New Password</h2>
    
    <form id="reset-form" novalidate>
      <input type="hidden" id="email" value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" id="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
      
      <div class="field field-underline">
        <label for="password">New Password</label>
        <input type="password" id="password" required minlength="6">
      </div>
      <button type="submit" class="btn btn-light btn-block">Update Password</button>
      <p class="form-alert" id="reset-alert" role="alert" hidden></p>
    </form>
  </div>
</div>

<script>
document.getElementById('reset-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const alertEl = document.getElementById('reset-alert');
    alertEl.hidden = true;

    try {
        const res = await fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'reset_password',
                email: document.getElementById('email').value,
                token: document.getElementById('token').value,
                password: document.getElementById('password').value
            })
        });
        const data = await res.json();
        
        if (data.ok) {
            alert('Password successfully updated. You can now log in.');
            window.location.href = 'login.php';
        } else {
            alertEl.textContent = data.error;
            alertEl.hidden = false;
        }
    } catch (err) {
        alertEl.textContent = 'Network error. Please try again.';
        alertEl.hidden = false;
    }
});
</script>
</body>
</html>