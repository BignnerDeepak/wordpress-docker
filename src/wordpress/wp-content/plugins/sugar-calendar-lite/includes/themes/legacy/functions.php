<?php
/**
 * Sugar Calendar Legacy Functions.
 *
 * Try not to use these in any code after 2.0.0.
 *
 * @since 1.0.0
 */

use Sugar_Calendar\Options;
use Sugar_Calendar\Helper;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Get events within a custom date range with flexible arguments.
 *
 * @since 3.7.0
 *
 * @param array $args Arguments.
 *        DateTimeInterface $start_range Start date range.
 *        DateTimeInterface $end_range   End date range.
 *        array|string      $category    Category IDs or term slugs.
 *        string            $search      Search term.
 *        int|null          $number      Number of events to retrieve.
 *        array             $venues      Venue IDs.
 *        array             $tags        Tag IDs.
 *        array             $speakers    Speaker IDs.
 *
 * @return array Array of event objects.
 */
function sugar_calendar_get_events_within_range( $args = [] ) {

	$defaults = [
		'start_range' => null,
		'end_range'   => null,
		'category'    => '',
		'search'      => '',
		'number'      => null,
		'venues'      => [],
		'tags'        => [],
		'speakers'    => [],
	];

	$args = wp_parse_args( $args, $defaults );

	if (
		! ( $args['start_range'] instanceof DateTimeInterface ) ||
		! ( $args['end_range'] instanceof DateTimeInterface )
	) {
		return [];
	}

	$view_start = $args['start_range']->format( 'Y-m-d H:i:s' );
	$view_end   = $args['end_range']->format( 'Y-m-d 23:59:59' );

	if ( is_null( $args['number'] ) ) {
		$args['number'] = sc_get_number_of_events();
	} else {
		$args['number'] = absint( $args['number'] );
	}

	// Default arguments.
	$query_args = [
		'no_found_rows' => true,
		'number'        => $args['number'],
		'object_type'   => 'post',
		'status'        => 'publish',
		'orderby'       => 'start',
		'order'         => 'ASC',
		'date_query'    => sugar_calendar_get_date_query_args( 'month', $view_start, $view_end ),
		'search'        => $args['search'],
	];

	// Maybe add category if non-empty.
	if ( ! empty( $args['category'] ) ) {
		$tax                = sugar_calendar_get_calendar_taxonomy_id();
		$query_args[ $tax ] = $args['category']; // Sanitized later.
	}

	// Maybe add venues if non-empty.
	if ( ! empty( $args['venues'] ) ) {
		$query_args['venue_id'] = $args['venues'];
	}

	// Maybe add tags if non-empty.
	if ( ! empty( $args['tags'] ) ) {
		$query_args['sc_event_tags'] = $args['tags'];
	}

	// Maybe add speakers if non-empty.
	if ( ! empty( $args['speakers'] ) ) {
		$query_args['speaker_ids'] = $args['speakers'];
	}

	// Query for events.
	$events = sugar_calendar_get_events( $query_args );

	// Get event sequences.
	$retval = sc_get_event_sequences_for_calendar( $events, $view_start, $view_end );

	// Return the events.
	return $retval;
}

/**
 * Get events with a custom parameter.
 *
 * @since 3.5.0
 * @deprecated 3.7.0 Use sugar_calendar_get_events_within_range() instead.
 *
 * @param DateTimeImmutable $start_range Start range.
 * @param DateTimeImmutable $end_range   End range.
 * @param array             $category    Category.
 * @param string            $search      Search.
 * @param int               $number      Number.
 * @param array             $venues      Venues.
 *
 * @return array
 */
function sc_get_events_for_calendar_with_custom_range(
	$start_range,
	$end_range,
	$category = '',
	$search = '',
	$number = null,
	$venues = []
) {

	// Trigger deprecation notice.
	_deprecated_function( __FUNCTION__, '3.7.0', 'sugar_calendar_get_events_within_range()' );

	// Backward compatibility.
	return sugar_calendar_get_events_within_range(
		[
			'start_range' => $start_range,
			'end_range'   => $end_range,
			'category'    => $category,
			'search'      => $search,
			'number'      => $number,
			'venues'      => $venues,
		]
	);
}

/**
 * Query events by a given day, month, and year.
 *
 * Also accepts a category.
 *
 * @since 2.0.0
 * @since 3.1.2 Support `$timezone`.
 *
 * @param int                $day
 * @param int                $month
 * @param int                $year
 * @param string             $category
 * @param string             $search
 * @param DateTimeZone|false $timezone TimeZone to convert the event datetime to.
 *
 * @return array
 */
function sc_get_events_for_calendar( $day = '01', $month = '01', $year = '1970', $category = '', $search = '', $timezone = false ) {

	// Sanitize
	$day   = str_pad( $day, 2, '0', STR_PAD_LEFT );
	$month = str_pad( $month, 2, '0', STR_PAD_LEFT );
	$year  = str_pad( $year, 4, '0', STR_PAD_LEFT );

	// Boundaries
	$view_start = "{$year}-{$month}-01 00:00:00";
	$month_end  = gmdate( 't', strtotime( $view_start ) );
	$view_end   = "{$year}-{$month}-{$month_end} 23:59:59";
	$number     = sc_get_number_of_events();

	// Default arguments
	$args = array(
		'no_found_rows' => true,
		'number'        => $number,
		'object_type'   => 'post',
		'status'        => 'publish',
		'orderby'       => 'start',
		'order'         => 'ASC',
		'date_query'    => sugar_calendar_get_date_query_args( 'month', $view_start, $view_end ),
		'search'        => $search,
	);

	// Maybe add category if non-empty
	if ( ! empty( $category ) ) {
		$tax          = sugar_calendar_get_calendar_taxonomy_id();
		$args[ $tax ] = $category; // Sanitized later
	}

	// Query for events
	$events = sugar_calendar_get_events( $args );

	// Get event sequences
	$retval = sc_get_event_sequences_for_calendar( $events, $view_start, $view_end );

	// Return the events
	return $retval;
}

/**
 * Given an array of Event objects, get a combined array of recurring sequences.
 *
 * @since 2.2.0
 *
 * @param array  $events
 * @param string $after
 * @param string $before
 *
 * @return array
 */
function sc_get_event_sequences_for_calendar( $events = array(), $after = null, $before = null ) {

	// Bail if anything is missing
	if ( empty( $events ) || empty( $before ) || empty( $after ) ) {
		return $events;
	}

	// Environment
	$timezone = sugar_calendar_get_timezone_object( sc_get_timezone() );
	$sow      = sugar_calendar_daynum_to_ical( sc_get_week_start_day() );

	// Range
	$after  = sugar_calendar_get_datetime_object( $after, $timezone );
	$before = sugar_calendar_get_datetime_object( $before, $timezone );

	// Get all of the items
	$retval = sugar_calendar_get_event_sequences( $events, $after, $before, $timezone, $sow );

	// Return the events
	return $retval;
}

/**
 * Return if an event overlaps a day, month, and year combination
 *
 * @since 2.0.0
 * @since 2.1.2 Prefers Event::intersects() over Event::overlaps()
 * @since 3.1.2 Added `$timezone` parameter.
 *
 * @param \Sugar_Calendar\Event $event    The event object.
 * @param string                $day
 * @param string                $month
 * @param string                $year
 * @param false|\DateTimeZone   $timezone Timezone to convert the events' datetime.
 *
 * @return bool
 */
function sc_is_event_for_day( $event, $day = '01', $month = '01', $year = '1970', $timezone = false ) {

	if ( $event->is_all_day() ) {
		$timezone = false;
	}

	if ( empty( $timezone ) ) {
		// Get the time zone, either by user preference or by settings.
		$timezone = sugar_calendar_get_timezone();
	}

	// Make time stamps
	$start_ts = gmmktime( 00, 00, 00, (int) $month, (int) $day, (int) $year );
	$end_ts   = gmmktime( 23, 59, 59, (int) $month, (int) $day, (int) $year );

	// Get start & end objects
	$start = sugar_calendar_get_datetime_object( $start_ts, $timezone );
	$end   = sugar_calendar_get_datetime_object( $end_ts, $timezone );

	// Return
	return $event->intersects( $start, $end );
}

