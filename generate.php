<?php
// Run this script once to generate hashed passwords
echo "Hashed password for 'admin123': " . password_hash('admin123', PASSWORD_DEFAULT) . "\n";
echo "Hashed password for 'user123': " . password_hash('user123', PASSWORD_DEFAULT) . "\n";
?>