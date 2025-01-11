<?php declare(strict_types=1);

use Rector\Arguments\Rector\ClassMethod\ArgumentAdderRector;
use Rector\Caching\ValueObject\Storage\FileCacheStorage;
use Rector\CodingStyle\Rector\ClassConst\SplitGroupedClassConstantsRector;
use Rector\CodingStyle\Rector\Encapsed\EncapsedStringsToSprintfRector;
use Rector\CodingStyle\Rector\PostInc\PostIncDecToPreIncDecRector;
use Rector\CodingStyle\Rector\Use_\SeparateMultiUseImportsRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\PropertyProperty\RemoveNullPropertyInitializationRector;
use Rector\EarlyReturn\Rector\If_\ChangeOrIfContinueToMultiContinueRector;
use Rector\EarlyReturn\Rector\Return_\ReturnBinaryOrToEarlyReturnRector;
use Rector\Php70\Rector\StaticCall\StaticCallOnNonStaticToInstanceCallRector;
use Rector\Php74\Rector\Closure\ClosureToArrowFunctionRector;
use Rector\Php74\Rector\Property\RestoreDefaultNullToNullableTypePropertyRector;
use Rector\Php74\Rector\Ternary\ParenthesizeNestedTernaryRector;
use Rector\Php81\Rector\Array_\FirstClassCallableRector;
use Rector\Php81\Rector\Property\ReadOnlyPropertyRector;
use Rector\Php82\Rector\Class_\ReadOnlyClassRector;
use Rector\Php82\Rector\Param\AddSensitiveParameterAttributeRector;
use Rector\Php83\Rector\ClassMethod\AddOverrideAttributeToOverriddenMethodsRector;
use Rector\Php84\Rector\Param\ExplicitNullableParamTypeRector;
use Rector\Privatization\Rector\Property\PrivatizeFinalClassPropertyRector;
use RectorLaravel\Rector\Class_\ModelCastsPropertyToCastsMethodRector;
use RectorLaravel\Rector\Coalesce\ApplyDefaultInsteadOfNullCoalesceRector;
use RectorLaravel\Rector\MethodCall\ReplaceServiceContainerCallArgRector;
use RectorLaravel\Set\LaravelSetList;

/**
 * @see https://github.com/rectorphp/rector/blob/main/docs/rector_rules_overview.md
 * @see https://github.com/driftingly/rector-laravel/blob/main/docs/rector_rules_overview.md
 */
return RectorConfig::configure()
    ->withCache(
        cacheDirectory: './.cache/rector',
        cacheClass: FileCacheStorage::class,
        containerCacheDirectory: './.cache/rectorContainer',
    )
    ->withPaths([
        __DIR__ . '/app',
        __DIR__ . '/config',
        __DIR__ . '/routes',
        __DIR__ . '/tests',
        __DIR__ . '/bootstrap',
    ])
    ->withRules([
        ExplicitNullableParamTypeRector::class,
        ParenthesizeNestedTernaryRector::class,
        PrivatizeFinalClassPropertyRector::class,
    ])
    ->withConfiguredRule(AddSensitiveParameterAttributeRector::class, [
        'sensitive_parameters' => [
            'appKey',
            'config',
            'confirmPassword',
            'confirmedPassword',
            'currentPassword',
            'newPassword',
            'password',
            'plainTextPassword',
            'secret',
            'token',
            'two_factor_secret',
        ],
    ])
    ->withSkip([
        AddOverrideAttributeToOverriddenMethodsRector::class,
        ApplyDefaultInsteadOfNullCoalesceRector::class,
        ArgumentAdderRector::class,
        ChangeOrIfContinueToMultiContinueRector::class,
        SplitGroupedClassConstantsRector::class,
        ClosureToArrowFunctionRector::class,
        SeparateMultiUseImportsRector::class,
        EncapsedStringsToSprintfRector::class,
        FirstClassCallableRector::class => [
            __DIR__ . '/routes',
        ],
        ModelCastsPropertyToCastsMethodRector::class,
        PostIncDecToPreIncDecRector::class,
        ReadOnlyClassRector::class => [
            __DIR__ . '/app/Events/*',
            __DIR__ . '/app/Jobs/*',
        ],
        ReadOnlyPropertyRector::class => [
            __DIR__ . '/app/Events/*',
            __DIR__ . '/app/Jobs/*',
        ],
        RemoveNullPropertyInitializationRector::class => [
            __DIR__ . '/app/Http/Resources/*',
        ],
        ReplaceServiceContainerCallArgRector::class,
        RestoreDefaultNullToNullableTypePropertyRector::class,
        ReturnBinaryOrToEarlyReturnRector::class,
        StaticCallOnNonStaticToInstanceCallRector::class,
        __DIR__ . '/app/Macros/*',
        __DIR__ . '/bootstrap/cache',
    ])
    ->withSets([
        LaravelSetList::LARAVEL_110,
        LaravelSetList::LARAVEL_CODE_QUALITY,
        LaravelSetList::LARAVEL_ARRAYACCESS_TO_METHOD_CALL,
        LaravelSetList::LARAVEL_COLLECTION,
        LaravelSetList::LARAVEL_CONTAINER_STRING_TO_FULLY_QUALIFIED_NAME,
        LaravelSetList::LARAVEL_FACADE_ALIASES_TO_FULL_NAMES,
    ])
    ->withParallel(300, 15, 15)
    // here we can define, what prepared sets of rules will be applied
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        privatization: true,
        instanceOf: true,
        earlyReturn: true,
        strictBooleans: false,
        carbon: true,
        rectorPreset: true,
        phpunitCodeQuality: true,
    )
    ->withMemoryLimit('3G')
    ->withPhpSets(php83: true);