/**
 * Return if doing events, either singular, archive, or related taxonomy
 *
 * @since 2.0.19
 *
 * @return bool
 */
function sc_doing_events() {

	// Get post types and taxonomies
	$pts = sugar_calendar_allowed_post_types();
	$tax = sugar_calendar_get_object_taxonomies( $pts );

	// Return true if single event, event archive, or allowed taxonomy archive
	if ( is_singular( $pts ) || is_post_type_archive( $pts ) || is_tax( $tax ) ) {
		return true;
	}

	// Default false
	return false;
}

/**
 * Gets Events for a specific day, month, and year, from an array of Events.
 *
 * @since 2.0.0
 *
 * @param array  $events
 * @param string $day
 * @param string $month
 * @param string $year
 *
 * @return \Sugar_Calendar\Event[]
 */
function sc_filter_events_for_day( $events = array(), $day = '01', $month = '01', $year = '1970' ) {

	// Default return value
	$retval = array();

	// Bail if no events
	if ( empty( $events ) ) {
		return $retval;
	}

	// Loop through events
	foreach ( $events as $event ) {

		// Skip if event is not for day
		if ( ! sc_is_event_for_day( $event, $day, $month, $year ) ) {
			continue;
		}

		// Add event to return array
		$retval[] = $event;
	}

	// Return events for day
	return $retval;
}

/**
 * Get links to Events for use in a calendar cell.
 *
 * This function is in the legacy theme folder, and as such should not be
 * used in new code anywhere else. If this kind of functionality is needed
 * elsewhere, please consider writing a newer better function.
 *
 * @since 2.1.9
 *
 * @param array  $events
 * @param string $size
 *
 * @return string
 */
function sc_get_event_calendar_links( $events = array(), $size = 'small' ) {

	// Default links array
	$links = array();

	// Loop through events
	if ( ! empty( $events ) ) {
		foreach ( $events as $event ) {

			// Object ID
			$id = $event->object_id;

			// Class
			$class = sc_get_event_class( $id );

			// Title & Link
			$title = get_the_title( $id );
			$url   = get_permalink( $id );
			$style = sc_get_event_style_attr( $id );

			// Big or small links
			$link = ( $size === 'small' )
				? '<a href="' . esc_url( $url ) . '" class="' . esc_attr( $class ) . '" ' . $style . ' title="' . esc_attr( strip_tags( $title ) ) . '">&bull;</a>'
				: '<a href="' . esc_url( $url ) . '" class="' . esc_attr( $class ) . '" ' . $style . '>' . esc_html( $title ) . '</a>';

			// Add to links array
			$links[] = apply_filters( 'sc_event_calendar_link', $link, $id, $size );
		}
	}

	// Return
	return implode( '', $links );
}

/**
 * Get all terms for an array of Events.
 *
 * @since 2.1.9
 *
 * @param array  $events
 * @param string $tax
 *
 * @return array
 */
function sc_get_terms_from_events( $events = array(), $tax = '' ) {

	// Default return value
	$retval = array();

	// Fallback taxonomy
	if ( empty( $tax ) ) {
		$tax = sugar_calendar_get_calendar_taxonomy_id();
	}

	// Loop through Events and prefetch them
	if ( ! empty( $tax ) && is_string( $tax ) && ! empty( $events ) ) {

		// Get object IDs
		$object_ids = is_scalar( $events )
			? (array) $events
			: wp_list_pluck( $events, 'object_id' );

		// Loop through object IDs
		foreach ( $object_ids as $object_id ) {

			// Check term cache first
			$terms = get_object_term_cache( $object_id, $tax );

			// No cache, so query for terms
			if ( false === $terms ) {
				$terms = wp_get_object_terms( $object_id, $tax );
			}

			// Maybe loop through terms
			if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {

				// Add them to the return value
				foreach ( $terms as $term ) {
					$retval[ $term->term_id ] = $term;
				}
			}
		}
	}

	// Return
	return $retval;
}

/**
 * Get the HTML class attribute contents for a theme-side calendar cell. It
 * exists to encapsulate a term-cache check, and code that was repeated a few
 * times in calendar functions defined in this file.
 *
 * This function is in the legacy theme folder, and as such should not be
 * used in new code anywhere else. If this kind of functionality is needed
 * elsewhere, please consider writing a newer better function.
 *
 * @since 2.1.9
 *
 * @param array  $events
 * @param string $class
 * @param int    $day
 * @param int    $month
 * @param int    $year
 *
 * @return string
 */
function sc_get_day_class( $events = array(), $class = '', $day = 0, $month = 0, $year = 0 ) {

	// Default return values
	$classes = explode( ' ', $class );

	// Day
	if ( ! empty( $day ) ) {
		$classes[] = 'day-' . absint( $day );
	}

	// Month
	if ( ! empty( $month ) ) {
		$classes[] = 'month-' . absint( $month );
	}

	// Year
	if ( ! empty( $year ) ) {
		$classes[] = 'year-' . absint( $year );
	}

	// Loop through Events and prefetch them
	if ( ! empty( $events ) && is_array( $events ) ) {

		// Day has events
		$classes[] = 'has-events';

		// Get the calendar taxonomy ID
		$tax = sugar_calendar_get_calendar_taxonomy_id();

		// Get Event terms
		$terms = sc_get_terms_from_events( $events );

		// Pluck out the slugs
		$slugs = wp_list_pluck( $terms, 'slug' );

		// Add them to the classes
		foreach ( $slugs as $slug ) {
			$classes[] = "{$tax}-{$slug}";
		}
	}

	// Sanitize and string'ify classes
	$retval = implode( ' ', array_map( 'sanitize_html_class', $classes ) );

	// Filter & return
	return apply_filters( 'sc_get_day_class', $retval, $classes, $events, $class, $day, $month, $year );
}

/**
 * Get the style attribute for the calendar day.
 *
 * @since 2.1.9
 *
 * @param array $events
 *
 * @return string
 */
function sc_get_day_style_attr( $events = array() ) {

	// Get the day color style
	$day_style = sc_get_day_color_style();

	// Bail if not styling the day
	if ( in_array( $day_style, array( 'none', 'each' ), true ) ) {
		return '';
	}

	// Loop through Events and prefetch them
	if ( empty( $events ) || ! is_array( $events ) ) {
		return '';
	}

	// Default colors
	$colors = array();

	// Add them to the classes
	foreach ( $events as $event ) {

		// Get calendar color
		$color = sugar_calendar_get_event_color( $event->object_id, 'post' );

		// Maybe add color
		if ( ! empty( $color ) && ( 'none' !== $color ) ) {
			$colors[] = $color;
		}
	}

	// Bail if no colors
	if ( empty( $colors ) ) {
		return '';
	}

	// Blend Calendar colors together
	if ( 'blend' === $day_style ) {
		$color = sugar_calendar_blend_colors( $colors );

		// Or use the first one
	} elseif ( 'first' === $day_style ) {
		$color = $colors[0];
	}

	// Use RGBA 30% opacity (total guess!) to avoid link styling issues
	$color = sugar_calendar_get_rgba_from_hex( $color, 0.3 );

	// Return the attribute
	return 'style="background-color: ' . $color . ' !important;"';
}

/**
 * Get the style attribute for an Event link in a calendar view.
 *
 * @since 2.1.9
 *
 * @param int $object_id
 *
 * @return string
 */
function sc_get_event_style_attr( $object_id = false ) {

	// Bail if not styling each Event
	if ( 'each' !== sc_get_day_color_style() ) {
		return '';
	}

	// Get the Event
	$color = sugar_calendar_get_event_color( $object_id );

	// Enforce a color
	if ( empty( $color ) || ( 'none' === $color ) ) {
		return '';
	}

	// Always style the text (not the background) due to padding/margin issues
	$attr = 'color: ' . $color . ' !important;';

	// Return style attribute
	return sprintf( 'style="%s"', $attr );
}

