<?php
require_once __DIR__ . '/config.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$registered = isset($_GET['registered']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $error = 'Enter a valid email and password.';
    } else {
        try {
            $statement = db()->prepare('SELECT id, full_name, password_hash FROM users WHERE email = ? LIMIT 1');
            $statement->execute([$email]);
            $user = $statement->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['full_name'] = $user['full_name'];
                header('Location: dashboard.php');
                exit;
            }
            $error = 'Invalid email or password.';
        } catch (PDOException $exception) {
            $error = 'Database is not ready. Import database.sql first.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>PNP Portal | Sign in</title>
  <style>
    :root { --navy:#102a43; --blue:#1769aa; --gold:#f2b134; --ink:#19324a; --muted:#6b7d8f; --line:#d9e4ec; }
    * { box-sizing:border-box; }
    body { margin:0; min-height:100vh; display:grid; place-items:center; padding:24px; color:var(--ink); background:linear-gradient(135deg,#f4f9fc,#dcecf7); font-family:Arial,sans-serif; }
    .shell { width:min(900px,100%); display:grid; grid-template-columns:.85fr 1.15fr; overflow:hidden; background:#fff; border-radius:18px; box-shadow:0 24px 70px rgba(16,42,67,.16); }
    .brand { display:grid; place-items:center; min-height:560px; padding:34px; background:var(--navy); }
    .brand-content { display:flex; flex-direction:column; align-items:center; text-align:center; }
    .logo { width:150px; height:190px; padding:10px; background:#fff; border:5px solid var(--gold); border-radius:14px; }
    .logo img { width:100%; height:100%; object-fit:contain; }
    .system-name { max-width:260px; margin:18px 0 0; color:#d7e4ee; font-size:.92rem; font-weight:bold; letter-spacing:.1em; line-height:1.45; text-transform:uppercase; }
    .form-panel { padding:64px clamp(30px,6vw,78px); }
    .eyebrow { margin:0 0 10px; color:var(--blue); font-size:.75rem; font-weight:bold; letter-spacing:.12em; text-transform:uppercase; }
    h1 { margin:0 0 10px; color:var(--navy); font-family:Georgia,'Times New Roman',serif; font-size:2.2rem; }
    .intro { margin:0 0 28px; color:var(--muted); line-height:1.6; }
    form { display:grid; gap:18px; }
    label { display:grid; gap:8px; font-size:.82rem; font-weight:bold; }
    input { padding:14px 15px; border:1px solid var(--line); border-radius:8px; font:15px Arial,sans-serif; }
    input:focus { outline:2px solid rgba(23,105,170,.16); border-color:var(--blue); }
    button { padding:15px; color:#fff; background:var(--blue); border:0; border-radius:8px; cursor:pointer; font-weight:bold; }
    .error, .success { margin:0 0 14px; font-size:.84rem; }
    .error { color:#a83b3b; }
    .success { color:#28734f; }
    .switch { margin:25px 0 0; color:var(--muted); text-align:center; font-size:.84rem; }
    a { color:var(--blue); font-weight:bold; }
    @media (max-width:700px) { body { padding:12px; } .shell { grid-template-columns:1fr; } .brand { min-height:245px; } .logo { width:105px; height:132px; } .system-name { max-width:290px; margin-top:12px; font-size:.78rem; } .form-panel { padding:38px 28px 42px; } }
  </style>
</head>
<body>
  <main class="shell">
    <section class="brand" aria-label="PNP branding">
      <div class="brand-content">
        <div class="logo"><img src="https://upload.wikimedia.org/wikipedia/commons/9/98/Philippine_National_Police_seal.svg" alt="Philippine National Police seal"></div>
        <p class="system-name">PNP Electronic Document Filing System</p>
      </div>
    </section>
    <section class="form-panel">
      <p class="eyebrow">PNP Portal</p>
      <h1>Welcome back</h1>
      <p class="intro">Enter your credentials to access your account.</p>
      <?php if ($registered): ?><p class="success">Account created. You can sign in now.</p><?php endif; ?>
      <?php if ($error !== ''): ?><p class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
      <form method="post">
        <label>Email address<input name="email" type="email" placeholder="you@example.com" required autocomplete="email"></label>
        <label>Password<input name="password" type="password" placeholder="Enter your password" required autocomplete="current-password"></label>
        <button type="submit">Sign in</button>
      </form>
      <p class="switch">Don't have an account? <a href="register.php">Create account</a></p>
    </section>
  </main>
</body>
</html>
