<?php

/*
 * PHPUnit bootstrap.
 *
 * Registers this checkout's tests/ directory on the composer autoloader
 * explicitly. When the repo is checked out as a git worktree with a shared
 * (symlinked) vendor/, composer's generated autoloader resolves Tests\ to
 * the checkout vendor/ physically lives in — this makes the local tests/
 * always win.
 */

$loader = require __DIR__.'/../vendor/autoload.php';

$loader->addPsr4('Tests\\', __DIR__, true);

return $loader;