/**
 * Get the HTML class attribute contents for an item in a theme-side calendar
 * cell. It exists to encapsulate a term-cache check, and code that was repeated
 * a few times in calendar functions defined in this file.
 *
 * This function is in the legacy theme folder, and as such should not be
 * used in new code anywhere else. If this kind of functionality is needed
 * elsewhere, please consider writing a newer better function.
 *
 * @since 2.0.15
 *
 * @param int $object_id
 *
 * @return string
 */
function sc_get_event_class( $object_id = false ) {

	// This function only accepts a post ID
	if ( empty( $object_id ) || ! is_numeric( $object_id ) ) {
		return '';
	}

	// Get Event terms
	$terms = sc_get_terms_from_events( $object_id );

	// Default classes
	$classes = array();

	// Bail if no terms
	if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {

		// Pluck the slugs
		$classes = array_unique( wp_list_pluck( $terms, 'slug' ) );
	}

	// Sanitize and string'ify classes
	$retval = implode( ' ', array_map( 'sanitize_html_class', $classes ) );

	// Filter & return
	return apply_filters( 'sc_get_event_class', $retval, $object_id );
}

/**
 * Get names of days for a calendar view
 *
 * Return value is shifted according to the start-of-week setting by default,
 * and can be flipped to only returning the day-of-week keys.
 *
 * @since 2.1.3
 *
 * @param string     $size Default large. "large", "mid", or "small".
 * @param bool       $sow  Default true. True uses start-of-week, False uses Sunday.
 *                         Accepts numeric value 0-6 or name of day to override.
 * @param string     $type Default values. "values" or "keys".
 *
 * @return array
 * @global WP_Locale $wp_locale
 */
function sc_get_calendar_day_names( $size = 'large', $sow = true, $type = 'values' ) {
	global $wp_locale;

	// Day values ("Sunday" or "S").
	switch ( $size ) {
		case 'small':
			$days = array_values( $wp_locale->weekday_initial );
			break;
		case 'mid':
			$days = array_values( $wp_locale->weekday_abbrev );
			break;
		default:
			$days = $wp_locale->weekday;
			break;
	}

	// Maybe shift according to the start-of-week setting
	if ( false !== $sow ) {

		// Use setting
		if ( in_array( $sow, array( 'true', true, null ), true ) ) {
			$sow = sc_get_week_start_day();

			// 0 - 6 for Sunday - Saturday
		} elseif ( is_numeric( $sow ) ) {
			$sow = (int) $sow;

			// Search for the day
		} elseif ( is_string( $sow ) ) {
			$sow = array_search(
				strtolower( $sow ),
				array_map( 'strtolower', $days )
			);
		}

		// Split the days in half by start-of-week
		$index = array_search( $sow, array_keys( $days ) );
		$start = array_slice( $days, $index, count( $days ), true );
		$end   = array_slice( $days, 0, $index, true );

		// Combine the halves
		$days = $start + $end;
	}

	// Return keys
	if ( 'keys' === $type ) {
		$days = array_keys( $days );

		// Return values
	} elseif ( 'values' === $type ) {
		$days = array_values( $days );
	}

	// Return the days
	return $days;
}

/**
 * Get day offset for a calendar
 *
 * Returns the number of days into a calendar view the timestamp is, taking into
 * account the start-of-week setting.
 *
 * @since 2.1.3
 *
 * @param int $timestamp
 *
 * @return int
 */
function sc_get_calendar_day_offset( $timestamp = '' ) {

	// Day name keys, with offset
	$days = sc_get_calendar_day_names( 'large', true, 'keys' );

	// Get the offset
	$off = (int) gmdate( 'w', $timestamp );

	// Return the offset
	return (int) array_search(
		$off,
		$days,
		true
	);
}

/**
 * Build Calendar for Event post type
 *
 * @since 1.0.0
 * @since 3.7.0 Use sugar_calendar_get_events_within_range() instead.
 *
 * @param                    $month
 * @param                    $year
 * @param string             $size
 * @param null|string        $category
 * @param null|string        $start_of_week
 * @param DateTimeZone|false $timezone      TimeZone to convert the event datetime to.
 *
 * @return string
 * @author Syamil MJ
 * @credit http://davidwalsh.name/php-calendar
 *
 */
function sc_draw_calendar( $month, $year, $size = 'large', $category = null, $start_of_week = null, $timezone = false ) {

	// Day names
	$day_names = sc_get_calendar_day_names( $size, $start_of_week );

	//start draw table
	$calendar = '<table cellpadding="0" cellspacing="0" class="calendar sc-table">';
	$calendar .= '<tr class="calendar-row">';

	for ( $i = 0; $i <= 6; $i ++ ) {
		$calendar .= '<th class="calendar-day-head">' . esc_html( $day_names[ $i ] ) . '</th>';
	}
	$calendar .= '</tr>';

	//days and weeks vars now
	$display_time      = gmmktime( 0, 0, 0, $month, 1, $year );
	$running_day       = sc_get_calendar_day_offset( $display_time );
	$days_in_month     = gmdate( 't', $display_time );
	$days_in_this_week = 1;
	$day_counter       = 0;

	//get today's date
	$time        = (int) sugar_calendar_get_request_time();
	$today_day   = gmdate( 'j', $time );
	$today_month = gmdate( 'm', $time );
	$today_year  = gmdate( 'Y', $time );

	$first_day_of_month = strtotime( 'first day of this month', $display_time );
	$last_day_of_month  = strtotime( 'last day of this month', $display_time );

	$start_period = new DateTimeImmutable( gmdate( 'Y-m-d 00:00:01', $first_day_of_month ) );
	$end_period   = new DateTimeImmutable( gmdate( 'Y-m-d 23:59:59', $last_day_of_month ) );

	if ( $timezone ) {
		$start_period = $start_period->modify( '-1 day' );
		$end_period   = $end_period->modify( '+1 day' );
	}

	$all_events = sugar_calendar_get_events_within_range(
		[
			'start_range' => $start_period,
			'end_range'   => $end_period,
			'category'    => $category,
		]
	);

	//row for week one */
	$calendar .= '<tr class="calendar-row">';

	//print "blank" days until the first of the current week
	for ( $x = 0; $x < $running_day; $x ++ ) {
		$calendar .= '<td class="calendar-day-np past" valign="top"></td>';
		$days_in_this_week ++;
	}

	//keep going with days
	for ( $list_day = 1; $list_day <= $days_in_month; $list_day ++ ) {
		$cal_event = '';
		$today     = ( $today_day == $list_day && $today_month == $month && $today_year == $year )
			? 'today'
			: ( ( $today_day > $list_day && $today_month >= $month && $today_year >= $year )
				? 'past'
				: 'upcoming' );

		// Filter events
		$events    = Helper::filter_events_by_day( $all_events, $list_day, $month, $year, $timezone );
		$cal_event = sc_get_event_calendar_links( $events, $size );
		$class     = sc_get_day_class( $events, "calendar-day {$today}", $list_day, $month, $year );
		$td_style  = sc_get_day_style_attr( $events );
		$cal_day   = '<td class="' . esc_attr( $class ) . '" valign="top" ' . $td_style . '><div class="sc_day_div">';

		// add in the day numbering
		$cal_day  .= '<div class="day-number day-' . $list_day . '">' . $list_day . '</div>';
		$calendar .= $cal_day;
		$calendar .= $cal_event ? $cal_event : '';
		$calendar .= '</div></td>';

		if ( $running_day == 6 ) {
			if ( ( $list_day < $days_in_month ) ) {
				$calendar          .= '</tr>';
				$calendar          .= '<tr class="calendar-row">';
				$running_day       = - 1;
				$days_in_this_week = 0;
			}
		}

		$days_in_this_week ++;
		$running_day ++;
		$day_counter ++;
	}

	//finish the rest of the days in the week
	if ( $days_in_this_week < 8 ) {
		for ( $x = 1; $x <= ( 8 - $days_in_this_week ); $x ++ ) {
			$calendar .= '<td class="calendar-day-np upcoming" valign="top"><div class="sc_day_div"></div></td>';
		}
	}

	//final row
	$calendar .= '</tr>';

	//end the table
	$calendar .= '</table>';

	// Clean up
	wp_reset_postdata();

	//all done, return the completed table
	return $calendar;
}

