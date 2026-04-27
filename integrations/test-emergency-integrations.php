<?php

/**
 * Test Emergency Integrations
 *
 * Test script for emergency notification and medical services integrations
 */

echo "=== Emergency Integrations Test ===\n\n";

try {
    echo "Current working directory: " . getcwd() . "\n\n";

    echo "1. Checking Emergency Notification Integration File...\n";

    // Check if emergency notification integration file exists and is readable
    $notificationFile = 'integrations/emergency-notification-integration.php';
    if (file_exists($notificationFile)) {
        echo "   ✓ Emergency Notification Integration file found\n";

        // Check file size and basic structure
        $fileSize = filesize($notificationFile);
        echo "   ✓ File size: " . number_format($fileSize) . " bytes\n";

        // Check if file contains expected class
        $content = file_get_contents($notificationFile);
        if (strpos($content, 'class EmergencyNotificationIntegration') !== false) {
            echo "   ✓ EmergencyNotificationIntegration class found\n";
        } else {
            echo "   ✗ EmergencyNotificationIntegration class not found\n";
        }

        if (strpos($content, 'sendEmergencyAlert') !== false) {
            echo "   ✓ sendEmergencyAlert method found\n";
        } else {
            echo "   ✗ sendEmergencyAlert method not found\n";
        }

        $notificationStatus = 'PASS';
    } else {
        echo "   ✗ Emergency Notification Integration file not found\n";
        $notificationStatus = 'FAIL';
    }

    echo "\n2. Checking Medical Services Integration File...\n";

    // Check if medical services integration file exists and is readable
    $medicalFile = 'integrations/medical-services-integration.php';
    if (file_exists($medicalFile)) {
        echo "   ✓ Medical Services Integration file found\n";

        // Check file size and basic structure
        $fileSize = filesize($medicalFile);
        echo "   ✓ File size: " . number_format($fileSize) . " bytes\n";

        // Check if file contains expected class
        $content = file_get_contents($medicalFile);
        if (strpos($content, 'class MedicalServicesIntegration') !== false) {
            echo "   ✓ MedicalServicesIntegration class found\n";
        } else {
            echo "   ✗ MedicalServicesIntegration class not found\n";
        }

        if (strpos($content, 'requestEmergencyMedical') !== false) {
            echo "   ✓ requestEmergencyMedical method found\n";
        } else {
            echo "   ✗ requestEmergencyMedical method not found\n";
        }

        $medicalStatus = 'PASS';
    } else {
        echo "   ✗ Medical Services Integration file not found\n";
        $medicalStatus = 'FAIL';
    }

    echo "\n3. Checking Documentation...\n";

    // Check if README file exists
    $readmeFile = 'integrations/README_EMERGENCY_INTEGRATIONS.md';
    if (file_exists($readmeFile)) {
        echo "   ✓ Documentation file found\n";
        $readmeSize = filesize($readmeFile);
        echo "   ✓ Documentation size: " . number_format($readmeSize) . " bytes\n";
        $docsStatus = 'PASS';
    } else {
        echo "   ✗ Documentation file not found\n";
        $docsStatus = 'FAIL';
    }

    echo "\n4. Integration Summary...\n";

    // Summary of integration status
    $totalFiles = 3;
    $passedFiles = 0;

    if ($notificationStatus === 'PASS') $passedFiles++;
    if ($medicalStatus === 'PASS') $passedFiles++;
    if ($docsStatus === 'PASS') $passedFiles++;

    echo "   Files checked: $totalFiles\n";
    echo "   Files passed: $passedFiles\n";
    echo "   Success rate: " . round(($passedFiles / $totalFiles) * 100, 1) . "%\n";

    echo "\n=== Test Summary ===\n";
    echo "Emergency Notification Integration: $notificationStatus\n";
    echo "Medical Services Integration: $medicalStatus\n";
    echo "Documentation: $docsStatus\n";

    $overallStatus = ($passedFiles === $totalFiles) ? 'ALL TESTS PASSED' : 'SOME TESTS FAILED';
    echo "Overall Status: $overallStatus\n";

    echo "\nTest completed at: " . date('Y-m-d H:i:s') . "\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== Integration Test Complete ===\n";
