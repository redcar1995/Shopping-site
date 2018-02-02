<?php

use Pimcore\Model\Tool\TmpStore;

$tmpStoreId = 'pimcore5-build-85-notice';
if (!TmpStore::get($tmpStoreId)) {
    TmpStore::add($tmpStoreId, 'true', null, 86400 * 30);
    echo '<b>You\'re going to install pimcore 5 build 85</b><br />';
    echo 'This build includes a change to admin session management which means you\'ll be logged out during the update process.<br/>';
    echo 'Please re-login after being logged out and continue the update process';
    exit;
} else {
    // deactivate maintenance mode as the session ID won't match after the update
    \Pimcore\Tool\Admin::deactivateMaintenanceMode();
}
