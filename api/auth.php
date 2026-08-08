<?php

declare(strict_types=1);

require_once __DIR__ . '/../controllers/AuthSession.php';

header('Content-Type: application/json; charset=utf-8');

$authUser = AuthSession::requireUser();