/**
 * Added function to call default sc_draw_calendar()
 *
 * Uses the start-of-week setting along with the requested display time to
 * determine the best possible starting day to show a full month view.
 *
 * @since 1.0.0
 * @since 3.1.2 Support `$timezone`.
 *
 * @param                    $display_time
 * @param string             $size
 * @param null|string        $category
 * @param null|string        $start_of_week
 * @param DateTimeZone|false $timezone      TimeZone to convert the event datetime to.
 *
 * @return string
 */
function sc_draw_calendar_month( $display_time, $size = 'large', $category = null, $start_of_week = null, $timezone = false ) {

	$month = gmdate( 'n', $display_time );
	$year  = gmdate( 'Y', $display_time );

	return sc_draw_calendar( $month, $year, $size, $category, $start_of_week, $timezone );
}

/**
 * Draw the weekly calendar
 *
 * Uses the start-of-week setting along with the requested display time to
 * determine the best possible starting day to show a full 1 week view.
 *
 * @since 1.0.0
 * @since 3.1.2 Support `$timezone`.
 *
 * @param                    $display_time
 * @param string             $size
 * @param null|string        $category
 * @param null|string        $start_of_week
 * @param DateTimeZone|false $timezone      TimeZone to convert the event datetime to.
 *
 * @return string
 */
function sc_draw_calendar_week($display_time, $size = 'large', $category = null, $start_of_week = null, $timezone = false ) {

	// Day names
	$day_names = sc_get_calendar_day_names( $size, $start_of_week );

	//start draw table
	$calendar = '<table cellpadding="0" cellspacing="0" class="calendar sc-table">';
	$calendar .= '<tr class="calendar-row">';

	for ( $i = 0; $i <= 6; $i ++ ) {
		$calendar .= '<th class="calendar-day-head">' . esc_html( $day_names[ $i ] ) . '</th>';
	}
	$calendar .= '</tr>';

	// get the values for the first day of week where $display_time occurs
	$day_of_week   = sc_get_calendar_day_offset( $display_time );
	$display_time  = strtotime( '-' . $day_of_week . ' days', $display_time );
	$display_day   = gmdate( 'j', $display_time );
	$display_month = gmdate( 'n', $display_time );
	$display_year  = gmdate( 'Y', $display_time );

	//get today's date
	$time        = (int) sugar_calendar_get_request_time();
	$today_day   = gmdate( 'j', $time );
	$today_month = gmdate( 'm', $time );
	$today_year  = gmdate( 'Y', $time );

	// start row
	$calendar .= '<tr class="calendar-row">';

	// Get the events
	$all_events = sc_get_events_for_calendar( $display_day, $display_month, $display_year, $category );

	// output seven days
	for ( $list_day = 1; $list_day <= 7; $list_day ++ ) {
		$cal_event = '';
		$today     = ( $today_day == $display_day && $today_month == $display_month && $today_year == $display_year )
			? 'today'
			: ( ( $today_day > $display_day && $today_month >= $display_month && $today_year >= $display_year )
				? 'past'
				: 'upcoming' );

		// Filter events
		$events    = Helper::filter_events_by_day( $all_events, $display_day, $display_month, $display_year, $timezone );
		$cal_event = sc_get_event_calendar_links( $events, $size );
		$class     = sc_get_day_class( $events, "calendar-day {$today}", $list_day, $display_month, $display_year );
		$td_style  = sc_get_day_style_attr( $events );
		$cal_day   = '<td class="' . esc_attr( $class ) . '" valign="top" ' . $td_style . '><div class="sc_day_div">';

		// add in the day numbering
		$cal_day  .= '<div class="day-number day-' . $display_day . '">' . $display_day . '</div>';
		$calendar .= $cal_day;
		$calendar .= $cal_event ? $cal_event : '';
		$calendar .= '</div></td>';

		$display_time  = strtotime( '+1 day', $display_time );
		$display_day   = gmdate( 'j', $display_time );
		$display_month = gmdate( 'n', $display_time );
		$display_year  = gmdate( 'Y', $display_time );
	}

	// finish row
	$calendar .= '</tr>';

	// end the calendar
	$calendar .= '</table>';

	// Clean up
	wp_reset_postdata();

	//all done, return the completed table
	return $calendar;
}

/**
 * Draw the two week calendar
 *
 * Uses the start-of-week setting along with the requested display time to
 * determine the best possible starting day to show a full 2 week view.
 *
 * @since 1.0.0
 * @since 3.1.2 Support `$timezone`.
 * @since 3.7.0 Use sugar_calendar_get_events_within_range() instead.
 *
 * @param                    $display_time
 * @param string             $size
 * @param null|string        $category
 * @param null|string        $start_of_week
 * @param DateTimeZone|false $timezone      TimeZone to convert the event datetime to.
 *
 * @return string
 */
function sc_draw_calendar_2week( $display_time, $size = 'large', $category = null, $start_of_week = null, $timezone = false ) {

	// Day names
	$day_names = sc_get_calendar_day_names( $size, $start_of_week );

	//start draw table
	$calendar = '<table cellpadding="0" cellspacing="0" class="calendar sc-table">';
	$calendar .= '<tr class="calendar-row">';

	for ( $i = 0; $i <= 6; $i ++ ) {
		$calendar .= '<th class="calendar-day-head">' . esc_html( $day_names[ $i ] ) . '</th>';
	}
	$calendar .= '</tr>';

	// get the values for the first day of week where $display_time occurs
	$day_of_week   = sc_get_calendar_day_offset( $display_time );
	$display_time  = strtotime( '-' . $day_of_week . ' days', $display_time );
	$display_day   = gmdate( 'j', $display_time );
	$display_month = gmdate( 'n', $display_time );
	$display_year  = gmdate( 'Y', $display_time );

	//get today's date
	$time        = (int) sugar_calendar_get_request_time();
	$today_day   = gmdate( 'j', $time );
	$today_month = gmdate( 'm', $time );
	$today_year  = gmdate( 'Y', $time );

	// start row
	$calendar .= '<tr class="calendar-row">';

	$start_period = new DateTimeImmutable( gmdate( 'Y-m-d 00:00:01', $display_time ) );
	$end_period   = ( new DateTimeImmutable( gmdate( 'Y-m-d 23:59:59', $display_time ) ) )->modify( '+13 days' );

	if ( $timezone ) {
		$start_period = $start_period->modify( '-1 day' );
		$end_period   = $end_period->modify( '+1 day' );
	}

	$all_events = sugar_calendar_get_events_within_range(
		[
			'start_range' => $start_period,
			'end_range'   => $end_period,
			'category'    => $category,
		]
	);

	// output seven days
	for ( $list_day = 1; $list_day <= 14; $list_day ++ ) {
		$cal_event = '';
		$today     = ( $today_day == $display_day && $today_month == $display_month && $today_year == $display_year )
			? 'today'
			: ( ( $today_day > $display_day && $today_month >= $display_month && $today_year >= $display_year )
				? 'past'
				: 'upcoming' );

		// Filter events
		$events    = Helper::filter_events_by_day( $all_events, $display_day, $display_month, $display_year, $timezone );
		$cal_event = sc_get_event_calendar_links( $events, $size );
		$class     = sc_get_day_class( $events, "calendar-day {$today}", $list_day, $display_month, $display_year );
		$td_style  = sc_get_day_style_attr( $events );
		$cal_day   = '<td class="' . esc_attr( $class ) . '" valign="top" ' . $td_style . '><div class="sc_day_div">';

		// add in the day numbering
		$cal_day  .= '<div class="day-number day-' . $display_day . '">' . $display_day . '</div>';
		$calendar .= $cal_day;
		$calendar .= $cal_event ? $cal_event : '';
		$calendar .= '</div></td>';

		if ( $list_day == 7 ) {
			$calendar .= '</tr>';
			$calendar .= '<tr class="calendar-row">';
		}

		$display_time  = strtotime( '+1 day', $display_time );
		$display_day   = gmdate( 'j', $display_time );
		$display_month = gmdate( 'n', $display_time );
		$display_year  = gmdate( 'Y', $display_time );
	}

	// finish row
	$calendar .= '</tr>';

	// end the calendar
	$calendar .= '</table>';

	// Clean up
	wp_reset_postdata();

	//all done, return the completed table
	return $calendar;
}

