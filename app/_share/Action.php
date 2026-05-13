<?php

namespace _share;

use _share\exceptions\ActionInputError;
use Throwable;


/**
 * Action: reusable "action" - also usually one api-request.
 * 
 * Buit actions could also be nested or used on pages or in snippets, services or elsewhere.
 * 
 * A bit of functionality wrapped in a way to be exposed to the api.
 * 
 * An acton should check all kinds of permissions for itself - never assume any correctness
 * Assume always malicious attempt.
 */
abstract class Action
{

    final public static function from_array(array $input): static
    {
        $method = self::getExecuteMethod();

        $args = self::bindInputToParameters($method, $input);

        try {
            $result = $method->invokeArgs(null, $args);
        } catch (\TypeError | \ArgumentCountError $e) {
            throw new ActionInputError(
                message: 'invalid input',
                previous: $e,
                #details: [
                #    'internal' => $e->getMessage(),
                #],
            );
        }

        if (!$result instanceof static) {
            throw new \LogicException(
                static::class . '::execute() must return an instance of ' . static::class
            );
        }

        return $result;
    }

    final public static function tryFromArray(array $input): static|Throwable
    {
        try {
            return static::from_array($input);
        } catch (Throwable $e) {
            return $e;
        }
    }

    final public static function execute_as_request_and_dump_json_and_exit(): never
    {
        $result = static::tryFromArray($_REQUEST);
        if ($result instanceof Throwable)
        {
            exit(
                json_encode(
                    [
                        "err" => true,
                        "message" => $result->getMessage(),
                        "trace" => app::is_debug() ? $result->getTraceAsString(): "",
                        "input" => app::is_debug() ? $_REQUEST: "",
                        "session" => app::is_debug() ? $_SESSION: "",
                        "logs" => app::is_debug() ? app::get_logs(): ""
                    ]
                )
            );
        }
        else
        {
            exit(
                json_encode(
                    [
                        "err" => false,
                        "message" => static::class . " sucessfully executed",
                        "logs" =>  app::is_debug() ? app::get_logs(): "",
                        "session" => app::is_debug() ? $_SESSION: "",
                        "input" => app::is_debug() ? $_REQUEST: "",
                        "data" => get_object_vars($result)
                    ]
                )
            );
        }
    }

    private static function getExecuteMethod(): \ReflectionMethod
    {
        if (!method_exists(static::class, 'execute')) {
            throw new \LogicException(
                static::class . ' must define public static execute(...): static'
            );
        }

        $method = new \ReflectionMethod(static::class, 'execute');

        if (!$method->isPublic() || !$method->isStatic()) {
            throw new \LogicException(
                static::class . '::execute() must be public static'
            );
        }

        $returnType = $method->getReturnType();

        if ($returnType === null) {
            throw new \LogicException(
                static::class . '::execute() must declare a return type'
            );
        }

        if (!$returnType instanceof \ReflectionNamedType) {
            throw new \LogicException(
                static::class . '::execute() must return static or ' . static::class
            );
        }

        $name = $returnType->getName();

        $validReturn =
            $name === 'static'
            || $name === 'self'
            || $name === static::class
            || is_subclass_of(static::class, $name);

        if (!$validReturn) {
            throw new \LogicException(
                static::class . '::execute() must return static or ' . static::class . ', got ' . $name
            );
        }

        return $method;
    }

    private static function bindInputToParameters(
        \ReflectionMethod $method,
        array $input,
    ): array {
        $args = [];
        $allowed = [];

        foreach ($method->getParameters() as $param) {
            $name = $param->getName();
            $allowed[$name] = true;

            if (array_key_exists($name, $input)) {
                $args[$name] = $input[$name];
                continue;
            }

            if ($param->isDefaultValueAvailable()) {
                // Bei named args darf man Default-Parameter einfach weglassen.
                continue;
            }

            if ($param->allowsNull()) {
                $args[$name] = null;
                continue;
            }

            throw new ActionInputError(
                message: "missing required input: {$name}",
                #field: $name,
            );
        }

        $unknown = array_diff(array_keys($input), array_keys($allowed));

        if ($unknown !== []) {
            throw new ActionInputError(
                message: 'unknown input fields',
                #details: [
                #    'fields' => array_values($unknown),
                #],
            );
        }

        return $args;
    }

}