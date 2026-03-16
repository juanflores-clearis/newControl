<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../app/Services/AnalyzerService.php';
require_once __DIR__ . '/../app/Services/EmailNotifier.php';
require_once __DIR__ . '/../app/Helpers/Logger.php';
EmailNotifier::sendDailyReport();