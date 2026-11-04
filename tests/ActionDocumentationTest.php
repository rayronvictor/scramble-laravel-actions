<?php

use Illuminate\Support\Facades\Route;
use RayronVictor\ScrambleLaravelActions\Tests\Fixtures;

function operation(array $docs, string $path, string $method): array
{
    return $docs['paths'][$path][$method] ?? throw new RuntimeException("Operation [$method $path] is not documented.");
}

beforeEach(function () {
    Route::prefix('api')->group(function () {
        Route::get('todos', Fixtures\ListTodos::class);
        Route::post('todos', Fixtures\CreateTodo::class);
        Route::get('todos/search', Fixtures\SearchTodos::class);
        Route::get('todos/count', Fixtures\CountTodos::class);
        Route::get('todos/custom', [Fixtures\CountTodos::class, 'custom']);
        Route::get('todos/{todo}', Fixtures\ShowTodo::class);
        Route::put('todos/{todo}', Fixtures\UpdateTodoWithInjectedRules::class);
        Route::delete('todos/{todo}', Fixtures\DeleteTodo::class);
        Route::get('ping', Fixtures\PingAction::class);
        Route::get('hidden', Fixtures\HiddenAction::class);
    });
});

it('documents the request body from the action rules', function () {
    $docs = $this->generateDocs();

    expect(operation($docs, '/todos', 'post')['requestBody']['content']['application/json']['schema'])
        ->toBe(['$ref' => '#/components/schemas/CreateTodoRequest'])
        ->and($docs['components']['schemas']['CreateTodoRequest'])->toMatchArray([
            'type' => 'object',
            'properties' => [
                'title' => ['type' => 'string', 'description' => 'The title of the todo.', 'maxLength' => 255],
                'done' => ['type' => 'boolean'],
            ],
            'required' => ['title'],
        ]);
});

it('documents the action rules as query parameters on GET routes', function () {
    $parameters = operation($this->generateDocs(), '/todos/count', 'get')['parameters'];

    expect($parameters)->toContain([
        'name' => 'status',
        'in' => 'query',
        'required' => true,
        'schema' => ['type' => 'string'],
    ]);
});

it('evaluates rules that depend on injected dependencies', function () {
    $docs = $this->generateDocs();

    expect($docs['components']['schemas']['UpdateTodoWithInjectedRulesRequest']['properties'])->toEqual([
        'title' => ['type' => 'string'],
        'priority' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 5],
    ]);
});

it('documents path parameters from the handle method', function () {
    $operation = operation($this->generateDocs(), '/todos/{todo}', 'get');

    expect($operation['parameters'])->toBe([[
        'name' => 'todo',
        'in' => 'path',
        'required' => true,
        'description' => 'The todo ID.',
        'schema' => ['type' => 'integer'],
    ]]);
});

it('documents request parameters read from the action request', function () {
    $operation = operation($this->generateDocs(), '/todos/search', 'get');

    expect($operation['parameters'])->toBe([[
        'name' => 'q',
        'in' => 'query',
        'description' => 'The search term.',
        'schema' => ['type' => 'string'],
    ]])->and($operation['responses'])->not->toHaveKey('422');
});

it('documents the response returned by jsonResponse', function () {
    $operation = operation($this->generateDocs(), '/todos', 'post');

    expect(array_keys($operation['responses']))->toBe([200, 422])
        ->and($operation['responses'][200]['content']['application/json']['schema']['properties']['data'])
        ->toBe(['$ref' => '#/components/schemas/TodoResource']);
});

it('passes the action result to jsonResponse', function () {
    $operation = operation($this->generateDocs(), '/todos/count', 'get');

    expect($operation['responses'][200]['content']['application/json']['schema'])->toBe([
        'type' => 'object',
        'properties' => ['count' => ['type' => 'integer', 'const' => 42]],
        'required' => ['count'],
    ]);
});

it('replaces the result response with the jsonResponse one', function () {
    $operation = operation($this->generateDocs(), '/todos/{todo}', 'delete');

    expect(array_keys($operation['responses']))->toBe([204, 403, 401]);
});

