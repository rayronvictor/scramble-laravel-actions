<?php

namespace RayronVictor\ScrambleLaravelActions\RulesEvaluators;

use Dedoc\Scramble\Diagnostics\DiagnosticsCollector;
use Dedoc\Scramble\Exceptions\RulesEvaluationException;
use Dedoc\Scramble\Infer\Reflector\ClassReflector;
use Dedoc\Scramble\Support\OperationExtensions\RulesEvaluator\NodeRulesEvaluator;
use Dedoc\Scramble\Support\OperationExtensions\RulesEvaluator\RulesEvaluator;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Http\Request;
use Lorisleiva\Actions\ActionRequest;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeFinder;
use PhpParser\PrettyPrinter;
use Throwable;

/**
 * Evaluates the `rules()` method of an action.
 *
 * It first calls the method for real, resolving its dependencies from the container like
 * Laravel Actions does. When that fails (for example the rules depend on route parameters
 * or on the authenticated user), it falls back to Scramble's static evaluation of the
 * array returned by the method.
 */
class ActionRulesEvaluator implements RulesEvaluator
{
    public function __construct(
        private PrettyPrinter $printer,
        private ClassReflector $classReflector,
        private string $method,
        private DiagnosticsCollector $diagnostics,
        private RouteInfo $routeInfo,
    ) {}

    public function handle(): array
    {
        $exceptions = [];

        try {
            return $this->evaluateAtRuntime();
        } catch (Throwable $e) {
            $exceptions['runtime'] = $e;
        }

        try {
            return $this->evaluateStatically();
        } catch (Throwable $e) {
            $exceptions[NodeRulesEvaluator::class] = $e;
        }

        throw RulesEvaluationException::fromExceptions($exceptions)
            ->forClass($this->classReflector->className);
    }

    private function evaluateAtRuntime(): array
    {
        $action = app()->make($this->classReflector->className);

        $request = ActionRequest::create('/', strtoupper($this->method));
        $request->setContainer(app());
        $request->setAction($action);

        $rules = app()->call([$action, 'rules'], [
            ActionRequest::class => $request,
            Request::class => $request,
        ]);

        return is_array($rules) ? $rules : [];
    }

    private function evaluateStatically(): array
    {
        $rulesMethod = $this->classReflector->getMethod('rules');
        $rulesMethodNode = $rulesMethod->getAstNode();

        /** @var Return_|null $returnStatement */
        $returnStatement = (new NodeFinder)->findFirst(
            $rulesMethodNode ? [$rulesMethodNode] : [],
            fn ($node) => $node instanceof Return_ && $node->expr instanceof Array_,
        );

        return (new NodeRulesEvaluator(
            $this->printer,
            $rulesMethodNode,
            $returnStatement?->expr,
            $this->method,
            $this->classReflector->className,
            $rulesMethod->getFunctionLikeDefinition()->getScope(),
            $this->diagnostics,
            $this->routeInfo,
        ))->handle();
    }
}
