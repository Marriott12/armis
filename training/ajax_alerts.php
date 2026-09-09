<?php
/**
 * Lightweight polling endpoint backing the "live" alerts panel on the
 * training dashboard. Returns the same computed-on-the-fly alert data as
 * TrainingManager::getTrainingAlerts() - never a separately-stored, and
 * therefore never a stale, copy.
 */
require_once dirname(__DIR__) . '/shared/module_auth.php';
require_once 'training_manager.php';

bootModuleApi('training');

header('Content-Type: application/json');
$manager = new TrainingManager();
echo json_encode(['alerts' => $manager->getTrainingAlerts()]);
