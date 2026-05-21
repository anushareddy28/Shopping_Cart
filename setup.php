<?php
$configFile = __DIR__ . '/api/config.php';
$lockFile   = __DIR__ . '/.setup_complete';

if (file_exists($lockFile)) {
    header('Location: index.html');
    exit;
}

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $dbHost   = trim($_POST['db_host']   ?? '');
    $dbName   = trim($_POST['db_name']   ?? '');
    $dbUser   = trim($_POST['db_user']   ?? '');
    $dbPass   = $_POST['db_pass']        ?? '';

    $adminName  = trim($_POST['admin_name']  ?? '');
    $adminEmail = trim($_POST['admin_email'] ?? '');
    $adminPhone = trim($_POST['admin_phone'] ?? '');
    $adminPass  = $_POST['admin_pass']       ?? '';
    $adminPass2 = $_POST['admin_pass2']      ?? '';

    $taxRate   = trim($_POST['tax_rate']   ?? '5');
    $freeShip  = trim($_POST['free_ship']  ?? '1000');
    $delivery  = trim($_POST['delivery']   ?? '50');

    if ($dbHost === '')    $errors[] = 'Database host is required.';
    if ($dbName === '')    $errors[] = 'Database name is required.';
    if ($dbUser === '')    $errors[] = 'Database username is required.';

    if ($adminName === '')  $errors[] = 'Admin name is required.';
    if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL))
        $errors[] = 'A valid admin email is required.';
    if (strlen($adminPass) < 8)
        $errors[] = 'Admin password must be at least 8 characters.';
    if ($adminPass !== $adminPass2)
        $errors[] = 'Admin passwords do not match.';

    if (!is_numeric($taxRate) || $taxRate < 0)
        $errors[] = 'Tax rate must be a number >= 0.';
    if (!is_numeric($freeShip) || $freeShip < 0)
        $errors[] = 'Free delivery threshold must be a number >= 0.';
    if (!is_numeric($delivery) || $delivery < 0)
        $errors[] = 'Delivery charge must be a number >= 0.';

    if (empty($errors)) {
        try {
            $dsn = "mysql:host={$dbHost};charset=utf8mb4";
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
        } catch (PDOException $e) {
            $errors[] = 'Database connection failed: ' . $e->getMessage();
        }
    }

    if (empty($errors)) {
        try {
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$dbName}`");

            $sqlFile = __DIR__ . '/database/smartcart.sql';
            if (!file_exists($sqlFile)) {
                $errors[] = 'database/smartcart.sql not found.';
            } else {
                $sql = file_get_contents($sqlFile);

                $sql = preg_replace('/^CREATE DATABASE.*$/mi', '', $sql);
                $sql = preg_replace('/^USE .*$/mi', '', $sql);
                $sql = str_replace('CREATE TABLE', 'CREATE TABLE IF NOT EXISTS', $sql);

                $lines = explode("\n", $sql);
                $clean = [];
                $skip  = false;
                foreach ($lines as $line) {
                    if (preg_match('/^INSERT\s+INTO/i', trim($line))) {
                        $skip = true;
                    }
                    if (!$skip) {
                        $clean[] = $line;
                    }
                    if ($skip && str_contains($line, ';')) {
                        $skip = false;
                    }
                }
                $sql = implode("\n", $clean);

                $statements = array_filter(array_map('trim', explode(';', $sql)));
                foreach ($statements as $stmt) {
                    if ($stmt !== '') {
                        $pdo->exec($stmt);
                    }
                }
            }
        } catch (PDOException $e) {
            $errors[] = 'Database setup failed: ' . $e->getMessage();
        }
    }

    if (empty($errors)) {
        try {
            $hash = password_hash($adminPass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare(
                'INSERT INTO users (name, email, password_hash, role, phone) VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([$adminName, $adminEmail, $hash, 'admin', $adminPhone]);
        } catch (PDOException $e) {
            $errors[] = 'Admin account creation failed: ' . $e->getMessage();
        }
    }

    if (empty($errors)) {
        try {
            $sqlFile = __DIR__ . '/database/smartcart.sql';
            $sql = file_get_contents($sqlFile);
            preg_match_all('/INSERT\s+INTO\s+(products|coupons)\s.*?;/si', $sql, $matches);
            foreach ($matches[0] as $insertStmt) {
                $pdo->exec($insertStmt);
            }
        } catch (PDOException $e) {
        }
    }

    if (empty($errors)) {
        $taxFloat = floatval($taxRate) / 100;
        $configContent = "<?php\n";
        $configContent .= "define('DB_HOST', " . var_export($dbHost, true) . ");\n";
        $configContent .= "define('DB_NAME', " . var_export($dbName, true) . ");\n";
        $configContent .= "define('DB_USER', " . var_export($dbUser, true) . ");\n";
        $configContent .= "define('DB_PASS', " . var_export($dbPass, true) . ");\n\n";
        $configContent .= "define('TAX_RATE', {$taxFloat});\n";
        $configContent .= "define('FREE_DELIVERY_THRESHOLD', " . intval($freeShip) . ");\n";
        $configContent .= "define('DELIVERY_CHARGE', " . intval($delivery) . ");\n\n";
        $configContent .= "ini_set('display_errors', 0);\n";
        $configContent .= "error_reporting(0);\n\n";
        $configContent .= "session_start();\n";

        if (file_put_contents($configFile, $configContent) === false) {
            $errors[] = 'Could not write api/config.php — check file permissions.';
        } else {
            file_put_contents($lockFile, date('Y-m-d H:i:s'));
            $success = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup — SmartCart</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        :root{
            --primary:#1f4e5f;--accent:#d9822b;--text:#1f2933;--text-light:#616e7c;
            --border:#d9e2ec;--bg:#ffffff;--bg-light:#f7f9fc;--success:#2f855a;
            --error:#c53030;--radius:6px;
        }
        body{font-family:'Inter',sans-serif;background:var(--bg-light);color:var(--text);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
        .setup-card{background:var(--bg);border:1px solid var(--border);border-radius:var(--radius);width:100%;max-width:580px;padding:40px 36px;box-shadow:0 4px 24px rgba(0,0,0,.06)}
        .setup-logo{text-align:center;font-size:26px;font-weight:700;color:var(--primary);margin-bottom:4px}
        .setup-logo span{color:var(--accent)}
        .setup-sub{text-align:center;font-size:13px;color:var(--text-light);margin-bottom:28px}
        .section-title{font-size:14px;font-weight:600;color:var(--primary);text-transform:uppercase;letter-spacing:.5px;margin:24px 0 12px;padding-bottom:6px;border-bottom:1px solid var(--border)}
        .section-title:first-of-type{margin-top:0}
        .form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
        .form-group{margin-bottom:14px}
        .form-group label{display:block;font-size:12px;font-weight:500;margin-bottom:5px;color:var(--text-light)}
        .form-group input{width:100%;padding:9px 12px;font-size:14px;font-family:inherit;border:1px solid var(--border);border-radius:var(--radius);background:var(--bg);color:var(--text);transition:border-color .2s}
        .form-group input:focus{outline:none;border-color:var(--primary)}
        .form-group .hint{font-size:11px;color:var(--text-light);margin-top:3px}
        .btn-setup{display:block;width:100%;padding:12px;font-size:15px;font-weight:600;font-family:inherit;color:#fff;background:var(--primary);border:none;border-radius:var(--radius);cursor:pointer;margin-top:24px;transition:opacity .2s}
        .btn-setup:hover{opacity:.9}
        .alert{padding:12px 16px;border-radius:var(--radius);margin-bottom:16px;font-size:13px;line-height:1.5}
        .alert-error{background:#fff5f5;color:var(--error);border:1px solid #fed7d7}
        .alert-success{background:#f0fff4;color:var(--success);border:1px solid #c6f6d5}
        .alert-success a{color:var(--primary);font-weight:600}
        .step-dots{display:flex;justify-content:center;gap:8px;margin-bottom:20px}
        .step-dot{width:10px;height:10px;border-radius:50%;background:var(--border)}
        .step-dot.active{background:var(--primary)}
        @media(max-width:520px){
            .form-row{grid-template-columns:1fr}
            .setup-card{padding:28px 20px}
        }
    </style>
</head>
<body>
<div class="setup-card">
    <div class="setup-logo">Smart<span>Cart</span></div>
    <p class="setup-sub">First-run setup — configure your store</p>

    <div class="step-dots">
        <span class="step-dot active"></span>
        <span class="step-dot active"></span>
        <span class="step-dot active"></span>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success">
            Setup complete! Your store is ready.<br>
            <a href="login.html">Log in as admin</a> &nbsp;|&nbsp;
            <a href="index.html">Visit store</a>
        </div>
    <?php else: ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <?php foreach ($errors as $err): ?>
                    <?= htmlspecialchars($err) ?><br>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <div class="section-title">Database Connection</div>
            <div class="form-row">
                <div class="form-group">
                    <label for="db_host">Host</label>
                    <input type="text" id="db_host" name="db_host" value="<?= htmlspecialchars($_POST['db_host'] ?? '') ?>" placeholder="e.g. localhost" required>
                </div>
                <div class="form-group">
                    <label for="db_name">Database Name</label>
                    <input type="text" id="db_name" name="db_name" value="<?= htmlspecialchars($_POST['db_name'] ?? '') ?>" placeholder="e.g. smartcart_db" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="db_user">Username</label>
                    <input type="text" id="db_user" name="db_user" value="<?= htmlspecialchars($_POST['db_user'] ?? '') ?>" placeholder="e.g. root" required>
                </div>
                <div class="form-group">
                    <label for="db_pass">Password</label>
                    <input type="password" id="db_pass" name="db_pass" placeholder="Database password">
                    <div class="hint">Leave blank if none</div>
                </div>
            </div>

            <div class="section-title">Admin Account</div>
            <div class="form-row">
                <div class="form-group">
                    <label for="admin_name">Full Name</label>
                    <input type="text" id="admin_name" name="admin_name" value="<?= htmlspecialchars($_POST['admin_name'] ?? '') ?>" placeholder="e.g. John Doe" required>
                </div>
                <div class="form-group">
                    <label for="admin_email">Email</label>
                    <input type="email" id="admin_email" name="admin_email" value="<?= htmlspecialchars($_POST['admin_email'] ?? '') ?>" placeholder="admin@example.com" required>
                </div>
            </div>
            <div class="form-group">
                <label for="admin_phone">Phone (optional)</label>
                <input type="text" id="admin_phone" name="admin_phone" value="<?= htmlspecialchars($_POST['admin_phone'] ?? '') ?>" placeholder="e.g. 9876543210">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="admin_pass">Password</label>
                    <input type="password" id="admin_pass" name="admin_pass" placeholder="Min 8 characters" required>
                </div>
                <div class="form-group">
                    <label for="admin_pass2">Confirm Password</label>
                    <input type="password" id="admin_pass2" name="admin_pass2" placeholder="Repeat password" required>
                </div>
            </div>

            <div class="section-title">Store Settings</div>
            <div class="form-row">
                <div class="form-group">
                    <label for="tax_rate">Tax Rate (%)</label>
                    <input type="number" id="tax_rate" name="tax_rate" value="<?= htmlspecialchars($_POST['tax_rate'] ?? '5') ?>" step="0.01" min="0" required>
                </div>
                <div class="form-group">
                    <label for="delivery">Delivery Charge (₹)</label>
                    <input type="number" id="delivery" name="delivery" value="<?= htmlspecialchars($_POST['delivery'] ?? '50') ?>" min="0" required>
                </div>
            </div>
            <div class="form-group">
                <label for="free_ship">Free Delivery Threshold (₹)</label>
                <input type="number" id="free_ship" name="free_ship" value="<?= htmlspecialchars($_POST['free_ship'] ?? '1000') ?>" min="0" required>
                <div class="hint">Orders above this amount get free delivery</div>
            </div>

            <button type="submit" class="btn-setup">Complete Setup</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>

