<?php

require __DIR__ . '/../vendor/autoload.php';

use Database\ConnectionPostgres;
use Database\DbOperation;
use Slim\Factory\AppFactory;
use DI\Container;
use Carbon\Carbon;
use Validator\UrlValidator;

session_start();

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

$pdo = ConnectionPostgres::get()->connect();

$container = new Container();
$container->set('renderer', function () {
    return new Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});
$container->set('flash', function () {
    return new Slim\Flash\Messages();
});

$app = AppFactory::createFromContainer($container);
$app->addErrorMiddleware(true, true, true);

$router = $app->getRouteCollector()->getRouteParser();

$app->get('/', function ($request, $response) {
    $params = [];
    return $this->get('renderer')->render($response, 'index.phtml', $params);
})->setName('main');

$app->get('/urls', function ($request, $response) use ($pdo) {
    $sql = "SELECT * FROM urls";
    $data = $pdo->query($sql)->fetchAll();
    $params = ['data' => $data];
    return $this->get('renderer')->render($response, 'urls/sites.phtml', $params);
})->setName('sites');

$app->get('/urls/{id}', function ($request, $response, $args) use ($pdo) {
    $id = $args['id'];
    $sql = "SELECT * FROM urls WHERE id = $id";
    $data = array_merge([], ...$pdo->query($sql)->fetchAll());
    $messages = $this->get('flash')->getMessages();
    $params = [
        'data' => $data,
        'flash' => $messages ?? []
    ];
    return $this->get('renderer')->render($response, 'urls/show.phtml', $params);
})->setName('page');

$app->post('/urls', function ($request, $response) use ($pdo, $router) {
    $data = $request->getParsedBody();
    $urlValidator = new UrlValidator();
    $url = $data['url']['name'];
    $errors = $urlValidator->validate($url);
    if (count($errors) === 0) {
        $parsedUrl = parse_url($url);
        $name = strtolower($parsedUrl['scheme'] . '://' . $parsedUrl['host']);
        $operation = new DbOperation($pdo);
        $isDuplicate = $operation->isNameDuplicate('urls', $name);
        if ($isDuplicate) {
            $this->get('flash')->addMessage('success', 'Страница уже существует');
            $sqlIdQuery = "SELECT id FROM urls WHERE name = '$name'";
            $idData = array_merge([], ...$pdo->query($sqlIdQuery)->fetchAll());
            return $response->withRedirect($router->urlFor('page', ['id' => $idData['id']]));
        }
        $sql = "INSERT INTO urls(name, created_at) VALUES (:name, :createdAt)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':name', $name);
        $stmt->bindValue(':createdAt', Carbon::now());
        $stmt->execute();
        $this->get('flash')->addMessage('success', 'Страница успешно добавлена');
        $sqlIdQuery = "SELECT id FROM urls WHERE name = '$name'";
        $idData = array_merge([], ...$pdo->query($sqlIdQuery)->fetchAll());
        return $response->withRedirect($router->urlFor('page', ['id' => $idData['id']]));
    }
    $errors['invalidCss'] = 'is-invalid';
    $params = [
        'errors' => $errors,
        'url' => $data['url']
    ];
    return $this->get('renderer')->render($response->withStatus(422), 'index.phtml', $params);
})->setName('add.url');

$app->run();