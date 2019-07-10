<?php
/**
 * Class Modules.
 *
 * Used in Core to type hint the $mod variable. For example, this way any calls to
 * \Smush\WP_Smush::get_instance()->core()->mod->settings will be typehinted as a call to Settings module.
 *
 * @package Smush\Core
 */

namespace Smush\Core;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Modules
 */
class Modules {

	/**
	 * Database module.
	 *
	 * @var Modules\DB
	 */
	public $db;

	/**
	 * Directory Smush module.
	 *
	 * @var Modules\Dir
	 */
	public $dir;

	/**
	 * Main Smush module.
	 *
	 * @var Modules\Smush
	 */
	public $smush;

	/**
	 * Backup module.
	 *
	 * @var Modules\Backup
	 */
	public $backup;

	/**
	 * PNG 2 JPG module.
	 *
	 * @var Modules\Png2jpg
	 */
	public $png2jpg;

	/**
	 * Resize module.
	 *
	 * @var Modules\Resize
	 */
	public $resize;

	/**
	 * CDN module.
	 *
	 * @var Modules\CDN
	 */
	public $cdn;

	/**
	 * Settings module.
	 *
	 * @var Settings
	 */
	public $settings;

	/**
	 * Image lazy load module.
	 *
	 * @since 3.2
	 *
	 * @var Modules\Lazy
	 */
	public $lazy;

	/**
	 * Modules constructor.
	 */
	public function __construct() {
		$this->db       = new Modules\DB();
		$this->dir      = new Modules\Dir();
		$this->smush    = new Modules\Smush();
		$this->backup   = new Modules\Backup();
		$this->png2jpg  = new Modules\Png2jpg();
		$this->resize   = new Modules\Resize();
		$this->settings = Settings::get_instance();

		$page_parser = new Modules\Helpers\Parser();
		$this->cdn   = new Modules\CDN( $page_parser );
		$this->lazy  = new Modules\Lazy( $page_parser );
	}

}
