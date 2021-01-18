<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Routing\RouteContext;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();

$app->addErrorMiddleware(true, true, true);

$app->options('/{routes:.+}', function ($request, $response, $args) {
    return $response;
});

$app->add(function ($request, $handler) {
    $response = $handler->handle($request);
    return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
});

class JsonBodyParserMiddleware implements MiddlewareInterface
{
    public function process(Request $request, RequestHandler $handler): Response
    {
        $contentType = $request->getHeaderLine('Content-Type');

        if (strstr($contentType, 'application/json')) {
            $contents = json_decode(file_get_contents('php://input'), true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $request = $request->withParsedBody($contents);
            }
        }

        return $handler->handle($request);
    }
}

$app->add(new JsonBodyParserMiddleware);

$app->get('/notes', function (Request $request, Response $response, $args) {
    $response->getBody()->write(file_get_contents('db.json'));
    return $response;
});

$app->post('/notes', function (Request $request, Response $response, $args) {
    $data = json_decode(file_get_contents('db.json'));
    
    $lastId = $data[count($data) - 1]->id;

    $note = new stdClass;
    $note->id = $lastId + 1;
    $note->text = $request->getParsedBody()["text"];
    array_push($data, $note);

    file_put_contents('db.json', json_encode($data));
    
    $response->getBody()->write(json_encode($data));
    return $response;
});

function findRow($noteId, $data) {
    for ($i = 0; $i < count($data); $i++) {        
        if ($data[$i]->id == $noteId) {
            return $i;
        }
    }
    return null;
}

function getRequestId($request) {
    $routeContext = RouteContext::fromRequest($request);
    $route = $routeContext->getRoute();
    return $route->getArgument('id');
}

$app->patch('/notes/{id:[0-9]+}', function (Request $request, Response $response) {
    $noteId = getRequestId($request);

    $data = json_decode(file_get_contents('db.json'));
    $rowId = findRow($noteId, $data);
    if ($rowId !== null) {
        $data[$rowId]->text = $request->getParsedBody()["text"];
    }
    file_put_contents('db.json', json_encode($data));
    
    $response->getBody()->write(json_encode($data));
    return $response;
});

$app->put('/notes/{id:[0-9]+}', function (Request $request, Response $response) {
    $noteId = getRequestId($request);

    $data = json_decode(file_get_contents('db.json'));
    $rowId = findRow($noteId, $data);
    if ($rowId !== null) {
        $data[$rowId]->text = $request->getParsedBody()["text"];
    }
    file_put_contents('db.json', json_encode($data));
    
    $response->getBody()->write(json_encode($data));
    return $response;
});

$app->delete('/notes/{id:[0-9]+}', function (Request $request, Response $response) {
    $noteId = getRequestId($request);

    $data = json_decode(file_get_contents('db.json'));
    $rowId = findRow($noteId, $data);
    if ($rowId !== null) {
        unset($data[$rowId]);
        $data = array_values($data);
    }
    file_put_contents('db.json', json_encode($data));
    
    $response->getBody()->write(json_encode($data));
    return $response;
});

$app->run();