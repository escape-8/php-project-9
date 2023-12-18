<?php

require __DIR__ . '/../vendor/autoload.php';

use Database\ConnectionPostgres;
use Database\DbOperation;
use GuzzleHttp\Exception\TransferException;
use Slim\Factory\AppFactory;
use DI\Container;
use Carbon\Carbon;
use Validator\UrlValidator;
use GuzzleHttp\Client;
use DiDom\Document;

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
    $sql = "SELECT urls.id, urls.name, url_checks.status_code, url_checks.last_check
    FROM urls
    LEFT JOIN (
      SELECT url_id, MAX(created_at) AS last_check, status_code
      FROM url_checks
      GROUP BY url_id, status_code
    ) url_checks ON urls.id = url_checks.url_id
    ORDER BY urls.id DESC";
    $data = $pdo->query($sql)->fetchAll();
    $params = ['data' => $data];
    return $this->get('renderer')->render($response, 'urls/sites.phtml', $params);
})->setName('sites');

$app->get('/urls/{id}', function ($request, $response, $args) use ($pdo) {
    $id = $args['id'];
    $sql = "SELECT * FROM urls WHERE id = $id";
    $data = array_merge([], ...$pdo->query($sql)->fetchAll());
    $messages = $this->get('flash')->getMessages();
    $sqlChecks = "SELECT * FROM url_checks WHERE url_id = $id ORDER BY id DESC";
    $checks = $pdo->query($sqlChecks)->fetchAll();
    $params = [
        'data' => $data,
        'flash' => $messages ?? [],
        'checks' => $checks ?? []
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

$app->post('/urls/{url_id}/checks', function ($request, $response, $args) use ($pdo, $router) {
    $id = $args['url_id'];
    $sqlName = "SELECT name FROM urls WHERE id = $id";
    $urlName = array_merge([], ...$pdo->query($sqlName)->fetchAll());

    $client = new Client();
    try {
        $client->request('GET', $urlName['name']);
    } catch (TransferException) {
        $this->get('flash')->addMessage('errors', 'Произошла ошибка при проверке, не удалось подключиться');
        return $response->withRedirect($router->urlFor('page', ['id' => $id]));
    }

    $resp = $client->request('GET', $urlName['name']);
    $statusCode = $resp->getStatusCode();

    $document = new Document($urlName['name'], true);
    $h1 = optional($document->first('h1'))->text();
    $title = optional($document->first('title'))->text();
    $description = optional($document->first('meta[name="description"]'))->getAttribute('content');
    $created_at = Carbon::now();

    $sql = "INSERT INTO url_checks(url_id, status_code, h1, title, description, created_at) 
            VALUES (:id, :statusCode, :h1, :title, :description, :createdAt)";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':id', $id);
    $stmt->bindValue(':statusCode', $statusCode);
    $stmt->bindValue(':h1', $h1);
    $stmt->bindValue(':title', $title);
    $stmt->bindValue(':description', $description);
    $stmt->bindValue(':createdAt', $created_at);
    $stmt->execute();
    $this->get('flash')->addMessage('success', 'Страница успешно проверена');
    return $response->withRedirect($router->urlFor('page', ['id' => $id]));
})->setName('checks');

$app->run();
