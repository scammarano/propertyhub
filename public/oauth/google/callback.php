<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3).'/app/bootstrap.php';
try { AuthManager::socialLogin(SocialAuth::callback('google')); redirect('membership-pending'); } catch (Throwable $e) { flash('danger', $e->getMessage()); redirect('login'); }
