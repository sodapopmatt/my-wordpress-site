<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Serves a downloadable .ics file for a single event via ?nnr_ics_event={id}.
 * No rewrite rules needed since the event CPT isn't publicly queryable anyway.
 */
class NNR_ICS {

	public function __construct() {
		add_action( 'template_redirect', array( $this, 'maybe_serve_ics' ) );
	}

	public static function get_url( $post_id ) {
		return add_query_arg( 'nnr_ics_event', $post_id, home_url( '/' ) );
	}

	/**
	 * Builds a "Google Calendar > Add event" link for direct one-click adding,
	 * as an alternative to downloading the .ics file (which still covers
	 * Apple Calendar, Outlook, and importing into Google Calendar manually).
	 */
	public static function get_google_url( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return '';
		}

		$start_date = get_post_meta( $post_id, '_nnr_start_date', true );
		if ( ! $start_date ) {
			return '';
		}

		$start_time  = get_post_meta( $post_id, '_nnr_start_time', true );
		$end_date    = get_post_meta( $post_id, '_nnr_end_date', true );
		$end_time    = get_post_meta( $post_id, '_nnr_end_time', true );
		$recurring   = '1' === get_post_meta( $post_id, '_nnr_recurring_weekly', true );
		$venue       = get_post_meta( $post_id, '_nnr_venue', true );
		$address     = get_post_meta( $post_id, '_nnr_address', true );
		$location    = trim( $venue . ( $venue && $address ? ', ' : '' ) . $address );
		$description = wp_strip_all_tags( get_the_excerpt( $post ) );

		if ( $start_time ) {
			$tz       = wp_timezone();
			$start_dt = DateTime::createFromFormat( 'Y-m-d H:i', $start_date . ' ' . $start_time, $tz );
			if ( $end_date ) {
				$end_dt = DateTime::createFromFormat( 'Y-m-d H:i', $end_date . ' ' . ( $end_time ? $end_time : $start_time ), $tz );
			} else {
				$end_dt = clone $start_dt;
				$end_dt->modify( '+1 hour' );
			}
			$start_dt->setTimezone( new DateTimeZone( 'UTC' ) );
			$end_dt->setTimezone( new DateTimeZone( 'UTC' ) );
			$dates = $start_dt->format( 'Ymd\THis\Z' ) . '/' . $end_dt->format( 'Ymd\THis\Z' );
		} else {
			$dtend_date = $end_date ? $end_date : $start_date;
			$next       = DateTime::createFromFormat( 'Y-m-d', $dtend_date );
			$next_str   = $next ? ( $next->modify( '+1 day' )->format( 'Ymd' ) ) : str_replace( '-', '', $dtend_date );
			$dates      = str_replace( '-', '', $start_date ) . '/' . $next_str;
		}

		$args = array(
			'action'   => 'TEMPLATE',
			'text'     => $post->post_title,
			'dates'    => $dates,
			'details'  => $description,
			'location' => $location,
		);

		if ( $recurring ) {
			$args['recur'] = 'RRULE:FREQ=WEEKLY';
		}

