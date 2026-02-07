<?php
/**
 * Rector configuration for WP System Report plugin.
 *
 * @package SystemReport
 */

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveEmptyClassMethodRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPrivateMethodParameterRector;
use Rector\DeadCode\Rector\Property\RemoveUnusedPrivatePropertyRector;
use Rector\Set\ValueObject\SetList;

return RectorConfig::configure()
	->withPaths(
		array(
			__DIR__ . '/includes',
			__DIR__ . '/wp-system-report.php',
		)
	)
	->withSkip(
		array(
			__DIR__ . '/vendor',
			__DIR__ . '/tests',
			// Skip rules that conflict with WordPress coding standards.
			RemoveUnusedPrivateMethodParameterRector::class,
			// GitHub_Updater property is stored to prevent garbage collection.
			RemoveUnusedPrivatePropertyRector::class => array(
				__DIR__ . '/includes/class-plugin.php',
			),
			// Singleton __clone() is intentionally empty to prevent cloning.
			RemoveEmptyClassMethodRector::class => array(
				__DIR__ . '/includes/class-plugin.php',
			),
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
