<?php
/**
 * Knowledge Lab Equipment Agreement — User Access Manager
 * Superadmins can add, change roles of, and remove users from allowed_users.txt.
 */

$possibleGuards = [
    __DIR__ . '/../auth/auth_guard.php',
    '/var/www/html/webapps/alma/auth/auth_guard.php',
    '/Volumes/alma$/auth/auth_guard.php',
];

$authGuardLoaded = false;
foreach ($possibleGuards as $guardPath) {
    if (file_exists($guardPath)) {
        require_once $guardPath;
        $authGuardLoaded = true;
        break;
    }
}

if (!$authGuardLoaded) {
    die('Error: Centralized Auth Hub (auth_guard.php) could not be loaded.');
}

$aclFile      = __DIR__ . '/allowed_users.txt';
// Requires at least admin role to access this management page
$authUser     = require_auth([
    'allowed_users_file' => $aclFile,
    'require_admin'      => true
]);

$isSuperAdmin = $authUser->isSuperAdmin();
$message      = '';
$messageType  = '';

if ($isSuperAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!auth_verify_csrf_token($csrfToken)) {
        $message = 'Security check failed (invalid or expired CSRF token). Please try again.';
        $messageType = 'error';
    } else {
        $action   = $_POST['action']   ?? '';
        $username = strtolower(trim($_POST['username'] ?? ''));
        $role     = $_POST['role']     ?? 'user';
        if (!in_array($role, ['superadmin', 'admin', 'user'], true)) { 
            $role = 'user'; 
        }
        $validUsername = preg_match('/^[a-z0-9._-]{1,50}$/', $username);
        $acl = auth_load_acl($aclFile);

        if ($action === 'add' || $action === 'edit') {
            if (!$validUsername) {
                $message = 'Invalid username. Use only lowercase letters, numbers, dots, underscores, or dashes (max 50 chars).';
                $messageType = 'error';
            } else {
                $acl[$username] = $role;
                $actionDesc = ($action === 'add' ? "Added user '$username' ($role)" : "Updated user '$username' to role '$role'");
                if (acl_write($aclFile, $acl, $authUser->username, $actionDesc)) {
                    $message = ($action === 'add' ? 'Added ' : 'Updated ') . htmlspecialchars($username) . " as $role.";
                    $messageType = 'success';
                } else {
                    $message = 'Could not write allowed_users.txt — please verify file permissions on the server.';
                    $messageType = 'error';
                }
            }
        } elseif ($action === 'remove') {
            if ($username === $authUser->username) {
                $message = 'You cannot remove yourself.'; 
                $messageType = 'error';
            } elseif (!$validUsername || !isset($acl[$username])) {
                $message = 'User not found.'; 
                $messageType = 'error';
            } else {
                unset($acl[$username]);
                $actionDesc = "Removed user '$username'";
                if (acl_write($aclFile, $acl, $authUser->username, $actionDesc)) {
                    $message = 'Removed ' . htmlspecialchars($username) . '.';
                    $messageType = 'success';
                } else {
                    $message = 'Could not write allowed_users.txt — please verify file permissions on the server.';
                    $messageType = 'error';
                }
            }
        }
    }
}

function acl_write($filePath, array $acl, $actingUser = null, $actionDesc = '') {
    // 1. Attempt self-healing permission repair if file is not writable
    if (file_exists($filePath) && !is_writable($filePath)) {
        @chmod($filePath, 0666);
    }

    // 2. Automated rolling backup of allowed_users.txt before writing
    if (file_exists($filePath) && filesize($filePath) > 0) {
        @copy($filePath, $filePath . '.bak');
        $archiveDir = __DIR__ . '/logs/archives';
        if (!is_dir($archiveDir)) {
            @mkdir($archiveDir, 0777, true);
        }
        if (is_dir($archiveDir) && is_writable($archiveDir)) {
            @copy($filePath, $archiveDir . '/allowed_users_' . date('Y_m_d_His') . '.bak');
        }
    }

    $lines = [
        '# Knowledge Lab Equipment Agreement — Access Control',
        '# Format: purdue_username[:superadmin|:admin|:user]',
        '# superadmin — can access admin dashboard & manage users',
        '# admin      — can access admin dashboard & reports',
        '# user       — can unlock kiosk check-in screen (read-only for patrons)',
        '# Lines starting with # are comments.',
        '',
    ];
    $order = ['superadmin' => [], 'admin' => [], 'user' => []];
    foreach ($acl as $u => $r) { 
        if (isset($order[$r])) {
            $order[$r][] = $u; 
        } else {
            $order['user'][] = $u;
        }
    }
    foreach ($order as $role => $users) {
        sort($users);
        foreach ($users as $u) { 
            $lines[] = "$u:$role"; 
        }
    }
    $lines[] = '';
    $content = implode("\n", $lines);

    // Try with exclusive lock, fallback to standard write if filesystem lock fails
    $res = @file_put_contents($filePath, $content, LOCK_EX);
    if ($res === false) {
        $res = @file_put_contents($filePath, $content);
    }

    if ($res !== false) {
        // Enforce 0666 permissions so web server and CLI retain shared access
        @chmod($filePath, 0666);

        // Record entry in user audit log
        $logDir = __DIR__ . '/logs';
        if (is_dir($logDir) && is_writable($logDir)) {
            $actor = $actingUser ?? 'system';
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $logEntry = sprintf(
                "[%s] [USER_MGR] Actor: %s (%s) | Action: %s | Total: %d users\n",
                date('Y-m-d H:i:s'),
                $actor,
                $ip,
                $actionDesc ?: 'Updated ACL',
                count($acl)
            );
            @file_put_contents($logDir . '/user_management.log', $logEntry, FILE_APPEND | LOCK_EX);
        }
        return true;
    }
    return false;
}

