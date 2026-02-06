<?php
/**
 * Rector configuration for System Report plugin.
 *
 * @package SystemReport
 */

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPrivateMethodParameterRector;
use Rector\Set\ValueObject\SetList;

return RectorConfig::configure()
	->withPaths(
		array(
			__DIR__ . '/includes',
			__DIR__ . '/system-report.php',
		)
	)
	->withSkip(
		array(
			__DIR__ . '/vendor',
			__DIR__ . '/tests',
			// Skip rules that conflict with WordPress coding standards.
			RemoveUnusedPrivateMethodParameterRector::class,
		)
	)
	->withPhpVersion( Rector\ValueObject\PhpVersion::PHP_74 )
	->withSets(
		array(
			SetList::DEAD_CODE,
			SetList::EARLY_RETURN,
			SetList::TYPE_DECLARATION,
		)
	);