it('documents the response returned by the route method when there is no jsonResponse', function () {
    $operation = operation($this->generateDocs(), '/ping', 'get');

    expect($operation['responses'][200]['content']['application/json']['schema'])->toBe([
        'type' => 'object',
        'properties' => ['pong' => ['type' => 'boolean']],
        'required' => ['pong'],
    ]);
});

it('documents authorization errors only when the action may deny access', function () {
    $docs = $this->generateDocs();

    expect(operation($docs, '/todos/{todo}', 'delete')['responses'])->toHaveKey(403)
        ->and(operation($docs, '/ping', 'get')['responses'])->not->toHaveKey(403);
});

it('uses the class PHPDoc as summary and description', function () {
    $docs = $this->generateDocs();

    expect(operation($docs, '/todos', 'post'))
        ->toMatchArray(['summary' => 'Create a todo', 'description' => 'Stores a new todo for the current user.'])
        ->and(operation($docs, '/todos', 'get')['summary'])->toBe('List todos')
        ->and(operation($docs, '/todos/{todo}', 'get')['summary'])->toBe('Show a todo');
});

it('keeps the route method PHPDoc tags when using the class PHPDoc', function () {
    $operation = operation($this->generateDocs(), '/todos', 'get');

    expect($operation['responses'][200]['content']['application/json']['schema']['properties']['data'])
        ->toBe(['type' => 'array', 'items' => ['type' => 'string']]);
});

it('does not include the route method in operation IDs', function () {
    $docs = $this->generateDocs();

    expect(operation($docs, '/todos', 'post')['operationId'])->toBe('fixtures.createTodo')
        ->and(operation($docs, '/todos/count', 'get')['operationId'])->toBe('fixtures.countTodos');
});

it('respects ExcludeRouteFromDocs on the route method', function () {
    expect($this->generateDocs()['paths'])->not->toHaveKey('/hidden');
});

it('does not apply action conventions to explicit route methods', function () {
    $operation = operation($this->generateDocs(), '/todos/custom', 'get');

    expect($operation)->not->toHaveKey('parameters')
        ->and(array_keys($operation['responses']))->toBe([200])
        ->and($operation['responses'][200]['content']['application/json']['schema']['properties'])
        ->toBe(['custom' => ['type' => 'boolean']]);
});

it('does not change the application routes', function () {
    $this->generateDocs();

    $route = collect(Route::getRoutes()->getRoutes())->first(fn ($route) => $route->uri === 'api/todos' && in_array('POST', $route->methods()));

    expect($route->getAction('uses'))->toBe(Fixtures\CreateTodo::class.'@__invoke');

    $this->postJson('api/todos', [])->assertUnprocessable()->assertJsonValidationErrors('title');

    $this->postJson('api/todos', ['title' => 'Write docs'])
        ->assertOk()
        ->assertExactJson(['data' => ['id' => 1, 'title' => 'Write docs', 'done' => false]]);
});

it('documents actions whose route was already dispatched', function () {
    // Laravel Actions swaps `__invoke` for `asController` on the route when it is dispatched.
    $this->postJson('api/todos', ['title' => 'Write docs'])->assertOk();

    $operation = operation($this->generateDocs(), '/todos', 'post');

    expect($operation['operationId'])->toBe('fixtures.createTodo')
        ->and($operation['requestBody']['content']['application/json']['schema'])
        ->toBe(['$ref' => '#/components/schemas/CreateTodoRequest'])
        ->and(array_keys($operation['responses']))->toBe([200, 422]);
});

it('works with the export command', function () {
    $path = sys_get_temp_dir().'/scramble-laravel-actions-'.uniqid().'.json';

    $this->artisan('scramble:export', ['--path' => $path])->assertSuccessful();

    $docs = json_decode(file_get_contents($path), true);
    unlink($path);

    expect(operation($docs, '/todos', 'post')['requestBody']['content']['application/json']['schema'])
        ->toBe(['$ref' => '#/components/schemas/CreateTodoRequest']);
});

it('documents authentication errors from the action controller middleware', function () {
    $docs = $this->generateDocs();

    expect(operation($docs, '/todos/{todo}', 'delete')['responses'])->toHaveKey(401)
        ->and(operation($docs, '/ping', 'get')['responses'])->not->toHaveKey(401);
});
