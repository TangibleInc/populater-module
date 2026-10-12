<?php
/**
 * Group index resolver.
 *
 * @package Tangible\Populater
 */

declare(strict_types=1);

namespace Tangible\Populater\Support;

/**
 * Distributes item indices evenly across a number of groups.
 */
final class GroupIndexResolver {

	/**
	 * Maps a one-based item index onto a one-based group index so items are spread evenly across the groups.
	 *
	 * @param int $itemIndex   One-based index of the item.
	 * @param int $totalItems  Total number of items.
	 * @param int $totalGroups Number of groups available.
	 * @return int One-based group index, or 0 if no group applies.
	 */
	public static function resolve( int $itemIndex, int $totalItems, int $totalGroups ): int {
		if ( $totalGroups <= 0 || $totalItems <= 0 || $itemIndex <= 0 ) {
			return 0;
		}

		$totalGroups = max( 1, min( $totalGroups, $totalItems ) );

		return (int) min( $totalGroups, max( 1, (int) ceil( $itemIndex * $totalGroups / $totalItems ) ) );
	}
}
