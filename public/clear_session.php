<?php
session_start();
session_destroy();
echo "Session cleared. <a href='login.php'>Go to Login</a>";
?>