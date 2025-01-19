<?php declare(strict_types=1);

namespace Collectiq\SolanaPhpSdk\Exceptions;

final class SNSError extends SolanaPhpSdkException
{
    public const string
        SymbolNotFound = 'SymbolNotFound',
        AccountDoesNotExist = 'AccountDoesNotExist',
        FavouriteDomainNotFound = 'FavouriteDomainNotFound',
        InvalidAAAARecord = 'InvalidAAAARecord',
        InvalidARecord = 'InvalidARecord',
        InvalidBufferLength = 'InvalidBufferLength',
        InvalidCustomBg = 'InvalidCustomBackground',
        InvalidDomain = 'InvalidDomain',
        InvalidEvmAddress = 'InvalidEvmAddress',
        InvalidInjectiveAddress = 'InvalidInjectiveAddress',
        InvalidInput = 'InvalidInput',
        InvalidRecordData = 'InvalidRecordData',
        InvalidRecordInput = 'InvalidRecordInput',
        InvalidReverseTwitter = 'InvalidReverseTwitter',
        InvalidSignature = 'InvalidSignature',
        InvalidSolRecordV2 = 'InvalidSolRecordV2',
        InvalidSubdomain = 'InvalidSubdomain',
        MissingParentOwner = 'MissingParentOwner',
        MissingVerifier = 'MissingVerifier',
        MultipleRegistries = 'MultipleRegistries',
        NoAccountData = 'NoAccountData',
        NoRecordData = 'NoRecordData',
        PythFeedNotFound = 'PythFeedNotFound',
        RecordDoestNotSupportGuardianSig = 'RecordDoestNotSupportGuardianSig',
        RecordIsNotSigned = 'RecordIsNotSigned',
        U32Overflow = 'U32Overflow',
        U64Overflow = 'U64Overflow',
        UnsupportedRecord = 'UnsupportedRecord',
        UnsupportedSignature = 'UnsupportedSignature',
        UnsupportedSignatureType = 'UnsupportedSignatureType';
}
