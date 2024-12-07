<?php
echo "PHP Version: " . phpversion() . "\n\n";

echo "Loaded Extensions:\n";
print_r(get_loaded_extensions());

echo "\n\nPDO Drivers:\n";
print_r(PDO::getAvailableDrivers());
