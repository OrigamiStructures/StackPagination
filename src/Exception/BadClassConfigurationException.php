<?php
namespace StackPagination\Exception;

use Cake\Database\Exception;

/**
 * Description of BadClassConfigurationException
 *
 * @author dondrake
 */
class BadClassConfigurationException extends Exception
{

    public function __construct($message, $code = 500, $previous = null)
    {

        // Clear the ArtworkStack-specific caches here
		/**
		 * There should be a storage structure, possibly a config file, that
		 * keeps a record of all the cache key/configs that are involved.
		 * That will give a simple code-free reference document to guide
		 * this cache management process.
		 *
		 * Or perhaps better, the exception should trigger some Event that
		 * Cache Management Listener classes respond to.
		 */

        parent::__construct($message, $code, $previous);
    }

}
