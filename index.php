<?php
declare(strict_types=1);
$root=__DIR__;
if (!is_file($root.'/storage/installed.lock') || !is_file($root.'/.env')) {
    header('Location: install/'); exit;
}
header('Location: public/'); exit;
