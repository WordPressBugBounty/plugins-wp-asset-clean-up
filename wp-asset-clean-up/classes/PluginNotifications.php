<?php
/** @noinspection MultipleReturnStatementsInspection */

namespace WpAssetCleanUp;

/**
 * Class PluginNotifications
 * @package WpAssetCleanUp
 */
class PluginNotifications
{
	/**
	 *
	 */
	const JSON_URL = '';

	/**
	 * PluginNotifications constructor.
	 */
	public function __construct()
	{

	}

	/**
	 * @return array|\WP_Error
	 */
	public function getJsonData()
	{
		$response = wp_remote_get( self::JSON_URL );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );

		if ( empty( $body ) ) {
			return array();
		}

		return $this->validateJsonData( json_decode( $body, true ) );
	}

	/**
	 * @param $entries
	 *
	 * @return array
	 */
	public function validateJsonData($entries)
	{
		$filteredEntries = array();

		if ( empty( $entries ) || ! is_array( $entries ) ) {
			return array();
		}

		foreach ( $entries as $entry ) {
			$validEntry = $this->validateEntry( $entry );

			if ( ! empty( $validEntry ) ) {
				$filteredEntries[] = $validEntry;
			}
		}

		return $filteredEntries;
	}

	/**
	 * @param $entry
	 *
	 * @return bool
	 */
	public function validateEntry($entry)
	{
		if ( ! (isset($entry['content']) && $entry['content']) ) {
			return false;
		}

		if ( isset( $entry['end'] ) && $entry['end'] && time() > strtotime( $entry['end'] ) ) {
			return false; // expired, do not show it
		}

		return true; // finally, return true if there are no issues with this notification
	}
}