/**
 * Draw the daily calendar
 *
 * The start-of-week setting is ignored, and only the display time is used.
 *
 * @since 1.0.0
 * @since 3.1.2 Support `$timezone`.
 * @since 3.7.0 Use sugar_calendar_get_events_within_range() instead.
 *
 * @param                    $display_time
 * @param string             $size
 * @param null|string        $category
 * @param null|string        $start_of_week
 * @param DateTimeZone|false $timezone      TimeZone to convert the event datetime to.
 *
 * @return string
 */
function sc_draw_calendar_day( $display_time, $size = 'large', $category = null, $start_of_week = null, $timezone = false ) {

	// Day & names
	$day_of_week   = gmdate( 'w', $display_time );
	$start_of_week = false; // Always override
	$day_names     = sc_get_calendar_day_names( $size, $start_of_week );
	$day_name      = $day_names[ $day_of_week ];

	//start draw table
	$calendar = '<table cellpadding="0" cellspacing="0" class="calendar">';
	$calendar .= '<tr class="calendar-row">';
	$calendar .= '<th class="calendar-day-head">' . esc_html( $day_name ) . '</th>';
	$calendar .= '</tr>';

	$display_day   = gmdate( 'j', $display_time );
	$display_month = gmdate( 'n', $display_time );
	$display_year  = gmdate( 'Y', $display_time );

	//get today's date
	$time        = (int) sugar_calendar_get_request_time();
	$today_day   = gmdate( 'j', $time );
	$today_month = gmdate( 'm', $time );
	$today_year  = gmdate( 'Y', $time );

	// start row
	$calendar .= '<tr class="calendar-row">';

	$start_period = new DateTimeImmutable( gmdate( 'Y-m-d 00:00:01', $display_time ) );
	$end_period   = new DateTimeImmutable( gmdate( 'Y-m-d 23:59:59', $display_time ) );

	if ( $timezone ) {
		$start_period = $start_period->modify( '-1 day' );
		$end_period   = $end_period->modify( '+1 day' );
	}

	$all_events = sugar_calendar_get_events_within_range(
		[
			'start_range' => $start_period,
			'end_range'   => $end_period,
			'category'    => $category,
		]
	);

	// output current day
	$today = ( $today_day == $display_day && $today_month == $display_month && $today_year == $display_year )
		? 'today'
		: ( ( $today_day > $display_day && $today_month >= $display_month && $today_year >= $display_year )
			? 'past'
			: 'upcoming' );

	$cal_event = '';

	// Filter events
	$events    = Helper::filter_events_by_day( $all_events, $display_day, $display_month, $display_year, $timezone );
	$cal_event = sc_get_event_calendar_links( $events, $size );
	$class     = sc_get_day_class( $events, "calendar-day {$today}", $display_day, $display_month, $display_year );
	$td_style  = sc_get_day_style_attr( $events );
	$cal_day   = '<td class="' . esc_attr( $class ) . '" valign="top" ' . $td_style . '><div class="sc_day_div">';

	// add in the day numbering
	$cal_day  .= '<div class="day-number day-' . $display_day . '">' . $display_day . '</div>';
	$calendar .= $cal_day;
	$calendar .= $cal_event;
	$calendar .= '</div></td>';

	// finish row
	$calendar .= '</tr>';

	// end the calendar
	$calendar .= '</table>';

	// Clean up
	wp_reset_postdata();

	//all done, return the completed table
	return $calendar;
}

/**
 * Draw the four day calendar
 *
 * The start-of-week setting is ignored, and only the display time is used.
 *
 * @since 1.0.0
 * @since 3.1.2 Support `$timezone`.
 *
 * @param                    $display_time
 * @param string             $size
 * @param null|string        $category
 * @param null|string        $start_of_week
 * @param DateTimeZone|false $timezone      TimeZone to convert the event datetime to.
 *
 * @return string
 */
function sc_draw_calendar_4day( $display_time, $size = 'large', $category = null, $start_of_week = false, $timezone = false ) {

	// Day & name
	$day_of_week   = gmdate( 'w', $display_time );
	$start_of_week = false; // Always override
	$day_names     = sc_get_calendar_day_names( $size, $start_of_week );

	//start draw table
	$calendar = '<table cellpadding="0" cellspacing="0" class="calendar sc-table">';
	$calendar .= '<tr class="calendar-row">';

	for ( $i = 0; $i <= 3; $i ++ ) {
		$day_name = $day_names[ $day_of_week ];
		$calendar .= '<th class="calendar-day-head">' . esc_html( $day_name ) . '</th>';

		if ( $day_of_week == 6 ) {
			$day_of_week = 0;
		} else {
			$day_of_week ++;
		}
	}
	$calendar .= '</tr>';

	$display_day   = gmdate( 'j', $display_time );
	$display_month = gmdate( 'n', $display_time );
	$display_year  = gmdate( 'Y', $display_time );

	//get today's date
	$time        = (int) sugar_calendar_get_request_time();
	$today_day   = gmdate( 'j', $time );
	$today_month = gmdate( 'm', $time );
	$today_year  = gmdate( 'Y', $time );

	// start row
	$calendar .= '<tr class="calendar-row">';

	// Get the events
	$all_events = sc_get_events_for_calendar( $display_day, $display_month, $display_year, $category );

	// output four days
	for ( $list_day = 0; $list_day <= 3; $list_day ++ ) {
		$cal_event = '';
		$today     = ( $today_day == $display_day && $today_month == $display_month && $today_year == $display_year )
			? 'today'
			: ( ( $today_day > $display_day && $today_month >= $display_month && $today_year >= $display_year )
				? 'past'
				: 'upcoming' );

		// Filter events
		$events    = Helper::filter_events_by_day( $all_events, $display_day, $display_month, $display_year, $timezone );
		$cal_event = sc_get_event_calendar_links( $events, $size );
		$class     = sc_get_day_class( $events, "calendar-day {$today}", $display_day, $display_month, $display_year );
		$td_style  = sc_get_day_style_attr( $events );
		$cal_day   = '<td class="' . esc_attr( $class ) . '" valign="top" ' . $td_style . '><div class="sc_day_div">';

		// add in the day numbering
		$cal_day  .= '<div class="day-number day-' . $display_day . '">' . $display_day . '</div>';
		$calendar .= $cal_day;
		$calendar .= $cal_event ? $cal_event : '';
		$calendar .= '</div></td>';

		$display_time  = strtotime( '+1 day', $display_time );
		$display_day   = gmdate( 'j', $display_time );
		$display_month = gmdate( 'n', $display_time );
		$display_year  = gmdate( 'Y', $display_time );
	}

	// finish row
	$calendar .= '</tr>';

	// end the calendar
	$calendar .= '</table>';

	// Clean up
	wp_reset_postdata();

	//all done, return the completed table
	return $calendar;
}

/**
 * Month number to name
 *
 * Takes a month number and returns the three letter name of it.
 *
 * @access      public
 * @since       1.0.0
 * @return      string
 */
function sc_month_num_to_name( $n = 1 ) {
	return $GLOBALS['wp_locale']->get_month( $n );
}

/**
 * Determines whether the current page has a calendar on it
 *
 * @access      public
 * @since       1.0.0
 * @return      string
 */
function sc_is_calendar_page() {
	$post = get_post();

	if ( ! is_object( $post ) ) {
		return false;
	}

	if ( has_shortcode( $post->post_content, 'sc_events_calendar' ) ) {
		return true;
	}

	return false;
}

/**
 * Determines whether a widget is in use
 *
 * @since 2.0.0
 * @return bool
 */
function sc_using_widget() {

	// Default return value
	$retval = false;

	// Array of widget IDs
	$widget_ids = sc_get_widget_ids();

	// Bail if there are no widgets
	if ( empty( $widget_ids ) ) {
		return $retval;
	}

	// Loop through Legacy widgets, and check if any are active
	foreach ( $widget_ids as $widget_id ) {
		if ( is_active_widget( false, false, $widget_id ) ) {
			$retval = true;
			continue;
		}
	}

	// Return if using a widget
	return (bool) $retval;
}

