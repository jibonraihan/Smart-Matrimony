<?php

// Backward-compatible route: logged-in users now use dashboard.php.
header('Location: dashboard.php');
exit;