		return 'https://calendar.google.com/calendar/render?' . http_build_query( $args );
	}

	public function maybe_serve_ics() {
		if ( empty( $_GET['nnr_ics_event'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$post_id = absint( $_GET['nnr_ics_event'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post    = get_post( $post_id );

		if ( ! $post || 'event' !== $post->post_type || 'publish' !== $post->post_status ) {
			wp_die( esc_html__( 'Event not found.', 'nnr-events' ), '', array( 'response' => 404 ) );
		}

		$ics = $this->build_ics( $post );

		nocache_headers();
		header( 'Content-Type: text/calendar; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $post->post_title ) . '.ics"' );
		echo $ics; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	private function build_ics( $post ) {
		$post_id       = $post->ID;
		$start_date    = get_post_meta( $post_id, '_nnr_start_date', true );
		$start_time    = get_post_meta( $post_id, '_nnr_start_time', true );
		$end_date      = get_post_meta( $post_id, '_nnr_end_date', true );
		$end_time      = get_post_meta( $post_id, '_nnr_end_time', true );
		$recurring     = '1' === get_post_meta( $post_id, '_nnr_recurring_weekly', true );
		$venue         = get_post_meta( $post_id, '_nnr_venue', true );
		$address       = get_post_meta( $post_id, '_nnr_address', true );
		$ticket_url    = get_post_meta( $post_id, '_nnr_ticket_url', true );
		$location      = trim( $venue . ( $venue && $address ? ', ' : '' ) . $address );
		$description   = wp_strip_all_tags( get_the_excerpt( $post ) );

		$tz      = wp_timezone();
		$tz_name = $tz->getName();
		// Olson names ("America/Los_Angeles") are safe as TZID; a raw UTC
		// offset ("+00:00") isn't understood by most calendar apps as a
		// TZID, so convert those to plain UTC instead.
		$use_utc = ! preg_match( '#^[A-Za-z]+/[A-Za-z_]+#', $tz_name );
		$all_day = ! $start_time;

		if ( ! $all_day ) {
			$start_dt = DateTime::createFromFormat( 'Y-m-d H:i', $start_date . ' ' . $start_time, $tz );
			if ( $end_date ) {
				$end_dt = DateTime::createFromFormat( 'Y-m-d H:i', $end_date . ' ' . ( $end_time ? $end_time : $start_time ), $tz );
			} else {
				$end_dt = clone $start_dt;
				$end_dt->modify( '+1 hour' );
			}

			if ( $use_utc ) {
				$start_dt->setTimezone( new DateTimeZone( 'UTC' ) );
				$end_dt->setTimezone( new DateTimeZone( 'UTC' ) );
			}
		}

		$lines   = array();
		$lines[] = 'BEGIN:VCALENDAR';
		$lines[] = 'VERSION:2.0';
		$lines[] = 'PRODID:-//NNR Events//EN';
		$lines[] = 'CALSCALE:GREGORIAN';
		$lines[] = 'BEGIN:VEVENT';
		$lines[] = 'UID:nnr-event-' . $post_id . '@' . wp_parse_url( home_url(), PHP_URL_HOST );
		$lines[] = 'DTSTAMP:' . gmdate( 'Ymd\THis\Z' );

		if ( $all_day ) {
			$dtend_date = $end_date ? $end_date : $start_date;
			$lines[]    = 'DTSTART;VALUE=DATE:' . str_replace( '-', '', $start_date );
			$lines[]    = 'DTEND;VALUE=DATE:' . str_replace( '-', '', $this->next_day( $dtend_date ) );
		} elseif ( $use_utc ) {
			$lines[] = 'DTSTART:' . $start_dt->format( 'Ymd\THis\Z' );
			$lines[] = 'DTEND:' . $end_dt->format( 'Ymd\THis\Z' );
		} else {
			$lines[] = 'DTSTART;TZID=' . $tz_name . ':' . $start_dt->format( 'Ymd\THis' );
			$lines[] = 'DTEND;TZID=' . $tz_name . ':' . $end_dt->format( 'Ymd\THis' );
		}

		if ( $recurring ) {
			$lines[] = 'RRULE:FREQ=WEEKLY';
		}

		$lines[] = 'SUMMARY:' . $this->escape_text( $post->post_title );

		if ( $location ) {
			$lines[] = 'LOCATION:' . $this->escape_text( $location );
		}

		if ( $description ) {
			$lines[] = 'DESCRIPTION:' . $this->escape_text( $description );
		}

		if ( $ticket_url ) {
			$lines[] = 'URL:' . $this->escape_text( $ticket_url );
		}

		$lines[] = 'END:VEVENT';
		$lines[] = 'END:VCALENDAR';

		return implode( "\r\n", $lines ) . "\r\n";
	}

	private function next_day( $date ) {
		$dt = DateTime::createFromFormat( 'Y-m-d', $date );
		if ( ! $dt ) {
			return $date;
		}
		$dt->modify( '+1 day' );
		return $dt->format( 'Y-m-d' );
	}

	/**
	 * Escape text per RFC 5545 (backslash, semicolon, comma, then newlines).
	 */
	private function escape_text( $text ) {
		$text = str_replace( array( '\\', ';', ',' ), array( '\\\\', '\\;', '\\,' ), $text );
		return str_replace( array( "\r\n", "\n" ), '\\n', $text );
	}
}