/**
 * Return array of valid calendar types.
 *
 * @since 2.0.0
 *
 * @return array
 */
function sc_get_valid_calendar_types() {
	return array(
		'day',
		'4day',
		'week',
		'2week',
		'month',

		// See: https://github.com/sugarcalendar/standard/issues/300
		'4days',
		'2weeks'
	);
}

/**
 * Retrieves the calendar date for an event
 *
 * @access      public
 * @since       1.0.0
 *
 * @param int  $event_id  int The ID number of the event
 * @param bool $formatted bool Whether to return a time stamp or the nicely formatted date
 *
 * @return      string
 */
function sc_get_event_date( $event_id = 0, $formatted = true ) {

	// Get start & end dates & times
	$retval = get_post_meta( $event_id, 'sc_event_date_time', true );

	// Bail if no event start datetime (how'd this happen?)
	if ( empty( $retval ) ) {
		return $retval;
	}

	// Return date if not formatting
	if ( empty( $formatted ) ) {
		return $retval;
	}

	// Get the event
	$event = sugar_calendar_get_event_by_object( $event_id );

	// Get the date format, and format start
	$format = sc_get_date_format();
	$dt     = $event->start_date( 'Y-m-d' );

	// Default time zone
	$tz = 'floating';

	// Maybe use the start time zone
	if ( ! empty( $event->start_tz ) ) {
		$tz = $event->start_tz;
	}

	$start_date = sugar_calendar_format_date_i18n( $format, $retval );
	$start_html = '<span class="sc-date-start"><time datetime="' . esc_attr( $dt ) . '" data-timezone="' . esc_attr( $tz ) . '">' . esc_html( $start_date ) . '</time></span>';

	// Get the end date
	$end = get_post_meta( $event_id, 'sc_event_end_date_time', true );

	// Maybe append the end date
	if ( empty( $end ) ) {
		return $start_html;
	}

	// End date
	$end_date = sugar_calendar_format_date_i18n( $format, $end );

	// Add end to start, with separator
	if ( $end_date !== $start_date ) {

		// Default time zone
		$tz = 'floating';

		// All-day Events have floating time zones
		if ( ! empty( $event->end_tz ) && ! $event->is_all_day() ) {
			$tz = $event->end_tz;

			// Maybe fallback to the start time zone
		} elseif ( empty( $event->end_tz ) && ! empty( $event->start_tz ) ) {
			$tz = $event->start_tz;
		}

		// End date
		$dt = $event->end_date( 'Y-m-d' );

		// Output
		$end_html = '<span class="sc-date-start-end-sep"> &ndash; </span><span class="sc-date-end"><time datetime="' . esc_attr( $dt ) . '" data-timezone="' . esc_attr( $tz ) . '">' . esc_html( $end_date ) . '</time></span>';
		$retval   = $start_html . $end_html;

		// Just the start
	} else {
		$retval = $start_html;
	}

	// Return the dates & times
	return $retval;
}

/**
 * Returns a formatted date for an event and given timestamp.
 * The timestamp is given because this could be a recurrence of an event.
 * Note: This does not display multi-day events, only start times.
 *
 * @since 1.6.0
 *
 * @param int    $event_id
 * @param int    $timestamp
 * @param string $timezone
 *
 * @return string
 */
function sc_get_formatted_date( $event_id = 0, $timestamp = null, $timezone = null ) {

	// Default return value
	$retval = '';

	// Bail if no event and no timestamp to derive a date from
	if ( empty( $event_id ) && empty( $timestamp ) ) {
		return $retval;
	}

	// Get a timestamp from the start date & time
	if ( ! empty( $event_id ) && empty( $timestamp ) ) {
		$timestamp = get_post_meta( $event_id, 'sc_event_date_time', true );
		$timezone  = get_post_meta( $event_id, 'sc_event_timezone', true );
	}

	// Maybe format a timestamp if one was found
	if ( ! empty( $timestamp ) ) {
		$format = sc_get_date_format();
		$retval = sugar_calendar_format_date_i18n( $format, $timestamp, $timezone );
	}

	// Return a possibly formatted start date & time
	return $retval;
}

/**
 * Retrieves the time for an event
 *
 * @access      public
 * @since       1.0.0
 *
 * @param int $event_id int The ID number of the event
 *
 * @return      array
 */
function sc_get_event_time( $event_id ) {

	// Get start & end times
	$start_time = sc_get_event_start_time( $event_id );
	$end_time   = sc_get_event_end_time( $event_id );

	// Return array of start & end times
	return apply_filters( 'sc_event_time', array(
		'start' => $start_time,
		'end'   => $end_time
	) );
}

/**
 * Retrieves the start time for an event
 *
 * @access      public
 * @since       1.0.0
 *
 * @param int $event_id int The ID number of the event
 *
 * @return      string
 */
function sc_get_event_start_time( $event_id = 0 ) {

	// Get the start date
	$start = get_post_meta( $event_id, 'sc_event_date', true );

	// Bail if no start time
	if ( empty( $start ) ) {
		return '';
	}

	// Use meta keys for back-compat
	$day      = get_post_meta( $event_id, 'sc_event_day_of_month', true );
	$month    = get_post_meta( $event_id, 'sc_event_month', true );
	$year     = get_post_meta( $event_id, 'sc_event_year', true );
	$hour     = get_post_meta( $event_id, 'sc_event_time_hour', true );
	$minute   = get_post_meta( $event_id, 'sc_event_time_minute', true );
	$am_pm    = get_post_meta( $event_id, 'sc_event_time_am_pm', true );
	$timezone = get_post_meta( $event_id, 'sc_event_timezone', true );

	// Adjust for meridiem
	if ( ( $am_pm === 'pm' ) && ( $hour < 12 ) ) {
		$hour += 12;
	} elseif ( ( $am_pm === 'am' ) && ( $hour >= 12 ) ) {
		$hour -= 12;
	}

	// Default return value
	$retval = null;

	// Format time value if not null
	if ( ( false !== $hour ) && ( false !== $minute ) ) {
		$format = sc_get_time_format();
		$mktime = gmmktime( $hour, $minute, 0, $month, $day, $year );
		$retval = sugar_calendar_format_date_i18n( $format, $mktime, $timezone );
	}

	return apply_filters( 'sc_event_start_time', $retval, $hour, $minute, $am_pm );
}

/**
 * Retrieves the end time for an event
 *
 * @access      public
 * @since       1.0.0
 *
 * @param int $event_id int The ID number of the event
 *
 * @return      string
 */
function sc_get_event_end_time( $event_id = 0 ) {

	// Get the end date
	$end = get_post_meta( $event_id, 'sc_event_end_date', true );

	// Bail if no end in sight (ha!)
	if ( empty( $end ) ) {
		return '';
	}

	// Use meta keys for back-compat
	$day      = get_post_meta( $event_id, 'sc_event_end_day_of_month', true );
	$month    = get_post_meta( $event_id, 'sc_event_end_month', true );
	$year     = get_post_meta( $event_id, 'sc_event_end_year', true );
	$hour     = get_post_meta( $event_id, 'sc_event_end_time_hour', true );
	$minute   = get_post_meta( $event_id, 'sc_event_end_time_minute', true );
	$am_pm    = get_post_meta( $event_id, 'sc_event_end_time_am_pm', true );
	$timezone = get_post_meta( $event_id, 'sc_event_end_timezone', true );

	// Adjust for meridiem
	if ( ( $am_pm === 'pm' ) && ( $hour < 12 ) ) {
		$hour += 12;
	} elseif ( ( $am_pm === 'am' ) && ( $hour >= 12 ) ) {
		$hour -= 12;
	}

	// Default return value
	$retval = null;

	// Format time value if not null
	if ( ( false !== $hour ) && ( false !== $minute ) ) {
		$format = sc_get_time_format();
		$mktime = gmmktime( $hour, $minute, 0, $month, $day, $year );
		$retval = sugar_calendar_format_date_i18n( $format, $mktime, $timezone );
	}

	return apply_filters( 'sc_event_end_time', $retval, $hour, $minute );
}

