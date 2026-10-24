<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3).'/app/bootstrap.php';
try { SocialAuth::begin('google'); } catch (Throwable $e) { flash('danger', $e->getMessage()); redirect('login'); }
