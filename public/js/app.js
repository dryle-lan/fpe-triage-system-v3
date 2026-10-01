// app.js — placeholder. Split into per-page files once there's more than a login form.

const loginForm = document.getElementById('login-form');

if (loginForm) {
  loginForm.addEventListener('submit', async (e) => {
    e.preventDefault();

    const email = document.getElementById('email').value;
    const password = document.getElementById('password').value;
    const errorEl = document.getElementById('login-error');
    errorEl.hidden = true;

    try {
      const res = await fetch('api/auth.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, password }),
      });

      const data = await res.json();

      if (!res.ok) {
        errorEl.textContent = data.error || 'Login failed';
        errorEl.hidden = false;
        return;
      }

      // TODO: redirect to the right landing page once a dashboard exists.
      window.location.href = 'index.php';
    } catch (err) {
      errorEl.textContent = 'Could not reach the server.';
      errorEl.hidden = false;
    }
  });
}