/**
 * Checks if an event is recurring
 *
 * @access      public
 * @since       1.1
 *
 * @param int $event_id int The ID number of the event
 *
 * @return      array
 */
function sc_is_recurring( $event_id ) {
	$recurring = get_post_meta( $event_id, 'sc_event_recurring', true );
	$retval    = ! empty( $recurring ) && ( 'none' !== $recurring );

	return $retval;
}

/**
 * Retrieves the recurring period for an event
 *
 * @access      public
 * @since       1.2
 *
 * @param int $event_id int The ID number of the event
 *
 * @return      string
 */
function sc_get_recurring_period( $event_id ) {
	$period = get_post_meta( $event_id, 'sc_event_recurring', true );

	return apply_filters( 'sc_recurring_period', $period, $event_id );
}

/**
 * Retrieves the recurring stop date for an event
 *
 * @access      public
 * @since       1.2
 *
 * @param int $event_id int The ID number of the event
 *
 * @return      mixed
 */
function sc_get_recurring_stop_date( $event_id ) {

	$recur_until = get_post_meta( $event_id, 'sc_recur_until', true );

	if ( ! sc_is_recurring( $event_id ) ) {
		$recur_until = false;
	}

	if ( strlen( trim( $recur_until ) ) == 0 ) {
		$recur_until = false;
	}

	return apply_filters( 'sc_recurring_stop_date', $recur_until, $event_id );
}

/**
 * Shows the date of a recurring event
 *
 * @access      public
 * @since       1.1.1
 * @return      array
 */
function sc_show_single_recurring_date( $event_id = 0 ) {
	echo sc_get_recurring_description( $event_id );
}

/**
 * Get the date of a recurring event
 *
 * @access      public
 * @since       2.0.9
 * @return      array
 */
function sc_get_recurring_description( $event_id = 0 ) {

	// Default return value
	$retval = '';

	$recurring_schedule = get_post_meta( $event_id, 'sc_event_recurring', true );
	$event_date_time    = get_post_meta( $event_id, 'sc_event_date_time', true );
	$recur_until        = sc_get_recurring_stop_date( $event_id );
	$date_format        = sc_get_date_format();

	$format = apply_filters( 'sc_recurring_date_format', array(), $event_date_time, $recur_until );

	if ( $recur_until ) :

		switch ( $recurring_schedule ) {

			case 'weekly':

				if ( isset( $format['weekly'] ) ) {
					$retval = $format['weekly'];
				} else {
					$retval = sprintf( __( 'Starts %s then every %s until %s', 'sugar-calendar-lite' ),

						// @todo needs time zone support
						date_i18n( $date_format, $event_date_time ),
						date_i18n( 'l', $event_date_time ),
						date_i18n( $date_format, $recur_until ) );
				}
				break;

			case 'monthly':

				if ( isset( $format['monthly'] ) ) {
					$retval = $format['monthly'];
				} else {
					$retval = sprintf( __( 'Starts %s then every month on the %s until %s', 'sugar-calendar-lite' ),

						// @todo needs time zone support
						date_i18n( $date_format, $event_date_time ),
						date_i18n( 'jS', $event_date_time ),
						date_i18n( $date_format, $recur_until ) );
				}
				break;

			case 'yearly':

				if ( isset( $format['yearly'] ) ) {
					$retval = $format['yearly'];
				} else {
					$retval = sprintf( __( 'Starts %s then every year on the %s of %s until %s', 'sugar-calendar-lite' ),

						// @todo needs time zone support
						date_i18n( $date_format, $event_date_time ),
						date_i18n( 'jS', $event_date_time ),
						date_i18n( 'F', $event_date_time ),
						date_i18n( $date_format, $recur_until ) );
				}
				break;
		}

	else :

		switch ( $recurring_schedule ) {

			case 'weekly':

				if ( isset( $format['weekly'] ) ) {
					$retval = $format['weekly'];
				} else {
					$retval = sprintf( __( 'Starts %s then every %s', 'sugar-calendar-lite' ),

						// @todo needs time zone support
						date_i18n( $date_format, $event_date_time ),
						date_i18n( 'l', $event_date_time ) );
				}
				break;

			case 'monthly':

				if ( isset( $format['monthly'] ) ) {
					$retval = $format['monthly'];
				} else {
					$retval = sprintf( __( 'Starts %s then every month on the %s', 'sugar-calendar-lite' ),

						// @todo needs time zone support
						date_i18n( $date_format, $event_date_time ),
						date_i18n( 'jS', $event_date_time ) );
				}
				break;

			case 'yearly':

				if ( isset( $format['yearly'] ) ) {
					$retval = $format['yearly'];
				} else {
					$retval = sprintf( __( 'Starts %s then every year on the %s of %s', 'sugar-calendar-lite' ),

						// @todo needs time zone support
						date_i18n( $date_format, $event_date_time ),
						date_i18n( 'jS', $event_date_time ),
						date_i18n( 'F', $event_date_time ) );
				}
				break;
		}
	endif;

	// Return the formatted recurring description
	return $retval;
}

/**
 * Retrieves the maximum number of events to include in a theme-side query.
 *
 * @access      public
 * @since       2.0.7
 * @return      string
 */
function sc_get_number_of_events() {

	$number = Options::get( 'number_of_events' );

	// Filter and return
	return (int) apply_filters( 'sc_number_of_events', $number );
}

/**
 * Retrieves the date format
 *
 * @access      public
 * @since       1.5
 * @return      string
 */
function sc_get_date_format() {

	$format = Options::get( 'date_format' );

	// Filter and return
	return apply_filters( 'sc_date_format', $format );
}

/**
 * Retrieves the time format
 *
 * @access      public
 * @since       1.5
 * @return      string
 */
function sc_get_time_format() {

	$format = Options::get( 'time_format' );

	// Filter and return
	return apply_filters( 'sc_time_format', $format );
}

/**
 * Retrieves the week start day. 0 = Sunday, 1 = Monday, etc
 *
 * @access      public
 * @since       1.5
 * @return      string
 */
function sc_get_week_start_day() {

	$start_day = Options::get( 'start_of_week' );

	// Filter and return
	return apply_filters( 'sc_week_start_day', $start_day );
}

/**
 * Retrieves the time zone.
 *
 * @access      public
 * @since       2.1.1
 * @return      string
 */
function sc_get_timezone() {

	$timezone = Options::get( 'timezone' );

	// Filter and return
	return apply_filters( 'sc_timezone', $timezone );
}

/**
 * Retrieves the day color style.
 *
 * @access      public
 * @since       2.1.9
 * @return      string
 */
function sc_get_day_color_style() {

	$option = Options::get( 'day_color_style', 'none' );

	// Filter and return
	return apply_filters( 'sc_day_color_style', $option );
}

/**
 * For recurring events, calculate recurrences, then save to sc_all_recurring meta
 *
 * @since 1.6.0
 *
 * @param int $event_id
 */
function sc_update_recurring_events( $event_id = 0 ) {

	if ( ! empty( $event_id ) ) {
		$events[] = $event_id;

	} else {
		$events = get_posts( array(
			'numberposts' => - 1,
			'post_type'   => sugar_calendar_get_event_post_type_id(),
			'post_status' => 'publish',
			'fields'      => 'ids',
			'order'       => 'asc'
		) );
	}

	foreach ( $events as $event_id ) {

		$type = get_post_meta( $event_id, 'sc_event_recurring', true );

		if ( ! empty( $type ) && ( 'none' !== $type ) ) {
			$recurring = sc_calculate_recurring( $event_id );
			update_post_meta( $event_id, 'sc_all_recurring', $recurring );
		}
	}
}

/**
 * This function calculates all occurrences for an event.
 *
 * @param $event_id
 *
 * @return array
 */
function sc_calculate_recurring( $event_id ) {
	$start = get_post_meta( $event_id, 'sc_event_date_time', true );
	$until = get_post_meta( $event_id, 'sc_recur_until', true );
	$type  = get_post_meta( $event_id, 'sc_event_recurring', true );

	$recurring = array();

	// add first occurrence of event
	$recurring[] = $start;
	$current     = $start;

	while ( $until > $current ) {
		switch ( $type ) {
			case 'weekly':
				$current = strtotime( "+1 week", $current );
				break;
			case 'monthly':
				$current = strtotime( "+1 month", $current );
				break;
			case 'yearly':
				$current = strtotime( "+1 year", $current );
				break;
		}

		if ( $until > $current ) {
			$recurring[] = $current;
		}
	}

	return apply_filters( 'sc_calculate_recurring', $recurring );
}

