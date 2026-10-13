<?php
declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use InfinityBlog\Config\DatabaseConfig;
use InfinityBlog\Config\EnvironmentLoader;
use InfinityBlog\Database\ConnectionFactory;
use InfinityBlog\Posts\PostRepository;
use InfinityBlog\Posts\StudioService;

$env = EnvironmentLoader::loadFile(dirname(__DIR__) . '/.env', getenv());
$config = DatabaseConfig::fromArray($env, 'DB_');
$pdo = ConnectionFactory::create($config);
$posts = new PostRepository($pdo);
$studio = new StudioService($pdo, $posts);

$count = $studio->publishDuePosts();
echo "Scheduled publisher runner executed: {$count} post(s) published.\n";
