<?php require_once dirname(__DIR__).'/core/bootstrap.php'; $_SESSION=[]; if(session_status()===PHP_SESSION_ACTIVE)session_destroy(); redirect('/admin/login.php');