/**
 * Get an array of all events keyed by start time timestamp
 *
 * Array will be sorted ascending by timestamp
 *
 * @since 1.6.0
 *
 * @param string $category
 *
 * @return array $full_list
 */
function sc_get_all_events( $category = null ) {
	$args = array(
		'numberposts' => - 1,
		'post_type'   => sugar_calendar_get_event_post_type_id(),
		'post_status' => 'publish',
		'orderby'     => 'meta_value_num',
		'fields'      => 'ids',
		'order'       => 'asc',
	);

	if ( ! is_null( $category ) ) {
		$tax          = sugar_calendar_get_calendar_taxonomy_id();
		$args[ $tax ] = $category;
	}

	$full_list = array();

	$events = get_posts( apply_filters( 'sc_calendar_query_args', $args ) );

	foreach ( $events as $event_id ) {

		$start = get_post_meta( $event_id, 'sc_event_date_time', true );
		$type  = get_post_meta( $event_id, 'sc_event_recurring', true );

		if ( ! empty( $type ) && 'none' != $type ) {

			$recurring = get_post_meta( $event_id, 'sc_all_recurring', true );

			if ( $recurring ) {
				foreach ( $recurring as $time ) {
					$full_list[ $time ][] = $event_id;
				}
			}
		} else {
			$full_list[ $start ][] = $event_id;
		}
	}

	ksort( $full_list );

	return apply_filters( 'sc_get_all_events', $full_list );
}

/**
 * Order the given list of event post_ids by the time of day they start
 *
 * @param array $events
 *
 * @return array $events_time
 */
function sc_order_events_by_time( $events ) {
	$events_time = array();

	foreach ( $events as $event_id ) {

		// sort by sc_event_time_hour + sc_event_time_minute + sc_event_time_am_pm
		$hour = get_post_meta( $event_id, 'sc_event_time_hour', true );
		if ( empty( $hour ) ) {
			$hour = '00';
		}

		$minute = get_post_meta( $event_id, 'sc_event_time_minute', true );
		if ( empty( $minute ) ) {
			$minute = '00';
		}

		$am_pm = get_post_meta( $event_id, 'sc_event_time_am_pm', true );
		if ( 'pm' === $am_pm ) {
			$hour += 12;
		}

		$events_time[ $hour . $minute . $event_id ] = $event_id;
	}

	ksort( $events_time );

	return $events_time;
}

/** Deprecated ****************************************************************/

/**
 * Retrieves all recurring events.
 *
 * This function is no longer in use.
 *
 * @access      public
 * @since       1.1
 *
 * @param int         $time     Timestamp that recurring event should include
 * @param string      $type     type of recurring event to retrieve
 * @param string|null $category Category to limit events
 *
 * @return array
 * @deprecated  2.0.0
 *
 */
function sc_get_recurring_events( $time, $type, $category = null ) {

	// Default variable values
	$start_key = $end_key = $date = '';

	switch ( $type ) {
		case 'weekly' :
			$start_key = 'sc_event_day_of_week';
			$end_key   = 'sc_event_end_day_of_week';
			$date      = gmdate( 'w', $time );
			break;

		case 'monthly' :
			$start_key = 'sc_event_day_of_month';
			$end_key   = 'sc_event_end_day_of_month';
			$date      = gmdate( 'd', $time );
			break;

		case 'yearly' :
			$date = ''; // these are reset below
			break;
	}

	$args = array(
		'post_type'      => sugar_calendar_get_event_post_type_id(),
		'posts_per_page' => - 1,
		'post_status'    => 'publish',
		'fields'         => 'ids',
		'order'          => 'asc',
		'meta_query'     => array(
			'relation' => 'AND',
			array(
				'key'     => $start_key,
				'value'   => $date,
				'compare' => '<=',
			),
			array(
				'key'     => $end_key,
				'value'   => $date,
				'compare' => '>=',
			),
			array(
				'key'   => 'sc_event_recurring',
				'value' => $type
			),
			array(
				'key'     => 'sc_event_recurring',
				'value'   => 'none',
				'compare' => '!='
			),
			array(
				'key'     => 'sc_event_date_time',
				'value'   => $time,
				'compare' => '<='
			)
		),
	);

	if ( 'yearly' === $type ) {

		// for yearly we have to completely reset the meta query
		$args['meta_query'] = array(
			'relation' => 'AND',
			array(
				'key'     => 'sc_event_day_of_month',
				'value'   => gmdate( 'j', $time ),
				'compare' => '<=',
			),
			array(
				'key'     => 'sc_event_end_day_of_month',
				'value'   => gmdate( 'j', $time ),
				'compare' => '>=',
			),
			array(
				'key'     => 'sc_event_month',
				'value'   => gmdate( 'm', $time ),
				'compare' => '<=',
			),
			array(
				'key'     => 'sc_event_end_month',
				'value'   => gmdate( 'm', $time ),
				'compare' => '>=',
			),
			array(
				'key'     => 'sc_event_date_time',
				'value'   => $time,
				'compare' => '<='
			),
			array(
				'key'   => 'sc_event_recurring',
				'value' => $type
			)
		);
	}

	if ( ! is_null( $category ) ) {
		$tax          = sugar_calendar_get_calendar_taxonomy_id();
		$args[ $tax ] = $category;
	}

	return get_posts( apply_filters( 'sc_recurring_events_query', $args ) );
}

/**
 * Get a list of event post ids ordered by start time for a specific day
 *
 * This function is no longer in use.
 *
 * @since      1.1
 *
 * @param $display_day
 * @param $display_month
 * @param $display_year
 * @param $category
 *
 * @return array
 * @deprecated 2.0.0
 *
 */
function sc_get_events_for_day( $display_day, $display_month, $display_year, $category ) {

	$args = array(
		'numberposts' => - 1,
		'post_type'   => sugar_calendar_get_event_post_type_id(),
		'post_status' => 'publish',
		'orderby'     => 'meta_value_num',
		'order'       => 'asc',
		'fields'      => 'ids',
		'meta_query'  => array(
			'relation' => 'AND',
			array(
				'key'     => 'sc_event_date',
				'value'   => gmmktime( 0, 0, 0, $display_month, $display_day, $display_year ),
				'compare' => '<=',
			),
			array(
				'key'     => 'sc_event_end_date',
				'value'   => gmmktime( 0, 0, 0, $display_month, $display_day, $display_year ),
				'compare' => '>=',
			),
		),
	);

	if ( ! is_null( $category ) ) {
		$tax          = sugar_calendar_get_calendar_taxonomy_id();
		$args[ $tax ] = $category;
	}

	$single = get_posts( apply_filters( 'sc_calendar_query_args', $args ) );

	$recurring_timestamp = gmmktime( 0, 0, 0, $display_month, $display_day, $display_year );
	$yearly              = sc_get_recurring_events( $recurring_timestamp, 'yearly', $category );
	$monthly             = sc_get_recurring_events( $recurring_timestamp, 'monthly', $category );
	$weekly              = sc_get_recurring_events( $recurring_timestamp, 'weekly', $category );

	$all_recurring = array_merge( $yearly, $monthly, $weekly );
	$recurring     = array();

	if ( ! empty( $all_recurring ) ) {
		foreach ( $all_recurring as $event_id ) {

			$stop_day = sc_get_recurring_stop_date( $event_id );

			if ( $stop_day && $recurring_timestamp > $stop_day ) {
				continue;
			}

			if ( in_array( $event_id, $recurring ) ) {
				continue;
			}

			$recurring[] = $event_id;
		}
	}

	$events = array_merge( $single, $recurring );

	// sort by sc_event_time_hour + sc_event_time_minute + sc_event_time_am_pm
	$events = sc_order_events_by_time( $events );

	return apply_filters( 'sc_get_events_for_day', $events, $display_day, $display_month, $display_year, $category );
}