$acl = auth_load_acl($aclFile);

// Pre-flight file permission check & auto-healing
$aclWritable = is_writable($aclFile);
if (!$aclWritable && file_exists($aclFile)) {
    @chmod($aclFile, 0666);
    $aclWritable = is_writable($aclFile);
}

$roleLabels = [
    'superadmin' => ['label' => 'Super Admin', 'desc' => 'Admin dashboard + manage users'],
    'admin'      => ['label' => 'Admin',        'desc' => 'Admin dashboard & reports'],
    'user'       => ['label' => 'User',         'desc' => 'Kiosk check-in access only'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users — Equipment Agreement</title>
    <link rel="stylesheet" href="styles.css?v=<?= filemtime('styles.css') ?>">
    <style>
        .users-container {
            max-width: 960px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        .pu-card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
            overflow: hidden;
            border: 1px solid #e0ded7;
        }
        .pu-card-header {
            background: #2b2b2b;
            color: #fff;
            padding: 1.2rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .pu-card-header h2 {
            margin: 0;
            font-size: 1.3rem;
            color: #CFB991;
        }
        .pu-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }
        .pu-table th {
            background: #f7f6f2;
            padding: 0.85rem 1.2rem;
            font-weight: 600;
            color: #1a1a1a;
            border-bottom: 2px solid #ddd8cd;
            font-size: 0.9rem;
        }
        .pu-table td {
            padding: 0.9rem 1.2rem;
            border-bottom: 1px solid #eeebe3;
            vertical-align: middle;
            font-size: 0.95rem;
        }
        .pu-table tr:hover {
            background: #faf9f6;
        }
        .you-badge {
            background: #CFB991;
            color: #1a1a1a;
            font-size: 0.7rem;
            font-weight: 700;
            padding: 0.15rem 0.45rem;
            border-radius: 99px;
            margin-left: 0.4rem;
            text-transform: uppercase;
        }
        .role-chip {
            display: inline-block;
            padding: 0.2rem 0.6rem;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 700;
        }
        .role-chip.superadmin {
            background: #CFB991;
            color: #1a1a1a;
        }
        .role-chip.admin {
            background: #2e2e1e;
            color: #CFB991;
            border: 1px solid #CFB991;
        }
        .role-chip.user {
            background: #F0EAD8;
            color: #1a1a1a;
            border: 1px solid #bbb7a8;
        }
        .add-form {
            padding: 1.5rem;
            background: #F0EAD8;
            border-top: 2px solid #ddd8cd;
            display: flex;
            gap: 1rem;
            align-items: flex-end;
            flex-wrap: wrap;
        }
        .add-form label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 0.35rem;
            color: #1a1a1a;
        }
        .pu-input, .pu-select {
            padding: 0.5rem 0.75rem;
            border: 1px solid #bbb7a8;
            border-radius: 4px;
            font-size: 0.95rem;
            background: #fff;
        }
        .pu-btn {
            padding: 0.55rem 1.1rem;
            border: none;
            border-radius: 4px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: background 0.15s ease;
        }
        .pu-btn-primary {
            background: #8E6F3E;
            color: #fff;
        }
        .pu-btn-primary:hover {
            background: #735930;
        }
        .pu-btn-muted {
            background: #e5e3dc;
            color: #1a1a1a;
            border: 1px solid #ccc8bd;
        }
        .pu-btn-muted:hover {
            background: #d8d5cb;
        }
        .pu-btn-danger {
            background: #c0392b;
            color: #fff;
        }
        .pu-btn-danger:hover {
            background: #a93226;
        }
        .pu-alert {
            padding: 0.85rem 1.2rem;
            border-radius: 6px;
            margin-bottom: 1.2rem;
            font-size: 0.95rem;
        }
        .pu-alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }
        .pu-alert-error {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
        }
        .pu-alert-gold {
            background: #fff8e1;
            color: #7b3300;
            border: 1px solid #ffe082;
        }
        .read-only-notice {
            padding: 1rem 1.5rem;
            background: #F0EAD8;
            color: #5C4F2A;
            font-size: 0.9rem;
            border-top: 1px solid #dddad1;
            font-style: italic;
        }
        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0,0,0,0);
            white-space: nowrap;
            border: 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <img src="LSIS_H-Full-RGB_1.jpg" alt="Purdue Libraries Logo" class="logo">
        <h1>Equipment Agreement — User Access Manager</h1>
        <div class="header-buttons">
            <span style="color: #fff; margin-right: 1rem; font-size: 0.95rem; align-self: center;">Logged in as: <strong><?= htmlspecialchars($authUser->displayName ?: $authUser->username) ?></strong> (<?= htmlspecialchars(ucfirst($authUser->role)) ?>)</span>
            <a href="admin.php" class="button">Back to Admin Dashboard</a>
            <a href="logout.php" class="button">Logout</a>
        </div>
    </div>

    <div class="users-container">
        <?php if ($message): ?>
            <div class="pu-alert pu-alert-<?= $messageType ?>" role="alert">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if (!$aclWritable): ?>
            <div class="pu-alert pu-alert-error" role="alert">
                <strong>Server File Permission Notice:</strong> <code>allowed_users.txt</code> is not currently writable by the web server. Please verify write permissions (<code>chmod 666</code>) on the server to save changes.
            </div>
        <?php endif; ?>

        <?php if (!$isSuperAdmin): ?>
            <div class="pu-alert pu-alert-gold">
                You have admin dashboard access. Only superadmins can add, edit, or remove users from the access list.
            </div>
        <?php endif; ?>

        <div class="pu-card">
            <div class="pu-card-header">
                <h2>Authorized Staff & Admins (<?= count($acl) ?>)</h2>
            </div>

            <table class="pu-table">
                <thead>
                    <tr>
                        <th scope="col">Purdue Username</th>
                        <th scope="col">Access Role</th>
                        <?php if ($isSuperAdmin): ?>
                            <th scope="col">Change Role</th>
                            <th scope="col">Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php
                $rankOrder = ['superadmin' => 0, 'admin' => 1, 'user' => 2];
                uksort($acl, function($a, $b) use ($acl, $rankOrder) {
                    $ra = $rankOrder[$acl[$a]] ?? 9;
                    $rb = $rankOrder[$acl[$b]] ?? 9;
                    return $ra !== $rb ? $ra - $rb : strcmp($a, $b);
                });

                foreach ($acl as $user => $role):
                    $meta  = $roleLabels[$role] ?? $roleLabels['user'];
                    $isYou = ($user === $authUser->username);
                ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($user) ?></strong>
                        <?php if ($isYou): ?>
                            <span class="you-badge">you</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="role-chip <?= htmlspecialchars($role) ?>"><?= htmlspecialchars($meta['label']) ?></span>
                        <span style="font-size: 0.85rem; color: #595959; margin-left: 0.4rem;">
                            <?= htmlspecialchars($meta['desc']) ?>
                        </span>
                    </td>
                    <?php if ($isSuperAdmin): ?>
                    <td>
                        <form method="post" style="display:inline-flex; align-items:center; gap:0.4rem;">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(auth_get_csrf_token()) ?>">
                            <input type="hidden" name="action"   value="edit">
                            <input type="hidden" name="username" value="<?= htmlspecialchars($user) ?>">
                            <label for="role-<?= htmlspecialchars($user) ?>" class="sr-only">Role for <?= htmlspecialchars($user) ?></label>
                            <select name="role" id="role-<?= htmlspecialchars($user) ?>" class="pu-select">
                                <?php foreach (['superadmin' => 'Super Admin', 'admin' => 'Admin', 'user' => 'User'] as $r => $lbl): ?>
                                    <option value="<?= $r ?>" <?= $role === $r ? 'selected' : '' ?>><?= $lbl ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="pu-btn pu-btn-muted">Save</button>
                        </form>
                    </td>
                    <td>
                        <?php if (!$isYou): ?>
                            <form method="post" style="display:inline;"
                                  onsubmit="return confirm('Remove <?= htmlspecialchars(addslashes($user)) ?>?\nThey will immediately lose access.')">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(auth_get_csrf_token()) ?>">
                                <input type="hidden" name="action"   value="remove">
                                <input type="hidden" name="username" value="<?= htmlspecialchars($user) ?>">
                                <button type="submit" class="pu-btn pu-btn-danger">Remove</button>
                            </form>
                        <?php else: ?>
                            <span style="color:#888; font-size:0.85rem;">—</span>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($isSuperAdmin): ?>
            <form method="post" class="add-form" aria-label="Add a new user">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(auth_get_csrf_token()) ?>">
                <input type="hidden" name="action" value="add">
                <div>
                    <label for="new-username">Purdue Username (Career Account)</label>
                    <input type="text" id="new-username" name="username" class="pu-input"
                           placeholder="e.g. jdoe" pattern="[a-z0-9._-]+"
                           maxlength="50" required autocomplete="off"
                           style="width: 220px;">
                </div>
                <div>
                    <label for="new-role">Assigned Role</label>
                    <select id="new-role" name="role" class="pu-select">
                        <option value="user">User (Kiosk check-in only)</option>
                        <option value="admin">Admin (Dashboard & Reports)</option>
                        <option value="superadmin">Super Admin (Full + User Management)</option>
                    </select>
                </div>
                <div>
                    <button type="submit" class="pu-btn pu-btn-primary">Add User</button>
                </div>
            </form>
            <?php else: ?>
                <div class="read-only-notice">Contact a superadmin to add or remove users from this system.</div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
