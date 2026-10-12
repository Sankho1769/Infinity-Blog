<?php
declare(strict_types=1);

namespace InfinityBlog\Auth;

use InfinityBlog\Database\DatabaseUnavailableException;
use InvalidArgumentException;
use PDOException;
use RuntimeException;
use Throwable;

final class AdminProvisioningDiagnostic
{
    /** @var array<string, string> */
    private const SAFE_REASONS = [
        'Passwords must be between 12 and 72 bytes.' => 'password_length_must_be_12_to_72_bytes',
        'Each administrator must have a distinct password.' => 'administrator_passwords_must_differ',
        'One or more initial administrator accounts already exist.' => 'initial_administrator_account_exists',
        'Administrator credentials could not be stored.' => 'password_hash_creation_failed',
        'Administrator provisioning could not begin.' => 'transaction_begin_failed',
        'Administrator provisioning failed.' => 'database_operation_failed',
        'Administrator operation could not be rolled back.' => 'transaction_rollback_failed',
        'Only the two configured administrator credentials can be rotated here.' => 'administrator_identifier_not_allowed',
        'The configured administrator account does not exist.' => 'administrator_account_missing',
        'Administrator password rotation failed.' => 'password_rotation_failed',
        'Diagnostic mode only permits the development runtime database.' => 'diagnostic_target_mismatch',
        'The users table must use InnoDB for atomic provisioning.' => 'users_table_not_transactional',
    ];

    public static function format(Throwable $exception): string
    {
        $parts = [];
        $current = $exception;
        for ($depth = 0; $current !== null && $depth < 6; $depth++) {
            $part = get_class($current);
            if ($current instanceof AdminProvisioningException) {
                $reason = self::SAFE_REASONS[$current->getMessage()] ?? null;
                if ($reason !== null) {
                    $part .= '[reason=' . $reason . ']';
                }
            } elseif ($current instanceof DatabaseUnavailableException) {
                $part .= '[reason=database_connection_failed]';
            } elseif ($current instanceof InvalidArgumentException) {
                $part .= '[reason=configuration_validation_failed]';
            } elseif ($current instanceof RuntimeException && $current->getMessage() === 'Administrator provisioning is disabled in the test environment.') {
                $part .= '[reason=provisioning_disabled_in_test_environment]';
            } elseif ($current instanceof RuntimeException && $current->getMessage() === 'Administrator passwords must be provided through the secure prompt wrapper.') {
                $part .= '[reason=password_prompt_wrapper_required]';
            } elseif ($current instanceof RuntimeException && $current->getMessage() === 'Choose an administrator and provide a new password through the secure prompt wrapper.') {
                $part .= '[reason=rotation_identifier_required]';
            }

            if ($current instanceof PDOException) {
                $errorInfo = $current->errorInfo ?? null;
                $sqlState = $current->getCode();
                if (!is_string($sqlState) || preg_match('/^[A-Z0-9]{5}$/iD', $sqlState) !== 1) {
                    $sqlState = is_array($errorInfo) ? ($errorInfo[0] ?? null) : null;
                }
                if (is_string($sqlState) && preg_match('/^[A-Z0-9]{5}$/iD', $sqlState) === 1) {
                    $part .= '[sqlstate=' . strtoupper($sqlState) . ']';
                }
                $driverCode = is_array($errorInfo) ? ($errorInfo[1] ?? null) : null;
                if (is_int($driverCode) || (is_string($driverCode) && ctype_digit($driverCode))) {
                    $part .= '[driver_code=' . $driverCode . ']';
                }
            }

            $parts[] = $part;
            $current = $current->getPrevious();
        }

        if ($current !== null) {
            $parts[] = 'cause_chain_truncated';
        }

        return implode(' <- ', $parts);
    }
}
