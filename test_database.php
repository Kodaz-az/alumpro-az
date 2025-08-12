<?php
// Database connection test file
// Place this in root directory and access via browser to test DB connection
// DELETE after testing!

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🔍 Database Connection Test</h2>";

try {
    // Test 1: Include config files
    echo "<h3>Step 1: Loading configuration...</h3>";
    require_once 'config/config.php';
    echo "✅ Config loaded successfully<br>";
    
    // Test 2: Check if Database class exists
    echo "<h3>Step 2: Checking Database class...</h3>";
    if (class_exists('Database')) {
        echo "✅ Database class exists<br>";
    } else {
        echo "❌ Database class not found<br>";
        exit;
    }
    
    // Test 3: Get database instance
    echo "<h3>Step 3: Creating database instance...</h3>";
    $db = Database::getInstance();
    echo "✅ Database instance created<br>";
    
    // Test 4: Test connection
    echo "<h3>Step 4: Testing connection...</h3>";
    $connection = $db->getConnection();
    echo "✅ Database connection established<br>";
    
    // Test 5: Get database info
    echo "<h3>Step 5: Database information...</h3>";
    $info = $db->getDatabaseInfo();
    echo "<pre>";
    print_r($info);
    echo "</pre>";
    
    // Test 6: Check if tables exist
    echo "<h3>Step 6: Checking tables...</h3>";
    $required_tables = ['users', 'settings', 'customers', 'orders'];
    
    foreach ($required_tables as $table) {
        if ($db->tableExists($table)) {
            echo "✅ Table '$table' exists<br>";
        } else {
            echo "❌ Table '$table' missing<br>";
        }
    }
    
    // Test 7: Test basic query
    echo "<h3>Step 7: Testing basic query...</h3>";
    try {
        $stmt = $db->query("SELECT 1 as test");
        $result = $stmt->fetch();
        if ($result['test'] == 1) {
            echo "✅ Basic query successful<br>";
        }
    } catch (Exception $e) {
        echo "❌ Basic query failed: " . $e->getMessage() . "<br>";
    }
    
    // Test 8: Test settings functions
    echo "<h3>Step 8: Testing helper functions...</h3>";
    
    if (function_exists('isFeatureEnabled')) {
        echo "✅ isFeatureEnabled() function exists<br>";
        $test_feature = isFeatureEnabled('sms_verification_enabled');
        echo "SMS verification enabled: " . ($test_feature ? 'Yes' : 'No') . "<br>";
    } else {
        echo "❌ isFeatureEnabled() function missing<br>";
    }
    
    if (function_exists('getSetting')) {
        echo "✅ getSetting() function exists<br>";
        $company_name = getSetting('company_name', 'Default');
        echo "Company name: $company_name<br>";
    } else {
        echo "❌ getSetting() function missing<br>";
    }
    
    echo "<h3>🎉 All tests completed successfully!</h3>";
    echo "<p style='color: green; font-weight: bold;'>Database connection is working properly.</p>";
    
} catch (Exception $e) {
    echo "<h3>❌ Test failed!</h3>";
    echo "<p style='color: red;'><strong>Error:</strong> " . $e->getMessage() . "</p>";
    echo "<p><strong>File:</strong> " . $e->getFile() . "</p>";
    echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<hr>";
echo "<p><strong>⚠️ IMPORTANT:</strong> Delete this file after testing!</p>";
echo "<p>File location: " . __FILE__ . "</p>";
?>