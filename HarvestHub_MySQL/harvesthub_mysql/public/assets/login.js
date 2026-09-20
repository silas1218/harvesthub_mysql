// login.js — role tab switching + AJAX login

const roleInput = document.getElementById('role');
const demoHint = document.getElementById('demo-hint');
const alertEl = document.getElementById('login-alert');

const demoAccounts = {
  customer: 'maria@harvesthub.test',
  staff: 'coordinator@harvesthub.test',
  admin: 'admin@harvesthub.test',
};

document.querySelectorAll('#role-tabs .role-tab').forEach((btn) => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('#role-tabs .role-tab').forEach((b) => b.classList.remove('active'));
    btn.classList.add('active');
    const role = btn.dataset.role;
    roleInput.value = role;
    demoHint.innerHTML = `Demo account: <code>${demoAccounts[role]}</code> / <code>demo1234</code>`;
  });
});

document.getElementById('login-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  alertEl.hidden = true;

  const formData = new URLSearchParams({
    action: 'login',
    role: roleInput.value,
    email: document.getElementById('email').value.trim(),
    password: document.getElementById('password').value,
  });

  try {
    const res = await fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: formData,
    });
    const data = await res.json();

    if (data.ok) {
      window.location.href = data.redirect;
    } else {
      alertEl.textContent = data.error || 'Login failed.';
      alertEl.hidden = false;
    }
  } catch (err) {
    alertEl.textContent = 'Network error. Please try again.';
    alertEl.hidden = false;
  }
});
