<?php
/**
 * Autoloader for the plugin.
 *
 * @author     RedCastor <team@redcastor.io>
 */
class Cache_Enabler_Autoloader {

    /**
     * Path to the includes directory.
     *
     * @var string
     */
    private $include_path = '';



    /**
     * The Constructor.
     */
    public function __construct( $path ) {
        if ( function_exists( "__autoload" ) ) {
            spl_autoload_register( "__autoload" );
        }

        spl_autoload_register( array( $this, 'autoload' ) );

        $this->include_path = untrailingslashit( $path ) . '/';
    }


    /**
     * Take a class name and turn it into a file name.
     *
     * @param  string $class
     * @return string
     */
    private function get_file_name_from_class( $class ) {

        $class = strtolower($class);

        return $class . '.php';
    }

    /**
     * Require once a class file.
     *
     * @param  string $path
     * @return bool successful or not
     */
    private function load_file( $path ) {
        if ( $path && is_readable( $path ) ) {
            include_once( $path );
            return true;
        }

        return false;
    }

    /**
     * Auto-load.
     *
     * @param string $class
     */
    public function autoload( $class ) {

        $file  = $this->get_file_name_from_class( $class );

        $file_path = $this->include_path . $file;

        //For Namespace class replace backslash with DIRECTORY_SEPARATOR
        $file_path = str_replace('\\', DIRECTORY_SEPARATOR, $file_path);
        //Path class replace slash with DIRECTORY_SEPARATOR
        $file_path = str_replace('/', DIRECTORY_SEPARATOR, $file_path);

        $this->load_file( $file_path );
    }
}

