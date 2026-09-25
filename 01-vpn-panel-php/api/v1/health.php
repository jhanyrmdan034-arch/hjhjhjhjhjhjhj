<?php require_once dirname(__DIR__,2).'/core/bootstrap.php'; json_response(['ok'=>true,'version'=>app_version(),'time'=>gmdate('c')]);
