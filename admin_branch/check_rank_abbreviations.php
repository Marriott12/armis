<?php
require_once __DIR__ . '/../config.php';

try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== RANK ABBREVIATIONS CHECK ===\n\n";
    
    // Get all ranks
    $stmt = $pdo->query("SELECT id, name, abbreviation FROM ranks ORDER BY id");
    $ranks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $missingAbbr = [];
    
    foreach ($ranks as $rank) {
        $abbr = $rank['abbreviation'] ?: '(NULL/EMPTY)';
        printf("ID: %-3s | Name: %-30s | Abbreviation: %s\n", 
            $rank['id'], 
            $rank['name'], 
            $abbr
        );
        
        if (empty($rank['abbreviation'])) {
            $missingAbbr[] = $rank;
        }
    }
    
    echo "\n=== SUMMARY ===\n";
    echo "Total Ranks: " . count($ranks) . "\n";
    echo "Missing Abbreviations: " . count($missingAbbr) . "\n";
    
    if (count($missingAbbr) > 0) {
        echo "\n⚠️ Ranks without abbreviations:\n";
        foreach ($missingAbbr as $rank) {
            echo "  - ID {$rank['id']}: {$rank['name']}\n";
        }
    } else {
        echo "\n✅ All ranks have abbreviations!\n";
    }
    
    // Check Brigadier Generals specifically
    echo "\n=== BRIGADIER GENERALS DATA CHECK ===\n";
    $staffStmt = $pdo->query("
        SELECT s.service_number, 
               CONCAT(s.first_name, ' ', s.last_name) as name,
               r.name as rank_name,
               r.abbreviation as rank_abbreviation,
               COALESCE(r.abbreviation, r.name) as rank_display,
               s.subWef, s.tempWef, s.attestDate,
               TIMESTAMPDIFF(MONTH, COALESCE(s.subWef, s.tempWef, s.attestDate), CURDATE()) as calculated_months_at_rank,
               (SELECT MAX(sp.date_to) FROM staff_promotions sp WHERE sp.staff_id = s.id AND sp.type = 'promotion') as last_promotion_date,
               (SELECT DATEDIFF(CURDATE(), MAX(sp.date_to)) FROM staff_promotions sp WHERE sp.staff_id = s.id AND sp.type = 'promotion') as days_since_last_promotion
        FROM staff s
        LEFT JOIN ranks r ON s.rank_id = r.id
        WHERE r.name = 'Brigadier General'
        ORDER BY s.service_number
        LIMIT 15
    ");
    
    while ($staff = $staffStmt->fetch(PDO::FETCH_ASSOC)) {
        echo "\nService No: {$staff['service_number']}\n";
        echo "  Name: {$staff['name']}\n";
        echo "  Rank Name: {$staff['rank_name']}\n";
        echo "  Rank Abbreviation: " . ($staff['rank_abbreviation'] ?: 'NULL') . "\n";
        echo "  Rank Display (COALESCE): {$staff['rank_display']}\n";
        echo "  SubWef: " . ($staff['subWef'] ?: 'NULL') . "\n";
        echo "  TempWef: " . ($staff['tempWef'] ?: 'NULL') . "\n";
        echo "  AttestDate: " . ($staff['attestDate'] ?: 'NULL') . "\n";
        echo "  Months at Rank: " . ($staff['calculated_months_at_rank'] ?? 'NULL') . "\n";
        echo "  Last Promotion: " . ($staff['last_promotion_date'] ?: 'NULL') . "\n";
        echo "  Days Since: " . ($staff['days_since_last_promotion'] ?? 'NULL') . "\n";
    }
    
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
