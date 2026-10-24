<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3).'/app/bootstrap.php';
try { AuthManager::socialLogin(SocialAuth::callback('apple')); redirect('membership-pending'); } catch (Throwable $e) { flash('danger', $e->getMessage()); redirect('login'); }
