<?php

// Подключение автозагрузки через composer
require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;
use Slim\Middleware\MethodOverrideMiddleware;

session_start();

$users = App\Generator::generate(100);
$clients = [
    ['name' => 'admin', 'passwordDigest' => hash('sha256', 'secret')],
    ['name' => 'mike', 'passwordDigest' => hash('sha256', 'superpass')],
    ['name' => 'kate', 'passwordDigest' => hash('sha256', 'strongpass')]
];

$container = new Container();
$container->set('renderer', function () {
    // Параметром передается базовая директория в которой будут храниться шаблоны
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});
$container->set('flash', function () {
    return new \Slim\Flash\Messages();
});

AppFactory::setContainer($container);
$app = AppFactory::create();
$app->addErrorMiddleware(true, true, true);
$app->add(MethodOverrideMiddleware::class);

$app->get('/', function ($request, $response){
    $flash = $this->get('flash')->getMessages();

    $params = [
        'currentUser' => $_SESSION['user'] ?? null,
        'flash' => $flash
    ];
	return $this->get('renderer')->render($response, 'main.phtml', $params);	
});
$app->get('/users', function ($request, $response) use ($users) {
	$params = ['users' => $users];
	return $this->get('renderer')->render($response, 'users/index.phtml', $params);
});

$app->post('/users', function ($request, $response) {
    return $response->write('POST /users');
});

$app->get('/users/{id}', function ($request, $response, $args) use ($users) {
    // Указанный путь считается относительно базовой директории для шаблонов, заданной на этапе конфигурации
    // $this доступен внутри анонимной функции благодаря http://php.net/manual/ru/closure.bindto.php
	$id = (int) $args['id'];
    $user = collect($users)->firstWhere('id', $id);
    $params = ['user' => $user];
	return $this->get('renderer')->render($response, 'users/show.phtml', $params);
});

$app->get('/courses', function ($request, $response) use ($courses) {
    $params = [
        'courses' => $courses
    ];
    return $this->get('renderer')->render($response, 'courses/index.phtml', $params);
});

$app->post('/session', function ($request, $response) use ($clients) {
    $userData = $request->getParsedBodyParam('user');

    $user = collect($clients)->first(function ($user) use ($userData) {
        return $user['name'] == $userData['name'] && $user['passwordDigest'] == hash('sha256', $userData['password']);
    });
    
    if ($user) {
        $_SESSION['user'] = $user;
    } else {
        $this->get('flash')->addMessage('error', 'Wrong password or name');
    }

    return $response->withRedirect('/'); 
});

$app->delete('/session', function ($request, $response) {
    $_SESSION = [];
    session_destroy();
    return $response->withRedirect('/');
});


$app->run();

