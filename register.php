<?php
require_once __DIR__ . '/config.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim((string)($_POST['full_name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($fullName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
        $error = 'Enter a name, a valid email, and a password with at least 8 characters.';
    } else {
        try {
            $statement = db()->prepare('INSERT INTO users (full_name, email, password_hash) VALUES (?, ?, ?)');
            $statement->execute([$fullName, $email, password_hash($password, PASSWORD_DEFAULT)]);
            header('Location: login.php?registered=1');
            exit;
        } catch (PDOException $exception) {
            $error = $exception->getCode() === '23000' ? 'That email is already registered.' : 'Unable to create the account right now.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>PNP Portal | Create account</title>
  <style>
    :root { --navy:#102a43; --blue:#1769aa; --gold:#f2b134; --ink:#19324a; --muted:#6b7d8f; --line:#d9e4ec; }
    * { box-sizing:border-box; }
    body { margin:0; min-height:100vh; display:grid; place-items:center; padding:24px; color:var(--ink); background:linear-gradient(135deg,#f4f9fc,#dcecf7); font-family:Arial,sans-serif; }
    .panel { width:min(470px,100%); padding:42px; background:#fff; border-radius:16px; box-shadow:0 24px 70px rgba(16,42,67,.16); }
    .logo { width:88px; height:112px; margin:0 auto 24px; padding:7px; background:#fff; border:4px solid var(--gold); border-radius:12px; }
    .logo img { width:100%; height:100%; object-fit:contain; }
    .eyebrow { margin:0 0 8px; color:var(--blue); font-size:.75rem; font-weight:bold; letter-spacing:.12em; text-transform:uppercase; }
    h1 { margin:0 0 10px; color:var(--navy); font-family:Georgia,'Times New Roman',serif; }
    .intro { margin:0 0 26px; color:var(--muted); line-height:1.6; }
    form { display:grid; gap:16px; }
    label { display:grid; gap:7px; font-size:.82rem; font-weight:bold; }
    input { padding:13px; border:1px solid var(--line); border-radius:7px; font:14px Arial,sans-serif; }
    input:focus { outline:2px solid rgba(23,105,170,.2); border-color:var(--blue); }
    button { padding:14px; color:#fff; background:var(--blue); border:0; border-radius:7px; cursor:pointer; font-weight:bold; }
    .error { margin:0; color:#a83b3b; font-size:.85rem; }
    .switch { margin:24px 0 0; color:var(--muted); text-align:center; font-size:.85rem; }
    a { color:var(--blue); font-weight:bold; }
  </style>
</head>
<body>
  <main class="panel">
    <div class="logo"><img src="https://upload.wikimedia.org/wikipedia/commons/9/98/Philippine_National_Police_seal.svg" alt="Philippine National Police seal"></div>
    <p class="eyebrow">PNP Portal</p>
    <h1>Create account</h1>
    <p class="intro">Create an account to continue to the portal.</p>
    <?php if ($error !== ''): ?><p class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
    <form method="post">
      <label>Full name<input name="full_name" type="text" required autocomplete="name"></label>
      <label>Email address<input name="email" type="email" required autocomplete="email"></label>
      <label>Password<input name="password" type="password" minlength="8" required autocomplete="new-password"></label>
      <button type="submit">Create account</button>
    </form>
    <p class="switch">Already have an account? <a href="login.php">Sign in</a></p>
  </main>
</body>
</html>
