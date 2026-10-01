<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Auth.php';

$user = Auth::user();
if (!$user) {
    header('Location: login.html');
    exit;
}
if ($user['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>FPE Triage System — Accounts</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body class="has-sidebar">
  <?php $navActive = 'users'; require __DIR__ . '/partials/sidebar.php'; ?>

  <main class="page page-wide">
    <div class="section-label">Accounts</div>
    <div id="accounts-grid" class="account-grid">
      <p class="empty-state">Loading accounts…</p>
    </div>
  </main>

  <!-- New account modal -->
  <div id="new-user-overlay" class="modal-overlay" hidden>
    <div class="modal-card">
      <h2>Create account</h2>
      <form id="new-user-form">
        <label for="u_name">Full name</label>
        <input type="text" id="u_name" required>

        <label for="u_email">Email</label>
        <input type="email" id="u_email" required autocomplete="off">

        <label for="u_password">Temporary password</label>
        <input type="password" id="u_password" required minlength="8" autocomplete="new-password">
        <p class="hint">At least 8 characters. Share it with them privately — there's no email invite yet.</p>

        <label>Role</label>
        <div class="chip-toggle">
          <input type="radio" name="u_role" value="health_worker" id="role_hw" checked>
          <label class="chip-label" for="role_hw">Health worker</label>
          <input type="radio" name="u_role" value="doctor" id="role_doc">
          <label class="chip-label" for="role_doc">Doctor</label>
          <input type="radio" name="u_role" value="admin" id="role_admin">
          <label class="chip-label" for="role_admin">Admin</label>
        </div>

        <p id="new-user-error" class="error" hidden></p>
        <div class="modal-actions">
          <button type="button" id="cancel-new-user-btn" class="btn btn-secondary">Cancel</button>
          <button type="submit" class="btn btn-primary">Create account</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    window.CURRENT_USER = <?= json_encode($user) ?>;
  </script>
  <script src="js/users.js"></script>
</body>
</html>
