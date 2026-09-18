<?php
/**
 * Knowledge Lab Equipment Agreement — Login Gateway
 * Redirects unauthenticated users to the Purdue SAML Single Sign-On (Auth Hub).
 */
$return = !empty($_GET['return']) ? $_GET['return'] : '/alma/equipment_agreement/index.php';

// Safe relative return path validation
if (!preg_match('#^/[a-zA-Z0-9/_\-.?&=%]*$#', $return)) {
    $return = '/alma/equipment_agreement/index.php';
}

header('Location: /alma/auth/login.php?return=' . urlencode($return));
exit();
