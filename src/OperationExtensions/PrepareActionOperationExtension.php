<?php

namespace RayronVictor\ScrambleLaravelActions\OperationExtensions;

use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Infer\Services\FileNameResolver;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\PhpDoc;
use Dedoc\Scramble\Support\RouteInfo;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\Literal\LiteralBooleanType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocChildNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTextNode;
use RayronVictor\ScrambleLaravelActions\Support\ActionInspector;
use RayronVictor\ScrambleLaravelActions\Support\ActionRequestArguments;
use ReflectionClass;

/**
 * Runs before Scramble's own operation extensions and teaches them about the action:
 * where its documentation lives and which errors Laravel Actions may respond with.
 */
class PrepareActionOperationExtension extends OperationExtension
{
    public function handle(Operation $operation, RouteInfo $routeInfo)
    {
        if (! ActionInspector::isActionRoute($routeInfo)) {
            return;
        }

        $this->useClassPhpDocAsFallback($routeInfo);

        if (! $methodType = $routeInfo->getActionType()) {
            return;
        }

        ActionRequestArguments::hide($methodType);

        $this->attachActionExceptions($routeInfo->className(), $methodType);
    }

    /**
     * Actions are usually documented on the class rather than on `handle` or `asController`.
     * When the route method has no summary nor description, the class PHPDoc is used for
     * them, and its tags (like `@response` or `@unauthenticated`) apply unless the
     * route method defines the same tags.
     */
    private function useClassPhpDocAsFallback(RouteInfo $routeInfo): void
    {
        $methodPhpDoc = $routeInfo->phpDoc();

        if ($methodPhpDoc->getAttribute('summary') || $methodPhpDoc->getAttribute('description')) {
            return;
        }

        $reflection = new ReflectionClass($routeInfo->className());

        if (! $docComment = $reflection->getDocComment()) {
            return;
        }

        $classPhpDoc = PhpDoc::parse(
            $docComment,
            $reflection->getFileName() ? FileNameResolver::createForFile($reflection->getFileName()) : null,
        );

        $isText = fn (PhpDocChildNode $node) => $node instanceof PhpDocTextNode;

        // Scramble reads the first matching tag, so the route method tags take precedence.
        $phpDoc = new PhpDocNode([
            ...array_filter($classPhpDoc->children, $isText),
            ...$methodPhpDoc->children,
            ...array_filter($classPhpDoc->children, fn (PhpDocChildNode $node) => ! $isText($node)),
        ]);

        PhpDoc::addSummaryAttributes($phpDoc);

        $phpDoc->setAttribute(
            'hasBodyTag',
            $methodPhpDoc->getAttribute('hasBodyTag') || $classPhpDoc->getAttribute('hasBodyTag'),
        );

        $routeInfo->setPhpDoc($phpDoc);
    }

    private function attachActionExceptions(string $actionClassName, FunctionType $methodType): void
    {
        if (ActionInspector::hasValidationRules($actionClassName)) {
            $this->attachException($methodType, ValidationException::class);
        }

        if (method_exists($actionClassName, 'authorize') && ! $this->alwaysAuthorizes($actionClassName)) {
            $this->attachException($methodType, AuthorizationException::class);
        }
    }

    private function alwaysAuthorizes(string $actionClassName): bool
    {
        $authorizeReturnType = $this->infer->analyzeClass($actionClassName)->getMethodCallType('authorize');

        return $authorizeReturnType instanceof LiteralBooleanType && $authorizeReturnType->value === true;
    }

    private function attachException(FunctionType $methodType, string $exceptionClassName): void
    {
        $alreadyAttached = collect($methodType->exceptions)
            ->contains(fn (Type $exception) => $exception->isInstanceOf($exceptionClassName));

        if ($alreadyAttached) {
            return;
        }

        $methodType->exceptions = [
            ...$methodType->exceptions,
            new ObjectType($exceptionClassName),
        ];
    }
}
