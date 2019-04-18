<?php declare(strict_types=1);

namespace HbLib\Container;

use Ds\Collection;
use Ds\Map;

class ArgumentResolver implements ArgumentResolverInterface
{
    /**
     * {@inheritdoc}
     */
    public function resolve(\ReflectionFunctionAbstract $function, array $arguments = []): array
    {
        $resolvedArguments = [];

        foreach ($function->getParameters() as $parameter) {
            // Identify if the type is a class we can attempt to build.
            $type = $parameter->getType();
            $parameterName = $parameter->getName();
            $isOptional = $parameter->isOptional();
            $isDefaultValueAvailable = $parameter->isDefaultValueAvailable();

            $argumentFactory = new ArgumentFactory();
            $argumentFactory->setName($parameterName);
            $argumentFactory->setIsOptional($isOptional);
            $declaringClassName = null;

            $declaringClass = $parameter->getDeclaringClass();
            if ($declaringClass !== null) {
                $declaringClassName = $declaringClass->getName();
                $argumentFactory->setDeclaringClassName($declaringClassName);
            }
            unset($declaringClass);

            if ($isDefaultValueAvailable) {
                $argumentFactory->setDefaultValue($parameter->getDefaultValue());
            }

            if (isset($arguments[$parameterName])) {
                $argumentFactory->setIsResolved(true);
                $argumentFactory->setValue($arguments[$parameterName]);
                $resolvedArguments[$parameterName] = $argumentFactory->make();
                continue;
            }

            // We must resolve this parameter.
            // Case #1: A class/interface/trait we can build
            if ($type !== null && !$type->isBuiltin()) {
                $typeName = $type->getName();
                $argumentFactory->setTypeHintClassName($typeName);

                if (\HbLib\Container\classNameExists($typeName)) {
                    $resolvedArguments[$parameterName] = $argumentFactory->make();
                    continue;
                }
            }

            // Case #2: Argument is optional and has a default value
            if ($isOptional && $isDefaultValueAvailable) {
                // Some builtin with a default value.
                $resolvedArguments[$parameterName] = $argumentFactory->make();
                continue;
            }

            throw new UnresolvedContainerException('Unable to resolve parameter ' . $parameter->getName() . ' on entity ' . ($declaringClassName ?? 'N/A'));
        }

        return $resolvedArguments;
    }
}
