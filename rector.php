<?php declare(strict_types=1);

use Rector\Arguments\Rector\ClassMethod\ArgumentAdderRector;
use Rector\Caching\ValueObject\Storage\FileCacheStorage;
use Rector\CodingStyle\Rector\ClassConst\SplitGroupedClassConstantsRector;
use Rector\CodingStyle\Rector\Encapsed\EncapsedStringsToSprintfRector;
use Rector\CodingStyle\Rector\PostInc\PostIncDecToPreIncDecRector;
use Rector\CodingStyle\Rector\Use_\SeparateMultiUseImportsRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\FunctionLike\NarrowWideUnionReturnTypeRector;
use Rector\EarlyReturn\Rector\If_\ChangeOrIfContinueToMultiContinueRector;
use Rector\EarlyReturn\Rector\Return_\ReturnBinaryOrToEarlyReturnRector;
use Rector\Php74\Rector\Closure\ClosureToArrowFunctionRector;
use Rector\Php74\Rector\Property\RestoreDefaultNullToNullableTypePropertyRector;
use Rector\Php82\Rector\Param\AddSensitiveParameterAttributeRector;
use Rector\Php83\Rector\ClassMethod\AddOverrideAttributeToOverriddenMethodsRector;
use Rector\PHPUnit\CodeQuality\Rector\Class_\PreferPHPUnitSelfCallRector;
use Rector\PHPUnit\CodeQuality\Rector\Class_\PreferPHPUnitThisCallRector;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Privatization\Rector\MethodCall\PrivatizeLocalGetterToPropertyRector;

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
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withRules([
        PreferPHPUnitSelfCallRector::class,
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
        ArgumentAdderRector::class,
        ChangeOrIfContinueToMultiContinueRector::class,
        ClosureToArrowFunctionRector::class,
        EncapsedStringsToSprintfRector::class,
        // Mangles generic-template returns (`@return TClass|null` →
        // fully-qualifies `TClass` and drops `|null`) on
        // `Borsh::deserialize()`.
        NarrowWideUnionReturnTypeRector::class,
        PostIncDecToPreIncDecRector::class,
        PreferPHPUnitThisCallRector::class,
        PrivatizeLocalGetterToPropertyRector::class,
        RestoreDefaultNullToNullableTypePropertyRector::class,
        ReturnBinaryOrToEarlyReturnRector::class,
        SeparateMultiUseImportsRector::class,
        SplitGroupedClassConstantsRector::class,
        __DIR__ . '/.cache',
    ])
    ->withSets([
        PHPUnitSetList::PHPUNIT_110,
        PHPUnitSetList::ANNOTATIONS_TO_ATTRIBUTES,
    ])
    ->withParallel(300, 15, 15)
    // here we can define, what prepared sets of rules will be applied
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        typeDeclarationDocblocks: true,
        privatization: true,
        instanceOf: true,
        earlyReturn: true,
        carbon: true,
        rectorPreset: true,
        phpunitCodeQuality: true,
    )
    ->withAttributesSets()
    ->withImportNames()
    ->withFluentCallNewLine()
    ->withMemoryLimit('3G')
    ->withPhpSets(php84: true);
